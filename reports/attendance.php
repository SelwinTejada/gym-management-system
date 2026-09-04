<?php
/**
 * Attendance Report
 * Gym Management System
 * 
 * File: reports/attendance.php
 * Purpose: Generate attendance report
 */

// Load configuration
require_once '../config/config.php';

// Require authentication
requireAuth();

// Check permissions
if (!isStaff()) {
    displayError('You do not have permission to view this report.');
    redirect('../dashboard.php');
}

// Get filter parameters
$date_from = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : date('Y-m-d');
$customer_type = isset($_GET['customer_type']) ? sanitize($_GET['customer_type']) : 'all';
$export = isset($_GET['export']) ? sanitize($_GET['export']) : '';

// Build query
$where_conditions = [];
$params = [];

$where_conditions[] = "a.check_in_date BETWEEN ? AND ?";
$params[] = $date_from;
$params[] = $date_to;

if ($customer_type !== 'all') {
    $where_conditions[] = "a.customer_type = ?";
    $params[] = $customer_type;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get attendance data
try {
    $sql = "SELECT a.*, 
            CASE 
                WHEN a.member_id IS NOT NULL THEN CONCAT(m.first_name, ' ', m.last_name)
                ELSE a.walkin_name
            END as customer_name,
            m.member_number,
            u.full_name as processed_by_name
            FROM attendance a
            LEFT JOIN members m ON a.member_id = m.member_id
            LEFT JOIN users u ON a.processed_by = u.user_id
            $where_clause
            ORDER BY a.check_in_date DESC, a.check_in_time DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $attendance = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Attendance report error: ' . $e->getMessage());
    $attendance = [];
}

// Get summary statistics
try {
    $summary_sql = "SELECT 
                        COUNT(*) as total_checkins,
                        SUM(CASE WHEN customer_type = 'student' THEN 1 ELSE 0 END) as student_checkins,
                        SUM(CASE WHEN customer_type = 'regular' THEN 1 ELSE 0 END) as regular_checkins,
                        COUNT(DISTINCT member_id) as unique_members
                    FROM attendance a
                    $where_clause";
    $stmt = $pdo->prepare($summary_sql);
    $stmt->execute($params);
    $summary = $stmt->fetch();
} catch (PDOException $e) {
    error_log('Summary error: ' . $e->getMessage());
    $summary = ['total_checkins' => 0, 'student_checkins' => 0, 'regular_checkins' => 0, 'unique_members' => 0];
}

// Handle CSV export
if ($export === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="attendance_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Date', 'Time', 'Customer', 'Type', 'Member ID', 'Processed By']);
    
    foreach ($attendance as $row) {
        fputcsv($output, [
            $row['check_in_date'],
            $row['check_in_time'],
            $row['customer_name'] ?? 'N/A',
            $row['customer_type'],
            $row['member_number'] ?? 'Walk-in',
            $row['processed_by_name'] ?? 'Unknown'
        ]);
    }
    
    fclose($output);
    exit;
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
                    <h4 class="mb-0"><i class="bi bi-clock-history"></i> Attendance Report</h4>
                    <div class="d-flex gap-2">
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" class="btn btn-outline-success">
                            <i class="bi bi-file-earmark-spreadsheet"></i> Export CSV
                        </a>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Back
                        </a>
                    </div>
                </div>
                
                <!-- Summary Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon checkins">
                                <i class="bi bi-people"></i>
                            </div>
                            <div class="stat-content">
                                <h5>Total Check-ins</h5>
                                <h2><?php echo number_format($summary['total_checkins'] ?? 0); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: rgba(23, 162, 184, 0.1); color: #17a2b8;">
                                <i class="bi bi-mortarboard"></i>
                            </div>
                            <div class="stat-content">
                                <h5>Students</h5>
                                <h2><?php echo number_format($summary['student_checkins'] ?? 0); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: rgba(108, 117, 125, 0.1); color: #6c757d;">
                                <i class="bi bi-person"></i>
                            </div>
                            <div class="stat-content">
                                <h5>Regular</h5>
                                <h2><?php echo number_format($summary['regular_checkins'] ?? 0); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: rgba(40, 167, 69, 0.1); color: #28a745;">
                                <i class="bi bi-person-arms-up"></i>
                            </div>
                            <div class="stat-content">
                                <h5>Unique Members</h5>
                                <h2><?php echo number_format($summary['unique_members'] ?? 0); ?></h2>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="card modern-card mb-4">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Date From</label>
                                <input type="date" class="form-control" name="date_from" value="<?php echo $date_from; ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Date To</label>
                                <input type="date" class="form-control" name="date_to" value="<?php echo $date_to; ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Customer Type</label>
                                <select class="form-select" name="customer_type">
                                    <option value="all" <?php echo $customer_type === 'all' ? 'selected' : ''; ?>>All</option>
                                    <option value="student" <?php echo $customer_type === 'student' ? 'selected' : ''; ?>>Student</option>
                                    <option value="regular" <?php echo $customer_type === 'regular' ? 'selected' : ''; ?>>Regular</option>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search"></i> Generate Report
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Data Table -->
                <div class="card modern-card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Customer</th>
                                        <th>Type</th>
                                        <th>Member ID</th>
                                        <th>Processed By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($attendance)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">
                                            <i class="bi bi-clock" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                                            No attendance records found for the selected period.
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($attendance as $row): ?>
                                        <tr>
                                            <td><?php echo formatDate($row['check_in_date']); ?></td>
                                            <td><?php echo formatTime($row['check_in_time']); ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($row['customer_name'] ?? 'N/A'); ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $row['customer_type'] === 'student' ? 'bg-info' : 'bg-secondary'; ?>">
                                                    <?php echo ucfirst($row['customer_type']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($row['member_number'] ?? 'Walk-in'); ?></td>
                                            <td><?php echo htmlspecialchars($row['processed_by_name'] ?? 'Unknown'); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>
</body>
</html>