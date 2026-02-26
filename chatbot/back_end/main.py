from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse
from contextlib import asynccontextmanager
import os
import sys
import traceback
import threading

# Import API routers
from api.upload_video import router as upload_router
from api.analyze_video import router as analyze_router
from api.chat import router as chat_router

# Import global Vision LLM storage
from vision_llm_global import set_vision_llm_client

# Configuration: Set to False to completely skip Vision LLM loading
ENABLE_VISION_LLM = os.getenv("ENABLE_VISION_LLM", "true").lower() == "true"

# Try to load Vision LLM at startup (optional, non-blocking)
def init_vision_llm():
    """Initialize Vision LLM model at startup (optional)."""
    try:
        # Add vison_llm to path
        vison_llm_path = os.path.join(os.path.dirname(__file__), "vison_llm")
        if vison_llm_path not in sys.path:
            sys.path.append(vison_llm_path)
        
        from vison_llm.init_llm import init_vision_llm_client
        
        print("\n" + "="*60)
        print("Initializing Vision LLM model...")
        print("This may take 30-60 seconds on first run...")
        print("="*60)
        
        vision_llm_client = init_vision_llm_client(
            model_name="Salesforce/blip-image-captioning-large"
        )
        
        # Store globally to avoid reloading on every request
        set_vision_llm_client(vision_llm_client)
        
        print("="*60)
        print("✅ Vision LLM model loaded successfully!")
        print("="*60 + "\n")
        
    except ImportError as e:
        print(f"⚠️  Vision LLM module not available: {e}")
        print("Continuing without Vision LLM analysis.\n")
        set_vision_llm_client(None)
    except Exception as e:
        print(f"⚠️  Failed to load Vision LLM model: {e}")
        print(f"Error details: {traceback.format_exc()}")
        print("Continuing without Vision LLM analysis.\n")
        set_vision_llm_client(None)

def init_vision_llm_background():
    """Initialize Vision LLM in background thread (non-blocking)."""
    def load_model():
        try:
            init_vision_llm()
        except Exception as e:
            print(f"\n⚠️  Background Vision LLM loading failed: {e}")
            print("   Server will continue without Vision LLM analysis.")
            print("   You can still use the system with MediaPipe pose data only.\n")
            set_vision_llm_client(None)
    
    # Start loading in background thread
    thread = threading.Thread(target=load_model, daemon=True, name="VisionLLMLoader")
    thread.start()
    print("📦 Vision LLM loading in background (server starting immediately)...")
    print("   Check console for progress. This may take 5-10 minutes on first run.\n")

# Lifespan context manager for startup/shutdown events
@asynccontextmanager
async def lifespan(app: FastAPI):
    """Handle startup and shutdown events."""
    # Startup: Load Vision LLM model in background (non-blocking)
    print("🚀 Starting server...")
    
    if ENABLE_VISION_LLM:
        # Initialize Vision LLM in background thread so server starts immediately
        init_vision_llm_background()
        print("✅ Server started successfully!")
        print("   (Vision LLM will be available once loaded in background)\n")
    else:
        print("✅ Server started successfully!")
        print("   (Vision LLM disabled - using MediaPipe pose data only)\n")
        set_vision_llm_client(None)
    
    yield  # Server is running
    
    # Shutdown: Cleanup if needed
    print("🛑 Shutting down server...")

# Create FastAPI app with lifespan
app = FastAPI(
    title="Pickleball Training Chatbot API",
    description="API for video analysis and coaching feedback",
    version="1.0.0",
    lifespan=lifespan
)

# Configure CORS
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],  # In production, replace with specific frontend URL
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Register routers
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
    
    # Get local IP address for network access
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
    
    # Use 0.0.0.0 to allow access from other devices on network
    # Access via localhost or your actual IP address
    uvicorn.run(app, host="0.0.0.0", port=8000)

