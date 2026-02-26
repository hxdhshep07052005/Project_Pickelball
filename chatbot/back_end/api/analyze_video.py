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
from vision.combine_data import load_vision_llm_analyses, combine_pose_and_llm, save_combined_data
from analysis.drive_forehand_phase import load_poses, detect_phases, save_phases
from analysis.drive_forehand_rule import evaluate_shadow_drive_forehand
from analysis.drive_two_backhand_phase import detect_two_backhand_phases
from analysis.drive_two_backhand_rule import evaluate_two_backhand

# Import Vision LLM functions (optional - only if available)
try:
    # Add vison_llm to path
    vison_llm_path = os.path.join(os.path.dirname(os.path.dirname(__file__)), "vison_llm")
    if vison_llm_path not in sys.path:
        sys.path.append(vison_llm_path)
    
    from vison_llm.init_llm import init_vision_llm_client
    from vison_llm.vison_forehand import analyze_frames_batch as analyze_forehand_frames_batch, save_frame_analyses
    from vison_llm.vison_backhand import analyze_frames_batch as analyze_backhand_frames_batch
    VISION_LLM_AVAILABLE = True
except ImportError as e:
    VISION_LLM_AVAILABLE = False
    print(f"Warning: Vision LLM module not available: {e}")
    print("Continuing without Vision LLM analysis.")

router = APIRouter(prefix="/api", tags=["analysis"])

# Base data directory
BASE_DATA_DIR = os.path.join(os.path.dirname(os.path.dirname(os.path.dirname(__file__))), "data")


class AnalyzeRequest(BaseModel):
    session_id: str
    skill: Optional[str] = "drive_forehand"  # Options: "drive_forehand", "drive_two_backhand"


def get_session_paths(session_id: str):
    """Generate all file paths for a session."""
    return {
        "video_dir": os.path.join(BASE_DATA_DIR, "video", session_id),
        "frame_dir": os.path.join(BASE_DATA_DIR, "frame", session_id),
        "pose_dir": os.path.join(BASE_DATA_DIR, "pose", session_id),
        "llm_analyses_file": os.path.join(BASE_DATA_DIR, "vision_llm", f"{session_id}_llm_analyses.json"),
        "combined_file": os.path.join(BASE_DATA_DIR, "combined", f"{session_id}_combined.json"),
        "phase_file": os.path.join(BASE_DATA_DIR, "phase", f"{session_id}_phases.json"),
        "feedback_file": os.path.join(BASE_DATA_DIR, "feedback", f"{session_id}_feedback.json")
    }


@router.post("/analyze-video")
async def analyze_video(request: AnalyzeRequest):
    """
    Run the full analysis pipeline on an uploaded video.
    
    Pipeline steps:
    1. Extract frames from video (sharpness-based)
    2. Extract pose landmarks from frames (MediaPipe)
    3. Analyze frames with Vision LLM (optional)
    4. Combine pose + Vision LLM data
    5. Detect phases (READY, BACKSWING, CONTACT, FOLLOW_THROUGH)
    6. Evaluate phases and generate feedback
    
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
        
        # Step 3: Vision LLM Analysis (optional)
        llm_analyses = {}
        if VISION_LLM_AVAILABLE:
            try:
                # Try to get pre-loaded Vision LLM client (loaded at startup)
                from vision_llm_global import get_vision_llm_client
                vision_llm_client = get_vision_llm_client()
                
                # If not available from startup, load it now (fallback)
                if vision_llm_client is None:
                    print("Vision LLM client not pre-loaded, initializing now...")
                    print("(This will take 30-60 seconds. Consider restarting server to pre-load at startup.)")
                    vision_llm_client = init_vision_llm_client(
                        model_name="Salesforce/blip-image-captioning-large"
                    )
                
                print(f"Running Vision LLM analysis on frames for {skill}...")
                os.makedirs(os.path.dirname(paths["llm_analyses_file"]), exist_ok=True)
                
                # Select appropriate Vision LLM analysis function based on skill
                if skill == "drive_two_backhand":
                    analyze_frames_func = analyze_backhand_frames_batch
                else:  # default to forehand
                    analyze_frames_func = analyze_forehand_frames_batch
                
                # Analyze frames with Vision LLM
                llm_results = analyze_frames_func(
                    frame_dir=paths["frame_dir"],
                    vision_llm_client=vision_llm_client
                )
                
                # Save Vision LLM analyses
                save_frame_analyses(llm_results, paths["llm_analyses_file"])
                
                # Convert to dictionary for easy lookup
                for item in llm_results:
                    frame_name = item.get("frame", "")
                    if frame_name:
                        llm_analyses[frame_name] = item.get("llm_analysis", {})
                
                print(f"Vision LLM analysis completed for {len(llm_analyses)} frames")
            except Exception as e:
                print(f"Warning: Vision LLM analysis failed: {str(e)}")
                print("Continuing with pose data only")
                llm_analyses = {}
        else:
            print("Vision LLM not available, skipping Vision LLM analysis")
        
        # Step 4: Combine pose + Vision LLM data
        pose_frames = load_poses(paths["pose_dir"])
        combined_frames = combine_pose_and_llm(pose_frames, llm_analyses)
        
        # Save combined data
        os.makedirs(os.path.dirname(paths["combined_file"]), exist_ok=True)
        save_combined_data(combined_frames, paths["combined_file"])
        
        # Step 5: Detect phases (using pose data from combined frames)
        os.makedirs(os.path.dirname(paths["phase_file"]), exist_ok=True)
        # Extract just pose data for phase detection (backward compatible)
        pose_only_frames = [
            {"frame": cf["frame"], "landmarks": cf["landmarks"]}
            for cf in combined_frames
        ]
        
        # Select appropriate phase detection function based on skill
        if skill == "drive_two_backhand":
            phases = detect_two_backhand_phases(pose_only_frames)
            from analysis.drive_two_backhand_phase import save_phases as save_backhand_phases
            save_backhand_phases(phases, paths["phase_file"])
        else:  # default to forehand
            phases = detect_phases(pose_only_frames)
            save_phases(phases, paths["phase_file"])
        
        # Step 6: Evaluate phases and generate feedback
        os.makedirs(os.path.dirname(paths["feedback_file"]), exist_ok=True)
        
        # Load phases for evaluation
        with open(paths["phase_file"], "r") as f:
            phases_data = json.load(f)
        
        # Select appropriate rule evaluation function based on skill
        if skill == "drive_two_backhand":
            feedback = evaluate_two_backhand(phases_data, combined_data=combined_frames)
        else:  # default to forehand
            feedback = evaluate_shadow_drive_forehand(phases_data, combined_data=combined_frames)
        
        # Save feedback
        with open(paths["feedback_file"], "w", encoding="utf-8") as f:
            json.dump(feedback, f, indent=2)
        
        return JSONResponse({
            "success": True,
            "session_id": session_id,
            "skill": skill,
            "frame_count": frame_count,
            "pose_count": len(pose_files),
            "llm_analysis_count": len(llm_analyses) if llm_analyses else 0,
            "combined_count": len(combined_frames),
            "phase_count": len(phases),
            "feedback_path": paths["feedback_file"],
            "combined_data_path": paths["combined_file"],
            "message": "Analysis completed successfully"
        })
    
    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(
            status_code=500,
            detail=f"Analysis failed: {str(e)}"
        )

