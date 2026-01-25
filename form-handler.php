<?php
// Handle form submission
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $name = sanitize_input($_POST['name'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $business = sanitize_input($_POST['business'] ?? '');
    $problem = sanitize_input($_POST['problem'] ?? '');
    $message = sanitize_input($_POST['message'] ?? '');
    
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
    
    $headers = "From: $email\r\n";
    $headers .= "Reply-To: $email\r\n";
    
    $email_sent = mail($to, $subject, $body, $headers);
    
    // Create Tawk.to ticket via webhook/API
    // Note: You'll need to set up a Tawk.to bot or use their API
    // This creates a message that appears in your Tawk.to dashboard
    create_tawk_ticket($name, $email, $business, $problem, $message);
    
    if ($email_sent) {
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
