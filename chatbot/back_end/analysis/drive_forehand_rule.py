import json
from typing import List, Dict, Optional


def evaluate_shadow_drive_forehand(
    phases: List[Dict], 
    combined_data: Optional[List[Dict]] = None
) -> List[Dict]:
    """
    Evaluate drive forehand shadow swing using phase data and optional Vision LLM insights.
    
    Args:
        phases: List of phase detection results
        combined_data: Optional combined data with Vision LLM analysis (from combine_data.py)
    
    Returns:
        List of feedback dictionaries with issues and tips
    """
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
            "tip": "Focus on creating a clear acceleration phase through the contact point. Visualize hitting through the ball, not just at it. Practice shadow swings emphasizing a smooth, powerful forward motion that peaks at contact."
        }]

    contact_frame = contact[0]

    arm_extension_tip = "Extend your hitting arm fully through contact for maximum power. Think of reaching forward and slightly upward, keeping your elbow from collapsing too early. This creates better leverage and paddle speed."
    
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
            "tip": arm_extension_tip + " Practice with a focus on maintaining arm extension through the hitting zone. Try shadow swings where you consciously extend your arm fully at contact."
        }]

    if not backswing:
        return [{
            "code": "FH02",
            "issue": "No backswing detected",
            "severity": "medium",
            "tip": "A proper backswing is crucial for power. Rotate your shoulders away from the net, bring your paddle back with your elbow leading, and create a 'coil' effect. This stored energy translates into paddle speed on the forward swing. Practice the 'load and explode' sequence."
        }]

    if not follow:
        return [{
            "code": "FH03",
            "issue": "No follow-through",
            "severity": "medium",
            "tip": "A complete follow-through ensures you're accelerating through the ball, not stopping at contact. Let your paddle continue forward and slightly across your body after contact. This natural finish helps with control and prevents injury."
        }]

    max_back_vel = min(
        [f["wrist_velocity"] for f in backswing], default=0
    )

    if contact_frame["wrist_velocity"] <= abs(max_back_vel):
        return [{
            "code": "FH04",
            "issue": "Low acceleration through swing",
            "severity": "medium",
            "tip": "Build paddle speed gradually from backswing to contact. Start slow and accelerate smoothly through the hitting zone. Think 'slow to fast' - the paddle should be moving fastest at contact. Practice with a focus on rhythm and timing."
        }]

    positive_follow = [
        f for f in follow if f["wrist_velocity"] > 0
    ]

    if len(positive_follow) < 2:
        return [{
            "code": "FH05",
            "issue": "Short follow-through",
            "severity": "low",
            "tip": "Extend your follow-through to improve shot quality and consistency. Your paddle should finish high and across your body, with your weight transferring forward. This ensures you're hitting through the ball, not just at it."
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
                stance_issues.append("Your stance appears too closed, limiting your rotation and power potential. Open your stance slightly - position your feet more parallel to the net or even slightly open. This allows better hip and shoulder rotation, generating more power from your core.")
            
            balance = llm_analysis.get("balance", "").lower()
            if balance == "unstable":
                balance_issues.append("Maintaining balance is crucial for consistent shots. Keep your weight centered over your feet, with knees slightly bent. Avoid leaning too far forward or backward. Practice balance drills and focus on a stable, athletic base throughout your swing.")
            
            body_rotation = llm_analysis.get("body_rotation", "").lower()
            if body_rotation == "minimal":
                body_rotation_issues.append("Power comes from your core, not just your arm. Rotate your shoulders and hips together during the swing - think of coiling on the backswing and uncoiling through contact. Engage your core muscles and transfer weight from back foot to front foot.")
    
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
            "tip": "Excellent work! Your swing shows good fundamentals. Continue refining the areas mentioned above, and remember: consistency comes from repetition. Keep practicing with focus on these specific improvements."
        })
        return feedback

    return [{
        "code": "FH99",
        "issue": "Excellent shadow drive forehand",
        "severity": "none",
        "tip": "Outstanding technique! Your arm extension, acceleration, and follow-through all look solid. You're generating good power through proper body rotation and maintaining balance throughout. Keep up the great work and continue refining these fundamentals!"
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
