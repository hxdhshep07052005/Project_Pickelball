import os
import json
from fastapi import APIRouter, HTTPException
from fastapi.responses import JSONResponse
from pydantic import BaseModel
from typing import Optional

# Import pipeline functions
import sys
sys.path.append(os.path.dirname(os.path.dirname(__file__)))

from vision.frame_extractor import extract_frames
from vision.pose_estimation import process_frame_folder
from analysis.drive_forehand_phase import load_poses, detect_phases, save_phases
from analysis.drive_forehand_rule import evaluate_shadow_drive_forehand

router = APIRouter(prefix="/api", tags=["analysis"])

# Base data directory
BASE_DATA_DIR = os.path.join(os.path.dirname(os.path.dirname(os.path.dirname(__file__))), "data")


class AnalyzeRequest(BaseModel):
    session_id: str
    skill: Optional[str] = "drive_forehand"


def get_session_paths(session_id: str):
    """Generate all file paths for a session."""
    return {
        "video_dir": os.path.join(BASE_DATA_DIR, "video", session_id),
        "frame_dir": os.path.join(BASE_DATA_DIR, "frame", session_id),
        "pose_dir": os.path.join(BASE_DATA_DIR, "pose", session_id),
        "phase_file": os.path.join(BASE_DATA_DIR, "phase", f"{session_id}_phases.json"),
        "feedback_file": os.path.join(BASE_DATA_DIR, "feedback", f"{session_id}_feedback.json")
    }


@router.post("/analyze-video")
async def analyze_video(request: AnalyzeRequest):
    """
    Run the full analysis pipeline on an uploaded video.
    
    Pipeline steps:
    1. Extract frames from video (sharpness-based)
    2. Extract pose landmarks from frames
    3. Detect phases (READY, BACKSWING, CONTACT, FOLLOW_THROUGH)
    4. Evaluate phases and generate feedback
    
    Returns:
        - success: Whether analysis completed
        - feedback_path: Path to feedback JSON file
        - frame_count: Number of frames extracted
    """
    session_id = request.session_id
    skill = request.skill
    
    paths = get_session_paths(session_id)
    
    # Check if video exists
    video_dir = paths["video_dir"]
    if not os.path.exists(video_dir):
        raise HTTPException(status_code=404, detail=f"Session {session_id} not found")
    
    # Find video file
    video_files = [f for f in os.listdir(video_dir) 
                   if f.lower().endswith((".mp4", ".mov", ".avi", ".mkv", ".webm"))]
    
    if not video_files:
        raise HTTPException(status_code=404, detail="No video file found in session")
    
    video_path = os.path.join(video_dir, video_files[0])
    
    try:
        # Step 1: Extract frames
        os.makedirs(paths["frame_dir"], exist_ok=True)
        frame_count = extract_frames(
            video_path=video_path,
            output_dir=paths["frame_dir"],
            seconds_interval=1.0,
            burst_size=7,
            keep_top_k=2
        )
        
        if frame_count == 0:
            raise HTTPException(
                status_code=500,
                detail="Failed to extract frames from video"
            )
        
        # Step 2: Extract pose landmarks
        os.makedirs(paths["pose_dir"], exist_ok=True)
        process_frame_folder(
            frame_dir=paths["frame_dir"],
            output_dir=paths["pose_dir"]
        )
        
        # Check if any poses were detected
        pose_files = [f for f in os.listdir(paths["pose_dir"]) if f.endswith(".json")]
        if not pose_files:
            raise HTTPException(
                status_code=500,
                detail="No pose detected in any frame. Please ensure person is visible in video."
            )
        
        # Step 3: Detect phases
        os.makedirs(os.path.dirname(paths["phase_file"]), exist_ok=True)
        pose_frames = load_poses(paths["pose_dir"])
        phases = detect_phases(pose_frames)
        save_phases(phases, paths["phase_file"])
        
        # Step 4: Evaluate phases and generate feedback
        os.makedirs(os.path.dirname(paths["feedback_file"]), exist_ok=True)
        
        # Load phases for evaluation
        with open(paths["phase_file"], "r") as f:
            phases_data = json.load(f)
        
        feedback = evaluate_shadow_drive_forehand(phases_data)
        
        # Save feedback
        with open(paths["feedback_file"], "w", encoding="utf-8") as f:
            json.dump(feedback, f, indent=2)
        
        return JSONResponse({
            "success": True,
            "session_id": session_id,
            "skill": skill,
            "frame_count": frame_count,
            "pose_count": len(pose_files),
            "phase_count": len(phases),
            "feedback_path": paths["feedback_file"],
            "message": "Analysis completed successfully"
        })
    
    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(
            status_code=500,
            detail=f"Analysis failed: {str(e)}"
        )

