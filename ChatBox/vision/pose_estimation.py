import cv2
import os
import json

# Try to use old API first (0.9.x), fallback to new API (0.10+) if needed
try:
    import mediapipe as mp
    # Test old API
    mp_pose = mp.solutions.pose
    USE_NEW_API = False
except AttributeError:
    # Use new API (0.10+)
    from mediapipe.tasks import python
    from mediapipe.tasks.python import vision
    from mediapipe import ImageFormat
    import mediapipe as mp
    USE_NEW_API = True
    mp_pose = None


def extract_pose_from_frame(image_path: str, pose) -> dict | None:
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
        # Use file path directly - simpler and more reliable
        mp_image = mp.Image.create_from_file(image_path)
        detection_result = pose.detect(mp_image)
        
        if not detection_result.pose_landmarks or len(detection_result.pose_landmarks) == 0:
            return None
        
        # MediaPipe 0.10+ returns list of pose_landmarks (one per person detected)
        # Get first person's landmarks (which is also a list)
        pose_landmarks_list = detection_result.pose_landmarks[0]
        if not pose_landmarks_list or len(pose_landmarks_list) == 0:
            return None
        
        landmarks = {}
        # Map index to landmark name (33 landmarks in MediaPipe Pose)
        landmark_names = [
            "NOSE", "LEFT_EYE_INNER", "LEFT_EYE", "LEFT_EYE_OUTER", "RIGHT_EYE_INNER",
            "RIGHT_EYE", "RIGHT_EYE_OUTER", "LEFT_EAR", "RIGHT_EAR", "MOUTH_LEFT",
            "MOUTH_RIGHT", "LEFT_SHOULDER", "RIGHT_SHOULDER", "LEFT_ELBOW", "RIGHT_ELBOW",
            "LEFT_WRIST", "RIGHT_WRIST", "LEFT_PINKY", "RIGHT_PINKY", "LEFT_INDEX",
            "RIGHT_INDEX", "LEFT_THUMB", "RIGHT_THUMB", "LEFT_HIP", "RIGHT_HIP",
            "LEFT_KNEE", "RIGHT_KNEE", "LEFT_ANKLE", "RIGHT_ANKLE", "LEFT_HEEL",
            "RIGHT_HEEL", "LEFT_FOOT_INDEX", "RIGHT_FOOT_INDEX"
        ]
        for idx, lm in enumerate(pose_landmarks_list):
            if idx < len(landmark_names):
                name = landmark_names[idx]
            else:
                name = f"LANDMARK_{idx}"
            landmarks[name] = [
                round(lm.x, 5),
                round(lm.y, 5),
                round(lm.z, 5),
                round(lm.visibility, 5)
            ]
    else:
        # MediaPipe 0.9.x API (old)
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

    if USE_NEW_API:
        # MediaPipe 0.10+ API requires explicit model file
        # Download model if not exists, or use bundled model path
        model_dir = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "models")
        os.makedirs(model_dir, exist_ok=True)
        model_path = os.path.join(model_dir, "pose_landmarker.task")
        
        # Download model if not exists
        if not os.path.exists(model_path):
            try:
                import urllib.request
                model_url = "https://storage.googleapis.com/mediapipe-models/pose_landmarker/pose_landmarker_lite/float16/1/pose_landmarker_lite.task"
                print(f"Downloading pose model to {model_path}...")
                urllib.request.urlretrieve(model_url, model_path)
                print("Model downloaded successfully.")
            except Exception as e:
                raise Exception(f"Failed to download pose model: {str(e)}. Please download manually from: https://developers.google.com/mediapipe/solutions/vision/pose_landmarker")
        
        base_options = python.BaseOptions(model_asset_path=model_path)
        options = vision.PoseLandmarkerOptions(
            base_options=base_options,
            output_segmentation_masks=False,
            min_pose_detection_confidence=0.5,
            min_pose_presence_confidence=0.5,
            min_tracking_confidence=0.5
        )
        pose = vision.PoseLandmarker.create_from_options(options)
    else:
        # MediaPipe 0.9.x API (old)
        pose = mp_pose.Pose(
            static_image_mode=True,
            model_complexity=2,
            enable_segmentation=False,
            min_detection_confidence=0.5
        )
    
    try:

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
    finally:
        if USE_NEW_API:
            # New API doesn't need explicit close, but we can clean up
            pass
        else:
            # Old API - close if it's a context manager
            if hasattr(pose, 'close'):
                pose.close()


# ------------------ TEST ------------------

if __name__ == "__main__":
    frames = r"D:\chatbot\back_end\data\frame\test_chat"
    output = r"D:\chatbot\back_end\data\pose\test_chat"

    process_frame_folder(frames, output)
