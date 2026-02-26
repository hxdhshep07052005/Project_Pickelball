"""
Global storage for Vision LLM client to avoid reloading on every request.
"""
vision_llm_client = None

def set_vision_llm_client(client):
    """Set the global Vision LLM client."""
    global vision_llm_client
    vision_llm_client = client

def get_vision_llm_client():
    """Get the global Vision LLM client."""
    return vision_llm_client




