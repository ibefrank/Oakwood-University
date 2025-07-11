<?php
/**
 * Configuration file for Oakwood University website
 */

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in production
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Timezone
date_default_timezone_set('America/New_York');

// Directory paths
define('BASE_DIR', dirname(__DIR__));
define('DATA_DIR', BASE_DIR . '/data');
define('LOGS_DIR', BASE_DIR . '/logs');

// Create necessary directories
if (!file_exists(DATA_DIR)) {
    mkdir(DATA_DIR, 0755, true);
}

if (!file_exists(LOGS_DIR)) {
    mkdir(LOGS_DIR, 0755, true);
}

// Database configuration (if needed in the future)
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'oakwood_university');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');

// Email configuration
define('SMTP_HOST', $_ENV['SMTP_HOST'] ?? 'localhost');
define('SMTP_PORT', $_ENV['SMTP_PORT'] ?? 587);
define('SMTP_USER', $_ENV['SMTP_USER'] ?? '');
define('SMTP_PASS', $_ENV['SMTP_PASS'] ?? '');
define('ADMIN_EMAIL', $_ENV['ADMIN_EMAIL'] ?? 'admin@oakwood.edu');
define('FROM_EMAIL', $_ENV['FROM_EMAIL'] ?? 'noreply@oakwood.edu');

// Security settings
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_FILE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx']);
define('RATE_LIMIT_WINDOW', 300); // 5 minutes
define('RATE_LIMIT_MAX_REQUESTS', 5);

// Application settings
define('ITEMS_PER_PAGE', 6);
define('MAX_NEWS_ITEMS', 100);
define('MAX_CONTACT_SUBMISSIONS', 1000);

// API Keys (if needed)
define('RECAPTCHA_SITE_KEY', $_ENV['RECAPTCHA_SITE_KEY'] ?? '');
define('RECAPTCHA_SECRET_KEY', $_ENV['RECAPTCHA_SECRET_KEY'] ?? '');

// Session configuration
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// CSRF Protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    return $_SESSION['csrf_token'] ?? '';
}

/**
 * Validate CSRF token
 */
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sanitize input data
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Log application events
 */
function logEvent($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$level] $message" . PHP_EOL;
    file_put_contents(LOGS_DIR . '/app.log', $logMessage, FILE_APPEND | LOCK_EX);
}

/**
 * Get client IP address
 */
function getClientIP() {
    $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    
    foreach ($ipKeys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            $ip = $_SERVER[$key];
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

/**
 * Validate file upload
 */
function validateFileUpload($file) {
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return false;
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        return false;
    }
    
    $fileInfo = pathinfo($file['name']);
    $extension = strtolower($fileInfo['extension']);
    
    return in_array($extension, ALLOWED_FILE_TYPES);
}

/**
 * Generate unique filename
 */
function generateUniqueFilename($originalName) {
    $fileInfo = pathinfo($originalName);
    $extension = strtolower($fileInfo['extension']);
    $filename = $fileInfo['filename'];
    
    // Remove special characters
    $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
    
    // Add timestamp and random string
    $uniqueId = date('YmdHis') . '_' . bin2hex(random_bytes(4));
    
    return $filename . '_' . $uniqueId . '.' . $extension;
}

/**
 * Check if request is from mobile device
 */
function isMobileDevice() {
    return preg_match('/(android|iphone|ipad|mobile)/i', $_SERVER['HTTP_USER_AGENT'] ?? '');
}

/**
 * Format phone number
 */
function formatPhoneNumber($phone) {
    $phone = preg_replace('/\D/', '', $phone);
    
    if (strlen($phone) === 10) {
        return sprintf('(%s) %s-%s', substr($phone, 0, 3), substr($phone, 3, 3), substr($phone, 6));
    } elseif (strlen($phone) === 11 && $phone[0] === '1') {
        return sprintf('+1 (%s) %s-%s', substr($phone, 1, 3), substr($phone, 4, 3), substr($phone, 7));
    }
    
    return $phone;
}

/**
 * Validate and sanitize URL
 */
function sanitizeURL($url) {
    $url = filter_var($url, FILTER_SANITIZE_URL);
    return filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
}

/**
 * Generate pagination
 */
function generatePagination($currentPage, $totalPages, $baseUrl) {
    $pagination = '';
    
    if ($totalPages <= 1) {
        return $pagination;
    }
    
    // Previous button
    if ($currentPage > 1) {
        $prevPage = $currentPage - 1;
        $pagination .= "<a href='{$baseUrl}?page={$prevPage}' class='pagination-link'>Previous</a>";
    }
    
    // Page numbers
    for ($i = 1; $i <= $totalPages; $i++) {
        $activeClass = $i === $currentPage ? 'active' : '';
        $pagination .= "<a href='{$baseUrl}?page={$i}' class='pagination-link {$activeClass}'>{$i}</a>";
    }
    
    // Next button
    if ($currentPage < $totalPages) {
        $nextPage = $currentPage + 1;
        $pagination .= "<a href='{$baseUrl}?page={$nextPage}' class='pagination-link'>Next</a>";
    }
    
    return $pagination;
}

// Set default headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Log the request
logEvent("Request: {$_SERVER['REQUEST_METHOD']} {$_SERVER['REQUEST_URI']} from " . getClientIP());
?>
