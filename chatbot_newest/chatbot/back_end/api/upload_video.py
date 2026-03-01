import os
import uuid
from fastapi import APIRouter, UploadFile, File, HTTPException
from fastapi.responses import JSONResponse
from typing import Optional
import sys

router = APIRouter(prefix="/api", tags=["upload"])

BASE_DATA_DIR = os.path.join(os.path.dirname(os.path.dirname(os.path.dirname(__file__))), "data")
VIDEO_DIR = os.path.join(BASE_DATA_DIR, "video")

sys.path.append(os.path.dirname(os.path.dirname(__file__)))
try:
    from skill_classifier import classify_video_skill, get_skill_classifier
    SKILL_CLASSIFIER_AVAILABLE = True
except ImportError as e:
    print(f"Warning: Skill classifier not available: {e}")
    SKILL_CLASSIFIER_AVAILABLE = False


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

    if not file.filename:
        raise HTTPException(status_code=400, detail="No filename provided")

    allowed_extensions = {".mp4", ".mov", ".avi", ".mkv", ".webm"}
    file_ext = os.path.splitext(file.filename.lower())[1]

    if file_ext not in allowed_extensions:
        raise HTTPException(
            status_code=400,
            detail=f"Invalid file type. Allowed: {', '.join(allowed_extensions)}"
        )

    session_id = str(uuid.uuid4())

    session_video_dir = os.path.join(VIDEO_DIR, session_id)
    os.makedirs(session_video_dir, exist_ok=True)

    video_filename = f"video{file_ext}"
    video_path = os.path.join(session_video_dir, video_filename)

    try:
        contents = await file.read()

        if len(contents) > 50 * 1024 * 1024:
            raise HTTPException(status_code=400, detail="File too large. Maximum 50MB.")

        with open(video_path, "wb") as f:
            f.write(contents)

        detected_skill = None
        confidence = None
        if SKILL_CLASSIFIER_AVAILABLE:
            try:
                print(f"Detecting skill from video...")
                detected_skill, confidence = classify_video_skill(video_path)

                print(f"Model detection: {detected_skill} (confidence: {confidence:.2f})")


            except Exception as e:
                print(f"Warning: Skill classification failed: {str(e)}")
                print("Continuing with upload (classification error ignored)")
                detected_skill = None
                confidence = None
        else:
            print("Skill classifier not available, skipping detection")

        response_data = {
            "success": True,
            "session_id": session_id,
            "skill": skill,  # Selected skill
            "filename": file.filename,
            "message": "Video uploaded successfully"
        }

        if detected_skill is not None:
            response_data["detected_skill"] = detected_skill
            response_data["confidence"] = confidence
            detected_skill_name = "Drive Forehand" if detected_skill == "drive_forehand" else "Drive Two-Handed Backhand"
            response_data["detected_skill_name"] = detected_skill_name

        return JSONResponse(response_data)

    except HTTPException:
        raise
    except Exception as e:
        if os.path.exists(video_path):
            os.remove(video_path)
        raise HTTPException(status_code=500, detail=f"Failed to save video: {str(e)}")











