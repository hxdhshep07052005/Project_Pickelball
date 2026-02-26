<?php
declare(strict_types=1);

/**
 * Admin Video Analyses Management page
 * List and manage video analysis records
 */

require __DIR__ . '/../backend/require_auth.php';

$admin = $_SESSION['admin'];

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Get analyses
$analyses = [];
$totalAnalyses = 0;
$totalPages = 1;

try {
    // Count total
    $countStmt = $pdo->query('SELECT COUNT(*) as count FROM video_analyses');
    $totalAnalyses = (int)$countStmt->fetch()['count'];
    
    // Get analyses with user info
    $query = 'SELECT va.id, va.user_id, u.email, u.display_name, va.video_name, va.video_path, va.score, va.status, va.created_at 
              FROM video_analyses va 
              LEFT JOIN users u ON va.user_id = u.id 
              ORDER BY va.created_at DESC LIMIT ? OFFSET ?';
    $stmt = $pdo->prepare($query);
    $stmt->execute([$limit, $offset]);
    $analyses = $stmt->fetchAll();
    
    $totalPages = max(1, (int)ceil($totalAnalyses / $limit));
} catch (PDOException $e) {
    error_log("Video analyses page error: " . $e->getMessage());
    $totalPages = 1;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Video Analyses - Admin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8fafc;
            color: #0f172a;
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
        }
        .header-actions {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s;
        }
        .btn-primary {
            background: #10b981;
            color: #ffffff;
        }
        .btn-primary:hover {
            background: #059669;
        }
        .btn-logout {
            background: #ef4444;
            color: #ffffff;
        }
        .btn-logout:hover {
            background: #dc2626;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 32px;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        .page-header h2 {
            font-size: 32px;
            font-weight: 700;
        }
        .table-container {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.1);
            overflow: hidden;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        thead {
            background: #f8fafc;
        }
        th {
            padding: 16px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        td {
            padding: 16px;
            border-top: 1px solid #e2e8f0;
        }
        tbody tr:hover {
            background: #f8fafc;
        }
        .badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        .badge-completed {
            background: #dcfce7;
            color: #15803d;
        }
        .badge-model_not_working {
            background: #fee2e2;
            color: #b91c1c;
        }
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 24px;
        }
        .pagination a, .pagination span {
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            color: #64748b;
            background: #ffffff;
            border: 1px solid #e2e8f0;
        }
        .pagination a:hover {
            background: #f8fafc;
            border-color: #10b981;
            color: #10b981;
        }
        .pagination .current {
            background: #10b981;
            color: #ffffff;
            border-color: #10b981;
        }
        .stat-card {
            background: #ffffff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.1);
            margin-bottom: 24px;
        }
        .stat-card h3 {
            font-size: 14px;
            color: #64748b;
            margin-bottom: 8px;
            text-transform: uppercase;
        }
        .stat-card .value {
            font-size: 28px;
            font-weight: 700;
            color: #0f172a;
        }
        .text-truncate {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        .btn-view {
            background: #3b82f6;
            color: #ffffff;
            padding: 6px 12px;
            font-size: 12px;
        }
        .btn-view:hover {
            background: #2563eb;
        }
        .btn-play {
            background: #10b981;
            color: #ffffff;
            padding: 6px 12px;
            font-size: 12px;
        }
        .btn-play:hover {
            background: #059669;
        }
        .btn-delete {
            background: #ef4444;
            color: #ffffff;
            padding: 6px 12px;
            font-size: 12px;
        }
        .btn-delete:hover {
            background: #dc2626;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            overflow-y: auto;
        }
        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background: #ffffff;
            border-radius: 12px;
            padding: 32px;
            max-width: 800px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            margin: 20px;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        .modal-header h2 {
            font-size: 24px;
            font-weight: 700;
        }
        .modal-close {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: #64748b;
            padding: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-close:hover {
            color: #0f172a;
        }
        .detail-section {
            margin-bottom: 24px;
        }
        .detail-section h3 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 12px;
            color: #0f172a;
        }
        .detail-row {
            display: grid;
            grid-template-columns: 150px 1fr;
            gap: 16px;
            padding: 12px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        .detail-label {
            font-weight: 600;
            color: #64748b;
        }
        .detail-value {
            color: #0f172a;
        }
        .json-display {
            background: #f8fafc;
            padding: 16px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            font-family: monospace;
            font-size: 14px;
            white-space: pre-wrap;
            word-break: break-word;
            max-height: 300px;
            overflow-y: auto;
        }
        #videoPlayerModal .modal-content {
            max-width: 1000px;
        }
        .video-player {
            width: 100%;
            max-height: 70vh;
            background: #000;
            border-radius: 8px;
        }
        .video-info {
            margin-top: 16px;
            padding: 16px;
            background: #f8fafc;
            border-radius: 8px;
        }
    </style>
</head>
<body>
<div class="dashboard-header">
    <h1>Admin Panel</h1>
    <div class="header-actions">
        <a href="dashboard.php" class="btn btn-primary">Dashboard</a>
        <a href="users.php" class="btn btn-primary">Users</a>
        <a href="../backend/logout.php" class="btn btn-logout">Logout</a>
    </div>
</div>

<div class="container">
    <div class="page-header">
        <h2>Video Analyses</h2>
    </div>
    
    <div class="stat-card">
        <h3>Total Video Analyses</h3>
        <div class="value"><?php echo number_format($totalAnalyses); ?></div>
    </div>
    
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>Video Name</th>
                    <th>Score</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($analyses)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 40px; color: #64748b;">No video analyses found</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($analyses as $analysis): ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string)$analysis['id'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <div><?php echo htmlspecialchars($analysis['display_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></div>
                                <div style="font-size: 12px; color: #64748b;"><?php echo htmlspecialchars($analysis['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                            </td>
                            <td class="text-truncate"><?php echo htmlspecialchars($analysis['video_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo $analysis['score'] !== null ? htmlspecialchars((string)$analysis['score'], ENT_QUOTES, 'UTF-8') : '-'; ?></td>
                            <td><span class="badge badge-<?php echo htmlspecialchars($analysis['status'] ?? 'model_not_working', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($analysis['status'] ?? 'model_not_working', ENT_QUOTES, 'UTF-8'); ?></span></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($analysis['created_at'])); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-view" onclick="viewVideo(<?php echo (int)$analysis['id']; ?>)">Details</button>
                                    <button class="btn btn-play" onclick="playVideo('<?php echo htmlspecialchars(addslashes($analysis['video_path'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>')">Play</button>
                                    <button class="btn btn-delete" onclick="deleteVideo(<?php echo (int)$analysis['id']; ?>)">Delete</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>">Previous</a>
            <?php endif; ?>
            
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <?php if ($i == $page): ?>
                    <span class="current"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if ($page < $totalPages): ?>
                <a href="?page=<?php echo $page + 1; ?>">Next</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Modal for viewing video details -->
<div id="videoModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Video Analysis Details</h2>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div id="modalBody"></div>
    </div>
</div>

<!-- Modal for playing video -->
<div id="videoPlayerModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Video Player</h2>
            <button class="modal-close" onclick="closeVideoModal()">&times;</button>
        </div>
        <div id="videoPlayerBody">
            <video id="videoPlayer" class="video-player" controls>
                Your browser does not support the video tag.
            </video>
            <div id="videoPlayerInfo" class="video-info"></div>
        </div>
    </div>
</div>

<script>
function viewVideo(id) {
    fetch(`../backend/view_video.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const video = data.video;
                const modalBody = document.getElementById('modalBody');
                
                let html = `
                    <div class="detail-section">
                        <h3>Basic Information</h3>
                        <div class="detail-row">
                            <div class="detail-label">ID:</div>
                            <div class="detail-value">${video.id}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Video Name:</div>
                            <div class="detail-value">${escapeHtml(video.video_name || 'N/A')}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">User:</div>
                            <div class="detail-value">${escapeHtml(video.display_name || 'N/A')} (${escapeHtml(video.email || 'N/A')})</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Status:</div>
                            <div class="detail-value"><span class="badge badge-${video.status || 'model_not_working'}">${video.status || 'model_not_working'}</span></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Score:</div>
                            <div class="detail-value">${video.score !== null ? video.score : '-'}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Created At:</div>
                            <div class="detail-value">${video.created_at || 'N/A'}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Session ID:</div>
                            <div class="detail-value">${video.session_id || '-'}</div>
                        </div>
                    </div>
                `;
                
                if (video.techniques_detected && video.techniques_detected.length > 0) {
                    html += `
                        <div class="detail-section">
                            <h3>Techniques Detected</h3>
                            <div class="json-display">${JSON.stringify(video.techniques_detected, null, 2)}</div>
                        </div>
                    `;
                }
                
                if (video.coaching_feedback) {
                    html += `
                        <div class="detail-section">
                            <h3>Coaching Feedback</h3>
                            <div class="json-display">${typeof video.coaching_feedback === 'string' ? escapeHtml(video.coaching_feedback) : JSON.stringify(video.coaching_feedback, null, 2)}</div>
                        </div>
                    `;
                }
                
                if (video.raw_feedback) {
                    html += `
                        <div class="detail-section">
                            <h3>Raw Feedback</h3>
                            <div class="json-display">${typeof video.raw_feedback === 'string' ? escapeHtml(video.raw_feedback) : JSON.stringify(video.raw_feedback, null, 2)}</div>
                        </div>
                    `;
                }
                
                modalBody.innerHTML = html;
                document.getElementById('videoModal').classList.add('active');
            } else {
                alert('Error: ' + (data.error || 'Failed to load video details'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading video details');
        });
}

function deleteVideo(id) {
    if (!confirm('Are you sure you want to delete this video? This action cannot be undone.')) {
        return;
    }
    
    fetch('../backend/delete_video.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ id: id })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Video deleted successfully');
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Failed to delete video'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error deleting video');
    });
}

function playVideo(videoPath) {
    if (!videoPath) {
        alert('Video path not available');
        return;
    }
    
    const videoPlayer = document.getElementById('videoPlayer');
    const videoPlayerInfo = document.getElementById('videoPlayerInfo');
    const videoName = videoPath.split('/').pop();
    
    videoPlayer.src = videoPath;
    videoPlayerInfo.innerHTML = `<strong>Video:</strong> ${escapeHtml(videoName)}`;
    
    document.getElementById('videoPlayerModal').classList.add('active');
}

function closeVideoModal() {
    const videoPlayer = document.getElementById('videoPlayer');
    videoPlayer.pause();
    videoPlayer.src = '';
    document.getElementById('videoPlayerModal').classList.remove('active');
}

function closeModal() {
    document.getElementById('videoModal').classList.remove('active');
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Close modal when clicking outside
document.getElementById('videoModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});

document.getElementById('videoPlayerModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeVideoModal();
    }
});
</script>

</body>
</html>

