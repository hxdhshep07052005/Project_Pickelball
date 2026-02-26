import cv2
import os
import numpy as np

# Import MediaPipe with error handling for protobuf conflicts
try:
    import mediapipe as mp
    mp_pose = mp.solutions.pose
    MEDIAPIPE_AVAILABLE = True
except (AttributeError, ImportError) as e:
    print(f"Warning: MediaPipe not available: {e}")
    print("Falling back to extraction without pose detection")
    MEDIAPIPE_AVAILABLE = False
    mp_pose = None


def sharpness_score(image) -> float:
    """Calculate image sharpness using Laplacian variance."""
    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    return cv2.Laplacian(gray, cv2.CV_64F).var()


def motion_score(prev_gray, curr_gray) -> float:
    """Calculate motion between two frames."""
    if prev_gray is None:
        return 0.0
    diff = cv2.absdiff(prev_gray, curr_gray)
    return diff.mean()


def pose_detection_score(frame_rgb, pose) -> float:
    """
    Calculate pose detection confidence score.
    Returns average visibility of key landmarks.
    """
    results = pose.process(frame_rgb)
    
    if not results.pose_landmarks:
        return 0.0
    
    # Key landmarks for pickleball analysis
    key_landmarks = [
        mp_pose.PoseLandmark.RIGHT_SHOULDER,
        mp_pose.PoseLandmark.RIGHT_ELBOW,
        mp_pose.PoseLandmark.RIGHT_WRIST,
        mp_pose.PoseLandmark.LEFT_SHOULDER,
        mp_pose.PoseLandmark.RIGHT_HIP,
        mp_pose.PoseLandmark.LEFT_HIP,
    ]
    
    visibilities = []
    for landmark_idx in key_landmarks:
        landmark = results.pose_landmarks.landmark[landmark_idx.value]
        visibilities.append(landmark.visibility)
    
    return np.mean(visibilities) if visibilities else 0.0


def key_body_parts_score(frame_rgb, pose) -> float:
    """
    Calculate score based on visibility of critical body parts for swing analysis.
    Returns percentage of critical landmarks with good visibility (> 0.5).
    """
    results = pose.process(frame_rgb)
    
    if not results.pose_landmarks:
        return 0.0
    
    # Critical landmarks for pickleball forehand analysis
    critical_landmarks = [
        mp_pose.PoseLandmark.RIGHT_SHOULDER,
        mp_pose.PoseLandmark.RIGHT_ELBOW,
        mp_pose.PoseLandmark.RIGHT_WRIST,
        mp_pose.PoseLandmark.RIGHT_HIP,
    ]
    
    visible_count = 0
    for landmark_idx in critical_landmarks:
        landmark = results.pose_landmarks.landmark[landmark_idx.value]
        if landmark.visibility > 0.5:
            visible_count += 1
    
    return visible_count / len(critical_landmarks)


def player_centering_score(frame_rgb, pose) -> float:
    """
    Calculate score based on how centered the player is in the frame.
    Returns 1.0 if player is centered, decreases with distance from center.
    """
    results = pose.process(frame_rgb)
    
    if not results.pose_landmarks:
        return 0.0
    
    # Get bounding box of all landmarks
    x_coords = [lm.x for lm in results.pose_landmarks.landmark]
    y_coords = [lm.y for lm in results.pose_landmarks.landmark]
    
    center_x = np.mean(x_coords)
    center_y = np.mean(y_coords)
    
    # Distance from frame center (0.5, 0.5)
    dist_from_center = np.sqrt((center_x - 0.5)**2 + (center_y - 0.5)**2)
    
    # Score decreases with distance (max distance is ~0.707 from corner to center)
    score = max(0.0, 1.0 - (dist_from_center / 0.707))
    
    return score


def temporal_diversity_score(frame_idx, selected_frames, video_length, min_frame_gap=15) -> float:
    """
    Calculate temporal diversity score.
    Higher score for frames further from already selected frames.
    
    Args:
        frame_idx: Current frame index
        selected_frames: List of already selected frame indices
        video_length: Total number of frames in video
        min_frame_gap: Minimum frames between selections (default: ~0.5s at 30fps)
    
    Returns:
        Score between 0.0 and 1.0
    """
    if not selected_frames:
        return 1.0
    
    # Calculate minimum distance to any selected frame
    distances = [abs(frame_idx - sel_idx) for sel_idx in selected_frames]
    min_distance = min(distances)
    
    # Normalize: 1.0 if very far, 0.0 if too close
    if min_distance >= min_frame_gap:
        # Already far enough, full score
        return 1.0
    else:
        # Penalize frames too close to selected ones
        return min_distance / min_frame_gap


def normalize_score(score, min_val, max_val) -> float:
    """Normalize a score to 0-1 range."""
    if max_val == min_val:
        return 0.5  # Default middle value if no variation
    return (score - min_val) / (max_val - min_val)


def extract_frames(
    video_path: str,
    output_dir: str,
    seconds_interval: float = 1.0,
    burst_size: int = 7,
    keep_top_k: int = 2,
    w_sharp: float = 0.2,
    w_motion: float = 0.2,
    w_pose: float = 0.3,
    w_body_parts: float = 0.2,
    w_temporal: float = 0.1,
    use_pose_detection: bool = True
) -> int:
    """
    Extract keyframes using combined scoring: sharpness + motion + pose + body parts + temporal diversity.
    
    Args:
        video_path: Path to input video
        output_dir: Directory to save extracted frames
        seconds_interval: Interval between bursts in seconds
        burst_size: Number of frames to evaluate per burst
        keep_top_k: Number of frames to keep per burst
        w_sharp: Weight for sharpness score (default: 0.2)
        w_motion: Weight for motion score (default: 0.2)
        w_pose: Weight for pose detection score (default: 0.3)
        w_body_parts: Weight for key body parts visibility (default: 0.2)
        w_temporal: Weight for temporal diversity (default: 0.1)
        use_pose_detection: Whether to use pose detection (slower but better quality)
    
    Returns:
        Number of frames extracted
    """
    cap = cv2.VideoCapture(video_path)
    if not cap.isOpened():
        raise FileNotFoundError(f"Cannot open video: {video_path}")

    fps = cap.get(cv2.CAP_PROP_FPS)
    total_frames = int(cap.get(cv2.CAP_PROP_FRAME_COUNT))
    if fps <= 0:
        fps = 30

    os.makedirs(output_dir, exist_ok=True)

    interval_frames = max(1, int(fps * seconds_interval))

    # Initialize pose detection if enabled and available
    pose = None
    if use_pose_detection:
        if not MEDIAPIPE_AVAILABLE:
            print("Warning: Pose detection requested but MediaPipe is not available.")
            print("Continuing without pose detection. Install correct protobuf version:")
            print("  pip install 'protobuf<4.0.0'")
            use_pose_detection = False
        else:
            try:
                pose = mp_pose.Pose(
                    static_image_mode=False,  # Video mode for speed
                    model_complexity=1,  # Faster than 2, still accurate
                    enable_segmentation=False,
                    min_detection_confidence=0.5
                )
            except Exception as e:
                print(f"Warning: Failed to initialize MediaPipe pose detection: {e}")
                print("Continuing without pose detection")
                use_pose_detection = False
                pose = None

    prev_gray = None
    frame_idx = 0
    saved = 0
    selected_frame_indices = []  # Track selected frames for temporal diversity

    print(f"Extracting frames with pose detection: {use_pose_detection}")

    while True:
        ret, frame = cap.read()
        if not ret:
            break

        gray = cv2.cvtColor(frame, cv2.COLOR_BGR2GRAY)

        if frame_idx % interval_frames == 0:
            burst = []
            burst_sharp_scores = []
            burst_motion_scores = []
            burst_pose_scores = []
            burst_body_scores = []
            burst_centering_scores = []

            # Collect burst frames
            for _ in range(burst_size):
                ret, frame = cap.read()
                if not ret:
                    break

                gray = cv2.cvtColor(frame, cv2.COLOR_BGR2GRAY)
                frame_rgb = cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)

                # Calculate all scores
                sharp = sharpness_score(frame)
                motion = motion_score(prev_gray, gray)
                
                burst_sharp_scores.append(sharp)
                burst_motion_scores.append(motion)

                # Pose-based scores (only if enabled)
                pose_score = 0.0
                body_score = 0.0
                centering_score = 0.0
                
                if use_pose_detection and pose:
                    pose_score = pose_detection_score(frame_rgb, pose)
                    body_score = key_body_parts_score(frame_rgb, pose)
                    centering_score = player_centering_score(frame_rgb, pose)
                
                burst_pose_scores.append(pose_score)
                burst_body_scores.append(body_score)
                burst_centering_scores.append(centering_score)

                burst.append((frame_idx, frame, sharp, motion, pose_score, body_score, centering_score))
                prev_gray = gray
                frame_idx += 1

            if not burst:
                continue

            # Normalize scores to 0-1 range
            min_sharp = min(burst_sharp_scores)
            max_sharp = max(burst_sharp_scores)
            min_motion = min(burst_motion_scores)
            max_motion = max(burst_motion_scores)
            min_pose = min(burst_pose_scores) if use_pose_detection else 0.0
            max_pose = max(burst_pose_scores) if use_pose_detection else 1.0
            min_body = min(burst_body_scores) if use_pose_detection else 0.0
            max_body = max(burst_body_scores) if use_pose_detection else 1.0

            # Calculate combined scores
            scored_burst = []
            for idx, frame, sharp, motion, pose_sc, body_sc, centering_sc in burst:
                # Normalize scores
                norm_sharp = normalize_score(sharp, min_sharp, max_sharp)
                norm_motion = normalize_score(motion, min_motion, max_motion)
                norm_pose = normalize_score(pose_sc, min_pose, max_pose) if use_pose_detection else 0.5
                norm_body = normalize_score(body_sc, min_body, max_body) if use_pose_detection else 0.5
                
                # Temporal diversity score
                temporal_sc = temporal_diversity_score(idx, selected_frame_indices, total_frames)
                
                # Combined score
                combined_score = (
                    w_sharp * norm_sharp +
                    w_motion * norm_motion +
                    w_pose * norm_pose +
                    w_body_parts * norm_body +
                    w_temporal * temporal_sc
                )
                
                scored_burst.append((
                    combined_score, idx, frame, 
                    sharp, motion, pose_sc, body_sc, temporal_sc
                ))

            # Sort by combined score
            scored_burst.sort(key=lambda x: x[0], reverse=True)

            # Keep top-K frames
            for score, idx, frame, sharp, motion, pose_sc, body_sc, temporal_sc in scored_burst[:keep_top_k]:
                filename = (
                    f"frame_{saved:04d}"
                    f"_s{int(sharp)}"
                    f"_m{int(motion)}"
                    f"_p{int(pose_sc*100)}"
                    f"_b{int(body_sc*100)}.jpg"
                )
                cv2.imwrite(os.path.join(output_dir, filename), frame)
                selected_frame_indices.append(idx)
                saved += 1

            continue

        prev_gray = gray
        frame_idx += 1

    if pose:
        pose.close()
    cap.release()
    
    print(f"Extracted {saved} frames total")
    return saved


# ------------------ TEST ------------------

if __name__ == "__main__":
    video = r"D:\chatbot\back_end\data\video\test_chat.mp4"
    output = r"D:\chatbot\back_end\data\frame\test_chat_2"

    count = extract_frames(
        video_path=video,
        output_dir=output,
        seconds_interval=1.0,
        burst_size=7,
        keep_top_k=2,
        w_sharp=0.2,
        w_motion=0.2,
        w_pose=0.3,
        w_body_parts=0.2,
        w_temporal=0.1,
        use_pose_detection=True  # Set to False for faster extraction without pose detection
    )

    print(f"Extracted {count} frames")
