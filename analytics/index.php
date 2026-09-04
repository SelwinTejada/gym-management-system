<?php
/**
 * Analytics Dashboard
 * Gym Management System
 * 
 * File: analytics/index.php
 * Purpose: Visual analytics and charts
 */

// Load configuration
require_once '../config/config.php';

// Require authentication
requireAuth();

// Check permissions
if (!isOwner()) {
    displayError('You do not have permission to view analytics.');
    redirect('../dashboard.php');
}

// Get data for charts
$current_year = date('Y');
$current_month = date('m');

// Monthly revenue data for current year
try {
    $stmt = $pdo->prepare("
        SELECT 
            MONTH(payment_date) as month,
            SUM(total) as revenue
        FROM payments 
        WHERE YEAR(payment_date) = ? AND status = 'completed'
        GROUP BY MONTH(payment_date)
        ORDER BY month
    ");
    $stmt->execute([$current_year]);
    $monthly_revenue = $stmt->fetchAll();
    
    // Fill in missing months
    $revenue_data = array_fill(1, 12, 0);
    foreach ($monthly_revenue as $row) {
        $revenue_data[$row['month']] = (float)$row['revenue'];
    }
} catch (PDOException $e) {
    error_log('Monthly revenue error: ' . $e->getMessage());
    $revenue_data = array_fill(1, 12, 0);
}

// Monthly expenses data for current year
try {
    $stmt = $pdo->prepare("
        SELECT 
            MONTH(expense_date) as month,
            SUM(amount) as expenses
        FROM expenses 
        WHERE YEAR(expense_date) = ?
        GROUP BY MONTH(expense_date)
        ORDER BY month
    ");
    $stmt->execute([$current_year]);
    $monthly_expenses = $stmt->fetchAll();
    
    $expense_data = array_fill(1, 12, 0);
    foreach ($monthly_expenses as $row) {
        $expense_data[$row['month']] = (float)$row['expenses'];
    }
} catch (PDOException $e) {
    error_log('Monthly expenses error: ' . $e->getMessage());
    $expense_data = array_fill(1, 12, 0);
}

// Revenue by type
try {
    $stmt = $pdo->query("
        SELECT 
            transaction_type,
            SUM(total) as total
        FROM payments 
        WHERE status = 'completed'
        GROUP BY transaction_type
    ");
    $revenue_by_type = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Revenue by type error: ' . $e->getMessage());
    $revenue_by_type = [];
}

// Attendance trend (last 30 days)
try {
    $stmt = $pdo->prepare("
        SELECT 
            check_in_date as date,
            COUNT(*) as checkins
        FROM attendance 
        WHERE check_in_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY check_in_date
        ORDER BY check_in_date
    ");
    $stmt->execute();
    $attendance_trend = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Attendance trend error: ' . $e->getMessage());
    $attendance_trend = [];
}

// Member growth
try {
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as new_members
        FROM members 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month
    ");
    $stmt->execute();
    $member_growth = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Member growth error: ' . $e->getMessage());
    $member_growth = [];
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
                <h4 class="mb-4"><i class="bi bi-graph-up-arrow"></i> Analytics Dashboard</h4>
                
                <!-- Revenue vs Expenses Chart -->
                <div class="card modern-card mb-4">
                    <div class="card-header">
                        <h5>Monthly Revenue vs Expenses - <?php echo $current_year; ?></h5>
                    </div>
                    <div class="card-body">
                        <canvas id="revenueExpenseChart" height="300"></canvas>
                    </div>
                </div>
                
                <div class="row">
                    <!-- Revenue by Type -->
                    <div class="col-md-6">
                        <div class="card modern-card">
                            <div class="card-header">
                                <h5>Revenue by Type</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="revenueTypeChart" height="250"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Attendance Trend -->
                    <div class="col-md-6">
                        <div class="card modern-card">
                            <div class="card-header">
                                <h5>Attendance Trend (Last 30 Days)</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="attendanceChart" height="250"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Member Growth -->
                <div class="card modern-card mt-4">
                    <div class="card-header">
                        <h5>Member Growth (Last 6 Months)</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="memberGrowthChart" height="200"></canvas>
                    </div>
                </div>
            </div>
            
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Revenue vs Expenses Chart
        const revenueExpenseCtx = document.getElementById('revenueExpenseChart').getContext('2d');
        const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const revenueData = <?php echo json_encode(array_values($revenue_data)); ?>;
        const expenseData = <?php echo json_encode(array_values($expense_data)); ?>;
        
        new Chart(revenueExpenseCtx, {
            type: 'bar',
            data: {
                labels: monthNames,
                datasets: [
                    {
                        label: 'Revenue',
                        data: revenueData,
                        backgroundColor: 'rgba(40, 167, 69, 0.7)',
                        borderColor: 'rgba(40, 167, 69, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Expenses',
                        data: expenseData,
                        backgroundColor: 'rgba(220, 53, 69, 0.7)',
                        borderColor: 'rgba(220, 53, 69, 1)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₱' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
        
        // Revenue by Type Chart
        const revenueTypeCtx = document.getElementById('revenueTypeChart').getContext('2d');
        const revenueTypes = <?php echo json_encode($revenue_by_type); ?>;
        const typeLabels = revenueTypes.map(r => r.transaction_type.replace('_', ' ').toUpperCase());
        const typeValues = revenueTypes.map(r => parseFloat(r.total));
        const colors = ['#28a745', '#17a2b8', '#ffc107', '#D72638', '#6c757d', '#dc3545'];
        
        new Chart(revenueTypeCtx, {
            type: 'pie',
            data: {
                labels: typeLabels,
                datasets: [{
                    data: typeValues,
                    backgroundColor: colors.slice(0, typeValues.length),
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        
        // Attendance Trend Chart
        const attendanceCtx = document.getElementById('attendanceChart').getContext('2d');
        const attendanceData = <?php echo json_encode($attendance_trend); ?>;
        const attLabels = attendanceData.map(a => a.date);
        const attValues = attendanceData.map(a => parseInt(a.checkins));
        
        new Chart(attendanceCtx, {
            type: 'line',
            data: {
                labels: attLabels,
                datasets: [{
                    label: 'Check-ins',
                    data: attValues,
                    borderColor: '#D72638',
                    backgroundColor: 'rgba(215, 38, 56, 0.1)',
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#D72638'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
        
        // Member Growth Chart
        const memberGrowthCtx = document.getElementById('memberGrowthChart').getContext('2d');
        const growthData = <?php echo json_encode($member_growth); ?>;
        const growthLabels = growthData.map(g => g.month);
        const growthValues = growthData.map(g => parseInt(g.new_members));
        
        new Chart(memberGrowthCtx, {
            type: 'bar',
            data: {
                labels: growthLabels,
                datasets: [{
                    label: 'New Members',
                    data: growthValues,
                    backgroundColor: 'rgba(215, 38, 56, 0.7)',
                    borderColor: '#D72638',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    });
    </script>
</body>
</html>