<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = 'Pickleball Training - AI-Powered Training Platform';
require_once __DIR__ . '/../../includes/i18n.php';
require __DIR__ . '/../../includes/header.php';
?>
<style>
    .hero {
        background: linear-gradient(135deg, rgba(15, 23, 42, 0.85) 0%, rgba(30, 41, 59, 0.85) 100%), url('/pickelball/images/banner1.jpg');
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        color: #ffffff;
        padding: 180px 24px;
        text-align: center;
        position: relative;
    }
    .hero-content {
        max-width: 1100px;
        margin: 0 auto;
    }
    .hero h1 {
        font-size: 80px;
        font-weight: 800;
        margin-bottom: 32px;
        letter-spacing: -2px;
        line-height: 1.1;
    }
    .hero-subtitle {
        font-size: 32px;
        font-weight: 400;
        margin-bottom: 24px;
        opacity: 0.9;
    }
    .hero-description {
        font-size: 22px;
        margin-bottom: 50px;
        opacity: 0.8;
        line-height: 1.6;
    }
    .hero-cta {
        display: flex;
        gap: 16px;
        justify-content: center;
        flex-wrap: wrap;
    }
    .btn-primary {
        padding: 18px 36px;
        background: #10b981;
        color: #ffffff;
        text-decoration: none;
        border-radius: 8px;
        font-size: 18px;
        font-weight: 600;
        transition: background 0.2s;
        display: inline-block;
    }
    .btn-primary:hover {
        background: #059669;
    }
    .btn-secondary {
        padding: 18px 36px;
        background: transparent;
        color: #ffffff;
        text-decoration: none;
        border: 2px solid #ffffff;
        border-radius: 8px;
        font-size: 18px;
        font-weight: 600;
        transition: all 0.2s;
        display: inline-block;
    }
    .btn-secondary:hover {
        background: #ffffff;
        color: #0f172a;
    }
    .features {
        padding: 160px 0;
        background: #ffffff;
        width: 100%;
    }
    .features-container {
        max-width: 100%;
        margin: 0 auto;
        padding: 0 80px;
    }
    .section-header {
        text-align: center;
        margin-bottom: 120px;
    }
    .section-title {
        font-size: 64px;
        font-weight: 700;
        margin-bottom: 24px;
        color: #0f172a;
        letter-spacing: -2px;
    }
    .section-subtitle {
        font-size: 24px;
        color: #64748b;
        max-width: 800px;
        margin: 0 auto;
        line-height: 1.6;
    }
    .feature-group {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 120px;
        align-items: center;
        margin-bottom: 160px;
    }
    .feature-group:last-child {
        margin-bottom: 0;
    }
    .feature-group.reverse {
        direction: rtl;
    }
    .feature-group.reverse > * {
        direction: ltr;
    }
    .feature-content {
        display: flex;
        flex-direction: column;
        gap: 56px;
    }
    .feature-item {
        display: flex;
        gap: 32px;
        align-items: flex-start;
    }
    .feature-icon {
        width: 96px;
        height: 96px;
        min-width: 96px;
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        box-shadow: 0 8px 24px rgba(16, 185, 129, 0.2);
    }
    .feature-icon svg {
        width: 48px;
        height: 48px;
        fill: #ffffff;
    }
    .feature-text {
        flex: 1;
    }
    .feature-text h3 {
        font-size: 32px;
        font-weight: 600;
        margin-bottom: 16px;
        color: #0f172a;
        line-height: 1.2;
    }
    .feature-text p {
        font-size: 20px;
        color: #64748b;
        line-height: 1.8;
        margin: 0 0 16px 0;
    }
    .feature-list {
        list-style: none;
        padding: 0;
        margin: 0;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    .feature-list li {
        font-size: 18px;
        color: #475569;
        line-height: 1.6;
        padding-left: 28px;
        position: relative;
    }
    .feature-list li::before {
        content: '✓';
        position: absolute;
        left: 0;
        color: #10b981;
        font-weight: 700;
        font-size: 20px;
    }
    .feature-image {
        border-radius: 24px;
        overflow: hidden;
        box-shadow: 0 30px 60px rgba(15, 23, 42, 0.15);
        background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
        aspect-ratio: 3/4;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        min-height: 500px;
    }
    .feature-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }
    .feature-image::before {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.05) 0%, rgba(5, 150, 105, 0.05) 100%);
        z-index: 1;
        pointer-events: none;
    }
    .feature-image-placeholder {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #64748b;
        font-size: 24px;
        font-weight: 600;
        text-align: center;
        padding: 40px;
        z-index: 1;
        position: relative;
    }
    @media (max-width: 1200px) {
        .features-container {
            padding: 0 60px;
        }
        .section-title {
            font-size: 56px;
        }
        .section-subtitle {
            font-size: 22px;
        }
        .feature-group {
            gap: 80px;
            margin-bottom: 120px;
        }
    }
    @media (max-width: 968px) {
        .features {
            padding: 120px 0;
        }
        .features-container {
            padding: 0 40px;
        }
        .section-header {
            margin-bottom: 80px;
        }
        .section-title {
            font-size: 48px;
        }
        .section-subtitle {
            font-size: 20px;
        }
        .feature-group {
            grid-template-columns: 1fr;
            gap: 60px;
            margin-bottom: 100px;
        }
        .feature-group.reverse {
            direction: ltr;
        }
        .feature-image {
            order: -1;
            min-height: 400px;
        }
        .feature-content {
            gap: 40px;
        }
        .feature-item {
            gap: 24px;
        }
        .feature-icon {
            width: 80px;
            height: 80px;
            min-width: 80px;
        }
        .feature-icon svg {
            width: 40px;
            height: 40px;
        }
        .feature-text h3 {
            font-size: 28px;
        }
        .feature-text p {
            font-size: 18px;
        }
        .feature-list li {
            font-size: 16px;
        }
    }
    @media (max-width: 640px) {
        .features {
            padding: 80px 0;
        }
        .features-container {
            padding: 0 24px;
        }
        .section-header {
            margin-bottom: 60px;
        }
        .section-title {
            font-size: 36px;
        }
        .section-subtitle {
            font-size: 18px;
        }
        .feature-group {
            gap: 40px;
            margin-bottom: 80px;
        }
        .feature-image {
            min-height: 300px;
        }
        .feature-content {
            gap: 32px;
        }
        .feature-item {
            flex-direction: column;
            text-align: center;
            gap: 20px;
        }
        .feature-icon {
            width: 72px;
            height: 72px;
            min-width: 72px;
            margin: 0 auto;
        }
        .feature-icon svg {
            width: 36px;
            height: 36px;
        }
        .feature-text h3 {
            font-size: 24px;
        }
        .feature-text p {
            font-size: 16px;
        }
        .feature-list li {
            font-size: 15px;
        }
    }
    .techniques {
        padding: 100px 24px;
        background: #f8fafc;
    }
    .techniques-container {
        max-width: 1280px;
        margin: 0 auto;
    }
    .techniques-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 32px;
        margin-top: 48px;
    }
    .technique-card {
        background: #ffffff;
        border-radius: 12px;
        padding: 32px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .technique-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
    }
    .technique-card h3 {
        font-size: 20px;
        font-weight: 600;
        margin-bottom: 12px;
        color: #0f172a;
    }
    .technique-card p {
        font-size: 15px;
        color: #64748b;
        line-height: 1.6;
        margin: 0;
    }
    .cta-section {
        padding: 100px 24px;
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: #ffffff;
        text-align: center;
    }
    .cta-content {
        max-width: 700px;
        margin: 0 auto;
    }
    .cta-section h2 {
        font-size: 42px;
        font-weight: 700;
        margin-bottom: 20px;
        letter-spacing: -1px;
    }
    .cta-section p {
        font-size: 18px;
        margin-bottom: 32px;
        opacity: 0.9;
        line-height: 1.6;
    }
    @media (max-width: 768px) {
        .hero {
            padding: 120px 24px;
        }
        .hero h1 {
            font-size: 48px;
        }
        .hero-subtitle {
            font-size: 24px;
        }
        .hero-description {
            font-size: 18px;
        }
        .section-title {
            font-size: 36px;
        }
        .techniques-grid {
            grid-template-columns: 1fr;
        }
        .section-header {
            margin-bottom: 48px;
        }
    }
    /* Scroll Animation Styles */
    .fade-in-up,
    .fade-in-left,
    .fade-in-right,
    .fade-in {
        opacity: 0;
        transition: opacity 0.8s ease-out, transform 0.8s ease-out;
    }
    .fade-in-up {
        transform: translateY(40px);
    }
    .fade-in-left {
        transform: translateX(-40px);
    }
    .fade-in-right {
        transform: translateX(40px);
    }
    .fade-in-up.animate-in,
    .fade-in-left.animate-in,
    .fade-in-right.animate-in,
    .fade-in.animate-in {
        opacity: 1;
        transform: translate(0, 0);
    }
    .feature-item,
    .feature-image,
    .technique-card,
    .section-header {
        transition-delay: 0s;
    }
    .feature-item:nth-child(2) {
        transition-delay: 0.2s;
    }
    .technique-card:nth-child(1) {
        transition-delay: 0.1s;
    }
    .technique-card:nth-child(2) {
        transition-delay: 0.2s;
    }
    .technique-card:nth-child(3) {
        transition-delay: 0.3s;
    }
    .technique-card:nth-child(4) {
        transition-delay: 0.4s;
    }
</style>
<section class="hero">
    <div class="hero-content">
        <h1><?php echo htmlspecialchars(t('hero_main_title'), ENT_QUOTES, 'UTF-8'); ?></h1>
        <p class="hero-subtitle"><?php echo htmlspecialchars(t('hero_effective_platform'), ENT_QUOTES, 'UTF-8'); ?></p>
        <p class="hero-description">
            <?php echo htmlspecialchars(t('hero_learn_description'), ENT_QUOTES, 'UTF-8'); ?>
        </p>
        <div class="hero-cta">
            <a href="/pickelball/user/frontend/register.php" class="btn-primary"><?php echo htmlspecialchars(t('get_started'), ENT_QUOTES, 'UTF-8'); ?></a>
            <a href="#how-it-works" class="btn-secondary"><?php echo htmlspecialchars(t('how_it_works'), ENT_QUOTES, 'UTF-8'); ?></a>
        </div>
    </div>
</section>

<section class="features" id="how-it-works">
    <div class="features-container">
        <div class="section-header fade-in-up">
            <h2 class="section-title"><?php echo htmlspecialchars(t('immersive_training'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <p class="section-subtitle"><?php echo htmlspecialchars(t('immersive_subtitle'), ENT_QUOTES, 'UTF-8'); ?></p>
            <p class="section-subtitle" style="margin-top: 16px;">
                <?php echo htmlspecialchars(t('training_approach'), ENT_QUOTES, 'UTF-8'); ?>
            </p>
        </div>
        <div class="feature-group fade-in-up">
            <div class="feature-content">
                <div class="feature-item fade-in-left">
                    <div class="feature-icon">
                        <svg viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                            <path d="M15 10L19.553 7.276A1 1 0 0121 8.118v7.764a1 1 0 01-1.447.842L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <div class="feature-text">
                        <h3><?php echo htmlspecialchars(t('feature_video_analysis'), ENT_QUOTES, 'UTF-8'); ?></h3>
                        <p><?php echo htmlspecialchars(t('feature_video_analysis_desc'), ENT_QUOTES, 'UTF-8'); ?></p>
                        <ul class="feature-list">
                            <li><?php echo htmlspecialchars(t('feature_video_analysis_item1'), ENT_QUOTES, 'UTF-8'); ?></li>
                            <li><?php echo htmlspecialchars(t('feature_video_analysis_item2'), ENT_QUOTES, 'UTF-8'); ?></li>
                            <li><?php echo htmlspecialchars(t('feature_video_analysis_item3'), ENT_QUOTES, 'UTF-8'); ?></li>
                            <li><?php echo htmlspecialchars(t('feature_video_analysis_item4'), ENT_QUOTES, 'UTF-8'); ?></li>
                        </ul>
                    </div>
                </div>
                <div class="feature-item fade-in-left">
                    <div class="feature-icon">
                        <svg viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                            <path d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            <circle cx="8" cy="12" r="1.5"/>
                        </svg>
                    </div>
                    <div class="feature-text">
                        <h3><?php echo htmlspecialchars(t('feature_live_sessions'), ENT_QUOTES, 'UTF-8'); ?></h3>
                        <p><?php echo htmlspecialchars(t('feature_live_sessions_desc'), ENT_QUOTES, 'UTF-8'); ?></p>
                        <ul class="feature-list">
                            <li><?php echo htmlspecialchars(t('feature_live_sessions_item1'), ENT_QUOTES, 'UTF-8'); ?></li>
                            <li><?php echo htmlspecialchars(t('feature_live_sessions_item2'), ENT_QUOTES, 'UTF-8'); ?></li>
                            <li><?php echo htmlspecialchars(t('feature_live_sessions_item3'), ENT_QUOTES, 'UTF-8'); ?></li>
                            <li><?php echo htmlspecialchars(t('feature_live_sessions_item4'), ENT_QUOTES, 'UTF-8'); ?></li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="feature-image fade-in-right">
                <img src="/pickelball/images/subbanner1.png" alt="Video Training" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                <div class="feature-image-placeholder" style="display: none;">Video Training</div>
            </div>
        </div>

        <div class="feature-group reverse fade-in-up">
            <div class="feature-content">
                <div class="feature-item fade-in-right">
                    <div class="feature-icon">
                        <svg viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 12a4 4 0 100-8 4 4 0 000 8zM12 14c-4.42 0-8 1.79-8 4v2h16v-2c0-2.21-3.58-4-8-4z"/>
                        </svg>
                    </div>
                    <div class="feature-text">
                        <h3><?php echo htmlspecialchars(t('feature_shadowing_mode'), ENT_QUOTES, 'UTF-8'); ?></h3>
                        <p><?php echo htmlspecialchars(t('feature_shadowing_mode_desc'), ENT_QUOTES, 'UTF-8'); ?></p>
                        <ul class="feature-list">
                            <li><?php echo htmlspecialchars(t('feature_shadowing_mode_item1'), ENT_QUOTES, 'UTF-8'); ?></li>
                            <li><?php echo htmlspecialchars(t('feature_shadowing_mode_item2'), ENT_QUOTES, 'UTF-8'); ?></li>
                            <li><?php echo htmlspecialchars(t('feature_shadowing_mode_item3'), ENT_QUOTES, 'UTF-8'); ?></li>
                            <li><?php echo htmlspecialchars(t('feature_shadowing_mode_item4'), ENT_QUOTES, 'UTF-8'); ?></li>
                        </ul>
                    </div>
                </div>
                <div class="feature-item fade-in-right">
                    <div class="feature-icon">
                        <svg viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                            <path d="M3 3v18h18M18 17V9M12 17V5M6 17v-3"/>
                        </svg>
                    </div>
                    <div class="feature-text">
                        <h3><?php echo htmlspecialchars(t('feature_performance_tracking'), ENT_QUOTES, 'UTF-8'); ?></h3>
                        <p><?php echo htmlspecialchars(t('feature_performance_tracking_desc'), ENT_QUOTES, 'UTF-8'); ?></p>
                        <ul class="feature-list">
                            <li><?php echo htmlspecialchars(t('feature_performance_tracking_item1'), ENT_QUOTES, 'UTF-8'); ?></li>
                            <li><?php echo htmlspecialchars(t('feature_performance_tracking_item2'), ENT_QUOTES, 'UTF-8'); ?></li>
                            <li><?php echo htmlspecialchars(t('feature_performance_tracking_item3'), ENT_QUOTES, 'UTF-8'); ?></li>
                            <li><?php echo htmlspecialchars(t('feature_performance_tracking_item4'), ENT_QUOTES, 'UTF-8'); ?></li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="feature-image fade-in-left">
                <img src="/pickelball/images/subbanner2.jpg" alt="Training Analytics" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                <div class="feature-image-placeholder" style="display: none;">Training Analytics</div>
            </div>
        </div>

        <div class="feature-group fade-in-up">
            <div class="feature-content">
                <div class="feature-item fade-in-left">
                    <div class="feature-icon">
                        <svg viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                            <path d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                        </svg>
                    </div>
                    <div class="feature-text">
                        <h3><?php echo htmlspecialchars(t('feature_ai_coach'), ENT_QUOTES, 'UTF-8'); ?></h3>
                        <p><?php echo htmlspecialchars(t('feature_ai_coach_desc'), ENT_QUOTES, 'UTF-8'); ?></p>
                        <ul class="feature-list">
                            <li><?php echo htmlspecialchars(t('feature_ai_coach_item1'), ENT_QUOTES, 'UTF-8'); ?></li>
                            <li><?php echo htmlspecialchars(t('feature_ai_coach_item2'), ENT_QUOTES, 'UTF-8'); ?></li>
                            <li><?php echo htmlspecialchars(t('feature_ai_coach_item3'), ENT_QUOTES, 'UTF-8'); ?></li>
                            <li><?php echo htmlspecialchars(t('feature_ai_coach_item4'), ENT_QUOTES, 'UTF-8'); ?></li>
                        </ul>
                    </div>
                </div>
                <div class="feature-item fade-in-left">
                    <div class="feature-icon">
                        <svg viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </div>
                    <div class="feature-text">
                        <h3><?php echo htmlspecialchars(t('feature_stunning_visuals'), ENT_QUOTES, 'UTF-8'); ?></h3>
                        <p><?php echo htmlspecialchars(t('feature_stunning_visuals_desc'), ENT_QUOTES, 'UTF-8'); ?></p>
                        <ul class="feature-list">
                            <li><?php echo htmlspecialchars(t('feature_stunning_visuals_item1'), ENT_QUOTES, 'UTF-8'); ?></li>
                            <li><?php echo htmlspecialchars(t('feature_stunning_visuals_item2'), ENT_QUOTES, 'UTF-8'); ?></li>
                            <li><?php echo htmlspecialchars(t('feature_stunning_visuals_item3'), ENT_QUOTES, 'UTF-8'); ?></li>
                            <li><?php echo htmlspecialchars(t('feature_stunning_visuals_item4'), ENT_QUOTES, 'UTF-8'); ?></li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="feature-image fade-in-right">
                <img src="/pickelball/images/subbanner3.jpg" alt="AI Insights" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                <div class="feature-image-placeholder" style="display: none;">AI Insights</div>
            </div>
        </div>
    </div>
</section>

<section class="techniques">
    <div class="techniques-container">
        <div class="section-header fade-in-up">
            <h2 class="section-title"><?php echo htmlspecialchars(t('master_techniques'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <p class="section-subtitle">
                <?php echo htmlspecialchars(t('master_techniques_subtitle'), ENT_QUOTES, 'UTF-8'); ?>
            </p>
        </div>
        <div class="techniques-grid">
            <div class="technique-card fade-in-up">
                <h3><?php echo htmlspecialchars(t('technique_serve'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <p><?php echo htmlspecialchars(t('technique_serve_desc'), ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <div class="technique-card fade-in-up">
                <h3><?php echo htmlspecialchars(t('technique_dink'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <p><?php echo htmlspecialchars(t('technique_dink_desc'), ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <div class="technique-card fade-in-up">
                <h3><?php echo htmlspecialchars(t('technique_drive_forehand'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <p><?php echo htmlspecialchars(t('technique_drive_forehand_desc'), ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <div class="technique-card fade-in-up">
                <h3><?php echo htmlspecialchars(t('technique_drive_backhand'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <p><?php echo htmlspecialchars(t('technique_drive_backhand_desc'), ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        </div>
    </div>
</section>

<section class="cta-section fade-in">
    <div class="cta-content">
        <h2><?php echo htmlspecialchars(t('cta_title'), ENT_QUOTES, 'UTF-8'); ?></h2>
        <p>
            <?php echo htmlspecialchars(t('cta_description'), ENT_QUOTES, 'UTF-8'); ?>
        </p>
        <a href="/pickelball/user/frontend/register.php" class="btn-primary" style="background: #ffffff; color: #10b981;"><?php echo htmlspecialchars(t('start_training'), ENT_QUOTES, 'UTF-8'); ?></a>
    </div>
</section>

<script src="/pickelball/main/frontend/js/scroll-animation.js"></script>
<?php require __DIR__ . '/../../includes/footer.php'; ?>


