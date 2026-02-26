import json
import os
from typing import Optional

# Get the directory where this file is located
LLM_DIR = os.path.dirname(os.path.abspath(__file__))
PROMPTS_DIR = os.path.join(LLM_DIR, "prompts")


def load_feedback(path: str) -> dict:
    with open(path, "r", encoding="utf-8") as f:
        return json.load(f)


def load_prompt(prompt_path: str) -> str:
    with open(prompt_path, "r", encoding="utf-8") as f:
        return f.read()


def load_combined_data(combined_data_path: str) -> dict:
    """
    Load combined data (pose + Vision LLM) from JSON file.
    
    Args:
        combined_data_path: Path to combined data JSON file
    
    Returns:
        Dictionary with combined data or empty dict if file doesn't exist
    """
    if not os.path.exists(combined_data_path):
        return {}
    
    try:
        with open(combined_data_path, "r", encoding="utf-8") as f:
            return json.load(f)
    except Exception as e:
        print(f"Warning: Failed to load combined data: {e}")
        return {}


def extract_vision_llm_summary(combined_data: list) -> dict:
    """
    Extract a summary of Vision LLM insights from combined data.
    
    Args:
        combined_data: List of combined frame data with Vision LLM analysis
    
    Returns:
        Dictionary summarizing Vision LLM insights
    """
    if not combined_data:
        return {}
    
    summary = {
        "stance_observations": [],
        "balance_observations": [],
        "arm_extension_observations": [],
        "body_rotation_observations": [],
        "follow_through_observations": []
    }
    
    for frame_data in combined_data:
        llm_analysis = frame_data.get("llm_analysis")
        if not llm_analysis:
            continue
        
        frame_name = frame_data.get("frame", "unknown")
        
        # Collect observations
        stance = llm_analysis.get("stance", "").lower()
        if stance and stance != "unknown":
            summary["stance_observations"].append(f"{frame_name}: {stance}")
        
        balance = llm_analysis.get("balance", "").lower()
        if balance and balance != "unknown":
            summary["balance_observations"].append(f"{frame_name}: {balance}")
        
        arm_ext = llm_analysis.get("arm_extension", "").lower()
        if arm_ext and arm_ext != "unknown":
            summary["arm_extension_observations"].append(f"{frame_name}: {arm_ext}")
        
        body_rot = llm_analysis.get("body_rotation", "").lower()
        if body_rot and body_rot != "unknown":
            summary["body_rotation_observations"].append(f"{frame_name}: {body_rot}")
        
        follow_through = llm_analysis.get("follow_through_direction", "").lower()
        if follow_through and follow_through != "unknown":
            summary["follow_through_observations"].append(f"{frame_name}: {follow_through}")
    
    # Remove empty lists
    summary = {k: v for k, v in summary.items() if v}
    
    return summary


def build_llm_messages(
    feedback_path: str, 
    skill: str = "drive_forehand",
    combined_data_path: Optional[str] = None
):
    """
    Build LLM messages from feedback data and optional Vision LLM analysis.
    
    Args:
        feedback_path: Path to feedback JSON file
        skill: Skill name (default: "drive_forehand")
        combined_data_path: Optional path to combined data JSON file (with Vision LLM analysis)
    
    Returns:
        List of message dicts for LLM API
    """
    # Load skill-specific prompt
    prompt_file = os.path.join(PROMPTS_DIR, f"{skill}_prompt.txt")
    
    # Fallback to drive_forehand if skill-specific prompt doesn't exist
    if not os.path.exists(prompt_file):
        prompt_file = os.path.join(PROMPTS_DIR, "drive_forehand_prompt.txt")
    
    system_prompt = load_prompt(prompt_file)

    feedback = load_feedback(feedback_path)
    
    # Build user message content
    user_content_parts = [
        "Here is the structured feedback data generated from motion analysis:\n\n",
        json.dumps(feedback, indent=2)
    ]
    
    # Add Vision LLM insights if available
    if combined_data_path:
        combined_data = load_combined_data(combined_data_path)
        if combined_data:
            llm_summary = extract_vision_llm_summary(combined_data)
            if llm_summary:
                user_content_parts.append("\n\n--- Additional Visual Analysis ---\n")
                user_content_parts.append(
                    "The following visual observations were made from analyzing the keyframes:\n\n"
                )
                user_content_parts.append(json.dumps(llm_summary, indent=2))
                user_content_parts.append(
                    "\n\nUse these visual observations to provide more context and specific feedback."
                )

    messages = [
        {
            "role": "system",
            "content": system_prompt
        },
        {
            "role": "user",
            "content": "".join(user_content_parts)
        }
    ]

    return messages

if __name__ == "__main__":
    msgs = build_llm_messages(
        "data/feedback/test_chat_feedback.json"
    )

    for m in msgs:
        print("ROLE:", m["role"])
        print(m["content"][:300], "\n")
