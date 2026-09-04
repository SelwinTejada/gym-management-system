<?php
/**
 * Member List
 * Gym Management System
 * 
 * File: members/index.php
 * Purpose: Display and manage all members
 */

// Load configuration
require_once '../config/config.php';

// Require authentication
requireAuth();

// Check permissions
if (!isStaff()) {
    displayError('You do not have permission to view members.');
    redirect('../dashboard.php');
}

// Get search parameters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$status = isset($_GET['status']) ? sanitize($_GET['status']) : 'all';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Build query
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(m.first_name LIKE ? OR m.last_name LIKE ? OR m.member_number LIKE ? OR m.contact_number LIKE ?)";
    $search_param = '%' . $search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($status !== 'all') {
    $where_conditions[] = "m.status = ?";
    $params[] = $status;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
try {
    $count_sql = "SELECT COUNT(DISTINCT m.member_id) as total 
                  FROM members m 
                  LEFT JOIN member_memberships mm ON m.member_id = mm.member_id AND mm.status = 'active'
                  $where_clause";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total = $stmt->fetch()['total'] ?? 0;
    $total_pages = ceil($total / $limit);
} catch (PDOException $e) {
    error_log('Member count error: ' . $e->getMessage());
    $total = 0;
    $total_pages = 0;
}

// Get members
try {
    $sql = "SELECT m.*, 
            mm.membership_id, mm.plan_id, mm.start_date, mm.expiration_date, mm.status as membership_status,
            mp.plan_name,
            (SELECT COUNT(*) FROM attendance a WHERE a.member_id = m.member_id) as total_visits
            FROM members m
            LEFT JOIN member_memberships mm ON m.member_id = mm.member_id AND mm.status = 'active'
            LEFT JOIN membership_plans mp ON mm.plan_id = mp.plan_id
            $where_clause
            ORDER BY m.created_at DESC
            LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $members = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Member list error: ' . $e->getMessage());
    $members = [];
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
                <!-- Page Header -->
                <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
                    <h4 class="mb-0">Members</h4>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#searchCollapse">
                            <i class="bi bi-search"></i> Search
                        </button>
                        <?php if (isStaff()): ?>
                        <a href="add.php" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Add Member
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Search Bar -->
                <div class="collapse <?php echo !empty($search) ? 'show' : ''; ?>" id="searchCollapse">
                    <div class="card modern-card mb-4">
                        <div class="card-body">
                            <form method="GET" action="" class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Search</label>
                                    <input type="text" class="form-control" name="search" 
                                           placeholder="Name, ID, or Contact" value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="status">
                                        <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All</option>
                                        <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="suspended" <?php echo $status === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                        <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bi bi-search"></i> Search
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Member Table -->
                <div class="card modern-card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Member</th>
                                        <th>ID</th>
                                        <th>Type</th>
                                        <th>Plan</th>
                                        <th>Expires</th>
                                        <th>Visits</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($members)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">
                                            <i class="bi bi-people" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                                            No members found.
                                            <?php if (!empty($search)): ?>
                                            <br>Try adjusting your search criteria.
                                            <?php else: ?>
                                            <br><a href="add.php" class="btn btn-primary btn-sm mt-2">Add Your First Member</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($members as $member): ?>
                                        <?php 
                                        $membership_status = $member['membership_status'] ?? 'no_membership';
                                        $is_expired = !empty($member['expiration_date']) && isDatePast($member['expiration_date']);
                                        $expiring_soon = !empty($member['expiration_date']) && !$is_expired && daysDifference(date('Y-m-d'), $member['expiration_date']) <= 7;
                                        ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></strong>
                                                <?php if (!empty($member['suffix'])): ?>
                                                <small class="text-muted"><?php echo htmlspecialchars($member['suffix']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><code><?php echo htmlspecialchars($member['member_number']); ?></code></td>
                                            <td>
                                                <span class="badge <?php echo $member['customer_type'] === 'student' ? 'bg-info' : 'bg-secondary'; ?>">
                                                    <?php echo ucfirst($member['customer_type']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($member['plan_name'] ?? 'No Plan'); ?></td>
                                            <td>
                                                <?php if (!empty($member['expiration_date'])): ?>
                                                    <?php if ($is_expired): ?>
                                                        <span class="text-danger">Expired</span>
                                                        <br><small><?php echo formatDate($member['expiration_date']); ?></small>
                                                    <?php elseif ($expiring_soon): ?>
                                                        <span class="text-warning"><?php echo formatDate($member['expiration_date']); ?></span>
                                                        <br><small class="text-warning">(<?php echo daysDifference(date('Y-m-d'), $member['expiration_date']); ?> days)</small>
                                                    <?php else: ?>
                                                        <?php echo formatDate($member['expiration_date']); ?>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo number_format($member['total_visits'] ?? 0); ?></td>
                                            <td>
                                                <?php if ($membership_status === 'active' && !$is_expired): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php elseif ($membership_status === 'active' && $is_expired): ?>
                                                    <span class="badge bg-danger">Expired</span>
                                                <?php elseif ($membership_status === 'active' && $expiring_soon): ?>
                                                    <span class="badge bg-warning text-dark">Expiring Soon</span>
                                                <?php elseif ($membership_status === 'suspended'): ?>
                                                    <span class="badge bg-warning text-dark">Suspended</span>
                                                <?php elseif ($membership_status === 'cancelled'): ?>
                                                    <span class="badge bg-secondary">Cancelled</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">No Membership</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="view.php?id=<?php echo $member['member_id']; ?>" class="btn btn-outline-primary" title="View">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <?php if (isStaff()): ?>
                                                    <a href="edit.php?id=<?php echo $member['member_id']; ?>" class="btn btn-outline-secondary" title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <?php endif; ?>
                                                    <?php if (isOwner()): ?>
                                                    <button class="btn btn-outline-danger" title="Delete" onclick="deleteMember(<?php echo $member['member_id']; ?>)">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
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
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>">Previous</a>
                                    </li>
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>"><?php echo $i; ?></a>
                                    </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>">Next</a>
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
    
    <script>
    function deleteMember(id) {
        if (confirm('Are you sure you want to delete this member? This action cannot be undone.')) {
            window.location.href = 'delete.php?id=' + id + '&confirm=1';
        }
    }
    </script>
</body>
</html>