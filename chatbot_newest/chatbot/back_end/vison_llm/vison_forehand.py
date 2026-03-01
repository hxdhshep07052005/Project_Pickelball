import json
import os
import re
from collections import Counter
from typing import Dict, List, Tuple

from PIL import Image
import torch

from prompt import forehand_prompt, forehand_evidence_prompt

_SCHEMA_KEYS = [
    "stance",
    "balance",
    "hand_position",
    "arm_structure",
    "follow_through_direction",
    "body_rotation",
]

_ALLOWED = {
    "stance": {"open", "closed", "square", "unknown"},
    "balance": {"stable", "unstable", "unknown"},
    "hand_position": {"together", "separated", "unknown"},
    "arm_structure": {"extended", "partial", "collapsed", "unknown"},
    "follow_through_direction": {"low", "across_body", "high", "unknown"},
    "body_rotation": {"good", "insufficient", "excessive", "unknown"},
}

_FIELD_PROMPTS = {
    "stance": "Classify stance only. Reply one word: open or closed or square or unknown.",
    "balance": "Classify body balance only. Reply one word: stable or unstable or unknown.",
    "hand_position": "Classify hand position only. Reply one word: together or separated or unknown.",
    "arm_structure": "Classify arm structure only. Reply one word: extended or partial or collapsed or unknown.",
    "follow_through_direction": "Classify follow-through direction only. Reply one word: low or across_body or high or unknown.",
    "body_rotation": "Classify body rotation only. Reply one word: good or insufficient or excessive or unknown.",
}

_FIELD_ALIASES = {
    "stance": {"open": "open", "closed": "closed", "square": "square", "unknown": "unknown"},
    "balance": {"stable": "stable", "unstable": "unstable", "unknown": "unknown"},
    "hand_position": {"together": "together", "separated": "separated", "unknown": "unknown"},
    "arm_structure": {"extended": "extended", "partial": "partial", "collapsed": "collapsed", "unknown": "unknown"},
    "follow_through_direction": {
        "low": "low",
        "high": "high",
        "across_body": "across_body",
        "across body": "across_body",
        "across-the-body": "across_body",
        "unknown": "unknown",
    },
    "body_rotation": {"good": "good", "insufficient": "insufficient", "excessive": "excessive", "unknown": "unknown"},
}


def _default_schema() -> dict:
    return {k: "unknown" for k in _SCHEMA_KEYS}


def _default_meta() -> dict:
    return {
        "source": {k: "unknown" for k in _SCHEMA_KEYS},
        "confidence": {k: 0.0 for k in _SCHEMA_KEYS},
    }


def _extract_answer_segment(text: str) -> str:
    idx = text.lower().rfind("answer:")
    if idx != -1:
        return text[idx + len("answer:") :].strip()
    return text.strip()


def _is_invalid_answer(answer: str) -> bool:
    a = answer.strip().lower()
    if not a:
        return True
    if "http://" in a or "https://" in a or "www." in a:
        return True
    if "<" in a and ">" in a:
        return True
    if a.startswith("question:"):
        return True
    return False


def _normalize_value(key: str, value: str) -> str:
    v = value.strip().lower().replace("-", "_").replace(" ", "_")
    return v if v in _ALLOWED[key] else "unknown"


def _parse_labeled_lines(answer: str) -> dict:
    data = _default_schema()
    patterns = {
        "stance": r"stance\s*:\s*(open|closed|square|unknown)",
        "balance": r"balance\s*:\s*(stable|unstable|unknown)",
        "hand_position": r"hand\s*position\s*:\s*(together|separated|unknown)",
        "arm_structure": r"arm\s*structure\s*:\s*(extended|partial|collapsed|unknown)",
        "follow_through_direction": r"follow[-\s]*through\s*direction\s*:\s*(low|across_body|high|unknown)",
        "body_rotation": r"body\s*rotation\s*:\s*(good|insufficient|excessive|unknown)",
    }
    for key, pattern in patterns.items():
        m = re.search(pattern, answer, flags=re.IGNORECASE)
        if m:
            data[key] = _normalize_value(key, m.group(1))
    return data


def _parse_keyword_fallback(answer: str) -> dict:
    text = answer.lower()
    data = _default_schema()

    if "closed stance" in text:
        data["stance"] = "closed"
    elif "square stance" in text:
        data["stance"] = "square"
    elif "open stance" in text:
        data["stance"] = "open"

    if any(s in text for s in ["unstable", "off balance", "poor balance"]):
        data["balance"] = "unstable"
    elif any(s in text for s in ["stable", "balanced", "good balance"]):
        data["balance"] = "stable"
    elif "standing" in text and "fall" not in text and "off balance" not in text:
        data["balance"] = "stable"

    if any(s in text for s in ["hands together", "both hands together", "connected"]):
        data["hand_position"] = "together"
    elif any(s in text for s in ["hands separated", "hands apart", "disconnected", "one hand"]):
        data["hand_position"] = "separated"
    elif "left hand" in text and "right hand" in text and "paddle" in text:
        data["hand_position"] = "separated"

    has_extended = any(s in text for s in ["fully extended", "full extension", "arm straight", "arms extended", "extended"])
    has_bent = any(s in text for s in ["bent arm", "elbow bent", "collapsed", "90 degree", "ninety degree", "bent"])
    if has_extended and has_bent:
        data["arm_structure"] = "partial"
    elif has_extended:
        data["arm_structure"] = "extended"
    elif any(s in text for s in ["partially extended", "partial extension", "semi bent"]):
        data["arm_structure"] = "partial"
    elif has_bent:
        data["arm_structure"] = "collapsed"

    if any(s in text for s in ["low finish", "follow through low"]):
        data["follow_through_direction"] = "low"
    elif any(s in text for s in ["high finish", "follow through high"]):
        data["follow_through_direction"] = "high"
    elif any(s in text for s in ["across body", "cross body", "across the body", "to the side of"]):
        data["follow_through_direction"] = "across_body"

    if any(s in text for s in ["excessive rotation", "too much rotation", "over rotation"]):
        data["body_rotation"] = "excessive"
    elif any(s in text for s in ["insufficient rotation", "limited rotation", "lack of rotation"]):
        data["body_rotation"] = "insufficient"
    elif any(s in text for s in ["good rotation", "proper rotation", "adequate rotation"]):
        data["body_rotation"] = "good"

    return data


def _coerce_generation_to_schema(generated_text: str) -> Tuple[dict, str]:
    answer = _extract_answer_segment(generated_text)
    if _is_invalid_answer(answer):
        return _default_schema(), "invalid"
    data = _parse_labeled_lines(answer)
    if any(v != "unknown" for v in data.values()):
        return data, "labeled"
    data = _parse_keyword_fallback(answer)
    if any(v != "unknown" for v in data.values()):
        return data, "keyword"
    return data, "unknown"


def _is_weak_text(answer: str) -> bool:
    t = answer.lower()
    weak_patterns = [
        "a, b, c",
        "combination of the stance",
        "similar to tennis",
        "standing in the room",
        "standing in a room",
        "no details",
    ]
    return any(p in t for p in weak_patterns)


def _extract_field_value(field: str, text: str) -> str:
    t = text.strip().lower().replace("-", "_")
    aliases = _FIELD_ALIASES[field]
    if t in aliases:
        return aliases[t]
    for raw, normalized in aliases.items():
        pattern = r"\b" + re.escape(raw).replace(r"\ ", r"\s+") + r"\b"
        if re.search(pattern, t, flags=re.IGNORECASE):
            return normalized
    return "unknown"


def _schema_to_labeled_text(data: dict) -> str:
    return (
        f"Stance: {data['stance']}\n"
        f"Balance: {data['balance']}\n"
        f"Hand position: {data['hand_position']}\n"
        f"Arm structure: {data['arm_structure']}\n"
        f"Follow-through direction: {data['follow_through_direction']}\n"
        f"Body rotation: {data['body_rotation']}"
    )


def _pose_json_path(frame_path: str) -> str:
    return frame_path.replace(os.sep + "frame" + os.sep, os.sep + "pose" + os.sep).replace(".jpg", ".json")


def _crop_player_region(image: Image.Image, frame_path: str) -> Image.Image:
    pose_path = _pose_json_path(frame_path)
    if not os.path.exists(pose_path):
        return image
    try:
        with open(pose_path, "r", encoding="utf-8") as f:
            payload = json.load(f)
        landmarks = payload.get("landmarks", {})
        xs = []
        ys = []
        for values in landmarks.values():
            if isinstance(values, list) and len(values) >= 2:
                x, y = values[0], values[1]
                if isinstance(x, (int, float)) and isinstance(y, (int, float)):
                    xs.append(float(x))
                    ys.append(float(y))
        if not xs or not ys:
            return image
        width, height = image.size
        x0 = max(0, int((min(xs) - 0.1) * width))
        y0 = max(0, int((min(ys) - 0.1) * height))
        x1 = min(width, int((max(xs) + 0.1) * width))
        y1 = min(height, int((max(ys) + 0.1) * height))
        if x1 <= x0 or y1 <= y0:
            return image
        return image.crop((x0, y0, x1, y1))
    except Exception:
        return image


def _generate_answer(
    image: Image.Image,
    prompt: str,
    processor,
    model,
    device,
    bad_words_ids: List[List[int]],
    max_new_tokens: int = 120,
    min_new_tokens: int = 20,
    num_beams: int = 3,
    do_sample: bool = False,
    temperature: float = 0.7,
) -> str:
    inputs = processor(images=image, text=prompt, return_tensors="pt").to(device)
    input_len = inputs["input_ids"].shape[-1]
    with torch.no_grad():
        out = model.generate(
            **inputs,
            max_new_tokens=max_new_tokens,
            min_new_tokens=min_new_tokens,
            num_beams=num_beams,
            do_sample=do_sample,
            top_p=0.9 if do_sample else None,
            temperature=temperature if do_sample else None,
            no_repeat_ngram_size=3,
            repetition_penalty=1.1,
            bad_words_ids=bad_words_ids,
        )
    return processor.tokenizer.decode(out[0][input_len:], skip_special_tokens=True).strip()


def _retry_unknown_fields(
    image: Image.Image,
    processor,
    model,
    device,
    data: dict,
    bad_words_ids: List[List[int]],
    meta: dict,
) -> dict:
    updated = dict(data)
    for field in _SCHEMA_KEYS:
        if updated[field] != "unknown":
            continue
        prompt = "Question: Analyze this pickleball forehand-drive keyframe. " + _FIELD_PROMPTS[field] + " Answer:"
        field_text = _generate_answer(
            image=image,
            prompt=prompt,
            processor=processor,
            model=model,
            device=device,
            bad_words_ids=bad_words_ids,
            max_new_tokens=24,
            min_new_tokens=1,
            num_beams=1,
            do_sample=False,
        )
        value = _extract_field_value(field, field_text)
        if value in _ALLOWED[field] and value != "unknown" and "/" not in field_text and "|" not in field_text:
            updated[field] = value
            meta["source"][field] = "field_retry"
            meta["confidence"][field] = 0.55
    return updated


def _apply_temporal_smoothing(results: List[Dict], window: int = 3) -> List[Dict]:
    half = window // 2
    smoothed = []
    for i, item in enumerate(results):
        analysis = item.get("llm_analysis")
        if not isinstance(analysis, dict):
            smoothed.append(item)
            continue
        updated = dict(analysis)
        meta = updated.get("_meta", _default_meta())
        for field in _SCHEMA_KEYS:
            center = str(updated.get(field, "unknown"))
            if center != "unknown":
                continue
            votes = []
            for j in range(max(0, i - half), min(len(results), i + half + 1)):
                neighbor = results[j].get("llm_analysis")
                if isinstance(neighbor, dict):
                    v = str(neighbor.get(field, "unknown"))
                    if v != "unknown":
                        votes.append(v)
            if votes:
                top, count = Counter(votes).most_common(1)[0]
                if count >= 2 or len(votes) == 1:
                    conf_votes = []
                    for j in range(max(0, i - half), min(len(results), i + half + 1)):
                        neighbor = results[j].get("llm_analysis")
                        if isinstance(neighbor, dict):
                            nmeta = neighbor.get("_meta", {})
                            c = nmeta.get("confidence", {}).get(field, 0.0)
                            if c:
                                conf_votes.append(float(c))
                    mean_conf = sum(conf_votes) / len(conf_votes) if conf_votes else 0.0
                    if mean_conf >= 0.55:
                        updated[field] = top
                        meta["source"][field] = "temporal_smoothing"
                        meta["confidence"][field] = max(meta["confidence"].get(field, 0.0), 0.6)
        updated["_meta"] = meta
        smoothed.append({**item, "llm_analysis": updated})
    return smoothed


def analyze_forehand_frame(image_path: str, vision_llm_client: dict) -> dict:
    if not os.path.exists(image_path):
        raise FileNotFoundError(f"Image not found: {image_path}")

    processor = vision_llm_client["processor"]
    model = vision_llm_client["model"]
    device = vision_llm_client["device"]

    try:
        image = Image.open(image_path).convert("RGB")
    except Exception as e:
        raise ValueError(f"Failed to load image: {str(e)}")

    image_for_model = _crop_player_region(image, image_path)
    tokenizer = processor.tokenizer
    bad_words = ["http", "https", "www", ".com", ".org", "docs.google"]
    bad_words_ids = []
    for token in bad_words:
        ids = tokenizer(token, add_special_tokens=False).input_ids
        if ids:
            bad_words_ids.append(ids)

    structured_text = _generate_answer(
        image=image_for_model,
        prompt=forehand_prompt(),
        processor=processor,
        model=model,
        device=device,
        bad_words_ids=bad_words_ids,
    )
    if len(structured_text) < 10 or _is_invalid_answer(_extract_answer_segment(structured_text)):
        structured_text = _generate_answer(
            image=image_for_model,
            prompt=forehand_prompt(),
            processor=processor,
            model=model,
            device=device,
            bad_words_ids=bad_words_ids,
            max_new_tokens=100,
            min_new_tokens=15,
            num_beams=1,
            do_sample=True,
            temperature=0.7,
        )

    data, mode = _coerce_generation_to_schema(structured_text)
    meta = _default_meta()
    answer_seg = _extract_answer_segment(structured_text)
    weak_main = _is_weak_text(answer_seg)
    for field in _SCHEMA_KEYS:
        if data[field] != "unknown":
            meta["source"][field] = "blip_structured"
            meta["confidence"][field] = (0.75 if mode == "labeled" else 0.62) if not weak_main else 0.45

    evidence_text = _generate_answer(
        image=image_for_model,
        prompt=forehand_evidence_prompt(),
        processor=processor,
        model=model,
        device=device,
        bad_words_ids=bad_words_ids,
        max_new_tokens=110,
        min_new_tokens=10,
        num_beams=2,
        do_sample=False,
    )
    evidence_data = _parse_keyword_fallback(_extract_answer_segment(evidence_text))
    weak_evidence = _is_weak_text(_extract_answer_segment(evidence_text))
    for field in _SCHEMA_KEYS:
        if data[field] == "unknown" and evidence_data[field] != "unknown" and not weak_evidence:
            data[field] = evidence_data[field]
            meta["source"][field] = "evidence_rule"
            meta["confidence"][field] = 0.58

    if weak_main and weak_evidence:
        for field in ["stance", "follow_through_direction", "body_rotation"]:
            if meta["source"][field] == "blip_structured" and meta["confidence"][field] < 0.5:
                data[field] = "unknown"
                meta["source"][field] = "unknown"
                meta["confidence"][field] = 0.0

    if any(v == "unknown" for v in data.values()):
        data = _retry_unknown_fields(image_for_model, processor, model, device, data, bad_words_ids, meta)

    data["_meta"] = meta
    print(f"Raw model text: {structured_text}")
    print(f"Evidence text: {evidence_text}")
    print(f"Generated text:\n{_schema_to_labeled_text(data)}")
    return data


def analyze_frames_batch(frame_dir: str, vision_llm_client: dict, frame_files: List[str] = None) -> List[Dict]:
    if frame_files is None:
        frame_files = sorted([f for f in os.listdir(frame_dir) if f.lower().endswith((".jpg", ".jpeg"))])
    results = []
    for frame_file in frame_files:
        frame_path = os.path.join(frame_dir, frame_file)
        if not os.path.exists(frame_path):
            print(f"Warning: Frame not found: {frame_path}")
            continue
        try:
            print(f"Analyzing frame: {frame_file}")
            analysis = analyze_forehand_frame(frame_path, vision_llm_client)
            results.append({"frame": frame_file, "llm_analysis": analysis})
        except Exception as e:
            print(f"Error analyzing {frame_file}: {str(e)}")
            results.append({"frame": frame_file, "llm_analysis": None, "error": str(e)})
    return _apply_temporal_smoothing(results)


def save_frame_analyses(results: List[Dict], output_path: str):
    os.makedirs(os.path.dirname(output_path), exist_ok=True)
    with open(output_path, "w", encoding="utf-8") as f:
        json.dump(results, f, indent=2)
    print(f"Saved {len(results)} frame analyses to: {output_path}")
