# Pickleball Training Application

A web-based AI coaching platform for pickleball players. The application helps users improve their technique through video analysis, action prediction, and real-time shadowing practice with visual feedback.

## About the Project

The Pickleball Training Application is designed to bring professional-level feedback to players of all skill levels. Users can upload videos of their swings, receive AI-powered technical analysis, practice movements with guided ghost overlays, and get personalized coaching through an intelligent chatbot.

## Team

| Member | Role |
|--------|------|
| **Đặng Đình Hòa** | Website development |
| **Lê Việt Hùng** | Video Analysis model |
| **Trần Gia Khánh** | Action Prediction model |
| **Nguyễn Gia Nam** | Shadowing model |
| **Phạm Gia Bảo** | Action Prediction model |
| **Nguyễn Đức Thành** | Report writing |

## Website Overview

### Video Analysis

Upload a video of your serve or drive shot. The system extracts key frames, detects your pose using MediaPipe, and evaluates your technique across phases (Ready, Backswing, Contact, Follow-through). An AI coach generates personalized feedback and suggestions to improve your form.

### Action Prediction

Upload a video clip to classify the action as either Drive Forehand or Drive Backhand. The system uses YOLO11n-Pose for keypoint detection and an LSTM model to predict the stroke type. After prediction, you receive full video analysis and coaching feedback.

### Shadowing Practice

Practice techniques such as Serve, Forehand Drive, Backhand Drive, Smash, and Volley using a ghost trainer. Your webcam feed is overlaid with a reference ghost silhouette. The system scores how closely your pose matches the target in real time, helping you refine your movement step by step.

### Live Action Detection

Use your webcam to detect actions in real time during practice. The system identifies strokes as they happen and provides immediate feedback.

### Chatbot

Ask questions about your video analysis results. The chatbot uses your stored analysis data and an LLM to answer technique-related questions and give tailored advice.

## Technologies

PHP, MySQL, MediaPipe, YOLO11n-Pose, LSTM, Python (OpenCV, NumPy, MediaPipe), JavaScript, HTML5, CSS3.

## Setup

Install XAMPP (Apache and MySQL), create the `pickleball_training` database, copy `user/backend/config.example.php` to `config.php` and configure it, then open `http://localhost/pickelball/` in your browser. Python with OpenCV, MediaPipe, NumPy, and PyTorch is required for the AI features.
