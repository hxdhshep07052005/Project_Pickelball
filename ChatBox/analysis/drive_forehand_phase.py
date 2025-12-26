import os
import json
import math
from typing import List


def load_poses(folder: str) -> List[dict]:
    frames = []
    for file in sorted(os.listdir(folder)):
        if file.endswith(".json"):
            with open(os.path.join(folder, file)) as f:
                frames.append(json.load(f))
    return frames


def angle(a, b, c) -> float:
    """
    Compute angle at point b between points a and c.
    """
    ba = (a[0] - b[0], a[1] - b[1])
    bc = (c[0] - b[0], c[1] - b[1])

    dot = ba[0] * bc[0] + ba[1] * bc[1]
    mag = math.sqrt(ba[0]**2 + ba[1]**2) * math.sqrt(bc[0]**2 + bc[1]**2)

    if mag == 0:
        return 0.0

    return math.degrees(math.acos(max(-1, min(1, dot / mag))))


def detect_phases(pose_frames: List[dict]) -> List[dict]:
    results = []

    prev_wrist_x = None

    for i, frame in enumerate(pose_frames):
        lm = frame["landmarks"]

        shoulder = lm["RIGHT_SHOULDER"]
        elbow = lm["RIGHT_ELBOW"]
        wrist = lm["RIGHT_WRIST"]
        hip = lm["RIGHT_HIP"]

        elbow_angle = angle(shoulder, elbow, wrist)

        wrist_x = wrist[0]
        wrist_vel = 0 if prev_wrist_x is None else wrist_x - prev_wrist_x

        # ---- Phase rules ----
        if abs(wrist_vel) < 0.002:
            phase = "READY"

        elif wrist_vel < -0.002:
            phase = "BACKSWING"

        elif elbow_angle > 160 and abs(wrist_vel) > 0.01:
            phase = "CONTACT"

        else:
            phase = "FOLLOW_THROUGH"

        results.append({
            "frame": frame["frame"],
            "phase": phase,
            "elbow_angle": round(elbow_angle, 1),
            "wrist_velocity": round(wrist_vel, 4)
        })

        prev_wrist_x = wrist_x

    return results


def save_phases(phases, output_path):
    with open(output_path, "w") as f:
        json.dump(phases, f, indent=2)


if __name__ == "__main__":
    poses_dir = r"D:\chatbot\back_end\data\pose\test_chat"
    output_file = r"D:\chatbot\back_end\data\phase\test_chat_phases.json"

    os.makedirs(os.path.dirname(output_file), exist_ok=True)

    frames = load_poses(poses_dir)
    phases = detect_phases(frames)
    save_phases(phases, output_file)

    print("Drive forehand phases detected")
