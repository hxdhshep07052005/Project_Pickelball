# Pickleball Training Chatbot API Documentation

## Overview

This API provides endpoints for uploading pickleball training videos, analyzing technique, and generating coaching feedback.

## Base URL

```
http://localhost:8000
```

## Setup

1. Install dependencies:
```bash
pip install -r requirements.txt
```

2. Start the server:
```bash
cd back_end
python main.py
```

Or with uvicorn:
```bash
cd back_end
uvicorn main:app --reload
```

3. Configure LLM (optional):
```bash
# Set environment variables
export LLM_PROVIDER=openai  # or "anthropic", "ollama"
export LLM_MODEL=gpt-4      # or "claude-3-opus-20240229", etc.
export OPENAI_API_KEY= "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"
```

## Endpoints

### 1. Health Check

**GET** `/health`

Check if the API is running.

**Response:**
```json
{
  "status": "healthy"
}
```

---

### 2. Upload Video

**POST** `/api/upload-video`

Upload a video file (3-5 seconds) for analysis.

**Request:**
- Method: `POST`
- Content-Type: `multipart/form-data`
- Body:
  - `file`: Video file (mp4, mov, avi, mkv, webm)
  - `skill`: (optional) Skill name, default: "drive_forehand"

**Response:**
```json
{
  "success": true,
  "session_id": "550e8400-e29b-41d4-a716-446655440000",
  "skill": "drive_forehand",
  "filename": "video.mp4",
  "message": "Video uploaded successfully"
}
```

**Example (curl):**
```bash
curl -X POST "http://localhost:8000/api/upload-video" \
  -F "file=@video.mp4" \
  -F "skill=drive_forehand"
```

**Example (Python):**
```python
import requests

with open("video.mp4", "rb") as f:
    files = {"file": f}
    data = {"skill": "drive_forehand"}
    response = requests.post(
        "http://localhost:8000/api/upload-video",
        files=files,
        data=data
    )
    result = response.json()
    session_id = result["session_id"]
```

---

### 3. Analyze Video

**POST** `/api/analyze-video`

Run the full analysis pipeline on an uploaded video.

**Request:**
- Method: `POST`
- Content-Type: `application/json`
- Body:
```json
{
  "session_id": "550e8400-e29b-41d4-a716-446655440000",
  "skill": "drive_forehand"
}
```

**Response:**
```json
{
  "success": true,
  "session_id": "550e8400-e29b-41d4-a716-446655440000",
  "skill": "drive_forehand",
  "frame_count": 10,
  "pose_count": 10,
  "phase_count": 10,
  "feedback_path": "D:\\chatbot\\back_end\\data\\feedback\\550e8400-e29b-41d4-a716-446655440000_feedback.json",
  "message": "Analysis completed successfully"
}
```

**Example (curl):**
```bash
curl -X POST "http://localhost:8000/api/analyze-video" \
  -H "Content-Type: application/json" \
  -d '{
    "session_id": "550e8400-e29b-41d4-a716-446655440000",
    "skill": "drive_forehand"
  }'
```

**Example (Python):**
```python
import requests

response = requests.post(
    "http://localhost:8000/api/analyze-video",
    json={
        "session_id": session_id,
        "skill": "drive_forehand"
    }
)
result = response.json()
```

---

### 4. Get Coaching Feedback

**POST** `/api/chat`

Generate natural language coaching feedback using LLM.

**Request:**
- Method: `POST`
- Content-Type: `application/json`
- Body:
```json
{
  "session_id": "550e8400-e29b-41d4-a716-446655440000",
  "skill": "drive_forehand"
}
```

**Response:**
```json
{
  "success": true,
  "session_id": "550e8400-e29b-41d4-a716-446655440000",
  "skill": "drive_forehand",
  "feedback": "Great effort on your shadow swing! I noticed a few areas we can work on...",
  "raw_feedback": [
    {
      "code": "FH04",
      "issue": "Low acceleration through swing",
      "severity": "medium",
      "tip": "Accelerate your paddle more as you swing forward."
    }
  ],
  "message": "Coaching feedback generated successfully"
}
```

**Example (curl):**
```bash
curl -X POST "http://localhost:8000/api/chat" \
  -H "Content-Type: application/json" \
  -d '{
    "session_id": "550e8400-e29b-41d4-a716-446655440000",
    "skill": "drive_forehand"
  }'
```

**Example (Python):**
```python
import requests

response = requests.post(
    "http://localhost:8000/api/chat",
    json={
        "session_id": session_id,
        "skill": "drive_forehand"
    }
)
result = response.json()
coaching_feedback = result["feedback"]
```

---

## Full Pipeline Example

```python
import requests

# Step 1: Upload video
with open("my_video.mp4", "rb") as f:
    files = {"file": f}
    data = {"skill": "drive_forehand"}
    upload_response = requests.post(
        "http://localhost:8000/api/upload-video",
        files=files,
        data=data
    )
    session_id = upload_response.json()["session_id"]

# Step 2: Analyze video
analyze_response = requests.post(
    "http://localhost:8000/api/analyze-video",
    json={"session_id": session_id, "skill": "drive_forehand"}
)
print(f"Analysis: {analyze_response.json()}")

# Step 3: Get coaching feedback
chat_response = requests.post(
    "http://localhost:8000/api/chat",
    json={"session_id": session_id, "skill": "drive_forehand"}
)
coaching = chat_response.json()["feedback"]
print(f"Coaching: {coaching}")
```

---

## Error Responses

All endpoints return standard HTTP status codes:

- `200 OK`: Success
- `400 Bad Request`: Invalid input (wrong file type, missing parameters)
- `404 Not Found`: Session not found, file not found
- `500 Internal Server Error`: Server error during processing

Error response format:
```json
{
  "detail": "Error message description"
}
```

---

## Testing

Run the test script:
```bash
python back_end/test_api.py
```

Make sure to update `TEST_VIDEO_PATH` in the script to point to your test video.

---

## LLM Configuration

The LLM client supports multiple providers. Configure via environment variables:

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

**Ollama (local):**
```bash
export LLM_PROVIDER=ollama
export LLM_MODEL=llama2
export OLLAMA_URL=http://localhost:11434/api/chat
```

If no LLM is configured, the API will return a placeholder response based on the structured feedback.










