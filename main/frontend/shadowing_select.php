<?php
// Require authentication - redirect to login if not logged in
require_once __DIR__ . '/../../user/backend/require_auth.php';
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../includes/header.php';

// Load backend logic to get list of available poses
$poses = require __DIR__ . '/../backend/shadowing_select.php';
?>
<style>
    .shadowing-select-section {
        min-height: calc(100vh - 72px);
        padding: 80px 0;
        background: linear-gradient(135deg, #f0fdf4 0%, #ffffff 100%);
    }
    .shadowing-select-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 0 40px;
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
        color: #64748b;
        max-width: 600px;
        margin: 0 auto;
        line-height: 1.6;
    }
    .videos-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 32px;
        margin-top: 60px;
    }
    .video-card {
        background: #ffffff;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        transition: all 0.3s ease;
        cursor: pointer;
        position: relative;
    }
    .video-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 12px 32px rgba(16, 185, 129, 0.2);
    }
    .video-thumbnail {
        position: relative;
        width: 100%;
        aspect-ratio: 16/9;
        background: #1e293b;
        overflow: hidden;
    }
    .video-thumbnail video {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }
    .video-thumbnail::after {
        content: '';
        position: absolute;
        inset: 0;
        background: rgba(0, 0, 0, 0.3);
        opacity: 0;
        transition: opacity 0.3s ease;
        z-index: 1;
    }
    .video-card:hover .video-thumbnail::after {
        opacity: 1;
    }
    .play-icon {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 64px;
        height: 64px;
        background: rgba(16, 185, 129, 0.9);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 2;
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    .video-card:hover .play-icon {
        opacity: 1;
    }
    .play-icon svg {
        width: 28px;
        height: 28px;
        fill: #ffffff;
        margin-left: 4px;
    }
    .video-info {
        padding: 24px;
    }
    .video-name {
        font-size: 20px;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 8px;
    }
    .video-description {
        font-size: 14px;
        color: #64748b;
        line-height: 1.5;
    }
    .empty-state {
        text-align: center;
        padding: 80px 20px;
        color: #64748b;
    }
    .empty-state-icon {
        width: 120px;
        height: 120px;
        margin: 0 auto 24px;
        opacity: 0.5;
    }
    .empty-state-icon svg {
        width: 100%;
        height: 100%;
        fill: #94a3b8;
    }
    .empty-state h3 {
        font-size: 24px;
        color: #475569;
        margin-bottom: 12px;
    }
    .empty-state p {
        font-size: 16px;
        max-width: 500px;
        margin: 0 auto;
    }
    .coming-soon-card {
        opacity: 0.85;
        cursor: default !important;
    }
    .coming-soon-card:hover {
        transform: none;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }
    .coming-soon-card .play-icon {
        display: none;
    }
    @media (max-width: 968px) {
        .shadowing-select-section {
            padding: 60px 0;
        }
        .shadowing-select-container {
            padding: 0 24px;
        }
        .page-title {
            font-size: 42px;
        }
        .page-subtitle {
            font-size: 18px;
        }
        .videos-grid {
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 24px;
        }
    }
    @media (max-width: 640px) {
        .videos-grid {
            grid-template-columns: 1fr;
        }
        .page-title {
            font-size: 36px;
        }
    }
</style>
<section class="shadowing-select-section">
    <div class="shadowing-select-container">
        <div class="page-header animate-on-scroll fade-in-up">
            <h1 class="page-title"><?php echo htmlspecialchars(t('shadowing_mode'), ENT_QUOTES, 'UTF-8'); ?></h1>
            <p class="page-subtitle"><?php echo htmlspecialchars(t('shadowing_subtitle'), ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
        
        <div class="videos-grid">
            <?php foreach ($poses as $index => $pose): ?>
                <div class="video-card animate-on-scroll fade-in-up" style="transition-delay: <?php echo $index * 0.1; ?>s;" 
                     onclick="window.location.href='shadowing_practice.php?pose=<?php echo urlencode($pose['pose']); ?>'">
                    <div class="video-thumbnail">
                        <?php if ($pose['hasAssets']): ?>
                            <div style="width: 100%; height: 100%; background: linear-gradient(135deg, #10b981 0%, #059669 100%); display: flex; align-items: center; justify-content: center; color: white;">
                                <svg width="80" height="80" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                </svg>
                            </div>
                        <?php else: ?>
                            <div style="width: 100%; height: 100%; background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); display: flex; align-items: center; justify-content: center; color: white; opacity: 0.7;">
                                <svg width="80" height="80" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                                </svg>
                            </div>
                        <?php endif; ?>
                        <div class="play-icon">
                            <svg viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                                <path d="M8 5v14l11-7z"/>
                            </svg>
                        </div>
                        <?php if (!$pose['hasAssets']): ?>
                            <div style="position: absolute; top: 10px; right: 10px; background: rgba(255, 255, 255, 0.9); padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; color: #f59e0b;">
                                Setup Required
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="video-info">
                        <h3 class="video-name"><?php echo htmlspecialchars($pose['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                        <p class="video-description"><?php echo htmlspecialchars($pose['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <!-- Coming Soon Card -->
            <div class="video-card coming-soon-card animate-on-scroll fade-in-up" style="transition-delay: <?php echo count($poses) * 0.1; ?>s; cursor: default;">
                <div class="video-thumbnail">
                    <div style="width: 100%; height: 100%; background: linear-gradient(135deg, #64748b 0%, #475569 100%); display: flex; align-items: center; justify-content: center; color: white; opacity: 0.8;">
                        <svg width="80" height="80" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/>
                        </svg>
                    </div>
                </div>
                <div class="video-info">
                    <h3 class="video-name">More Techniques</h3>
                    <p class="video-description">Additional training techniques will be available soon. Stay tuned for updates!</p>
                    <div style="margin-top: 12px; padding: 8px 12px; background: rgba(100, 116, 139, 0.1); border-radius: 6px; font-size: 12px; color: #64748b;">
                        Coming Soon
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script src="/pickelball/main/frontend/js/scroll-animation.js"></script>
<script>
// Initialize video hover preview functionality
document.addEventListener('DOMContentLoaded', () => {
    // Play video on hover, pause and reset on mouse leave
    document.querySelectorAll('.video-thumbnail video').forEach(video => {
        const card = video.closest('.video-card');
        card.addEventListener('mouseenter', () => {
            video.play().catch(() => {
                // Ignore autoplay errors (browser restrictions)
            });
        });
        card.addEventListener('mouseleave', () => {
            video.pause();
            video.currentTime = 0; // Reset to beginning
        });
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

