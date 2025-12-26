# Pickleball Training Application

Web-based pickleball training application with ghost trainer using MediaPipe pose detection.

## Quick Start

### 1. Install XAMPP
Download and install from: https://www.apachefriends.org/
- Install **Apache** and **MySQL** components

### 2. Setup Project
1. Copy project to: `C:\xampp\htdocs\pickelball\`
2. Copy config file:
   ```bash
   cp user/backend/config.example.php user/backend/config.php
   ```
3. Edit `user/backend/config.php` with your credentials

### 3. Start XAMPP
1. Open XAMPP Control Panel
2. Start **Apache** and **MySQL**
3. Open phpMyAdmin: http://localhost/phpmyadmin
4. Create database: `pickleball_training`

### 4. Run Application
Open browser: `http://localhost/pickelball/`

## Python Requirements (for Shadowing)
```bash
pip install opencv-python mediapipe numpy
```

## Project Structure
- `main/` - Main application pages
- `user/` - Authentication system
- `shawdowing/` - Python pose detection scripts
- `assets/` - Ghost trainer assets

## Technologies
- PHP 7.4+, MySQL
- MediaPipe (Pose Detection)
- JavaScript, HTML5, CSS3
