import json
import os
from typing import List, Dict
from PIL import Image
import torch
from prompt import forehand_prompt


def analyze_forehand_frame(image_path: str, vision_llm_client: dict) -> dict:
    """
    Analyze a single frame using Hugging Face Vision-Language model.
    
    Args:
        image_path: Path to the image file
        vision_llm_client: Dictionary containing processor, model, device from init_vision_llm_client
    
    Returns:
        dict: Structured analysis of the frame
    """
    if not os.path.exists(image_path):
        raise FileNotFoundError(f"Image not found: {image_path}")
    
    processor = vision_llm_client["processor"]
    model = vision_llm_client["model"]
    device = vision_llm_client["device"]
    
    try:
        image = Image.open(image_path).convert('RGB')
    except Exception as e:
        raise ValueError(f"Failed to load image: {str(e)}")
    
    prompt_text = forehand_prompt()
    
    inputs = processor(image, prompt_text, return_tensors="pt").to(device)
    
    with torch.no_grad():
        out = model.generate(
            **inputs, 
            max_length=500,  
            num_beams=5,     
            temperature=0.7, 
            do_sample=True 
        )
    
    generated_text = processor.decode(out[0], skip_special_tokens=True)
    print(f"Model generated text: {generated_text[:200]}...") 
    
    json_text = _extract_json_from_text(generated_text)
    
    try:
        data = json.loads(json_text)
        if isinstance(data, dict):
            all_placeholders = all(
                str(v).strip() in ["...", ".", "", "... "] or 
                str(v).strip().startswith("...") 
                for v in data.values()
            )
            if all_placeholders:
                print(f"Warning: JSON contains only placeholder values, parsing from text instead")
                print(f"Generated text: {generated_text}")
                data = _parse_text_to_structured_format(generated_text)
    except json.JSONDecodeError as e:
        print(f"Parsing structured data from descriptive text (this is normal for BLIP models)")
        print(f"Generated text: {generated_text}")
        data = _parse_text_to_structured_format(generated_text)
    
    return data


def _extract_json_from_text(text: str) -> str:
    """
    Extract JSON object from text that might contain other text.
    
    Args:
        text: Text that may contain a JSON object
    
    Returns:
        str: JSON string
    """
    start_idx = text.find('{')
    end_idx = text.rfind('}')
    
    if start_idx != -1 and end_idx != -1 and end_idx > start_idx:
        return text[start_idx:end_idx + 1]
    
    return text


def _parse_text_to_structured_format(text: str) -> dict:
    """
    Parse unstructured text description into structured format.
    This extracts information from the BLIP model's descriptive output.
    
    Args:
        text: Unstructured text description from BLIP model
    
    Returns:
        dict: Structured analysis with extracted values
    """
    text_lower = text.lower()
    
    stance = "unknown"
    if any(term in text_lower for term in ["open stance", "open position", "open"]):
        if "closed stance" not in text_lower and "closed position" not in text_lower:
            stance = "open"
    if "closed stance" in text_lower or "closed position" in text_lower:
        stance = "closed"
    elif "square stance" in text_lower or "square position" in text_lower:
        stance = "square"
    
    balance = "unknown"
    if any(term in text_lower for term in ["stable", "balanced", "good balance", "well balanced"]):
        if "unstable" not in text_lower:
            balance = "stable"
    if any(term in text_lower for term in ["unstable", "off balance", "poor balance", "losing balance"]):
        balance = "unstable"
    
    arm_extension = "unknown"
    if any(term in text_lower for term in ["fully extended", "full extension", "arm fully extended", "extended arm"]):
        arm_extension = "full"
    elif any(term in text_lower for term in ["partially extended", "partial extension", "somewhat extended"]):
        arm_extension = "partial"
    elif any(term in text_lower for term in ["bent arm", "arm bent", "bent", "elbow bent"]):
        arm_extension = "bent"
    
    follow_through = "unknown"
    if any(term in text_lower for term in ["low finish", "low follow", "follow through low", "finishes low"]):
        follow_through = "low"
    elif any(term in text_lower for term in ["high finish", "high follow", "follow through high", "finishes high", "high"]):
        follow_through = "high"
    elif any(term in text_lower for term in ["across body", "across the body", "across", "cross body"]):
        follow_through = "across_body"
    
    body_rotation = "unknown"
    if any(term in text_lower for term in ["good rotation", "proper rotation", "adequate rotation", "sufficient rotation"]):
        body_rotation = "good"
    elif any(term in text_lower for term in ["insufficient rotation", "lack of rotation", "minimal rotation", "not enough rotation"]):
        body_rotation = "insufficient"
    elif any(term in text_lower for term in ["excessive rotation", "too much rotation", "over rotation", "over-rotation"]):
        body_rotation = "excessive"
    
    return {
        "stance": stance,
        "balance": balance,
        "arm_extension": arm_extension,
        "follow_through_direction": follow_through,
        "body_rotation": body_rotation
    }


def analyze_frames_batch(frame_dir: str, vision_llm_client: dict, frame_files: List[str] = None) -> List[Dict]:
    """
    Analyze multiple frames in a directory using Hugging Face Vision-Language model.
    
    Args:
        frame_dir: Directory containing frame images
        vision_llm_client: Dictionary containing processor, model, device from init_vision_llm_client
        frame_files: Optional list of specific frame filenames to process.
                    If None, processes all .jpg files in frame_dir
    
    Returns:
        List of dicts, each containing frame analysis results with frame filename
    """
    if frame_files is None:
        frame_files = sorted([f for f in os.listdir(frame_dir) if f.lower().endswith(('.jpg', '.jpeg'))])
    
    results = []
    
    for frame_file in frame_files:
        frame_path = os.path.join(frame_dir, frame_file)
        
        if not os.path.exists(frame_path):
            print(f"Warning: Frame not found: {frame_path}")
            continue
        
        try:
            print(f"Analyzing frame: {frame_file}")
            analysis = analyze_forehand_frame(frame_path, vision_llm_client)
            
            result = {
                "frame": frame_file,
                "llm_analysis": analysis
            }
            results.append(result)
            
        except Exception as e:
            print(f"Error analyzing {frame_file}: {str(e)}")
            results.append({
                "frame": frame_file,
                "llm_analysis": None,
                "error": str(e)
            })
    
    return results


def save_frame_analyses(results: List[Dict], output_path: str):
    """
    Save frame analysis results to JSON file.
    
    Args:
        results: List of analysis results from analyze_frames_batch
        output_path: Path to save JSON file
    """
    os.makedirs(os.path.dirname(output_path), exist_ok=True)
    
    with open(output_path, "w", encoding="utf-8") as f:
        json.dump(results, f, indent=2)
    
    print(f"Saved {len(results)} frame analyses to: {output_path}")
