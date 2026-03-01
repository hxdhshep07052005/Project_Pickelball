from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse
from contextlib import asynccontextmanager
import os
import sys
import traceback
import threading
import time

from api.upload_video import router as upload_router
from api.analyze_video import router as analyze_router
from api.chat import router as chat_router

from vision_llm_global import set_vision_llm_client

ENABLE_VISION_LLM = os.getenv("ENABLE_VISION_LLM", "true").lower() == "true"

_cors_origins_raw = os.getenv("CORS_ORIGINS", "").strip()
if _cors_origins_raw:
    CORS_ORIGINS = [o.strip() for o in _cors_origins_raw.split(",") if o.strip()]
else:
    CORS_ORIGINS = [
        "http://localhost:3000",
        "http://127.0.0.1:3000",
        "http://localhost:5173",
        "http://127.0.0.1:5173",
    ]

def init_vision_llm():
    """Initialize Vision LLM model at startup (optional)."""
    try:
        vison_llm_path = os.path.join(os.path.dirname(__file__), "vison_llm")
        if vison_llm_path not in sys.path:
            sys.path.append(vison_llm_path)

        from vison_llm.init_llm import init_vision_llm_client

        print("\n" + "="*60)
        print("Initializing Vision LLM model...")
        print("This may take 30-60 seconds on first run...")
        print("="*60)

        vision_llm_client = init_vision_llm_client(
            model_name="Salesforce/blip2-opt-2.7b"  # BLIP-2 can follow prompts properly
        )

        set_vision_llm_client(vision_llm_client)

        print("="*60)
        print("✅ Vision LLM model loaded successfully!")
        print("="*60 + "\n")

    except ImportError as e:
        print(f"⚠️  Vision LLM module not available: {e}")
        print("Continuing without Vision LLM analysis.\n")
        set_vision_llm_client(None)
    except KeyboardInterrupt:
        raise
    except Exception as e:
        print(f"\n{'='*60}")
        print(f"⚠️  Failed to load Vision LLM model")
        print(f"{'='*60}")
        print(f"Error: {str(e)}")
        print(f"Error type: {type(e).__name__}")
        print(f"\nFull traceback:")
        traceback.print_exc()
        print(f"\n{'='*60}")
        print("Continuing without Vision LLM analysis.")
        print("The server will continue running with MediaPipe pose data only.")
        print(f"{'='*60}\n")
        set_vision_llm_client(None)

def init_vision_llm_background():
    """Initialize Vision LLM in background thread (non-blocking)."""
    def load_model():
        try:
            time.sleep(1)
            init_vision_llm()
        except KeyboardInterrupt:
            raise
        except SystemExit:
            raise
        except Exception as e:
            print(f"\n{'='*60}")
            print(f"⚠️  Background Vision LLM loading failed!")
            print(f"{'='*60}")
            print(f"Error: {str(e)}")
            print(f"Error type: {type(e).__name__}")
            print(f"\nFull traceback:")
            try:
                traceback.print_exc()
            except:
                print("(Could not print full traceback)")
            print(f"\n{'='*60}")
            print("✅ Server will continue without Vision LLM analysis.")
            print("   You can still use the system with MediaPipe pose data only.")
            print("   To disable Vision LLM loading permanently, set ENABLE_VISION_LLM=false")
            print(f"{'='*60}\n")
            set_vision_llm_client(None)
        finally:
            try:
                from vision_llm_global import get_vision_llm_client
                if get_vision_llm_client() is None:
                    set_vision_llm_client(None)
            except:
                try:
                    set_vision_llm_client(None)
                except:
                    pass  # If even this fails, just continue - server must not crash

    thread = threading.Thread(target=load_model, daemon=True, name="VisionLLMLoader")
    thread.start()
    print("📦 Vision LLM loading in background (server starting immediately)...")
    print("   Check console for progress. This may take 5-10 minutes on first run.")
    print("   If loading fails or hangs, server will continue without Vision LLM.")
    print("   You can safely ignore Vision LLM loading and use the system normally.\n")

@asynccontextmanager
async def lifespan(app: FastAPI):
    """Handle startup and shutdown events."""
    print("🚀 Starting server...")

    if ENABLE_VISION_LLM:
        init_vision_llm_background()
        print("✅ Server started successfully!")
        print("   (Vision LLM will be available once loaded in background)\n")
    else:
        print("✅ Server started successfully!")
        print("   (Vision LLM disabled - using MediaPipe pose data only)\n")
        set_vision_llm_client(None)

    yield  # Server is running

    print("🛑 Shutting down server...")

app = FastAPI(
    title="Pickleball Training Chatbot API",
    description="API for video analysis and coaching feedback",
    version="1.0.0",
    lifespan=lifespan
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=CORS_ORIGINS,
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

app.include_router(upload_router)
app.include_router(analyze_router)
app.include_router(chat_router)


@app.get("/")
async def root():
    """Root endpoint - API health check."""
    return JSONResponse({
        "message": "Pickleball Training Chatbot API",
        "status": "running",
        "version": "1.0.0"
    })


@app.get("/health")
async def health_check():
    """Health check endpoint."""
    return {"status": "healthy"}


if __name__ == "__main__":
    import uvicorn
    import socket

    hostname = socket.gethostname()
    local_ip = socket.gethostbyname(hostname)

    print("\n" + "="*60)
    print("Pickleball Training Chatbot API Server")
    print("="*60)
    print(f"Server starting on:")
    print(f"  - Local:   http://localhost:8000")
    print(f"  - Local:   http://127.0.0.1:8000")
    print(f"  - Network: http://{local_ip}:8000")
    print(f"\nAPI Documentation: http://localhost:8000/docs")
    print("="*60 + "\n")

    uvicorn.run(app, host="0.0.0.0", port=8000)
