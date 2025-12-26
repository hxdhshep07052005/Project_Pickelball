<?php
declare(strict_types=1);

/**
 * Live Action Detection page frontend
 * Real-time pose detection from webcam
 */

require_once __DIR__ . '/../../user/backend/require_auth.php';
require_once __DIR__ . '/../../user/backend/bootstrap.php';
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../includes/header.php';
?>
<style>
    .live-action-section {
        min-height: calc(100vh - 72px);
        padding: 40px 24px;
        background: linear-gradient(135deg, #f0fdf4 0%, #ffffff 100%);
    }
    .live-action-container {
        max-width: 1400px;
        margin: 0 auto;
    }
    .page-header {
        text-align: center;
        margin-bottom: 40px;
    }
    .page-title {
        font-size: 48px;
        font-weight: 800;
        color: #0f172a;
        margin-bottom: 12px;
        letter-spacing: -1px;
    }
    .page-subtitle {
        font-size: 18px;
        color: #334155;
        max-width: 600px;
        margin: 0 auto;
        line-height: 1.6;
    }
    .live-prediction-area {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 32px;
        margin-bottom: 32px;
    }
    @media (max-width: 968px) {
        .live-prediction-area {
            grid-template-columns: 1fr;
        }
    }
    .video-panel, .prediction-panel {
        background: #ffffff;
        border-radius: 16px;
        padding: 32px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    }
    .panel-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 24px;
        padding-bottom: 16px;
        border-bottom: 2px solid #e2e8f0;
    }
    .panel-title {
        font-size: 24px;
        font-weight: 700;
        color: #0f172a;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .panel-title svg {
        width: 28px;
        height: 28px;
        color: #10b981;
    }
    .video-container {
        position: relative;
        width: 100%;
        aspect-ratio: 16/9;
        background: #000;
        border-radius: 12px;
        overflow: hidden;
    }
    #videoElement {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .video-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(0, 0, 0, 0.7);
        color: #ffffff;
        font-size: 18px;
        z-index: 10;
    }
    .video-overlay.hidden {
        display: none;
    }
    .controls {
        display: flex;
        gap: 12px;
        margin-top: 24px;
        flex-wrap: wrap;
    }
    .btn {
        padding: 12px 24px;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .btn-primary {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: #ffffff;
    }
    .btn-primary:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
    }
    .btn-secondary {
        background: #f1f5f9;
        color: #334155;
    }
    .btn-secondary:hover:not(:disabled) {
        background: #e2e8f0;
    }
    .btn-danger {
        background: #ef4444;
        color: #ffffff;
    }
    .btn-danger:hover:not(:disabled) {
        background: #dc2626;
    }
    .btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    .btn svg {
        width: 20px;
        height: 20px;
    }
    .prediction-display {
        min-height: 200px;
    }
    .prediction-status {
        text-align: center;
        padding: 24px;
        border-radius: 12px;
        margin-bottom: 24px;
    }
    .prediction-status.buffering {
        background: #fef3c7;
        color: #92400e;
    }
    .prediction-status.ready {
        background: #d1fae5;
        color: #065f46;
    }
    .prediction-status.error {
        background: #fee2e2;
        color: #991b1b;
    }
    .prediction-result {
        text-align: center;
    }
    .predicted-class {
        font-size: 36px;
        font-weight: 800;
        color: #10b981;
        margin-bottom: 16px;
    }
    .confidence-score {
        font-size: 24px;
        color: #334155;
        margin-bottom: 24px;
    }
    .probabilities {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    .probability-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 16px;
        background: #f8fafc;
        border-radius: 8px;
    }
    .probability-label {
        font-weight: 600;
        color: #334155;
    }
    .probability-value {
        font-weight: 700;
        color: #10b981;
    }
    .progress-bar {
        width: 100%;
        height: 8px;
        background: #e2e8f0;
        border-radius: 4px;
        overflow: hidden;
        margin-top: 8px;
    }
    .progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #10b981 0%, #059669 100%);
        transition: width 0.3s ease;
    }
    .status-indicator {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 14px;
        font-weight: 600;
    }
    .status-indicator.active {
        background: #d1fae5;
        color: #065f46;
    }
    .status-indicator.inactive {
        background: #f1f5f9;
        color: #64748b;
    }
    .status-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        animation: pulse 2s infinite;
    }
    .status-indicator.active .status-dot {
        background: #10b981;
    }
    .status-indicator.inactive .status-dot {
        background: #94a3b8;
    }
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }
    .error-message {
        padding: 16px;
        background: #fee2e2;
        border: 1px solid #fca5a5;
        border-radius: 8px;
        color: #991b1b;
        margin-top: 16px;
    }
</style>
<section class="live-action-section">
    <div class="live-action-container">
        <div class="page-header animate-on-scroll fade-in-up">
            <h1 class="page-title"><?php echo htmlspecialchars(t('live_action_detection'), ENT_QUOTES, 'UTF-8'); ?></h1>
            <p class="page-subtitle"><?php echo htmlspecialchars(t('live_action_subtitle'), ENT_QUOTES, 'UTF-8'); ?></p>
        </div>

        <div class="live-prediction-area">
            <!-- Video Panel -->
            <div class="video-panel animate-on-scroll fade-in-up">
                <div class="panel-header">
                    <h2 class="panel-title">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17 10.5V7c0-.55-.45-1-1-1H4c-.55 0-1 .45-1 1v10c0 .55.45 1 1 1h12c.55 0 1-.45 1-1v-3.5l4 4v-11l-4 4z"/></svg>
                        <?php echo htmlspecialchars(t('webcam_feed'), ENT_QUOTES, 'UTF-8'); ?>
                    </h2>
                    <div class="status-indicator inactive" id="statusIndicator">
                        <span class="status-dot"></span>
                        <span id="statusText"><?php echo htmlspecialchars(t('inactive'), ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                </div>
                <div class="video-container">
                    <video id="videoElement" autoplay playsinline></video>
                    <div class="video-overlay" id="videoOverlay">
                        <div><?php echo htmlspecialchars(t('camera_not_started'), ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                </div>
                <div class="controls">
                    <button class="btn btn-primary" id="startBtn" onclick="startCamera()">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
                        <?php echo htmlspecialchars(t('start_detection'), ENT_QUOTES, 'UTF-8'); ?>
                    </button>
                    <button class="btn btn-secondary" id="stopBtn" onclick="stopCamera()" disabled>
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M6 6h12v12H6z"/></svg>
                        <?php echo htmlspecialchars(t('stop_detection'), ENT_QUOTES, 'UTF-8'); ?>
                    </button>
                    <button class="btn btn-secondary" id="resetBtn" onclick="resetBuffer()" disabled>
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.65 6.35C16.2 4.9 14.21 4 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08c-.82 2.33-3.04 4-5.65 4-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/></svg>
                        <?php echo htmlspecialchars(t('reset'), ENT_QUOTES, 'UTF-8'); ?>
                    </button>
                </div>
            </div>

            <!-- Prediction Panel -->
            <div class="prediction-panel animate-on-scroll fade-in-up">
                <div class="panel-header">
                    <h2 class="panel-title">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                        <?php echo htmlspecialchars(t('prediction_results'), ENT_QUOTES, 'UTF-8'); ?>
                    </h2>
                </div>
                <div class="prediction-display" id="predictionDisplay">
                    <div class="prediction-status inactive">
                        <p><?php echo htmlspecialchars(t('waiting_for_camera'), ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script src="/pickelball/main/frontend/js/scroll-animation.js"></script>
<script>
let videoStream = null;
let isDetecting = false;
let detectionInterval = null;
const PREDICTION_INTERVAL = 300; // Send frame every 300ms for faster response

const videoElement = document.getElementById('videoElement');
const startBtn = document.getElementById('startBtn');
const stopBtn = document.getElementById('stopBtn');
const resetBtn = document.getElementById('resetBtn');
const videoOverlay = document.getElementById('videoOverlay');
const statusIndicator = document.getElementById('statusIndicator');
const statusText = document.getElementById('statusText');
const predictionDisplay = document.getElementById('predictionDisplay');

async function startCamera() {
    try {
        videoStream = await navigator.mediaDevices.getUserMedia({
            video: {
                width: { ideal: 1280 },
                height: { ideal: 720 },
                facingMode: 'user'
            }
        });
        
        videoElement.srcObject = videoStream;
        videoOverlay.classList.add('hidden');
        startBtn.disabled = true;
        stopBtn.disabled = false;
        resetBtn.disabled = false;
        
        updateStatus('active', '<?php echo htmlspecialchars(t('detecting'), ENT_QUOTES, 'UTF-8'); ?>');
        
        // Wait for video to be ready before starting detection
        const startDetectionWhenReady = () => {
            if (videoElement.readyState >= videoElement.HAVE_CURRENT_DATA && 
                videoElement.videoWidth > 0 && videoElement.videoHeight > 0) {
                console.log('Video ready, starting detection');
                isDetecting = true;
                startDetection();
            } else {
                // Try again after a short delay
                console.log('Video not ready yet, retrying...', {
                    readyState: videoElement.readyState,
                    width: videoElement.videoWidth,
                    height: videoElement.videoHeight
                });
                setTimeout(startDetectionWhenReady, 100);
            }
        };
        
        // Start detection when video metadata is loaded
        videoElement.addEventListener('loadedmetadata', startDetectionWhenReady, { once: true });
        
        // Also try immediately in case it's already loaded
        startDetectionWhenReady();
        
    } catch (error) {
        console.error('Error accessing camera:', error);
        showError('<?php echo htmlspecialchars(t('camera_error'), ENT_QUOTES, 'UTF-8'); ?>: ' + error.message);
        updateStatus('inactive', '<?php echo htmlspecialchars(t('error'), ENT_QUOTES, 'UTF-8'); ?>');
    }
}

function stopCamera() {
    if (videoStream) {
        videoStream.getTracks().forEach(track => track.stop());
        videoStream = null;
    }
    
    videoElement.srcObject = null;
    videoOverlay.classList.remove('hidden');
    startBtn.disabled = false;
    stopBtn.disabled = true;
    resetBtn.disabled = true;
    
    isDetecting = false;
    if (detectionInterval) {
        clearInterval(detectionInterval);
        detectionInterval = null;
    }
    
    updateStatus('inactive', '<?php echo htmlspecialchars(t('inactive'), ENT_QUOTES, 'UTF-8'); ?>');
    showWaiting();
}

function startDetection() {
    if (detectionInterval) {
        clearInterval(detectionInterval);
    }
    
    // Update display to show detection is starting
    predictionDisplay.innerHTML = `
        <div class="prediction-status inactive">
            <p><?php echo htmlspecialchars(t('detecting'), ENT_QUOTES, 'UTF-8'); ?>...</p>
        </div>
    `;
    
    // Start immediately if video is ready
    if (isDetecting && videoElement.readyState >= videoElement.HAVE_CURRENT_DATA && 
        videoElement.videoWidth > 0 && videoElement.videoHeight > 0) {
        console.log('Starting immediate capture');
        captureAndPredict();
    }
    
    detectionInterval = setInterval(() => {
        if (isDetecting && videoElement.readyState >= videoElement.HAVE_CURRENT_DATA && 
            videoElement.videoWidth > 0 && videoElement.videoHeight > 0) {
            captureAndPredict();
        }
    }, PREDICTION_INTERVAL);
}

function captureAndPredict() {
    try {
        // Capture frame from video
        const canvas = document.createElement('canvas');
        canvas.width = videoElement.videoWidth;
        canvas.height = videoElement.videoHeight;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(videoElement, 0, 0);
        
        // Convert to base64
        const frameData = canvas.toDataURL('image/jpeg', 0.8);
        
        // Send to backend
        sendFrameForPrediction(frameData);
        
    } catch (error) {
        console.error('Error capturing frame:', error);
    }
}

async function sendFrameForPrediction(frameData) {
    try {
        const formData = new FormData();
        formData.append('action', 'predict');
        formData.append('frame', frameData);
        
        const response = await fetch('/pickelball/main/backend/live_action.php', {
            method: 'POST',
            body: formData
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        
        if (result.success) {
            displayPrediction(result);
        } else {
            showError(result.error || '<?php echo htmlspecialchars(t('prediction_error'), ENT_QUOTES, 'UTF-8'); ?>');
        }
        
    } catch (error) {
        console.error('Prediction error:', error);
        showError('<?php echo htmlspecialchars(t('prediction_error'), ENT_QUOTES, 'UTF-8'); ?>: ' + error.message);
    }
}

function displayPrediction(result) {
    // Always show prediction, even if it's "Waiting..."
    if (result.status === 'ready' && result.predicted_class) {
        // Don't show "Waiting..." if confidence is 0
        if (result.predicted_class === 'Waiting...' && result.confidence === 0) {
            predictionDisplay.innerHTML = `
                <div class="prediction-status inactive">
                    <p><?php echo htmlspecialchars(t('waiting_for_camera'), ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            `;
            return;
        }
        
        predictionDisplay.innerHTML = `
            <div class="prediction-result">
                <div class="predicted-class">${result.predicted_class}</div>
                ${result.confidence > 0 ? `<div class="confidence-score"><?php echo htmlspecialchars(t('confidence'), ENT_QUOTES, 'UTF-8'); ?>: ${result.confidence}%</div>` : ''}
                <div class="probabilities">
                    ${Object.entries(result.probabilities || {}).map(([label, value]) => `
                        <div class="probability-item">
                            <span class="probability-label">${label}</span>
                            <span class="probability-value">${value}%</span>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
        return;
    }
    
    showWaiting();
}

function showWaiting() {
    predictionDisplay.innerHTML = `
        <div class="prediction-status inactive">
            <p><?php echo htmlspecialchars(t('waiting_for_camera'), ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
    `;
}

function showError(message) {
    predictionDisplay.innerHTML = `
        <div class="prediction-status error">
            <p><strong><?php echo htmlspecialchars(t('error'), ENT_QUOTES, 'UTF-8'); ?></strong></p>
            <p>${message}</p>
        </div>
    `;
}

function updateStatus(status, text) {
    statusIndicator.className = `status-indicator ${status}`;
    statusText.textContent = text;
}

async function resetBuffer() {
    try {
        const formData = new FormData();
        formData.append('action', 'reset');
        
        const response = await fetch('/pickelball/main/backend/live_action.php', {
            method: 'POST',
            body: formData
        });
        
        if (!response.ok) {
            const errorText = await response.text();
            console.error('Reset response error:', errorText);
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        
        if (result.success) {
            showWaiting();
            // Restart detection if camera is still active
            if (isDetecting && videoStream) {
                if (detectionInterval) {
                    clearInterval(detectionInterval);
                }
                startDetection();
            }
        } else {
            throw new Error(result.error || 'Reset failed');
        }
        
    } catch (error) {
        console.error('Reset error:', error);
        showError('<?php echo htmlspecialchars(t('reset_error'), ENT_QUOTES, 'UTF-8'); ?>: ' + error.message);
    }
}

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    stopCamera();
});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

