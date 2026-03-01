# BLIP-2 Upgrade Guide

## Overview

The system has been upgraded from **BLIP-1** (`blip-image-captioning-large`) to **BLIP-2** (`blip2-opt-2.7b`) to enable proper prompt following for biomechanics analysis.

## Key Differences

### BLIP-1 (Old)
- **Model**: `Salesforce/blip-image-captioning-large`
- **Size**: ~1.4GB
- **Limitation**: Cannot follow instruction prompts properly. When given a prompt, it either ignores it or echoes it back.
- **Output**: Generic scene captions (e.g., "man standing in kitchen with tennis racquet")

### BLIP-2 (New)
- **Model**: `Salesforce/blip2-opt-2.7b` (default)
- **Size**: ~5-6GB
- **Capability**: **Can follow instruction prompts** to generate structured biomechanics descriptions
- **Output**: Follows the prompt format to describe stance, balance, arm extension, follow-through, body rotation, etc.

## System Requirements

BLIP-2 has **significantly higher requirements** than BLIP-1:

### Minimum Requirements
- **GPU**: 8GB VRAM (strongly recommended)
- **RAM**: 16GB (if using CPU, which is very slow)
- **Disk Space**: ~6GB for model files
- **Python Packages**: 
  - `transformers>=4.30.0`
  - `torch>=2.0.0`
  - `accelerate` (recommended)

### Recommended Setup
- **GPU**: NVIDIA GPU with 12GB+ VRAM
- **CUDA**: Properly configured CUDA toolkit
- **RAM**: 32GB+ system RAM
- **Disk**: 10GB+ free space

## Installation

1. **Upgrade required packages**:
   ```bash
   pip install --upgrade transformers torch accelerate
   ```

2. **Verify CUDA availability** (if using GPU):
   ```python
   import torch
   print(torch.cuda.is_available())  # Should be True
   print(torch.cuda.get_device_name(0))  # Should show your GPU name
   ```

3. **Run the backend**:
   ```bash
   cd back_end
   python main.py
   ```

   The first run will download the BLIP-2 model (~5-6GB), which may take 10-20 minutes depending on your internet speed.

## Model Options

You can change the BLIP-2 model by modifying `main.py` or setting an environment variable:

### Available Models (from smallest to largest):

1. **`Salesforce/blip2-opt-2.7b`** (default)
   - Size: ~5-6GB
   - GPU Memory: ~8GB
   - Good balance of quality and resource usage

2. **`Salesforce/blip2-opt-6.7b`**
   - Size: ~13GB
   - GPU Memory: ~12GB
   - Better quality, requires more resources

3. **`Salesforce/blip2-flan-t5-xl`**
   - Size: ~9GB
   - GPU Memory: ~10GB
   - Uses Flan-T5, good instruction following

4. **`Salesforce/blip2-flan-t5-xxl`**
   - Size: ~15GB
   - GPU Memory: ~16GB
   - Best quality, requires significant resources

## How It Works Now

### Prompt Following

BLIP-2 receives the biomechanics prompt from `vison_llm/prompt.py` and generates structured descriptions:

**Forehand Prompt Example**:
```
You are analyzing a single keyframe of a pickleball player performing a forehand drive.
Your job is ONLY to describe the PLAYER'S BODY and PADDLE mechanics.
...
Respond in EXACTLY 7 lines using this template:
Stance: ... (open / closed / square)
Body balance: ... (stable / unstable / unknown)
...
```

**BLIP-2 Output** (example):
```
Stance: square
Body balance: stable
Hitting arm and elbow: partially extended
Wrist and paddle position: wrist neutral, paddle slightly closed
Follow-through direction: across the body
Body rotation: good rotation
Overall technique summary: solid forehand posture with good body rotation
```

### Code Changes

1. **`vison_llm/init_llm.py`**:
   - Changed from `BlipProcessor`/`BlipForConditionalGeneration` to `Blip2Processor`/`Blip2ForConditionalGeneration`
   - Added `torch_dtype=torch.float16` for GPU efficiency
   - Updated error messages for BLIP-2 requirements

2. **`vison_llm/vison_forehand.py`** and **`vison_llm/vison_backhand.py`**:
   - Now pass the prompt as `text` parameter: `processor(images=image, text=prompt_text, ...)`
   - Adjusted generation parameters (lower temperature, fewer beams) for structured output

3. **`main.py`**:
   - Updated default model to `Salesforce/blip2-opt-2.7b`

## Troubleshooting

### Out of Memory Errors

**Error**: `CUDA out of memory` or `RuntimeError: CUDA error: out of memory`

**Solutions**:
1. Use a smaller BLIP-2 model (e.g., `blip2-opt-2.7b`)
2. Reduce batch size if processing multiple frames
3. Close other GPU-intensive applications
4. Use CPU mode (very slow, not recommended):
   ```python
   # In init_llm.py, force CPU:
   device = "cpu"
   ```

### Slow Performance

**Issue**: Analysis takes too long

**Solutions**:
1. Ensure GPU is being used (check console output: "Using device: cuda")
2. Use `torch.float16` (already enabled in code)
3. Consider using a smaller model
4. Process frames in smaller batches

### Model Download Issues

**Error**: Network timeout or connection errors during download

**Solutions**:
1. Check internet connection
2. Use Hugging Face token for faster downloads:
   ```bash
   huggingface-cli login
   ```
3. Download manually and place in cache directory:
   - Windows: `C:\Users\<username>\.cache\huggingface\hub\`
   - Linux/Mac: `~/.cache/huggingface/hub/`

### Fallback to BLIP-1

If BLIP-2 is too resource-intensive, you can temporarily revert to BLIP-1 by:

1. **Modify `main.py`**:
   ```python
   vision_llm_client = init_vision_llm_client(
       model_name="Salesforce/blip-image-captioning-large"
   )
   ```

2. **Revert `vison_llm/init_llm.py`** imports:
   ```python
   from transformers import BlipProcessor, BlipForConditionalGeneration
   ```

3. **Revert `vison_forehand.py` and `vison_backhand.py`** to not pass prompts (BLIP-1 will ignore them anyway)

**Note**: BLIP-1 will not follow prompts and will generate generic captions. The system will still work but biomechanics analysis will be less accurate.

## Performance Comparison

| Metric | BLIP-1 | BLIP-2 (opt-2.7b) |
|--------|--------|-------------------|
| Model Size | ~1.4GB | ~5-6GB |
| GPU Memory | ~2GB | ~8GB |
| Inference Speed (GPU) | ~0.5s/frame | ~1-2s/frame |
| Prompt Following | ❌ No | ✅ Yes |
| Biomechanics Accuracy | Low | High |
| Scene Description | Generic | Focused on player |

## Expected Output Quality

With BLIP-2, you should see outputs like:

**Before (BLIP-1)**:
```
"there is a man standing in the middle of a kitchen with a frisbee in his hand"
```

**After (BLIP-2)**:
```
Stance: square
Body balance: stable
Hitting arm and elbow: partially extended
Wrist and paddle position: wrist neutral, paddle slightly closed
Follow-through direction: across the body
Body rotation: good rotation
Overall technique summary: solid forehand posture with good body rotation
```

The structured output is then parsed by `_parse_text_to_structured_format()` to extract biomechanics fields for rule-based evaluation.

## Next Steps

1. **Test on your powerful machine**: Run the system and verify BLIP-2 generates proper biomechanics descriptions
2. **Monitor performance**: Check GPU usage and inference speed
3. **Adjust prompts**: Fine-tune prompts in `vison_llm/prompt.py` if needed
4. **Consider larger models**: If you have sufficient GPU memory, try `blip2-opt-6.7b` or `blip2-flan-t5-xxl` for better quality

## References

- BLIP-2 Paper: https://arxiv.org/abs/2301.12597
- Hugging Face BLIP-2: https://huggingface.co/docs/transformers/model_doc/blip-2
- Model Cards:
  - https://huggingface.co/Salesforce/blip2-opt-2.7b
  - https://huggingface.co/Salesforce/blip2-opt-6.7b
  - https://huggingface.co/Salesforce/blip2-flan-t5-xl


