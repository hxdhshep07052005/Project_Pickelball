"""
Standalone script to run video analysis pipeline
Can be called directly from PHP without FastAPI server
Supports both drive_forehand and drive_two_backhand skills
"""

import os
import sys
import json
import uuid
import argparse
from pathlib import Path

# Suppress TensorFlow warnings to stdout (they go to stderr anyway)
os.environ['TF_CPP_MIN_LOG_LEVEL'] = '2'  # Suppress INFO and WARNING messages

# Check for required packages before importing
missing_packages = []
try:
    import cv2
except ImportError:
    missing_packages.append("opencv-python")

try:
    import mediapipe as mp
    # Test if mediapipe has solutions attribute (0.9.x) or tasks (0.10+)
    mediapipe_works = False
    try:
        _ = mp.solutions.pose
        mediapipe_works = True
    except AttributeError:
        # Try new API
        try:
            from mediapipe.tasks import python
            from mediapipe.tasks.python import vision
            mediapipe_works = True
        except (ImportError, AttributeError):
            pass
    
    # If neither API works, mark as missing
    if not mediapipe_works:
        missing_packages.append("mediapipe (version issue - need >= 0.9.0)")
except ImportError as e:
    missing_packages.append("mediapipe")
    # Check if it's a protobuf conflict issue
    import_error_str = str(e)
    is_protobuf_error = "protobuf" in import_error_str.lower() or "runtime_version" in import_error_str
    
    # Add debug info about import error
    import sys
    error_msg = {
        "success": False,
        "error": f"Missing required Python packages: {', '.join(missing_packages)}. Please install them with: pip install {' '.join(missing_packages)}",
        "missing_packages": missing_packages,
        "debug_info": {
            "python_version": sys.version,
            "python_executable": sys.executable,
            "import_error": import_error_str
        }
    }
    
    # Add specific fix for protobuf conflict
    if is_protobuf_error:
        error_msg["fix_instructions"] = [
            "This is a protobuf version conflict issue. To fix:",
            "Option 1 (Recommended - Use newer protobuf compatible with TensorFlow):",
            f"  {sys.executable} -m pip install --upgrade protobuf>=5.28.0",
            f"  {sys.executable} -m pip install mediapipe opencv-python numpy",
            "",
            "Option 2 (If Option 1 doesn't work - Force protobuf 3.20.3, may break TensorFlow):",
            f"  {sys.executable} -m pip uninstall protobuf -y",
            f"  {sys.executable} -m pip install protobuf==3.20.3",
            f"  {sys.executable} -m pip install mediapipe opencv-python numpy",
            "",
            "Note: If you see TensorFlow conflict warnings, try Option 1 first."
        ]
        error_msg["error"] = "MediaPipe installation failed due to protobuf version conflict. See fix_instructions below."
    
    print(json.dumps(error_msg, indent=2))
    sys.exit(1)

try:
    import numpy
except ImportError:
    missing_packages.append("numpy")

if missing_packages:
    error_msg = {
        "success": False,
        "error": f"Missing required Python packages: {', '.join(missing_packages)}. Please install them with: pip install {' '.join(missing_packages)}",
        "missing_packages": missing_packages,
        "debug_info": {
            "python_version": sys.version,
            "python_executable": sys.executable,
            "python_path": sys.path[:5]  # First 5 paths
        },
        "installation_help": "Make sure you install packages in the same Python environment that PHP is using. Check the 'python_executable' path above and run: pip install " + " ".join(missing_packages)
    }
    print(json.dumps(error_msg, indent=2))
    sys.exit(1)

# Add current directory to path
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from vision.frame_extractor import extract_frames
from vision.pose_estimation import process_frame_folder
from vision.combine_data import load_vision_llm_analyses, combine_pose_and_llm, save_combined_data
from analysis.drive_forehand_phase import load_poses, detect_phases, save_phases
from analysis.drive_forehand_rule import evaluate_shadow_drive_forehand
from analysis.drive_two_backhand_phase import detect_two_backhand_phases
from analysis.drive_two_backhand_rule import evaluate_two_backhand
from llm.prompt_builder import build_llm_messages
from llm.llm_client import get_llm_response

# Import Vision LLM functions (optional - only if available)
VISION_LLM_AVAILABLE = False
try:
    # Add vison_llm to path
    vison_llm_path = os.path.join(os.path.dirname(__file__), "vison_llm")
    if vison_llm_path not in sys.path:
        sys.path.append(vison_llm_path)
    
    from vison_llm.init_llm import init_vision_llm_client
    from vison_llm.vison_forehand import analyze_frames_batch as analyze_forehand_frames_batch, save_frame_analyses
    from vison_llm.vison_backhand import analyze_frames_batch as analyze_backhand_frames_batch
    VISION_LLM_AVAILABLE = True
except ImportError as e:
    VISION_LLM_AVAILABLE = False
    print(f"Warning: Vision LLM module not available: {e}", file=sys.stderr)
    print("Continuing without Vision LLM analysis.", file=sys.stderr)

# Base data directory - use parent chatbot/data folder
BASE_DATA_DIR = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "data")


def get_session_paths(session_id: str):
    """Generate all file paths for a session."""
    return {
        "video_dir": os.path.join(BASE_DATA_DIR, "video", session_id),
        "frame_dir": os.path.join(BASE_DATA_DIR, "frame", session_id),
        "pose_dir": os.path.join(BASE_DATA_DIR, "pose", session_id),
        "phase_file": os.path.join(BASE_DATA_DIR, "phase", f"{session_id}_phases.json"),
        "feedback_file": os.path.join(BASE_DATA_DIR, "feedback", f"{session_id}_feedback.json"),
        "llm_analyses_file": os.path.join(BASE_DATA_DIR, "vision_llm", f"{session_id}_llm_analyses.json"),
        "combined_file": os.path.join(BASE_DATA_DIR, "combined", f"{session_id}_combined.json")
    }


def run_analysis(video_path: str, skill: str = "drive_forehand", output_dir: str = None):
    """
    Run full analysis pipeline on a video file.
    
    Args:
        video_path: Path to input video file
        skill: Skill name ("drive_forehand" or "drive_two_backhand")
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
    paths = get_session_paths(session_id)
    if output_dir is None:
        output_dir = paths["video_dir"]
    
    os.makedirs(output_dir, exist_ok=True)
    
    # Copy video to session directory
    import shutil
    video_filename = f"video{os.path.splitext(video_path)[1]}"
    session_video_path = os.path.join(output_dir, video_filename)
    shutil.copy2(video_path, session_video_path)
    
    try:
        # Step 1: Extract frames
        os.makedirs(paths["frame_dir"], exist_ok=True)
        
        frame_count = extract_frames(
            video_path=session_video_path,
            output_dir=paths["frame_dir"],
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
        os.makedirs(paths["pose_dir"], exist_ok=True)
        
        try:
            process_frame_folder(
                frame_dir=paths["frame_dir"],
                output_dir=paths["pose_dir"]
            )
        except ImportError as e:
            # MediaPipe not available
            return {
                "success": False,
                "error": f"MediaPipe is not available: {str(e)}. Please install mediapipe package: pip install mediapipe",
                "session_id": session_id,
                "frame_count": frame_count,
                "debug_info": {
                    "python_executable": sys.executable,
                    "python_version": sys.version,
                    "import_error": str(e)
                }
            }
        except Exception as e:
            # Other errors (e.g., model download failure, API issues)
            error_str = str(e)
            is_mediapipe_error = "mediapipe" in error_str.lower() or "MediaPipe" in error_str
            return {
                "success": False,
                "error": f"Pose estimation failed: {error_str}",
                "session_id": session_id,
                "frame_count": frame_count,
                "debug_info": {
                    "python_executable": sys.executable,
                    "python_version": sys.version,
                    "error_type": type(e).__name__,
                    "error_message": error_str
                },
                "fix_instructions": [
                    "If this is a MediaPipe error, try:",
                    f"  {sys.executable} -m pip install --upgrade mediapipe",
                    "If you see protobuf conflicts, see the protobuf fix instructions above."
                ] if is_mediapipe_error else []
            }
        
        # Check if any poses were detected
        pose_files = [f for f in os.listdir(paths["pose_dir"]) if f.endswith(".json")]
        if not pose_files:
            return {
                "success": False,
                "error": "No pose detected in any frame. Please ensure person is visible in video.",
                "session_id": session_id,
                "frame_count": frame_count
            }
        
        # Step 3: Vision LLM Analysis (optional)
        llm_analyses = {}
        if VISION_LLM_AVAILABLE:
            try:
                vision_llm_client = init_vision_llm_client(
                    model_name="Salesforce/blip-image-captioning-large"
                )
                
                print(f"Running Vision LLM analysis on frames for {skill}...", file=sys.stderr)
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
                
                print(f"Vision LLM analysis completed for {len(llm_analyses)} frames", file=sys.stderr)
            except Exception as e:
                print(f"Warning: Vision LLM analysis failed: {str(e)}", file=sys.stderr)
                print("Continuing with pose data only", file=sys.stderr)
                llm_analyses = {}
        
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
        
        # Step 7: Get coaching feedback from LLM
        coaching_feedback = None
        try:
            messages = build_llm_messages(paths["feedback_file"], skill=skill, combined_data_path=paths["combined_file"])
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
            "llm_analysis_count": len(llm_analyses) if llm_analyses else 0,
            "combined_count": len(combined_frames),
            "phase_count": len(phases),
            "techniques_detected": techniques_detected,
            "feedback": feedback,
            "coaching_feedback": coaching_feedback,
            "feedback_file": paths["feedback_file"],
            "combined_data_path": paths["combined_file"],
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
    parser.add_argument("--skill", default="drive_forehand", 
                       help="Skill name: 'drive_forehand' or 'drive_two_backhand' (default: drive_forehand)")
    parser.add_argument("--output", help="Output directory (optional)")
    
    args = parser.parse_args()
    
    result = run_analysis(args.video_path, args.skill, args.output)
    
    # Output JSON result to stdout only
    # All debug/warning messages should go to stderr
    # Flush stderr first to avoid mixing with JSON output
    sys.stderr.flush()
    # Print JSON to stdout (this is what PHP will parse)
    print(json.dumps(result, indent=2), file=sys.stdout, flush=True)


if __name__ == "__main__":
    main()

