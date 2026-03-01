# Quick Start Guide

## If Server Hangs During Vision LLM Loading

If your server hangs at "Step 2/3: Loading model", this is normal on first run - the model is downloading (~1.4GB). However, if it hangs for more than 15 minutes, you can:

### Option 1: Disable Vision LLM (Recommended for First Test)

**Windows:**
```cmd
cd back_end
set ENABLE_VISION_LLM=false
python main.py
```

Or use the provided script:
```cmd
cd back_end
start_server_no_vision_llm.bat
```

**Linux/Mac:**
```bash
cd back_end
export ENABLE_VISION_LLM=false
python main.py
```

Or use the provided script:
```bash
cd back_end
chmod +x start_server_no_vision_llm.sh
./start_server_no_vision_llm.sh
```

### Option 2: Wait for Model Download

The model download can take 5-15 minutes depending on your internet speed. The server will continue running even if Vision LLM is still loading - you can use the API endpoints immediately.

### Option 3: Pre-download the Model

If you want to download the model separately before starting the server:

```python
# Run this once in Python:
from transformers import BlipProcessor, BlipForConditionalGeneration

print("Downloading Vision LLM model...")
processor = BlipProcessor.from_pretrained("Salesforce/blip-image-captioning-large")
model = BlipForConditionalGeneration.from_pretrained("Salesforce/blip-image-captioning-large")
print("Model downloaded! Now start the server normally.")
```

## What Works Without Vision LLM?

Everything! The system works perfectly without Vision LLM:
- ✅ Video upload and skill validation
- ✅ Frame extraction
- ✅ MediaPipe pose estimation
- ✅ Phase detection
- ✅ Rule-based evaluation
- ✅ LLM feedback generation

Only the Vision LLM visual analysis (stance, balance, etc.) is skipped, but the system still provides excellent feedback using pose data.

## Normal Server Startup

When the server starts successfully, you should see:

```
✅ Server started successfully!
INFO:     Uvicorn running on http://0.0.0.0:8000
```

You can then:
1. Open http://localhost:8000/docs for API documentation
2. Open the frontend (front_end/index.html) in your browser
3. Start uploading videos and getting feedback!

## Troubleshooting

See `TROUBLESHOOTING.md` for more detailed help.















