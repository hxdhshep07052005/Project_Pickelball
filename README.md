# Pickleball Training Application - Website Development Documentation

This document is written for thesis defense and focuses on the Website Development scope: page behavior, backend processing flows, data movement, and AI integration.

## 1) Project Introduction

This is a web-based Pickleball training system that combines:

- Video Analysis (technique analysis from uploaded videos)
- Action Prediction (classifying player action)
- Shadowing Practice (real-time ghost trainer)
- Live Action Detection (webcam-based action detection)
- AI Coaching Chatbot (follow-up Q&A after analysis)

The system architecture uses PHP + MySQL for web/backend and Python for AI pipelines.

## Demo Video

- Demo file: [`video_demo/0301.mp4`](video_demo/0301.mp4)

<video src="video_demo/0301.mp4" controls width="900"></video>

## 2) Team Members

| Member | Role |
|---|---|
| Dang Dinh Hoa | Website development |
| Le Viet Hung | Video Analysis model |
| Tran Gia Khanh | Action Prediction model |
| Nguyen Gia Nam | Shadowing model |
| Pham Gia Bao | Action Prediction model |
| Nguyen Duc Thanh | Report writing |

## 3) Website Architecture Overview

High-level flow:

1. User interacts with frontend pages (`main/frontend`, `user/frontend`, `admin/frontend`)
2. Frontend calls PHP backend handlers (`main/backend`, `user/backend`, `admin/backend`)
3. Backend:
   - Validates input/authentication
   - Processes database operations via PDO/MySQL
   - Calls Python scripts for AI pages
   - Returns JSON or redirects with flash messages
4. Frontend updates UI based on response data

## 4) Important Database Areas

- `users`, `user_identities`, `user_preferences`, `user_sessions`
- `video_analyses`
- `action_predictions`
- OTP flows for user/admin actions (login, register, verify, change password)

## 5) Page-by-Page Technical Description (Frontend -> Backend -> DB/AI)

### 5.1 Main Website Pages

#### Page: `main/frontend/index.php`
- Purpose: Landing page and navigation hub
- Backend: No direct processing endpoint
- Behavior: Presents system introduction and routes users to feature modules

#### Page: `main/frontend/video_analysis.php`
- Purpose: Upload a video for technical analysis
- Main backend: `main/backend/video_analysis.php`
- Supporting backend: `main/backend/chat_handler.php` (follow-up coaching chat)
- Input: Video file + selected technique
- Backend validation:
  - Authentication (`require_auth.php`)
  - Request method (`POST`)
  - Upload constraints (error code, extension, size)
  - Technique whitelist
- Backend processing:
  1. Save uploaded video
  2. Run Python pipeline (`chatbot_newest/chatbot/back_end/run_analysis.py`)
  3. Parse JSON result
  4. Store record in `video_analyses`
  5. Set flash message and redirect back
- Output: Analysis result + coaching feedback + debug info (if needed)

#### Page: `main/frontend/action_prediction.php`
- Purpose: Predict action class (DriveForehand / DriveBackhand) from video
- Main backend: `main/backend/action_prediction.php`
- Supporting backend: `main/backend/chat_handler.php`
- Input: Video file
- Backend validation:
  - Authentication + request method
  - Upload constraints
- Backend processing:
  1. Save video
  2. Run Python classifier (`Action_Video_Prediction/predict_action.py`)
  3. Optionally chain technical analysis (`chatbot_newest/chatbot/back_end/run_analysis.py`)
  4. Insert prediction and analysis metadata into `action_predictions`
  5. Return JSON payload to frontend
- Output: Predicted action, confidence/probabilities, and optional analysis details

#### Page: `main/frontend/shadowing_select.php`
- Purpose: Select a shadowing practice technique
- Backend: `main/backend/shadowing_select.php`
- Processing:
  - Enumerate supported poses
  - Check required assets (`ghost_0.png`, `meta_0.npy`, `target_0.npy`)
  - Return readiness per pose (`hasAssets`)
- Output: UI cards with available/unavailable state

#### Page: `main/frontend/shadowing_practice.php`
- Purpose: Real-time shadowing with webcam + ghost overlay + similarity score
- Backend: `main/backend/shadowing_practice.php`
- Supporting API: `main/backend/shadowing_assets_api.php`
- Input: `GET pose`
- Backend validation:
  - Pose whitelist
  - Asset availability
- Frontend JS behavior:
  - Start webcam stream
  - Run MediaPipe Pose
  - Load ghost images + NPY stage data from API
  - Calculate similarity score and stage progression
  - Apply camera mirror for user-friendly view
- Output: Real-time stage coaching with visual guidance

#### Page: `main/frontend/live_action.php`
- Purpose: Real-time action detection from webcam frames
- Backend: `main/backend/live_action.php`
- Input: `POST action=predict|reset`, with frame payload for `predict`
- Backend processing:
  1. Validate action/frame
  2. Save temporary frame
  3. Run Python script (`Live_Action/live_predict.py`)
  4. Parse prediction
  5. Return JSON to frontend
- Output: Live action label and confidence updates

### 5.2 User Pages (Authentication / Profile / Settings)

#### Page: `user/frontend/login.php`
- Backends: `user/backend/login.php`, `google_login.php`, `google_callback.php`
- Behavior:
  - Standard login: email/password + captcha -> OTP verification
  - Google login: OAuth callback -> account binding -> OTP verification

#### Page: `user/frontend/register.php`
- Backend: `user/backend/register.php`
- Input: email, name, password, confirm password, captcha
- Processing: validate -> duplicate check -> create/update pending user -> send OTP -> redirect verify

#### Page: `user/frontend/verify.php`
- Backend: `user/backend/verify.php`
- Modes:
  - `login`
  - `registration`
  - `password_change`
- Processing: validate OTP, check expiration/attempts, execute mode-specific completion

#### Page: `user/frontend/profile.php`
- Backends: `user/backend/change_password.php`, `user/backend/verify.php`
- Processing: verify current password, enforce new password policy, OTP-confirm password change

#### Page: `user/frontend/settings.php`
- Backend: `user/backend/save_settings.php`
- Input: theme, language
- Processing: whitelist validation -> upsert `user_preferences` -> update session/cookies

### 5.3 Admin Pages

#### Page: `admin/frontend/login.php`
- Backend: `admin/backend/login.php`
- Processing: validate admin credential -> send OTP -> redirect verify

#### Page: `admin/frontend/verify.php`
- Backend: `admin/backend/verify.php`
- Processing: verify OTP -> set `$_SESSION['admin']` -> redirect dashboard

#### Page: `admin/frontend/dashboard.php`
- Backend: `admin/backend/get_chart_data.php`
- Input: `GET days`
- Processing: aggregate users/videos/predictions over selected range -> return chart JSON

#### Page: `admin/frontend/users.php`
- Backend: `admin/backend/delete_user.php`
- Input: JSON user `id`
- Processing: transactional deletion across related tables + media cleanup

#### Page: `admin/frontend/video_analyses.php`
- Backends: `admin/backend/view_video.php`, `admin/backend/delete_video.php`
- Processing: inspect analysis details and delete records

#### Page: `admin/frontend/action_predictions.php`
- Backends: `admin/backend/view_prediction.php`, `admin/backend/delete_prediction.php`
- Processing: inspect prediction details and delete records

## 6) Backend Function-Level Breakdown (Overview -> Detail)

Important note:
- Many backend files are procedural rather than class-based.
- For these files, the "function-level" section is represented as clear processing blocks.
- Named function/class examples include `chatbox_api.php` and `mailer.php`.

### 6.1 Main Backend (Core AI Pages)

#### `main/backend/action_prediction.php`
Overview:
- Receives uploaded video, predicts action class, optionally runs technical analysis, stores results
- Returns JSON response

Detailed blocks:
1. Authentication and request guard
2. Upload validation (error code, type, size)
3. Storage with unique naming
4. Python inference (`predict_action.py`)
5. Optional technical analysis chaining (`run_analysis.py`)
6. DB persistence (`action_predictions`)
7. JSON response assembly

Common errors:
- 401 unauthorized
- 405 invalid method
- 400 upload/input errors
- 500 Python/DB processing failures

#### `main/backend/video_analysis.php`
Overview:
- Handles analysis upload flow and stores result in `video_analyses`
- Uses flash session messages and redirect pattern

Detailed blocks:
1. Auth + POST validation
2. File + technique validation
3. Technique-match verification using `Live_Action/Model_2dongtac.pth`
4. Save upload to server
5. Run `chatbot_newest/chatbot/back_end/run_analysis.py`
6. Parse JSON output
7. Insert analysis record
8. Set flash message and redirect

Error strategy:
- Log details on backend
- Return user to UI with actionable status message

#### `main/backend/chat_handler.php`
Overview:
- Handles chatbot Q&A using existing analysis context
- Returns JSON response

Input:
- `session_id` (required)
- `message` (required)
- `analysis_id` (optional)

Detailed blocks:
1. Auth + method + JSON body validation
2. Pickleball-only scope filtering for user message
3. Load analysis context (`video_analyses`, fallback `action_predictions`)
4. Build model messages
5. Run `chatbot_newest/chatbot/back_end/chat_response.py`
6. Parse and return chatbot response JSON

#### `main/backend/live_action.php`
Overview:
- Endpoint for live webcam prediction/reset
- Returns JSON

Detailed blocks:
- `predict`: validate frame -> store temp frame -> run Python -> parse output -> return prediction
- `reset`: clear state/buffer -> return success

#### `main/backend/shadowing_select.php`
Overview:
- Builds pose catalog with readiness flags

Detailed blocks:
1. Define supported poses
2. Validate required assets per pose
3. Return pose metadata (`hasAssets`, labels, paths)

#### `main/backend/shadowing_practice.php`
Overview:
- Validates selected pose and builds runtime config

Detailed blocks:
1. Validate `GET pose`
2. Resolve pose asset directory
3. Check required stage assets
4. Return structured config (`pose`, `name`, `assetsPath`, `availablePoses`, `hasAssets`)

#### `main/backend/shadowing_assets_api.php`
Overview:
- Reads NPY data and returns JSON for frontend practice engine

Input:
- `pose`
- `type` in `meta|target`
- `stage` index

Detailed blocks:
1. Validate request parameters
2. Resolve NPY path
3. Run Python reader (`main/backend/read_npy.py`)
4. Return parsed JSON or structured error

#### `main/backend/chatbox_api.php` (Class helper)
Class: `ChatBoxAPI`

Methods:
1. `__construct($baseUrl, $timeout)`
2. `healthCheck()`
3. `uploadVideo($videoPath, $skill)`
4. `analyzeVideo($sessionId, $skill)`
5. `getFeedback($sessionId, $skill)`
6. `runFullAnalysis($videoPath, $skill)` (upload -> analyze -> feedback orchestration)

### 6.2 User Backend (Account & Auth)

#### `user/backend/login.php`
1. Validate input + captcha
2. Verify user/password
3. Generate OTP context
4. Send OTP via mailer
5. Redirect to verify flow

#### `user/backend/register.php`
1. Validate registration fields + captcha
2. Check duplicate user
3. Create/update pending account
4. Generate and send OTP
5. Redirect verify

#### `user/backend/verify.php`
Shared:
1. Validate OTP format
2. Enforce expiration and attempt limit
3. Verify hashed OTP

Mode-specific:
- login: create user session
- registration: activate account
- password_change: update password hash

#### `user/backend/change_password.php`
1. Validate auth + current password
2. Validate new password policy
3. Start OTP confirmation context

#### `user/backend/save_settings.php`
1. Validate theme/language whitelist
2. Upsert `user_preferences`
3. Sync session/cookie preferences

#### `user/backend/google_login.php` + `google_callback.php`
- OAuth state protection
- Token exchange + profile retrieval
- User/identity upsert
- OTP verification flow before full login

#### `user/backend/mailer.php`
Function: `sendOtpMail($config, $recipientEmail, $code, $context)`

Flow:
1. Open SMTP connection
2. EHLO / STARTTLS / AUTH
3. Send MIME message
4. Return boolean success/failure

### 6.3 Admin Backend

#### `admin/backend/login.php` + `admin/backend/verify.php`
- OTP-based admin authentication flow

#### `admin/backend/get_chart_data.php`
1. Normalize day range
2. Aggregate users, analyses, predictions
3. Return chart datasets JSON

#### `admin/backend/delete_user.php`
1. Validate user
2. Start transaction
3. Remove related records (`video_analyses`, `action_predictions`, preferences, sessions, identities, user)
4. Remove related media files
5. Commit

#### `admin/backend/delete_video.php` / `delete_prediction.php`
- Validate record
- Transactional record delete + media cleanup
- Return JSON status

#### `admin/backend/view_video.php` / `view_prediction.php`
- Validate ID
- Query detail + user metadata
- Decode JSON fields
- Return JSON detail payload

## 7) Defense Talking Points

- Clear layered architecture: Frontend PHP -> Backend PHP -> AI Python -> MySQL
- Complete OTP-based authentication for both user and admin
- Strong validation and error handling strategy (status codes, flash messages, JSON errors)
- AI outputs are stored and traceable for history and coaching chat context
- Real-time shadowing supports stage guidance with similarity scoring
- Admin dashboard supports analytics and governance operations

## 8) Technology Stack

- Web: PHP, HTML, CSS, JavaScript
- Database: MySQL (PDO)
- AI/ML: Python, MediaPipe, YOLO11n-Pose, LSTM, NumPy, OpenCV
- Local runtime: XAMPP (Apache + MySQL)
# Pickleball Training Application - Website Development Documentation

Tai lieu nay duoc viet theo huong bao ve do an, tap trung vao phan Website Development: mo ta tung trang, tung luong backend, va cach du lieu di qua he thong.

## 1) Gioi thieu du an

Day la he thong huan luyen Pickleball tren web, ket hop:
- Video Analysis (phan tich ky thuat tu video upload)
- Action Prediction (du doan dong tac)
- Shadowing Practice (ghost trainer theo thoi gian thuc)
- Live Action Detection (nhan dien truc tiep qua webcam)
- Chatbot hoi dap ky thuat

He thong duoc xay dung theo kieu PHP + MySQL cho web/backend, Python cho AI pipeline.

## Demo Video

- Video demo: [`video_demo/0301.mp4`](video_demo/0301.mp4)

<video src="video_demo/0301.mp4" controls width="900"></video>

## 2) Thanh vien nhom

| Thanh vien | Vai tro |
|---|---|
| **Dang Dinh Hoa** | Website development |
| **Le Viet Hung** | Video Analysis model |
| **Tran Gia Khanh** | Action Prediction model |
| **Nguyen Gia Nam** | Shadowing model |
| **Pham Gia Bao** | Action Prediction model |
| **Nguyen Duc Thanh** | Report writing |

## 3) Kien truc website (tong quan)

Luong tong:
1. User thao tac tren trang frontend (`main/frontend`, `user/frontend`, `admin/frontend`)
2. Frontend goi backend PHP (`main/backend`, `user/backend`, `admin/backend`)
3. Backend:
   - Validate input/authentication
   - Xu ly DB voi PDO/MySQL
   - Goi Python script (neu la AI pages)
   - Tra ve JSON hoac redirect + flash message
4. Frontend cap nhat giao dien theo ket qua tra ve

## 4) Cac bang/chuc nang backend quan trong

- `users`, `user_identities`, `user_preferences`, `user_sessions`
- `video_analyses`
- `action_predictions`
- OTP flow cho user/admin (login, register, verify, change password)

## 5) Mo ta chi tiet tung page (Frontend -> Backend -> DB/AI)

---

### 5.1 Main website pages

#### Page: `main/frontend/index.php`
- **Muc dich:** Trang landing gioi thieu he thong va dieu huong den cac module.
- **Backend lien quan:** Khong goi endpoint xu ly truc tiep.
- **Cach hoat dong:** Hien thi thong tin, button dieu huong sang cac trang tinh nang.

#### Page: `main/frontend/video_analysis.php`
- **Muc dich:** Upload video de phan tich ky thuat.
- **Backend chinh:** `main/backend/video_analysis.php`
- **Backend phu:** `main/backend/chat_handler.php` (hoi dap sau phan tich)
- **Input:** File video + skill level.
- **Validation backend:**
  - Check login (`require_auth.php`)
  - Check method POST
  - Check file upload error, extension, dung luong
  - Check skill hop le
- **Xu ly backend:**
  1. Luu file upload vao server
  2. Goi Python `run_analysis.py`
  3. Parse ket qua JSON
  4. Insert vao bang `video_analyses`
  5. Set flash message + redirect lai page
- **Output:** Trang hien ket qua phan tich va feedback.

#### Page: `main/frontend/action_prediction.php`
- **Muc dich:** Du doan dong tac (DriveForehand/DriveBackhand) tu video.
- **Backend chinh:** `main/backend/action_prediction.php`
- **Backend phu:** `main/backend/chat_handler.php`
- **Input:** File video.
- **Validation backend:**
  - Auth, POST method
  - Upload file constraints
- **Xu ly backend:**
  1. Save video
  2. Goi Python `predict_action.py`
  3. Co the chain sang phan tich ky thuat (`run_analysis.py`)
  4. Insert vao `action_predictions`
  5. Tra JSON prediction + analysis
- **Output:** Frontend render nhan dang dong tac + giai thich.

#### Page: `main/frontend/shadowing_select.php`
- **Muc dich:** Chon bai shadowing (Serve, DriveForehand, DriveBackhand, Smash, Volley).
- **Backend:** `main/backend/shadowing_select.php`
- **Input:** Khong co POST; backend check du lieu assets.
- **Xu ly backend:**
  - Duyet danh sach poses
  - Kiem tra folder assets va file bat buoc (`ghost_0.png`, `meta_0.npy`, `target_0.npy`)
  - Tra ve mang poses co field `hasAssets`
- **Output:** Frontend hien card ky thuat co/khong san sang.

#### Page: `main/frontend/shadowing_practice.php`
- **Muc dich:** Luyen tap shadowing theo webcam + ghost overlay + similarity score.
- **Backend:** `main/backend/shadowing_practice.php`
- **API bo tro:** `main/backend/shadowing_assets_api.php`
- **Input:** `GET pose`
- **Validation backend:**
  - Pose phai nam trong whitelist
  - Kiem tra assets ton tai
- **Xu ly backend:**
  - Tra ve `pose`, `name`, `hasAssets`, `assetsPath`, `availablePoses`
- **Xu ly frontend JS (trong page):**
  - Khoi tao MediaPipe Pose
  - Lay camera stream
  - Load ghost image + npy data qua `shadowing_assets_api.php`
  - Tinh similarity score, stage progression, cooldown, next stage
  - Mirror camera cho trai nghiem nguoi dung
- **Output:** Huan luyen real-time voi score va huong dan stage.

#### Page: `main/frontend/live_action.php`
- **Muc dich:** Nhan dien dong tac live qua webcam frame.
- **Backend:** `main/backend/live_action.php`
- **Input:** `POST action=predict/reset`, frame image (base64 or image payload).
- **Validation backend:**
  - Auth + method
  - Kiem tra frame voi action=predict
- **Xu ly backend:**
  1. Luu frame tam
  2. Goi Python `live_predict.py`
  3. Parse ket qua
  4. Tra JSON prediction
- **Output:** Frontend cap nhat ket qua theo thoi gian thuc.

---

### 5.2 User pages (authentication/profile/settings)

#### Page: `user/frontend/login.php`
- **Backend:** `user/backend/login.php`, `google_login.php`, `google_callback.php`
- **Cach hoat dong:**
  - Login thuong: email/password + captcha -> check DB -> gui OTP -> redirect verify
  - Login Google: redirect OAuth -> callback -> tao/cap nhat identity -> gui OTP

#### Page: `user/frontend/register.php`
- **Backend:** `user/backend/register.php`
- **Input:** email, name, password, confirm password, captcha
- **Xu ly:** validate -> check duplicate -> insert/update pending account -> gui OTP -> redirect verify

#### Page: `user/frontend/verify.php`
- **Backend:** `user/backend/verify.php`
- **Input:** OTP code (6 digits)
- **Xu ly backend theo mode:**
  - `login`: xac thuc OTP -> tao session user
  - `registration`: kich hoat account
  - `password_change`: cap nhat password hash moi
- **Error handling:** expiry, max attempts, sai OTP -> flash message + redirect an toan

#### Page: `user/frontend/profile.php`
- **Backend:** `user/backend/change_password.php` (+ `verify.php` cho OTP)
- **Xu ly:** verify current password, check rule password moi, gui OTP doi mat khau.

#### Page: `user/frontend/settings.php`
- **Backend:** `user/backend/save_settings.php`
- **Input:** theme, language
- **Xu ly:** whitelist validate -> upsert `user_preferences` -> cap nhat session/cookie -> redirect.

---

### 5.3 Admin pages

#### Page: `admin/frontend/login.php`
- **Backend:** `admin/backend/login.php`
- **Xu ly:** check admin credentials -> gui OTP -> redirect verify.

#### Page: `admin/frontend/verify.php`
- **Backend:** `admin/backend/verify.php`
- **Xu ly:** verify OTP -> set `$_SESSION['admin']` -> redirect dashboard.

#### Page: `admin/frontend/dashboard.php`
- **Backend:** `admin/backend/get_chart_data.php`
- **Input:** `GET days`
- **Xu ly:** aggregate so lieu user/videos/predictions theo ngay -> tra JSON cho chart.

#### Page: `admin/frontend/users.php`
- **Backend:** `admin/backend/delete_user.php`
- **Input:** JSON `id`
- **Xu ly:** transaction xoa user + du lieu lien quan + file video.

#### Page: `admin/frontend/video_analyses.php`
- **Backends:** `admin/backend/view_video.php`, `delete_video.php`
- **Xu ly:** xem chi tiet ban ghi va xoa ban ghi phan tich.

#### Page: `admin/frontend/action_predictions.php`
- **Backends:** `admin/backend/view_prediction.php`, `delete_prediction.php`
- **Xu ly:** xem chi tiet prediction va xoa ban ghi.

## 6) Backend function-level breakdown (overview -> detail)

Luu y quan trong:
- Phan lon backend duoc viet procedural (khong tach nhieu ham named), vi vay "function-level" duoc trinh bay theo **processing blocks**.
- Cac file co ham named ro rang: `chatbox_api.php` (class methods), `mailer.php` (ham `sendOtpMail`).

### 6.1 Main backend (core AI pages)

#### `main/backend/action_prediction.php`
**Overview**
- Vai tro: API nhan video, du doan dong tac, luu ket qua prediction + thong tin phan tich.
- Kieu response: JSON (success/error).

**Detail blocks**
1) **Auth + request method guard**
   - Yeu cau user dang nhap.
   - Tu choi neu khong phai `POST`.
2) **Input validation**
   - Kiem tra `$_FILES['video']` ton tai.
   - Check upload error code, extension/mime, max file size.
3) **Storage**
   - Dat ten file duy nhat (timestamp/uuid style), move vao upload dir.
4) **Python inference pipeline**
   - Goi `predict_action.py` -> lay class label + probabilities.
   - Co the tiep tuc goi phan tich ky thuat de tao coaching feedback.
5) **DB persistence**
   - Tao/cap nhat schema `action_predictions` neu can.
   - Insert row gom: user_id, file path, predicted action, probabilities, metadata.
6) **Return payload**
   - `success=true`: tra ket qua prediction + thong tin bo sung.
   - `success=false`: ma loi + message + (co the co debug info).

**Error cases**
- 401: chua auth
- 405: sai method
- 400: loi upload/invalid input
- 500: python/db/process fail

---

#### `main/backend/video_analysis.php`
**Overview**
- Vai tro: xu ly upload video phan tich ky thuat va luu vao `video_analyses`.
- Kieu response: session flash + redirect (form-based flow).

**Detail blocks**
1) Validate auth + method `POST`.
2) Validate file + `skill` (whitelist).
3) Luu file video vao server.
4) Goi Python `run_analysis.py`.
5) Parse JSON output:
   - phases/techniques detected
   - coaching feedback
   - debug/raw data
6) Insert DB `video_analyses`.
7) Ghi flash `success/error` vao session va redirect ve page.

**Error handling**
- Khong crash trang: luon redirect ve UI va hien message.
- Ghi log backend khi loi python/db.

---

#### `main/backend/chat_handler.php`
**Overview**
- Vai tro: API chat theo context ket qua phan tich.
- Kieu response: JSON.

**Input**
- JSON body:
  - `session_id` (required)
  - `message` (required)
  - `analysis_id` (optional)

**Detail blocks**
1) Check auth + method POST + parse JSON body.
2) Validate required fields.
3) Lay context uu tien tu `video_analyses`; fallback `action_predictions`.
4) Build temp context file (JSON) cho script chat.
5) Goi Python `chat_response.py`.
6) Parse va tra response text cho frontend.

**Error handling**
- 404 neu khong tim thay context analysis.
- 500 neu script khong tra JSON hop le.

---

#### `main/backend/live_action.php`
**Overview**
- Vai tro: endpoint cho live webcam prediction.
- Kieu response: JSON.

**Input**
- `POST action=predict|reset`
- voi `predict`: can `frame` data.

**Detail blocks**
- **predict flow**
  1) validate frame payload
  2) save frame tam ra disk
  3) call `live_predict.py`
  4) parse output
  5) return JSON prediction
- **reset flow**
  1) reset model/buffer state
  2) return success JSON

**Error handling**
- 400: thieu frame/action
- 500: python fail

---

#### `main/backend/shadowing_select.php`
**Overview**
- Vai tro: tao danh sach bai shadowing co trang thai san sang.
- Kieu output: `return array` cho frontend include.

**Detail**
1) Dinh nghia list poses + mo ta.
2) Kiem tra asset folder cho tung pose:
   - `ghost_0.png`
   - `meta_0.npy`
   - `target_0.npy`
3) Gan `hasAssets=true/false`.
4) Return full poses list.

---

#### `main/backend/shadowing_practice.php`
**Overview**
- Vai tro: validate pose tu URL va tao config cho trang practice.
- Kieu output: `return array`.

**Input**
- `GET pose`

**Detail**
1) Validate pose trong whitelist.
2) Resolve assets directory theo pose.
3) Kiem tra ton tai file stage dau.
4) Build metadata:
   - `valid`
   - `pose`, `name`
   - `hasAssets`
   - `assetsPath`
   - `availablePoses` (pose co du assets)
5) Return config cho frontend JS.

---

#### `main/backend/shadowing_assets_api.php`
**Overview**
- Vai tro: doc du lieu NPY cho shadowing va tra JSON.
- Kieu response: JSON.

**Input**
- `GET pose`
- `GET type` in `meta|target`
- `GET stage` (0..3)

**Detail**
1) Validate pose/type/stage.
2) Build path den file `.npy`.
3) Goi `read_npy.py` bang python command fallback (`python3`, `python`, `py`).
4) Parse output:
   - neu hop le -> passthrough JSON
   - neu loi -> return JSON error 500

**Error handling**
- 400 invalid params
- 404 file not found
- 500 python/no output/invalid JSON

---

#### `main/backend/chatbox_api.php` (class-based helper)
Class: `ChatBoxAPI`

1) `__construct(string $baseUrl='http://localhost:8000', int $timeout=300)`
   - Luu base URL API chatbot + timeout cURL.
2) `healthCheck(): bool`
   - Goi `/health`, check HTTP code + JSON status.
3) `uploadVideo(string $videoPath, string $skill='drive_forehand'): ?array`
   - Dung `CURLFile` upload file len endpoint upload.
4) `analyzeVideo(string $sessionId, string $skill='drive_forehand'): ?array`
   - Goi endpoint analyze theo session.
5) `getFeedback(string $sessionId, string $skill='drive_forehand'): ?array`
   - Goi endpoint feedback.
6) `runFullAnalysis(string $videoPath, string $skill='drive_forehand'): ?array`
   - Orchestrate chuoi upload -> analyze -> feedback.

**Common error strategy**
- Return `null` khi API fail.
- Ghi chi tiet qua `error_log`.

### 6.2 User backend (auth/account)

#### `user/backend/login.php`
**Overview**
- Login local account + captcha + OTP.

**Detail blocks**
1) Validate POST fields + captcha token.
2) Verify captcha voi Google endpoint.
3) Query user theo email.
4) Check user status (active/pending/blocked).
5) Verify password hash.
6) Tao OTP hash + expiry + attempts counter trong session.
7) Goi `sendOtpMail`.
8) Set `pending_login` va redirect `verify.php`.

#### `user/backend/register.php`
1) Validate email/name/password/captcha.
2) Check duplicate account.
3) Transaction:
   - insert user moi hoac update user dang pending
4) Tao OTP + gui mail.
5) Set `pending_registration`.

#### `user/backend/verify.php`
**Modes:** `login`, `registration`, `password_change`

**Shared flow**
1) Validate code 6 so.
2) Check expiry + max attempts.
3) Verify OTP hash.

**Mode-specific**
- login: set session user + update last_login
- registration: activate account (`status=active`)
- password_change: update password hash moi

#### `user/backend/change_password.php`
1) Check auth + current password.
2) Validate new password policy + confirm.
3) Tao OTP context `password_change`.
4) Chuyen huong qua verify.

#### `user/backend/save_settings.php`
1) Validate `theme`/`language` theo whitelist.
2) Upsert `user_preferences`.
3) Update session/cookie de apply ngay.

#### `user/backend/google_login.php` + `google_callback.php`
- `google_login.php`: tao `state` anti-CSRF, redirect Google OAuth.
- `google_callback.php`:
  1) Validate `state`
  2) Exchange code -> token
  3) Lay profile
  4) Transaction upsert `users` + `user_identities`
  5) Gui OTP va set pending login.

#### `user/backend/mailer.php`
Function: `sendOtpMail(array $config, string $recipientEmail, string $code, string $context='login'): bool`

**Detail**
1) Build SMTP socket connection.
2) SMTP handshake (`EHLO`, `STARTTLS`, `AUTH LOGIN`).
3) Gui MIME message body theo context.
4) Return `true/false`.

#### `user/backend/require_auth.php`
- Kiem tra session user.
- Neu chua login: redirect sang login + return URL an toan.

### 6.3 Admin backend

#### `admin/backend/login.php` + `admin/backend/verify.php`
- Cung mo hinh OTP nhu user flow.
- Login check hardcoded/admin credential source.
- Verify set `$_SESSION['admin']`.

#### `admin/backend/get_chart_data.php`
**Input:** `GET days` (clamp range)

**Detail**
1) Chuan hoa khoang ngay.
2) Query aggregate:
   - so users moi/ngay
   - so video analyses/ngay
   - so predictions/ngay
3) Build JSON arrays `labels`, `users`, `analyses`, `predictions`.

#### `admin/backend/delete_user.php`
1) Validate id + user exists.
2) Start transaction.
3) Xoa cac bang lien quan:
   - `video_analyses`
   - `action_predictions`
   - `user_preferences`
   - `user_sessions`
   - `user_identities`
   - `users`
4) Xoa file media user.
5) Commit + return JSON.

#### `admin/backend/delete_video.php`
1) Validate id.
2) Lay file path record.
3) Transaction delete row + unlink file.
4) Return JSON success/error.

#### `admin/backend/delete_prediction.php`
Tuong tu `delete_video.php` nhung tren bang `action_predictions`.

#### `admin/backend/view_video.php`
1) Validate id.
2) Join query voi user info.
3) Decode JSON columns (`techniques_detected`, `coaching_feedback`, `raw_feedback`).
4) Return detail JSON.

#### `admin/backend/view_prediction.php`
1) Validate id.
2) Query prediction + user info.
3) Decode probability JSON field.
4) Return detail JSON.

#### `admin/backend/mailer.php`
Function giong user mailer: `sendOtpMail(...)` cho OTP admin.

## 7) Cac diem can trinh bay khi bao ve (goi y)

- Kien truc phan tang ro rang: Frontend PHP -> Backend PHP -> AI Python -> MySQL
- Auth/OTP flow day du cho user va admin
- Validation va error handling co he thong (HTTP codes, flash message, JSON)
- Du lieu AI duoc luu de truy vet lich su va chatbot tu van
- Shadowing real-time: camera + mediapipe + ghost overlay + similarity score
- Admin panel co monitoring (charts) va data governance (view/delete)

## 8) Cong nghe

- **Web:** PHP, HTML, CSS, JavaScript
- **Database:** MySQL (PDO)
- **AI/ML:** Python, MediaPipe, YOLO11n-Pose, LSTM, NumPy, OpenCV
- **Infrastructure local:** XAMPP (Apache + MySQL)
