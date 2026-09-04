<?php
/**
 * Member Search
 * Gym Management System
 * 
 * File: members/search.php
 * Purpose: Search members
 */

// Load configuration
require_once '../config/config.php';

// Require authentication
requireAuth;

// Check permissions
if (!isStaff()) {
    displayError('You do not have permission to search members.');
    redirect('../dashboard.php');
}

// Get search query
$query = isset($_GET['query']) ? sanitize($_GET['query']) : '';

if (strlen($query) < 2) {
    displayError('Please enter at least 2 characters to search.');
    redirect('index.php');
}

try {
    $search_param = '%' . $query . '%';
    
    $sql = "SELECT m.*, 
            mm.status as membership_status,
            mm.expiration_date,
            mp.plan_name
            FROM members m
            LEFT JOIN member_memberships mm ON m.member_id = mm.member_id AND mm.status = 'active'
            LEFT JOIN membership_plans mp ON mm.plan_id = mp.plan_id
            WHERE m.status = 'active' AND (
                m.first_name LIKE ? OR 
                m.last_name LIKE ? OR 
                m.member_number LIKE ? OR 
                m.contact_number LIKE ? OR
                CONCAT(m.first_name, ' ', m.last_name) LIKE ?
            )
            LIMIT 10";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$search_param, $search_param, $search_param, $search_param, $search_param]);
    $members = $stmt->fetchAll();
    
    if (empty($members)) {
        displayError('No members found matching your search criteria.');
        redirect('index.php');
    }
    
    // Format results for display
    $results = [];
    foreach ($members as $member) {
        $is_expired = !empty($member['expiration_date']) && isDatePast($member['expiration_date']);
        $results[] = [
            'member_id' => $member['member_id'],
            'member_number' => $member['member_number'],
            'first_name' => $member['first_name'],
            'last_name' => $member['last_name'],
            'customer_type' => $member['customer_type'],
            'plan_name' => $member['plan_name'] ?? 'No Plan',
            'expiration_date' => $member['expiration_date'],
            'membership_status' => $is_expired ? 'expired' : ($member['membership_status'] ?? 'none'),
            'display_name' => $member['first_name'] . ' ' . $member['last_name'],
        ];
    }
    
    // Include header and show results
    include '../includes/header.php';
    ?>
    <body>
        <div class="app-container">
            <div class="main-content">
                <div class="content-wrapper">
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <h4><i class="bi bi-search"></i> Search Results for: "<?php echo htmlspecialchars($query); ?>"</h4>
                        <a href="index.php" class="btn btn-link">← Back to Members</a>
                    </div>
                    
                    <div class="card modern-card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Member</th>
                                            <th>Member No.</th>
                                            <th>Plan</th>
                                            <th>Type</th>
                                            <th>Status</th>
                                            <th>Expires</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($results as $member): 
                                            $days_left = !empty($member['expiration_date']) ? daysDifference(date('Y-m-d'), $member['expiration_date']) : 'N/A';
                                        ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($member['display_name']); ?></strong>
                                            </td>
                                            <td><?php echo htmlspecialchars($member['member_number']); ?></td>
                                            <td><?php echo htmlspecialchars($member['plan_name']); ?></td>
                                            <td><span class="badge <?php echo $member['customer_type'] === 'student' ? 'bg-info' : 'bg-secondary'; ?>">
                                                <?php echo ucfirst($member['customer_type']); ?></span></td>
                                            <td>
                                                <span class="badge <?php echo $member['membership_status'] === 'expired' ? 'bg-danger' : ($member['membership_status'] === 'active' ? 'bg-success' : 'bg-warning'); ?>">
                                                    <?php echo ucfirst($member['membership_status']); ?></span>
                                            </td>
                                            <td>
                                                <?php if (!empty($member['expiration_date'])): ?>
                                                <?php echo formatDate($member['expiration_date']); ?>
                                                <br><small>(<?php echo $days_left !== 'N/A' ? $days_left . ' days' : ''; ?>)</small>
                                                <?php else: ?>
                                                <small class="text-muted">No membership</small>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="mt-3">
                                <a href="../members/add.php" class="btn btn-primary">
                                    <i class="bi bi-person-plus"></i> Add New Member
                                </a>
                                <small class="text-muted d-block mt-2">No members found? Try adding a new member.</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php include '../includes/footer.php'; ?>
    </body>
</html>
<?php
} catch (PDOException $e) {
    error_log('Member search error: ' . $e->getMessage());
    displayError('Database error occurred while searching members.');
    redirect('index.php');
}
?>