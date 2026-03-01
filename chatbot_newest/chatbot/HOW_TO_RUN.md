# How to Run the Pickleball Training Chatbot

## 📋 Prerequisites

1. **Python 3.8+** installed
2. **Internet connection** (for first-time model downloads)
3. **Modern web browser** (Chrome, Firefox, Edge, etc.)

---

## 🚀 Quick Start (3 Steps)

### Step 1: Install Dependencies

Open a terminal/command prompt and navigate to the project root:

```bash
cd D:\chatbot
```

Install required Python packages:

```bash
pip install -r requirements.txt
```

**Optional - For Vision LLM (Enhanced Analysis):**
If you want enhanced visual analysis, install additional dependencies:

```bash
pip install transformers torch torchvision pillow accelerate
```

**Note:** Vision LLM is optional. The system works perfectly without it using MediaPipe pose data.

---

### Step 2: Start the Backend Server

Navigate to the backend directory:

```bash
cd back_end
```

Start the FastAPI server:

```bash
python main.py
```

**You should see:**
```
============================================================
Pickleball Training Chatbot API Server
============================================================
Server starting on:
  - Local:   http://localhost:8000
  - Local:   http://127.0.0.1:8000
  - Network: http://YOUR_IP:8000

API Documentation: http://localhost:8000/docs
============================================================

🚀 Starting server...
✅ Server started successfully!
INFO:     Uvicorn running on http://0.0.0.0:8000
```

**⚠️ Important:** Keep this terminal window open! The server must stay running.

**📝 Note:** On first run, if Vision LLM is enabled, it will download the model (~1.4GB) in the background. This takes 5-10 minutes but doesn't block the server - you can use it immediately.

---

### Step 3: Open the Frontend

You have **two options**:

#### Option A: Direct File Open (Simplest)
1. Open File Explorer
2. Navigate to: `D:\chatbot\front_end`
3. Double-click `index.html`
4. It will open in your default browser

#### Option B: Using a Local Web Server (Recommended)
1. Open a **NEW** terminal/command prompt (keep the backend server running!)
2. Navigate to the frontend directory:
   ```bash
   cd D:\chatbot\front_end
   ```
3. Start a simple web server:
   ```bash
   python -m http.server 3000
   ```
4. Open your browser and go to:
   ```
   http://localhost:3000
   ```

---

## 🎯 Using the System

### 1. Select Skill
- Choose either:
  - **Drive Forehand**
  - **Drive Two-Handed Backhand**

### 2. Upload Video
- Click "📹 Click to select video file"
- Select a video file (3-5 seconds recommended, max 50MB)
- Supported formats: `.mp4`, `.mov`, `.avi`, `.mkv`, `.webm`

### 3. Automatic Skill Detection
- After upload, the system will **automatically detect** whether your video is forehand or backhand
- The detected skill will be displayed: **"🎯 Detected Skill: Drive Forehand (85% confidence)"**
- The skill dropdown will auto-update if detection differs from your selection

### 4. Start Analysis
- Click "🚀 Start Analysis"
- Watch the progress:
  - **1️⃣ Uploading video...** (includes skill detection)
  - **2️⃣ Analyzing video...** (frame extraction, pose estimation, phase detection)
  - **3️⃣ Generating feedback...** (rule evaluation, LLM feedback)

### 5. View Feedback
- Once complete, your personalized coaching feedback will appear
- Feedback includes:
  - Technical issues identified
  - Specific advice for improvement
  - Phase-by-phase analysis

---

## ⚙️ Configuration Options

### Disable Vision LLM (Faster Startup)

If you want to skip Vision LLM loading for faster startup:

**Windows:**
```cmd
cd back_end
set ENABLE_VISION_LLM=false
python main.py
```

Or use the provided script:
```cmd
cd back_end
start_server_no_vision_llm.bat
```

**Linux/Mac:**
```bash
cd back_end
export ENABLE_VISION_LLM=false
python main.py
```

Or use the provided script:
```bash
cd back_end
chmod +x start_server_no_vision_llm.sh
./start_server_no_vision_llm.sh
```

### Change Server Port

If port 8000 is already in use:

1. Edit `back_end/main.py` (line 204):
   ```python
   uvicorn.run(app, host="0.0.0.0", port=8001)  # Change to 8001
   ```

2. Edit `front_end/app.js` (line 2):
   ```javascript
   const API_BASE_URL = 'http://localhost:8001/api';  // Change to 8001
   ```

---

## 🔍 System Features

### Automatic Skill Detection
- Uses `Model_2dongtac.pth` to classify videos
- Displays detected skill with confidence percentage
- Auto-updates skill selection if needed

### Video Analysis Pipeline
1. **Frame Extraction**: Extracts keyframes based on sharpness, motion, pose detection
2. **Pose Estimation**: MediaPipe extracts 33 body landmarks
3. **Phase Detection**: Identifies READY, BACKSWING, CONTACT, FOLLOW_THROUGH phases
4. **Rule Evaluation**: Checks technical rules (elbow extension, balance, etc.)
5. **Vision LLM Analysis** (optional): Visual analysis of stance, balance, body rotation
6. **LLM Feedback**: Generates natural language coaching advice

### Supported Skills
- ✅ **Drive Forehand**
- ✅ **Drive Two-Handed Backhand**

---

## 🐛 Troubleshooting

### Backend Server Issues

**Problem:** `ModuleNotFoundError: No module named 'fastapi'`
- **Solution:** Install dependencies:
  ```bash
  pip install -r requirements.txt
  ```

**Problem:** Port 8000 already in use
- **Solution:** Change the port (see Configuration Options above)

**Problem:** Server hangs during Vision LLM loading
- **Solution:** 
  - This is normal on first run (downloading ~1.4GB model)
  - Wait 5-15 minutes, or disable Vision LLM (see Configuration Options)
  - Server works immediately even while model is loading

**Problem:** `AttributeError: 'SymbolDatabase' object has no attribute 'GetPrototype'`
- **Solution:** Reinstall protobuf:
  ```bash
  pip install 'protobuf>=3.20.0,<4.0.0'
  ```

**Problem:** Skill classifier not working
- **Solution:** 
  - Ensure `Model_2dongtac.pth` is in `back_end/` directory
  - Check that PyTorch is installed: `pip install torch torchvision`
  - System will continue without skill detection if model is unavailable

### Frontend Issues

**Problem:** "Failed to fetch" or network errors
- **Solution:** 
  1. Check that backend server is running
  2. Verify the API URL in `front_end/app.js` matches your backend port
  3. Try opening `http://localhost:8000` in browser to verify backend is accessible
  4. Check browser console (F12) for detailed error messages

**Problem:** Video upload fails
- **Solution:**
  - Check file size (max 50MB)
  - Check file format (mp4, mov, avi, mkv, webm)
  - Check browser console for detailed error messages

**Problem:** Analysis takes too long or fails
- **Solution:**
  - Make sure video is 3-5 seconds
  - Ensure person is clearly visible in video
  - Check backend terminal for error messages
  - Try disabling Vision LLM for faster processing

**Problem:** CORS errors in browser console
- **Solution:** Make sure the backend server is running and accessible at `http://localhost:8000`

---

## 📊 System Architecture

```
┌─────────────────┐
│   Browser       │
│  (Frontend)     │  ← http://localhost:3000
│  index.html     │
│  app.js         │
└────────┬────────┘
         │ HTTP Requests
         │ (upload, analyze, chat)
         ▼
┌─────────────────┐
│  FastAPI Server  │
│  (Backend)      │  ← http://localhost:8000
│  main.py        │
│  ├─ upload_video│  (with skill detection)
│  ├─ analyze_video│ (frame extraction, pose, phases)
│  └─ chat        │  (LLM feedback)
└─────────────────┘
```

---

## ✅ Testing Checklist

- [ ] Backend server starts without errors
- [ ] Can access `http://localhost:8000` in browser
- [ ] Can access `http://localhost:8000/docs` (API documentation)
- [ ] Frontend opens in browser
- [ ] Can select video file
- [ ] Video uploads successfully
- [ ] Skill detection displays correctly
- [ ] Analysis completes
- [ ] Coaching feedback appears

---

## 📚 Additional Resources

- **API Documentation**: `http://localhost:8000/docs` (when server is running)
- **Troubleshooting Guide**: `back_end/TROUBLESHOOTING.md`
- **Quick Start**: `back_end/QUICK_START.md`
- **Skill Classifier Info**: `back_end/SKILL_CLASSIFIER_README.md`

---

## 🎓 What Works Without Vision LLM?

Everything! The system works perfectly without Vision LLM:
- ✅ Video upload and skill detection
- ✅ Frame extraction
- ✅ MediaPipe pose estimation
- ✅ Phase detection
- ✅ Rule-based evaluation
- ✅ LLM feedback generation

Only the Vision LLM visual analysis (stance, balance, etc.) is skipped, but the system still provides excellent feedback using pose data.

---

## 💡 Tips

1. **First Run**: Vision LLM download takes 5-10 minutes. You can use the system immediately - it will work with MediaPipe data while the model loads.

2. **Video Quality**: For best results, use clear videos with the player fully visible.

3. **Video Length**: 3-5 seconds is optimal. Longer videos work but may take more time to process.

4. **Network Access**: The server runs on `0.0.0.0`, so you can access it from other devices on your network using your IP address.

5. **API Testing**: Use `http://localhost:8000/docs` to test API endpoints directly.

---

## 🆘 Need Help?

1. Check backend terminal for error messages
2. Check browser console (F12) for frontend errors
3. Verify all dependencies are installed
4. Make sure both servers are running
5. Review `back_end/TROUBLESHOOTING.md` for detailed solutions

---

**Happy Training! 🏓**
