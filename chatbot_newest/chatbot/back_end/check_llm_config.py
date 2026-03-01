#!/usr/bin/env python3
"""
Quick script to check which text LLM model is configured.
Run this from the back_end directory: python check_llm_config.py
"""

import os
import sys

sys.path.insert(0, os.path.dirname(__file__))

from llm.llm_client import get_current_llm_config, LLM_PROVIDER, LLM_MODEL

def main():
    print("=" * 60)
    print("Text LLM Configuration Check")
    print("=" * 60)
    print()

    env_provider = os.getenv("LLM_PROVIDER", "")
    env_model = os.getenv("LLM_MODEL", "")

    print("Environment Variables:")
    if env_provider:
        print(f"  LLM_PROVIDER = '{env_provider}'")
    else:
        print(f"  LLM_PROVIDER = (not set)")

    if env_model:
        print(f"  LLM_MODEL = '{env_model}'")
    else:
        print(f"  LLM_MODEL = (not set)")
    print()

    config = get_current_llm_config()

    print("Current Configuration:")
    print(f"  Provider: {config['provider']}")
    print(f"  Model: {config['model']}")
    print(f"  Status: {config['status']}")
    print()

    print("Additional Information:")
    if config['provider'] == 'placeholder':
        print("  [OK] System will use built-in placeholder response")
        print("  [OK] No API keys or external services required")
        print("  [OK] Feedback will be formatted from structured data")
        print()
        print("To use a real LLM, set environment variables:")
        print("  Windows: set LLM_PROVIDER=openai")
        print("  Linux/Mac: export LLM_PROVIDER=openai")
        print()
        print("Available providers:")
        print("  - openai (requires OPENAI_API_KEY)")
        print("  - anthropic (requires ANTHROPIC_API_KEY)")
        print("  - ollama (requires local Ollama server)")
    elif config['provider'] == 'openai':
        api_key = os.getenv("OPENAI_API_KEY")
        if api_key:
            print(f"  [OK] OPENAI_API_KEY is set (length: {len(api_key)} chars)")
        else:
            print("  [WARNING] OPENAI_API_KEY is NOT set - API calls will fail")
    elif config['provider'] == 'anthropic':
        api_key = os.getenv("ANTHROPIC_API_KEY")
        if api_key:
            print(f"  [OK] ANTHROPIC_API_KEY is set (length: {len(api_key)} chars)")
        else:
            print("  [WARNING] ANTHROPIC_API_KEY is NOT set - API calls will fail")
    elif config['provider'] == 'ollama':
        ollama_url = os.getenv("OLLAMA_URL", "http://localhost:11434/api/chat")
        print(f"  Ollama URL: {ollama_url}")
        print("  Make sure Ollama is running locally")

    print()
    print("=" * 60)

if __name__ == "__main__":
    main()






