/**
 * Main JavaScript
 * Gym Management System
 * 
 * File: assets/js/app.js
 * Purpose: Global JavaScript functions and initialization
 */

// ============================================
// DOM READY
// ============================================
$(document).ready(function() {
    // Initialize sidebar toggle
    initSidebar();
    
    // Initialize notifications
    initNotifications();
    
    // Initialize date/time
    updateDateTime();
    setInterval(updateDateTime, 60000);
    
    // Initialize tooltips
    initTooltips();
    
    // Initialize CSRF token refresh
    refreshCSRFToken();
});

// ============================================
// SIDEBAR
// ============================================
function initSidebar() {
    // Toggle sidebar on mobile
    $('#navbarToggle, #sidebarToggle').on('click', function(e) {
        e.stopPropagation();
        $('#sidebar').toggleClass('open');
        $('#sidebarOverlay').toggleClass('active');
    });
    
    // Close sidebar when clicking overlay
    $('#sidebarOverlay').on('click', function() {
        $('#sidebar').removeClass('open');
        $(this).removeClass('active');
    });
    
    // Close sidebar on escape key
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            $('#sidebar').removeClass('open');
            $('#sidebarOverlay').removeClass('active');
        }
    });
}

// ============================================
// DATE AND TIME
// ============================================
function updateDateTime() {
    const now = new Date();
    const timeElement = document.querySelector('.navbar-time');
    if (timeElement) {
        timeElement.textContent = now.toLocaleTimeString('en-US', {
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        });
    }
}

// ============================================
// NOTIFICATIONS
// ============================================
function initNotifications() {
    // Load notifications on page load
    loadNotifications();
    
    // Refresh notifications every 60 seconds
    setInterval(loadNotifications, 60000);
}

function loadNotifications() {
    $.ajax({
        url: BASE_URL + 'ajax/notifications.php',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                updateNotificationBadge(response.count);
                updateNotificationList(response.notifications);
            }
        },
        error: function() {
            // Silent fail - don't show errors to users
        }
    });
}

function updateNotificationBadge(count) {
    const badge = document.getElementById('notificationBadge');
    if (badge) {
        badge.textContent = count;
        badge.style.display = count > 0 ? 'inline' : 'none';
    }
}

function updateNotificationList(notifications) {
    const container = document.getElementById('notificationList');
    const emptyMessage = document.getElementById('noNotifications');
    
    if (!container) return;
    
    if (!notifications || notifications.length === 0) {
        container.innerHTML = '';
        if (emptyMessage) emptyMessage.style.display = 'block';
        return;
    }
    
    if (emptyMessage) emptyMessage.style.display = 'none';
    
    let html = '';
    notifications.forEach(function(notif) {
        const icon = getNotificationIcon(notif.type);
        const unreadClass = notif.is_read ? '' : 'unread';
        html += `
            <div class="notification-item ${unreadClass}" data-id="${notif.notification_id}">
                <div class="d-flex align-items-start gap-2">
                    <i class="bi ${icon} text-${notif.type}"></i>
                    <div class="flex-grow-1">
                        <div class="fw-bold">${notif.title}</div>
                        <div class="text-muted small">${notif.message}</div>
                        <div class="text-muted small">${timeAgo(notif.created_at)}</div>
                    </div>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
    
    // Mark notifications as read when clicked
    $('.notification-item').on('click', function() {
        const id = $(this).data('id');
        if (!$(this).hasClass('unread')) return;
        
        $.ajax({
            url: BASE_URL + 'ajax/mark-notification-read.php',
            type: 'POST',
            data: { notification_id: id },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    loadNotifications();
                }
            }
        });
    });
}

function getNotificationIcon(type) {
    const icons = {
        'info': 'bi-info-circle',
        'warning': 'bi-exclamation-triangle',
        'error': 'bi-x-circle',
        'success': 'bi-check-circle'
    };
    return icons[type] || 'bi-bell';
}

// ============================================
// TOAST NOTIFICATIONS
// ============================================
function showToast(type, message) {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    
    const colors = {
        'success': '#28a745',
        'error': '#dc3545',
        'warning': '#ffc107',
        'info': '#17a2b8'
    };
    
    const icons = {
        'success': 'bi-check-circle-fill',
        'error': 'bi-x-circle-fill',
        'warning': 'bi-exclamation-triangle-fill',
        'info': 'bi-info-circle-fill'
    };
    
    const color = colors[type] || '#6c757d';
    const icon = icons[type] || 'bi-bell-fill';
    
    const toastId = 'toast-' + Date.now();
    const html = `
        <div id="${toastId}" class="toast align-items-center border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi ${icon}" style="color: ${color}; margin-right: 8px;"></i>
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', html);
    
    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement, {
        autohide: true,
        delay: 5000
    });
    toast.show();
    
    // Remove from DOM after hidden
    toastElement.addEventListener('hidden.bs.toast', function() {
        this.remove();
    });
}

// ============================================
// TOOLTIPS
// ============================================
function initTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

// ============================================
// UTILITY FUNCTIONS
// ============================================
function timeAgo(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diff = Math.floor((now - date) / 1000);
    
    if (diff < 60) return 'Just now';
    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
    
    return date.toLocaleDateString();
}

function formatCurrency(amount) {
    return '₱' + parseFloat(amount).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

function refreshCSRFToken() {
    const token = document.querySelector('meta[name="csrf-token"]');
    if (token) {
        // Refresh token via AJAX periodically
        setInterval(function() {
            $.ajax({
                url: BASE_URL + 'ajax/refresh-csrf.php',
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.token) {
                        token.content = response.token;
                        // Update all CSRF token fields
                        $('input[name="csrf_token"]').val(response.token);
                    }
                }
            });
        }, 1800000); // 30 minutes
    }
}

// ============================================
// FORM HELPERS
// ============================================
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return true;
    
    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(function(field) {
        if (!field.value.trim()) {
            field.classList.add('is-invalid');
            isValid = false;
        } else {
            field.classList.remove('is-invalid');
        }
    });
    
    if (!isValid) {
        showToast('warning', 'Please fill in all required fields.');
    }
    
    return isValid;
}

function confirmAction(message) {
    return confirm(message || 'Are you sure you want to proceed?');
}

// ============================================
// SEARCH HELPERS
// ============================================
function debounce(func, delay) {
    let timeoutId;
    return function(...args) {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => func.apply(this, args), delay);
    };
}