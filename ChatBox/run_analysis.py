"""
Standalone script to run video analysis pipeline.
Updated to follow the same core pipeline used by chatbot/back_end/main.py.
"""

import argparse
import contextlib
import io
import inspect
import json
import os
import shutil
import sys
import uuid

os.environ["TF_CPP_MIN_LOG_LEVEL"] = "2"

missing_packages = []
try:
    import cv2  # noqa: F401
except ImportError:
    missing_packages.append("opencv-python")

try:
    import mediapipe as mp  # noqa: F401
    mediapipe_works = False
    try:
        _ = mp.solutions.pose
        mediapipe_works = True
    except AttributeError:
        try:
            from mediapipe.tasks import python  # noqa: F401
            from mediapipe.tasks.python import vision  # noqa: F401
            mediapipe_works = True
        except (ImportError, AttributeError):
            pass
    if not mediapipe_works:
        missing_packages.append("mediapipe (version issue - need >= 0.9.0)")
except ImportError:
    missing_packages.append("mediapipe")

try:
    import numpy  # noqa: F401
except ImportError:
    missing_packages.append("numpy")

if missing_packages:
    print(
        json.dumps(
            {
                "success": False,
                "error": (
                    f"Missing required Python packages: {', '.join(missing_packages)}. "
                    f"Please install them with: pip install {' '.join(missing_packages)}"
                ),
                "missing_packages": missing_packages,
            },
            indent=2,
        )
    )
    sys.exit(1)

CURRENT_DIR = os.path.dirname(os.path.abspath(__file__))
CHATBOT_BACKEND_DIR = os.path.abspath(
    os.path.join(CURRENT_DIR, "..", "chatbot", "back_end")
)
SEARCH_DIRS = [CHATBOT_BACKEND_DIR, CURRENT_DIR] if os.path.isdir(CHATBOT_BACKEND_DIR) else [CURRENT_DIR]
for d in reversed(SEARCH_DIRS):
    if d not in sys.path:
        sys.path.insert(0, d)

from vision.frame_extractor import extract_frames
from vision.pose_estimation import process_frame_folder
from analysis.drive_forehand_phase import load_poses, detect_phases, save_phases
from analysis.drive_forehand_rule import evaluate_shadow_drive_forehand
from llm.prompt_builder import build_llm_messages
from llm.llm_client import get_llm_response

try:
    from vision.combine_data import combine_pose_and_llm, save_combined_data  # pyright: ignore[reportMissingImports]
    HAS_COMBINE = True
except Exception:
    HAS_COMBINE = False

try:
    from analysis.drive_two_backhand_phase import detect_two_backhand_phases  # pyright: ignore[reportMissingImports]
    from analysis.drive_two_backhand_rule import evaluate_two_backhand  # pyright: ignore[reportMissingImports]
    HAS_BACKHAND = True
except Exception:
    HAS_BACKHAND = False

VISION_LLM_AVAILABLE = False
try:
    vison_llm_path = os.path.join(SEARCH_DIRS[0], "vison_llm")
    if os.path.isdir(vison_llm_path) and vison_llm_path not in sys.path:
        sys.path.append(vison_llm_path)
    from vison_llm.init_llm import init_vision_llm_client  # pyright: ignore[reportMissingImports]
    from vison_llm.vison_forehand import analyze_frames_batch as analyze_forehand_frames_batch, save_frame_analyses  # pyright: ignore[reportMissingImports]
    from vison_llm.vison_backhand import analyze_frames_batch as analyze_backhand_frames_batch  # pyright: ignore[reportMissingImports]
    VISION_LLM_AVAILABLE = True
except Exception as e:
    print(f"Warning: Vision LLM module not available: {e}", file=sys.stderr)
    print("Continuing without Vision LLM analysis.", file=sys.stderr)

if os.path.isdir(CHATBOT_BACKEND_DIR):
    BASE_DATA_DIR = os.path.join(os.path.dirname(CHATBOT_BACKEND_DIR), "data")
else:
    BASE_DATA_DIR = os.path.join(CURRENT_DIR, "data")


def get_session_paths(session_id: str):
    return {
        "video_dir": os.path.join(BASE_DATA_DIR, "video", session_id),
        "frame_dir": os.path.join(BASE_DATA_DIR, "frame", session_id),
        "pose_dir": os.path.join(BASE_DATA_DIR, "pose", session_id),
        "phase_file": os.path.join(BASE_DATA_DIR, "phase", f"{session_id}_phases.json"),
        "feedback_file": os.path.join(BASE_DATA_DIR, "feedback", f"{session_id}_feedback.json"),
        "llm_analyses_file": os.path.join(BASE_DATA_DIR, "vision_llm", f"{session_id}_llm_analyses.json"),
        "combined_file": os.path.join(BASE_DATA_DIR, "combined", f"{session_id}_combined.json"),
    }


def _combine_fallback(pose_frames, llm_analyses):
    """Fallback combiner if vision.combine_data is unavailable."""
    combined = []
    for p in pose_frames:
        frame_name = p.get("frame", "")
        combined.append(
            {
                "frame": frame_name,
                "landmarks": p.get("landmarks", []),
                "llm_analysis": llm_analyses.get(frame_name, {}),
            }
        )
    return combined


def run_analysis(video_path: str, skill: str = "drive_forehand", output_dir: str = None):
    if not os.path.exists(video_path):
        return {"success": False, "error": f"Video file not found: {video_path}"}

    session_id = str(uuid.uuid4())
    paths = get_session_paths(session_id)
    if output_dir is None:
        output_dir = paths["video_dir"]
    os.makedirs(output_dir, exist_ok=True)

    video_filename = f"video{os.path.splitext(video_path)[1]}"
    session_video_path = os.path.join(output_dir, video_filename)
    shutil.copy2(video_path, session_video_path)

    try:
        os.makedirs(paths["frame_dir"], exist_ok=True)
        frame_count = extract_frames(
            video_path=session_video_path,
            output_dir=paths["frame_dir"],
            seconds_interval=1.0,
            burst_size=7,
            keep_top_k=2,
        )
        if frame_count == 0:
            return {"success": False, "error": "Failed to extract frames from video", "session_id": session_id}

        os.makedirs(paths["pose_dir"], exist_ok=True)
        process_frame_folder(frame_dir=paths["frame_dir"], output_dir=paths["pose_dir"])
        pose_files = [f for f in os.listdir(paths["pose_dir"]) if f.endswith(".json")]
        if not pose_files:
            return {
                "success": False,
                "error": "No pose detected in any frame. Please ensure person is visible in video.",
                "session_id": session_id,
                "frame_count": frame_count,
            }

        llm_analyses = {}
        if VISION_LLM_AVAILABLE:
            try:
                vision_llm_client = init_vision_llm_client(model_name="Salesforce/blip-image-captioning-large")
                os.makedirs(os.path.dirname(paths["llm_analyses_file"]), exist_ok=True)
                analyze_frames_func = (
                    analyze_backhand_frames_batch if skill == "drive_two_backhand" else analyze_forehand_frames_batch
                )
                llm_results = analyze_frames_func(frame_dir=paths["frame_dir"], vision_llm_client=vision_llm_client)
                save_frame_analyses(llm_results, paths["llm_analyses_file"])
                for item in llm_results:
                    frame_name = item.get("frame", "")
                    if frame_name:
                        llm_analyses[frame_name] = item.get("llm_analysis", {})
            except Exception as e:
                print(f"Warning: Vision LLM analysis failed: {e}", file=sys.stderr)
                llm_analyses = {}

        pose_frames = load_poses(paths["pose_dir"])
        if HAS_COMBINE:
            combined_frames = combine_pose_and_llm(pose_frames, llm_analyses)
            os.makedirs(os.path.dirname(paths["combined_file"]), exist_ok=True)
            save_combined_data(combined_frames, paths["combined_file"])
        else:
            combined_frames = _combine_fallback(pose_frames, llm_analyses)
            os.makedirs(os.path.dirname(paths["combined_file"]), exist_ok=True)
            with open(paths["combined_file"], "w", encoding="utf-8") as f:
                json.dump(combined_frames, f, indent=2)

        os.makedirs(os.path.dirname(paths["phase_file"]), exist_ok=True)
        pose_only_frames = [{"frame": cf.get("frame"), "landmarks": cf.get("landmarks", [])} for cf in combined_frames]
        if skill == "drive_two_backhand" and HAS_BACKHAND:
            phases = detect_two_backhand_phases(pose_only_frames)
            try:
                from analysis.drive_two_backhand_phase import save_phases as save_backhand_phases  # pyright: ignore[reportMissingImports]
                save_backhand_phases(phases, paths["phase_file"])
            except Exception:
                save_phases(phases, paths["phase_file"])
        else:
            phases = detect_phases(pose_only_frames)
            save_phases(phases, paths["phase_file"])

        os.makedirs(os.path.dirname(paths["feedback_file"]), exist_ok=True)
        with open(paths["phase_file"], "r", encoding="utf-8") as f:
            phases_data = json.load(f)
        if skill == "drive_two_backhand" and HAS_BACKHAND:
            feedback = evaluate_two_backhand(phases_data, combined_data=combined_frames)
        else:
            try:
                feedback = evaluate_shadow_drive_forehand(phases_data, combined_data=combined_frames)
            except TypeError:
                feedback = evaluate_shadow_drive_forehand(phases_data)
        with open(paths["feedback_file"], "w", encoding="utf-8") as f:
            json.dump(feedback, f, indent=2)

        coaching_feedback = None
        try:
            sig = inspect.signature(build_llm_messages)
            if "combined_data_path" in sig.parameters:
                messages = build_llm_messages(paths["feedback_file"], skill=skill, combined_data_path=paths["combined_file"])
            else:
                messages = build_llm_messages(paths["feedback_file"], skill=skill)
            coaching_feedback = get_llm_response(messages)
            if not coaching_feedback or coaching_feedback.strip() == "":
                coaching_feedback = "Great effort on your shadow swing! Keep practicing and you'll see improvement!"
        except Exception as e:
            print(f"Warning: LLM feedback generation failed: {e}", file=sys.stderr)
            coaching_feedback = "Great effort on your shadow swing! Keep practicing and you'll see improvement!"

        techniques_detected = []
        if isinstance(feedback, list):
            for item in feedback:
                if isinstance(item, dict) and "code" in item:
                    techniques_detected.append(f"{item['code']}: {item.get('issue', '')}")

        return {
            "success": True,
            "session_id": session_id,
            "skill": skill,
            "frame_count": frame_count,
            "pose_count": len(pose_files),
            "llm_analysis_count": len(llm_analyses),
            "combined_count": len(combined_frames),
            "phase_count": len(phases),
            "techniques_detected": techniques_detected,
            "feedback": feedback,
            "coaching_feedback": coaching_feedback,
            "feedback_file": paths["feedback_file"],
            "combined_data_path": paths["combined_file"],
            "video_path": session_video_path,
        }
    except Exception as e:
        import traceback
        return {
            "success": False,
            "error": f"Analysis failed: {str(e)}",
            "error_trace": traceback.format_exc(),
            "session_id": session_id,
        }


def main():
    parser = argparse.ArgumentParser(description="Run video analysis pipeline")
    parser.add_argument("video_path", help="Path to video file")
    parser.add_argument(
        "--skill",
        default="drive_forehand",
        help="Skill name: 'drive_forehand' or 'drive_two_backhand' (default: drive_forehand)",
    )
    parser.add_argument("--output", help="Output directory (optional)")
    args = parser.parse_args()

    with contextlib.redirect_stdout(io.StringIO()):
        result = run_analysis(args.video_path, args.skill, args.output)
    sys.stderr.flush()
    print(json.dumps(result, indent=2), file=sys.stdout, flush=True)


if __name__ == "__main__":
    main()
