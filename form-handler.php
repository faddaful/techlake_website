<?php
// Handle form submission with spam prevention
header('Content-Type: application/json');
session_start();

// Generate CSRF token endpoint
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'csrf') {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    echo json_encode(['csrf_token' => $_SESSION['csrf_token'], 'timestamp' => time()]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // SPAM PREVENTION: Check CSRF token
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Security validation failed']);
        exit;
    }
    
    // SPAM PREVENTION: Check honeypot field (should be empty)
    if (!empty($_POST['website'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Form validation failed']);
        exit;
    }
    
    // SPAM PREVENTION: Check submission timestamp
    $submit_time = $_POST['submit_time'] ?? '';
    if (empty($submit_time) || (time() - intval($submit_time) < 2)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Please take your time to fill out the form']);
        exit;
    }
    
    // SPAM PREVENTION: Rate limiting by IP
    $client_ip = get_client_ip();
    if (check_rate_limit($client_ip)) {
        http_response_code(429);
        echo json_encode(['success' => false, 'message' => 'Too many submissions. Please try again later']);
        exit;
    }
    
    // Get form data
    $name = sanitize_input($_POST['name'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $business = sanitize_input($_POST['business'] ?? '');
    $problem = sanitize_input($_POST['problem'] ?? '');
    $message = sanitize_input($_POST['message'] ?? '');
    
    // Validate required fields
    if (empty($name) || empty($email)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Name and email are required']);
        exit;
    }
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid email address']);
        exit;
    }
    
    // Send email to info@techlake.co
    $to = 'info@techlake.co';
    $subject = 'New Contact Form Submission from ' . $name;
    $body = "Name: $name\n";
    $body .= "Email: $email\n";
    $body .= "Business: $business\n";
    $body .= "Problem: $problem\n";
    $body .= "Message: $message\n";
    $body .= "Submitted: " . date('Y-m-d H:i:s') . "\n";
    
    // Strip CRLF to prevent email header injection
    $safe_email = str_replace(["\r", "\n", "%0a", "%0d", "%0A", "%0D"], '', $email);
    $safe_name = str_replace(["\r", "\n", "%0a", "%0d", "%0A", "%0D"], '', $name);

    $headers = "From: noreply@techlake.co\r\n";
    $headers .= "Reply-To: $safe_email\r\n";
    
    $email_sent = mail($to, $subject, $body, $headers);
    
    // Create Tawk.to ticket via webhook/API
    // Note: You'll need to set up a Tawk.to bot or use their API
    // This creates a message that appears in your Tawk.to dashboard
    create_tawk_ticket($name, $email, $business, $problem, $message);
    
    if ($email_sent) {
        // Reset rate limit on successful submission
        reset_rate_limit($client_ip);
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Form submitted successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to send email']);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

function get_client_ip() {
    // Only trust REMOTE_ADDR — HTTP_CLIENT_IP and HTTP_X_FORWARDED_FOR
    // are user-controlled headers that attackers can spoof
    return $_SERVER['REMOTE_ADDR'];
}

function check_rate_limit($ip) {
    $rate_limit_file = __DIR__ . '/.rate-limit';
    $limit_threshold = 5; // Max 5 submissions
    $time_window = 3600; // Per hour
    
    $limits = [];
    if (file_exists($rate_limit_file)) {
        $limits = json_decode(file_get_contents($rate_limit_file), true) ?? [];
    }
    
    $current_time = time();
    
    // Clean old entries
    foreach ($limits as $stored_ip => $timestamps) {
        $limits[$stored_ip] = array_filter($timestamps, function($ts) use ($current_time, $time_window) {
            return ($current_time - $ts) < $time_window;
        });
        if (empty($limits[$stored_ip])) {
            unset($limits[$stored_ip]);
        }
    }
    
    // Check if IP has exceeded limit
    if (isset($limits[$ip]) && count($limits[$ip]) >= $limit_threshold) {
        return true;
    }
    
    // Record this submission
    if (!isset($limits[$ip])) {
        $limits[$ip] = [];
    }
    $limits[$ip][] = $current_time;
    
    // Save updated limits
    file_put_contents($rate_limit_file, json_encode($limits));
    
    return false;
}

function reset_rate_limit($ip) {
    $rate_limit_file = __DIR__ . '/.rate-limit';
    if (file_exists($rate_limit_file)) {
        $limits = json_decode(file_get_contents($rate_limit_file), true) ?? [];
        if (isset($limits[$ip])) {
            unset($limits[$ip]);
            file_put_contents($rate_limit_file, json_encode($limits));
        }
    }
}

function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function create_tawk_ticket($name, $email, $business, $problem, $message) {
    // Create a formatted message for Tawk.to
    // This will be logged and can be accessed via Tawk.to's visitor API
    $ticket_message = "New Lead: $name\nEmail: $email\nBusiness: $business\nProblem: $problem\nMessage: $message";
    
    // Log to a file as a backup record
    $log_file = __DIR__ . '/tawk-tickets.log';
    $log_entry = "[" . date('Y-m-d H:i:s') . "] " . $ticket_message . "\n---\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
    
    // Optional: Send to Tawk.to via their custom API if you have an integration
    // You can set up a Zapier webhook or IFTTT rule to forward this data
    // For now, Tawk.to will capture the conversation when they chat
}
?>
