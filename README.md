# Pickleball Training Application

## Project Introduction
Pickleball Training Application is an AI-powered web platform designed to help players improve their pickleball techniques through structured practice and intelligent feedback.

The system combines a web stack (PHP + MySQL) with Python-based AI pipelines to deliver:
- upload-based video analysis,
- real-time action tracking,
- shadowing practice with guided posture references,
- and a pickleball-focused chatbot for follow-up coaching support.

This project is built for practical training use, where users can practice independently while still receiving model-assisted guidance.

## Demo Video

<video controls width="900">
  <source src="video_demo/0301.mp4" type="video/mp4">
  Your browser does not support the video tag.
</video>

## Core Features

### 1) Video Analysis
Users upload a technique video and choose the corresponding skill type.  
The backend processes the video through the AI pipeline and returns actionable analysis feedback for improvement.

### 2) Action Prediction
Users upload short clips and receive predicted action labels with confidence scores.  
This module helps quickly verify what movement the model recognizes.

### 3) Shadowing Mode
A real-time guided practice mode where users follow pose references and train movement sequence and consistency with camera input.

### 4) Live Action Detection
Webcam-based action detection for immediate recognition during practice sessions without requiring file upload.

### 5) Pickleball Chatbot
A chatbot module specialized for pickleball-related questions and analysis follow-up support.

## Technology Stack

- **Frontend:** PHP, HTML, CSS, JavaScript  
- **Backend:** PHP + Python services  
- **AI/ML:** MediaPipe, YOLO, LSTM, Vision-Language pipeline, LLM integration flow  
- **Database:** MySQL  
- **Runtime Environment:** XAMPP (Apache, PHP, MySQL)

## Project Goals

- Build a complete web-based pickleball training platform.
- Integrate AI models into practical coaching workflows.
- Support both upload-based and real-time training experiences.
- Improve accessibility and consistency of technique feedback.

## Team Members

- Đặng Đình Hòa  
- Lê Việt Hùng  
- Trần Gia Khánh  
- Nguyễn Gia Nam  
- Phạm Gia Bảo  
- Nguyễn Đức Thành