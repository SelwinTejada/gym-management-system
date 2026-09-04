<?php
/**
 * PT Sessions
 * Gym Management System
 * 
 * File: pt/sessions.php
 * Purpose: View and manage PT sessions
 */

// Load configuration
require_once '../config/config.php';

// Require authentication
requireAuth();

// Get filter parameters
$status = isset($_GET['status']) ? sanitize($_GET['status']) : 'all';
$trainer_id = isset($_GET['trainer']) ? (int)$_GET['trainer'] : 0;
$date_from = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : date('Y-m-d');
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Build query
$where_conditions = [];
$params = [];

$where_conditions[] = "ps.session_date BETWEEN ? AND ?";
$params[] = $date_from;
$params[] = $date_to;

if ($status !== 'all') {
    $where_conditions[] = "ps.status = ?";
    $params[] = $status;
}

if ($trainer_id > 0) {
    $where_conditions[] = "ps.trainer_id = ?";
    $params[] = $trainer_id;
}

// If trainer, only show their sessions
if (isTrainer()) {
    $current_trainer_id = getCurrentTrainerId();
    if ($current_trainer_id) {
        $where_conditions[] = "ps.trainer_id = ?";
        $params[] = $current_trainer_id;
    }
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
try {
    $count_sql = "SELECT COUNT(*) as total 
                  FROM pt_sessions ps
                  $where_clause";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total = $stmt->fetch()['total'] ?? 0;
    $total_pages = ceil($total / $limit);
} catch (PDOException $e) {
    error_log('PT sessions count error: ' . $e->getMessage());
    $total = 0;
    $total_pages = 0;
}

// Get sessions
try {
    $sql = "SELECT ps.*, 
            CONCAT(m.first_name, ' ', m.last_name) as member_name,
            CONCAT(pt.first_name, ' ', pt.last_name) as trainer_name,
            m.member_number
            FROM pt_sessions ps
            INNER JOIN members m ON ps.member_id = m.member_id
            INNER JOIN personal_trainers pt ON ps.trainer_id = pt.trainer_id
            $where_clause
            ORDER BY ps.session_date DESC, ps.session_time DESC
            LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $sessions = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('PT sessions list error: ' . $e->getMessage());
    $sessions = [];
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
                <!-- Page Header -->
                <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
                    <h4 class="mb-0"><i class="bi bi-clock-history"></i> PT Sessions</h4>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse">
                            <i class="bi bi-funnel"></i> Filters
                        </button>
                        <?php if (isStaff()): ?>
                        <a href="schedule.php" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Schedule Session
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Filter Bar -->
                <div class="collapse <?php echo ($status !== 'all' || $trainer_id > 0) ? 'show' : ''; ?>" id="filterCollapse">
                    <div class="card modern-card mb-4">
                        <div class="card-body">
                            <form method="GET" action="" class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="status">
                                        <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All</option>
                                        <option value="scheduled" <?php echo $status === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                        <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                        <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                        <option value="no_show" <?php echo $status === 'no_show' ? 'selected' : ''; ?>>No Show</option>
                                    </select>
                                </div>
                                <?php if (!isTrainer()): ?>
                                <div class="col-md-3">
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
                </div>
                
                <!-- Sessions Table -->
                <div class="card modern-card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Member</th>
                                        <th>Trainer</th>
                                        <th>Session #</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($sessions)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">
                                            <i class="bi bi-clock" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                                            No PT sessions found.
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($sessions as $session): ?>
                                        <tr>
                                            <td><?php echo formatDate($session['session_date']); ?></td>
                                            <td><?php echo formatTime($session['session_time']); ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($session['member_name']); ?></strong>
                                                <br><small class="text-muted"><?php echo $session['member_number']; ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($session['trainer_name']); ?></td>
                                            <td>#<?php echo $session['session_number']; ?></td>
                                            <td>
                                                <span class="badge <?php 
                                                    echo $session['status'] === 'completed' ? 'bg-success' : 
                                                         ($session['status'] === 'scheduled' ? 'bg-info' : 
                                                         ($session['status'] === 'cancelled' ? 'bg-danger' : 'bg-warning')); 
                                                ?>">
                                                    <?php echo ucfirst($session['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <?php if ($session['status'] === 'scheduled' && (isStaff() || isTrainer())): ?>
                                                    <button class="btn btn-outline-success" onclick="completeSession(<?php echo $session['session_id']; ?>)">
                                                        <i class="bi bi-check-lg"></i>
                                                    </button>
                                                    <button class="btn btn-outline-danger" onclick="cancelSession(<?php echo $session['session_id']; ?>)">
                                                        <i class="bi bi-x-lg"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                    <?php if (!empty($session['notes'])): ?>
                                                    <button class="btn btn-outline-info" title="View Notes" onclick="showNotes('<?php echo addslashes($session['notes']); ?>')">
                                                        <i class="bi bi-chat"></i>
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
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo $status; ?>&trainer=<?php echo $trainer_id; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">Previous</a>
                                    </li>
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $status; ?>&trainer=<?php echo $trainer_id; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>"><?php echo $i; ?></a>
                                    </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo $status; ?>&trainer=<?php echo $trainer_id; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">Next</a>
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
    function completeSession(id) {
        if (confirm('Mark this session as completed?')) {
            window.location.href = 'complete_session.php?id=' + id;
        }
    }
    
    function cancelSession(id) {
        if (confirm('Cancel this session?')) {
            const reason = prompt('Please provide a reason for cancellation:');
            if (reason !== null) {
                window.location.href = 'cancel_session.php?id=' + id + '&reason=' + encodeURIComponent(reason);
            }
        }
    }
    
    function showNotes(notes) {
        alert('Session Notes:\n\n' + notes);
    }
    </script>
</body>
</html>