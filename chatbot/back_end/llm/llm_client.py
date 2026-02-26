import os
from typing import List, Dict

# Configuration
# Set your LLM provider via environment variable or config file
LLM_PROVIDER = os.getenv("LLM_PROVIDER", "").lower()  # Options: "openai", "anthropic", "ollama", or "" for placeholder
LLM_MODEL = os.getenv("LLM_MODEL", "gpt-4")  # Model name


def get_llm_response(messages: List[Dict[str, str]]) -> str:
    """
    Send messages to LLM API and return response.
    
    Args:
        messages: List of message dicts with 'role' and 'content' keys
                 Format: [{"role": "system", "content": "..."}, ...]
    
    Returns:
        str: LLM generated response text
    """
    
    provider = LLM_PROVIDER.lower() if LLM_PROVIDER else ""
    
    try:
        if provider == "openai":
            return _get_openai_response(messages)
        elif provider == "anthropic":
            return _get_anthropic_response(messages)
        elif provider == "ollama":
            return _get_ollama_response(messages)
        else:
            # Default: Use placeholder response (no LLM configured)
            return _get_placeholder_response(messages)
    except Exception as e:
        # If any LLM provider fails, fall back to placeholder
        print(f"LLM provider '{provider}' failed: {str(e)}. Using placeholder response.")
        return _get_placeholder_response(messages)


def _get_openai_response(messages: List[Dict[str, str]]) -> str:
    """Get response from OpenAI API."""
    try:
        from openai import OpenAI
        
        # Check if API key is set
        api_key = os.getenv("OPENAI_API_KEY")
        if not api_key:
            raise Exception("OPENAI_API_KEY environment variable not set")
        
        client = OpenAI(api_key=api_key)
        response = client.chat.completions.create(
            model=LLM_MODEL,
            messages=messages,
            max_tokens=1000,  # Increased to allow more detailed feedback
            temperature=0.7
        )
        return response.choices[0].message.content
    
    except ImportError:
        raise Exception("OpenAI library not installed. Install with: pip install openai")
    except Exception as e:
        raise Exception(f"OpenAI API error: {str(e)}")


def _get_anthropic_response(messages: List[Dict[str, str]]) -> str:
    """Get response from Anthropic (Claude) API."""
    try:
        import anthropic
        
        client = anthropic.Anthropic()
        
        # Convert messages format if needed
        # Anthropic uses slightly different format
        response = client.messages.create(
            model=LLM_MODEL,
            max_tokens=1000,  # Increased to allow more detailed feedback
            messages=messages
        )
        return response.content[0].text
    
    except ImportError:
        raise ImportError(
            "Anthropic library not installed. Install with: pip install anthropic"
        )
    except Exception as e:
        raise Exception(f"Anthropic API error: {str(e)}")


def _get_ollama_response(messages: List[Dict[str, str]]) -> str:
    """Get response from Ollama (local models)."""
    try:
        import requests
        
        # Ollama API endpoint (default: http://localhost:11434)
        ollama_url = os.getenv("OLLAMA_URL", "http://localhost:11434/api/chat")
        
        # Convert messages format for Ollama
        response = requests.post(
            ollama_url,
            json={
                "model": LLM_MODEL,
                "messages": messages,
                "stream": False
            }
        )
        response.raise_for_status()
        return response.json()["message"]["content"]
    
    except ImportError:
        raise ImportError(
            "Requests library not installed. Install with: pip install requests"
        )
    except Exception as e:
        raise Exception(f"Ollama API error: {str(e)}")


def _get_placeholder_response(messages: List[Dict[str, str]]) -> str:
    """
    Placeholder response when no LLM provider is configured.
    This extracts user question and feedback data to provide context-aware responses.
    """
    if not messages:
        return "No feedback available."
    
    # Extract system prompt, context, and user question
    system_prompt = next((m["content"] for m in messages if m["role"] == "system"), "")
    user_messages = [m["content"] for m in messages if m["role"] == "user"]
    
    # The last user message is usually the actual question
    user_question = user_messages[-1] if user_messages else ""
    
    # Extract context from earlier user messages (analysis results)
    context_message = user_messages[0] if len(user_messages) > 1 else ""
    
    # Extract feedback data from context
    feedback_data = []
    coaching_feedback = ""
    
    if context_message:
        # Try to extract coaching feedback
        if "Coaching Feedback:" in context_message:
            parts = context_message.split("Coaching Feedback:")
            if len(parts) > 1:
                coaching_feedback = parts[1].split("\n\n")[0].strip()
        
        # Try to extract technical issues
        if "Technical Issues Detected:" in context_message:
            issues_part = context_message.split("Technical Issues Detected:")[1]
            # Parse issues (format: "- issue: tip")
            for line in issues_part.split("\n"):
                if line.strip().startswith("-"):
                    parts = line.strip()[1:].split(":", 1)
                    if len(parts) == 2:
                        feedback_data.append({
                            "issue": parts[0].strip(),
                            "tip": parts[1].strip()
                        })
    
    # Build response based on user's question
    question_lower = user_question.lower()
    
    if "routine" in question_lower or "daily" in question_lower:
        response = "Here's a recommended daily practice routine:\n\n"
        response += "**Morning Routine (15-20 minutes):**\n"
        response += "1. Warm-up (3-5 min): Light stretching, arm circles, leg swings\n"
        response += "2. Shadow practice (5-7 min): Focus on the technique issues identified in your analysis\n"
        response += "3. Form drills (5-7 min): Work on specific corrections from your feedback\n\n"
        response += "**Evening Routine (10-15 minutes):**\n"
        response += "1. Review your analysis feedback\n"
        response += "2. Practice corrections slowly\n"
        response += "3. Cool-down stretches\n\n"
        if coaching_feedback:
            response += f"Focus on: {coaching_feedback[:100]}...\n\n"
        if feedback_data:
            response += "Key areas to practice daily:\n"
            for item in feedback_data[:3]:  # Top 3 issues
                response += f"• {item.get('issue', '')}: {item.get('tip', '')[:80]}...\n"
        response += "\nConsistency is key - practice daily for best results!"
        
    elif "schedule" in question_lower or "practice" in question_lower:
        response = "Recommended practice schedule:\n\n"
        response += "**Weekly Schedule:**\n"
        response += "• Monday, Wednesday, Friday: Full practice (30-45 min)\n"
        response += "• Tuesday, Thursday: Light practice (15-20 min)\n"
        response += "• Saturday: Video analysis session\n"
        response += "• Sunday: Rest day\n\n"
        response += "**Daily Breakdown:**\n"
        response += "• Warm-up: 5 minutes\n"
        response += "• Technique work: 10-15 minutes\n"
        response += "• Shadow practice: 5-10 minutes\n"
        response += "• Cool-down: 5 minutes\n\n"
        if feedback_data:
            response += "Focus your practice sessions on:\n"
            for item in feedback_data[:3]:
                response += f"• {item.get('issue', '')}\n"
        response += "\nTrack your progress weekly with video recordings!"
        
    elif "improve" in question_lower or "better" in question_lower:
        response = "To improve your technique, follow this plan:\n\n"
        if feedback_data:
            response += "**Priority Areas:**\n"
            for idx, item in enumerate(feedback_data[:5], 1):
                response += f"{idx}. {item.get('issue', '')}\n"
                response += f"   → {item.get('tip', '')}\n\n"
        response += "**Improvement Timeline:**\n"
        response += "• Week 1-2: Focus on correcting form issues\n"
        response += "• Week 3-4: Build muscle memory\n"
        response += "• Week 5-8: Noticeable improvement\n"
        response += "• Month 3+: Significant progress\n\n"
        response += "Practice 15-30 minutes daily, focusing on one issue at a time."
        
    elif "time" in question_lower or "long" in question_lower or "when" in question_lower:
        response = "Timeline for improvement:\n\n"
        response += "• **2-4 weeks**: Noticeable form improvements\n"
        response += "• **1-2 months**: Muscle memory develops, more consistent technique\n"
        response += "• **3-6 months**: Significant improvement, technique becomes natural\n\n"
        if feedback_data:
            response += "Focus on these areas for faster progress:\n"
            for item in feedback_data[:3]:
                response += f"• {item.get('issue', '')}\n"
        response += "\nConsistent daily practice accelerates improvement!"
        
    elif "mistake" in question_lower or "error" in question_lower or "wrong" in question_lower:
        response = "Common mistakes to avoid:\n\n"
        if feedback_data:
            response += "Based on your analysis:\n"
            for item in feedback_data:
                response += f"• **{item.get('issue', '')}**: {item.get('tip', '')}\n\n"
        response += "**General Mistakes:**\n"
        response += "• Rushing the swing - build speed gradually\n"
        response += "• Stopping at contact - always follow through\n"
        response += "• Using only arms - engage core and rotate body\n"
        response += "• Poor balance - maintain stable athletic stance\n\n"
        response += "Focus on correcting these one at a time."
        
    elif "drill" in question_lower or "exercise" in question_lower:
        response = "Effective drills and exercises:\n\n"
        response += "**Form Drills:**\n"
        response += "• Shadow swings: Practice form without ball (5-10 min daily)\n"
        response += "• Slow motion swings: Break down each phase (3-5 reps)\n"
        response += "• Mirror work: Check form and body position\n\n"
        response += "**Technique Drills:**\n"
        response += "• Wall practice: Improve consistency\n"
        response += "• Partner drills: Practice with feedback\n"
        response += "• Video analysis: Record and review weekly\n\n"
        if feedback_data:
            response += "**Specific to Your Analysis:**\n"
            for item in feedback_data[:3]:
                response += f"• Focus on {item.get('issue', '')}: {item.get('tip', '')[:60]}...\n"
        response += "\nPractice these drills 15-20 minutes daily."
        
    elif "warm" in question_lower or "warmup" in question_lower:
        response = "Proper warm-up routine:\n\n"
        response += "**Before Practice (10 minutes):**\n"
        response += "1. Light cardio (3-5 min): Jogging, jumping jacks, or skipping\n"
        response += "2. Dynamic stretching (3-5 min):\n"
        response += "   • Arm circles (forward and backward)\n"
        response += "   • Leg swings (front and side)\n"
        response += "   • Torso twists\n"
        response += "   • Shoulder rotations\n"
        response += "3. Sport-specific movements (2-3 min):\n"
        response += "   • Shadow swings\n"
        response += "   • Light footwork drills\n\n"
        response += "A proper warm-up prevents injury and improves performance!"
        
    elif "strength" in question_lower or "fitness" in question_lower:
        response = "Strength training for pickleball:\n\n"
        response += "**Core Exercises (2-3x/week):**\n"
        response += "• Planks: 3 sets of 30-60 seconds\n"
        response += "• Russian twists: 3 sets of 15-20 reps\n"
        response += "• Dead bugs: 3 sets of 10-12 reps\n\n"
        response += "**Upper Body (2x/week):**\n"
        response += "• Push-ups: 3 sets of 10-15 reps\n"
        response += "• Rows: 3 sets of 10-12 reps\n"
        response += "• Shoulder rotations: 2 sets of 15 reps\n\n"
        response += "**Lower Body (2x/week):**\n"
        response += "• Squats: 3 sets of 12-15 reps\n"
        response += "• Lunges: 3 sets of 10 per leg\n"
        response += "• Calf raises: 3 sets of 15-20 reps\n\n"
        response += "Strength training complements your technique practice!"
        
    else:
        # Generic response but include actual feedback
        response = ""
        if coaching_feedback:
            response += f"Based on your analysis: {coaching_feedback}\n\n"
        if feedback_data:
            response += "Key areas to focus on:\n"
            for item in feedback_data[:5]:
                response += f"• {item.get('issue', '')}: {item.get('tip', '')}\n"
            response += "\n"
        response += "For best results, practice 15-30 minutes daily, focusing on one technique at a time. "
        response += "You should see improvement within 2-4 weeks of consistent practice."
    
    return response
