<?php
/**
 * Login Page
 * Gym Management System
 * 
 * File: login.php
 * Purpose: User authentication
 */

// Load configuration
require_once 'config/config.php';

// Define constants if not already defined
if (!defined('APP_NAME')) {
    define('APP_NAME', 'Gym Management System');
}

if (!defined('GYM_NAME')) {
    define('GYM_NAME', 'Elite Fitness Gym');
}

// Check if already logged in
if (isAuthenticated()) {
    header('Location: dashboard.php');
    exit;
}

// Process login form
$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validate CSRF token
    if (!verifyCSRFToken()) {
        $error = 'Security validation failed. Please try again.';
    } elseif (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        // Attempt authentication
        $user = authenticateUser($username, $password);
        
        if ($user) {
            // Login successful
            logAudit('LOGIN_SUCCESS', 'auth', $user['user_id'], 'User logged in successfully');
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Invalid username or password. Please try again.';
            logAudit('LOGIN_FAILED', 'auth', 0, 'Failed login attempt for username: ' . $username);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Google Fonts - Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #111111;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            width: 100%;
            max-width: 440px;
        }
        
        .login-card {
            background: #1C1C1C;
            border-radius: 16px;
            padding: 48px 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.8);
        }
        
        .login-logo {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .login-logo i {
            font-size: 48px;
            color: #D72638;
        }
        
        .login-logo h1 {
            color: #FFFFFF;
            font-size: 24px;
            font-weight: 700;
            margin-top: 12px;
            letter-spacing: -0.5px;
        }
        
        .login-logo p {
            color: #888888;
            font-size: 14px;
            margin-top: 4px;
        }
        
        .form-label {
            color: #E0E0E0;
            font-weight: 500;
            font-size: 14px;
        }
        
        .form-control {
            background: #2C2C2C;
            border: 1px solid #3C3C3C;
            color: #FFFFFF;
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            background: #2C2C2C;
            border-color: #D72638;
            color: #FFFFFF;
            box-shadow: 0 0 0 3px rgba(215, 38, 56, 0.15);
        }
        
        .form-control::placeholder {
            color: #666666;
        }
        
        .btn-login {
            background: #D72638;
            border: none;
            color: #FFFFFF;
            padding: 14px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 16px;
            width: 100%;
            transition: all 0.3s;
            margin-top: 8px;
        }
        
        .btn-login:hover {
            background: #C01F30;
            color: #FFFFFF;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(215, 38, 56, 0.3);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .alert-danger {
            background: rgba(215, 38, 56, 0.15);
            border: 1px solid #D72638;
            color: #FF6B7A;
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 14px;
        }
        
        .login-footer {
            text-align: center;
            margin-top: 24px;
            color: #666666;
            font-size: 13px;
        }
        
        .login-footer a {
            color: #D72638;
            text-decoration: none;
        }
        
        .login-footer a:hover {
            color: #FF6B7A;
        }
        
        .input-group-text {
            background: #2C2C2C;
            border: 1px solid #3C3C3C;
            border-right: none;
            color: #888888;
        }
        
        .input-group .form-control {
            border-left: none;
        }
        
        .input-group .form-control:focus {
            border-left: none;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-logo">
                <i class="bi bi-dumbbell"></i>
                <h1><?php echo GYM_NAME; ?></h1>
                <p>Management System</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <?php echo csrfTokenField(); ?>
                
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" class="form-control" id="username" name="username" 
                               placeholder="Enter your username" value="<?php echo htmlspecialchars($username); ?>" required>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" 
                               placeholder="Enter your password" required>
                    </div>
                </div>
                
                <button type="submit" class="btn-login">
                    <i class="bi bi-box-arrow-in-right"></i> Sign In
                </button>
            </form>
            
            <div class="login-footer">
                <p>Default credentials:</p>
                <p style="font-size: 12px; color: #888;">
                    <strong>Owner:</strong> admin / Admin123!<br>
                    <strong>Front Desk:</strong> frontdesk / Front123!<br>
                    <strong>Trainer:</strong> trainer1 / Trainer123!
                </p>
            </div>
        </div>
    </div>
</body>
</html>