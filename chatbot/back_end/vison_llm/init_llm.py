from transformers import BlipProcessor, BlipForConditionalGeneration
import torch
import os
import sys


def init_vision_llm_client(
    model_name: str = "Salesforce/blip-image-captioning-large",
    device: str = None
):
    """
    Initialize Hugging Face Vision-Language model for image analysis.
    
    Args:
        model_name: Hugging Face model identifier. Options:
                   - "Salesforce/blip-image-captioning-large" (default, good for descriptions)
                   - "Salesforce/blip2-opt-2.7b" (better but larger, requires more memory)
                   - "llava-hf/llava-1.5-7b-hf" (instruction-following, best for structured output)
        device: Device to run model on ("cuda", "cpu", or None for auto-detect)
    
    Returns:
        Tuple of (processor, model) for vision analysis
    """
    if device is None:
        device = "cuda" if torch.cuda.is_available() else "cpu"
    
    print(f"Loading Hugging Face model: {model_name}")
    print(f"Using device: {device}")
    print("Note: First-time download can take 5-10 minutes and ~1.4GB disk space")
    print("      If this hangs, check your internet connection or disk space.\n")
    
    try:
        print("Step 1/3: Loading processor...")
        processor = BlipProcessor.from_pretrained(model_name)
        print(" Processor loaded")
        sys.stdout.flush()
        
        print("Step 2/3: Loading model (this may take several minutes on first run)...")
        sys.stdout.flush()
        model = BlipForConditionalGeneration.from_pretrained(model_name)
        print("✓ Model loaded")
        sys.stdout.flush()
        
        print("Step 3/3: Moving model to device...")
        sys.stdout.flush()
        model.to(device)
        model.eval()
        print(f"✓ Model moved to {device}")
        sys.stdout.flush()
        
        print(f"\nModel loaded successfully on {device}")
        return {
            "processor": processor,
            "model": model,
            "device": device,
            "model_name": model_name
        }
    
    except KeyboardInterrupt:
        print("\nModel loading interrupted by user")
        raise
    except Exception as e:
        error_msg = str(e)
        print(f"\nFailed to load Hugging Face model: {error_msg}")
        if "Connection" in error_msg or "timeout" in error_msg.lower():
            print("   → This might be a network issue. Check your internet connection.")
        elif "disk" in error_msg.lower() or "space" in error_msg.lower():
            print("   → This might be a disk space issue. Free up some space.")
        elif "memory" in error_msg.lower():
            print("   → This might be a memory issue. Try closing other applications.")
        raise RuntimeError(f"Failed to load Hugging Face model: {error_msg}")
