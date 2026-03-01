<?php
declare(strict_types=1);



require_once __DIR__ . '/../../user/backend/require_auth.php';
require_once __DIR__ . '/../../user/backend/bootstrap.php';
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../includes/header.php';

$userId = (int)$authUser['id'];
$history = [];
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS action_predictions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        video_name VARCHAR(255) NOT NULL,
        video_path VARCHAR(500) NOT NULL,
        predicted_class VARCHAR(50) NOT NULL,
        confidence DECIMAL(5,2) NOT NULL,
        probabilities TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $columns = $pdo->query("SHOW COLUMNS FROM action_predictions")->fetchAll(PDO::FETCH_COLUMN);

    $selectFields = ['id', 'video_name', 'video_path', 'predicted_class', 'confidence', 'probabilities', 'created_at'];
    if (in_array('analysis_session_id', $columns)) {
        $selectFields[] = 'analysis_session_id';
    }
    if (in_array('analysis_success', $columns)) {
        $selectFields[] = 'analysis_success';
    }
    if (in_array('analysis_coaching_feedback', $columns)) {
        $selectFields[] = 'analysis_coaching_feedback';
    }
    if (in_array('analysis_raw_feedback', $columns)) {
        $selectFields[] = 'analysis_raw_feedback';
    }

    $query = 'SELECT ' . implode(', ', $selectFields) . ' FROM action_predictions WHERE user_id = ? ORDER BY created_at DESC LIMIT 50';
    $stmt = $pdo->prepare($query);
    $stmt->execute([$userId]);
    $history = $stmt->fetchAll();

    foreach ($history as &$item) {
        $item['probabilities'] = json_decode($item['probabilities'] ?? '{}', true) ?: [];
        $item['has_analysis'] = false;
        if (isset($item['analysis_session_id']) && isset($item['analysis_success'])) {
            $item['has_analysis'] = !empty($item['analysis_session_id']) && !empty($item['analysis_success']);
        }
        if (isset($item['analysis_raw_feedback']) && !empty($item['analysis_raw_feedback'])) {
            $item['analysis_feedback'] = json_decode($item['analysis_raw_feedback'], true) ?: [];
        } else {
            $item['analysis_feedback'] = [];
        }
    }
    unset($item); // Break reference
} catch (PDOException $e) {
    error_log("Error loading prediction history: " . $e->getMessage());
    $history = [];
}
?>
<style>
    .action-prediction-section {
        min-height: calc(100vh - 72px);
        padding: 80px 24px;
        background: linear-gradient(135deg, #f0fdf4 0%, #ffffff 100%) !important;
        background-color: #ffffff !important;
        position: relative;
        z-index: 1;
        width: 100%;
        display: block !important;
        visibility: visible !important;
        opacity: 1 !important;
    }

    .action-prediction-section * {
        visibility: visible !important;
        opacity: 1 !important;
    }


    html[data-theme="dark"] .action-prediction-section,
    html.theme-dark .action-prediction-section,
    body.theme-dark .action-prediction-section,
    body[data-theme="dark"] .action-prediction-section {
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%) !important;
        background-color: #0f172a !important;
        display: block !important;
        visibility: visible !important;
        opacity: 1 !important;
    }

    html[data-theme="dark"] .action-prediction-section *,
    html.theme-dark .action-prediction-section *,
    body.theme-dark .action-prediction-section *,
    body[data-theme="dark"] .action-prediction-section * {
        visibility: visible !important;
        opacity: 1 !important;
    }

    html[data-theme="dark"] .action-prediction-section .page-title,
    html.theme-dark .action-prediction-section .page-title,
    body.theme-dark .action-prediction-section .page-title,
    body[data-theme="dark"] .action-prediction-section .page-title {
        color: #ffffff !important;
    }

    html[data-theme="dark"] .action-prediction-section .page-subtitle,
    html.theme-dark .action-prediction-section .page-subtitle,
    body.theme-dark .action-prediction-section .page-subtitle,
    body[data-theme="dark"] .action-prediction-section .page-subtitle {
        color: #e2e8f0 !important;
    }

    html[data-theme="dark"] .action-prediction-section .upload-section,
    html.theme-dark .action-prediction-section .upload-section,
    body.theme-dark .action-prediction-section .upload-section,
    body[data-theme="dark"] .action-prediction-section .upload-section {
        background: #1e293b !important;
        background-color: #1e293b !important;
        border-color: #334155 !important;
    }

    html[data-theme="dark"] .action-prediction-section .history-section,
    html.theme-dark .action-prediction-section .history-section,
    body.theme-dark .action-prediction-section .history-section,
    body[data-theme="dark"] .action-prediction-section .history-section {
        background: #1e293b !important;
        background-color: #1e293b !important;
    }

    body {
        background: #ffffff;
        overflow-x: hidden;
    }

    html[data-theme="dark"] body,
    html.theme-dark body,
    body.theme-dark {
        background: #0f172a !important;
        background-color: #0f172a !important;
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
        padding: 48px 32px;
        text-align: center;
    }
    .predicted-class {
        font-size: 42px;
        font-weight: 800;
        color: #059669;
        margin-bottom: 0;
        letter-spacing: -0.5px;
    }
    .prediction-confidence {
        margin-top: 14px;
        font-size: 18px;
        font-weight: 600;
        color: #0f172a;
    }
    .probabilities {
        margin-top: 18px;
        display: flex;
        gap: 12px;
        justify-content: center;
        flex-wrap: wrap;
    }
    .probability-chip {
        padding: 10px 14px;
        border-radius: 10px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        font-size: 14px;
        color: #334155;
        min-width: 200px;
        text-align: left;
    }
    .probability-label {
        font-weight: 600;
        color: #0f172a;
        margin-bottom: 6px;
    }
    .probability-value {
        font-weight: 700;
        color: #059669;
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
        cursor: pointer;
    }
    .history-item:hover {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        transform: translateY(-2px);
        border-color: #6366f1;
    }
    .history-item.expanded {
        border-color: #6366f1;
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.15);
    }
    .history-details {
        display: none;
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid #e2e8f0;
    }
    .history-item.expanded .history-details {
        display: block;
    }
    .detail-section {
        margin-bottom: 20px;
    }
    .detail-section-title {
        font-size: 16px;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .detail-section-title svg {
        width: 20px;
        height: 20px;
        color: #6366f1;
    }
    .detail-content {
        background: #ffffff;
        border-radius: 8px;
        padding: 16px;
        border: 1px solid #e2e8f0;
    }
    .issue-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .issue-item {
        padding: 12px;
        margin-bottom: 8px;
        background: #f8fafc;
        border-radius: 6px;
        border-left: 3px solid #3b82f6;
    }
    .issue-item.severity-high {
        border-left-color: #ef4444;
        background: #fef2f2;
    }
    .issue-item.severity-medium {
        border-left-color: #f59e0b;
        background: #fffbeb;
    }
    .issue-item.severity-low {
        border-left-color: #10b981;
        background: #f0fdf4;
    }
    .issue-title {
        font-weight: 600;
        color: #0f172a;
        margin-bottom: 4px;
    }
    .issue-tip {
        font-size: 14px;
        color: #475569;
        margin-top: 6px;
    }
    .coaching-feedback {
        color: #1e293b;
        line-height: 1.6;
        white-space: pre-wrap;
    }
    .expand-icon {
        transition: transform 0.3s ease;
        display: inline-block;
        margin-left: 8px;
    }
    .history-item.expanded .expand-icon {
        transform: rotate(180deg);
    }
    .history-item.has-analysis {
        border-left: 4px solid #10b981;
    }
    .history-item.has-analysis:hover {
        border-left-color: #6366f1;
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
                <div class="prediction-confidence" id="predictionConfidence"></div>
                <div class="probabilities" id="predictionProbabilities"></div>
            </div>

            <!-- Chat Interface -->
            <div id="chatContainer" style="display: none; margin-top: 32px;" data-session-id="">
                <!-- Chat Header -->
                <div style="padding: 18px 24px; background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: #ffffff; display: flex; align-items: center; gap: 14px; border-bottom: 1px solid rgba(255, 255, 255, 0.15); box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1); border-radius: 20px 20px 0 0;">
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

                <div class="chat-messages" id="chatMessages" style="flex: 1; overflow-y: auto; padding: 20px; display: flex; flex-direction: column; gap: 16px; background: linear-gradient(to bottom, #f8fafc 0%, #ffffff 100%); scroll-behavior: smooth; max-height: 500px;">
                    <!-- Initial message will be added here -->
                </div>

                <!-- Chat Suggestions -->
                <div class="chat-suggestions" id="chatSuggestions" style="padding: 12px 20px; background: #ffffff; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0;">
                    <div class="suggestion-title" style="font-size: 11px; font-weight: 600; color: #64748b; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.8px;"><?php echo htmlspecialchars(t('suggested_questions'), ENT_QUOTES, 'UTF-8'); ?>:</div>
                    <div class="suggestion-buttons" style="display: flex; flex-wrap: wrap; gap: 6px;">
                        <button class="suggestion-btn" onclick="sendSuggestion('<?php echo htmlspecialchars(t('how_to_improve'), ENT_QUOTES, 'UTF-8'); ?>')" style="padding: 6px 14px; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 16px; font-size: 12.5px; color: #475569; cursor: pointer; transition: all 0.2s ease; font-weight: 500; white-space: nowrap;">
                            <?php echo htmlspecialchars(t('how_to_improve'), ENT_QUOTES, 'UTF-8'); ?>
                        </button>
                        <button class="suggestion-btn" onclick="sendSuggestion('<?php echo htmlspecialchars(t('practice_schedule'), ENT_QUOTES, 'UTF-8'); ?>')" style="padding: 6px 14px; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 16px; font-size: 12.5px; color: #475569; cursor: pointer; transition: all 0.2s ease; font-weight: 500; white-space: nowrap;">
                            <?php echo htmlspecialchars(t('practice_schedule'), ENT_QUOTES, 'UTF-8'); ?>
                        </button>
                        <button class="suggestion-btn" onclick="sendSuggestion('<?php echo htmlspecialchars(t('daily_routine'), ENT_QUOTES, 'UTF-8'); ?>')" style="padding: 6px 14px; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 16px; font-size: 12.5px; color: #475569; cursor: pointer; transition: all 0.2s ease; font-weight: 500; white-space: nowrap;">
                            <?php echo htmlspecialchars(t('daily_routine'), ENT_QUOTES, 'UTF-8'); ?>
                        </button>
                        <button class="suggestion-btn" onclick="sendSuggestion('<?php echo htmlspecialchars(t('timeline_improvement'), ENT_QUOTES, 'UTF-8'); ?>')" style="padding: 6px 14px; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 16px; font-size: 12.5px; color: #475569; cursor: pointer; transition: all 0.2s ease; font-weight: 500; white-space: nowrap;">
                            <?php echo htmlspecialchars(t('timeline_improvement'), ENT_QUOTES, 'UTF-8'); ?>
                        </button>
                        <button class="suggestion-btn" onclick="sendSuggestion('<?php echo htmlspecialchars(t('common_mistakes'), ENT_QUOTES, 'UTF-8'); ?>')" style="padding: 6px 14px; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 16px; font-size: 12.5px; color: #475569; cursor: pointer; transition: all 0.2s ease; font-weight: 500; white-space: nowrap;">
                            <?php echo htmlspecialchars(t('common_mistakes'), ENT_QUOTES, 'UTF-8'); ?>
                        </button>
                        <button class="suggestion-btn" onclick="sendSuggestion('<?php echo htmlspecialchars(t('drill_exercises'), ENT_QUOTES, 'UTF-8'); ?>')" style="padding: 6px 14px; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 16px; font-size: 12.5px; color: #475569; cursor: pointer; transition: all 0.2s ease; font-weight: 500; white-space: nowrap;">
                            <?php echo htmlspecialchars(t('drill_exercises'), ENT_QUOTES, 'UTF-8'); ?>
                        </button>
                        <button class="suggestion-btn" onclick="sendSuggestion('<?php echo htmlspecialchars(t('warmup_routine'), ENT_QUOTES, 'UTF-8'); ?>')" style="padding: 6px 14px; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 16px; font-size: 12.5px; color: #475569; cursor: pointer; transition: all 0.2s ease; font-weight: 500; white-space: nowrap;">
                            <?php echo htmlspecialchars(t('warmup_routine'), ENT_QUOTES, 'UTF-8'); ?>
                        </button>
                        <button class="suggestion-btn" onclick="sendSuggestion('<?php echo htmlspecialchars(t('strength_training'), ENT_QUOTES, 'UTF-8'); ?>')" style="padding: 6px 14px; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 16px; font-size: 12.5px; color: #475569; cursor: pointer; transition: all 0.2s ease; font-weight: 500; white-space: nowrap;">
                            <?php echo htmlspecialchars(t('strength_training'), ENT_QUOTES, 'UTF-8'); ?>
                        </button>
                    </div>
                </div>

                <!-- Chat Input -->
                <div class="chat-input-container" style="padding: 12px 20px; background: #ffffff; border-radius: 0 0 20px 20px;">
                    <div class="chat-input-wrapper" style="display: flex; align-items: flex-end; gap: 10px; background: #f8fafc; border: 2px solid #e2e8f0; border-radius: 24px; padding: 10px 16px; transition: all 0.2s ease;">
                        <textarea
                            id="chatInput"
                            class="chat-input"
                            placeholder="<?php echo htmlspecialchars(t('type_your_question'), ENT_QUOTES, 'UTF-8'); ?>"
                            rows="1"
                            style="flex: 1; border: none; background: transparent; resize: none; font-size: 14.5px; color: #1e293b; font-family: inherit; outline: none; max-height: 120px; overflow-y: auto; line-height: 1.5; padding: 0;"
                        ></textarea>
                        <button id="chatSendBtn" class="chat-send-btn" onclick="sendChatMessage()" style="width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); border: none; color: #ffffff; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease; flex-shrink: 0; box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);">
                            <svg viewBox="0 0 24 24" fill="currentColor" style="width: 18px; height: 18px;">
                                <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <style>
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
            .suggestion-btn:hover {
                background: #e2e8f0;
                color: #1e293b;
                transform: translateY(-1px);
                box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
            }
            .suggestion-btn:active {
                transform: translateY(0);
            }
            .chat-input-wrapper:focus-within {
                border-color: #3b82f6;
                background: #ffffff;
                box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
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
        </style>

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
                        <div class="history-item <?php echo $item['has_analysis'] ? 'has-analysis' : ''; ?>" onclick="toggleHistoryDetails(this)">
                            <div class="history-item-header">
                                <div class="history-video-name">
                                    <svg viewBox="0 0 24 24" fill="currentColor" style="width: 20px; height: 20px; display: inline-block; vertical-align: middle; margin-right: 8px; color: #10b981;">
                                        <path d="M17 10.5V7c0-.55-.45-1-1-1H4c-.55 0-1 .45-1 1v10c0 .55.45 1 1 1h12c.55 0 1-.45 1-1v-3.5l4 4v-11l-4 4z"/>
                                    </svg>
                                    <?php echo htmlspecialchars($item['video_name'], ENT_QUOTES, 'UTF-8'); ?>
                                    <?php if ($item['has_analysis']): ?>
                                        <span style="margin-left: 8px; padding: 2px 8px; background: #10b981; color: white; border-radius: 12px; font-size: 11px; font-weight: 600;">
                                            <?php echo htmlspecialchars(t('analyzed'), ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    <?php endif; ?>
                                    <svg class="expand-icon" viewBox="0 0 24 24" fill="currentColor" style="width: 16px; height: 16px; color: #64748b; vertical-align: middle;">
                                        <path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z"/>
                                    </svg>
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

                            <!-- Details Section -->
                            <div class="history-details">
                                <?php if ($item['has_analysis'] && !empty($item['analysis_feedback'])): ?>
                                    <?php
                                    $issues = array_filter($item['analysis_feedback'], function($issue) {
                                        return isset($issue['severity']) && $issue['severity'] !== 'none';
                                    });
                                    if (!empty($issues)):
                                    ?>
                                        <div class="detail-section">
                                            <div class="detail-section-title">
                                                <svg viewBox="0 0 24 24" fill="currentColor">
                                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                                                </svg>
                                                <?php echo htmlspecialchars(t('key_issues'), ENT_QUOTES, 'UTF-8'); ?>
                                            </div>
                                            <div class="detail-content">
                                                <ul class="issue-list">
                                                    <?php foreach ($issues as $issue): ?>
                                                        <li class="issue-item severity-<?php echo htmlspecialchars($issue['severity'] ?? 'low', ENT_QUOTES, 'UTF-8'); ?>">
                                                            <div class="issue-title">
                                                                <?php echo htmlspecialchars($issue['issue'] ?? 'Issue detected', ENT_QUOTES, 'UTF-8'); ?>
                                                            </div>
                                                            <?php if (!empty($issue['tip'])): ?>
                                                                <div class="issue-tip">
                                                                    <?php echo htmlspecialchars($issue['tip'], ENT_QUOTES, 'UTF-8'); ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php if ($item['has_analysis'] && !empty($item['analysis_coaching_feedback'])): ?>
                                    <div class="detail-section">
                                        <div class="detail-section-title">
                                            <svg viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                            </svg>
                                            <?php echo htmlspecialchars(t('coaching_feedback'), ENT_QUOTES, 'UTF-8'); ?>
                                        </div>
                                        <div class="detail-content">
                                            <div class="coaching-feedback">
                                                <?php echo nl2br(htmlspecialchars($item['analysis_coaching_feedback'], ENT_QUOTES, 'UTF-8')); ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if (!$item['has_analysis']): ?>
                                    <div class="detail-section">
                                        <div class="detail-content" style="padding: 16px; background: #fef3c7; border-left: 3px solid #f59e0b; color: #92400e;">
                                            <p style="margin: 0; font-size: 14px;">
                                                <?php echo htmlspecialchars(t('no_analysis_available'), ENT_QUOTES, 'UTF-8'); ?>
                                            </p>
                                        </div>
                                    </div>
                                <?php endif; ?>
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
document.addEventListener('DOMContentLoaded', function() {
    const uploadArea = document.getElementById('uploadArea');
    videoInput = document.getElementById('videoInput');
    selectedFile = document.getElementById('selectedFile');
    const fileName = document.getElementById('fileName');
    const fileSize = document.getElementById('fileSize');
    submitBtn = document.getElementById('submitBtn');
    uploadForm = document.getElementById('uploadForm');
    resultSection = document.getElementById('resultSection');
    alertContainer = document.getElementById('alertContainer');

    const modal = document.getElementById('predictionModal');
    if (modal) {
        modal.style.display = 'none';
    }

    if (!uploadArea || !videoInput || !selectedFile || !fileName || !fileSize || !submitBtn || !uploadForm) {
        console.error('Required elements not found');
        return;
    }

    uploadArea.addEventListener('click', () => {
        videoInput.click();
    });

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

            const responseText = await response.text();
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (e) {
                console.error('Invalid JSON response:', responseText.substring(0, 500));
                throw new Error('Invalid response from server. Please check server logs.');
            }

            if (data.success && data.prediction) {
                resultSection.style.display = 'block';

                const predictedClassEl = document.getElementById('predictedClass');
                if (predictedClassEl) {
                    predictedClassEl.textContent = data.prediction.class;
                    predictedClassEl.style.fontSize = '36px';
                    predictedClassEl.style.fontWeight = '700';
                    predictedClassEl.style.color = '#059669';
                    predictedClassEl.style.marginBottom = '0';
                }

                const confidenceEl = document.getElementById('predictionConfidence');
                const probsEl = document.getElementById('predictionProbabilities');
                const formatPercent = (value) => {
                    const num = Number(value);
                    if (Number.isNaN(num)) return null;
                    const percent = num <= 1 ? num * 100 : num;
                    return `${percent.toFixed(2)}%`;
                };

                const confidenceText = formatPercent(data.prediction.confidence);
                if (confidenceEl) {
                    confidenceEl.textContent = confidenceText
                        ? `Confidence: ${confidenceText}`
                        : 'Confidence: N/A';
                }

                if (probsEl) {
                    probsEl.innerHTML = '';
                    const probs = data.prediction.probabilities;
                    if (probs && typeof probs === 'object') {
                        Object.entries(probs).forEach(([label, value]) => {
                            const percent = formatPercent(value);
                            const chip = document.createElement('div');
                            chip.className = 'probability-chip';
                            chip.innerHTML = `
                                <div class="probability-label">${escapeHtml(label)}</div>
                                <div class="probability-value">${percent ?? 'N/A'}</div>
                            `;
                            probsEl.appendChild(chip);
                        });
                    }
                }

                const chatContainer = document.getElementById('chatContainer');
                const chatMessages = document.getElementById('chatMessages');

                if (data.analysis && data.analysis.success && data.analysis.session_id) {
                    const sessionId = data.analysis.session_id;
                    console.log('Setting session_id:', sessionId);

                    if (!chatContainer) {
                        console.error('Chat container not found');
                    } else {
                        chatContainer.dataset.sessionId = sessionId;
                        currentSessionId = sessionId;
                        chatContainer.style.display = 'block';

                        console.log('Session ID set. chatContainer.dataset.sessionId:', chatContainer.dataset.sessionId);

                        if (chatMessages) {
                            chatMessages.innerHTML = '';

                            let initialMessage = '';
                            if (data.analysis.coaching_feedback) {
                                initialMessage = data.analysis.coaching_feedback;
                            } else {
                                initialMessage = 'Great effort on your shadow swing! Keep practicing and you\'ll see improvement!';
                            }

                            let keyIssuesHtml = '';
                            if (data.analysis.feedback && Array.isArray(data.analysis.feedback) && data.analysis.feedback.length > 0) {
                                const issues = data.analysis.feedback.filter(item => item.severity !== 'none');
                                if (issues.length > 0) {
                                    keyIssuesHtml = '<div style="margin-top: 12px; padding: 12px 14px; background: rgba(59, 130, 246, 0.06); border-radius: 12px; border-left: 3px solid #3b82f6;"><div style="font-weight: 600; color: #1e293b; margin-bottom: 10px; font-size: 13px; display: flex; align-items: center; gap: 6px;"><svg viewBox="0 0 24 24" fill="currentColor" style="width: 16px; height: 16px; color: #3b82f6;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg><?php echo htmlspecialchars(t('key_issues'), ENT_QUOTES, 'UTF-8'); ?></div><ul style="margin: 0; padding-left: 20px; color: #334155; font-size: 13px; line-height: 1.7;">';
                                    issues.forEach((item, index) => {
                                        keyIssuesHtml += `<li style="margin-bottom: 8px;"><strong style="color: #1e293b; font-weight: 600;">${item.issue || 'Issue detected'}</strong>${item.tip ? `<div style="color: #475569; margin-top: 4px; font-size: 12.5px; line-height: 1.6;">${item.tip}</div>` : ''}</li>`;
                                    });
                                    keyIssuesHtml += '</ul></div>';
                                }
                            }

                            const time = new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
                            chatMessages.innerHTML = `
                                <div class="chat-message chat-message-assistant">
                                    <div class="chat-avatar">
                                        <svg viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                        </svg>
                                    </div>
                                    <div class="chat-content">
                                        <div class="chat-header">
                                            <span class="chat-name"><?php echo htmlspecialchars(t('ai_coach'), ENT_QUOTES, 'UTF-8'); ?></span>
                                            <span class="chat-time">${time}</span>
                                        </div>
                                        <div class="chat-text">${escapeHtml(initialMessage).replace(/\n/g, '<br>')}${keyIssuesHtml}</div>
                                    </div>
                                </div>
                            `;
                        }

                        initChat();
                    }
                } else {
                    console.log('Analysis not successful or no session_id. Analysis:', data.analysis);
                    if (chatContainer) {
                        chatContainer.style.display = 'none';
                    }
                }
            } else {
                showAlert(data.error || '<?php echo htmlspecialchars(t('prediction_failed'), ENT_QUOTES, 'UTF-8'); ?>', 'error');
            }

            submitBtn.disabled = false;
            submitBtn.textContent = '<?php echo htmlspecialchars(t('predict_action'), ENT_QUOTES, 'UTF-8'); ?>';
            videoInput.value = '';
            selectedFile.style.display = 'none';

            resultSection.scrollIntoView({ behavior: 'smooth' });

        } catch (error) {
            console.error('Prediction error:', error);
            const errorMessage = (error && error.message)
                ? error.message
                : '<?php echo htmlspecialchars(t('upload_error'), ENT_QUOTES, 'UTF-8'); ?>';
            showAlert(errorMessage, 'error');
            submitBtn.disabled = false;
            submitBtn.textContent = '<?php echo htmlspecialchars(t('predict_action'), ENT_QUOTES, 'UTF-8'); ?>';
        }
    });
});

let uploadForm, videoInput, submitBtn, selectedFile, resultSection, alertContainer;

function handleFileSelect() {
    const videoInput = document.getElementById('videoInput');
    const fileName = document.getElementById('fileName');
    const fileSize = document.getElementById('fileSize');
    const selectedFile = document.getElementById('selectedFile');
    const submitBtn = document.getElementById('submitBtn');

    if (!videoInput || !fileName || !fileSize || !selectedFile || !submitBtn) return;

    const file = videoInput.files[0];
    if (file) {
        fileName.textContent = file.name;
        fileSize.textContent = formatFileSize(file.size);
        selectedFile.style.display = 'flex';
        submitBtn.disabled = false;
    }
}

function removeFile() {
    const videoInput = document.getElementById('videoInput');
    const selectedFile = document.getElementById('selectedFile');
    const submitBtn = document.getElementById('submitBtn');
    const resultSection = document.getElementById('resultSection');

    if (videoInput) videoInput.value = '';
    if (selectedFile) selectedFile.style.display = 'none';
    if (submitBtn) submitBtn.disabled = false;
    if (resultSection) resultSection.style.display = 'none';
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


let chatInitialized = false;
let currentSessionId = null;

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function sendSuggestion(text) {
    const chatInput = document.getElementById('chatInput');
    if (!chatInput) return;

    chatInput.value = text;
    chatInput.style.height = 'auto';
    sendChatMessage();
}

function sendChatMessage() {
    const chatContainer = document.getElementById('chatContainer');
    const chatInput = document.getElementById('chatInput');
    const chatSendBtn = document.getElementById('chatSendBtn');
    const chatSuggestions = document.getElementById('chatSuggestions');

    if (!chatContainer || !chatInput) {
        console.error('Chat container or input not found');
        return;
    }

    const sessionId = chatContainer.dataset.sessionId || currentSessionId;
    if (!sessionId) {
        console.error('No session ID available. chatContainer.dataset.sessionId:', chatContainer.dataset.sessionId, 'currentSessionId:', currentSessionId);
        alert('Session ID not found. Please refresh the page and try again.');
        return;
    }

    const message = chatInput.value.trim();
    if (!message) return;

    addChatMessage(message, 'user');
    chatInput.value = '';
    chatInput.style.height = 'auto';

    if (chatSuggestions) {
        chatSuggestions.style.display = 'none';
    }

    const loadingId = addLoadingMessage();

    chatInput.disabled = true;
    if (chatSendBtn) chatSendBtn.disabled = true;

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
            return response.text().then(text => {
                try {
                    const errorData = JSON.parse(text);
                    console.error('Chat handler error response:', errorData);
                    if (errorData.debug) {
                        console.error('Debug info:', errorData.debug);
                    }
                    throw new Error(errorData.error || `HTTP ${response.status}`);
                } catch (e) {
                    console.error('Failed to parse error response:', text);
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
            console.error('Chat error:', data.error);
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
            addChatMessage('Thank you for your question! Based on the analysis, I recommend focusing on the key areas mentioned in the feedback. Practice 15-30 minutes daily for best results.', 'assistant');
        }
    })
    .catch(error => {
        removeLoadingMessage(loadingId);
        console.error('Chat error:', error);
        addChatMessage('Thank you for your question! Based on the analysis, I recommend focusing on the key areas mentioned in the feedback. Practice 15-30 minutes daily for best results.', 'assistant');
    })
    .finally(() => {
        if (chatInput) chatInput.disabled = false;
        if (chatSendBtn) chatSendBtn.disabled = false;
        if (chatInput) chatInput.focus();
    });
}

function addChatMessage(text, role) {
    const chatMessages = document.getElementById('chatMessages');
    if (!chatMessages) return;

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
    const chatMessages = document.getElementById('chatMessages');
    if (!chatMessages) return null;

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

function initChat() {
    if (chatInitialized) return;
    chatInitialized = true;

    const chatContainer = document.getElementById('chatContainer');
    const chatInput = document.getElementById('chatInput');
    const chatSendBtn = document.getElementById('chatSendBtn');

    if (!chatContainer || !chatInput) return;

    const sessionId = chatContainer.dataset.sessionId;
    if (!sessionId) return;

    currentSessionId = sessionId;

    chatInput.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
    });

    chatInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendChatMessage();
        }
    });
}

function toggleHistoryDetails(element) {
    element.classList.toggle('expanded');
}

</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
