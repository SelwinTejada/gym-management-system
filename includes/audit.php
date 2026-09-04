<?php
/**
 * Audit Logging
 * Gym Management System
 * 
 * File: includes/audit.php
 * Purpose: System activity logging
 */

/**
 * Log audit entry
 */
function logAudit($action, $module, $recordId = null, $description = null, $ipAddress = null) {
    global $pdo;
    
    try {
        $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
        
        if ($ipAddress === null) {
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }
        
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (user_id, action, module, record_id, description, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([$userId, $action, $module, $recordId, $description, $ipAddress, $userAgent]);
        
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log('Audit logging error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get audit logs
 */
function getAuditLogs($limit = 100, $offset = 0, $filters = []) {
    global $pdo;
    
    try {
        $sql = "SELECT al.*, u.username, u.full_name 
                FROM audit_logs al 
                LEFT JOIN users u ON al.user_id = u.user_id 
                WHERE 1=1";
        
        $params = [];
        
        if (!empty($filters['user_id'])) {
            $sql .= " AND al.user_id = ?";
            $params[] = $filters['user_id'];
        }
        
        if (!empty($filters['module'])) {
            $sql .= " AND al.module = ?";
            $params[] = $filters['module'];
        }
        
        if (!empty($filters['action'])) {
            $sql .= " AND al.action = ?";
            $params[] = $filters['action'];
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(al.created_at) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(al.created_at) <= ?";
            $params[] = $filters['date_to'];
        }
        
        $sql .= " ORDER BY al.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Get audit logs error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get audit log count
 */
function getAuditLogCount($filters = []) {
    global $pdo;
    
    try {
        $sql = "SELECT COUNT(*) FROM audit_logs WHERE 1=1";
        $params = [];
        
        if (!empty($filters['user_id'])) {
            $sql .= " AND user_id = ?";
            $params[] = $filters['user_id'];
        }
        
        if (!empty($filters['module'])) {
            $sql .= " AND module = ?";
            $params[] = $filters['module'];
        }
        
        if (!empty($filters['action'])) {
            $sql .= " AND action = ?";
            $params[] = $filters['action'];
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(created_at) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(created_at) <= ?";
            $params[] = $filters['date_to'];
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log('Get audit log count error: ' . $e->getMessage());
        return 0;
    }
}
?>