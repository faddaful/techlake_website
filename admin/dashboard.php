<?php
session_start();

// Redirect to login if not authenticated
if (!($_SESSION['admin_logged_in'] ?? false)) {
    header('Location: index.php');
    exit;
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Handle clear logs
if (isset($_POST['clear_logs'])) {
    $log_file = __DIR__ . '/../tawk-tickets.log';
    if (file_exists($log_file)) {
        file_put_contents($log_file, '');
        $clear_message = 'Log file cleared successfully.';
    }
}

// Read log file
$log_file = __DIR__ . '/../tawk-tickets.log';
$logs = [];
$log_content = '';

if (file_exists($log_file)) {
    $log_content = file_get_contents($log_file);
    // Parse log entries
    $entries = explode('---', $log_content);
    foreach ($entries as $entry) {
        $entry = trim($entry);
        if (!empty($entry)) {
            $logs[] = $entry;
        }
    }
}

// Reverse to show newest first
$logs = array_reverse($logs);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TechLake Admin - Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #f5f5f5;
            color: #333;
        }
        
        .navbar {
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 20px 0;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .navbar-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .navbar h1 {
            font-size: 24px;
            color: #667eea;
        }
        
        .navbar-actions {
            display: flex;
            gap: 20px;
            align-items: center;
        }
        
        .logout-btn {
            background: #ff6b6b;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: background 0.3s;
        }
        
        .logout-btn:hover {
            background: #ff5252;
        }
        
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .stat-label {
            color: #666;
            font-size: 13px;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #667eea;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .section-header h2 {
            font-size: 22px;
            color: #333;
        }
        
        .clear-logs-btn {
            background: #ff9800;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: background 0.3s;
        }
        
        .clear-logs-btn:hover {
            background: #f57c00;
        }
        
        .logs-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }
        
        .log-entry {
            background: #f9f9f9;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            line-height: 1.6;
            word-break: break-word;
            white-space: pre-wrap;
        }
        
        .log-entry:last-child {
            margin-bottom: 0;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        
        .empty-state p {
            font-size: 16px;
        }
        
        .success-message {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid #2e7d32;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="navbar-content">
            <h1>TechLake Admin Dashboard</h1>
            <div class="navbar-actions">
                <span>Welcome, Admin</span>
                <a href="?logout=true" class="logout-btn">Logout</a>
            </div>
        </div>
    </div>
    
    <div class="container">
        <?php if (isset($clear_message)): ?>
            <div class="success-message"><?php echo $clear_message; ?></div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Submissions</div>
                <div class="stat-value"><?php echo count($logs); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Log File Size</div>
                <div class="stat-value">
                    <?php 
                    if (file_exists($log_file)) {
                        $size = filesize($log_file);
                        if ($size > 1024 * 1024) {
                            echo round($size / (1024 * 1024), 2) . ' MB';
                        } elseif ($size > 1024) {
                            echo round($size / 1024, 2) . ' KB';
                        } else {
                            echo $size . ' B';
                        }
                    } else {
                        echo '0 B';
                    }
                    ?>
                </div>
            </div>
        </div>
        
        <div class="section-header">
            <h2>Contact Form Submissions</h2>
            <?php if (count($logs) > 0): ?>
                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure? This cannot be undone.');">
                    <button type="submit" name="clear_logs" class="clear-logs-btn">Clear All Logs</button>
                </form>
            <?php endif; ?>
        </div>
        
        <div class="logs-container">
            <?php if (count($logs) > 0): ?>
                <?php foreach ($logs as $index => $log): ?>
                    <div class="log-entry"><?php echo htmlspecialchars($log); ?></div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <p>No submissions yet. Contact form submissions will appear here.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
