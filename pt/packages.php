<?php
/**
 * PT Packages
 * Gym Management System
 * 
 * File: pt/packages.php
 * Purpose: View and manage PT packages
 */

// Load configuration
require_once '../config/config.php';

// Require authentication
requireAuth();

// Get filter parameters
$status = isset($_GET['status']) ? sanitize($_GET['status']) : 'all';
$trainer_id = isset($_GET['trainer']) ? (int)$_GET['trainer'] : 0;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Build query
$where_conditions = [];
$params = [];

if ($status !== 'all') {
    $where_conditions[] = "pp.status = ?";
    $params[] = $status;
}

if ($trainer_id > 0) {
    $where_conditions[] = "pp.trainer_id = ?";
    $params[] = $trainer_id;
}

// If trainer, only show their packages
if (isTrainer()) {
    $current_trainer_id = getCurrentTrainerId();
    if ($current_trainer_id) {
        $where_conditions[] = "pp.trainer_id = ?";
        $params[] = $current_trainer_id;
    }
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
try {
    $count_sql = "SELECT COUNT(*) as total FROM pt_packages pp $where_clause";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total = $stmt->fetch()['total'] ?? 0;
    $total_pages = ceil($total / $limit);
} catch (PDOException $e) {
    error_log('Package count error: ' . $e->getMessage());
    $total = 0;
    $total_pages = 0;
}

// Get packages
try {
    $sql = "SELECT pp.*, 
            CONCAT(m.first_name, ' ', m.last_name) as member_name,
            CONCAT(pt.first_name, ' ', pt.last_name) as trainer_name,
            m.member_number
            FROM pt_packages pp
            INNER JOIN members m ON pp.member_id = m.member_id
            INNER JOIN personal_trainers pt ON pp.trainer_id = pt.trainer_id
            $where_clause
            ORDER BY pp.created_at DESC
            LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $packages = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Package list error: ' . $e->getMessage());
    $packages = [];
}

// Get trainers for filter
try {
    $stmt = $pdo->query("SELECT trainer_id, first_name, last_name FROM personal_trainers WHERE status = 'active' ORDER BY last_name");
    $trainers = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Get trainers error: ' . $e->getMessage());
    $trainers = [];
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
                    <h4 class="mb-0"><i class="bi bi-box"></i> PT Packages</h4>
                    <?php if (isStaff()): ?>
                    <a href="create_package.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Create Package
                    </a>
                    <?php endif; ?>
                </div>
                
                <!-- Filters -->
                <div class="card modern-card mb-4">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status">
                                    <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All</option>
                                    <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="expired" <?php echo $status === 'expired' ? 'selected' : ''; ?>>Expired</option>
                                    <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                            <?php if (!isTrainer()): ?>
                            <div class="col-md-4">
                                <label class="form-label">Trainer</label>
                                <select class="form-select" name="trainer">
                                    <option value="0">All Trainers</option>
                                    <?php foreach ($trainers as $t): ?>
                                    <option value="<?php echo $t['trainer_id']; ?>" <?php echo $trainer_id == $t['trainer_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($t['first_name'] . ' ' . $t['last_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search"></i> Filter
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Packages Table -->
                <div class="card modern-card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Member</th>
                                        <th>Trainer</th>
                                        <th>Sessions</th>
                                        <th>Used</th>
                                        <th>Remaining</th>
                                        <th>Price</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($packages)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">
                                            <i class="bi bi-box" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                                            No PT packages found.
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($packages as $pkg): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($pkg['member_name']); ?></strong>
                                                <br><small class="text-muted"><?php echo $pkg['member_number']; ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($pkg['trainer_name']); ?></td>
                                            <td><?php echo $pkg['total_sessions']; ?></td>
                                            <td><?php echo $pkg['used_sessions']; ?></td>
                                            <td>
                                                <span class="<?php echo $pkg['remaining_sessions'] <= 3 ? 'text-warning' : ''; ?>">
                                                    <?php echo $pkg['remaining_sessions']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo formatCurrency($pkg['price']); ?></td>
                                            <td>
                                                <span class="badge <?php 
                                                    echo $pkg['status'] === 'active' ? 'bg-success' : 
                                                         ($pkg['status'] === 'completed' ? 'bg-info' : 
                                                         ($pkg['status'] === 'cancelled' ? 'bg-danger' : 'bg-warning')); 
                                                ?>">
                                                    <?php echo ucfirst($pkg['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="view_package.php?id=<?php echo $pkg['package_id']; ?>" class="btn btn-outline-primary btn-sm">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if ($total_pages > 1): ?>
                        <div class="card-footer bg-transparent">
                            <nav aria-label="Page navigation">
                                <ul class="pagination justify-content-center mb-0">
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo $status; ?>&trainer=<?php echo $trainer_id; ?>">Previous</a>
                                    </li>
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $status; ?>&trainer=<?php echo $trainer_id; ?>"><?php echo $i; ?></a>
                                    </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo $status; ?>&trainer=<?php echo $trainer_id; ?>">Next</a>
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