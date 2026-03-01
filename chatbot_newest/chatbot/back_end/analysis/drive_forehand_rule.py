import json
from typing import List, Dict, Optional


def evaluate_shadow_drive_forehand(
    phases: List[Dict],
    combined_data: Optional[List[Dict]] = None
) -> List[Dict]:

    feedback = []

    ready = [f for f in phases if f["phase"] == "READY"]
    backswing = [f for f in phases if f["phase"] == "BACKSWING"]
    contact = [f for f in phases if f["phase"] == "CONTACT"]
    follow = [f for f in phases if f["phase"] == "FOLLOW_THROUGH"]

    llm_analysis_map = {}
    if combined_data:
        for frame_data in combined_data:
            frame_name = frame_data.get("frame", "")
            llm_analysis = frame_data.get("llm_analysis")
            if frame_name and llm_analysis:
                llm_analysis_map[frame_name] = llm_analysis

    if not contact:
        return [{
            "code": "FH00",
            "issue": "No swing peak detected",
            "severity": "high",
            "tip": "Make a full forward swing with clear acceleration."
        }]

    contact_frame = contact[0]

    arm_extension_tip = "Extend your hitting arm more during the swing."

    if contact_frame["elbow_angle"] < 150:
        arm_extension_issue = True

    contact_frame_name = contact_frame.get("frame", "")
    if contact_frame_name in llm_analysis_map:
        llm_analysis = llm_analysis_map[contact_frame_name]
        arm_ext = llm_analysis.get("arm_extension", "").lower()
        if arm_ext in ["bent", "partial"]:
            arm_extension_issue = True
        elif arm_ext == "full" and contact_frame["elbow_angle"] >= 150:
            arm_extension_issue = False

    if arm_extension_issue:
        return [{
            "code": "FH01",
            "issue": "Arm too bent at peak",
            "severity": "high",
            "tip": arm_extension_tip
        }]

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

    stance_issues = []
    balance_issues = []
    body_rotation_issues = []

    key_frames_to_check = []
    if ready:
        key_frames_to_check.extend(ready)
    if contact:
        key_frames_to_check.append(contact[0])

    for phase_frame in key_frames_to_check:
        frame_name = phase_frame.get("frame", "")
        if frame_name in llm_analysis_map:
            llm_analysis = llm_analysis_map[frame_name]

            stance = llm_analysis.get("stance", "").lower()
            if stance == "closed":
                stance_issues.append("Your stance appears too closed. Try a more open or square stance for better power.")

            balance = llm_analysis.get("balance", "").lower()
            if balance == "unstable":
                balance_issues.append("Your balance looks unstable. Focus on maintaining a stable base throughout the swing.")

            body_rotation = llm_analysis.get("body_rotation", "").lower()
            if body_rotation == "minimal":
                body_rotation_issues.append("Your body rotation is minimal. Engage your core and rotate your torso more for power.")

    if stance_issues:
        feedback.append({
            "code": "FH06",
            "issue": "Stance needs adjustment",
            "severity": "medium",
            "tip": stance_issues[0]
        })

    if balance_issues:
        feedback.append({
            "code": "FH07",
            "issue": "Balance instability detected",
            "severity": "medium",
            "tip": balance_issues[0]
        })

    if body_rotation_issues:
        feedback.append({
            "code": "FH08",
            "issue": "Insufficient body rotation",
            "severity": "medium",
            "tip": body_rotation_issues[0]
        })

    if feedback:
        feedback.append({
            "code": "FH99",
            "issue": "Good shadow drive forehand",
            "severity": "none",
            "tip": "Nice swing! Keep working on the areas mentioned above."
        })
        return feedback

    return [{
        "code": "FH99",
        "issue": "Good shadow drive forehand",
        "severity": "none",
        "tip": "Nice swing! Your arm extension and acceleration look solid."
    }]


def main():
    phases_path = r"D:\chatbot\back_end\data\phase\test_chat_phases.json"
    output_path = r"D:\chatbot\back_end\data\feedback\test_chat_feedback.json"

    with open(phases_path, "r", encoding="utf-8") as f:
        phases = json.load(f)

    feedback = evaluate_shadow_drive_forehand(phases)

    with open(output_path, "w", encoding="utf-8") as f:
        json.dump(feedback, f, indent=2)

    print("=== FOREHAND SHADOW FEEDBACK ===")
    for item in feedback:
        print(f"- [{item['code']}] {item['issue']}")
        print(f"  Tip: {item['tip']}")


if __name__ == "__main__":
    main()
