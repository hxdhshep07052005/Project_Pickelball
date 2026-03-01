"""
Compatibility CLI for website chat_handler.php.

Input: path to JSON file containing chat messages
Output: JSON { success, response } to stdout
"""

import argparse
import json
import os
import sys

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
if BASE_DIR not in sys.path:
    sys.path.insert(0, BASE_DIR)

from llm.llm_client import get_llm_response  # noqa: E402


def main():
    parser = argparse.ArgumentParser(description="Get LLM chat response")
    parser.add_argument("messages_file", help="Path to JSON file containing messages")
    args = parser.parse_args()

    try:
        if not os.path.exists(args.messages_file):
            raise Exception(f"Messages file not found: {args.messages_file}")

        with open(args.messages_file, "r", encoding="utf-8") as f:
            messages = json.load(f)

        if not isinstance(messages, list) or not messages:
            raise Exception("Invalid messages format: expected a non-empty list")

        try:
            response = get_llm_response(messages) or ""
        except Exception:
            response = ""

        if not response.strip():
            response = (
                "Thank you for your question. Based on your analysis, keep focusing "
                "on the identified issues and practice consistently for 15-30 minutes daily."
            )

        print(json.dumps({"success": True, "response": response}, indent=2), flush=True)

    except Exception as e:
        print(
            json.dumps(
                {
                    "success": False,
                    "error": str(e),
                },
                indent=2,
            ),
            flush=True,
        )
        sys.exit(1)


if __name__ == "__main__":
    main()
