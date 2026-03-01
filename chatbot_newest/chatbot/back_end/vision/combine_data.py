"""
Combine MediaPipe pose data with Vision LLM analysis data.
"""
import os
import json
from typing import List, Dict


def load_vision_llm_analyses(llm_analyses_path: str) -> Dict[str, dict]:

    if not os.path.exists(llm_analyses_path):
        return {}

    with open(llm_analyses_path, "r", encoding="utf-8") as f:
        analyses = json.load(f)

    llm_dict = {}
    for item in analyses:
        frame_name = item.get("frame", "")
        llm_analysis = item.get("llm_analysis", {})
        if frame_name:
            llm_dict[frame_name] = llm_analysis

    return llm_dict


def combine_pose_and_llm(
    pose_frames: List[dict],
    llm_analyses: Dict[str, dict]
) -> List[dict]:

    combined_frames = []

    for pose_frame in pose_frames:
        frame_name = pose_frame.get("frame", "")

        llm_data = llm_analyses.get(frame_name, {})

        combined = {
            "frame": frame_name,
            "landmarks": pose_frame.get("landmarks", {}),
            "llm_analysis": llm_data if llm_data else None
        }

        combined_frames.append(combined)

    return combined_frames


def save_combined_data(combined_frames: List[dict], output_path: str):

    os.makedirs(os.path.dirname(output_path), exist_ok=True)

    with open(output_path, "w", encoding="utf-8") as f:
        json.dump(combined_frames, f, indent=2)

    print(f"Saved {len(combined_frames)} combined frames to: {output_path}")


if __name__ == "__main__":
    poses_dir = r"D:\chatbot\back_end\data\pose\test_chat"
    llm_analyses_path = r"D:\chatbot\back_end\data\vision_llm\test_chat_llm_analyses.json"
    output_path = r"D:\chatbot\back_end\data\combined\test_chat_combined.json"

    from analysis.drive_forehand_phase import load_poses

    pose_frames = load_poses(poses_dir)
    print(f"Loaded {len(pose_frames)} pose frames")

    llm_analyses = load_vision_llm_analyses(llm_analyses_path)
    print(f"Loaded {len(llm_analyses)} LLM analyses")

    combined = combine_pose_and_llm(pose_frames, llm_analyses)
    print(f"Combined {len(combined)} frames")

    save_combined_data(combined, output_path)

    if combined:
        print("\nExample combined frame:")
        print(json.dumps(combined[0], indent=2))



















