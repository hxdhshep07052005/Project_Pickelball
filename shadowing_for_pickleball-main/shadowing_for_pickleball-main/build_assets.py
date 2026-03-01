import cv2
import mediapipe as mp
import numpy as np
import os
import shutil
import glob


POSES_LIST = ["Serve", "DriveForehand", "DriveBackhand", "Smash", "Volley"]




INPUT_ROOT = "input_images"
ASSETS_ROOT = "assets"

mp_pose = mp.solutions.pose
pose_static = mp_pose.Pose(
    static_image_mode=True,
    model_complexity=2,
    enable_segmentation=True,
    min_detection_confidence=0.5
)

def build_all_assets():
    print("========================================")
    print("   STARTING ASSETS BUILD PROCESS")
    print("========================================")

    if os.path.exists(ASSETS_ROOT):
        print(f"-> Deleting old folder: {ASSETS_ROOT}")
        shutil.rmtree(ASSETS_ROOT)

    os.makedirs(ASSETS_ROOT)
    print(f"-> Created new folder: {ASSETS_ROOT}")

    total_poses_processed = 0

    for pose_name in POSES_LIST:
        input_dir = os.path.join(INPUT_ROOT, pose_name)
        output_dir = os.path.join(ASSETS_ROOT, pose_name)

        print(f"\nChecking: {pose_name}...")

        if not os.path.exists(input_dir):
            print(f"ERROR: Folder '{input_dir}' not found.")
            print("   -> Please create this folder and add 4 images.")
            continue

        os.makedirs(output_dir, exist_ok=True)

        valid_extensions = ["*.jpg", "*.jpeg", "*.png", "*.JPG", "*.PNG", "*.JPEG"]
        images = []
        for ext in valid_extensions:
            found = glob.glob(os.path.join(input_dir, ext))
            images.extend(found)

        images = sorted(list(set(images)))

        if len(images) != 4:
            print(f"WARNING: Exactly 4 images required for '{pose_name}'. Found {len(images)}.")
            print(f"   -> File list: {images}")
            print("   -> Skipping this pose.")
            continue

        print(f"   Found {len(images)} images. Processing...")

        processed_count = 0
        for i, img_path in enumerate(images):
            frame = cv2.imread(img_path)
            if frame is None:
                print(f"   Cannot read file: {img_path}")
                continue

            frame = cv2.flip(frame, 1)

            img_rgb = cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)
            results = pose_static.process(img_rgb)

            if results.pose_landmarks and results.segmentation_mask is not None:
                mask = (results.segmentation_mask > 0.5).astype(np.uint8) * 255
                ghost = np.zeros_like(frame)
                ghost[:] = (0, 255, 0) # Green color

                ghost_masked = cv2.bitwise_and(ghost, ghost, mask=mask)

                contours, _ = cv2.findContours(mask, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
                cv2.drawContours(ghost_masked, contours, -1, (255, 255, 255), 2) #

                cv2.imwrite(f"{output_dir}/ghost_{i}.png", ghost_masked)

                lms = results.pose_landmarks.landmark
                ys = (lms[11].y + lms[12].y) / 2
                yh = (lms[23].y + lms[24].y) / 2
                torso_h = abs(ys - yh)
                xh = (lms[23].x + lms[24].x) / 2
                yh_c = (lms[23].y + lms[24].y) / 2

                np.save(f"{output_dir}/meta_{i}.npy", [torso_h, xh, yh_c])

                landmarks_xy = []
                for lm in lms:
                    landmarks_xy.append([lm.x, lm.y])
                np.save(f"{output_dir}/target_{i}.npy", np.array(landmarks_xy))

                processed_count += 1
            else:
                print(f"   No person detected in image: {os.path.basename(img_path)}")

        if processed_count == 4:
            total_poses_processed += 1
            print(f"   -> Finished pose: {pose_name}")

    pose_static.close()

    print("\n========================================")
    if total_poses_processed > 0:
        print(f"COMPLETED! Successfully processed {total_poses_processed} poses.")
        print(f"Data saved at: {os.path.abspath(ASSETS_ROOT)}")
        print("You can now run the training scripts.")
    else:
        print("NOTHING PROCESSED. Please check input_images folder.")
    print("========================================")

if __name__ == "__main__":
    build_all_assets()