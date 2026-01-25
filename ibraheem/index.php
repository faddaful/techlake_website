<?php
session_start();

// Security: Set secure session settings
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1); // Only for HTTPS
session_regenerate_id(true);

// Rate limiting: Prevent brute force attacks
$max_attempts = 5;
$lockout_time = 15 * 60; // 15 minutes in seconds
$attempts_file = __DIR__ . '/../.login_attempts.json';

function check_rate_limit() {
    global $attempts_file, $max_attempts, $lockout_time;
    
    $client_ip = $_SERVER['REMOTE_ADDR'];
    $attempts = [];
    
    if (file_exists($attempts_file)) {
        $attempts = json_decode(file_get_contents($attempts_file), true) ?? [];
    }
    
    $current_time = time();
    
    // Clean up old attempts
    foreach ($attempts as $ip => $data) {
        if ($current_time - $data['last_attempt'] > $lockout_time) {
            unset($attempts[$ip]);
        }
    }
    
    if (isset($attempts[$client_ip])) {
        $attempt_data = $attempts[$client_ip];
        
        // Check if account is locked
        if ($attempt_data['count'] >= $max_attempts) {
            $time_left = $lockout_time - ($current_time - $attempt_data['last_attempt']);
            if ($time_left > 0) {
                return [
                    'allowed' => false,
                    'message' => 'Too many failed attempts. Please try again in ' . ceil($time_left / 60) . ' minutes.'
                ];
            }
        }
    }
    
    return ['allowed' => true];
}

function record_failed_attempt() {
    global $attempts_file, $lockout_time;
    
    $client_ip = $_SERVER['REMOTE_ADDR'];
    $attempts = [];
    
    if (file_exists($attempts_file)) {
        $attempts = json_decode(file_get_contents($attempts_file), true) ?? [];
    }
    
    $current_time = time();
    
    if (!isset($attempts[$client_ip])) {
        $attempts[$client_ip] = ['count' => 0, 'last_attempt' => $current_time];
    }
    
    $attempts[$client_ip]['count']++;
    $attempts[$client_ip]['last_attempt'] = $current_time;
    
    file_put_contents($attempts_file, json_encode($attempts));
}

function clear_failed_attempts() {
    global $attempts_file;
    $client_ip = $_SERVER['REMOTE_ADDR'];
    
    if (file_exists($attempts_file)) {
        $attempts = json_decode(file_get_contents($attempts_file), true) ?? [];
        unset($attempts[$client_ip]);
        file_put_contents($attempts_file, json_encode($attempts));
    }
}

// Admin password (hashed with password_hash for security)
$ADMIN_PASSWORD_HASH = password_hash('***REMOVED***', PASSWORD_BCRYPT, ['cost' => 12]);

// Check rate limit first
$rate_limit = check_rate_limit();
$error = '';

if (!$rate_limit['allowed']) {
    $error = $rate_limit['message'];
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    
    // Verify password using bcrypt hash
    if (password_verify($password, $ADMIN_PASSWORD_HASH)) {
        clear_failed_attempts();
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['login_time'] = time();
        header('Location: dashboard.php');
        exit;
    } else {
        record_failed_attempt();
        $error = 'Invalid password. Please try again.';
    }
}

// If already logged in, redirect to dashboard
if ($_SESSION['admin_logged_in'] ?? false) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>TechLake Secure Admin Portal</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 40px;
            width: 100%;
            max-width: 400px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 5px;
            font-weight: 700;
        }
        
        .login-header p {
            color: #999;
            font-size: 13px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            color: #333;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 8px;
            text-transform: uppercase;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .login-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }
        
        .login-btn:active {
            transform: translateY(0);
        }
        
        .error-message {
            background: #fee;
            color: #c33;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 13px;
            border-left: 4px solid #c33;
        }
        
        .security-notice {
            background: #f0f7ff;
            color: #0066cc;
            padding: 12px;
            border-radius: 6px;
            margin-top: 20px;
            font-size: 12px;
            border-left: 4px solid #0066cc;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>TechLake Admin</h1>
            <p>Secure Portal</p>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST" autocomplete="off">
            <div class="form-group">
                <label for="password">Admin Password</label>
                <input type="password" id="password" name="password" placeholder="Enter your secure password" required autofocus autocomplete="off">
            </div>
            <button type="submit" class="login-btn">Login</button>
        </form>
        
        <div class="security-notice">
            🔒 This portal is protected with advanced security features including rate limiting and encryption.
        </div>
    </div>
</body>
</html>
