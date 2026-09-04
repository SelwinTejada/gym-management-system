<?php
/**
 * Membership History
 * Gym Management System
 * 
 * File: membership/history.php
 * Purpose: View membership history
 */

// Load configuration
require_once '../config/config.php';

// Require authentication
requireAuth;

// Check permissions
if (!isStaff()) {
    displayError('You do not have permission to view membership history.');
    redirect('../dashboard.php');
}

// Get filter parameters
$member_id = isset($_GET['member_id']) ? (int)$_GET['member_id'] : 0;
$status = isset($_GET['status']) ? sanitize($_GET['status']) : '';

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Build conditions
$where = '';
$params = [];

if ($member_id > 0) {
    $where = 'WHERE mm.member_id = ?';
    $params[] = $member_id;
}

if (!empty($status)) {
    if (!empty($where)) $where .= ' AND ';
    $where .= 'mm.status = ?';
    $params[] = $status;
}

// Get total count
try {
    $count_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM member_memberships mm $where");
    $count_stmt->execute($params);
    $total = $count_stmt->fetch()['total'] ?? 0;
    $total_pages = ceil($total / $limit);
} catch (PDOException $e) {
    error_log('Membership history count error: ' . $e->getMessage());
    $total = 0;
    $total_pages = 1;
}

// Get membership history
try {
    $history_stmt = $pdo->prepare("
        SELECT mm.*, mp.plan_name
        FROM member_memberships mm
        INNER JOIN membership_plans mp ON mm.plan_id = mp.plan_id
        $where
        ORDER BY mm.start_date DESC
        LIMIT ? OFFSET ?
    ");
    $history_stmt->execute(array_merge($params, [$limit, $offset]));
    $history = $history_stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Get membership history error: ' . $e->getMessage());
    $history = [];
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
                    <h4 class="mb-0"><i class="bi bi-card-list"></i> Membership History</h5>
                    
                    <?php if ($member_id > 0): ?>
                    <small class="text-muted">
                        Member: <?php 
                        $stmt = $pdo->prepare("SELECT member_number, first_name, last_name FROM members WHERE member_id = ?");
                        $stmt->execute([$member_id]);
                        $m = $stmt->fetch();
                        echo $m ? htmlspecialchars($m['first_name'] . ' ' . $m['last_name'] . ' (' . $m['member_number'] . ')' ) : 'Member';
                        ?>
                    </small>
                    <?php endif; ?>
                </div>
                
                <!-- Filters -->
                <div class="card modern-card mb-4">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <?php if ($member_id > 0): ?>
                            <input type="hidden" name="member_id" value="<?php echo $member_id; ?>">
                            <?php endif; ?>
                            <div class="col-md-3">
                                <label class="form-label">Status Filter</label>
                                <select class="form-control" name="status">
                                    <option value="">All Statuses</option>
                                    <?php 
                                    $statuses = ['active', 'expired', 'suspended', 'cancelled'];
                                    foreach ($statuses as $s): 
                                    ?>
                                    <option value="<?php echo $s; ?>" 
                                        <?php echo ($status === $s) ? 'selected' : ''; ?>>
                                        <?php echo ucfirst($s); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Date From</label>
                                <input type="date" class="form-control" name="date_from" value="<?php echo $date_from; ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Date To</label>
                                <input type="date" class="form-control" name="date_to" value="<?php echo $date_to; ?>">
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-filter"></i> Filter
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Membership History Table -->
                <div class="card modern-card">
                    <div class="card-header">
                        <h5><i class="bi bi-clock-history"></i> Membership History</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Plan</th>
                                        <th>Type</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Duration</th>
                                        <th>Price</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($history)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">
                                            <i class="bi bi-person" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                                            No membership records found.
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($history as $h): 
                                        $start = $h['start_date'];
                                        $end = $h['expiration_date'];
                                        $duration_days = !empty($start) && !empty($end) ? daysDifference($start, $end) : 'N/A';
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($h['plan_name']); ?></td>
                                        <td><?php echo ucfirst($h['customer_type']); ?></td>
                                        <td><?php echo formatDate($start); ?></td>
                                        <td><?php echo formatDate($end); ?></td>
                                        <td><?php echo $duration_days; ?> days</td>
                                        <td><?php echo formatCurrency($h['price']); ?></td>
                                        <td>
                                            <span class="badge <?php echo $h['status'] === 'active' ? 'bg-success' : ($h['status'] === 'expired' ? 'bg-danger' : ($h['status'] === 'suspended' ? 'bg-warning' : 'bg-secondary')); ?>">
                                                <?php echo ucfirst($h['status']); ?></span>
                                        </td>
                                        <td>
                                            <?php if (isStaff()): ?>
                                            <a href="edit_plan.php?plan_id=<?php echo $h['plan_id']; ?>" class="btn btn-outline-secondary btn-sm">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="card-footer bg-transparent">
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center mb-0">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&member_id=<?php echo $member_id; ?>&status=<?php echo $status; ?>">Previous</a>
                            </li>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&member_id=<?php echo $member_id; ?>&status=<?php echo $status; ?>"><?php echo $i; ?></a>
                            </li>
                            <?php endfor; ?>
                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&member_id=<?php echo $member_id; ?>&status=<?php echo $status; ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            </div>
            
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>
</body>
</html>