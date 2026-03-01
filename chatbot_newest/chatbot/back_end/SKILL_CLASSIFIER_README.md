# Skill Classifier Integration

## Overview

The skill classifier module (`skill_classifier.py`) integrates the `Model_2dongtac.pth` model to automatically detect whether an uploaded video is a forehand or backhand drive. This validation happens during video upload to ensure the user's selected skill matches the detected skill.

## How It Works

1. **Video Upload**: User uploads a video and selects a skill (forehand or backhand)
2. **Skill Detection**: The model analyzes the video and predicts the skill
3. **Validation**: If the detected skill doesn't match the selected skill, the upload is rejected with an error message
4. **Pipeline Continuation**: If validation passes, the video proceeds to the normal analysis pipeline

## Model Architecture

The current implementation attempts to automatically infer the model architecture from the saved state dict. It tries:

1. **ResNet18-based architecture** (most common for image/video classification)
2. **Simple CNN architecture** (fallback)

### If Model Loading Fails

If you encounter errors loading the model, you may need to specify the exact model architecture. The model might be:

- A different ResNet variant (ResNet34, ResNet50, etc.)
- A custom CNN architecture
- A video-based model (3D CNN, RNN, etc.)
- A transformer-based model

### To Fix Model Architecture Issues

Edit `back_end/skill_classifier.py` and modify the `_create_model_from_state_dict` method to match your model's architecture. For example:

```python
def _create_model_from_state_dict(self, state_dict: dict) -> nn.Module:
    # Replace with your actual model architecture
    from torchvision.models import resnet34  # or your custom model
    model = resnet34(pretrained=False)
    model.fc = nn.Linear(model.fc.in_features, 2)  # 2 classes
    return model
```

Or if you have a custom model class:

```python
def _create_model_from_state_dict(self, state_dict: dict) -> nn.Module:
    from your_model_module import YourCustomModel
    model = YourCustomModel(num_classes=2)
    return model
```

## Input Format

The current implementation:
- Extracts 16 frames evenly spaced across the video
- Resizes each frame to 224x224 pixels
- Uses the average frame for classification (single-frame input)

### If Your Model Expects Different Input

If your model expects:
- **Video sequence** (multiple frames): Modify `preprocess_video()` to return a sequence tensor
- **Different frame size**: Change the resize dimensions in `preprocess_video()`
- **Different preprocessing**: Add normalization, augmentation, etc. in `preprocess_video()`

## Class Mapping

The model outputs 2 classes:
- **Class 0**: `drive_forehand`
- **Class 1**: `drive_two_backhand`

If your model uses different class indices, update the `skill_map` in the `classify_video()` method.

## Configuration

The model is automatically loaded on first use. To disable skill validation:

1. Set `SKILL_CLASSIFIER_AVAILABLE = False` in `back_end/api/upload_video.py`
2. Or handle import errors gracefully (already implemented)

## Testing

To test the skill classifier independently:

```python
from skill_classifier import classify_video_skill

video_path = "path/to/your/video.mp4"
predicted_skill, confidence = classify_video_skill(video_path)
print(f"Predicted: {predicted_skill} (confidence: {confidence:.2%})")
```

## Error Handling

- If the model file is missing, the system continues without validation (logs a warning)
- If classification fails, the upload continues (logs a warning)
- If skill mismatch is detected, upload is rejected with a clear error message

## Performance

- Model loading: ~1-5 seconds (first time only, then cached)
- Video classification: ~0.5-2 seconds per video
- Uses GPU if available, falls back to CPU

## Troubleshooting

### "Could not infer model architecture"
- **Solution**: Specify the model architecture in `_create_model_from_state_dict()`

### "Input size mismatch"
- **Solution**: Adjust the input preprocessing in `preprocess_video()` to match your model's expected input size

### "CUDA out of memory"
- **Solution**: The model will automatically fall back to CPU, or reduce batch size if processing multiple videos

### Model predictions are incorrect
- **Solution**: Check class mapping, verify model was trained correctly, or adjust confidence threshold















