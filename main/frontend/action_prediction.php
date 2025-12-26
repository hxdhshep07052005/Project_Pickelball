<?php
declare(strict_types=1);

/**
 * Action Video Prediction page frontend
 * Uses trained LSTM model to predict DriveBackhand or DriveForehand
 */

require_once __DIR__ . '/../../user/backend/require_auth.php';
require_once __DIR__ . '/../../user/backend/bootstrap.php';
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../includes/header.php';

// Get prediction history from database
$userId = (int)$authUser['id'];
$history = [];
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS action_predictions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        video_name VARCHAR(255) NOT NULL,
        video_path VARCHAR(500) NOT NULL,
        predicted_class VARCHAR(50) NOT NULL,
        confidence DECIMAL(5,2) NOT NULL,
        probabilities TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_id (user_id),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    $stmt = $pdo->prepare('SELECT id, video_name, video_path, predicted_class, confidence, probabilities, created_at FROM action_predictions WHERE user_id = ? ORDER BY created_at DESC LIMIT 50');
    $stmt->execute([$userId]);
    $history = $stmt->fetchAll();
    
    foreach ($history as &$item) {
        $item['probabilities'] = json_decode($item['probabilities'] ?? '{}', true) ?: [];
    }
} catch (PDOException $e) {
    $history = [];
}
?>
<style>
    .action-prediction-section {
        min-height: calc(100vh - 72px);
        padding: 80px 24px;
        background: linear-gradient(135deg, #f0fdf4 0%, #ffffff 100%);
    }
    .prediction-container {
        max-width: 1200px;
        margin: 0 auto;
    }
    .page-header {
        text-align: center;
        margin-bottom: 60px;
    }
    .page-title {
        font-size: 56px;
        font-weight: 800;
        color: #0f172a;
        margin-bottom: 16px;
        letter-spacing: -1px;
    }
    .page-subtitle {
        font-size: 20px;
        color: #334155;
        max-width: 600px;
        margin: 0 auto;
        line-height: 1.6;
        font-weight: 500;
    }
    .upload-section {
        background: #ffffff;
        border-radius: 16px;
        padding: 48px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        margin-bottom: 32px;
    }
    .upload-form {
        display: flex;
        flex-direction: column;
        gap: 24px;
    }
    .file-upload-area {
        border: 2px dashed #cbd5e1;
        border-radius: 12px;
        padding: 48px 24px;
        text-align: center;
        transition: all 0.3s ease;
        cursor: pointer;
        background: #f8fafc;
    }
    .file-upload-area:hover {
        border-color: #10b981;
        background: #f0fdf4;
    }
    .file-upload-area.dragover {
        border-color: #10b981;
        background: #f0fdf4;
    }
    .upload-icon {
        width: 64px;
        height: 64px;
        margin: 0 auto 24px;
        color: #10b981;
    }
    .upload-text {
        font-size: 18px;
        font-weight: 600;
        color: #0f172a;
        margin-bottom: 8px;
    }
    .upload-hint {
        font-size: 14px;
        color: #64748b;
    }
    .file-input {
        display: none;
    }
    .selected-file {
        display: none;
        align-items: center;
        justify-content: space-between;
        padding: 16px;
        background: #f8fafc;
        border-radius: 8px;
        margin-top: 16px;
    }
    .file-name {
        font-weight: 600;
        color: #0f172a;
    }
    .file-size {
        font-size: 14px;
        color: #64748b;
        margin-top: 4px;
    }
    .remove-file {
        padding: 8px 16px;
        background: #ef4444;
        color: #ffffff;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
    }
    .submit-btn {
        padding: 16px 32px;
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: #ffffff;
        border: none;
        border-radius: 12px;
        font-size: 16px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    .submit-btn:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
    }
    .submit-btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
    .result-section {
        background: #ffffff;
        border-radius: 16px;
        padding: 48px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        margin-top: 32px;
    }
    .result-card {
        background: linear-gradient(135deg, #f0fdf4 0%, #ffffff 100%);
        border: 2px solid #10b981;
        border-radius: 12px;
        padding: 32px;
        text-align: center;
    }
    .predicted-class {
        font-size: 32px;
        font-weight: 700;
        color: #059669;
        margin-bottom: 16px;
    }
    .confidence {
        font-size: 24px;
        font-weight: 600;
        color: #0f172a;
        margin-bottom: 24px;
    }
    .probabilities {
        display: flex;
        gap: 16px;
        justify-content: center;
        margin-top: 24px;
    }
    .probability-item {
        padding: 16px 24px;
        background: #f8fafc;
        border-radius: 8px;
        min-width: 150px;
    }
    .probability-label {
        font-size: 14px;
        color: #64748b;
        margin-bottom: 8px;
    }
    .probability-value {
        font-size: 20px;
        font-weight: 700;
        color: #0f172a;
    }
    .history-section {
        background: #ffffff;
        border-radius: 16px;
        padding: 48px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        margin-top: 32px;
    }
    .history-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 32px;
        padding-bottom: 24px;
        border-bottom: 2px solid #e2e8f0;
    }
    .history-title {
        font-size: 28px;
        font-weight: 700;
        color: #0f172a;
    }
    .history-list {
        display: grid;
        gap: 16px;
    }
    .history-item {
        background: #f8fafc;
        border-radius: 12px;
        padding: 20px;
        border: 1px solid #e2e8f0;
        transition: all 0.3s ease;
    }
    .history-item:hover {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        transform: translateY(-2px);
    }
    .history-item-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 12px;
    }
    .history-video-name {
        font-size: 16px;
        font-weight: 600;
        color: #0f172a;
    }
    .history-date {
        font-size: 14px;
        color: #475569;
    }
    .history-prediction {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-top: 12px;
    }
    .prediction-badge {
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 600;
    }
    .badge-backhand {
        background: #dbeafe;
        color: #1e40af;
    }
    .badge-forehand {
        background: #dcfce7;
        color: #166534;
    }
    .loading {
        text-align: center;
        padding: 48px;
        color: #64748b;
    }
    .alert {
        padding: 16px 20px;
        border-radius: 12px;
        margin-bottom: 24px;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .alert-error {
        background: #fef2f2;
        border: 1px solid #fecaca;
        color: #991b1b;
    }
    .alert-success {
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
        color: #166534;
    }
    @media (max-width: 968px) {
        .page-title {
            font-size: 42px;
        }
        .upload-section,
        .result-section {
            padding: 32px 24px;
        }
    }
    @media (max-width: 640px) {
        .page-title {
            font-size: 36px;
        }
        .upload-section,
        .result-section {
            padding: 24px 16px;
        }
        .probabilities {
            flex-direction: column;
        }
    }
</style>
<section class="action-prediction-section">
    <div class="prediction-container">
        <div class="page-header animate-on-scroll fade-in-up">
            <h1 class="page-title"><?php echo htmlspecialchars(t('action_video_prediction'), ENT_QUOTES, 'UTF-8'); ?></h1>
            <p class="page-subtitle"><?php echo htmlspecialchars(t('action_prediction_subtitle'), ENT_QUOTES, 'UTF-8'); ?></p>
        </div>

        <!-- Upload Section -->
        <div class="upload-section animate-on-scroll fade-in-up">
            <div id="alertContainer"></div>
            
            <form id="uploadForm" class="upload-form" enctype="multipart/form-data">
                <div class="file-upload-area" id="uploadArea">
                    <div class="upload-icon">
                        <svg viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                            <path d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96zM14 13v4h-4v-4H7l5-5 5 5h-3z"/>
                        </svg>
                    </div>
                    <div class="upload-text"><?php echo htmlspecialchars(t('upload_video'), ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="upload-hint"><?php echo htmlspecialchars(t('upload_hint'), ENT_QUOTES, 'UTF-8'); ?></div>
                    <input type="file" name="video" id="videoInput" class="file-input" accept="video/*" required>
                </div>
                <div id="selectedFile" class="selected-file">
                    <div>
                        <div class="file-name" id="fileName"></div>
                        <div class="file-size" id="fileSize"></div>
                    </div>
                    <button type="button" class="remove-file" onclick="removeFile()">Remove</button>
                </div>
                <button type="submit" class="submit-btn" id="submitBtn" disabled>
                    <?php echo htmlspecialchars(t('predict_action'), ENT_QUOTES, 'UTF-8'); ?>
                </button>
            </form>
        </div>

        <!-- Results Section -->
        <div id="resultSection" class="result-section animate-on-scroll fade-in-up" style="display: none;">
            <div class="result-card">
                <div class="predicted-class" id="predictedClass"></div>
                <div class="confidence" id="confidence"></div>
                <div class="probabilities" id="probabilities"></div>
            </div>
        </div>

        <!-- History Section -->
        <div class="history-section animate-on-scroll fade-in-up">
            <div class="history-header">
                <h2 class="history-title"><?php echo htmlspecialchars(t('prediction_history'), ENT_QUOTES, 'UTF-8'); ?></h2>
            </div>
            <?php if (empty($history)): ?>
                <div class="loading">
                    <p><?php echo htmlspecialchars(t('no_history'), ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            <?php else: ?>
                <div class="history-list">
                    <?php foreach ($history as $item): ?>
                        <div class="history-item">
                            <div class="history-item-header">
                                <div class="history-video-name">
                                    <svg viewBox="0 0 24 24" fill="currentColor" style="width: 20px; height: 20px; display: inline-block; vertical-align: middle; margin-right: 8px; color: #10b981;">
                                        <path d="M17 10.5V7c0-.55-.45-1-1-1H4c-.55 0-1 .45-1 1v10c0 .55.45 1 1 1h12c.55 0 1-.45 1-1v-3.5l4 4v-11l-4 4z"/>
                                    </svg>
                                    <?php echo htmlspecialchars($item['video_name'], ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <div class="history-date">
                                    <?php echo htmlspecialchars(date('M d, Y H:i', strtotime($item['created_at'])), ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>
                            <div class="history-prediction">
                                <span class="prediction-badge <?php echo $item['predicted_class'] === 'DriveBackhand' ? 'badge-backhand' : 'badge-forehand'; ?>">
                                    <?php echo htmlspecialchars($item['predicted_class'], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                                <span style="color: #64748b; font-weight: 600;">
                                    <?php echo htmlspecialchars($item['confidence'], ENT_QUOTES, 'UTF-8'); ?>% confidence
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<script src="/pickelball/main/frontend/js/scroll-animation.js"></script>
<script>
// File upload handling
const uploadArea = document.getElementById('uploadArea');
const videoInput = document.getElementById('videoInput');
const selectedFile = document.getElementById('selectedFile');
const fileName = document.getElementById('fileName');
const fileSize = document.getElementById('fileSize');
const submitBtn = document.getElementById('submitBtn');
const uploadForm = document.getElementById('uploadForm');
const resultSection = document.getElementById('resultSection');
const alertContainer = document.getElementById('alertContainer');

// Click to select file
uploadArea.addEventListener('click', () => {
    videoInput.click();
});

// Drag and drop
uploadArea.addEventListener('dragover', (e) => {
    e.preventDefault();
    uploadArea.classList.add('dragover');
});

uploadArea.addEventListener('dragleave', () => {
    uploadArea.classList.remove('dragover');
});

uploadArea.addEventListener('drop', (e) => {
    e.preventDefault();
    uploadArea.classList.remove('dragover');
    if (e.dataTransfer.files.length > 0) {
        videoInput.files = e.dataTransfer.files;
        handleFileSelect();
    }
});

videoInput.addEventListener('change', handleFileSelect);

function handleFileSelect() {
    const file = videoInput.files[0];
    if (file) {
        fileName.textContent = file.name;
        fileSize.textContent = formatFileSize(file.size);
        selectedFile.style.display = 'flex';
        submitBtn.disabled = false;
    }
}

function removeFile() {
    videoInput.value = '';
    selectedFile.style.display = 'none';
    submitBtn.disabled = false;
    resultSection.style.display = 'none';
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

function showAlert(message, type) {
    alertContainer.innerHTML = `
        <div class="alert alert-${type}">
            <svg viewBox="0 0 24 24" fill="currentColor" style="width: 20px; height: 20px;">
                ${type === 'error' ? 
                    '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>' :
                    '<path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>'
                }
            </svg>
            <span>${message}</span>
        </div>
    `;
    setTimeout(() => {
        alertContainer.innerHTML = '';
    }, 5000);
}

// Form submission
uploadForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const file = videoInput.files[0];
    if (!file) {
        showAlert('<?php echo htmlspecialchars(t('please_select_video'), ENT_QUOTES, 'UTF-8'); ?>', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('video', file);
    
    submitBtn.disabled = true;
    submitBtn.textContent = '<?php echo htmlspecialchars(t('processing'), ENT_QUOTES, 'UTF-8'); ?>...';
    
    try {
        const response = await fetch('/pickelball/main/backend/action_prediction.php', {
            method: 'POST',
            body: formData
        });
        
        // Check if response is OK
        if (!response.ok) {
            const errorText = await response.text();
            let errorData;
            try {
                errorData = JSON.parse(errorText);
            } catch {
                errorData = { success: false, error: `HTTP ${response.status}: ${errorText.substring(0, 100)}` };
            }
            throw new Error(errorData.error || `HTTP error! status: ${response.status}`);
        }
        
        // Get response text first to check if it's valid JSON
        const responseText = await response.text();
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (e) {
            console.error('Invalid JSON response:', responseText.substring(0, 500));
            throw new Error('Invalid response from server. Please check server logs.');
        }
        
        if (data.success && data.prediction) {
            // Display results
            document.getElementById('predictedClass').textContent = data.prediction.class;
            document.getElementById('confidence').textContent = 
                '<?php echo htmlspecialchars(t('confidence'), ENT_QUOTES, 'UTF-8'); ?>: ' + data.prediction.confidence + '%';
            
            const probabilitiesDiv = document.getElementById('probabilities');
            probabilitiesDiv.innerHTML = `
                <div class="probability-item">
                    <div class="probability-label">DriveBackhand</div>
                    <div class="probability-value">${data.prediction.probabilities.DriveBackhand}%</div>
                </div>
                <div class="probability-item">
                    <div class="probability-label">DriveForehand</div>
                    <div class="probability-value">${data.prediction.probabilities.DriveForehand}%</div>
                </div>
            `;
            
            resultSection.style.display = 'block';
            resultSection.scrollIntoView({ behavior: 'smooth' });
            
            // Reset form
            submitBtn.disabled = false;
            submitBtn.textContent = '<?php echo htmlspecialchars(t('predict_action'), ENT_QUOTES, 'UTF-8'); ?>';
            videoInput.value = '';
            selectedFile.style.display = 'none';
            
            // Reload history section without reloading the entire page
            setTimeout(() => {
                loadHistory();
            }, 1000);
        } else {
            showAlert(data.error || '<?php echo htmlspecialchars(t('prediction_failed'), ENT_QUOTES, 'UTF-8'); ?>', 'error');
            submitBtn.disabled = false;
            submitBtn.textContent = '<?php echo htmlspecialchars(t('predict_action'), ENT_QUOTES, 'UTF-8'); ?>';
        }
    } catch (error) {
        console.error('Error:', error);
        showAlert('<?php echo htmlspecialchars(t('upload_error'), ENT_QUOTES, 'UTF-8'); ?>', 'error');
        submitBtn.disabled = false;
        submitBtn.textContent = '<?php echo htmlspecialchars(t('predict_action'), ENT_QUOTES, 'UTF-8'); ?>';
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

