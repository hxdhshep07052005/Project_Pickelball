# ChatBox - Pickleball Video Analysis System

## Installation

### 1. Install Python Dependencies

```bash
pip install -r requirements.txt
```

Or install individually:
```bash
pip install opencv-python mediapipe numpy fastapi uvicorn pydantic
```

### 2. Verify Installation

Test if all packages are installed:
```bash
python -c "import cv2; import mediapipe; import numpy; print('All packages installed!')"
```

## Usage

### Standalone Script (for PHP integration)

```bash
python run_analysis.py <video_path> --skill drive_forehand
```

### FastAPI Server

```bash
python main.py
```

Server will start on `http://localhost:8000`

## Dependencies

- **opencv-python**: Video processing and frame extraction
- **mediapipe**: Pose estimation
- **numpy**: Numerical operations
- **fastapi**: API framework (for server mode)
- **uvicorn**: ASGI server (for server mode)
- **pydantic**: Data validation (for server mode)

## Troubleshooting

If you see "ModuleNotFoundError", install the missing package:
```bash
pip install <package_name>
```

