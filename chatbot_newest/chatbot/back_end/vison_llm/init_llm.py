from transformers import Blip2Processor, Blip2ForConditionalGeneration
import torch
import os
import sys
import signal
import time
import threading


def init_vision_llm_client(
    model_name: str = "Salesforce/blip2-opt-2.7b",
    device: str = None
):
    """
    Initialize Hugging Face Vision-Language model (BLIP-2) for image analysis.

    BLIP-2 can follow instruction prompts to generate structured biomechanics descriptions.

    Args:
        model_name: Hugging Face model identifier. Options:
                   - "Salesforce/blip2-opt-2.7b" (default, good balance of quality and size)
                   - "Salesforce/blip2-opt-6.7b" (better quality, requires more GPU memory)
                   - "Salesforce/blip2-flan-t5-xl" (uses Flan-T5, good instruction following)
                   - "Salesforce/blip2-flan-t5-xxl" (largest, best quality, requires significant GPU)
        device: Device to run model on ("cuda", "cpu", or None for auto-detect)

    Returns:
        Dictionary with processor, model, device, and model_name for vision analysis
    """
    if device is None:
        device = "cuda" if torch.cuda.is_available() else "cpu"

    print(f"Loading BLIP-2 model: {model_name}")
    print(f"Using device: {device}")
    print("Note: BLIP-2 is larger than BLIP-1. First-time download can take 10-20 minutes")
    print("      and requires ~5-15GB disk space depending on model size.")
    print("      If this hangs, check your internet connection or disk space.")
    print("      BLIP-2 requires GPU (CUDA) for reasonable performance.\n")

    try:
        print("Step 1/3: Loading processor...")
        processor = Blip2Processor.from_pretrained(model_name)
        print("✓ Processor loaded")
        sys.stdout.flush()

        print("Step 2/3: Loading model (this may take several minutes on first run)...")
        print("   BLIP-2 models are large (~5-15GB). Download may take 10-20 minutes on slow connections.")
        print("   Please be patient - the download is in progress...")
        sys.stdout.flush()

        start_time = time.time()
        update_interval = 10  # Update every 10 seconds
        stop_updates = threading.Event()
        download_success = threading.Event()

        def show_progress():
            """Show periodic progress updates so user knows it's not hung."""
            iteration = 0
            max_iterations = 120  # Max 20 minutes (120 * 10s)
            while not stop_updates.is_set() and iteration < max_iterations:
                time.sleep(update_interval)
                if not stop_updates.is_set() and not download_success.is_set():
                    iteration += 1
                    elapsed = time.time() - start_time
                    minutes = int(elapsed // 60)
                    seconds = int(elapsed % 60)
                    print(f"   ⏳ Still downloading... ({minutes}m {seconds}s elapsed)")
                    print("      This is normal - large model download in progress...")
                    print("      Please be patient, download will complete automatically.")
                    sys.stdout.flush()

        progress_thread = threading.Thread(target=show_progress, daemon=True)
        progress_thread.start()

        model = None
        print("   Starting model download...")
        sys.stdout.flush()

        try:
            model = Blip2ForConditionalGeneration.from_pretrained(
                model_name,
                torch_dtype=torch.float16 if device == "cuda" else torch.float32,
                low_cpu_mem_usage=True,
                local_files_only=False
            )

            stop_updates.set()

            elapsed_time = time.time() - start_time
            minutes = int(elapsed_time // 60)
            seconds = int(elapsed_time % 60)
            print(f"✓ Model loaded successfully! (took {minutes}m {seconds}s)")
            sys.stdout.flush()

        except MemoryError as mem_err:
            print(f"\n❌ Out of memory during model loading: {mem_err}")
            print("   → BLIP-2 requires ~8-16GB GPU memory (or ~16-32GB RAM if using CPU)")
            print("   → Close other applications and try again")
            print("   → Consider using a smaller BLIP-2 model or disable Vision LLM: set ENABLE_VISION_LLM=false")
            stop_updates.set()
            raise
        except OSError as os_err:
            error_str = str(os_err)
            if "No space" in error_str or "disk" in error_str.lower():
                print(f"\n❌ Disk space issue: {os_err}")
                print("   → BLIP-2 requires ~5-15GB disk space (depending on model)")
                print("   → Free up disk space and try again")
                print("   → Or disable Vision LLM: set ENABLE_VISION_LLM=false")
            else:
                print(f"\n❌ OS error during model loading: {os_err}")
            stop_updates.set()
            raise
        except Exception as load_err:
            error_str = str(load_err)
            error_type = type(load_err).__name__
            print(f"\n❌ Error during model loading ({error_type}): {error_str}")

            if "timeout" in error_str.lower() or "Connection" in error_str:
                print("\n💡 This appears to be a network/download issue.")
                print("   → Check your internet connection")
                print("   → Try again later or use a different network")
                print("   → Or disable Vision LLM: set ENABLE_VISION_LLM=false")
            elif "memory" in error_str.lower() or "OOM" in error_str:
                print("\n💡 This appears to be a memory issue.")
                print("   → BLIP-2 requires significant GPU memory (~8-16GB) or RAM (~16-32GB)")
                print("   → Close other applications to free up memory")
                print("   → Consider using a smaller BLIP-2 model or disable Vision LLM: set ENABLE_VISION_LLM=false")
            elif "disk" in error_str.lower() or "space" in error_str.lower():
                print("\n💡 This appears to be a disk space issue.")
                print("   → BLIP-2 requires ~5-15GB disk space (depending on model)")
                print("   → Free up disk space and try again")
                print("   → Or disable Vision LLM: set ENABLE_VISION_LLM=false")

            stop_updates.set()
            raise

        if model is None:
            raise RuntimeError("Model was not loaded successfully")

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
        error_type = type(e).__name__
        print(f"\n{'='*60}")
        print(f"Failed to load BLIP-2 model")
        print(f"{'='*60}")
        print(f"Error type: {error_type}")
        print(f"Error message: {error_msg}")

        if "Connection" in error_msg or "timeout" in error_msg.lower() or "network" in error_msg.lower():
            print("\n💡 Suggestions:")
            print("   → This might be a network issue. Check your internet connection.")
            print("   → BLIP-2 models are large (~5-15GB), download may take 10-20 minutes.")
            print("   → If behind a firewall/proxy, configure Hugging Face token or use offline mode.")
        elif "disk" in error_msg.lower() or "space" in error_msg.lower() or "No space" in error_msg:
            print("\n💡 Suggestions:")
            print("   → This might be a disk space issue. Free up some space.")
            print("   → BLIP-2 requires ~5-15GB disk space (depending on model).")
        elif "memory" in error_msg.lower() or "Memory" in error_msg or "OOM" in error_msg:
            print("\n💡 Suggestions:")
            print("   → This might be a memory issue. BLIP-2 requires significant resources:")
            print("     - GPU: ~8-16GB VRAM (recommended)")
            print("     - CPU: ~16-32GB RAM (much slower)")
            print("   → Try closing other applications or use a smaller BLIP-2 model.")
            print("   → Or disable Vision LLM: set ENABLE_VISION_LLM=false")
        elif "CUDA" in error_msg or "cuda" in error_msg.lower():
            print("\n💡 Suggestions:")
            print("   → CUDA/GPU error detected. BLIP-2 works best on GPU.")
            print("   → If no GPU available, it will use CPU but will be very slow.")
            print("   → Ensure CUDA is properly installed if you have a GPU.")
        else:
            print("\n💡 Suggestions:")
            print("   → Check if transformers and torch are properly installed.")
            print("   → Try: pip install --upgrade transformers torch accelerate")
            print("   → BLIP-2 requires transformers>=4.30.0 and torch>=2.0.0")
            print("   → Or disable Vision LLM: set ENABLE_VISION_LLM=false")

        print(f"{'='*60}\n")
        raise RuntimeError(f"Failed to load BLIP-2 model ({error_type}): {error_msg}") from e
