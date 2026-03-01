"""
Skill Classification Module
Loads and uses Model_2dongtac.pth to classify video as forehand or backhand drive.
"""
import os
import torch
import torch.nn as nn
import cv2
import numpy as np
from typing import Optional, Tuple
from PIL import Image
import torchvision.transforms as transforms


class SkillClassifier:
    """Classifies pickleball videos as forehand or backhand drive."""

    def __init__(self, model_path: str, device: Optional[str] = None):
        """
        Initialize the skill classifier.

        Args:
            model_path: Path to the .pth model file
            device: Device to run on ('cuda', 'cpu', or None for auto-detect)
        """
        self.model_path = model_path
        self.device = device if device else ("cuda" if torch.cuda.is_available() else "cpu")
        self.model = None
        self.loaded = False

    def load_model(self):
        """Load the PyTorch model from file."""
        if self.loaded:
            return

        if not os.path.exists(self.model_path):
            raise FileNotFoundError(f"Model file not found: {self.model_path}")

        try:
            print(f"Loading skill classification model from {self.model_path}...")
            print(f"Using device: {self.device}")

            try:
                checkpoint = torch.load(self.model_path, map_location=self.device)

                if isinstance(checkpoint, dict):
                    if 'model_state_dict' in checkpoint:
                        state_dict = checkpoint['model_state_dict']
                    elif 'state_dict' in checkpoint:
                        state_dict = checkpoint['state_dict']
                    else:
                        state_dict = checkpoint

                    self.model = self._create_model_from_state_dict(state_dict)
                    self.model.load_state_dict(state_dict)
                else:
                    self.model = checkpoint

            except Exception as e:
                print(f"Error loading model: {e}")
                print("Attempting alternative loading method...")
                state_dict = torch.load(self.model_path, map_location=self.device)
                self.model = self._create_model_from_state_dict(state_dict)
                self.model.load_state_dict(state_dict)

            self.model.to(self.device)
            self.model.eval()
            self.loaded = True
            print("✓ Skill classification model loaded successfully")

        except Exception as e:
            raise RuntimeError(f"Failed to load skill classification model: {str(e)}")

    def _create_model_from_state_dict(self, state_dict: dict) -> nn.Module:
        """
        Infer and create model architecture from state dict.
        This is a fallback - ideally you should know the model architecture.
        """
        first_key = list(state_dict.keys())[0]
        first_layer = state_dict[first_key]

        try:
            if 'conv1.weight' in state_dict or 'features.0.weight' in state_dict:
                from torchvision.models import resnet18
                model = resnet18(pretrained=False)
                model.fc = nn.Linear(model.fc.in_features, 2)  # 2 classes: forehand, backhand
                return model
        except:
            pass

        try:
            if len(first_layer.shape) == 4:  # Conv layer
                in_channels = first_layer.shape[1]
            else:
                in_channels = 3  # Default to RGB

            model = nn.Sequential(
                nn.Conv2d(in_channels, 32, kernel_size=3, padding=1),
                nn.ReLU(),
                nn.MaxPool2d(2),
                nn.Conv2d(32, 64, kernel_size=3, padding=1),
                nn.ReLU(),
                nn.MaxPool2d(2),
                nn.Conv2d(64, 128, kernel_size=3, padding=1),
                nn.ReLU(),
                nn.AdaptiveAvgPool2d((1, 1)),
                nn.Flatten(),
                nn.Linear(128, 2)  # 2 classes
            )
            return model
        except Exception as e:
            raise RuntimeError(
                f"Could not infer model architecture. Please provide model architecture. "
                f"Error: {str(e)}"
            )

    def preprocess_video(self, video_path: str, num_frames: int = 16) -> torch.Tensor:
        """
        Extract and preprocess frames from video.

        Args:
            video_path: Path to video file
            num_frames: Number of frames to extract

        Returns:
            Preprocessed tensor ready for model input
        """
        cap = cv2.VideoCapture(video_path)
        if not cap.isOpened():
            raise ValueError(f"Could not open video: {video_path}")

        frames = []
        total_frames = int(cap.get(cv2.CAP_PROP_FRAME_COUNT))

        frame_indices = np.linspace(0, total_frames - 1, num_frames, dtype=int)

        frame_idx = 0
        while cap.isOpened():
            ret, frame = cap.read()
            if not ret:
                break

            if frame_idx in frame_indices:
                frame_rgb = cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)
                frame_resized = cv2.resize(frame_rgb, (224, 224))
                frames.append(frame_resized)

            frame_idx += 1

        cap.release()

        if len(frames) == 0:
            raise ValueError("No frames extracted from video")

        while len(frames) < num_frames:
            frames.append(frames[-1])  # Repeat last frame
        frames = frames[:num_frames]

        frames_array = np.array(frames, dtype=np.float32) / 255.0

        avg_frame = np.mean(frames_array, axis=0)

        tensor = torch.from_numpy(avg_frame).permute(2, 0, 1).unsqueeze(0)

        return tensor

    def classify_video(self, video_path: str) -> Tuple[str, float]:
        """
        Classify a video as forehand or backhand drive.

        Args:
            video_path: Path to video file

        Returns:
            Tuple of (predicted_skill, confidence)
            predicted_skill: 'drive_forehand' or 'drive_two_backhand'
            confidence: Confidence score (0.0 to 1.0)
        """
        if not self.loaded:
            self.load_model()

        input_tensor = self.preprocess_video(video_path)
        input_tensor = input_tensor.to(self.device)

        with torch.no_grad():
            output = self.model(input_tensor)

            probabilities = torch.softmax(output, dim=1)
            confidence, predicted_class = torch.max(probabilities, dim=1)

            confidence_score = confidence.item()
            class_idx = predicted_class.item()

        skill_map = {
            0: "drive_forehand",
            1: "drive_two_backhand"
        }

        predicted_skill = skill_map.get(class_idx, "drive_forehand")

        return predicted_skill, confidence_score


_skill_classifier_instance = None


def get_skill_classifier(model_path: Optional[str] = None) -> SkillClassifier:
    """
    Get or create the global skill classifier instance.

    Args:
        model_path: Path to model file (only used on first call)

    Returns:
        SkillClassifier instance
    """
    global _skill_classifier_instance

    if _skill_classifier_instance is None:
        if model_path is None:
            model_path = os.path.join(os.path.dirname(__file__), "Model_2dongtac.pth")
            if not os.path.exists(model_path):
                base_dir = os.path.dirname(os.path.dirname(__file__))
                model_path = os.path.join(base_dir, "back_end", "Model_2dongtac.pth")

        _skill_classifier_instance = SkillClassifier(model_path)
        _skill_classifier_instance.load_model()

    return _skill_classifier_instance


def classify_video_skill(video_path: str, model_path: Optional[str] = None) -> Tuple[str, float]:
    """
    Convenience function to classify a video.

    Args:
        video_path: Path to video file
        model_path: Optional path to model file

    Returns:
        Tuple of (predicted_skill, confidence)
    """
    classifier = get_skill_classifier(model_path)
    return classifier.classify_video(video_path)
