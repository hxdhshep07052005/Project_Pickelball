"""
Compatibility CLI for website PHP integration.

This script keeps the old contract used by:
- main/backend/video_analysis.php
- main/backend/action_prediction.php

It delegates processing to chatbot_newest FastAPI pipeline modules.
"""

import argparse
import asyncio
import contextlib
import io
import json
import os
import shutil
import sys
import uuid
from pathlib import Path

from fastapi import HTTPException

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
if BASE_DIR not in sys.path:
    sys.path.insert(0, BASE_DIR)

from api.analyze_video import AnalyzeRequest, analyze_video  # noqa: E402
from api.chat import ChatRequest, chat  # noqa: E402


def _parse_json_response(resp):
    """Parse FastAPI JSONResponse into Python dict."""
    if hasattr(resp, "body") and resp.body:
        return json.loads(resp.body.decode("utf-8"))
    return {}


def _extract_techniques(raw_feedback):
    techniques = []
    if isinstance(raw_feedback, list):
        for item in raw_feedback:
            if isinstance(item, dict) and item.get("code"):
                issue = item.get("issue", "")
                techniques.append(f"{item['code']}: {issue}")
    return techniques


def run_analysis(video_path: str, skill: str = "drive_forehand"):
    if not os.path.exists(video_path):
        return {"success": False, "error": f"Video file not found: {video_path}"}

    session_id = str(uuid.uuid4())
    data_dir = os.path.join(os.path.dirname(BASE_DIR), "data")
    session_video_dir = os.path.join(data_dir, "video", session_id)
    os.makedirs(session_video_dir, exist_ok=True)

    ext = Path(video_path).suffix or ".mp4"
    session_video_path = os.path.join(session_video_dir, f"video{ext}")
    shutil.copy2(video_path, session_video_path)

    try:
        analyze_req = AnalyzeRequest(session_id=session_id, skill=skill)
        with contextlib.redirect_stdout(io.StringIO()):
            analyze_resp = asyncio.run(analyze_video(analyze_req))
        analyze_data = _parse_json_response(analyze_resp)

        if not analyze_data.get("success"):
            return {
                "success": False,
                "error": analyze_data.get("detail", "Analysis failed"),
                "session_id": session_id,
            }

        chat_req = ChatRequest(session_id=session_id, skill=skill)
        with contextlib.redirect_stdout(io.StringIO()):
            chat_resp = asyncio.run(chat(chat_req))
        chat_data = _parse_json_response(chat_resp)

        coaching_feedback = chat_data.get("feedback")
        raw_feedback = chat_data.get("raw_feedback", [])
        techniques_detected = _extract_techniques(raw_feedback)

        return {
            "success": True,
            "session_id": session_id,
            "skill": skill,
            "frame_count": analyze_data.get("frame_count", 0),
            "pose_count": analyze_data.get("pose_count", 0),
            "llm_analysis_count": analyze_data.get("llm_analysis_count", 0),
            "combined_count": analyze_data.get("combined_count", 0),
            "phase_count": analyze_data.get("phase_count", 0),
            "techniques_detected": techniques_detected,
            "feedback": raw_feedback,
            "coaching_feedback": coaching_feedback,
            "feedback_file": analyze_data.get("feedback_path"),
            "combined_data_path": analyze_data.get("combined_data_path"),
            "video_path": session_video_path,
        }

    except HTTPException as e:
        return {
            "success": False,
            "error": str(e.detail),
            "session_id": session_id,
        }
    except Exception as e:
        return {
            "success": False,
            "error": f"Analysis failed: {str(e)}",
            "session_id": session_id,
        }


def main():
    parser = argparse.ArgumentParser(description="Run video analysis pipeline")
    parser.add_argument("video_path", help="Path to video file")
    parser.add_argument(
        "--skill",
        default="drive_forehand",
        help="Skill name: drive_forehand or drive_two_backhand",
    )
    args = parser.parse_args()

    result = run_analysis(args.video_path, args.skill)
    print(json.dumps(result, indent=2), flush=True)


if __name__ == "__main__":
    main()
