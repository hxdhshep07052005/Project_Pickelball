"""
Updated pose estimation using MediaPipe 0.10+ API
"""
import cv2
import os
import json

try:
    # Try new API (0.10+)
    from mediapipe.tasks import python
    from mediapipe.tasks.python import vision
    from mediapipe import ImageFormat
    import mediapipe as mp
    USE_NEW_API = True
except ImportError:
    # Fallback to old API (0.9.x)
    import mediapipe as mp
    USE_NEW_API = False


def extract_pose_from_frame(image_path: str, pose_processor=None) -> dict | None:
    """
    Extract pose landmarks from a single image.
    Compatible with both MediaPipe 0.9.x and 0.10+
    """
    image = cv2.imread(image_path)
    if image is None:
        return None

    image_rgb = cv2.cvtColor(image, cv2.COLOR_BGR2RGB)
    
    if USE_NEW_API:
        # MediaPipe 0.10+ API
        mp_image = mp.Image(image_format=ImageFormat.SRGB, data=image_rgb)
        detection_result = pose_processor.detect(mp_image)
        
        if not detection_result.pose_landmarks:
            return None
        
        landmarks = {}
        for idx, lm in enumerate(detection_result.pose_landmarks):
            # Get landmark name from enum
            landmark_name = mp.framework.formats.landmark_pb2.PoseLandmark.Name(idx)
            landmarks[landmark_name] = [
                round(lm.x, 5),
                round(lm.y, 5),
                round(lm.z, 5),
                round(lm.visibility, 5)
            ]
    else:
        # MediaPipe 0.9.x API (old)
        results = pose_processor.process(image_rgb)
        
        if not results.pose_landmarks:
            return None
        
        landmarks = {}
        mp_pose = mp.solutions.pose
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
    
    if USE_NEW_API:
        # MediaPipe 0.10+ API
        base_options = python.BaseOptions(model_asset_path=None)  # Use default model
        options = vision.PoseLandmarkerOptions(
            base_options=base_options,
            output_segmentation_masks=False,
            min_pose_detection_confidence=0.5,
            min_pose_presence_confidence=0.5,
            min_tracking_confidence=0.5
        )
        pose_processor = vision.PoseLandmarker.create_from_options(options)
    else:
        # MediaPipe 0.9.x API (old)
        mp_pose = mp.solutions.pose
        pose_processor = mp_pose.Pose(
            static_image_mode=True,
            model_complexity=2,
            enable_segmentation=False,
            min_detection_confidence=0.5
        )

    for file in sorted(os.listdir(frame_dir)):
        if not file.lower().endswith(".jpg"):
            continue

        frame_path = os.path.join(frame_dir, file)
        landmarks = extract_pose_from_frame(frame_path, pose_processor)

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
    
    if not USE_NEW_API:
        pose_processor.close()


if __name__ == "__main__":
    frames = r"D:\chatbot\back_end\data\frame\test_chat"
    output = r"D:\chatbot\back_end\data\pose\test_chat"

    process_frame_folder(frames, output)

