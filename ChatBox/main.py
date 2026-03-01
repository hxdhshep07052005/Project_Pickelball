from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse

from api.upload_video import router as upload_router
from api.analyze_video import router as analyze_router
from api.chat import router as chat_router

app = FastAPI(
    title="Pickleball Training Chatbot API",
    description="API for video analysis and coaching feedback",
    version="1.0.0"
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],  # In production, replace with specific frontend URL
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
