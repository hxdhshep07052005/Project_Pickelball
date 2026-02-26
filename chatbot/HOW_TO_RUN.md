# How to Run the Pickleball Training Chatbot

## Quick Start Guide

### Step 1: Start the Backend Server

1. Open a terminal/command prompt
2. Navigate to the project directory:
   ```bash
   cd D:\chatbot\back_end
   ```

3. Start the FastAPI server:
   ```bash
   python main.py
   ```

4. You should see output like:
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
   ```

5. **Keep this terminal window open** - the server must stay running!

---

### Step 2: Open the Frontend

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

   **Using Python 3:**
   ```bash
   python -m http.server 3000
   ```

   **Or using Python 2:**
   ```bash
   python -m SimpleHTTPServer 3000
   ```

4. Open your browser and go to:
   ```
   http://localhost:3000
   ```

---

### Step 3: Test the Chatbot

1. In the browser, you should see the "Pickleball Training Chatbot" interface

2. **Select Skill:**
   - Choose "Drive Forehand" from the dropdown

3. **Upload Video:**
   - Click "📹 Click to select video file"
   - Select a video file (3-5 seconds, max 50MB)
   - Supported formats: mp4, mov, avi, mkv, webm

4. **Start Analysis:**
   - Click "🚀 Start Analysis"
   - Watch the progress steps:
     - 1️⃣ Uploading video...
     - 2️⃣ Analyzing video...
     - 3️⃣ Generating feedback...

5. **View Feedback:**
   - Once complete, your personalized coaching feedback will appear below

---

## Troubleshooting

### Backend Server Issues

**Problem:** `ModuleNotFoundError: No module named 'fastapi'`
- **Solution:** Install dependencies:
  ```bash
  pip install -r requirements.txt
  ```

**Problem:** Port 8000 already in use
- **Solution:** Change the port in `back_end/main.py`:
  ```python
  uvicorn.run(app, host="0.0.0.0", port=8001)  # Change to 8001
  ```
  Then update `front_end/app.js`:
  ```javascript
  const API_BASE_URL = 'http://localhost:8001/api';  // Change to 8001
  ```

**Problem:** CORS errors in browser console
- **Solution:** Make sure the backend server is running and accessible at `http://localhost:8000`

### Frontend Issues

**Problem:** "Failed to fetch" or network errors
- **Solution:** 
  1. Check that backend server is running
  2. Verify the API URL in `front_end/app.js` matches your backend port
  3. Try opening `http://localhost:8000` in browser to verify backend is accessible

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

---

## Complete System Architecture

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
│  - upload_video │
│  - analyze_video│
│  - chat         │
└─────────────────┘
```

---

## Testing Checklist

- [ ] Backend server starts without errors
- [ ] Can access `http://localhost:8000` in browser
- [ ] Can access `http://localhost:8000/docs` (API documentation)
- [ ] Frontend opens in browser
- [ ] Can select video file
- [ ] Video uploads successfully
- [ ] Analysis completes
- [ ] Coaching feedback appears

---

## Next Steps

Once everything is working:
1. Test with different videos
2. Try different skills (when you add more)
3. Check the API documentation at `http://localhost:8000/docs`
4. Customize the frontend styling if needed

---

## Need Help?

- Check backend terminal for error messages
- Check browser console (F12) for frontend errors
- Verify all dependencies are installed
- Make sure both servers are running










