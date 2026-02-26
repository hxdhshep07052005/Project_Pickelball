def forehand_prompt():
    """
    Detailed prompt for analyzing a pickleball forehand drive keyframe.
    Optimized for BLIP-style vision-language models.
    """

    return """
You are analyzing a single keyframe of a pickleball player performing a forehand drive.
Only describe what is directly visible in the image. Do NOT guess intent or outcome.

Analyze the following aspects and respond in clear, complete sentences:

1. Stance:
- Identify the player's stance as open, closed, or square.
- Base this on the orientation of the hips, shoulders, and feet relative to the net direction.

2. Body Balance:
- Describe whether the player appears stable or unstable.
- Observe knee bend, center of gravity, and whether the upper body is leaning excessively.

3. Hitting Arm and Elbow:
- Describe the hitting arm position.
- State whether the elbow is fully extended, partially extended, or significantly bent at this moment.
- Mention the relationship between shoulder, elbow, and wrist alignment.

4. Wrist and Paddle Position:
- Describe the wrist position (neutral, slightly flexed, or extended).
- Describe the paddle orientation (approximately vertical, slightly closed, or open).

5. Follow-Through Direction:
- Identify the visible follow-through direction: low finish, across the body, or high finish.
- Base this on the paddle path and arm position relative to the torso.

6. Body Rotation:
- Describe torso rotation as good rotation, limited rotation, or excessive rotation.
- Base this on shoulder rotation relative to the hips.

7. Overall Technique Summary:
- Provide a concise summary of the forehand drive posture visible in this frame.
- Avoid coaching advice; only describe the observed mechanics.
"""


def backhand_prompt():
    """
    Detailed prompt for analyzing a pickleball two-handed backhand drive keyframe.
    Optimized for BLIP-style vision-language models.
    """

    return """
You are analyzing a single keyframe of a pickleball player performing a two-handed backhand drive.
Only describe what is directly visible in the image. Do NOT guess intent or outcome.

Analyze the following aspects and respond in clear, complete sentences:

1. Stance:
- Identify the player's stance as open, closed, or square.
- Base this on the orientation of the hips, shoulders, and feet relative to the net direction.

2. Body Balance:
- Describe whether the player appears stable or unstable.
- Observe knee bend, center of gravity, and whether the upper body is leaning excessively.

3. Hand Position and Grip:
- Describe whether both hands are together on the paddle or separated.
- Note the position of the top hand (left hand for right-handed players) and bottom hand.
- State if the hands appear connected or disconnected during the swing.

4. Arm Structure:
- Describe the position of both arms (left and right).
- State whether the elbows are extended, partially bent, or significantly collapsed.
- Mention the relationship between shoulders, elbows, and wrists for both arms.

5. Shoulder and Torso Rotation:
- Describe the shoulder rotation and torso position.
- State whether there is good rotation, limited rotation, or excessive rotation.
- Base this on shoulder alignment relative to the hips and net direction.

6. Follow-Through Direction:
- Identify the visible follow-through direction: low finish, across the body, or high finish.
- Base this on the paddle path and arm position relative to the torso.

7. Overall Technique Summary:
- Provide a concise summary of the two-handed backhand drive posture visible in this frame.
- Avoid coaching advice; only describe the observed mechanics.
"""
