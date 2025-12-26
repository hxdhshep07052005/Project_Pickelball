<?php
declare(strict_types=1);

/**
 * Video analysis page frontend
 * Displays video upload form and analysis results
 */

require_once __DIR__ . '/../../user/backend/require_auth.php';
require_once __DIR__ . '/../../user/backend/bootstrap.php';
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../includes/header.php';

// Get messages from session
$error = $_SESSION['analysis_error'] ?? null;
$success = $_SESSION['analysis_success'] ?? null;
$result = $_SESSION['analysis_result'] ?? null;

unset($_SESSION['analysis_error'], $_SESSION['analysis_success'], $_SESSION['analysis_result']);

// Get analysis history from database
$userId = (int)$authUser['id'];
$history = [];
try {
    $stmt = $pdo->prepare('SELECT id, video_name, video_path, techniques_detected, score, status, session_id, coaching_feedback, created_at FROM video_analyses WHERE user_id = ? ORDER BY created_at DESC LIMIT 50');
    $stmt->execute([$userId]);
    $history = $stmt->fetchAll();
    
    // Decode JSON techniques and coaching feedback
    foreach ($history as &$item) {
        $item['techniques_detected'] = json_decode($item['techniques_detected'] ?? '[]', true) ?: [];
        if (!empty($item['coaching_feedback'])) {
            $coaching = json_decode($item['coaching_feedback'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $item['coaching_feedback'] = $coaching;
            }
            // If not JSON, keep as string
        }
    }
} catch (PDOException $e) {
    // If table doesn't exist yet, history will be empty
    $history = [];
}
?>
<style>
    .video-analysis-section {
        min-height: calc(100vh - 72px);
        padding: 80px 24px;
        background: linear-gradient(135deg, #f0fdf4 0%, #ffffff 100%);
    }
    .analysis-container {
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
    select {
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2364748b' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 16px center;
        padding-right: 40px;
    }
    select:focus {
        outline: none;
        border-color: #10b981;
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
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
        margin: 0 auto 16px;
        color: #10b981;
    }
    .upload-icon svg {
        width: 100%;
        height: 100%;
        fill: currentColor;
    }
    .upload-text {
        font-size: 18px;
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 8px;
    }
    .upload-hint {
        font-size: 14px;
        color: #475569;
        font-weight: 500;
    }
    .file-input {
        display: none;
    }
    .selected-file {
        margin-top: 16px;
        padding: 12px 16px;
        background: #f1f5f9;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }
    .file-name {
        font-size: 14px;
        color: #1e293b;
        font-weight: 600;
    }
    .file-size {
        font-size: 12px;
        color: #475569;
        font-weight: 500;
    }
    .remove-file {
        background: #ef4444;
        color: #ffffff;
        border: none;
        padding: 4px 12px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 12px;
        font-weight: 600;
    }
    .submit-btn {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: #ffffff;
        border: none;
        padding: 16px 32px;
        border-radius: 12px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }
    .submit-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
    }
    .submit-btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
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
    .alert-icon {
        width: 20px;
        height: 20px;
        flex-shrink: 0;
    }
    .result-section {
        background: #ffffff;
        border-radius: 16px;
        padding: 48px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        margin-top: 32px;
    }
    .result-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 32px;
        padding-bottom: 24px;
        border-bottom: 2px solid #e2e8f0;
    }
    .result-title {
        font-size: 28px;
        font-weight: 700;
        color: #0f172a;
    }
    .model-status {
        padding: 8px 16px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
    }
    .status-not-working {
        background: #fef2f2;
        color: #991b1b;
    }
    .status-working {
        background: #f0fdf4;
        color: #166534;
    }
    .result-content {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 32px;
    }
    .result-card {
        background: #f8fafc;
        border-radius: 12px;
        padding: 24px;
    }
    .card-title {
        font-size: 16px;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 16px;
    }
    .card-content {
        font-size: 15px;
        color: #334155;
        line-height: 1.7;
        font-weight: 500;
    }
    .techniques-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .technique-item {
        padding: 12px;
        background: #ffffff;
        border-radius: 8px;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .technique-item span {
        color: #1e293b;
        font-weight: 500;
    }
    .technique-icon {
        width: 24px;
        height: 24px;
        color: #10b981;
    }
    .empty-state {
        text-align: center;
        padding: 48px 24px;
        color: #475569;
    }
    .empty-state h3 {
        color: #1e293b;
        font-weight: 600;
    }
    .empty-state p {
        color: #475569;
        font-weight: 500;
    }
    .empty-state-icon {
        width: 80px;
        height: 80px;
        margin: 0 auto 24px;
        opacity: 0.5;
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
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .history-video-name svg {
        width: 20px;
        height: 20px;
        color: #10b981;
    }
    .history-date {
        font-size: 14px;
        color: #475569;
        font-weight: 500;
    }
    .history-techniques {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 12px;
    }
    .technique-tag {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: #ffffff;
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 600;
    }
    .no-techniques {
        font-size: 14px;
        color: #94a3b8;
        font-style: italic;
    }
    .history-status {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 600;
        margin-top: 8px;
    }
    .status-model-not-working {
        background: #fef2f2;
        color: #991b1b;
    }
    .status-completed {
        background: #f0fdf4;
        color: #166534;
    }
    /* Chat Interface Styles - Modern Chat Box Design */
    .chat-container {
        display: flex;
        flex-direction: column;
        height: 700px;
        background: #ffffff;
        border-radius: 20px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
        overflow: hidden;
        border: 1px solid #e2e8f0;
    }
    .chat-messages {
        flex: 1;
        overflow-y: auto;
        padding: 20px;
        display: flex;
        flex-direction: column;
        gap: 16px;
        background: linear-gradient(to bottom, #f8fafc 0%, #ffffff 100%);
        scroll-behavior: smooth;
    }
    .chat-messages::-webkit-scrollbar {
        width: 6px;
    }
    .chat-messages::-webkit-scrollbar-track {
        background: transparent;
    }
    .chat-messages::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 3px;
    }
    .chat-messages::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }
    .chat-message {
        display: flex;
        gap: 10px;
        animation: slideIn 0.3s ease-out;
        align-items: flex-start;
    }
    @keyframes slideIn {
        from { 
            opacity: 0; 
            transform: translateY(15px) scale(0.95); 
        }
        to { 
            opacity: 1; 
            transform: translateY(0) scale(1); 
        }
    }
    .chat-message-user {
        flex-direction: row-reverse;
    }
    .chat-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }
    .chat-message-assistant .chat-avatar {
        background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
        color: #ffffff;
    }
    .chat-message-user .chat-avatar {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: #ffffff;
    }
    .chat-avatar svg {
        width: 18px;
        height: 18px;
    }
    .chat-content {
        flex: 1;
        max-width: 70%;
        display: flex;
        flex-direction: column;
    }
    .chat-message-user .chat-content {
        align-items: flex-end;
    }
    .chat-header {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 4px;
        padding: 0 4px;
    }
    .chat-message-user .chat-header {
        justify-content: flex-end;
    }
    .chat-name {
        font-weight: 600;
        font-size: 13px;
        color: #475569;
        letter-spacing: 0.3px;
    }
    .chat-time {
        font-size: 11px;
        color: #94a3b8;
        font-weight: 400;
    }
    .chat-text {
        background: #ffffff;
        padding: 12px 16px;
        border-radius: 18px;
        color: #1e293b;
        font-size: 14.5px;
        line-height: 1.6;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        word-wrap: break-word;
        position: relative;
        border: 1px solid #f1f5f9;
    }
    .chat-message-assistant .chat-text {
        border-bottom-left-radius: 4px;
        background: #ffffff;
    }
    .chat-message-user .chat-text {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: #ffffff;
        border: none;
        border-bottom-right-radius: 4px;
        box-shadow: 0 2px 8px rgba(59, 130, 246, 0.25);
    }
    .chat-suggestions {
        padding: 12px 20px;
        background: #ffffff;
        border-top: 1px solid #e2e8f0;
        border-bottom: 1px solid #e2e8f0;
    }
    .suggestion-title {
        font-size: 11px;
        font-weight: 600;
        color: #64748b;
        margin-bottom: 10px;
        text-transform: uppercase;
        letter-spacing: 0.8px;
    }
    .suggestion-buttons {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }
    .suggestion-btn {
        padding: 6px 14px;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        font-size: 12.5px;
        color: #475569;
        cursor: pointer;
        transition: all 0.2s ease;
        font-weight: 500;
        white-space: nowrap;
    }
    .suggestion-btn:hover {
        background: #e2e8f0;
        color: #1e293b;
        transform: translateY(-1px);
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
    }
    .suggestion-btn:active {
        transform: translateY(0);
    }
    .chat-input-container {
        padding: 12px 20px;
        background: #ffffff;
    }
    .chat-input-wrapper {
        display: flex;
        align-items: flex-end;
        gap: 10px;
        background: #f8fafc;
        border: 2px solid #e2e8f0;
        border-radius: 24px;
        padding: 10px 16px;
        transition: all 0.2s ease;
    }
    .chat-input-wrapper:focus-within {
        border-color: #3b82f6;
        background: #ffffff;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    .chat-input {
        flex: 1;
        border: none;
        background: transparent;
        resize: none;
        font-size: 14.5px;
        color: #1e293b;
        font-family: inherit;
        outline: none;
        max-height: 120px;
        overflow-y: auto;
        line-height: 1.5;
        padding: 0;
    }
    .chat-input::placeholder {
        color: #94a3b8;
    }
    .chat-send-btn {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        border: none;
        color: #ffffff;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
        flex-shrink: 0;
        box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
    }
    .chat-send-btn:hover:not(:disabled) {
        transform: scale(1.08);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
    }
    .chat-send-btn:active:not(:disabled) {
        transform: scale(1.0);
    }
    .chat-send-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none;
    }
    .chat-send-btn svg {
        width: 18px;
        height: 18px;
    }
    .chat-loading {
        display: inline-flex;
        gap: 4px;
        padding: 4px 0;
        align-items: center;
    }
    .chat-loading span {
        width: 6px;
        height: 6px;
        background: #94a3b8;
        border-radius: 50%;
        animation: bounce 1.4s infinite ease-in-out;
    }
    .chat-loading span:nth-child(1) { animation-delay: -0.32s; }
    .chat-loading span:nth-child(2) { animation-delay: -0.16s; }
    @keyframes bounce {
        0%, 80%, 100% { transform: scale(0.6); opacity: 0.5; }
        40% { transform: scale(1); opacity: 1; }
    }
    @media (max-width: 968px) {
        .result-content {
            grid-template-columns: 1fr;
        }
        .chat-container {
            height: 500px;
        }
        .chat-content {
            max-width: 85%;
        }
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
    }
</style>
<section class="video-analysis-section">
    <div class="analysis-container">
        <div class="page-header animate-on-scroll fade-in-up">
            <h1 class="page-title"><?php echo htmlspecialchars(t('video_analysis'), ENT_QUOTES, 'UTF-8'); ?></h1>
            <p class="page-subtitle"><?php echo htmlspecialchars(t('video_analysis_subtitle'), ENT_QUOTES, 'UTF-8'); ?></p>
        </div>

        <!-- Upload Section -->
        <div class="upload-section animate-on-scroll fade-in-up">
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <svg class="alert-icon" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                    </svg>
                    <span><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <svg class="alert-icon" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                        <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                    </svg>
                    <span><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            <?php endif; ?>

            <form action="/pickelball/main/backend/video_analysis.php" method="POST" enctype="multipart/form-data" class="upload-form" id="uploadForm">
                <!-- Skill Selection -->
                <div style="margin-bottom: 24px;">
                    <label for="skillSelect" style="display: block; font-size: 16px; font-weight: 600; color: #0f172a; margin-bottom: 12px;">
                        <?php echo htmlspecialchars(t('select_technique'), ENT_QUOTES, 'UTF-8'); ?>
                    </label>
                    <select name="skill" id="skillSelect" style="width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 15px; background: #ffffff; color: #0f172a; cursor: pointer; transition: border-color 0.3s;">
                        <option value="drive_forehand" selected><?php echo htmlspecialchars(t('forehand_drive'), ENT_QUOTES, 'UTF-8'); ?></option>
                        <option value="drive_backhand" disabled><?php echo htmlspecialchars(t('backhand_drive'), ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars(t('coming_soon'), ENT_QUOTES, 'UTF-8'); ?>)</option>
                        <option value="serve" disabled>Serve (<?php echo htmlspecialchars(t('coming_soon'), ENT_QUOTES, 'UTF-8'); ?>)</option>
                        <option value="dink" disabled>Dink (<?php echo htmlspecialchars(t('coming_soon'), ENT_QUOTES, 'UTF-8'); ?>)</option>
                    </select>
                    <p style="font-size: 13px; color: #64748b; margin-top: 8px;">
                        <?php echo htmlspecialchars(t('select_technique_hint'), ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                </div>
                
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
                <div id="selectedFile" style="display: none;" class="selected-file">
                    <div>
                        <div class="file-name" id="fileName"></div>
                        <div class="file-size" id="fileSize"></div>
                    </div>
                    <button type="button" class="remove-file" onclick="removeFile()">Remove</button>
                </div>
                <button type="submit" class="submit-btn" id="submitBtn" disabled>
                    <?php echo htmlspecialchars(t('analyze_video'), ENT_QUOTES, 'UTF-8'); ?>
                </button>
            </form>
        </div>

        <!-- Results Section -->
        <?php if ($result): ?>
            <div class="result-section animate-on-scroll fade-in-up">
            <div class="result-header">
                <h2 class="result-title"><?php echo htmlspecialchars(t('analysis_results'), ENT_QUOTES, 'UTF-8'); ?></h2>
                <span class="model-status <?php 
                    echo ($result['status'] === 'completed') ? 'status-working' : 
                         (($result['status'] === 'api_unavailable' || $result['status'] === 'api_error' || $result['status'] === 'analysis_failed') ? 'status-not-working' : 'status-not-working'); 
                ?>">
                    <?php 
                    if ($result['status'] === 'completed') {
                        echo htmlspecialchars(t('analysis_completed'), ENT_QUOTES, 'UTF-8');
                    } elseif ($result['status'] === 'analysis_failed') {
                        echo htmlspecialchars(t('analysis_failed'), ENT_QUOTES, 'UTF-8');
                    } elseif ($result['status'] === 'api_unavailable') {
                        echo htmlspecialchars(t('api_unavailable'), ENT_QUOTES, 'UTF-8');
                    } elseif ($result['status'] === 'api_error') {
                        echo htmlspecialchars(t('api_error'), ENT_QUOTES, 'UTF-8');
                    } else {
                        echo htmlspecialchars(t('model_not_working'), ENT_QUOTES, 'UTF-8');
                    }
                    ?>
                </span>
            </div>

            <?php if ($result['status'] !== 'completed'): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <svg viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                        </svg>
                    </div>
                    <h3 style="font-size: 20px; color: #1e293b; margin-bottom: 12px; font-weight: 600;">
                        <?php 
                        if ($result['status'] === 'analysis_failed') {
                            echo htmlspecialchars(t('analysis_failed'), ENT_QUOTES, 'UTF-8');
                        } elseif ($result['status'] === 'api_unavailable') {
                            echo htmlspecialchars(t('api_unavailable'), ENT_QUOTES, 'UTF-8');
                        } elseif ($result['status'] === 'api_error') {
                            echo htmlspecialchars(t('api_error'), ENT_QUOTES, 'UTF-8');
                        } else {
                            echo htmlspecialchars(t('ai_model_not_integrated'), ENT_QUOTES, 'UTF-8');
                        }
                        ?>
                    </h3>
                    <p style="max-width: 500px; margin: 0 auto; color: #475569; font-weight: 500;">
                        <?php 
                        if ($result['status'] === 'analysis_failed') {
                            echo htmlspecialchars(t('analysis_failed_desc') ?? 'Video uploaded but analysis failed. Please check debug information below.', ENT_QUOTES, 'UTF-8');
                        } elseif ($result['status'] === 'api_unavailable') {
                            echo htmlspecialchars(t('api_unavailable_desc') ?? 'Analysis service is currently unavailable. Please ensure the ChatBox/run_analysis.py file exists.', ENT_QUOTES, 'UTF-8');
                        } elseif ($result['status'] === 'api_error') {
                            echo htmlspecialchars(t('api_error_desc') ?? 'Error running analysis service. Please check server logs for details.', ENT_QUOTES, 'UTF-8');
                        } else {
                            echo htmlspecialchars(t('model_not_integrated_desc'), ENT_QUOTES, 'UTF-8');
                        }
                        ?>
                    </p>
                    <div style="margin-top: 24px; padding: 16px; background: #f1f5f9; border-radius: 8px; text-align: left; max-width: 500px; margin-left: auto; margin-right: auto;">
                        <div style="font-weight: 700; margin-bottom: 8px; color: #1e293b;"><?php echo htmlspecialchars(t('video_information'), ENT_QUOTES, 'UTF-8'); ?>:</div>
                        <div style="font-size: 14px; color: #475569; font-weight: 500;">
                            <div><strong style="color: #334155;"><?php echo htmlspecialchars(t('file'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo htmlspecialchars($result['video_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <div style="margin-top: 4px;"><strong style="color: #334155;"><?php echo htmlspecialchars(t('uploaded'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo htmlspecialchars($result['uploaded_at'], ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                    </div>
                    <?php if (isset($result['debug_info']) && !empty($result['debug_info'])): ?>
                        <div style="margin-top: 24px; padding: 16px; background: #fef2f2; border-radius: 8px; text-align: left; max-width: 600px; margin-left: auto; margin-right: auto; border: 1px solid #fecaca;">
                            <div style="font-weight: 700; margin-bottom: 12px; color: #991b1b; font-size: 16px;">Debug Information:</div>
                            <div style="font-size: 13px; color: #7f1d1d; font-family: monospace; line-height: 1.8;">
                                <div><strong>Script Path:</strong> <?php echo htmlspecialchars($result['debug_info']['python_script'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></div>
                                <div><strong>Script Exists:</strong> <?php echo ($result['debug_info']['script_exists'] ?? false) ? 'Yes' : 'No'; ?></div>
                                <div><strong>Upload Path:</strong> <?php echo htmlspecialchars($result['debug_info']['upload_path'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></div>
                                <div><strong>Upload Exists:</strong> <?php echo ($result['debug_info']['upload_exists'] ?? false) ? 'Yes' : 'No'; ?></div>
                                <?php if (isset($result['debug_info']['missing_packages'])): ?>
                                    <div style="margin-top: 12px; padding: 12px; background: #fee2e2; border-radius: 6px; border-left: 4px solid #dc2626;">
                                        <div style="font-weight: 700; color: #991b1b; margin-bottom: 8px;">⚠ Missing Python Packages:</div>
                                        <div style="color: #7f1d1d;">
                                            <?php foreach ($result['debug_info']['missing_packages'] as $pkg): ?>
                                                <div>• <?php echo htmlspecialchars($pkg, ENT_QUOTES, 'UTF-8'); ?></div>
                                            <?php endforeach; ?>
                                        </div>
                                        <div style="margin-top: 12px; padding: 8px; background: #ffffff; border-radius: 4px; font-weight: 600; color: #991b1b;">
                                            Install with: <code style="background: #f3f4f6; padding: 4px 8px; border-radius: 4px;">pip install <?php echo htmlspecialchars(implode(' ', $result['debug_info']['missing_packages']), ENT_QUOTES, 'UTF-8'); ?></code>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <?php if (isset($result['debug_info']['analysis_error'])): ?>
                                    <div style="margin-top: 12px; padding: 12px; background: #fee2e2; border-radius: 6px; border-left: 4px solid #dc2626;">
                                        <div style="font-weight: 700; color: #991b1b; margin-bottom: 8px;">Error:</div>
                                        <div style="color: #7f1d1d;"><?php echo htmlspecialchars($result['debug_info']['analysis_error'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    </div>
                                <?php endif; ?>
                                <?php if (isset($result['debug_info']['python_output_preview'])): ?>
                                    <div style="margin-top: 12px; padding: 12px; background: #f3f4f6; border-radius: 6px;">
                                        <div style="font-weight: 700; color: #374151; margin-bottom: 8px;">Python Output Preview:</div>
                                        <div style="color: #4b5563; white-space: pre-wrap; font-size: 11px; max-height: 200px; overflow-y: auto;"><?php echo htmlspecialchars($result['debug_info']['python_output_preview'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                    <!-- Chat Interface -->
                    <div class="chat-container" id="chatContainer" data-session-id="<?php echo htmlspecialchars($result['session_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        <!-- Chat Header -->
                        <div style="padding: 18px 24px; background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: #ffffff; display: flex; align-items: center; gap: 14px; border-bottom: 1px solid rgba(255, 255, 255, 0.15); box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);">
                            <div style="width: 44px; height: 44px; border-radius: 50%; background: rgba(255, 255, 255, 0.25); display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);">
                                <svg viewBox="0 0 24 24" fill="currentColor" style="width: 24px; height: 24px;">
                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                </svg>
                            </div>
                            <div style="flex: 1;">
                                <div style="font-weight: 700; font-size: 17px; letter-spacing: -0.3px;"><?php echo htmlspecialchars(t('ai_coach'), ENT_QUOTES, 'UTF-8'); ?></div>
                                <div style="font-size: 12.5px; opacity: 0.95; margin-top: 3px; font-weight: 400;"><?php echo htmlspecialchars(t('coaching_assistant'), ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>
                            <div style="font-size: 11.5px; opacity: 0.9; display: flex; align-items: center; gap: 6px; background: rgba(255, 255, 255, 0.15); padding: 6px 12px; border-radius: 12px; font-weight: 500;">
                                <div style="width: 8px; height: 8px; background: #10b981; border-radius: 50%; box-shadow: 0 0 8px rgba(16, 185, 129, 0.6);"></div>
                                <?php echo htmlspecialchars(t('online'), ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        </div>
                        
                        <div class="chat-messages" id="chatMessages">
                            <!-- Initial Analysis Result Message -->
                            <div class="chat-message chat-message-assistant">
                                <div class="chat-avatar">
                                    <svg viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                    </svg>
                                </div>
                                <div class="chat-content">
                                    <div class="chat-header">
                                        <span class="chat-name"><?php echo htmlspecialchars(t('ai_coach'), ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span class="chat-time"><?php echo date('H:i'); ?></span>
                                    </div>
                                    <div class="chat-text">
                                        <?php if (!empty($result['coaching_feedback'])): ?>
                                            <?php 
                                            $feedback = is_string($result['coaching_feedback']) ? $result['coaching_feedback'] : json_encode($result['coaching_feedback']);
                                            echo nl2br(htmlspecialchars($feedback, ENT_QUOTES, 'UTF-8')); 
                                            ?>
                                        <?php else: ?>
                                            <?php echo htmlspecialchars(t('analysis_completed'), ENT_QUOTES, 'UTF-8'); ?>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($result['feedback']) && is_array($result['feedback'])): ?>
                                            <div style="margin-top: 12px; padding: 12px 14px; background: rgba(59, 130, 246, 0.06); border-radius: 12px; border-left: 3px solid #3b82f6;">
                                                <div style="font-weight: 600; color: #1e293b; margin-bottom: 10px; font-size: 13px; display: flex; align-items: center; gap: 6px;">
                                                    <svg viewBox="0 0 24 24" fill="currentColor" style="width: 16px; height: 16px; color: #3b82f6;">
                                                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                                                    </svg>
                                                    <?php echo htmlspecialchars(t('key_issues'), ENT_QUOTES, 'UTF-8'); ?>
                                                </div>
                                                <ul style="margin: 0; padding-left: 20px; color: #334155; font-size: 13px; line-height: 1.7;">
                                                    <?php foreach ($result['feedback'] as $item): ?>
                                                        <?php if (isset($item['issue'])): ?>
                                                            <li style="margin-bottom: 8px;">
                                                                <strong style="color: #1e293b; font-weight: 600;"><?php echo htmlspecialchars($item['issue'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                                                <?php if (isset($item['tip'])): ?>
                                                                    <div style="color: #475569; margin-top: 4px; font-size: 12.5px; line-height: 1.6;"><?php echo htmlspecialchars($item['tip'], ENT_QUOTES, 'UTF-8'); ?></div>
                                                                <?php endif; ?>
                                                            </li>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Chat Suggestions -->
                        <div class="chat-suggestions" id="chatSuggestions">
                            <div class="suggestion-title"><?php echo htmlspecialchars(t('suggested_questions'), ENT_QUOTES, 'UTF-8'); ?>:</div>
                            <div class="suggestion-buttons">
                                <button class="suggestion-btn" onclick="sendSuggestion('<?php echo htmlspecialchars(t('how_to_improve'), ENT_QUOTES, 'UTF-8'); ?>')">
                                    <?php echo htmlspecialchars(t('how_to_improve'), ENT_QUOTES, 'UTF-8'); ?>
                                </button>
                                <button class="suggestion-btn" onclick="sendSuggestion('<?php echo htmlspecialchars(t('practice_schedule'), ENT_QUOTES, 'UTF-8'); ?>')">
                                    <?php echo htmlspecialchars(t('practice_schedule'), ENT_QUOTES, 'UTF-8'); ?>
                                </button>
                                <button class="suggestion-btn" onclick="sendSuggestion('<?php echo htmlspecialchars(t('daily_routine'), ENT_QUOTES, 'UTF-8'); ?>')">
                                    <?php echo htmlspecialchars(t('daily_routine'), ENT_QUOTES, 'UTF-8'); ?>
                                </button>
                                <button class="suggestion-btn" onclick="sendSuggestion('<?php echo htmlspecialchars(t('timeline_improvement'), ENT_QUOTES, 'UTF-8'); ?>')">
                                    <?php echo htmlspecialchars(t('timeline_improvement'), ENT_QUOTES, 'UTF-8'); ?>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Chat Input -->
                        <div class="chat-input-container">
                            <div class="chat-input-wrapper">
                                <textarea 
                                    id="chatInput" 
                                    class="chat-input" 
                                    placeholder="<?php echo htmlspecialchars(t('type_your_question'), ENT_QUOTES, 'UTF-8'); ?>"
                                    rows="1"
                                ></textarea>
                                <button id="chatSendBtn" class="chat-send-btn" onclick="sendChatMessage()">
                                    <svg viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- History Section -->
        <div class="history-section animate-on-scroll fade-in-up">
            <div class="history-header">
                <h2 class="history-title"><?php echo htmlspecialchars(t('analysis_history'), ENT_QUOTES, 'UTF-8'); ?></h2>
            </div>
            
            <?php if (empty($history)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <svg viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                            <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/>
                        </svg>
                    </div>
                    <h3 style="font-size: 20px; color: #1e293b; margin-bottom: 12px; font-weight: 600;"><?php echo htmlspecialchars(t('no_analysis_history'), ENT_QUOTES, 'UTF-8'); ?></h3>
                    <p style="max-width: 500px; margin: 0 auto; color: #475569; font-weight: 500;">
                        <?php echo htmlspecialchars(t('no_history_desc'), ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="history-list">
                    <?php foreach ($history as $item): ?>
                        <div class="history-item">
                            <div class="history-item-header">
                                <div class="history-video-name">
                                    <svg viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M17 10.5V7c0-.55-.45-1-1-1H4c-.55 0-1 .45-1 1v10c0 .55.45 1 1 1h12c.55 0 1-.45 1-1v-3.5l4 4v-11l-4 4z"/>
                                    </svg>
                                    <?php echo htmlspecialchars($item['video_name'], ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <div class="history-date">
                                    <?php echo htmlspecialchars(date('M d, Y H:i', strtotime($item['created_at'])), ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>
                            
                            <?php if (!empty($item['techniques_detected'])): ?>
                                <div class="history-techniques">
                                    <?php foreach ($item['techniques_detected'] as $technique): ?>
                                        <span class="technique-tag"><?php echo htmlspecialchars($technique, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="no-techniques"><?php echo htmlspecialchars(t('no_techniques'), ENT_QUOTES, 'UTF-8'); ?></div>
                            <?php endif; ?>
                            
                            <div class="history-status <?php echo $item['status'] === 'model_not_working' ? 'status-model-not-working' : 'status-completed'; ?>">
                                <?php echo $item['status'] === 'model_not_working' ? htmlspecialchars(t('model_not_working'), ENT_QUOTES, 'UTF-8') : htmlspecialchars(t('model_active'), ENT_QUOTES, 'UTF-8'); ?>
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
// Chat functionality
const chatContainer = document.getElementById('chatContainer');
const chatMessages = document.getElementById('chatMessages');
const chatInput = document.getElementById('chatInput');
const chatSendBtn = document.getElementById('chatSendBtn');
const chatSuggestions = document.getElementById('chatSuggestions');

if (chatContainer) {
    const sessionId = chatContainer.dataset.sessionId;
    
    // Auto-resize textarea
    chatInput.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
    });
    
    // Send on Enter (Shift+Enter for new line)
    chatInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendChatMessage();
        }
    });
    
    function sendSuggestion(text) {
        chatInput.value = text;
        chatInput.style.height = 'auto';
        sendChatMessage();
    }
    
    function sendChatMessage() {
        const message = chatInput.value.trim();
        if (!message || !sessionId) return;
        
        // Add user message to chat
        addChatMessage(message, 'user');
        chatInput.value = '';
        chatInput.style.height = 'auto';
        
        // Hide suggestions after first message
        if (chatSuggestions) {
            chatSuggestions.style.display = 'none';
        }
        
        // Show loading
        const loadingId = addLoadingMessage();
        
        // Disable input
        chatInput.disabled = true;
        chatSendBtn.disabled = true;
        
        // Send to backend
        fetch('/pickelball/main/backend/chat_handler.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                session_id: sessionId,
                message: message
            })
        })
        .then(response => {
            if (!response.ok) {
                // If HTTP error, try to get error message from response
                return response.text().then(text => {
                    try {
                        const errorData = JSON.parse(text);
                        throw new Error(errorData.error || `HTTP ${response.status}`);
                    } catch {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                });
            }
            return response.json();
        })
        .then(data => {
            removeLoadingMessage(loadingId);
            
            if (data && data.success && data.response) {
                addChatMessage(data.response, 'assistant');
            } else if (data && data.error) {
                // Show error but also provide helpful message
                console.error('Chat error:', data.error);
                // Try to provide a helpful response based on the question
                const question = message.toLowerCase();
                let fallbackResponse = '';
                if (question.includes('improve') || question.includes('better')) {
                    fallbackResponse = 'To improve this technique, focus on the key issues identified in your analysis. Practice 15-30 minutes daily with proper form, and you should see improvement within 2-4 weeks.';
                } else if (question.includes('schedule') || question.includes('practice')) {
                    fallbackResponse = 'For best results, practice 15-30 minutes daily, 3-4 times per week. Include shadow practice and regular video analysis to track your progress.';
                } else if (question.includes('routine') || question.includes('daily')) {
                    fallbackResponse = 'A good daily routine includes: 5 min warm-up, 10-15 min technique focus, 5-10 min shadow practice, and 5 min cool-down. Quality over quantity!';
                } else if (question.includes('time') || question.includes('long')) {
                    fallbackResponse = 'Most players see noticeable improvement within 2-4 weeks of consistent practice. Significant improvement typically comes after 2-3 months of regular training.';
                } else {
                    fallbackResponse = 'Thank you for your question! Based on the analysis, I recommend focusing on the key areas mentioned in the feedback. Practice 15-30 minutes daily for best results.';
                }
                addChatMessage(fallbackResponse, 'assistant');
            } else {
                console.error('Unexpected response format:', data);
                // Provide helpful fallback based on question
                const question = message.toLowerCase();
                let fallbackResponse = 'Thank you for your question! Based on the analysis, I recommend focusing on the key areas mentioned in the feedback. Practice 15-30 minutes daily for best results.';
                if (question.includes('improve') || question.includes('better')) {
                    fallbackResponse = 'To improve this technique, focus on the key issues identified in your analysis. Practice 15-30 minutes daily with proper form.';
                }
                addChatMessage(fallbackResponse, 'assistant');
            }
        })
        .catch(error => {
            removeLoadingMessage(loadingId);
            console.error('Chat error:', error);
            // Provide helpful fallback message based on question
            const question = message.toLowerCase();
            let fallbackResponse = 'Thank you for your question! Based on the analysis, I recommend focusing on the key areas mentioned in the feedback. Practice 15-30 minutes daily for best results.';
            if (question.includes('improve') || question.includes('better')) {
                fallbackResponse = 'To improve this technique, focus on the key issues identified in your analysis. Practice 15-30 minutes daily with proper form, and you should see improvement within 2-4 weeks.';
            } else if (question.includes('schedule') || question.includes('practice')) {
                fallbackResponse = 'For best results, practice 15-30 minutes daily, 3-4 times per week. Include shadow practice and regular video analysis to track your progress.';
            } else if (question.includes('routine') || question.includes('daily')) {
                fallbackResponse = 'A good daily routine includes: 5 min warm-up, 10-15 min technique focus, 5-10 min shadow practice, and 5 min cool-down.';
            } else if (question.includes('time') || question.includes('long')) {
                fallbackResponse = 'Most players see noticeable improvement within 2-4 weeks of consistent practice. Significant improvement typically comes after 2-3 months.';
            }
            addChatMessage(fallbackResponse, 'assistant');
        })
        .finally(() => {
            chatInput.disabled = false;
            chatSendBtn.disabled = false;
            chatInput.focus();
        });
    }
    
    function addChatMessage(text, role) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `chat-message chat-message-${role}`;
        
        const time = new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
        const name = role === 'user' ? '<?php echo htmlspecialchars(t('you'), ENT_QUOTES, 'UTF-8'); ?>' : '<?php echo htmlspecialchars(t('ai_coach'), ENT_QUOTES, 'UTF-8'); ?>';
        
        let avatarSvg = '';
        if (role === 'assistant') {
            avatarSvg = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>';
        } else {
            avatarSvg = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>';
        }
        
        messageDiv.innerHTML = `
            <div class="chat-avatar">${avatarSvg}</div>
            <div class="chat-content">
                <div class="chat-header">
                    <span class="chat-name">${name}</span>
                    <span class="chat-time">${time}</span>
                </div>
                <div class="chat-text">${escapeHtml(text).replace(/\n/g, '<br>')}</div>
            </div>
        `;
        
        chatMessages.appendChild(messageDiv);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    
    function addLoadingMessage() {
        const messageDiv = document.createElement('div');
        messageDiv.className = 'chat-message chat-message-assistant';
        messageDiv.id = 'loading-message';
        
        messageDiv.innerHTML = `
            <div class="chat-avatar">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
            </div>
            <div class="chat-content">
                <div class="chat-header">
                    <span class="chat-name"><?php echo htmlspecialchars(t('ai_coach'), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="chat-text">
                    <div class="chat-loading">
                        <span></span><span></span><span></span>
                    </div>
                </div>
            </div>
        `;
        
        chatMessages.appendChild(messageDiv);
        chatMessages.scrollTop = chatMessages.scrollHeight;
        return 'loading-message';
    }
    
    function removeLoadingMessage(id) {
        const loadingMsg = document.getElementById(id);
        if (loadingMsg) {
            loadingMsg.remove();
        }
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Scroll to bottom on load
    setTimeout(() => {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }, 100);
}

// File upload handling
const uploadArea = document.getElementById('uploadArea');
const videoInput = document.getElementById('videoInput');
const selectedFile = document.getElementById('selectedFile');
const fileName = document.getElementById('fileName');
const fileSize = document.getElementById('fileSize');
const submitBtn = document.getElementById('submitBtn');

// Click to select file
uploadArea.addEventListener('click', () => {
    videoInput.click();
});

// Drag and drop handling
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

// File input change
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
    submitBtn.disabled = true;
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

