<?php
/**
 * Trainers List
 * Gym Management System
 * 
 * File: trainers/index.php
 * Purpose: Display and manage personal trainers
 */

// Load configuration
require_once '../config/config.php';

// Require authentication
requireAuth();

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
    $where_conditions[] = "(first_name LIKE ? OR last_name LIKE ? OR contact_number LIKE ? OR email LIKE ?)";
    $search_param = '%' . $search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($status !== 'all') {
    $where_conditions[] = "status = ?";
    $params[] = $status;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
try {
    $count_sql = "SELECT COUNT(*) as total FROM personal_trainers $where_clause";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total = $stmt->fetch()['total'] ?? 0;
    $total_pages = ceil($total / $limit);
} catch (PDOException $e) {
    error_log('Trainer count error: ' . $e->getMessage());
    $total = 0;
    $total_pages = 0;
}

// Get trainers
try {
    $sql = "SELECT pt.*, u.username as user_username
            FROM personal_trainers pt
            LEFT JOIN users u ON pt.user_id = u.user_id
            $where_clause
            ORDER BY pt.status DESC, pt.last_name ASC
            LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $trainers = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Trainer list error: ' . $e->getMessage());
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
                <!-- Page Header -->
                <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
                    <h4 class="mb-0"><i class="bi bi-person-badge"></i> Personal Trainers</h4>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#searchCollapse">
                            <i class="bi bi-search"></i> Search
                        </button>
                        <?php if (isOwner()): ?>
                        <a href="add.php" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Add Trainer
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
                                           placeholder="Name, Contact, or Email" value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="status">
                                        <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All</option>
                                        <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
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
                
                <!-- Trainers Grid -->
                <div class="row">
                    <?php if (empty($trainers)): ?>
                    <div class="col-12">
                        <div class="card modern-card">
                            <div class="card-body text-center py-5">
                                <i class="bi bi-person-badge" style="font-size: 48px; color: #ccc; display: block; margin-bottom: 16px;"></i>
                                <h5>No trainers found</h5>
                                <p class="text-muted"><?php echo !empty($search) ? 'Try adjusting your search criteria.' : 'Get started by adding your first personal trainer.'; ?></p>
                                <?php if (isOwner()): ?>
                                <a href="add.php" class="btn btn-primary">Add Trainer</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                        <?php foreach ($trainers as $trainer): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card modern-card trainer-card">
                                <div class="card-body text-center">
                                    <div class="trainer-avatar">
                                        <?php if (!empty($trainer['profile_image'])): ?>
                                        <img src="<?php echo UPLOAD_URL . 'trainer_photos/' . $trainer['profile_image']; ?>" 
                                             alt="<?php echo htmlspecialchars($trainer['first_name']); ?>" 
                                             class="rounded-circle" style="width: 80px; height: 80px; object-fit: cover;">
                                        <?php else: ?>
                                        <i class="bi bi-person-circle" style="font-size: 64px; color: #ccc;"></i>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <h5 class="mt-3 mb-1">
                                        <?php echo htmlspecialchars($trainer['first_name'] . ' ' . $trainer['last_name']); ?>
                                    </h5>
                                    
                                    <?php if (!empty($trainer['specialization'])): ?>
                                    <p class="text-muted small"><?php echo htmlspecialchars($trainer['specialization']); ?></p>
                                    <?php endif; ?>
                                    
                                    <div class="mb-2">
                                        <span class="badge <?php echo $trainer['status'] === 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                            <?php echo ucfirst($trainer['status']); ?>
                                        </span>
                                    </div>
                                    
                                    <div class="trainer-info small text-muted">
                                        <div><i class="bi bi-telephone"></i> <?php echo htmlspecialchars($trainer['contact_number']); ?></div>
                                        <?php if (!empty($trainer['email'])): ?>
                                        <div><i class="bi bi-envelope"></i> <?php echo htmlspecialchars($trainer['email']); ?></div>
                                        <?php endif; ?>
                                        <div><i class="bi bi-currency-dollar"></i> <?php echo formatCurrency($trainer['rate']); ?> / session</div>
                                    </div>
                                    
                                    <hr>
                                    
                                    <div class="d-flex gap-2 justify-content-center">
                                        <a href="view.php?id=<?php echo $trainer['trainer_id']; ?>" class="btn btn-outline-primary btn-sm">
                                            <i class="bi bi-eye"></i> View
                                        </a>
                                        <?php if (isOwner()): ?>
                                        <a href="edit.php?id=<?php echo $trainer['trainer_id']; ?>" class="btn btn-outline-secondary btn-sm">
                                            <i class="bi bi-pencil"></i> Edit
                                        </a>
                                        <button class="btn btn-outline-danger btn-sm" onclick="toggleTrainer(<?php echo $trainer['trainer_id']; ?>, '<?php echo $trainer['status']; ?>')">
                                            <i class="bi bi-<?php echo $trainer['status'] === 'active' ? 'pause' : 'play'; ?>"></i>
                                            <?php echo $trainer['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
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
                <?php endif; ?>
            </div>
            
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>
    
    <script>
    function toggleTrainer(id, currentStatus) {
        const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
        const action = newStatus === 'active' ? 'activate' : 'deactivate';
        
        if (confirm('Are you sure you want to ' + action + ' this trainer?')) {
            window.location.href = 'edit.php?id=' + id + '&status=' + newStatus;
        }
    }
    </script>
    
    <style>
    .trainer-card {
        transition: var(--transition);
    }
    
    .trainer-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-md);
    }
    
    .trainer-info {
        line-height: 1.8;
    }
    
    .trainer-avatar i {
        font-size: 64px;
        color: #ccc;
    }
    </style>
</body>
</html>