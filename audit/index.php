<?php
/**
 * Audit Logs
 * Gym Management System
 * 
 * File: audit/index.php
 * Purpose: View system audit logs
 */

// Load configuration
require_once '../config/config.php';

// Require authentication
requireAuth();

// Check permissions
if (!canViewAudit()) {
    displayError('You do not have permission to view audit logs.');
    redirect('../dashboard.php');
}

// Get filter parameters
$module = isset($_GET['module']) ? sanitize($_GET['module']) : '';
$action = isset($_GET['action']) ? sanitize($_GET['action']) : '';
$date_from = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : date('Y-m-d', strtotime('-7 days'));
$date_to = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : date('Y-m-d');
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

// Build filters
$filters = [
    'date_from' => $date_from,
    'date_to' => $date_to,
];

if (!empty($module)) $filters['module'] = $module;
if (!empty($action)) $filters['action'] = $action;

// Get audit logs
$logs = getAuditLogs($limit, $offset, $filters);
$total = getAuditLogCount($filters);
$total_pages = ceil($total / $limit);

// Get distinct modules and actions for filters
try {
    $stmt = $pdo->query("SELECT DISTINCT module FROM audit_logs ORDER BY module");
    $modules = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $stmt = $pdo->query("SELECT DISTINCT action FROM audit_logs ORDER BY action");
    $actions = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log('Get filter options error: ' . $e->getMessage());
    $modules = [];
    $actions = [];
}

// Include header
include '../includes/header.php';
?>

<body>
    <div class="app-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include '../includes/navbar.php'; ?>
            
            <div class="content-wrapper">
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <h4 class="mb-0"><i class="bi bi-clipboard-data"></i> Audit Logs</h4>
                </div>
                
                <!-- Filters -->
                <div class="card modern-card mb-4">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Module</label>
                                <select class="form-select" name="module">
                                    <option value="">All Modules</option>
                                    <?php foreach ($modules as $mod): ?>
                                    <option value="<?php echo $mod; ?>" <?php echo $module === $mod ? 'selected' : ''; ?>>
                                        <?php echo ucfirst($mod); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Action</label>
                                <select class="form-select" name="action">
                                    <option value="">All Actions</option>
                                    <?php foreach ($actions as $act): ?>
                                    <option value="<?php echo $act; ?>" <?php echo $action === $act ? 'selected' : ''; ?>>
                                        <?php echo str_replace('_', ' ', $act); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Date From</label>
                                <input type="date" class="form-control" name="date_from" value="<?php echo $date_from; ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Date To</label>
                                <input type="date" class="form-control" name="date_to" value="<?php echo $date_to; ?>">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search"></i> Filter
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Log Table -->
                <div class="card modern-card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Date/Time</th>
                                        <th>User</th>
                                        <th>Module</th>
                                        <th>Action</th>
                                        <th>Description</th>
                                        <th>IP Address</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($logs)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">
                                            <i class="bi bi-clipboard-data" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                                            No audit logs found.
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($logs as $log): ?>
                                        <tr>
                                            <td>
                                                <?php echo formatDate($log['created_at']); ?>
                                                <br><small class="text-muted"><?php echo formatTime($log['created_at']); ?></small>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($log['full_name'] ?? $log['username'] ?? 'Unknown'); ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?php echo htmlspecialchars($log['module']); ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?php echo str_replace('_', ' ', $log['action']); ?></span>
                                            </td>
                                            <td><?php echo htmlspecialchars($log['description'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                        <div class="card-footer bg-transparent">
                            <nav aria-label="Page navigation">
                                <ul class="pagination justify-content-center mb-0">
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&module=<?php echo $module; ?>&action=<?php echo $action; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">Previous</a>
                                    </li>
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&module=<?php echo $module; ?>&action=<?php echo $action; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>"><?php echo $i; ?></a>
                                    </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&module=<?php echo $module; ?>&action=<?php echo $action; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">Next</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>
</body>
</html>