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


def distance(a, b) -> float:
    return math.sqrt(
        (a[0] - b[0]) ** 2 +
        (a[1] - b[1]) ** 2
    )


def angle(a, b, c) -> float:
    ba = (a[0] - b[0], a[1] - b[1])
    bc = (c[0] - b[0], c[1] - b[1])

    dot = ba[0] * bc[0] + ba[1] * bc[1]
    mag = math.sqrt(ba[0]**2 + ba[1]**2) * math.sqrt(bc[0]**2 + bc[1]**2)

    if mag == 0:
        return 0.0

    return math.degrees(math.acos(max(-1, min(1, dot / mag))))



def detect_two_backhand_phases(pose_frames: List[dict]) -> List[dict]:
    results = []
    prev_left_wrist_x = None

    for frame in pose_frames:
        lm = frame["landmarks"]

        # Joints
        LS = lm["LEFT_SHOULDER"]
        RS = lm["RIGHT_SHOULDER"]
        LE = lm["LEFT_ELBOW"]
        RE = lm["RIGHT_ELBOW"]
        LW = lm["LEFT_WRIST"]
        RW = lm["RIGHT_WRIST"]

        left_elbow_angle = angle(LS, LE, LW)
        right_elbow_angle = angle(RS, RE, RW)

        wrist_distance = distance(LW, RW)

        shoulder_rotation = abs(LS[0] - RS[0])

        wrist_x = LW[0]
        wrist_velocity = 0 if prev_left_wrist_x is None else wrist_x - prev_left_wrist_x

        if abs(wrist_velocity) < 0.002:
            phase = "READY"

        elif wrist_velocity < -0.002:
            phase = "BACKSWING"

        elif abs(wrist_velocity) > 0.01 and shoulder_rotation > 0.15:
            phase = "CONTACT"

        else:
            phase = "FOLLOW_THROUGH"

        results.append({
            "frame": frame["frame"],
            "phase": phase,
            "left_elbow_angle": round(left_elbow_angle, 1),
            "right_elbow_angle": round(right_elbow_angle, 1),
            "wrist_distance": round(wrist_distance, 4),
            "shoulder_rotation": round(shoulder_rotation, 4),
            "wrist_velocity": round(wrist_velocity, 4)
        })

        prev_left_wrist_x = wrist_x

    return results


def save_phases(phases: List[dict], path: str):
    with open(path, "w") as f:
        json.dump(phases, f, indent=2)


if __name__ == "__main__":
    poses_dir = r"D:\chatbot\back_end\data\pose\test_backhand"
    output_path = r"D:\chatbot\back_end\data\phase\test_backhand_phases.json"

    os.makedirs(os.path.dirname(output_path), exist_ok=True)

    frames = load_poses(poses_dir)
    phases = detect_two_backhand_phases(frames)
    save_phases(phases, output_path)

    print("Two-handed backhand phases detected")
