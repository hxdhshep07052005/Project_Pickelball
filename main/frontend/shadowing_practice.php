<?php
// Require authentication - redirect to login if not logged in
require_once __DIR__ . '/../../user/backend/require_auth.php';
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../includes/header.php';

// Load backend logic to get pose information
$poseData = require __DIR__ . '/../backend/shadowing_practice.php';

// Validate pose data and redirect to selection page if invalid
if (!$poseData['valid']) {
    header('Location: shadowing_select.php');
    exit;
}

// Extract pose data for display
$poseName = $poseData['pose'];
$displayName = $poseData['name'];
$hasAssets = $poseData['hasAssets'];
$assetsPath = $poseData['assetsPath'] ?? '';
?>
<style>
    .shadowing-practice-section {
        padding: 0;
        background: #ffffff;
        position: relative;
    }
    .practice-container {
        width: 100%;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 24px;
        padding: 80px 24px 24px;
        align-items: start;
        transition: grid-template-columns 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .practice-card {
        background: #ffffff;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08), 0 0 0 1px rgba(0, 0, 0, 0.05);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        transition: all 0.3s ease;
        border: 1px solid #e2e8f0;
    }
    .practice-card:hover {
        box-shadow: 0 8px 32px rgba(16, 185, 129, 0.12), 0 0 0 1px rgba(16, 185, 129, 0.2);
        transform: translateY(-2px);
    }
    .camera-panel {
        position: relative;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .camera-panel.expanded {
        grid-column: 1 / -1;
        max-width: 100%;
    }
    .camera-panel.expanded .panel-content {
        max-height: calc(100vh - 200px);
        aspect-ratio: 16 / 9;
        width: 100%;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .video-panel {
        position: relative;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        opacity: 1;
        transform: scale(1);
    }
    .video-panel.hidden {
        opacity: 0;
        width: 0;
        min-width: 0;
        margin: 0;
        padding: 0;
        overflow: hidden;
        transform: scale(0.95);
        pointer-events: none;
        grid-column: 0;
        max-width: 0;
    }
    .panel-header {
        padding: 20px 24px;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: space-between;
        min-height: 64px;
        box-sizing: border-box;
    }
    .panel-title {
        font-size: 18px;
        font-weight: 700;
        color: #0f172a;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .panel-title svg {
        width: 24px;
        height: 24px;
        fill: #10b981;
    }
    .toggle-video-btn {
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        color: #0f172a;
        padding: 8px 16px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .toggle-video-btn:hover {
        background: #e2e8f0;
        border-color: #cbd5e1;
    }
    .toggle-video-btn svg {
        width: 18px;
        height: 18px;
        fill: currentColor;
    }
    .panel-content {
        position: relative;
        overflow: hidden;
        width: 100%;
        aspect-ratio: 16 / 9;
        border-radius: 0 0 16px 16px;
        margin: 0;
        padding: 0;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .camera-view {
        width: 100%;
        height: 100%;
        background: #000000;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
    }
    .camera-placeholder {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .camera-placeholder-icon {
        width: 120px;
        height: 120px;
        opacity: 0.3;
    }
    .camera-placeholder-icon svg {
        width: 100%;
        height: 100%;
        fill: #64748b;
    }
    .video-view {
        width: 100%;
        height: 100%;
        background: #000000;
        position: relative;
        margin: 0;
        padding: 0;
    }
    .video-view video {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
        margin: 0;
        padding: 0;
    }
    .video-controls {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        background: linear-gradient(to top, rgba(0, 0, 0, 0.8), transparent);
        padding: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
        opacity: 0;
        transition: opacity 0.3s;
    }
    .video-view:hover .video-controls {
        opacity: 1;
    }
    .control-btn {
        background: rgba(255, 255, 255, 0.2);
        border: none;
        color: #ffffff;
        width: 44px;
        height: 44px;
        border-radius: 50%;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
    }
    .control-btn:hover {
        background: rgba(255, 255, 255, 0.3);
    }
    .control-btn svg {
        width: 20px;
        height: 20px;
        fill: currentColor;
    }
    .back-button {
        position: absolute;
        top: 24px;
        left: 24px;
        z-index: 10;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        color: #0f172a;
        padding: 12px 20px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s;
        text-decoration: none;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }
    .back-button:hover {
        background: #f8fafc;
        border-color: #cbd5e1;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
    .back-button svg {
        width: 18px;
        height: 18px;
        fill: currentColor;
    }
    @media (max-width: 1200px) {
        .practice-container {
            grid-template-columns: 1fr;
            gap: 24px;
            padding: 80px 20px 20px;
        }
        .camera-panel.expanded {
            grid-column: 1;
        }
    }
    @media (max-width: 640px) {
        .practice-container {
            padding: 70px 16px 16px;
            gap: 16px;
        }
        .panel-header {
            padding: 16px;
        }
        .back-button {
            top: 16px;
            left: 16px;
            padding: 10px 16px;
            font-size: 13px;
        }
    }
    #ghostCanvas {
        image-rendering: -webkit-optimize-contrast;
        image-rendering: crisp-edges;
    }
</style>
<!-- MediaPipe Pose Detection -->
<!-- Using unpkg as primary CDN for better reliability -->
<script src="https://unpkg.com/@mediapipe/camera_utils/camera_utils.js" crossorigin="anonymous"></script>
<script src="https://unpkg.com/@mediapipe/control_utils/control_utils.js" crossorigin="anonymous"></script>
<script src="https://unpkg.com/@mediapipe/drawing_utils/drawing_utils.js" crossorigin="anonymous"></script>
<script src="https://unpkg.com/@mediapipe/pose/pose.js" crossorigin="anonymous"></script>
<section class="shadowing-practice-section">
    <a href="shadowing_select.php" class="back-button">
        <svg viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
            <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/>
        </svg>
        <?php echo htmlspecialchars(t('back_to_selection'), ENT_QUOTES, 'UTF-8'); ?>
    </a>
    
    <div class="practice-container">
        <!-- Camera Card (Left) -->
        <div class="practice-card camera-panel" id="cameraPanel">
            <div class="panel-header">
                <div class="panel-title">
                    <svg viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                        <path d="M17 10.5V7c0-.55-.45-1-1-1H4c-.55 0-1 .45-1 1v10c0 .55.45 1 1 1h12c.55 0 1-.45 1-1v-3.5l4 4v-11l-4 4z"/>
                    </svg>
                    <?php echo htmlspecialchars(t('your_camera_view'), ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                    <button class="toggle-video-btn" id="runModelBtn" onclick="runModel()" title="Initialize & Test MediaPipe Model">
                        <svg viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg" style="width: 18px; height: 18px;">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                        </svg>
                        <span id="runModelBtnText">Run Model</span>
                    </button>
                    <button class="toggle-video-btn" id="startGhostTrainerBtn" onclick="startGhostTrainer()" title="Start Ghost Trainer" style="display: none;">
                        <svg viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg" style="width: 18px; height: 18px;">
                            <path d="M8 5v14l11-7z"/>
                        </svg>
                        <span>Start Ghost Trainer</span>
                    </button>
                    <button class="toggle-video-btn" id="nextPoseBtn" onclick="nextPose()" style="display: none;" title="Next Pose">
                        <svg viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg" style="width: 18px; height: 18px;">
                            <path d="M12 4l-1.41 1.41L16.17 11H4v2h12.17l-5.58 5.59L12 20l8-8z"/>
                        </svg>
                        <span>Next Pose</span>
                    </button>
                    <button class="toggle-video-btn" id="resetStageBtn" onclick="resetStage()" style="display: none;" title="Reset Stage">
                        <svg viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg" style="width: 18px; height: 18px;">
                            <path d="M17.65 6.35C16.2 4.9 14.21 4 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08c-.82 2.33-3.04 4-5.65 4-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/>
                        </svg>
                        <span>Reset</span>
                    </button>
                    <button class="toggle-video-btn" id="checkCameraBtn" onclick="checkCamera()" title="<?php echo htmlspecialchars(t('test_camera'), ENT_QUOTES, 'UTF-8'); ?>">
                        <svg viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg" style="width: 18px; height: 18px;">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                        </svg>
                        <span><?php echo htmlspecialchars(t('test_camera'), ENT_QUOTES, 'UTF-8'); ?></span>
                    </button>
                    <button class="toggle-video-btn" id="toggleVideoBtnCamera" onclick="toggleVideo()" style="visibility: hidden; opacity: 0; pointer-events: none;">
                        <svg viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                            <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
                        </svg>
                        <span id="toggleTextCamera"><?php echo htmlspecialchars(t('show_video'), ENT_QUOTES, 'UTF-8'); ?></span>
                    </button>
                </div>
            </div>
            <div class="panel-content">
                <div class="camera-view" id="cameraView">
                    <div class="camera-placeholder" id="cameraPlaceholder">
                        <div class="camera-placeholder-icon">
                            <svg viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                                <path d="M17 10.5V7c0-.55-.45-1-1-1H4c-.55 0-1 .45-1 1v10c0 .55.45 1 1 1h12c.55 0 1-.45 1-1v-3.5l4 4v-11l-4 4z"/>
                            </svg>
                        </div>
                    </div>
                    <video id="cameraPreview" autoplay playsinline style="display: none; width: 100%; height: 100%; object-fit: cover;"></video>
                    <canvas id="ghostCanvas" style="display: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none;"></canvas>
                    <!-- Ghost Trainer UI Overlay -->
                    <div id="ghostTrainerUI" style="display: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 10;">
                        <div style="position: absolute; top: 20px; left: 20px; background: rgba(0,0,0,0.7); padding: 12px 20px; border-radius: 8px; color: white; font-weight: bold; font-size: 18px;" id="poseNameDisplay">POSE: SERVE</div>
                        <div style="position: absolute; top: 20px; right: 20px; background: rgba(0,0,0,0.7); padding: 12px 20px; border-radius: 8px; color: white; font-weight: bold; font-size: 18px;" id="stageDisplay">Step: 1/4</div>
                        <div style="position: absolute; top: 80px; left: 20px; background: rgba(0,0,0,0.7); padding: 12px 20px; border-radius: 8px; color: #00ff00; font-weight: bold; font-size: 20px;" id="scoreDisplay">SCORE: 0%</div>
                        <div style="position: absolute; top: 130px; left: 20px; width: 200px; height: 20px; background: rgba(255,255,255,0.3); border-radius: 10px; overflow: hidden; border: 2px solid white;">
                            <div id="progressBar" style="height: 100%; width: 0%; background: #00ff00; transition: width 0.1s;"></div>
                        </div>
                        <div id="cooldownMessage" style="display: none; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: rgba(0,0,0,0.9); padding: 40px 60px; border-radius: 16px; color: #ffff00; font-size: 24px; font-weight: bold; text-align: center;">
                            <div>GET READY FOR STEP <span id="nextStageNum">1</span>...</div>
                            <div style="margin-top: 20px; font-size: 32px;" id="cooldownTimer">3</div>
                        </div>
                        <div id="perfectMessage" style="display: none; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: rgba(0,255,0,0.9); padding: 40px 60px; border-radius: 16px; color: white; font-size: 32px; font-weight: bold;">PERFECT!</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Video Card (Right) -->
        <div class="practice-card video-panel" id="videoPanel">
            <div class="panel-header">
                <div class="panel-title">
                    <svg viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                        <path d="M8 5v14l11-7z"/>
                    </svg>
                    <?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <button class="toggle-video-btn" id="toggleVideoBtn" onclick="toggleVideo()">
                    <svg viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                        <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
                    </svg>
                    <span id="toggleText"><?php echo htmlspecialchars(t('hide_video'), ENT_QUOTES, 'UTF-8'); ?></span>
                </button>
            </div>
            <div class="panel-content">
                <div class="video-view">
                    <?php if ($hasAssets): ?>
                        <div style="width: 100%; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 20px; text-align: center;">
                            <svg width="64" height="64" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg" style="margin-bottom: 20px;">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                            </svg>
                            <h3 style="font-size: 24px; font-weight: bold; margin-bottom: 10px;"><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></h3>
                            <p style="font-size: 14px; opacity: 0.9;">Ghost Trainer Ready</p>
                            <p style="font-size: 12px; opacity: 0.7; margin-top: 10px;">Use the camera panel to start training</p>
                        </div>
                    <?php else: ?>
                        <div style="width: 100%; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); color: white; padding: 20px; text-align: center;">
                            <svg width="64" height="64" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg" style="margin-bottom: 20px;">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                            </svg>
                            <h3 style="font-size: 24px; font-weight: bold; margin-bottom: 10px;">Assets Not Found</h3>
                            <p style="font-size: 14px; opacity: 0.9;">Please run the Python script to generate assets</p>
                        </div>
                    <?php endif; ?>
                    <!-- Keep video element for compatibility but hide it -->
                    <video id="practiceVideo" style="display: none;" controls>
                        Your browser does not support the video tag.
                    </video>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
// Toggle video panel visibility and expand camera panel
let videoHidden = false;
const video = document.getElementById('practiceVideo');
const videoPanel = document.getElementById('videoPanel');
const cameraPanel = document.getElementById('cameraPanel');
const toggleBtn = document.getElementById('toggleVideoBtn');
const toggleText = document.getElementById('toggleText');
const toggleBtnCamera = document.getElementById('toggleVideoBtnCamera');
const toggleTextCamera = document.getElementById('toggleTextCamera');
const playPauseBtn = document.getElementById('playPauseBtn');
const playIcon = document.getElementById('playIcon');
const pauseIcon = document.getElementById('pauseIcon');

// Function to hide/show video panel and expand camera view
function toggleVideo() {
    videoHidden = !videoHidden;
    
    if (videoHidden) {
        // Hide video panel and expand camera panel
        videoPanel.classList.add('hidden');
        cameraPanel.classList.add('expanded');
        
        // Smooth button transitions with delay
        setTimeout(() => {
            if (toggleBtn) {
                toggleBtn.style.visibility = 'hidden';
                toggleBtn.style.opacity = '0';
                toggleBtn.style.pointerEvents = 'none';
            }
            if (toggleBtnCamera) {
                toggleBtnCamera.style.visibility = 'visible';
                toggleBtnCamera.style.opacity = '1';
                toggleBtnCamera.style.pointerEvents = 'auto';
                toggleTextCamera.textContent = '<?php echo htmlspecialchars(t('show_video'), ENT_QUOTES, 'UTF-8'); ?>';
            }
        }, 100);
    } else {
        // Show video panel and restore camera panel size
        videoPanel.classList.remove('hidden');
        cameraPanel.classList.remove('expanded');
        
        // Smooth button transitions with delay
        setTimeout(() => {
            if (toggleBtn) {
                toggleBtn.style.visibility = 'visible';
                toggleBtn.style.opacity = '1';
                toggleBtn.style.pointerEvents = 'auto';
                toggleText.textContent = '<?php echo htmlspecialchars(t('hide_video'), ENT_QUOTES, 'UTF-8'); ?>';
            }
            if (toggleBtnCamera) {
                toggleBtnCamera.style.visibility = 'hidden';
                toggleBtnCamera.style.opacity = '0';
                toggleBtnCamera.style.pointerEvents = 'none';
            }
        }, 100);
    }
}

// Video controls removed - using ghost trainer instead
// Video element kept for compatibility but hidden

// Camera check functionality
let cameraStream = null;
const cameraPreview = document.getElementById('cameraPreview');
const cameraPlaceholder = document.getElementById('cameraPlaceholder');
const checkCameraBtn = document.getElementById('checkCameraBtn');

async function checkCamera() {
    try {
        // Request camera access
        const stream = await navigator.mediaDevices.getUserMedia({ 
            video: { 
                width: { ideal: 1280 },
                height: { ideal: 720 },
                facingMode: 'user'
            } 
        });
        
        // Stop previous stream if exists
        if (cameraStream) {
            cameraStream.getTracks().forEach(track => track.stop());
        }
        
        cameraStream = stream;
        cameraPreview.srcObject = stream;
        cameraPreview.style.display = 'block';
        cameraPlaceholder.style.display = 'none';
        
        // Update button text
        checkCameraBtn.innerHTML = `
            <svg viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg" style="width: 18px; height: 18px;">
                <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
            </svg>
            <span><?php echo htmlspecialchars(t('stop_camera'), ENT_QUOTES, 'UTF-8'); ?></span>
        `;
        checkCameraBtn.onclick = stopCamera;
        
        // Show success message
        showCameraMessage('<?php echo htmlspecialchars(t('camera_working'), ENT_QUOTES, 'UTF-8'); ?>', 'success');
    } catch (error) {
        console.error('Camera error:', error);
        let errorMessage = '<?php echo htmlspecialchars(t('camera_error'), ENT_QUOTES, 'UTF-8'); ?> ';
        if (error.name === 'NotAllowedError') {
            errorMessage += '<?php echo htmlspecialchars(t('camera_not_allowed'), ENT_QUOTES, 'UTF-8'); ?>';
        } else if (error.name === 'NotFoundError') {
            errorMessage += '<?php echo htmlspecialchars(t('camera_not_found'), ENT_QUOTES, 'UTF-8'); ?>';
        } else {
            errorMessage += error.message;
        }
        showCameraMessage(errorMessage, 'error');
    }
}

function stopCamera() {
    if (cameraStream) {
        cameraStream.getTracks().forEach(track => track.stop());
        cameraStream = null;
    }
    cameraPreview.srcObject = null;
    cameraPreview.style.display = 'none';
    cameraPlaceholder.style.display = 'flex';
    
    // Update button text
    checkCameraBtn.innerHTML = `
        <svg viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg" style="width: 18px; height: 18px;">
            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
        </svg>
        <span>Test Camera</span>
    `;
    checkCameraBtn.onclick = checkCamera;
}

function showCameraMessage(message, type) {
    // Remove existing message
    const existingMsg = document.getElementById('cameraMessage');
    if (existingMsg) {
        existingMsg.remove();
    }
    
    // Create message element
    const msgDiv = document.createElement('div');
    msgDiv.id = 'cameraMessage';
    msgDiv.style.cssText = `
        position: fixed;
        top: 100px;
        right: 24px;
        padding: 16px 20px;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        z-index: 10000;
        max-width: 400px;
        animation: slideIn 0.3s ease;
        ${type === 'success' 
            ? 'background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534;' 
            : 'background: #fef2f2; border: 1px solid #fecaca; color: #991b1b;'
        }
    `;
    msgDiv.textContent = message;
    
    document.body.appendChild(msgDiv);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        msgDiv.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => msgDiv.remove(), 300);
    }, 5000);
}

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);

// Stop camera when page is unloaded
window.addEventListener('beforeunload', () => {
    if (cameraStream) {
        cameraStream.getTracks().forEach(track => track.stop());
    }
    if (ghostTrainerActive) {
        stopGhostTrainer();
    }
});

// ==================== GHOST TRAINER FUNCTIONALITY ====================

// Get pose from PHP
const SELECTED_POSE = "<?php echo htmlspecialchars($poseName, ENT_QUOTES, 'UTF-8'); ?>";
const POSES_LIST = ["Serve", "DriveForehand", "DriveBackhand"];
const SIMILARITY_THRESH = 0.85;
const REQUIRED_HOLD_TIME = 500; // milliseconds
const COOLDOWN_TIME = 2000; // milliseconds

let ghostTrainerActive = false;
let poseDetector = null;
let modelInitialized = false;
let modelInitializing = false;
// Set current pose index based on selected pose from URL
let currentPoseIdx = POSES_LIST.indexOf(SELECTED_POSE);
if (currentPoseIdx === -1) currentPoseIdx = 0; // Fallback to first pose if not found
let currentStage = 0;
let lastMatchTime = 0;
let matchDuration = 0;
let inCooldown = false;
let cooldownStart = 0;
let ghostImages = {};
let ghostMetadata = {};
let targetPoses = {};
let animationFrameId = null;

// Reuse cameraPreview from above, don't redeclare
const ghostCanvas = document.getElementById('ghostCanvas');
const ghostTrainerUI = document.getElementById('ghostTrainerUI');
const poseNameDisplay = document.getElementById('poseNameDisplay');
const stageDisplay = document.getElementById('stageDisplay');
const scoreDisplay = document.getElementById('scoreDisplay');
const progressBar = document.getElementById('progressBar');
const cooldownMessage = document.getElementById('cooldownMessage');
const cooldownTimer = document.getElementById('cooldownTimer');
const nextStageNum = document.getElementById('nextStageNum');
const perfectMessage = document.getElementById('perfectMessage');
const runModelBtn = document.getElementById('runModelBtn');
const runModelBtnText = document.getElementById('runModelBtnText');
const startGhostTrainerBtn = document.getElementById('startGhostTrainerBtn');

// ==================== MODEL INITIALIZATION ====================

async function runModel() {
    if (modelInitializing) {
        showCameraMessage('Model is already initializing...', 'error');
        return;
    }

    if (modelInitialized && poseDetector) {
        showCameraMessage('Model is already initialized and ready!', 'success');
        startGhostTrainerBtn.style.display = 'flex';
        return;
    }

    modelInitializing = true;
    runModelBtn.disabled = true;
    runModelBtnText.textContent = 'Initializing...';

    try {
        // Check if MediaPipe Pose library is loaded
        if (typeof Pose === 'undefined') {
            throw new Error('MediaPipe Pose library not loaded. Please refresh the page.');
        }

        showCameraMessage('Loading MediaPipe Pose model...', 'success');

        // Initialize MediaPipe Pose
        poseDetector = new Pose({
            locateFile: (file) => {
                const baseUrl = `https://unpkg.com/@mediapipe/pose/${file}`;
                console.log('Loading MediaPipe asset:', file);
                return baseUrl;
            }
        });

        // Set options
        poseDetector.setOptions({
            modelComplexity: 2,
            smoothLandmarks: true,
            enableSegmentation: false,
            smoothSegmentation: false,
            minDetectionConfidence: 0.5,
            minTrackingConfidence: 0.5
        });

        // Wait a bit for model to fully load assets
        showCameraMessage('Loading model assets...', 'success');
        await new Promise(resolve => setTimeout(resolve, 2000));

        // Test the model with a simple test
        showCameraMessage('Testing model...', 'success');
        
        // Create a test canvas to verify model works
        const testCanvas = document.createElement('canvas');
        testCanvas.width = 640;
        testCanvas.height = 480;
        const testCtx = testCanvas.getContext('2d');
        testCtx.fillStyle = '#000000';
        testCtx.fillRect(0, 0, 640, 480);

        // Test with blank image (just to verify model can process)
        let testCompleted = false;
        const testCallback = (results) => {
            if (!testCompleted) {
                testCompleted = true;
                modelInitialized = true;
                modelInitializing = false;
                
                // Remove test callback
                poseDetector.onResults(null);
                
                runModelBtn.disabled = false;
                runModelBtnText.innerHTML = `
                    <svg viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg" style="width: 18px; height: 18px;">
                        <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                    </svg>
                    <span>Model Ready</span>
                `;
                runModelBtn.style.background = '#10b981';
                runModelBtn.style.color = 'white';
                
                showCameraMessage('✓ MediaPipe Model initialized successfully!', 'success');
                startGhostTrainerBtn.style.display = 'flex';
            }
        };

        poseDetector.onResults(testCallback);

        // Send test frame
        try {
            poseDetector.send({ image: testCanvas });
        } catch (error) {
            console.error('Error sending test frame:', error);
            testCompleted = true;
            modelInitialized = true;
            modelInitializing = false;
            runModelBtn.disabled = false;
            runModelBtnText.innerHTML = `
                <svg viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg" style="width: 18px; height: 18px;">
                    <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                </svg>
                <span>Model Ready</span>
            `;
            runModelBtn.style.background = '#10b981';
            runModelBtn.style.color = 'white';
            showCameraMessage('✓ MediaPipe Model initialized!', 'success');
            startGhostTrainerBtn.style.display = 'flex';
        }
        
        // Timeout after 5 seconds
        setTimeout(() => {
            if (!testCompleted) {
                modelInitializing = false;
                runModelBtn.disabled = false;
                runModelBtnText.textContent = 'Run Model';
                showCameraMessage('Model initialization timeout. Please check your internet connection.', 'error');
                poseDetector.onResults(null);
            }
        }, 5000);

    } catch (error) {
        console.error('Error initializing MediaPipe model:', error);
        modelInitializing = false;
        modelInitialized = false;
        runModelBtn.disabled = false;
        runModelBtnText.textContent = 'Run Model';
        
        let errorMsg = 'Failed to initialize model: ' + error.message;
        if (error.message.includes('NetworkError') || error.message.includes('Failed to fetch')) {
            errorMsg = 'Network error: Cannot load MediaPipe assets. Please check your internet connection.';
        }
        showCameraMessage(errorMsg, 'error');
    }
}

// ==================== GHOST TRAINER ====================

async function startGhostTrainer() {
    if (ghostTrainerActive) {
        stopGhostTrainer();
        return;
    }

    // Check if model is initialized
    if (!modelInitialized || !poseDetector) {
        showCameraMessage('Please run the model first by clicking "Run Model" button', 'error');
        return;
    }

    // Ensure camera is started
    if (!cameraStream) {
        await checkCamera();
        if (!cameraStream) {
            showCameraMessage('Please enable camera first', 'error');
            return;
        }
    }

    ghostTrainerActive = true;
    currentPoseIdx = 0;
    currentStage = 0;
    inCooldown = false;

    // Show UI
    ghostTrainerUI.style.display = 'block';
    ghostCanvas.style.display = 'block';

    showCameraMessage('Loading pose assets...', 'success');

    // Load assets for current pose
    try {
        await loadPoseAssets(POSES_LIST[currentPoseIdx]);
        showCameraMessage('Assets loaded successfully!', 'success');
    } catch (error) {
        console.error('Error loading assets:', error);
        showCameraMessage('Failed to load pose assets. Please check console for details.', 'error');
        stopGhostTrainer();
        return;
    }

    // Set up pose detection callback
    poseDetector.onResults(onPoseResults);
    
    // Start detection loop
    startDetectionLoop();
    
    showCameraMessage('Ghost Trainer started!', 'success');
    
    // Update button states
    if (runModelBtn) {
        runModelBtn.style.display = 'none'; // Hide run model button when trainer is active
    }

    // Update buttons
    const btn = document.getElementById('startGhostTrainerBtn');
    btn.innerHTML = `
        <svg viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg" style="width: 18px; height: 18px;">
            <path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/>
        </svg>
        <span>Stop Trainer</span>
    `;
    btn.onclick = startGhostTrainer;
    
    // Show control buttons
    document.getElementById('nextPoseBtn').style.display = 'flex';
    document.getElementById('resetStageBtn').style.display = 'flex';
}

function stopGhostTrainer() {
    ghostTrainerActive = false;
    if (animationFrameId) {
        cancelAnimationFrame(animationFrameId);
        animationFrameId = null;
    }
    
    // Don't close poseDetector here - keep it for reuse
    // if (poseDetector) {
    //     poseDetector.close();
    //     poseDetector = null;
    // }
    
    ghostTrainerUI.style.display = 'none';
    ghostCanvas.style.display = 'none';
    
    const ctx = ghostCanvas.getContext('2d');
    ctx.clearRect(0, 0, ghostCanvas.width, ghostCanvas.height);

    const btn = document.getElementById('startGhostTrainerBtn');
    btn.innerHTML = `
        <svg viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg" style="width: 18px; height: 18px;">
            <path d="M8 5v14l11-7z"/>
        </svg>
        <span>Start Ghost Trainer</span>
    `;
    btn.onclick = startGhostTrainer;
    
    // Hide control buttons
    document.getElementById('nextPoseBtn').style.display = 'none';
    document.getElementById('resetStageBtn').style.display = 'none';
    
    // Show run model button again if model is still initialized
    if (runModelBtn && modelInitialized) {
        runModelBtn.style.display = 'flex';
    }
}

function nextPose() {
    if (!ghostTrainerActive) return;
    
    currentPoseIdx = (currentPoseIdx + 1) % POSES_LIST.length;
    currentStage = 0;
    inCooldown = false;
    matchDuration = 0;
    progressBar.style.width = '0%';
    
    // Load new pose assets
    loadPoseAssets(POSES_LIST[currentPoseIdx]).then(() => {
        console.log(`Switched to: ${POSES_LIST[currentPoseIdx]}`);
    });
}

function resetStage() {
    if (!ghostTrainerActive) return;
    
    currentStage = 0;
    inCooldown = false;
    matchDuration = 0;
    progressBar.style.width = '0%';
    cooldownMessage.style.display = 'none';
    perfectMessage.style.display = 'none';
    console.log('Reset stage.');
}

async function loadPoseAssets(poseName) {
    const baseUrl = '/pickelball/assets/' + poseName + '/';
    
    // Load ghost images
    for (let i = 0; i < 4; i++) {
        const img = new Image();
        img.src = baseUrl + 'ghost_' + i + '.png';
        await new Promise((resolve) => {
            img.onload = resolve;
            img.onerror = resolve; // Continue even if image fails
        });
        if (!ghostImages[poseName]) ghostImages[poseName] = {};
        ghostImages[poseName][i] = img;
    }

    // Load metadata and target poses
    for (let i = 0; i < 4; i++) {
        try {
            const metaResponse = await fetch(`/pickelball/main/backend/shadowing_assets_api.php?pose=${poseName}&type=meta&stage=${i}`);
            if (!metaResponse.ok) {
                console.error(`Failed to load meta for ${poseName} stage ${i}:`, metaResponse.statusText);
                continue;
            }
            const metaData = await metaResponse.json();
            if (metaData.error) {
                console.error(`Error in meta data for ${poseName} stage ${i}:`, metaData.error);
                continue;
            }
            if (!ghostMetadata[poseName]) ghostMetadata[poseName] = {};
            ghostMetadata[poseName][i] = metaData;

            const targetResponse = await fetch(`/pickelball/main/backend/shadowing_assets_api.php?pose=${poseName}&type=target&stage=${i}`);
            if (!targetResponse.ok) {
                console.error(`Failed to load target for ${poseName} stage ${i}:`, targetResponse.statusText);
                continue;
            }
            const targetData = await targetResponse.json();
            if (targetData.error) {
                console.error(`Error in target data for ${poseName} stage ${i}:`, targetData.error);
                continue;
            }
            if (!targetPoses[poseName]) targetPoses[poseName] = {};
            targetPoses[poseName][i] = targetData;
        } catch (error) {
            console.error(`Error loading assets for ${poseName} stage ${i}:`, error);
        }
    }
    
    console.log(`Loaded assets for ${poseName}:`, {
        images: Object.keys(ghostImages[poseName] || {}).length,
        metadata: Object.keys(ghostMetadata[poseName] || {}).length,
        targets: Object.keys(targetPoses[poseName] || {}).length
    });
}

function startDetectionLoop() {
    function detect() {
        if (!ghostTrainerActive || !cameraPreview || !poseDetector) {
            if (animationFrameId) {
                cancelAnimationFrame(animationFrameId);
                animationFrameId = null;
            }
            return;
        }
        
        // Check if video is ready
        if (cameraPreview.readyState >= 2) { // HAVE_CURRENT_DATA or higher
            try {
                poseDetector.send({ image: cameraPreview });
            } catch (error) {
                console.error('Error sending frame to MediaPipe:', error);
            }
        }
        
        animationFrameId = requestAnimationFrame(detect);
    }
    detect();
}

function onPoseResults(results) {
    if (!ghostTrainerActive) return;

    const poseName = POSES_LIST[currentPoseIdx];
    
    // Update UI
    poseNameDisplay.textContent = `POSE: ${poseName.toUpperCase()}`;
    stageDisplay.textContent = `Step: ${currentStage + 1}/4`;

    // Set canvas size to match video
    if (ghostCanvas.width !== cameraPreview.videoWidth || ghostCanvas.height !== cameraPreview.videoHeight) {
        ghostCanvas.width = cameraPreview.videoWidth;
        ghostCanvas.height = cameraPreview.videoHeight;
    }

    const ctx = ghostCanvas.getContext('2d');
    ctx.clearRect(0, 0, ghostCanvas.width, ghostCanvas.height);

    // Handle cooldown
    if (inCooldown) {
        const elapsed = Date.now() - cooldownStart;
        const remaining = Math.ceil((COOLDOWN_TIME - elapsed) / 1000);
        
        if (remaining > 0) {
            cooldownMessage.style.display = 'block';
            cooldownTimer.textContent = remaining;
            nextStageNum.textContent = currentStage + 1;
            
            // Show preview of next ghost
            if (ghostImages[poseName] && ghostImages[poseName][currentStage] && results.poseLandmarks) {
                drawGhostOverlay(ctx, ghostImages[poseName][currentStage], ghostMetadata[poseName][currentStage], results.poseLandmarks, 0.2);
            }
        } else {
            inCooldown = false;
            cooldownMessage.style.display = 'none';
            lastMatchTime = Date.now();
        }
        return;
    }

    // Check if all stages completed
    if (currentStage >= 4) {
        ctx.fillStyle = 'rgba(0, 255, 0, 0.8)';
        ctx.font = 'bold 48px Arial';
        ctx.textAlign = 'center';
        ctx.fillText(`${poseName.toUpperCase()} COMPLETE!`, ghostCanvas.width / 2, ghostCanvas.height / 2);
        return;
    }

    // Load current stage data
    const ghostImg = ghostImages[poseName] && ghostImages[poseName][currentStage];
    const ghostMeta = ghostMetadata[poseName] && ghostMetadata[poseName][currentStage];
    const targetLms = targetPoses[poseName] && targetPoses[poseName][currentStage];

    if (!ghostImg || !ghostMeta || !targetLms) {
        ctx.fillStyle = 'red';
        ctx.font = '24px Arial';
        ctx.fillText('Loading assets...', 50, 50);
        return;
    }

    if (!results.poseLandmarks || results.poseLandmarks.length === 0) {
        scoreDisplay.textContent = 'SCORE: Please stand in frame';
        scoreDisplay.style.color = '#ffff00';
        return;
    }

    // Draw ghost overlay
    drawGhostOverlay(ctx, ghostImg, ghostMeta, results.poseLandmarks, 0.4);

    // Calculate similarity score
    const simScore = calculateCosineSimilarity(results.poseLandmarks, targetLms);

    // Update score display
    const scorePercent = Math.round(simScore * 100);
    scoreDisplay.textContent = `SCORE: ${scorePercent}%`;
    scoreDisplay.style.color = simScore > SIMILARITY_THRESH ? '#00ff00' : '#ff0000';

    // Check if pose matches
    if (simScore > SIMILARITY_THRESH) {
        matchDuration = Date.now() - lastMatchTime;
        const progress = Math.min(100, (matchDuration / REQUIRED_HOLD_TIME) * 100);
        progressBar.style.width = progress + '%';

        if (matchDuration > REQUIRED_HOLD_TIME) {
            // Stage complete!
            perfectMessage.style.display = 'block';
            setTimeout(() => {
                perfectMessage.style.display = 'none';
            }, 500);

            currentStage++;
            matchDuration = 0;
            progressBar.style.width = '0%';

            if (currentStage < 4) {
                // Start cooldown
                inCooldown = true;
                cooldownStart = Date.now();
            }
        }
    } else {
        lastMatchTime = Date.now();
        matchDuration = 0;
        progressBar.style.width = '0%';
    }
}

function drawGhostOverlay(ctx, ghostImg, ghostMeta, userLandmarks, alpha) {
    if (!ghostImg || !ghostMeta || !userLandmarks || userLandmarks.length < 33) return;

    const canvasWidth = ghostCanvas.width;
    const canvasHeight = ghostCanvas.height;

    // Get user torso height (in pixels) - MediaPipe landmarks are normalized (0-1)
    const userShoulderY = (userLandmarks[11].y + userLandmarks[12].y) / 2;
    const userHipY = (userLandmarks[23].y + userLandmarks[24].y) / 2;
    const userTorsoPx = Math.abs(userShoulderY - userHipY) * canvasHeight;
    const userHipX = (userLandmarks[23].x + userLandmarks[24].x) / 2;

    // Get ghost metadata
    const ghostTorsoRatio = ghostMeta[0];
    const ghostHipX = ghostMeta[1];
    const ghostHipY = ghostMeta[2];

    if (ghostTorsoRatio === 0 || userTorsoPx === 0) return;

    // Calculate target size
    let targetH = userTorsoPx / ghostTorsoRatio;
    const aspectRatio = ghostImg.width / ghostImg.height;
    let targetW = targetH * aspectRatio;

    // Safety clamp
    if (targetH > canvasHeight * 2.5) {
        targetH = canvasHeight * 2.5;
        targetW = targetH * aspectRatio;
    }

    // Calculate position (align hips)
    const userHipPxX = userHipX * canvasWidth;
    const userHipPxY = userHipY * canvasHeight;
    const ghostHipPxX = ghostHipX * targetW;
    const ghostHipPxY = ghostHipY * targetH;

    const topLeftX = userHipPxX - ghostHipPxX;
    const topLeftY = userHipPxY - ghostHipPxY;

    // Draw with alpha
    ctx.globalAlpha = alpha;
    ctx.drawImage(ghostImg, topLeftX, topLeftY, targetW, targetH);
    ctx.globalAlpha = 1.0;
}

function calculateCosineSimilarity(userLandmarks, targetLmsArray) {
    const CONNECTIONS = [
        [11, 13], [13, 15], [12, 14], [14, 16],
        [11, 23], [12, 24],
        [23, 25], [24, 26]
    ];

    let totalScore = 0;
    let validConnections = 0;

    for (const [idx1, idx2] of CONNECTIONS) {
        if (!userLandmarks[idx1] || !userLandmarks[idx2] || !targetLmsArray[idx1] || !targetLmsArray[idx2]) continue;

        // MediaPipe landmarks have x, y, z, visibility properties
        const u1 = { x: userLandmarks[idx1].x, y: userLandmarks[idx1].y };
        const u2 = { x: userLandmarks[idx2].x, y: userLandmarks[idx2].y };
        const uVec = { x: u2.x - u1.x, y: u2.y - u1.y };

        // Target landmarks are arrays [x, y]
        const t1 = { x: targetLmsArray[idx1][0], y: targetLmsArray[idx1][1] };
        const t2 = { x: targetLmsArray[idx2][0], y: targetLmsArray[idx2][1] };
        const tVec = { x: t2.x - t1.x, y: t2.y - t1.y };

        const normU = Math.sqrt(uVec.x * uVec.x + uVec.y * uVec.y);
        const normT = Math.sqrt(tVec.x * tVec.x + tVec.y * tVec.y);

        if (normU > 0 && normT > 0) {
            const dotProduct = uVec.x * tVec.x + uVec.y * tVec.y;
            const score = dotProduct / (normU * normT);
            totalScore += score;
            validConnections++;
        }
    }

    return validConnections === 0 ? 0 : totalScore / validConnections;
}

// Make functions globally accessible for onclick handlers
window.checkCamera = checkCamera;
window.runModel = runModel;
window.startGhostTrainer = startGhostTrainer;
window.nextPose = nextPose;
window.resetStage = resetStage;
window.toggleVideo = toggleVideo;
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

