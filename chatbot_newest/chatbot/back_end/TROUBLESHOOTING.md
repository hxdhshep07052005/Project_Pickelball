# Troubleshooting Guide

## Server Crashes During Vision LLM Loading

If your server crashes during Vision LLM model loading (at "Step 2/3: Loading model"), try these solutions:

### Solution 1: Disable Vision LLM (Quick Fix)

Set the environment variable before starting the server:

**Windows (PowerShell):**
```powershell
$env:ENABLE_VISION_LLM="false"
python main.py
```

**Windows (CMD):**
```cmd
set ENABLE_VISION_LLM=false
python main.py
```

**Linux/Mac:**
```bash
export ENABLE_VISION_LLM=false
python main.py
```

Or modify `back_end/main.py` line 19:
```python
ENABLE_VISION_LLM = os.getenv("ENABLE_VISION_LLM", "false").lower() == "true"  # Changed default to False
```

### Solution 2: Check System Resources

The Vision LLM model requires:
- **Disk Space**: ~1.4GB for model download
- **RAM**: ~2-4GB during loading
- **Internet**: Stable connection for first-time download

**Check available resources:**
- Windows: Open Task Manager → Performance tab
- Linux: `free -h` and `df -h`

### Solution 3: Install/Update Dependencies

Make sure you have the latest versions:

```bash
pip install --upgrade transformers torch torchvision
```

### Solution 4: Use CPU-Only PyTorch (If GPU Issues)

If you're having CUDA/GPU issues:

```bash
pip uninstall torch torchvision
pip install torch torchvision --index-url https://download.pytorch.org/whl/cpu
```

### Solution 5: Pre-download the Model

Download the model manually before starting the server:

```python
from transformers import BlipProcessor, BlipForConditionalGeneration

# This will download the model to cache
processor = BlipProcessor.from_pretrained("Salesforce/blip-image-captioning-large")
model = BlipForConditionalGeneration.from_pretrained("Salesforce/blip-image-captioning-large")
print("Model downloaded successfully!")
```

Then start the server - it will use the cached model.

### Solution 6: Check Error Logs

Look for specific error messages in the console. Common issues:

- **"Connection timeout"**: Network/firewall issue
- **"Out of memory"**: Not enough RAM
- **"No space left"**: Disk full
- **"CUDA error"**: GPU/CUDA configuration issue (will fall back to CPU)

### Solution 7: Run Without Vision LLM

The system works perfectly fine without Vision LLM! It will use:
- MediaPipe pose estimation (still works)
- Rule-based evaluation (still works)
- LLM feedback generation (still works)

Only the Vision LLM visual analysis will be skipped.

## Skill Classifier Issues

If skill classification fails:

1. **Model file missing**: Ensure `Model_2dongtac.pth` is in `back_end/` folder
2. **Model architecture mismatch**: See `SKILL_CLASSIFIER_README.md` for details
3. **Classification errors are logged but don't block uploads**: This is intentional

## General Server Issues

### Port Already in Use

If port 8000 is already in use:

```python
# In main.py, change port:
uvicorn.run(app, host="0.0.0.0", port=8001)  # Use different port
```

### Import Errors

Make sure you're running from the correct directory:

```bash
cd back_end
python main.py
```

### Dependencies Missing

Install all dependencies:

```bash
pip install -r requirements.txt
```

## Getting Help

If issues persist:

1. Check the full error traceback in console
2. Note which step fails (Vision LLM loading, skill classification, etc.)
3. Check system resources (RAM, disk space)
4. Try disabling Vision LLM first to isolate the issue















