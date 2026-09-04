<?php
/**
 * Report Generation
 * Gym Management System
 * 
 * File: reports/generate.php
 * Purpose: Generate and export reports
 */

// Load configuration
require_once '../config/config.php';

// Require authentication
requireAuth();

// Check permissions - owner can generate all reports, staff can generate basic
if (!isOwner() && !isStaff()) {
    displayError('You do not have permission to generate reports.');
    redirect('../dashboard.php');
}

$report_type = isset($_GET['type']) ? sanitize($_GET['type']) : 'summary';
$date_from = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : date('Y-m-d');

// Initialize report data
$report_data = [];

try {
    switch ($report_type) {
        case 'attendance':
            // Get attendance data for report
            $stmt = $pdo->prepare("
                SELECT a.*, 
                       CASE 
                           WHEN a.member_id IS NOT NULL THEN CONCAT(m.first_name, ' ', m.last_name)
                           ELSE a.walkin_name
                       END as customer_name,
                       m.member_number,
                       m.customer_type
                FROM attendance a
                LEFT JOIN members m ON a.member_id = m.member_id
                WHERE a.check_in_date BETWEEN ? AND ?
                ORDER BY a.check_in_date DESC, a.check_in_time DESC
            ");
            $stmt->execute([$date_from, $date_to]);
            $report_data['attendance'] = $stmt->fetchAll();
            break;
            
        case 'revenue':
            // Get revenue data
            $stmt = $pdo->prepare("
                SELECT p.*, 
                       CONCAT(m.first_name, ' ', m.last_name) as member_name,
                       m.member_number
                FROM payments p
                LEFT JOIN members m ON p.member_id = m.member_id
                WHERE p.payment_date BETWEEN ? AND ? AND p.status = 'completed'
                ORDER BY p.payment_date DESC, p.payment_time DESC
            ");
            $stmt->execute([$date_from, $date_to]);
            $report_data['revenue'] = $stmt->fetchAll();
            break;
            
        case 'expenses':
            // Get expenses data
            $stmt = $pdo->prepare("SELECT * FROM expenses WHERE expense_date BETWEEN ? AND ? ORDER BY expense_date DESC, expense_time DESC");
            $stmt->execute([$date_from, $date_to]);
            $report_data['expenses'] = $stmt->fetchAll();
            break;
            
        case 'profit-loss':
            // Get both revenue and expenses
            $rev_stmt = $pdo->prepare("SELECT SUM(total) as total FROM payments WHERE payment_date BETWEEN ? AND ? AND status = 'completed'");
            $rev_stmt->execute([$date_from, $date_to]);
            $report_data['monthly_revenue'] = $rev_stmt->fetch()['total'] ?? 0;
            
            $exp_stmt = $pdo->prepare("SELECT SUM(amount) as total FROM expenses WHERE expense_date BETWEEN ? AND ?");
            $exp_stmt->execute([$date_from, $date_to]);
            $report_data['monthly_expenses'] = $exp_stmt->fetch()['total'] ?? 0;
            $report_data['monthly_profit'] = $report_data['monthly_revenue'] - $report_data['monthly_expenses'];
            break;
            
        case 'membership':
            // Get membership data
            $stmt = $pdo->prepare("
                SELECT m.*, mp.plan_name, mm.status as membership_status, mm.expiration_date
                FROM members m
                INNER JOIN member_memberships mm ON m.member_id = mm.member_id
                INNER JOIN membership_plans mp ON mm.plan_id = mp.plan_id
                WHERE mm.expiration_date IS NOT NULL
                ORDER BY mm.expiration_date DESC
            ");
            $stmt->execute();
            $report_data['memberships'] = $stmt->fetchAll();
            break;
            
        case 'trainers':
            // Get trainer data
            $stmt = $pdo->prepare("
                SELECT u.*, CONCAT(u.first_name, ' ', u.last_name) as full_name,
                       COUNT(ps.session_id) as total_sessions,
                       COALESCE(SUM(ps.amount), 0) as total_revenue
                FROM users u
                LEFT JOIN pt_sessions ps ON u.user_id = ps.trainer_id
                WHERE ps.session_date BETWEEN ? AND ?
                GROUP BY u.user_id
                ORDER BY total_revenue DESC
            ");
            $stmt->execute([$date_from, $date_to]);
            $report_data['trainers'] = $stmt->fetchAll();
            break;
            
        default:
            // Summary report
            $rev_stmt = $pdo->prepare("SELECT SUM(total) as total FROM payments WHERE payment_date BETWEEN ? AND ? AND status = 'completed'");
            $rev_stmt->execute([$date_from, $date_to]);
            $report_data['revenue'] = $rev_stmt->fetch()['total'] ?? 0;
            
            $exp_stmt = $pdo->prepare("SELECT SUM(amount) as total FROM expenses WHERE expense_date BETWEEN ? AND ?");
            $exp_stmt->execute([$date_from, $date_to]);
            $report_data['expenses'] = $exp_stmt->fetch()['total'] ?? 0;
            $report_data['profit'] = $report_data['revenue'] - $report_data['expenses'];
            break;
    }
    
    $report_data['date_from'] = $date_from;
    $report_data['date_to'] = $date_to;
    $report_data['report_type'] = $report_type;
    $report_data['generated_at'] = date('Y-m-d H:i:s');
    
} catch (PDOException $e) {
    error_log('Report generation error: ' . $e->getMessage());
    displayError('Error generating report: ' . $e->getMessage());
    $report_data = [];
}

// Set content type based on report
$export_format = isset($_GET['format']) ? sanitize($_GET['format']) : 'html';

if ($export_format === 'csv' && !empty($report_data)) {
    // Generate CSV
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename=' . $report_type . '_' . $date_from . '_to_' . $date_to . '.csv');
    $output = fopen('php://output', 'w');
    
    switch ($report_type) {
        case 'attendance':
            fputcsv($output, ['Date', 'Time', 'Customer Name', 'Type', 'Member Number']);
            if (isset($report_data['attendance'])) {
                foreach ($report_data['attendance'] as $row) {
                    fputcsv($output, [
                        formatDate($row['check_in_date']),
                        formatTime($row['check_in_time']),
                        $row['customer_name'] ?? 'Walk-in',
                        ucfirst($row['customer_type'] ?? 'regular'),
                        $row['member_number'] ?? ''
                    ]);
                }
            }
            break;
            
        case 'revenue':
            fputcsv($output, ['Date', 'Amount', 'Member', 'Payment Method', 'Status']);
            if (isset($report_data['revenue'])) {
                foreach ($report_data['revenue'] as $row) {
                    fputcsv($output, [
                        formatDate($row['payment_date']),
                        formatCurrency($row['total']),
                        $row['member_name'] ?? 'N/A',
                        $row['payment_method'] ?? 'N/A',
                        $row['status'] ?? 'N/A'
                    ]);
                }
            }
            break;
            
        case 'expenses':
            fputcsv($output, ['Date', 'Type', 'Category', 'Description', 'Amount', 'Payment Method']);
            if (isset($report_data['expenses'])) {
                foreach ($report_data['expenses'] as $row) {
                    fputcsv($output, [
                        formatDate($row['expense_date']),
                        ucfirst($row['type'] ?? 'other'),
                        $row['category'] ?? 'N/A',
                        $row['description'] ?? 'N/A',
                        formatCurrency($row['amount']),
                        ucfirst($row['payment_method'] ?? 'cash')
                    ]);
                }
            }
            break;
            
        case 'profit-loss':
            fputcsv($output, ['Period', 'Revenue', 'Expenses', 'Profit']);
            fputcsv($output, [
                $date_from . ' - ' . $date_to,
                formatCurrency($report_data['monthly_revenue'] ?? 0),
                formatCurrency($report_data['monthly_expenses'] ?? 0),
                formatCurrency($report_data['monthly_profit'] ?? 0)
            ]);
            break;
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
                <h4 class="mb-4"><i class="bi bi-file-earmark-text"></i> Report Generation</h4>
                
                <div class="card modern-card mb-4">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Report Type</label>
                                <select class="form-control" name="type" onchange="this.form.submit()">
                                    <option value="summary" <?php echo ($report_type === 'summary') ? 'selected' : ''; ?>>
                                        Summary
                                    </option>
                                    <option value="attendance" <?php echo ($report_type === 'attendance') ? 'selected' : ''; ?>>
                                        Attendance
                                    </option>
                                    <option value="revenue" <?php echo ($report_type === 'revenue') ? 'selected' : ''; ?>>
                                        Revenue
                                    </option>
                                    <option value="expenses" <?php echo ($report_type === 'expenses') ? 'selected' : ''; ?>>
                                        Expenses
                                    </option>
                                    <option value="profit-loss" <?php echo ($report_type === 'profit-loss') ? 'selected' : ''; ?>>
                                        Profit & Loss
                                    </option>
                                    <option value="membership" <?php echo ($report_type === 'membership') ? 'selected' : ''; ?>>
                                        Membership
                                    </option>
                                    <option value="trainers" <?php echo ($report_type === 'trainers') ? 'selected' : ''; ?>>
                                        Trainer
                                    </option>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Date From</label>
                                <input type="date" class="form-control" name="date_from" value="<?php echo $date_from; ?>">
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Date To</label>
                                <input type="date" class="form-control" name="date_to" value="<?php echo $date_to; ?>">
                            </div>
                            
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-filter"></i> Generate Report
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="exportReport('<?php echo $report_type; ?>')">
                                    <i class="bi bi-download"></i> Export CSV
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <?php if (!empty($report_data)): ?>
                <div class="card modern-card">
                    <div class="card-header">
                        <h5><i class="bi bi-bar-chart"></i> Report Data</h5>
                    </div>
                    <div class="card-body">
                        <!-- Summary Section -->
                        <?php if (isset($report_data['revenue']) && isset($report_data['expenses'])): ?>
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="summary-item">
                                    <small class="text-muted">Revenue</small>
                                    <h4 class="text-success"><?php echo formatCurrency($report_data['revenue']); ?></h4>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="summary-item">
                                    <small class="text-muted">Expenses</small>
                                    <h4 class="text-danger"><?php echo formatCurrency($report_data['expenses']); ?></h4>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="summary-item">
                                    <small class="text-muted">Profit</small>
                                    <h4 class="<?php echo $report_data['profit'] >= 0 ? 'text-primary' : 'text-danger'; ?>">
                                        <?php echo formatCurrency($report_data['profit']); ?>
                                    </h4>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Data Tables -->
                        <?php 
                        switch ($report_type):
                            case 'attendance': 
                        ?>
                                <h5>Attendance Records (<?php echo count($report_data['attendance'] ?? []); ?> records)</h5>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Time</th>
                                                <th>Customer</th>
                                                <th>Type</th>
                                                <th>Member</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($report_data['attendance'] as $row): ?>
                                            <tr>
                                                <td><?php echo formatDate($row['check_in_date']); ?></td>
                                                <td><?php echo formatTime($row['check_in_time']); ?></td>
                                                <td><?php echo htmlspecialchars($row['customer_name'] ?? 'Walk-in'); ?></td>
                                                <td><span class="badge <?php echo $row['customer_type'] === 'student' ? 'bg-info' : 'bg-secondary'; ?>"><?php echo ucfirst($row['customer_type']); ?></span></td>
                                                <td><?php echo htmlspecialchars($row['member_number'] ?? ''); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php break; ?>
                            
                            <?php case 'revenue': ?>
                                <h5>Revenue Records (<?php echo count($report_data['revenue'] ?? []); ?> records)</h5>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Amount</th>
                                                <th>Member</th>
                                                <th>Method</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($report_data['revenue'] as $row): ?>
                                            <tr>
                                                <td><?php echo formatDate($row['payment_date']); ?></td>
                                                <td><?php echo formatCurrency($row['total']); ?></td>
                                                <td><?php echo htmlspecialchars($row['member_name'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($row['payment_method'] ?? 'N/A'); ?></td>
                                                <td><span class="badge bg-success"><?php echo $row['status'] ?? 'N/A'; ?></span></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php break; ?>
                            
                            <?php case 'expenses': ?>
                                <h5>Expense Records (<?php echo count($report_data['expenses'] ?? []); ?> records)</h5>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Type</th>
                                                <th>Category</th>
                                                <th>Description</th>
                                                <th>Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($report_data['expenses'] as $row): ?>
                                            <tr>
                                                <td><?php echo formatDate($row['expense_date']); ?></td>
                                                <td><?php echo ucfirst($row['type'] ?? 'other'); ?></td>
                                                <td><?php echo htmlspecialchars($row['category'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($row['description'] ?? 'N/A'); ?></td>
                                                <td><?php echo formatCurrency($row['amount']); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php break; ?>
                            
                            <?php case 'profit-loss': ?>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="summary-item">
                                            <small class="text-muted">Total Revenue</small>
                                            <h4 class="text-success"><?php echo formatCurrency($report_data['monthly_revenue'] ?? 0); ?></h4>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="summary-item">
                                            <small class="text-muted">Total Expenses</small>
                                            <h4 class="text-danger"><?php echo formatCurrency($report_data['monthly_expenses'] ?? 0); ?></h4>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="summary-item">
                                            <small class="text-muted">Net Profit</small>
                                            <h4 class="<?php echo ($report_data['monthly_profit'] ?? 0) >= 0 ? 'text-primary' : 'text-danger'; ?>">
                                                <?php echo formatCurrency($report_data['monthly_profit'] ?? 0); ?>
                                            </h4>
                                        </div>
                                    </div>
                                </div>
                                <?php break; ?>
                            
                            <?php case 'membership': ?>
                                <h5>Membership Records (<?php echo count($report_data['memberships'] ?? []); ?> records)</h5>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Plan</th>
                                                <th>Status</th>
                                                <th>Expiration</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($report_data['memberships'] as $row): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                                <td><?php echo htmlspecialchars($row['plan_name']); ?></td>
                                                <td><span class="badge <?php echo $row['membership_status'] === 'active' ? 'bg-success' : 'bg-warning'; ?>"><?php echo ucfirst($row['membership_status']); ?></span></td>
                                                <td><?php echo formatDate($row['expiration_date']); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php break; ?>
                            
                            <?php case 'trainers': ?>
                                <h5>Trainer Performance (<?php echo count($report_data['trainers'] ?? []); ?> trainers)</h5>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Trainer</th>
                                                <th>Sessions</th>
                                                <th>Revenue</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($report_data['trainers'] as $row): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($row['full_name'] ?? $row['first_name'] . ' ' . $row['last_name']); ?></td>
                                                <td><?php echo $row['total_sessions'] ?? 0; ?></td>
                                                <td><?php echo formatCurrency($row['total_revenue'] ?? 0); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php break; ?>
                            
                            <?php default: ?>
                                <!-- Summary Report Display -->
                                <p class="text-muted">Summary report generated for period <?php echo formatDate($date_from); ?> to <?php echo formatDate($date_to); ?></p>
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i> Use the filter above to select a specific report type.
                                </div>
                        <?php endswitch; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>
    
    <script>
    function exportReport(reportType) {
        var dateFrom = document.querySelector('input[name="date_from"]').value;
        var dateTo = document.querySelector('input[name="date_to"]').value;
        window.location.href = 'generate.php?type=' + reportType + '&date_from=' + dateFrom + '&date_to=' + dateTo + '&format=csv';
    }
    </script>
</body>
</html>