import json
from typing import List, Dict


# ==========================================================
# STEP 7: Rule-based evaluation (shadow forehand drive)
# ==========================================================
def evaluate_shadow_drive_forehand(phases: List[Dict]) -> List[Dict]:
    feedback = []

    ready = [f for f in phases if f["phase"] == "READY"]
    backswing = [f for f in phases if f["phase"] == "BACKSWING"]
    contact = [f for f in phases if f["phase"] == "CONTACT"]
    follow = [f for f in phases if f["phase"] == "FOLLOW_THROUGH"]

    # ---------- RULE 0: No contact ----------
    if not contact:
        return [{
            "code": "FH00",
            "issue": "No swing peak detected",
            "severity": "high",
            "tip": "Make a full forward swing with clear acceleration."
        }]

    contact_frame = contact[0]

    # ---------- RULE 1: Elbow extension ----------
    if contact_frame["elbow_angle"] < 150:
        return [{
            "code": "FH01",
            "issue": "Arm too bent at peak",
            "severity": "high",
            "tip": "Extend your hitting arm more during the swing."
        }]

    # ---------- RULE 2: Swing sequence ----------
    if not backswing:
        return [{
            "code": "FH02",
            "issue": "No backswing detected",
            "severity": "medium",
            "tip": "Turn your shoulder and load your arm before swinging forward."
        }]

    if not follow:
        return [{
            "code": "FH03",
            "issue": "No follow-through",
            "severity": "medium",
            "tip": "Allow your swing to continue after the peak."
        }]

    # ---------- RULE 3: Acceleration ----------
    max_back_vel = min(
        [f["wrist_velocity"] for f in backswing], default=0
    )

    if contact_frame["wrist_velocity"] <= abs(max_back_vel):
        return [{
            "code": "FH04",
            "issue": "Low acceleration through swing",
            "severity": "medium",
            "tip": "Accelerate your paddle more as you swing forward."
        }]

    # ---------- RULE 4: Follow-through continuity ----------
    positive_follow = [
        f for f in follow if f["wrist_velocity"] > 0
    ]

    if len(positive_follow) < 2:
        return [{
            "code": "FH05",
            "issue": "Short follow-through",
            "severity": "low",
            "tip": "Let your swing finish smoothly instead of stopping early."
        }]

    # ---------- GOOD ----------
    return [{
        "code": "FH99",
        "issue": "Good shadow drive forehand",
        "severity": "none",
        "tip": "Nice swing! Your arm extension and acceleration look solid."
    }]


# ==========================================================
# MAIN FUNCTION FOR TESTING
# ==========================================================
def main():
    phases_path = r"D:\chatbot\back_end\data\phase\test_chat_phases.json"
    output_path = r"D:\chatbot\back_end\data\feedback\test_chat_feedback.json"

    # Load Step 6 output
    with open(phases_path, "r", encoding="utf-8") as f:
        phases = json.load(f)

    # Run evaluation
    feedback = evaluate_shadow_drive_forehand(phases)

    # Save feedback
    with open(output_path, "w", encoding="utf-8") as f:
        json.dump(feedback, f, indent=2)

    # Print to console
    print("=== FOREHAND SHADOW FEEDBACK ===")
    for item in feedback:
        print(f"- [{item['code']}] {item['issue']}")
        print(f"  Tip: {item['tip']}")


# ==========================================================
# ENTRY POINT
# ==========================================================
if __name__ == "__main__":
    main()
