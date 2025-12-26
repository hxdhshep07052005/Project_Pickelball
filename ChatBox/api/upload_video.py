import os
import uuid
from fastapi import APIRouter, UploadFile, File, HTTPException
from fastapi.responses import JSONResponse
from typing import Optional

router = APIRouter(prefix="/api", tags=["upload"])

# Base data directory
BASE_DATA_DIR = os.path.join(os.path.dirname(os.path.dirname(os.path.dirname(__file__))), "data")
VIDEO_DIR = os.path.join(BASE_DATA_DIR, "video")


@router.post("/upload-video")
async def upload_video(
    file: UploadFile = File(...),
    skill: Optional[str] = "drive_forehand"
):
    """
    Upload a video file (3-5 seconds) for analysis.
    
    Returns:
        - session_id: Unique identifier for this analysis session
        - skill: The skill being analyzed
        - filename: Original filename
    """
    
    # Validate file type
    if not file.filename:
        raise HTTPException(status_code=400, detail="No filename provided")
    
    allowed_extensions = {".mp4", ".mov", ".avi", ".mkv", ".webm"}
    file_ext = os.path.splitext(file.filename.lower())[1]
    
    if file_ext not in allowed_extensions:
        raise HTTPException(
            status_code=400,
            detail=f"Invalid file type. Allowed: {', '.join(allowed_extensions)}"
        )
    
    # Generate unique session ID
    session_id = str(uuid.uuid4())
    
    # Create session directory
    session_video_dir = os.path.join(VIDEO_DIR, session_id)
    os.makedirs(session_video_dir, exist_ok=True)
    
    # Save video file
    video_filename = f"video{file_ext}"
    video_path = os.path.join(session_video_dir, video_filename)
    
    try:
        # Read and save file
        contents = await file.read()
        
        # Basic size check (e.g., max 50MB)
        if len(contents) > 50 * 1024 * 1024:
            raise HTTPException(status_code=400, detail="File too large. Maximum 50MB.")
        
        with open(video_path, "wb") as f:
            f.write(contents)
        
        return JSONResponse({
            "success": True,
            "session_id": session_id,
            "skill": skill,
            "filename": file.filename,
            "message": "Video uploaded successfully"
        })
    
    except Exception as e:
        # Clean up on error
        if os.path.exists(video_path):
            os.remove(video_path)
        raise HTTPException(status_code=500, detail=f"Failed to save video: {str(e)}")


