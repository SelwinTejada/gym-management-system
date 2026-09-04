<?php
/**
 * View Trainer Profile
 * Gym Management System
 * 
 * File: trainers/view.php
 * Purpose: Display trainer profile
 */

// Load configuration
require_once '../config/config.php';

// Require authentication
requireAuth();

// Get trainer ID
$trainer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($trainer_id <= 0) {
    displayError('Invalid trainer ID.');
    redirect('index.php');
}

// Get trainer details
try {
    $stmt = $pdo->prepare("
        SELECT pt.*, u.username, u.email as user_email
        FROM personal_trainers pt
        LEFT JOIN users u ON pt.user_id = u.user_id
        WHERE pt.trainer_id = ?
    ");
    $stmt->execute([$trainer_id]);
    $trainer = $stmt->fetch();
    
    if (!$trainer) {
        displayError('Trainer not found.');
        redirect('index.php');
    }
} catch (PDOException $e) {
    error_log('Get trainer error: ' . $e->getMessage());
    displayError('An error occurred.');
    redirect('index.php');
}

// Get trainer's PT packages
try {
    $stmt = $pdo->prepare("
        SELECT pp.*, 
               CONCAT(m.first_name, ' ', m.last_name) as member_name,
               m.member_number
        FROM pt_packages pp
        INNER JOIN members m ON pp.member_id = m.member_id
        WHERE pp.trainer_id = ?
        ORDER BY pp.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$trainer_id]);
    $packages = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Get packages error: ' . $e->getMessage());
    $packages = [];
}

// Get trainer's sessions stats
try {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_sessions,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_sessions,
            SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled_sessions,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_sessions
        FROM pt_sessions
        WHERE trainer_id = ?
    ");
    $stmt->execute([$trainer_id]);
    $stats = $stmt->fetch();
} catch (PDOException $e) {
    error_log('Get stats error: ' . $e->getMessage());
    $stats = ['total_sessions' => 0, 'completed_sessions' => 0, 'scheduled_sessions' => 0, 'cancelled_sessions' => 0];
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
                    <h4 class="mb-0"><i class="bi bi-person-badge"></i> Trainer Profile</h4>
                    <div class="d-flex gap-2">
                        <?php if (isOwner()): ?>
                        <a href="edit.php?id=<?php echo $trainer_id; ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-pencil"></i> Edit
                        </a>
                        <?php endif; ?>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Back
                        </a>
                    </div>
                </div>
                
                <!-- Profile Header -->
                <div class="card modern-card mb-4">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="d-flex align-items-start gap-3">
                                    <div class="trainer-avatar">
                                        <?php if (!empty($trainer['profile_image'])): ?>
                                        <img src="<?php echo UPLOAD_URL . 'trainer_photos/' . $trainer['profile_image']; ?>" 
                                             alt="<?php echo htmlspecialchars($trainer['first_name']); ?>" 
                                             style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover;">
                                        <?php else: ?>
                                        <i class="bi bi-person-circle" style="font-size: 64px; color: #ccc;"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <h3 class="mb-1">
                                            <?php echo htmlspecialchars($trainer['first_name'] . ' ' . $trainer['last_name']); ?>
                                        </h3>
                                        <div class="d-flex flex-wrap gap-2 mb-2">
                                            <span class="badge <?php echo $trainer['status'] === 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                                <?php echo ucfirst($trainer['status']); ?>
                                            </span>
                                            <?php if (!empty($trainer['specialization'])): ?>
                                            <span class="badge bg-info"><?php echo htmlspecialchars($trainer['specialization']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-muted small">
                                            <div><i class="bi bi-telephone"></i> <?php echo htmlspecialchars($trainer['contact_number']); ?></div>
                                            <?php if (!empty($trainer['email'])): ?>
                                            <div><i class="bi bi-envelope"></i> <?php echo htmlspecialchars($trainer['email']); ?></div>
                                            <?php endif; ?>
                                            <div><i class="bi bi-currency-dollar"></i> Rate: <?php echo formatCurrency($trainer['rate']); ?> / session</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 text-md-end">
                                <div class="stats-mini">
                                    <div class="stat-mini-item">
                                        <div class="stat-mini-label">Total Sessions</div>
                                        <div class="stat-mini-value"><?php echo number_format($stats['total_sessions'] ?? 0); ?></div>
                                    </div>
                                    <div class="stat-mini-item">
                                        <div class="stat-mini-label">Completed</div>
                                        <div class="stat-mini-value text-success"><?php echo number_format($stats['completed_sessions'] ?? 0); ?></div>
                                    </div>
                                    <div class="stat-mini-item">
                                        <div class="stat-mini-label">Scheduled</div>
                                        <div class="stat-mini-value text-info"><?php echo number_format($stats['scheduled_sessions'] ?? 0); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Notes -->
                <?php if (!empty($trainer['notes'])): ?>
                <div class="card modern-card mb-4">
                    <div class="card-header">
                        <h5>Notes</h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($trainer['notes'])); ?></p>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- PT Packages -->
                <div class="card modern-card">
                    <div class="card-header">
                        <h5>PT Packages</h5>
                        <?php if (isStaff()): ?>
                        <a href="../pt/create_package.php?trainer_id=<?php echo $trainer_id; ?>" class="btn btn-primary btn-sm">
                            <i class="bi bi-plus-circle"></i> Create Package
                        </a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($packages)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-box" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                            No PT packages assigned to this trainer.
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Member</th>
                                        <th>Sessions</th>
                                        <th>Used</th>
                                        <th>Remaining</th>
                                        <th>Price</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($packages as $pkg): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($pkg['member_name']); ?></strong>
                                            <br><small class="text-muted"><?php echo $pkg['member_number']; ?></small>
                                        </td>
                                        <td><?php echo $pkg['total_sessions']; ?></td>
                                        <td><?php echo $pkg['used_sessions']; ?></td>
                                        <td><?php echo $pkg['remaining_sessions']; ?></td>
                                        <td><?php echo formatCurrency($pkg['price']); ?></td>
                                        <td>
                                            <span class="badge <?php echo $pkg['status'] === 'active' ? 'bg-success' : ($pkg['status'] === 'completed' ? 'bg-info' : 'bg-warning'); ?>">
                                                <?php echo ucfirst($pkg['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="../pt/view_package.php?id=<?php echo $pkg['package_id']; ?>" class="btn btn-outline-primary btn-sm">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>
    
    <style>
    .stats-mini {
        display: flex;
        gap: 20px;
        justify-content: flex-end;
    }
    
    .stat-mini-item {
        text-align: center;
    }
    
    .stat-mini-label {
        font-size: 12px;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .stat-mini-value {
        font-size: 20px;
        font-weight: 700;
    }
    </style>
</body>
</html>