"""
Test script for Pickleball Training Chatbot API

Usage:
    python test_api.py

This script tests the full pipeline:
1. Upload a video
2. Analyze the video
3. Get coaching feedback
"""

import requests
import json
import os
import sys

# API base URL
BASE_URL = "http://localhost:8000/api"

# Test video path (update this to point to your test video)
TEST_VIDEO_PATH = r"D:\chatbot\back_end\data\video\test_chat.mp4"


def test_upload_video(video_path: str, skill: str = "drive_forehand"):
    """Test video upload endpoint."""
    print("\n" + "="*50)
    print("STEP 1: Uploading video...")
    print("="*50)
    
    if not os.path.exists(video_path):
        print(f"ERROR: Video file not found: {video_path}")
        return None
    
    url = f"{BASE_URL}/upload-video"
    
    with open(video_path, "rb") as f:
        files = {"file": (os.path.basename(video_path), f, "video/mp4")}
        data = {"skill": skill}
        
        try:
            response = requests.post(url, files=files, data=data)
            response.raise_for_status()
            
            result = response.json()
            print(f"✓ Video uploaded successfully")
            print(f"  Session ID: {result['session_id']}")
            print(f"  Skill: {result['skill']}")
            
            return result["session_id"]
        
        except requests.exceptions.RequestException as e:
            print(f"✗ Upload failed: {e}")
            if hasattr(e.response, 'text'):
                print(f"  Response: {e.response.text}")
            return None


def test_analyze_video(session_id: str, skill: str = "drive_forehand"):
    """Test video analysis endpoint."""
    print("\n" + "="*50)
    print("STEP 2: Analyzing video...")
    print("="*50)
    
    url = f"{BASE_URL}/analyze-video"
    data = {
        "session_id": session_id,
        "skill": skill
    }
    
    try:
        response = requests.post(url, json=data)
        response.raise_for_status()
        
        result = response.json()
        print(f"✓ Analysis completed successfully")
        print(f"  Frames extracted: {result['frame_count']}")
        print(f"  Poses detected: {result['pose_count']}")
        print(f"  Phases detected: {result['phase_count']}")
        print(f"  Feedback file: {result['feedback_path']}")
        
        return True
    
    except requests.exceptions.RequestException as e:
        print(f"✗ Analysis failed: {e}")
        if hasattr(e.response, 'text'):
            print(f"  Response: {e.response.text}")
        return False


def test_chat(session_id: str, skill: str = "drive_forehand"):
    """Test chat/feedback endpoint."""
    print("\n" + "="*50)
    print("STEP 3: Getting coaching feedback...")
    print("="*50)
    
    url = f"{BASE_URL}/chat"
    data = {
        "session_id": session_id,
        "skill": skill
    }
    
    try:
        response = requests.post(url, json=data)
        response.raise_for_status()
        
        result = response.json()
        print(f"✓ Feedback generated successfully")
        print(f"\n--- Coaching Feedback ---")
        print(result['feedback'])
        print(f"\n--- Raw Feedback Data ---")
        print(json.dumps(result['raw_feedback'], indent=2))
        
        return True
    
    except requests.exceptions.RequestException as e:
        print(f"✗ Chat failed: {e}")
        if hasattr(e.response, 'text'):
            print(f"  Response: {e.response.text}")
        return False


def test_health_check():
    """Test API health check."""
    print("\n" + "="*50)
    print("Testing API health...")
    print("="*50)
    
    try:
        response = requests.get("http://localhost:8000/health")
        response.raise_for_status()
        print(f"✓ API is healthy: {response.json()}")
        return True
    except requests.exceptions.RequestException as e:
        print(f"✗ API health check failed: {e}")
        print("  Make sure the server is running: python back_end/main.py")
        return False


def main():
    """Run full pipeline test."""
    print("\n" + "="*60)
    print("Pickleball Training Chatbot API - Test Script")
    print("="*60)
    
    # Check if server is running
    if not test_health_check():
        print("\n⚠ Please start the server first:")
        print("  cd back_end")
        print("  python main.py")
        print("\nOr:")
        print("  uvicorn main:app --reload")
        return
    
    # Check if test video exists
    if not os.path.exists(TEST_VIDEO_PATH):
        print(f"\n⚠ Test video not found: {TEST_VIDEO_PATH}")
        print("Please update TEST_VIDEO_PATH in this script.")
        return
    
    # Run full pipeline
    session_id = test_upload_video(TEST_VIDEO_PATH)
    
    if not session_id:
        print("\n✗ Pipeline stopped: Upload failed")
        return
    
    if not test_analyze_video(session_id):
        print("\n✗ Pipeline stopped: Analysis failed")
        return
    
    if not test_chat(session_id):
        print("\n✗ Pipeline stopped: Chat failed")
        return
    
    print("\n" + "="*60)
    print("✓ Full pipeline test completed successfully!")
    print("="*60)


if __name__ == "__main__":
    main()


