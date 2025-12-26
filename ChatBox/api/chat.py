import os
import json
from fastapi import APIRouter, HTTPException
from fastapi.responses import JSONResponse
from pydantic import BaseModel
from typing import Optional

# Import LLM functions
import sys
sys.path.append(os.path.dirname(os.path.dirname(__file__)))

from llm.prompt_builder import build_llm_messages
from llm.llm_client import get_llm_response

router = APIRouter(prefix="/api", tags=["chat"])

# Base data directory
BASE_DATA_DIR = os.path.join(os.path.dirname(os.path.dirname(os.path.dirname(__file__))), "data")


class ChatRequest(BaseModel):
    session_id: str
    skill: Optional[str] = "drive_forehand"


@router.post("/chat")
async def chat(request: ChatRequest):
    """
    Generate coaching feedback using LLM based on analysis results.
    
    This endpoint:
    1. Loads the feedback JSON from analysis
    2. Builds LLM prompt using prompt_builder
    3. Calls LLM to generate natural language coaching feedback
    
    Returns:
        - success: Whether feedback was generated
        - feedback: Natural language coaching response
        - raw_feedback: Original structured feedback data
    """
    session_id = request.session_id
    skill = request.skill
    
    feedback_file = os.path.join(BASE_DATA_DIR, "feedback", f"{session_id}_feedback.json")
    
    print(f"Looking for feedback file: {feedback_file}")
    print(f"File exists: {os.path.exists(feedback_file)}")
    
    # Check if feedback file exists
    if not os.path.exists(feedback_file):
        print(f"ERROR: Feedback file not found at {feedback_file}")
        raise HTTPException(
            status_code=404,
            detail=f"Feedback not found for session {session_id}. Please run analysis first."
        )
    
    try:
        # Load feedback
        print(f"Loading feedback from: {feedback_file}")
        with open(feedback_file, "r", encoding="utf-8") as f:
            raw_feedback = json.load(f)
        
        print(f"Loaded feedback: {raw_feedback}")
        
        # Build LLM messages
        messages = build_llm_messages(feedback_file, skill=skill)
        print(f"Built LLM messages, count: {len(messages)}")
        
        # Get LLM response (will use placeholder if LLM not configured)
        # The get_llm_response function already handles errors and falls back to placeholder
        print("Calling get_llm_response...")
        coaching_feedback = get_llm_response(messages)
        print(f"Received coaching feedback: {coaching_feedback[:100]}...")
        
        if not coaching_feedback or coaching_feedback.strip() == "":
            print("WARNING: Empty feedback received, using fallback")
            coaching_feedback = "Great effort on your shadow swing! Keep practicing and you'll see improvement!"
        
        return JSONResponse({
            "success": True,
            "session_id": session_id,
            "skill": skill,
            "feedback": coaching_feedback,
            "raw_feedback": raw_feedback,
            "message": "Coaching feedback generated successfully"
        })
    
    except HTTPException:
        raise
    except Exception as e:
        print(f"Chat endpoint error: {str(e)}")
        raise HTTPException(
            status_code=500,
            detail=f"Failed to generate feedback: {str(e)}"
        )

