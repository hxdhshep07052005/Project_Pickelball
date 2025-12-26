"""
Real-time Live Action Prediction from Webcam
Uses trained LSTM model to predict DriveBackhand or DriveForehand in real-time
"""

import os
import sys
import json
import base64
import argparse
import numpy as np
import cv2
import torch
import torch.nn as nn
from ultralytics import YOLO

# Model architecture (same as predict_action.py)
class KeypointLSTMClassifier(nn.Module):
    def __init__(self, feature_dim=51, hidden=128, num_layers=1,
                 num_classes=2, dropout=0.3):
        super().__init__()
        self.lstm = nn.LSTM(
            feature_dim,
            hidden,
            num_layers=num_layers,
            batch_first=True,
            dropout=dropout if num_layers > 1 else 0
        )
        self.dropout = nn.Dropout(dropout)
        self.ln = nn.LayerNorm(hidden)
        self.classifier = nn.Linear(hidden, num_classes)

    def forward(self, x):
        _, (h, _) = self.lstm(x)
        h = h[-1]
        h = self.dropout(h)
        h = self.ln(h)
        return self.classifier(h)


class KeypointExtractor:
    """Extract human pose keypoints using YOLO11 pose model"""
    def __init__(self, device='cpu'):
        self.model = YOLO('yolo11n-pose.pt')
        self.device = device
        self.feature_dim = 51

    def extract(self, frame):
        try:
            results = self.model(frame, imgsz=640, device=self.device, verbose=False)
            if len(results) == 0 or not hasattr(results[0], 'keypoints'):
                return np.zeros(self.feature_dim, dtype=np.float32)
            kpts = results[0].keypoints
            if kpts is None or len(kpts) == 0:
                return np.zeros(self.feature_dim, dtype=np.float32)
            kpt_data = kpts.data[0].cpu().numpy()
            h, w = frame.shape[:2]
            kpt_data[:, 0] /= w
            kpt_data[:, 1] /= h
            features = kpt_data.flatten()
            return features.astype(np.float32)
        except Exception as e:
            print(f"Keypoint extraction failed: {e}", file=sys.stderr)
            return np.zeros(self.feature_dim, dtype=np.float32)


class LiveActionPredictor:
    """Real-time action predictor with frame buffer"""
    def __init__(self, model_path, device='cpu', buffer_size=16, min_frames=8):
        self.buffer_size = buffer_size
        self.min_frames = min_frames  # Minimum frames needed for prediction
        self.frame_buffer = []
        self.device = device
        
        # Initialize keypoint extractor
        self.keypoint_extractor = KeypointExtractor(device=device)
        
        # Load model
        self.model = KeypointLSTMClassifier(
            feature_dim=51,
            hidden=128,
            num_layers=1,
            num_classes=2,
            dropout=0.3
        )
        self.model.load_state_dict(torch.load(model_path, map_location=device))
        self.model.eval()
        self.model.to(device)
        
        self.class_names = ['DriveBackhand', 'DriveForehand']
    
    def add_frame(self, frame):
        """Add a frame to the buffer and extract keypoints"""
        kpt_features = self.keypoint_extractor.extract(frame)
        self.frame_buffer.append(kpt_features)
        
        # Keep only last buffer_size frames
        if len(self.frame_buffer) > self.buffer_size:
            self.frame_buffer.pop(0)
    
    def predict(self):
        """Predict action from current frame buffer - works with partial data"""
        if len(self.frame_buffer) < self.min_frames:
            # Not enough frames yet, return None
            return None
        
        # Use available frames (pad if needed)
        available_frames = len(self.frame_buffer)
        if available_frames < self.buffer_size:
            # Pad with last frame to reach buffer_size
            features = list(self.frame_buffer)
            last_frame = features[-1] if features else np.zeros(51, dtype=np.float32)
            while len(features) < self.buffer_size:
                features.append(last_frame.copy())
        else:
            # Use last buffer_size frames
            features = self.frame_buffer[-self.buffer_size:]
        
        features_array = np.array(features, dtype=np.float32)
        features_tensor = torch.tensor(features_array).unsqueeze(0).to(self.device)
        
        # Predict
        with torch.no_grad():
            output = self.model(features_tensor)
            probabilities = torch.softmax(output, dim=1)
            predicted_class = output.argmax(1).item()
            confidence = probabilities[0][predicted_class].item()
        
        predicted_name = self.class_names[predicted_class]
        prob_backhand = probabilities[0][0].item()
        prob_forehand = probabilities[0][1].item()
        
        # Adjust confidence based on available frames
        if available_frames < self.buffer_size:
            # Reduce confidence if we don't have full buffer
            frame_ratio = available_frames / self.buffer_size
            confidence = confidence * (0.5 + 0.5 * frame_ratio)  # Scale between 50% and 100%
        
        return {
            "predicted_class": predicted_name,
            "class_index": predicted_class,
            "confidence": round(confidence * 100, 2),
            "probabilities": {
                "DriveBackhand": round(prob_backhand * 100, 2),
                "DriveForehand": round(prob_forehand * 100, 2)
            },
            "frames_used": available_frames
        }
    
    def reset(self):
        """Reset frame buffer"""
        self.frame_buffer = []


# Global predictor instance (reused across calls)
_global_predictor = None

def process_frame(frame_data, model_path, device='cpu'):
    """
    Process a single frame (base64 encoded image) and return prediction
    
    Args:
        frame_data: Base64 encoded image string or file path
        model_path: Path to model file
        device: 'cpu' or 'cuda'
    
    Returns:
        dict with prediction results
    """
    global _global_predictor
    
    try:
        # Decode base64 image
        if isinstance(frame_data, str):
            # Check if it's a file path
            if os.path.exists(frame_data):
                frame = cv2.imread(frame_data)
            else:
                # Remove data URL prefix if present
                if ',' in frame_data:
                    frame_data = frame_data.split(',')[1]
                image_data = base64.b64decode(frame_data)
                nparr = np.frombuffer(image_data, np.uint8)
                frame = cv2.imdecode(nparr, cv2.IMREAD_COLOR)
        else:
            frame = frame_data
        
        if frame is None:
            return {
                "success": False,
                "error": "Failed to decode image"
            }
        
        # Initialize predictor (reuse instance if possible)
        if _global_predictor is None:
            _global_predictor = LiveActionPredictor(model_path, device=device)
        
        # Add frame to buffer
        _global_predictor.add_frame(frame)
        
        # Predict
        result = _global_predictor.predict()
        
        if result is None:
            # Return a default prediction if not enough frames
            return {
                "success": True,
                "status": "ready",
                "predicted_class": "Waiting...",
                "confidence": 0,
                "probabilities": {
                    "DriveBackhand": 50.0,
                    "DriveForehand": 50.0
                },
                "frames_used": len(_global_predictor.frame_buffer)
            }
        
        return {
            "success": True,
            "status": "ready",
            **result
        }
        
    except Exception as e:
        import traceback
        return {
            "success": False,
            "error": str(e),
            "traceback": traceback.format_exc()
        }


def main():
    parser = argparse.ArgumentParser(description="Real-time action prediction from frame")
    parser.add_argument("--frame", help="Base64 encoded frame data")
    parser.add_argument("--frame_file", help="Path to frame image file")
    parser.add_argument("--model", help="Path to model file")
    parser.add_argument("--device", default="cpu", choices=["cpu", "cuda"], help="Device to use")
    parser.add_argument("--reset", action="store_true", help="Reset frame buffer")
    
    args = parser.parse_args()
    
    # Get script directory
    script_dir = os.path.dirname(os.path.abspath(__file__))
    
    # Find model file
    if args.model:
        model_path = os.path.abspath(args.model)
    else:
        model_path = os.path.join(script_dir, "Model_2dongtac.pth")
    
    if not os.path.exists(model_path):
        result = {
            "success": False,
            "error": f"Model file not found: {model_path}"
        }
        print(json.dumps(result, indent=2))
        sys.exit(1)
    
    # Reset buffer if requested
    if args.reset:
        global _global_predictor
        if _global_predictor is not None:
            _global_predictor.reset()
        result = {
            "success": True,
            "message": "Buffer reset"
        }
        print(json.dumps(result, indent=2))
        return
    
    # Get frame data
    frame_data = None
    if args.frame:
        frame_data = args.frame
    elif args.frame_file:
        if os.path.exists(args.frame_file):
            # Read the file - it contains base64 encoded data
            with open(args.frame_file, 'r', encoding='utf-8') as f:
                frame_data = f.read().strip()
        else:
            result = {
                "success": False,
                "error": f"Frame file not found: {args.frame_file}"
            }
            print(json.dumps(result, indent=2))
            sys.exit(1)
    else:
        result = {
            "success": False,
            "error": "No frame data provided. Use --frame or --frame_file"
        }
        print(json.dumps(result, indent=2))
        sys.exit(1)
    
    # Process frame
    result = process_frame(frame_data, model_path, device=args.device)
    print(json.dumps(result, indent=2))
    
    if not result.get("success"):
        sys.exit(1)


if __name__ == "__main__":
    main()

