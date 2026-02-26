import json
from typing import List, Dict, Optional


def evaluate_two_backhand(
    phases: List[Dict], 
    combined_data: Optional[List[Dict]] = None
) -> List[Dict]:
    """
    Evaluate two-handed backhand shadow swing using phase data and optional Vision LLM insights.
    
    Args:
        phases: List of phase detection results
        combined_data: Optional combined data with Vision LLM analysis (from combine_data.py)
    
    Returns:
        List of feedback dictionaries with issues and tips
    """
    feedback = []

    contact_frames = [f for f in phases if f["phase"] == "CONTACT"]

    if not contact_frames:
        feedback.append({
            "code": "BH00",
            "issue": "No clear contact phase detected",
            "severity": "high",
            "tip": "Focus on creating a clear, decisive contact point. Rotate your shoulders and torso together, leading with your core. Swing smoothly through the ball with both hands working as one unit. Visualize making contact in front of your body, not beside it."
        })
        return feedback

    contact = contact_frames[0]
    
    llm_analysis_map = {}
    if combined_data:
        for frame_data in combined_data:
            frame_name = frame_data.get("frame", "")
            llm_analysis = frame_data.get("llm_analysis")
            if frame_name and llm_analysis:
                llm_analysis_map[frame_name] = llm_analysis

    hand_separation_issue = False
    contact_frame_name = contact.get("frame", "")
    
    if contact["wrist_distance"] > 0.06:
        hand_separation_issue = True
    
    if contact_frame_name in llm_analysis_map:
        llm_analysis = llm_analysis_map[contact_frame_name]
        hand_pos = llm_analysis.get("hand_position", "").lower()
        if hand_pos == "separated":
            hand_separation_issue = True
        elif hand_pos == "together" and contact["wrist_distance"] <= 0.06:
            hand_separation_issue = False
    
    if hand_separation_issue:
        feedback.append({
            "code": "BH01",
            "issue": "Hands separated during backhand",
            "severity": "high",
            "tip": "Maintain a firm, connected grip with both hands throughout the entire swing. Your hands should work together as one unit - think of them as 'glued' to the paddle. This connection provides stability, power, and control. Practice shadow swings focusing on keeping both hands together from start to finish."
        })

    rotation_issue = False
    
    if contact["shoulder_rotation"] < 0.15:
        rotation_issue = True
    
    if contact_frame_name in llm_analysis_map:
        llm_analysis = llm_analysis_map[contact_frame_name]
        body_rot = llm_analysis.get("body_rotation", "").lower()
        if body_rot == "insufficient" or body_rot == "limited":
            rotation_issue = True
        elif body_rot == "good" and contact["shoulder_rotation"] >= 0.15:
            rotation_issue = False
    
    if rotation_issue:
        feedback.append({
            "code": "BH02",
            "issue": "Insufficient torso rotation",
            "severity": "high",
            "tip": "Power in the two-handed backhand comes from your core rotation, not just arm strength. Rotate your shoulders and hips together - coil on the backswing by turning away from the net, then uncoil through contact. Your torso should lead the swing, with your arms following. Think 'body first, arms second'."
        })

    elbow_issue = False
    
    if contact["left_elbow_angle"] < 100 or contact["right_elbow_angle"] < 100:
        elbow_issue = True
    
    if contact_frame_name in llm_analysis_map:
        llm_analysis = llm_analysis_map[contact_frame_name]
        arm_struct = llm_analysis.get("arm_structure", "").lower()
        if arm_struct == "collapsed":
            elbow_issue = True
        elif arm_struct == "extended" and contact["left_elbow_angle"] >= 100 and contact["right_elbow_angle"] >= 100:
            elbow_issue = False
    
    if elbow_issue:
        feedback.append({
            "code": "BH03",
            "issue": "Elbows collapsed at contact",
            "severity": "medium",
            "tip": "Keep both arms extended and structured through contact. Avoid letting your elbows collapse inward - this reduces power and control. Maintain a firm, athletic position with both arms working together. Practice with a focus on keeping your arms extended through the hitting zone."
        })

    follow_frames = [f for f in phases if f["phase"] == "FOLLOW_THROUGH"]
    if len(follow_frames) < 2:
        feedback.append({
            "code": "BH04",
            "issue": "Short or incomplete follow-through",
            "severity": "low",
            "tip": "Complete your follow-through by allowing the paddle to finish high and across your body. This ensures you're accelerating through the ball, not stopping at contact. A full follow-through improves shot quality, consistency, and helps prevent injury. Let your body rotation carry the paddle through naturally."
        })

    if abs(contact["wrist_velocity"]) < 0.01:
        feedback.append({
            "code": "BH05",
            "issue": "Weak contact motion",
            "severity": "medium",
            "tip": "Build paddle speed through the contact zone. Start your swing smoothly and accelerate as you approach contact - the paddle should be moving fastest at the moment of impact. Use your body rotation to generate speed, not just your arms. Practice with a focus on rhythm and timing."
        })
    
    stance_issues = []
    balance_issues = []
    
    key_frames_to_check = []
    ready_frames = [f for f in phases if f["phase"] == "READY"]
    if ready_frames:
        key_frames_to_check.extend(ready_frames)
    if contact_frames:
        key_frames_to_check.append(contact_frames[0])
    
    for phase_frame in key_frames_to_check:
        frame_name = phase_frame.get("frame", "")
        if frame_name in llm_analysis_map:
            llm_analysis = llm_analysis_map[frame_name]
            
            stance = llm_analysis.get("stance", "").lower()
            if stance == "closed":
                stance_issues.append("Your stance appears too closed, which limits your ability to rotate and generate power. For a two-handed backhand, use a more open or square stance - position your feet more parallel to the net. This allows better hip and shoulder rotation, enabling you to use your core effectively.")
            
            balance = llm_analysis.get("balance", "").lower()
            if balance == "unstable":
                balance_issues.append("Stability is crucial for a powerful two-handed backhand. Maintain a balanced, athletic stance with your weight centered. Keep your knees slightly bent and avoid leaning too far in any direction. A stable base allows you to transfer power from your legs and core through to the paddle.")
    
    if stance_issues:
        feedback.append({
            "code": "BH06",
            "issue": "Stance needs adjustment",
            "severity": "medium",
            "tip": stance_issues[0]
        })
    
    if balance_issues:
        feedback.append({
            "code": "BH07",
            "issue": "Balance instability detected",
            "severity": "medium",
            "tip": balance_issues[0]
        })
    
    if feedback:
        feedback.append({
            "code": "BH99",
            "issue": "Good shadow two-handed backhand",
            "severity": "none",
            "tip": "Great work! Your swing demonstrates solid fundamentals. Continue refining the specific areas mentioned above. Remember: consistency comes from focused practice. Keep working on these improvements and you'll see steady progress."
        })
        return feedback

    return [{
        "code": "BH99",
        "issue": "Excellent shadow two-handed backhand",
        "severity": "none",
        "tip": "Outstanding technique! Your hand connection, body rotation, arm structure, and follow-through all look excellent. You're generating good power through proper core engagement and maintaining balance throughout. This is high-level form - keep up the excellent work and continue refining these fundamentals!"
    }]


def save_feedback(feedback: List[dict], path: str):
    with open(path, "w") as f:
        json.dump(feedback, f, indent=2)


if __name__ == "__main__":
    phases_path = r"D:\chatbot\back_end\data\phase\test_backhand_phases.json"
    output_path = r"D:\chatbot\back_end\data\feedback\test_backhand_feedback.json"

    with open(phases_path) as f:
        phases = json.load(f)

    feedback = evaluate_two_backhand(phases)
    save_feedback(feedback, output_path)

    print("Two-handed backhand feedback generated")
