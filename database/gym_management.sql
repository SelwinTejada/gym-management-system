-- ============================================
-- GYM MANAGEMENT SYSTEM DATABASE SCHEMA
-- Complete Database with All Tables
-- ============================================

-- Drop database if exists and create fresh
DROP DATABASE IF EXISTS gym_management;
CREATE DATABASE gym_management;
USE gym_management;

-- ============================================
-- TABLE: users (System user accounts)
-- ============================================
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('owner', 'front_desk', 'trainer') NOT NULL,
    contact_number VARCHAR(20),
    profile_image VARCHAR(255),
    last_login DATETIME,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_role (role),
    INDEX idx_status (status),
    INDEX idx_username (username)
);

-- ============================================
-- TABLE: members (Gym member profiles)
-- ============================================
CREATE TABLE members (
    member_id INT PRIMARY KEY AUTO_INCREMENT,
    member_number VARCHAR(20) UNIQUE NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50),
    last_name VARCHAR(50) NOT NULL,
    suffix VARCHAR(10),
    date_of_birth DATE,
    gender ENUM('male', 'female', 'other'),
    contact_number VARCHAR(20) NOT NULL,
    email VARCHAR(100),
    address TEXT,
    emergency_contact_name VARCHAR(100),
    emergency_contact_number VARCHAR(20),
    profile_image VARCHAR(255),
    qr_code VARCHAR(255),
    customer_type ENUM('student', 'regular') NOT NULL,
    status ENUM('active', 'suspended', 'cancelled') DEFAULT 'active',
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_member_number (member_number),
    INDEX idx_status (status),
    INDEX idx_customer_type (customer_type),
    INDEX idx_name (last_name, first_name)
);

-- ============================================
-- TABLE: membership_plans (Available plans)
-- ============================================
CREATE TABLE membership_plans (
    plan_id INT PRIMARY KEY AUTO_INCREMENT,
    plan_name VARCHAR(50) NOT NULL,
    duration_days INT NOT NULL,
    student_price DECIMAL(10,2) NOT NULL,
    regular_price DECIMAL(10,2) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (is_active),
    INDEX idx_display_order (display_order)
);

-- ============================================
-- TABLE: member_memberships (Membership history)
-- ============================================
CREATE TABLE member_memberships (
    membership_id INT PRIMARY KEY AUTO_INCREMENT,
    member_id INT NOT NULL,
    plan_id INT NOT NULL,
    customer_type ENUM('student', 'regular') NOT NULL,
    start_date DATE NOT NULL,
    expiration_date DATE NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    discount DECIMAL(10,2) DEFAULT 0.00,
    amount_paid DECIMAL(10,2) NOT NULL,
    balance DECIMAL(10,2) DEFAULT 0.00,
    status ENUM('active', 'expired', 'cancelled', 'suspended') DEFAULT 'active',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES membership_plans(plan_id),
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_member (member_id),
    INDEX idx_plan (plan_id),
    INDEX idx_status (status),
    INDEX idx_expiration (expiration_date),
    INDEX idx_start_date (start_date)
);

-- ============================================
-- TABLE: attendance (Check-in records)
-- ============================================
CREATE TABLE attendance (
    attendance_id INT PRIMARY KEY AUTO_INCREMENT,
    member_id INT NULL,
    walkin_name VARCHAR(100) NULL,
    customer_type ENUM('student', 'regular') NOT NULL,
    check_in_date DATE NOT NULL,
    check_in_time TIME NOT NULL,
    membership_status VARCHAR(20),
    pt_session_id INT NULL,
    processed_by INT NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE SET NULL,
    FOREIGN KEY (processed_by) REFERENCES users(user_id),
    INDEX idx_member (member_id),
    INDEX idx_date (check_in_date),
    INDEX idx_processed_by (processed_by),
    INDEX idx_customer_type (customer_type)
);

-- ============================================
-- TABLE: payments (All financial transactions)
-- ============================================
CREATE TABLE payments (
    payment_id INT PRIMARY KEY AUTO_INCREMENT,
    transaction_number VARCHAR(50) UNIQUE NOT NULL,
    member_id INT NULL,
    walkin_name VARCHAR(100) NULL,
    transaction_type ENUM('membership', 'renewal', 'walkin', 'pt_package', 'pt_session', 'other') NOT NULL,
    payment_method ENUM('cash', 'gcash', 'bank_transfer', 'card', 'other') NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    discount DECIMAL(10,2) DEFAULT 0.00,
    total DECIMAL(10,2) NOT NULL,
    amount_paid DECIMAL(10,2) NOT NULL,
    change_amount DECIMAL(10,2) DEFAULT 0.00,
    balance DECIMAL(10,2) DEFAULT 0.00,
    payment_date DATE NOT NULL,
    payment_time TIME NOT NULL,
    status ENUM('completed', 'void', 'refunded') DEFAULT 'completed',
    reference_number VARCHAR(100),
    notes TEXT,
    processed_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE SET NULL,
    FOREIGN KEY (processed_by) REFERENCES users(user_id),
    INDEX idx_transaction (transaction_number),
    INDEX idx_member (member_id),
    INDEX idx_status (status),
    INDEX idx_payment_date (payment_date),
    INDEX idx_method (payment_method),
    INDEX idx_type (transaction_type)
);

-- ============================================
-- TABLE: payment_items (Line items)
-- ============================================
CREATE TABLE payment_items (
    item_id INT PRIMARY KEY AUTO_INCREMENT,
    payment_id INT NOT NULL,
    item_type ENUM('membership', 'pt_package', 'pt_session', 'walkin', 'other') NOT NULL,
    description VARCHAR(255) NOT NULL,
    quantity INT DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    reference_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (payment_id) REFERENCES payments(payment_id) ON DELETE CASCADE,
    INDEX idx_payment (payment_id),
    INDEX idx_item_type (item_type)
);

-- ============================================
-- TABLE: personal_trainers (Trainer profiles)
-- ============================================
CREATE TABLE personal_trainers (
    trainer_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    contact_number VARCHAR(20) NOT NULL,
    email VARCHAR(100),
    specialization VARCHAR(100),
    rate DECIMAL(10,2) NOT NULL,
    profile_image VARCHAR(255),
    status ENUM('active', 'inactive') DEFAULT 'active',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_name (last_name, first_name)
);

-- ============================================
-- TABLE: pt_packages (PT packages purchased)
-- ============================================
CREATE TABLE pt_packages (
    package_id INT PRIMARY KEY AUTO_INCREMENT,
    member_id INT NOT NULL,
    trainer_id INT NOT NULL,
    total_sessions INT NOT NULL,
    used_sessions INT DEFAULT 0,
    remaining_sessions INT GENERATED ALWAYS AS (total_sessions - used_sessions) STORED,
    price DECIMAL(10,2) NOT NULL,
    start_date DATE NOT NULL,
    expiration_date DATE,
    status ENUM('active', 'completed', 'expired', 'cancelled') DEFAULT 'active',
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE,
    FOREIGN KEY (trainer_id) REFERENCES personal_trainers(trainer_id),
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_member (member_id),
    INDEX idx_trainer (trainer_id),
    INDEX idx_status (status),
    INDEX idx_remaining (remaining_sessions)
);

-- ============================================
-- TABLE: pt_sessions (Individual PT sessions)
-- ============================================
CREATE TABLE pt_sessions (
    session_id INT PRIMARY KEY AUTO_INCREMENT,
    package_id INT NOT NULL,
    member_id INT NOT NULL,
    trainer_id INT NOT NULL,
    session_date DATE NOT NULL,
    session_time TIME NOT NULL,
    session_number INT NOT NULL,
    status ENUM('scheduled', 'completed', 'cancelled', 'no_show') DEFAULT 'scheduled',
    notes TEXT,
    recorded_by INT,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (package_id) REFERENCES pt_packages(package_id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE,
    FOREIGN KEY (trainer_id) REFERENCES personal_trainers(trainer_id),
    FOREIGN KEY (recorded_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_package (package_id),
    INDEX idx_member (member_id),
    INDEX idx_trainer (trainer_id),
    INDEX idx_date (session_date),
    INDEX idx_status (status)
);

-- ============================================
-- TABLE: expense_categories (Expense categories)
-- ============================================
CREATE TABLE expense_categories (
    category_id INT PRIMARY KEY AUTO_INCREMENT,
    category_name VARCHAR(50) NOT NULL,
    is_system BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (is_active),
    INDEX idx_name (category_name)
);

-- ============================================
-- TABLE: expenses (Business expenses)
-- ============================================
CREATE TABLE expenses (
    expense_id INT PRIMARY KEY AUTO_INCREMENT,
    category_id INT NOT NULL,
    description TEXT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    expense_date DATE NOT NULL,
    payment_method ENUM('cash', 'gcash', 'bank_transfer', 'card', 'other') NOT NULL,
    reference_number VARCHAR(100),
    receipt_image VARCHAR(255),
    notes TEXT,
    recorded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES expense_categories(category_id),
    FOREIGN KEY (recorded_by) REFERENCES users(user_id),
    INDEX idx_category (category_id),
    INDEX idx_expense_date (expense_date),
    INDEX idx_recorded_by (recorded_by)
);

-- ============================================
-- TABLE: daily_closings (End-of-day reconciliation)
-- ============================================
CREATE TABLE daily_closings (
    closing_id INT PRIMARY KEY AUTO_INCREMENT,
    closing_date DATE NOT NULL UNIQUE,
    expected_cash DECIMAL(10,2) DEFAULT 0.00,
    expected_gcash DECIMAL(10,2) DEFAULT 0.00,
    expected_card DECIMAL(10,2) DEFAULT 0.00,
    expected_other DECIMAL(10,2) DEFAULT 0.00,
    actual_cash DECIMAL(10,2) DEFAULT 0.00,
    actual_gcash DECIMAL(10,2) DEFAULT 0.00,
    actual_card DECIMAL(10,2) DEFAULT 0.00,
    actual_other DECIMAL(10,2) DEFAULT 0.00,
    difference_cash DECIMAL(10,2) GENERATED ALWAYS AS (actual_cash - expected_cash) STORED,
    difference_gcash DECIMAL(10,2) GENERATED ALWAYS AS (actual_gcash - expected_gcash) STORED,
    difference_card DECIMAL(10,2) GENERATED ALWAYS AS (actual_card - expected_card) STORED,
    difference_other DECIMAL(10,2) GENERATED ALWAYS AS (actual_other - expected_other) STORED,
    total_expected DECIMAL(10,2) GENERATED ALWAYS AS (expected_cash + expected_gcash + expected_card + expected_other) STORED,
    total_actual DECIMAL(10,2) GENERATED ALWAYS AS (actual_cash + actual_gcash + actual_card + actual_other) STORED,
    total_difference DECIMAL(10,2) GENERATED ALWAYS AS ((actual_cash + actual_gcash + actual_card + actual_other) - (expected_cash + expected_gcash + expected_card + expected_other)) STORED,
    notes TEXT,
    closed_by INT NOT NULL,
    closed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (closed_by) REFERENCES users(user_id),
    INDEX idx_closing_date (closing_date),
    INDEX idx_closed_by (closed_by)
);

-- ============================================
-- TABLE: audit_logs (System activity tracking)
-- ============================================
CREATE TABLE audit_logs (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    module VARCHAR(50) NOT NULL,
    record_id INT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_module (module),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
);

-- ============================================
-- TABLE: settings (System configuration)
-- ============================================
CREATE TABLE settings (
    setting_id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_group VARCHAR(50) DEFAULT 'general',
    is_public BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key),
    INDEX idx_group (setting_group)
);

-- ============================================
-- TABLE: notifications (In-system notifications)
-- ============================================
CREATE TABLE notifications (
    notification_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'warning', 'error', 'success') DEFAULT 'info',
    link VARCHAR(255),
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_read (is_read),
    INDEX idx_created_at (created_at)
);

-- ============================================
-- DEFAULT DATA
-- ============================================

-- Default Admin User (password: Admin123!)
INSERT INTO users (username, password_hash, email, full_name, role, status) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@gym.com', 'System Administrator', 'owner', 'active');

-- Default Front Desk User (password: Front123!)
INSERT INTO users (username, password_hash, email, full_name, role, status) VALUES
('frontdesk', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'frontdesk@gym.com', 'Front Desk Staff', 'front_desk', 'active');

-- Default Trainer User (password: Trainer123!)
INSERT INTO users (username, password_hash, email, full_name, role, status) VALUES
('trainer1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'trainer@gym.com', 'John Smith', 'trainer', 'active');

-- Default Membership Plans
INSERT INTO membership_plans (plan_name, duration_days, student_price, regular_price, description, display_order) VALUES
('Daily Pass', 1, 70.00, 100.00, 'Single day gym access', 1),
('Weekly Pass', 7, 400.00, 500.00, '7 days of unlimited gym access', 2),
('Monthly', 30, 600.00, 800.00, '30 days of unlimited gym access', 3),
('3 Months', 90, 1600.00, 2100.00, '90 days of unlimited gym access', 4),
('Annual', 365, 6000.00, 8000.00, '365 days of unlimited gym access', 5);

-- Default Expense Categories
INSERT INTO expense_categories (category_name, is_system, display_order) VALUES
('Rent', TRUE, 1),
('Electricity', TRUE, 2),
('Water', TRUE, 3),
('Internet', TRUE, 4),
('Salaries', TRUE, 5),
('Trainer Commissions', TRUE, 6),
('Equipment', TRUE, 7),
('Equipment Repair', TRUE, 8),
('Maintenance', TRUE, 9),
('Cleaning', TRUE, 10),
('Marketing', TRUE, 11),
('Supplies', TRUE, 12),
('Other', TRUE, 13);

-- Default Settings
INSERT INTO settings (setting_key, setting_value, setting_group) VALUES
('gym_name', 'Elite Fitness Gym', 'general'),
('gym_address', '123 Fitness Street, Manila, Philippines', 'general'),
('gym_contact', '+63 912 345 6789', 'general'),
('gym_email', 'info@elitefitness.com', 'general'),
('currency_symbol', '₱', 'general'),
('duplicate_checkin_threshold_minutes', '60', 'attendance'),
('membership_grace_period_days', '7', 'membership'),
('default_role', 'front_desk', 'security');

-- Sample Personal Trainer
INSERT INTO personal_trainers (first_name, last_name, contact_number, email, specialization, rate, status) VALUES
('John', 'Smith', '09123456789', 'john.smith@elitefitness.com', 'Strength Training, Weight Loss', 500.00, 'active');

-- Sample Member
INSERT INTO members (member_number, first_name, last_name, contact_number, customer_type, status) VALUES
('GM-000001', 'Juan', 'Dela Cruz', '09123456780', 'student', 'active');

-- Sample Membership for member
INSERT INTO member_memberships (member_id, plan_id, customer_type, start_date, expiration_date, price, amount_paid, status) VALUES
(1, 3, 'student', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 600.00, 600.00, 'active');

-- Sample PT Package
INSERT INTO pt_packages (member_id, trainer_id, total_sessions, price, start_date, expiration_date, status) VALUES
(1, 1, 12, 3600.00, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 90 DAY), 'active');

-- Sample PT Sessions
INSERT INTO pt_sessions (package_id, member_id, trainer_id, session_date, session_time, session_number, status) VALUES
(1, 1, 1, CURDATE(), '10:00:00', 1, 'scheduled'),
(1, 1, 1, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '10:00:00', 2, 'scheduled'),
(1, 1, 1, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '10:00:00', 3, 'scheduled');

-- Sample Attendance
INSERT INTO attendance (member_id, customer_type, check_in_date, check_in_time, membership_status, processed_by) VALUES
(1, 'student', CURDATE(), '08:30:00', 'active', 1);