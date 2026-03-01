import os
from vison_forehand import analyze_forehand_frame, analyze_frames_batch, save_frame_analyses
from init_llm import init_vision_llm_client



def test_batch_frames():
    """Test analyzing multiple frames."""
    print("\n" + "=" * 60)
    print("Testing Batch Frame Analysis")
    print("=" * 60)

    vision_llm_client = init_vision_llm_client(
        model_name="Salesforce/blip2-opt-2.7b"  # BLIP-2 can follow prompts properly
    )

    frame_dir = r"D:\chatbot\back_end\data\frame\test_chat_2"

    if not os.path.exists(frame_dir):
        frame_dir = os.path.join("back_end", "data", "frame", "test_chat")

    if not os.path.exists(frame_dir):
        print(f"ERROR: Frame directory not found: {frame_dir}")
        return

    print(f"Analyzing frames in: {frame_dir}")
    results = analyze_frames_batch(frame_dir, vision_llm_client)

    print(f"\nAnalyzed {len(results)} frames")

    output_path = r"D:\chatbot\back_end\data\vision_llm\test_chat_llm_analyses_2.json"
    if not os.path.exists(os.path.dirname(output_path)):
        output_path = os.path.join("back_end", "data", "vision_llm", "test_chat_llm_analyses_2.json")

    save_frame_analyses(results, output_path)

    if results:
        print("\nExample result:")
        print(results[0])


if __name__ == "__main__":

    test_batch_frames()
