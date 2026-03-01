import cv2
import os
import json
import mediapipe as mp


mp_pose = mp.solutions.pose


def extract_pose_from_frame(image_path: str, pose) -> dict | None:
    """
    Extract pose landmarks from a single image.
    """
    image = cv2.imread(image_path)
    if image is None:
        return None

    image_rgb = cv2.cvtColor(image, cv2.COLOR_BGR2RGB)
    results = pose.process(image_rgb)

    if not results.pose_landmarks:
        return None

    landmarks = {}
    for idx, lm in enumerate(results.pose_landmarks.landmark):
        name = mp_pose.PoseLandmark(idx).name
        landmarks[name] = [
            round(lm.x, 5),
            round(lm.y, 5),
            round(lm.z, 5),
            round(lm.visibility, 5)
        ]

    return landmarks


def process_frame_folder(
    frame_dir: str,
    output_dir: str
):
    os.makedirs(output_dir, exist_ok=True)

    with mp_pose.Pose(
        static_image_mode=True,
        model_complexity=2,
        enable_segmentation=False,
        min_detection_confidence=0.5
    ) as pose:

        for file in sorted(os.listdir(frame_dir)):
            if not file.lower().endswith(".jpg"):
                continue

            frame_path = os.path.join(frame_dir, file)
            landmarks = extract_pose_from_frame(frame_path, pose)

            if landmarks is None:
                print(f" No pose detected in {file}")
                continue

            output = {
                "frame": file,
                "landmarks": landmarks
            }

            json_path = os.path.join(
                output_dir,
                file.replace(".jpg", ".json")
            )

            with open(json_path, "w") as f:
                json.dump(output, f, indent=2)

            print(f" Pose saved: {json_path}")



if __name__ == "__main__":
    frames = r"D:\chatbot\back_end\data\frame\test_chat"
    output = r"D:\chatbot\back_end\data\pose\test_chat"

    process_frame_folder(frames, output)
