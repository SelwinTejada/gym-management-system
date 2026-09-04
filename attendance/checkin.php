<?php
/**
 * Member Check-in
 * Gym Management System
 * 
 * File: attendance/checkin.php
 * Purpose: Process member check-ins
 */

// Load configuration
require_once '../config/config.php';

// Require authentication
requireAuth();

// Check permissions
if (!isStaff()) {
    displayError('You do not have permission to process check-ins.');
    redirect('../dashboard.php');
}

// Get today's date
$today = date('Y-m-d');

// Get recent check-ins
try {
    $stmt = $pdo->prepare("
        SELECT a.*, 
               CASE 
                   WHEN a.member_id IS NOT NULL THEN CONCAT(m.first_name, ' ', m.last_name)
                   ELSE a.walkin_name
               END as customer_name,
               m.member_number,
               m.customer_type as member_type
        FROM attendance a
        LEFT JOIN members m ON a.member_id = m.member_id
        WHERE a.check_in_date = ?
        ORDER BY a.check_in_time DESC
        LIMIT 20
    ");
    $stmt->execute([$today]);
    $recent_checkins = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Get recent checkins error: ' . $e->getMessage());
    $recent_checkins = [];
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
                <div class="row">
                    <!-- Check-in Form -->
                    <div class="col-lg-6">
                        <div class="card modern-card">
                            <div class="card-header">
                                <h5><i class="bi bi-check2-circle"></i> Member Check-in</h5>
                            </div>
                            <div class="card-body">
                                <div class="checkin-search">
                                    <label class="form-label">Search Member</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                                        <input type="text" class="form-control form-control-lg" id="memberSearch" 
                                               placeholder="Search by name, ID, or scan QR code">
                                        <button class="btn btn-outline-secondary" type="button" id="scanQRBtn">
                                            <i class="bi bi-qr-code"></i>
                                        </button>
                                    </div>
                                    <div id="searchResults" class="mt-3" style="display: none;"></div>
                                </div>
                                
                                <!-- Check-in Result -->
                                <div id="checkinResult" class="mt-4" style="display: none;"></div>
                                
                                <!-- Quick Actions -->
                                <hr>
                                <div class="d-flex gap-2">
                                    <a href="../walkins/add.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-person-walking"></i> Walk-in
                                    </a>
                                    <a href="../members/add.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-person-plus"></i> New Member
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Check-ins -->
                    <div class="col-lg-6">
                        <div class="card modern-card">
                            <div class="card-header">
                                <h5><i class="bi bi-clock-history"></i> Today's Check-ins</h5>
                                <span class="badge bg-primary"><?php echo count($recent_checkins); ?></span>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>Customer</th>
                                                <th>Type</th>
                                                <th>Time</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody id="recentCheckins">
                                            <?php if (empty($recent_checkins)): ?>
                                            <tr>
                                                <td colspan="4" class="text-center text-muted py-4">
                                                    <i class="bi bi-clock" style="font-size: 24px; display: block;"></i>
                                                    No check-ins yet today.
                                                </td>
                                            </tr>
                                            <?php else: ?>
                                                <?php foreach ($recent_checkins as $checkin): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($checkin['customer_name']); ?></strong>
                                                        <?php if (!empty($checkin['member_number'])): ?>
                                                        <br><small class="text-muted"><?php echo $checkin['member_number']; ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge <?php echo $checkin['customer_type'] === 'student' ? 'bg-info' : 'bg-secondary'; ?>">
                                                            <?php echo ucfirst($checkin['customer_type']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo formatTime($checkin['check_in_time']); ?></td>
                                                    <td>
                                                        <span class="badge bg-success">✓ Checked In</span>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>
    
    <script>
    $(document).ready(function() {
        // Member search with debounce
        let searchTimeout;
        
        $('#memberSearch').on('input', function() {
            clearTimeout(searchTimeout);
            const query = $(this).val().trim();
            
            if (query.length < 2) {
                $('#searchResults').hide().empty();
                return;
            }
            
            searchTimeout = setTimeout(function() {
                searchMembers(query);
            }, 300);
        });
        
        // Handle Enter key
        $('#memberSearch').on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                const query = $(this).val().trim();
                if (query.length >= 2) {
                    searchMembers(query);
                }
            }
        });
        
        function searchMembers(query) {
            $.ajax({
                url: '../ajax/member-search.php',
                type: 'GET',
                data: { query: query },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.members.length > 0) {
                        displaySearchResults(response.members);
                    } else {
                        displayNoResults(query);
                    }
                },
                error: function() {
                    showToast('error', 'An error occurred while searching.');
                }
            });
        }
        
        function displaySearchResults(members) {
            let html = '<div class="search-results-list">';
            
            members.forEach(function(member) {
                const statusBadge = member.membership_status === 'active' 
                    ? '<span class="badge bg-success">Active</span>'
                    : member.membership_status === 'expired'
                    ? '<span class="badge bg-danger">Expired</span>'
                    : '<span class="badge bg-warning">No Membership</span>';
                
                html += `
                    <div class="search-result-item" data-id="${member.member_id}">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>${member.first_name} ${member.last_name}</strong>
                                <br><small class="text-muted">${member.member_number}</small>
                                <span class="badge ${member.customer_type === 'student' ? 'bg-info' : 'bg-secondary'}">${member.customer_type}</span>
                            </div>
                            <div class="text-end">
                                ${statusBadge}
                                ${member.plan_name ? '<br><small>' + member.plan_name + '</small>' : ''}
                                ${member.expiration_date ? '<br><small>Expires: ' + formatDate(member.expiration_date) + '</small>' : ''}
                                <button class="btn btn-primary btn-sm ms-2 checkin-btn" data-id="${member.member_id}">
                                    <i class="bi bi-check2-circle"></i> Check In
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            
            $('#searchResults').html(html).show();
            
            // Bind check-in buttons
            $('.checkin-btn').on('click', function() {
                const memberId = $(this).data('id');
                processCheckin(memberId);
            });
        }
        
        function displayNoResults(query) {
            $('#searchResults').html(`
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> No members found for "${query}".
                    <br><a href="../members/add.php" class="btn btn-primary btn-sm mt-2">
                        <i class="bi bi-person-plus"></i> Add New Member
                    </a>
                </div>
            `).show();
        }
        
        function processCheckin(memberId) {
            $.ajax({
                url: '../ajax/checkin-member.php',
                type: 'POST',
                data: { 
                    member_id: memberId,
                    csrf_token: $('meta[name="csrf-token"]').attr('content')
                },
                dataType: 'json',
                success: function(response) {
                    displayCheckinResult(response);
                    
                    if (response.success) {
                        // Refresh recent check-ins
                        refreshRecentCheckins();
                        // Clear search
                        $('#memberSearch').val('');
                        $('#searchResults').hide();
                    }
                },
                error: function() {
                    showToast('error', 'An error occurred while processing check-in.');
                }
            });
        }
        
        function displayCheckinResult(response) {
            const container = $('#checkinResult');
            
            if (response.success) {
                container.html(`
                    <div class="alert alert-success">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-check-circle-fill" style="font-size: 32px; margin-right: 16px;"></i>
                            <div>
                                <h5 class="mb-1">✓ Check-in Successful</h5>
                                <p class="mb-0">${response.member_name}</p>
                                <small class="text-muted">
                                    ${response.customer_type} · Checked in at ${response.checkin_time}
                                </small>
                            </div>
                        </div>
                    </div>
                `).show();
                
                // Auto-hide after 5 seconds
                setTimeout(function() {
                    container.fadeOut();
                }, 5000);
            } else {
                let buttons = '';
                if (response.action === 'renew') {
                    buttons = `
                        <a href="../membership/renew.php?member_id=${response.member_id}" class="btn btn-primary mt-2">
                            <i class="bi bi-arrow-repeat"></i> Renew Membership
                        </a>
                    `;
                }
                
                container.html(`
                    <div class="alert alert-${response.type === 'error' ? 'danger' : 'warning'}">
                        <div class="d-flex align-items-start">
                            <i class="bi ${response.type === 'error' ? 'bi-x-circle-fill' : 'bi-exclamation-triangle-fill'}" 
                               style="font-size: 24px; margin-right: 12px; margin-top: 4px;"></i>
                            <div>
                                <h5 class="mb-1">${response.title}</h5>
                                <p class="mb-0">${response.message}</p>
                                ${buttons}
                            </div>
                        </div>
                    </div>
                `).show();
            }
        }
        
        function refreshRecentCheckins() {
            $.ajax({
                url: '../ajax/recent-checkins.php',
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        const tbody = $('#recentCheckins');
                        if (response.checkins.length === 0) {
                            tbody.html(`
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        <i class="bi bi-clock" style="font-size: 24px; display: block;"></i>
                                        No check-ins yet today.
                                    </td>
                                </tr>
                            `);
                        } else {
                            let html = '';
                            response.checkins.forEach(function(checkin) {
                                html += `
                                    <tr>
                                        <td>
                                            <strong>${checkin.customer_name}</strong>
                                            ${checkin.member_number ? '<br><small class="text-muted">' + checkin.member_number + '</small>' : ''}
                                        </td>
                                        <td>
                                            <span class="badge ${checkin.customer_type === 'student' ? 'bg-info' : 'bg-secondary'}">
                                                ${checkin.customer_type}
                                            </span>
                                        </td>
                                        <td>${checkin.check_in_time}</td>
                                        <td>
                                            <span class="badge bg-success">✓ Checked In</span>
                                        </td>
                                    </tr>
                                `;
                            });
                            tbody.html(html);
                        }
                    }
                }
            });
        }
        
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        }
    });
    </script>
    
    <style>
    .search-results-list {
        max-height: 400px;
        overflow-y: auto;
    }
    
    .search-result-item {
        padding: 12px 16px;
        border: 1px solid var(--border-color);
        border-radius: var(--radius-sm);
        margin-bottom: 8px;
        transition: var(--transition);
    }
    
    .search-result-item:hover {
        background: var(--light-bg);
        border-color: var(--accent-color);
    }
    
    #memberSearch {
        font-size: 18px;
        padding: 12px 16px;
    }
    
    .checkin-result {
        animation: slideDown 0.3s ease;
    }
    
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    </style>
</body>
</html>