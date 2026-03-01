import os
from typing import List, Dict

ENV_PROVIDER = os.getenv("LLM_PROVIDER", "").lower()
LLM_PROVIDER = "ollama" if ENV_PROVIDER in ("", "openai") else ENV_PROVIDER
LLM_MODEL = os.getenv("LLM_MODEL", "llama3:latest")


def get_current_llm_config():
    """
    Get the current LLM configuration.

    Returns:
        dict: Dictionary with 'provider', 'model', and 'status' keys
    """
    provider = LLM_PROVIDER.lower() if LLM_PROVIDER else "ollama"

    if provider == "openai":
        status = f"Using OpenAI API with model: {LLM_MODEL}"
    elif provider == "anthropic":
        status = f"Using Anthropic (Claude) API with model: {LLM_MODEL}"
    elif provider == "ollama":
        status = f"Using Ollama (local) with model: {LLM_MODEL}"
    else:
        status = "Using Placeholder Response (no external LLM configured)"

    return {
        "provider": provider if provider else "placeholder",
        "model": LLM_MODEL if provider else "N/A (placeholder)",
        "status": status
    }


def get_llm_response(messages: List[Dict[str, str]]) -> str:
    """
    Send messages to LLM API and return response.

    Args:
        messages: List of message dicts with 'role' and 'content' keys
                 Format: [{"role": "system", "content": "..."}, ...]

    Returns:
        str: LLM generated response text
    """

    provider = LLM_PROVIDER.lower() if LLM_PROVIDER else "ollama"

    if provider:
        print(f"📝 Using LLM Provider: {provider.upper()}, Model: {LLM_MODEL}")
    else:
        print("📝 Unsupported provider configured; falling back to Ollama")
        provider = "ollama"

    try:
        if provider == "anthropic":
            return _get_anthropic_response(messages)
        elif provider == "ollama":
            return _get_ollama_response(messages)
        else:
            return _get_ollama_response(messages)
    except Exception as e:
        print(f"LLM provider '{provider}' failed: {str(e)}. Using placeholder response.")
        return _get_placeholder_response(messages)


def _get_openai_response(messages: List[Dict[str, str]]) -> str:
    """Get response from OpenAI API."""
    try:
        from openai import OpenAI

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

        ollama_url = os.getenv("OLLAMA_URL", "http://localhost:11434/api/chat")
        model = LLM_MODEL
        if model.startswith("gpt-"):
            model = "llama3:latest"

        payload_chat = {
            "model": model,
            "messages": messages,
            "stream": False,
        }

        response = requests.post(ollama_url, json=payload_chat, timeout=120)
        if response.ok:
            data = response.json()
            if isinstance(data, dict) and "message" in data and "content" in data["message"]:
                return data["message"]["content"]

        base_url = ollama_url.replace("/api/chat", "")
        generate_url = base_url.rstrip("/") + "/api/generate"
        prompt = "\n".join(
            [f"{m.get('role', 'user').upper()}: {m.get('content', '')}" for m in messages]
        )
        payload_generate = {
            "model": model,
            "prompt": prompt,
            "stream": False,
        }
        response = requests.post(generate_url, json=payload_generate, timeout=120)
        response.raise_for_status()
        data = response.json()
        if isinstance(data, dict) and "response" in data:
            return data["response"]
        raise Exception(f"Unexpected Ollama response format: {data}")

    except ImportError:
        raise ImportError(
            "Requests library not installed. Install with: pip install requests"
        )
    except Exception as e:
        raise Exception(f"Ollama API error: {str(e)}")


def _get_placeholder_response(messages: List[Dict[str, str]]) -> str:
    """
    Placeholder response when no LLM provider is configured.
    This extracts ALL feedback points and returns a comprehensive response.
    """
    if not messages:
        return "No feedback available."

    user_msg = next((m["content"] for m in messages if m["role"] == "user"), "")

    import json
    try:
        start_idx = user_msg.find("[")
        if start_idx != -1:
            bracket_count = 0
            end_idx = start_idx
            for i in range(start_idx, len(user_msg)):
                if user_msg[i] == '[':
                    bracket_count += 1
                elif user_msg[i] == ']':
                    bracket_count -= 1
                    if bracket_count == 0:
                        end_idx = i + 1
                        break

            json_str = user_msg[start_idx:end_idx]
            feedback = json.loads(json_str)

            if feedback and len(feedback) > 0:
                response_parts = [
                    "Great effort on your shadow swing! I've analyzed your technique and found the following areas we can work on:\n\n"
                ]

                for idx, item in enumerate(feedback, 1):
                    issue = item.get("issue", "")
                    tip = item.get("tip", "")
                    severity = item.get("severity", "").upper()

                    if issue and tip:
                        severity_indicator = ""
                        if severity == "HIGH":
                            severity_indicator = " (Important)"
                        elif severity == "MEDIUM":
                            severity_indicator = " (Moderate)"

                        response_parts.append(f"{idx}. **Issue: {issue}**{severity_indicator}\n")
                        response_parts.append(f"   **Advice:** {tip}\n\n")

                response_parts.append("Keep practicing these corrections and you'll see steady improvement in your technique!")

                return "".join(response_parts)
    except Exception as e:
        print(f"Error parsing feedback in placeholder response: {e}")
        pass

    return (
        "Great effort on your shadow swing! I noticed a few areas we can work on. "
        "Try to focus on your form and technique. Keep practicing and you'll see improvement!"
    )
