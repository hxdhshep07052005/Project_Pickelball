"""
Action Video Prediction Script
Uses trained LSTM model to predict DriveBackhand or DriveForehand from video
"""

import os
import sys
import json
import argparse
import numpy as np
import cv2
import torch
import torch.nn as nn
from pathlib import Path
from ultralytics import YOLO
from tqdm import tqdm

# Model architecture
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


class AdaptiveKeyframeExtractor:
    """Extract 16 keyframes from video"""
    def __init__(self, target_frames=16):
        self.target_frames = target_frames

    def extract_frame_indices(self, video_path):
        cap = cv2.VideoCapture(video_path)
        total_frames = int(cap.get(cv2.CAP_PROP_FRAME_COUNT))
        fps = cap.get(cv2.CAP_PROP_FPS)
        duration = total_frames / fps if fps > 0 else 0
        cap.release()

        if duration > 0:
            indices = list(range(total_frames))
            if len(indices) == 0:
                return []
            if len(indices) < self.target_frames:
                repeat_factor = self.target_frames / len(indices)
                new_indices = []
                for i in indices:
                    n_repeats = int(np.ceil(repeat_factor))
                    new_indices.extend([i] * n_repeats)
                indices = new_indices[:self.target_frames]
        return indices[:self.target_frames]


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


def predict_video(video_path, model_path, device='cpu'):
    """
    Predict action class from video
    
    Args:
        video_path: Path to video file
        model_path: Path to model .pth file
        device: 'cpu' or 'cuda'
    
    Returns:
        dict with prediction results
    """
    try:
        # Initialize components
        keyframe_extractor = AdaptiveKeyframeExtractor(target_frames=16)
        keypoint_extractor = KeypointExtractor(device=device)
        
        # Extract keyframes
        frame_indices = keyframe_extractor.extract_frame_indices(video_path)
        if len(frame_indices) == 0:
            return {
                "success": False,
                "error": "No frames extracted from video"
            }
        
        # Extract keypoints from frames
        cap = cv2.VideoCapture(video_path)
        features = []
        
        for idx in frame_indices:
            cap.set(cv2.CAP_PROP_POS_FRAMES, idx)
            ret, frame = cap.read()
            if ret:
                kpt_features = keypoint_extractor.extract(frame)
                features.append(kpt_features)
            else:
                # Use zeros if frame read fails
                features.append(np.zeros(51, dtype=np.float32))
        
        cap.release()
        
        if len(features) == 0:
            return {
                "success": False,
                "error": "Failed to extract features from video"
            }
        
        # Pad or truncate to 16 frames
        target_frames = 16
        if len(features) < target_frames:
            # Repeat last frame
            last_frame = features[-1]
            while len(features) < target_frames:
                features.append(last_frame.copy())
        else:
            features = features[:target_frames]
        
        # Convert to tensor
        features_array = np.array(features, dtype=np.float32)
        features_tensor = torch.tensor(features_array).unsqueeze(0).to(device)
        
        # Load model
        model = KeypointLSTMClassifier(
            feature_dim=51,
            hidden=128,
            num_layers=1,
            num_classes=2,
            dropout=0.3
        )
        
        model.load_state_dict(torch.load(model_path, map_location=device))
        model.eval()
        model.to(device)
        
        # Predict
        with torch.no_grad():
            output = model(features_tensor)
            probabilities = torch.softmax(output, dim=1)
            predicted_class = output.argmax(1).item()
            confidence = probabilities[0][predicted_class].item()
        
        # Class names
        class_names = ['DriveBackhand', 'DriveForehand']
        predicted_name = class_names[predicted_class]
        
        # Get probabilities for both classes
        prob_backhand = probabilities[0][0].item()
        prob_forehand = probabilities[0][1].item()
        
        return {
            "success": True,
            "predicted_class": predicted_name,
            "class_index": predicted_class,
            "confidence": round(confidence * 100, 2),
            "probabilities": {
                "DriveBackhand": round(prob_backhand * 100, 2),
                "DriveForehand": round(prob_forehand * 100, 2)
            },
            "frames_processed": len(features)
        }
        
    except Exception as e:
        import traceback
        return {
            "success": False,
            "error": str(e),
            "traceback": traceback.format_exc()
        }


def main():
    parser = argparse.ArgumentParser(description="Predict action from video")
    parser.add_argument("video_path", help="Path to video file")
    parser.add_argument("--model", help="Path to model file")
    parser.add_argument("--device", default="cpu", choices=["cpu", "cuda"], help="Device to use")
    
    args = parser.parse_args()
    
    # Get absolute paths
    script_dir = os.path.dirname(os.path.abspath(__file__))
    video_path = os.path.abspath(args.video_path)
    
    # Find model file
    if args.model:
        model_path = os.path.abspath(args.model)
    else:
        # Default: look in script directory
        model_path = os.path.join(script_dir, "Model_2dongtac.pth")
    
    if not os.path.exists(video_path):
        result = {
            "success": False,
            "error": f"Video file not found: {video_path}"
        }
        print(json.dumps(result, indent=2))
        sys.exit(1)
    
    if not os.path.exists(model_path):
        result = {
            "success": False,
            "error": f"Model file not found: {model_path}"
        }
        print(json.dumps(result, indent=2))
        sys.exit(1)
    
    # Predict
    result = predict_video(video_path, model_path, device=args.device)
    print(json.dumps(result, indent=2))
    
    if not result.get("success"):
        sys.exit(1)


if __name__ == "__main__":
    main()

