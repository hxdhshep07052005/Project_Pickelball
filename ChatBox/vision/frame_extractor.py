import cv2
import os


def sharpness_score(image) -> float:
    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    return cv2.Laplacian(gray, cv2.CV_64F).var()


def extract_frames(
    video_path: str,
    output_dir: str,
    seconds_interval: float = 1.0,
    burst_size: int = 7,
    keep_top_k: int = 2
) -> int:
    """
    Extract best frames per burst using sharpness ranking.
    """

    cap = cv2.VideoCapture(video_path)
    if not cap.isOpened():
        raise FileNotFoundError(f"Cannot open video: {video_path}")

    fps = cap.get(cv2.CAP_PROP_FPS)
    total_frames = int(cap.get(cv2.CAP_PROP_FRAME_COUNT))

    print(f"FPS: {fps}")
    print(f"Total frames: {total_frames}")

    if fps <= 0:
        fps = 30

    os.makedirs(output_dir, exist_ok=True)

    interval_frames = max(1, int(fps * seconds_interval))

    frame_idx = 0
    saved = 0

    while True:
        ret, frame = cap.read()
        if not ret:
            break

        if frame_idx % interval_frames == 0:
            burst = []

            # Collect burst
            for _ in range(burst_size):
                ret, frame = cap.read()
                if not ret:
                    break

                score = sharpness_score(frame)
                burst.append((score, frame))
                frame_idx += 1

            # Sort by sharpness (descending)
            burst.sort(key=lambda x: x[0], reverse=True)

            # Keep top-K frames
            for score, frame in burst[:keep_top_k]:
                filename = f"frame_{saved:04d}_sharp_{int(score)}.jpg"
                cv2.imwrite(
                    os.path.join(output_dir, filename),
                    frame
                )
                saved += 1

            continue

        frame_idx += 1

    cap.release()
    return saved


if __name__ == "__main__":
    video = r"D:\chatbot\back_end\data\video\test_chat.mp4"
    output = r"D:\chatbot\back_end\data\frame\test_chat"

    count = extract_frames(
        video_path=video,
        output_dir=output,
        seconds_interval=1.0,
        burst_size=7,
        keep_top_k=2
    )

    print(f"Extracted {count} frames")
