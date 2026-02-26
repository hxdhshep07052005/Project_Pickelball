# Pickleball Training Chatbot

A web application for analyzing pickleball training videos and providing AI-powered coaching feedback.

## Features

- 📹 Video upload and processing
- 🎯 Pose estimation using MediaPipe
- 📊 Phase detection (READY, BACKSWING, CONTACT, FOLLOW_THROUGH)
- 🤖 AI-powered coaching feedback via LLM
- 🎓 Skill-specific analysis (currently: Drive Forehand)

## System Architecture

```
User Uploads Video (3-5s)
    ↓
Frame Extraction (sharpness-based, ~10 frames)
    ↓
Pose Estimation (MediaPipe keypoints)
    ↓
Phase Detection (swing phases)
    ↓
Rule-based Evaluation (technical feedback)
    ↓
LLM Coaching Feedback (natural language)
```

## Setup

### 1. Install Dependencies

```bash
pip install -r requirements.txt
```

### 2. Configure LLM (Optional)

Set environment variables for your LLM provider:

**OpenAI:**
```bash
export LLM_PROVIDER=openai
export LLM_MODEL=gpt-4
export OPENAI_API_KEY=your_key_here
```

**Anthropic:**
```bash
export LLM_PROVIDER=anthropic
export LLM_MODEL=claude-3-opus-20240229
export ANTHROPIC_API_KEY=your_key_here
```

**Ollama (Local):**
```bash
export LLM_PROVIDER=ollama
export LLM_MODEL=llama2
export OLLAMA_URL=http://localhost:11434/api/chat
```

If no LLM is configured, the system will use a placeholder response.

### 3. Start the Server

```bash
cd back_end
python main.py
```

Or with auto-reload:
```bash
cd back_end
uvicorn main:app --reload
```

The API will be available at `http://localhost:8000`

## API Usage

See [API_DOCUMENTATION.md](API_DOCUMENTATION.md) for detailed API documentation.

### Quick Start Example

```python
import requests

# 1. Upload video
with open("video.mp4", "rb") as f:
    response = requests.post(
        "http://localhost:8000/api/upload-video",
        files={"file": f},
        data={"skill": "drive_forehand"}
    )
    session_id = response.json()["session_id"]

# 2. Analyze video
response = requests.post(
    "http://localhost:8000/api/analyze-video",
    json={"session_id": session_id, "skill": "drive_forehand"}
)

# 3. Get coaching feedback
response = requests.post(
    "http://localhost:8000/api/chat",
    json={"session_id": session_id, "skill": "drive_forehand"}
)
coaching = response.json()["feedback"]
print(coaching)
```

## Testing

Run the test script:
```bash
python back_end/test_api.py
```

Make sure to update `TEST_VIDEO_PATH` in the script to point to your test video.

## Project Structure

```
chatbot/
├── back_end/
│   ├── api/
│   │   ├── upload_video.py      # Video upload endpoint
│   │   ├── analyze_video.py      # Analysis pipeline endpoint
│   │   └── chat.py               # LLM feedback endpoint
│   ├── vision/
│   │   ├── frame_extractor.py    # Extract frames from video
│   │   └── pose_estimation.py    # MediaPipe pose detection
│   ├── analysis/
│   │   ├── drive_forehand_phase.py  # Phase detection
│   │   └── drive_forehand_rule.py   # Rule-based evaluation
│   ├── llm/
│   │   ├── llm_client.py         # LLM API integration
│   │   ├── prompt_builder.py     # Build LLM prompts
│   │   └── prompts/
│   │       └── drive_forehand_prompt.txt
│   ├── data/
│   │   ├── video/                # Uploaded videos
│   │   ├── frame/                # Extracted frames
│   │   ├── pose/                 # Pose keypoints JSON
│   │   ├── phase/                # Phase detection results
│   │   └── feedback/              # Structured feedback JSON
│   ├── main.py                   # FastAPI server
│   └── test_api.py               # API test script
├── front_end/                    # Frontend (to be implemented)
├── requirements.txt
└── README.md
```

## Development Status

✅ **Completed:**
- Video frame extraction
- Pose estimation
- Phase detection
- Rule-based evaluation
- FastAPI endpoints
- LLM integration structure

🚧 **In Progress:**
- Frontend UI
- Additional skills (beyond drive_forehand)

## License

[Your License Here]










