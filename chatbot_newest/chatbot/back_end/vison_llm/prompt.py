def forehand_prompt():
    return (
        "Question: Describe this pickleball forehand-drive keyframe. "
        "Only describe body mechanics and paddle action. "
        "No background description. No links or URLs. "
        "Answer with exactly 6 lines:\n"
        "Stance: open|closed|square|unknown\n"
        "Balance: stable|unstable|unknown\n"
        "Hand position: together|separated|unknown\n"
        "Arm structure: extended|partial|collapsed|unknown\n"
        "Follow-through direction: low|across_body|high|unknown\n"
        "Body rotation: good|insufficient|excessive|unknown\n"
        "Answer:"
    )


def forehand_evidence_prompt():
    return (
        "Question: Describe only observable player biomechanics in this pickleball forehand frame. "
        "Mention stance, balance, hand use, arm shape, follow-through direction, and body rotation cues. "
        "No background details. No links.\n"
        "Answer:"
    )


def backhand_prompt():
    return (
        "Question: Describe this pickleball two-handed-backhand-drive keyframe. "
        "Only describe body mechanics and paddle action. "
        "No background description. No links or URLs. "
        "Answer with exactly 6 lines:\n"
        "Stance: open|closed|square|unknown\n"
        "Balance: stable|unstable|unknown\n"
        "Hand position: together|separated|unknown\n"
        "Arm structure: extended|partial|collapsed|unknown\n"
        "Follow-through direction: low|across_body|high|unknown\n"
        "Body rotation: good|insufficient|excessive|unknown\n"
        "Answer:"
    )


def backhand_evidence_prompt():
    return (
        "Question: Describe only observable player biomechanics in this pickleball two-handed-backhand frame. "
        "Mention stance, balance, hand connection, arm shape, follow-through direction, and body rotation cues. "
        "No background details. No links.\n"
        "Answer:"
    )