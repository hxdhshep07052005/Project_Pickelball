"""
Standalone script to get LLM chat response
Called from PHP to handle user questions about analysis
"""

import os
import sys
import json
import argparse

# Add current directory to path
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from llm.llm_client import get_llm_response


def main():
    """Command line interface"""
    parser = argparse.ArgumentParser(description="Get LLM chat response")
    parser.add_argument("messages_file", help="Path to JSON file containing messages")
    
    args = parser.parse_args()
    
    try:
        # Load messages from file
        if not os.path.exists(args.messages_file):
            raise Exception(f"Messages file not found: {args.messages_file}")
        
        with open(args.messages_file, 'r', encoding='utf-8') as f:
            messages = json.load(f)
        
        if not messages or not isinstance(messages, list):
            raise Exception("Invalid messages format: expected a list")
        
        # Get LLM response
        try:
            response = get_llm_response(messages)
            # Ensure response is a string
            if response is None:
                response = ""
        except Exception as llm_error:
            # If LLM fails, provide a helpful fallback based on the user's question
            user_question = ""
            for msg in messages:
                if msg.get('role') == 'user' and len(msg.get('content', '')) > 10:
                    user_question = msg.get('content', '')
                    break
            
            if 'improve' in user_question.lower() or 'better' in user_question.lower():
                response = "To improve this technique, focus on the key issues identified in your analysis. Practice 15-30 minutes daily with proper form, and you should see improvement within 2-4 weeks."
            elif 'schedule' in user_question.lower() or 'practice' in user_question.lower():
                response = "For best results, practice 15-30 minutes daily, 3-4 times per week. Include shadow practice and regular video analysis to track your progress."
            elif 'routine' in user_question.lower() or 'daily' in user_question.lower():
                response = "A good daily routine includes: 5 min warm-up, 10-15 min technique focus, 5-10 min shadow practice, and 5 min cool-down. Quality over quantity!"
            elif 'time' in user_question.lower() or 'long' in user_question.lower():
                response = "Most players see noticeable improvement within 2-4 weeks of consistent practice. Significant improvement typically comes after 2-3 months of regular training."
            else:
                response = "Thank you for your question! Based on the analysis, I recommend focusing on the key areas mentioned in the feedback. Practice 15-30 minutes daily for best results."
        
        if not response or response.strip() == "":
            response = "I understand your question. Based on the analysis, I recommend focusing on consistent practice and the specific techniques mentioned in the feedback."
        
        # Output JSON result
        result = {
            "success": True,
            "response": response
        }
        print(json.dumps(result, indent=2))
        
    except Exception as e:
        import traceback
        error_msg = str(e)
        error_trace = traceback.format_exc()
        
        result = {
            "success": False,
            "error": error_msg,
            "trace": error_trace
        }
        print(json.dumps(result, indent=2))
        sys.exit(1)


if __name__ == "__main__":
    main()

