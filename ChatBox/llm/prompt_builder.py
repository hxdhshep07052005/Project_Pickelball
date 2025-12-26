import json
import os

# Get the directory where this file is located
LLM_DIR = os.path.dirname(os.path.abspath(__file__))
PROMPTS_DIR = os.path.join(LLM_DIR, "prompts")


def load_feedback(path: str) -> dict:
    with open(path, "r", encoding="utf-8") as f:
        return json.load(f)


def load_prompt(prompt_path: str) -> str:
    with open(prompt_path, "r", encoding="utf-8") as f:
        return f.read()


def build_llm_messages(feedback_path: str, skill: str = "drive_forehand"):
    """
    Build LLM messages from feedback data.
    
    Args:
        feedback_path: Path to feedback JSON file
        skill: Skill name (default: "drive_forehand")
    
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

    messages = [
        {
            "role": "system",
            "content": system_prompt
        },
        {
            "role": "user",
            "content": (
                "Here is the structured feedback data "
                "generated from motion analysis:\n\n"
                f"{json.dumps(feedback, indent=2)}"
            )
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
