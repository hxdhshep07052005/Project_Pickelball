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
            max_tokens=500,
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
            max_tokens=500,
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
    This extracts key feedback points and returns a basic response.
    """
    if not messages:
        return "No feedback available."
    
    # Extract the last user message (the actual question)
    user_messages = [m["content"] for m in messages if m.get("role") == "user"]
    user_question = user_messages[-1] if user_messages else ""
    
    # Provide context-aware response based on question keywords
    question_lower = user_question.lower()
    
    if 'improve' in question_lower or 'better' in question_lower or 'how can i' in question_lower:
        return (
            "To improve this technique, I recommend:\n\n"
            "1. **Focus on Form**: Pay attention to the key issues identified in your analysis. Work on correcting one issue at a time.\n\n"
            "2. **Practice Regularly**: Aim for 15-30 minutes of focused practice daily. Consistency is more important than duration.\n\n"
            "3. **Use Video Feedback**: Record yourself regularly to track your progress and identify areas that still need work.\n\n"
            "4. **Shadow Practice**: Practice the movements without equipment to build muscle memory for proper form.\n\n"
            "You should see noticeable improvement within 2-4 weeks of consistent practice. Keep at it!"
        )
    elif 'schedule' in question_lower or 'practice schedule' in question_lower:
        return (
            "For optimal improvement, here's a recommended practice schedule:\n\n"
            "• **Daily**: 15-30 minutes of focused technique work\n"
            "• **3-4 times per week**: Shadow practice with video reference\n"
            "• **Weekly**: Record and analyze your technique to track progress\n"
            "• **Rest Days**: Take 1-2 days off per week for recovery\n\n"
            "Remember: Quality over quantity. Short, focused sessions are better than long, unfocused ones."
        )
    elif 'routine' in question_lower or 'daily routine' in question_lower or 'daily practice' in question_lower:
        return (
            "Here's a recommended daily practice routine:\n\n"
            "1. **Warm-up (5 min)**: Light stretching and movement to prepare your body\n"
            "2. **Technique Focus (10-15 min)**: Work on specific issues from your analysis\n"
            "3. **Shadow Practice (5-10 min)**: Mimic proper form without equipment\n"
            "4. **Cool-down (5 min)**: Review what you worked on and plan for next session\n\n"
            "Total time: 25-35 minutes. Focus on proper form throughout!"
        )
    elif 'time' in question_lower or 'long' in question_lower or 'when' in question_lower or 'timeline' in question_lower:
        return (
            "Here's a realistic timeline for improvement:\n\n"
            "• **Weeks 1-2**: Focus on understanding and correcting form issues identified in your analysis\n"
            "• **Weeks 3-4**: Begin to see muscle memory developing, movements feel more natural\n"
            "• **Weeks 5-8**: Noticeable improvement in technique consistency\n"
            "• **Month 3+**: Significant improvement with continued practice\n\n"
            "Everyone progresses at different rates. Stay consistent, be patient, and celebrate small wins along the way!"
        )
    else:
        # Extract context from analysis if available
        context_msg = user_messages[0] if len(user_messages) > 1 else ""
        
        if 'coaching feedback' in context_msg.lower() or 'technical issues' in context_msg.lower():
            return (
                "Thank you for your question! Based on your analysis, I recommend:\n\n"
                "• Focus on the key issues mentioned in your feedback\n"
                "• Practice 15-30 minutes daily with proper form\n"
                "• Record yourself regularly to track progress\n"
                "• Work on one technique aspect at a time\n\n"
                "You should see improvement within 2-4 weeks of consistent practice. Keep up the great work!"
            )
        else:
            return (
                "Thank you for your question! Based on the analysis, I recommend focusing on the key areas mentioned in the feedback. "
                "For best results, practice 15-30 minutes daily, focusing on one technique at a time. "
                "You should see improvement within 2-4 weeks of consistent practice."
            )
