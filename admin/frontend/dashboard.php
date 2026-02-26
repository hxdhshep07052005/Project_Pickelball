<?php
declare(strict_types=1);

/**
 * Admin Dashboard page
 * Main admin interface with statistics and management links
 */

require __DIR__ . '/../backend/require_auth.php';

$admin = $_SESSION['admin'];

// Get statistics from database
$stats = [
    'total_users' => 0,
    'active_users' => 0,
    'total_video_analyses' => 0,
    'total_action_predictions' => 0,
    'analyses_today' => 0,
    'predictions_today' => 0,
];

try {
    // Count total users
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM users');
    $stats['total_users'] = (int)$stmt->fetch()['count'];
    
    // Count active users
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE status = 'active'");
    $stats['active_users'] = (int)$stmt->fetch()['count'];
    
    // Count video analyses
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM video_analyses');
    $stats['total_video_analyses'] = (int)$stmt->fetch()['count'];
    
    // Count action predictions
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM action_predictions');
    $stats['total_action_predictions'] = (int)$stmt->fetch()['count'];
    
    // Count analyses today
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM video_analyses WHERE DATE(created_at) = CURDATE()");
    $stats['analyses_today'] = (int)$stmt->fetch()['count'];
    
    // Count predictions today
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM action_predictions WHERE DATE(created_at) = CURDATE()");
    $stats['predictions_today'] = (int)$stmt->fetch()['count'];
} catch (PDOException $e) {
    // If tables don't exist, stats will remain 0
    error_log("Dashboard stats error: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Pickleball Training</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f8fafc;
            color: #0f172a;
            line-height: 1.6;
        }
        .dashboard-container {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .dashboard-header {
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            padding: 20px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        .dashboard-header h1 {
            font-size: 24px;
            font-weight: 700;
            color: #0f172a;
        }
        .header-actions {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .admin-info {
            color: #64748b;
            font-size: 14px;
        }
        .btn-logout {
            padding: 10px 20px;
            background: #ef4444;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s;
            text-decoration: none;
            display: inline-block;
        }
        .btn-logout:hover {
            background: #dc2626;
        }
        .dashboard-content {
            flex: 1;
            padding: 32px;
            max-width: 1400px;
            margin: 0 auto;
            width: 100%;
        }
        .welcome-card {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border-radius: 16px;
            padding: 40px;
            color: #ffffff;
            margin-bottom: 32px;
            box-shadow: 0 8px 24px rgba(16, 185, 129, 0.2);
        }
        .welcome-card h2 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .welcome-card p {
            font-size: 18px;
            opacity: 0.95;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }
        .stat-card {
            background: #ffffff;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.1);
            transition: transform 0.2s, box-shadow 0.2s;
            position: relative;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(15, 23, 42, 0.15);
        }
        .stat-icon {
            position: absolute;
            top: 24px;
            right: 24px;
            width: 48px;
            height: 48px;
            opacity: 0.1;
        }
        .stat-card h3 {
            font-size: 14px;
            font-weight: 500;
            color: #64748b;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .stat-card h3 svg {
            width: 16px;
            height: 16px;
            color: #64748b;
        }
        .stat-card .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #0f172a;
        }
        .stat-card .stat-change {
            font-size: 14px;
            color: #10b981;
            margin-top: 8px;
        }
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 32px;
        }
        .action-btn {
            background: #ffffff;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 24px;
            text-decoration: none;
            color: #0f172a;
            transition: all 0.2s;
            display: block;
            text-align: center;
        }
        .action-btn:hover {
            border-color: #10b981;
            background: #f0fdf4;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.1);
        }
        .action-btn h3 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .action-btn p {
            font-size: 14px;
            color: #64748b;
        }
        .action-btn svg {
            color: #10b981;
        }
        .charts-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }
        .chart-container {
            background: #ffffff;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.1);
        }
        .chart-container h3 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #0f172a;
        }
        .chart-wrapper {
            position: relative;
            height: 300px;
        }
        .chart-controls {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
        }
        .chart-control-btn {
            padding: 6px 12px;
            border: 1px solid #e2e8f0;
            background: #ffffff;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            color: #64748b;
            transition: all 0.2s;
        }
        .chart-control-btn:hover {
            border-color: #10b981;
            color: #10b981;
        }
        .chart-control-btn.active {
            background: #10b981;
            border-color: #10b981;
            color: #ffffff;
        }
        .section {
            background: #ffffff;
            border-radius: 12px;
            padding: 32px;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.1);
            margin-bottom: 32px;
        }
        .section h2 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 24px;
            color: #0f172a;
        }
        .info-text {
            color: #64748b;
            font-size: 16px;
        }
        @media (max-width: 768px) {
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }
            .dashboard-content {
                padding: 16px;
            }
            .welcome-card {
                padding: 24px;
            }
            .welcome-card h2 {
                font-size: 24px;
            }
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .quick-actions {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
<div class="dashboard-container">
    <header class="dashboard-header">
        <h1>Admin Dashboard</h1>
        <div class="header-actions">
            <span class="admin-info">Logged in as: <?php echo htmlspecialchars($admin['username'], ENT_QUOTES, 'UTF-8'); ?></span>
            <a href="../backend/logout.php" class="btn-logout">Logout</a>
        </div>
    </header>
    
    <main class="dashboard-content">
        <div class="welcome-card">
            <h2>Welcome, Admin!</h2>
            <p>Manage your Pickleball Training System from here</p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <svg class="stat-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
                <h3>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                    Total Users
                </h3>
                <div class="stat-value"><?php echo number_format($stats['total_users']); ?></div>
            </div>
            <div class="stat-card">
                <svg class="stat-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
                <h3>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    Active Users
                </h3>
                <div class="stat-value"><?php echo number_format($stats['active_users']); ?></div>
            </div>
            <div class="stat-card">
                <svg class="stat-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M23 7l-7 5 7 5V7z"></path>
                    <rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect>
                </svg>
                <h3>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M23 7l-7 5 7 5V7z"></path>
                        <rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect>
                    </svg>
                    Video Analyses
                </h3>
                <div class="stat-value"><?php echo number_format($stats['total_video_analyses']); ?></div>
                <div class="stat-change"><?php echo number_format($stats['analyses_today']); ?> today</div>
            </div>
            <div class="stat-card">
                <svg class="stat-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                </svg>
                <h3>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                    </svg>
                    Action Predictions
                </h3>
                <div class="stat-value"><?php echo number_format($stats['total_action_predictions']); ?></div>
                <div class="stat-change"><?php echo number_format($stats['predictions_today']); ?> today</div>
            </div>
        </div>
        
        <div class="charts-section">
            <div class="chart-container">
                <h3>Activity Overview</h3>
                <div class="chart-controls">
                    <button class="chart-control-btn active" onclick="loadChart(7, this)">7 Days</button>
                    <button class="chart-control-btn" onclick="loadChart(14, this)">14 Days</button>
                    <button class="chart-control-btn" onclick="loadChart(30, this)">30 Days</button>
                </div>
                <div class="chart-wrapper">
                    <canvas id="activityChart"></canvas>
                </div>
            </div>
            <div class="chart-container">
                <h3>User Registrations</h3>
                <div class="chart-controls">
                    <button class="chart-control-btn active" onclick="loadUserChart(7, this)">7 Days</button>
                    <button class="chart-control-btn" onclick="loadUserChart(14, this)">14 Days</button>
                    <button class="chart-control-btn" onclick="loadUserChart(30, this)">30 Days</button>
                </div>
                <div class="chart-wrapper">
                    <canvas id="userChart"></canvas>
                </div>
            </div>
        </div>
        
        <div class="quick-actions">
            <a href="users.php" class="action-btn">
                <svg style="width: 24px; height: 24px; margin-bottom: 8px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
                <h3>Manage Users</h3>
                <p>View, edit, and delete user accounts</p>
            </a>
            <a href="video_analyses.php" class="action-btn">
                <svg style="width: 24px; height: 24px; margin-bottom: 8px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M23 7l-7 5 7 5V7z"></path>
                    <rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect>
                </svg>
                <h3>Video Analyses</h3>
                <p>Manage video analysis records</p>
            </a>
            <a href="action_predictions.php" class="action-btn">
                <svg style="width: 24px; height: 24px; margin-bottom: 8px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                </svg>
                <h3>Action Predictions</h3>
                <p>View and manage prediction records</p>
            </a>
        </div>
    </main>
</div>

<script>
let activityChart = null;
let userChart = null;

function loadChart(days, button) {
    // Update active button
    const buttons = document.querySelectorAll('.chart-container:first-child .chart-control-btn');
    buttons.forEach(btn => btn.classList.remove('active'));
    if (button) {
        button.classList.add('active');
    }
    
    fetch(`../backend/get_chart_data.php?days=${days}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderActivityChart(data.data);
            }
        })
        .catch(error => {
            console.error('Error loading chart data:', error);
        });
}

function loadUserChart(days, button) {
    // Update active button
    const buttons = document.querySelectorAll('.chart-container:last-child .chart-control-btn');
    buttons.forEach(btn => btn.classList.remove('active'));
    if (button) {
        button.classList.add('active');
    }
    
    fetch(`../backend/get_chart_data.php?days=${days}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            console.log('User chart data received:', data);
            if (data.success && data.data) {
                renderUserChart(data.data);
            } else {
                console.error('Invalid data structure:', data);
            }
        })
        .catch(error => {
            console.error('Error loading user chart data:', error);
        });
}

function renderActivityChart(data) {
    const ctx = document.getElementById('activityChart').getContext('2d');
    
    if (activityChart) {
        activityChart.destroy();
    }
    
    activityChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [
                {
                    label: 'Video Analyses',
                    data: data.analyses,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4,
                    fill: true
                },
                {
                    label: 'Action Predictions',
                    data: data.predictions,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4,
                    fill: true
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
}

function renderUserChart(data) {
    const canvas = document.getElementById('userChart');
    if (!canvas) {
        console.error('User chart canvas not found');
        return;
    }
    
    const ctx = canvas.getContext('2d');
    
    if (userChart) {
        userChart.destroy();
    }
    
    console.log('Rendering user chart with data:', data);
    
    userChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.labels || [],
            datasets: [{
                label: 'New Users',
                data: data.users || [],
                backgroundColor: '#f59e0b',
                borderColor: '#d97706',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                },
                tooltip: {
                    enabled: true
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1,
                        precision: 0
                    }
                },
                x: {
                    display: true
                }
            }
        }
    });
}

// Load initial charts
document.addEventListener('DOMContentLoaded', function() {
    const firstBtn = document.querySelector('.chart-container:first-child .chart-control-btn.active');
    const lastBtn = document.querySelector('.chart-container:last-child .chart-control-btn.active');
    loadChart(7, firstBtn);
    loadUserChart(7, lastBtn);
});
</script>

</body>
</html>
