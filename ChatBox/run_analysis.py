"""
Standalone script to run video analysis pipeline
Can be called directly from PHP without FastAPI server
"""

import os
import sys
import json
import uuid
import argparse
from pathlib import Path

# Check for required packages before importing
missing_packages = []
try:
    import cv2
except ImportError:
    missing_packages.append("opencv-python")

try:
    import mediapipe as mp
    # Test if mediapipe has solutions attribute (0.9.x) or tasks (0.10+)
    try:
        _ = mp.solutions.pose
    except AttributeError:
        # Try new API
        try:
            from mediapipe.tasks import python
            from mediapipe.tasks.python import vision
        except ImportError:
            missing_packages.append("mediapipe (version issue - need >= 0.9.0)")
except ImportError:
    missing_packages.append("mediapipe")

try:
    import numpy
except ImportError:
    missing_packages.append("numpy")

if missing_packages:
    error_msg = {
        "success": False,
        "error": f"Missing required Python packages: {', '.join(missing_packages)}. Please install them with: pip install {' '.join(missing_packages)}",
        "missing_packages": missing_packages
    }
    print(json.dumps(error_msg, indent=2))
    sys.exit(1)

# Add current directory to path
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from vision.frame_extractor import extract_frames
from vision.pose_estimation import process_frame_folder
from analysis.drive_forehand_phase import load_poses, detect_phases, save_phases
from analysis.drive_forehand_rule import evaluate_shadow_drive_forehand
from llm.prompt_builder import build_llm_messages
from llm.llm_client import get_llm_response

# Base data directory
BASE_DATA_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), "data")


def run_analysis(video_path: str, skill: str = "drive_forehand", output_dir: str = None):
    """
    Run full analysis pipeline on a video file.
    
    Args:
        video_path: Path to input video file
        skill: Skill name (default: "drive_forehand")
        output_dir: Optional output directory (default: creates session_id folder)
    
    Returns:
        dict: Analysis results with session_id, feedback, coaching_feedback, etc.
    """
    
    if not os.path.exists(video_path):
        return {
            "success": False,
            "error": f"Video file not found: {video_path}"
        }
    
    # Generate session ID
    session_id = str(uuid.uuid4())
    
    # Set up paths
    if output_dir is None:
        output_dir = os.path.join(BASE_DATA_DIR, "video", session_id)
    
    os.makedirs(output_dir, exist_ok=True)
    
    # Copy video to session directory
    import shutil
    video_filename = f"video{os.path.splitext(video_path)[1]}"
    session_video_path = os.path.join(output_dir, video_filename)
    shutil.copy2(video_path, session_video_path)
    
    try:
        # Step 1: Extract frames
        frame_dir = os.path.join(BASE_DATA_DIR, "frame", session_id)
        os.makedirs(frame_dir, exist_ok=True)
        
        frame_count = extract_frames(
            video_path=session_video_path,
            output_dir=frame_dir,
            seconds_interval=1.0,
            burst_size=7,
            keep_top_k=2
        )
        
        if frame_count == 0:
            return {
                "success": False,
                "error": "Failed to extract frames from video",
                "session_id": session_id
            }
        
        # Step 2: Extract pose landmarks
        pose_dir = os.path.join(BASE_DATA_DIR, "pose", session_id)
        os.makedirs(pose_dir, exist_ok=True)
        
        process_frame_folder(
            frame_dir=frame_dir,
            output_dir=pose_dir
        )
        
        # Check if any poses were detected
        pose_files = [f for f in os.listdir(pose_dir) if f.endswith(".json")]
        if not pose_files:
            return {
                "success": False,
                "error": "No pose detected in any frame. Please ensure person is visible in video.",
                "session_id": session_id,
                "frame_count": frame_count
            }
        
        # Step 3: Detect phases
        phase_file = os.path.join(BASE_DATA_DIR, "phase", f"{session_id}_phases.json")
        os.makedirs(os.path.dirname(phase_file), exist_ok=True)
        
        pose_frames = load_poses(pose_dir)
        phases = detect_phases(pose_frames)
        save_phases(phases, phase_file)
        
        # Step 4: Evaluate phases and generate feedback
        feedback_file = os.path.join(BASE_DATA_DIR, "feedback", f"{session_id}_feedback.json")
        os.makedirs(os.path.dirname(feedback_file), exist_ok=True)
        
        # Load phases for evaluation
        with open(phase_file, "r") as f:
            phases_data = json.load(f)
        
        feedback = evaluate_shadow_drive_forehand(phases_data)
        
        # Save feedback
        with open(feedback_file, "w", encoding="utf-8") as f:
            json.dump(feedback, f, indent=2)
        
        # Step 5: Get coaching feedback from LLM
        coaching_feedback = None
        try:
            messages = build_llm_messages(feedback_file, skill=skill)
            coaching_feedback = get_llm_response(messages)
            
            if not coaching_feedback or coaching_feedback.strip() == "":
                coaching_feedback = "Great effort on your shadow swing! Keep practicing and you'll see improvement!"
        except Exception as e:
            print(f"Warning: LLM feedback generation failed: {str(e)}", file=sys.stderr)
            coaching_feedback = "Great effort on your shadow swing! Keep practicing and you'll see improvement!"
        
        # Extract techniques from feedback
        techniques_detected = []
        if feedback and isinstance(feedback, list):
            for item in feedback:
                if isinstance(item, dict) and 'code' in item:
                    issue = item.get('issue', '')
                    techniques_detected.append(f"{item['code']}: {issue}")
        
        return {
            "success": True,
            "session_id": session_id,
            "skill": skill,
            "frame_count": frame_count,
            "pose_count": len(pose_files),
            "phase_count": len(phases),
            "techniques_detected": techniques_detected,
            "feedback": feedback,
            "coaching_feedback": coaching_feedback,
            "feedback_file": feedback_file,
            "video_path": session_video_path
        }
    
    except Exception as e:
        import traceback
        error_trace = traceback.format_exc()
        return {
            "success": False,
            "error": f"Analysis failed: {str(e)}",
            "error_trace": error_trace,
            "session_id": session_id
        }


def main():
    """Command line interface"""
    parser = argparse.ArgumentParser(description="Run video analysis pipeline")
    parser.add_argument("video_path", help="Path to video file")
    parser.add_argument("--skill", default="drive_forehand", help="Skill name (default: drive_forehand)")
    parser.add_argument("--output", help="Output directory (optional)")
    
    args = parser.parse_args()
    
    result = run_analysis(args.video_path, args.skill, args.output)
    
    # Output JSON result
    print(json.dumps(result, indent=2))


if __name__ == "__main__":
    main()

