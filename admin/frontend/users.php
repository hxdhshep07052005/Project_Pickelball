<?php
declare(strict_types=1);



require __DIR__ . '/../backend/require_auth.php';

$admin = $_SESSION['admin'];

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$searchQuery = '';
$searchParams = [];

if ($search !== '') {
    $searchQuery = 'WHERE email LIKE ? OR display_name LIKE ?';
    $searchTerm = '%' . $search . '%';
    $searchParams = [$searchTerm, $searchTerm];
}

$users = [];
$totalUsers = 0;
$totalPages = 1;

try {
    $countQuery = 'SELECT COUNT(*) as count FROM users ' . $searchQuery;
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($searchParams);
    $totalUsers = (int)$countStmt->fetch()['count'];

    $query = 'SELECT id, email, display_name, role, status, auth_provider, email_verified_at, last_login_at, created_at FROM users ' . $searchQuery . ' ORDER BY created_at DESC LIMIT ? OFFSET ?';
    $stmt = $pdo->prepare($query);
    $params = array_merge($searchParams, [$limit, $offset]);
    $stmt->execute($params);
    $users = $stmt->fetchAll();

    $totalPages = max(1, (int)ceil($totalUsers / $limit));
} catch (PDOException $e) {
    error_log("Users page error: " . $e->getMessage());
    $totalPages = 1;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin</title>
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
            border: none;
            cursor: pointer;
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
        .search-box {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
        }
        .search-input {
            flex: 1;
            padding: 12px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 16px;
        }
        .search-input:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
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
        .badge-active {
            background: #dcfce7;
            color: #15803d;
        }
        .badge-pending {
            background: #fef3c7;
            color: #92400e;
        }
        .badge-suspended {
            background: #fee2e2;
            color: #b91c1c;
        }
        .badge-player {
            background: #dbeafe;
            color: #1e40af;
        }
        .badge-admin {
            background: #f3e8ff;
            color: #7c3aed;
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
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: #ffffff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.1);
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
        .action-buttons {
            display: flex;
            gap: 8px;
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
    </style>
</head>
<body>
<div class="dashboard-header">
    <h1>Admin Panel</h1>
    <div class="header-actions">
        <a href="dashboard.php" class="btn btn-primary">Dashboard</a>
        <span><?php echo htmlspecialchars($admin['username'], ENT_QUOTES, 'UTF-8'); ?></span>
        <a href="../backend/logout.php" class="btn btn-logout">Logout</a>
    </div>
</div>

<div class="container">
    <div class="page-header">
        <h2>Manage Users</h2>
    </div>

    <div class="search-box">
        <form method="get" style="display: flex; gap: 12px; width: 100%;">
            <input type="text" name="search" class="search-input" placeholder="Search by email or name..." value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit" class="btn btn-primary">Search</button>
            <?php if ($search): ?>
                <a href="users.php" class="btn" style="background: #64748b; color: white;">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="stats">
        <div class="stat-card">
            <h3>Total Users</h3>
            <div class="value"><?php echo number_format($totalUsers); ?></div>
        </div>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Email</th>
                    <th>Name</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Provider</th>
                    <th>Last Login</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 40px; color: #64748b;">No users found</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string)$user['id'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($user['display_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><span class="badge badge-<?php echo htmlspecialchars($user['role'] ?? 'player', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($user['role'] ?? 'player', ENT_QUOTES, 'UTF-8'); ?></span></td>
                            <td><span class="badge badge-<?php echo htmlspecialchars($user['status'] ?? 'pending', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($user['status'] ?? 'pending', ENT_QUOTES, 'UTF-8'); ?></span></td>
                            <td><?php echo htmlspecialchars($user['auth_provider'] ?? 'password', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo $user['last_login_at'] ? date('Y-m-d H:i', strtotime($user['last_login_at'])) : '-'; ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($user['created_at'])); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-delete" onclick="deleteUser(<?php echo (int)$user['id']; ?>, '<?php echo htmlspecialchars(addslashes($user['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>')">Delete</button>
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
                <a href="?page=<?php echo $page - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">Previous</a>
            <?php endif; ?>

            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <?php if ($i == $page): ?>
                    <span class="current"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>"><?php echo $i; ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <a href="?page=<?php echo $page + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">Next</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function deleteUser(id, email) {
    if (!confirm(`Are you sure you want to delete user "${email}"? This will also delete all their videos and related data. This action cannot be undone.`)) {
        return;
    }

    fetch('../backend/delete_user.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ id: id })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('User deleted successfully');
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Failed to delete user'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error deleting user');
    });
}
</script>

</body>
</html>
