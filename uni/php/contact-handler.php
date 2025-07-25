<?php
/**
 * Contact Form Handler
 * Handles contact form submissions and newsletter subscriptions
 */

require_once 'config.php';
require_once 'database.php';

// Set content type to JSON
header('Content-Type: application/json');

// Enable CORS if needed
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Initialize database models
    $contactModel = new ContactModel();
    $newsletterModel = new NewsletterModel();
    
    // Check if it's a JSON request (newsletter)
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input && isset($input['action']) && $input['action'] === 'newsletter') {
            handleNewsletterSubscription($input, $newsletterModel);
        } else {
            throw new Exception('Invalid request format');
        }
    } else {
        // Handle regular form submission
        handleContactForm($contactModel, $newsletterModel);
    }
} catch (Exception $e) {
    error_log('Contact handler error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while processing your request. Please try again later.'
    ]);
}

/**
 * Handle contact form submission
 */
function handleContactForm($contactModel, $newsletterModel) {
    // Validate required fields
    $required_fields = ['name', 'email', 'subject', 'message'];
    $errors = [];
    
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
            $errors[] = ucfirst($field) . ' is required';
        }
    }
    
    if (!empty($errors)) {
        echo json_encode([
            'success' => false,
            'message' => 'Please fill in all required fields: ' . implode(', ', $errors)
        ]);
        return;
    }
    
    // Sanitize input data
    $name = sanitizeInput($_POST['name']);
    $email = sanitizeInput($_POST['email']);
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $subject = sanitizeInput($_POST['subject']);
    $message = sanitizeInput($_POST['message']);
    $newsletter = isset($_POST['newsletter']) && $_POST['newsletter'] === 'yes';
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode([
            'success' => false,
            'message' => 'Please enter a valid email address'
        ]);
        return;
    }
    
    // Validate name length
    if (strlen($name) < 2) {
        echo json_encode([
            'success' => false,
            'message' => 'Name must be at least 2 characters long'
        ]);
        return;
    }
    
    // Validate message length
    if (strlen($message) < 10) {
        echo json_encode([
            'success' => false,
            'message' => 'Message must be at least 10 characters long'
        ]);
        return;
    }
    
    // Validate phone if provided
    if (!empty($phone) && !validatePhone($phone)) {
        echo json_encode([
            'success' => false,
            'message' => 'Please enter a valid phone number'
        ]);
        return;
    }
    
    // Rate limiting - simple implementation
    if (!checkRateLimit($email)) {
        echo json_encode([
            'success' => false,
            'message' => 'Too many requests. Please wait before sending another message.'
        ]);
        return;
    }
    
    // Save to database/file
    $contactData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'subject' => $subject,
        'message' => $message,
        'newsletter' => $newsletter,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ];
    
    // Store in database
    $dbContactData = [
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'subject' => $subject,
        'message' => $message,
        'newsletter_subscription' => $newsletter,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
    ];
    
    try {
        $contactId = $contactModel->saveContactSubmission($dbContactData);
        
        // Handle newsletter subscription
        if ($newsletter) {
            try {
                $newsletterModel->addSubscriber($email, $name, $_SERVER['REMOTE_ADDR'] ?? null);
            } catch (Exception $e) {
                logEvent('Newsletter subscription failed: ' . $e->getMessage(), 'WARNING');
                // Don't fail the contact form if newsletter subscription fails
            }
        }
        
        logEvent("Contact form submitted successfully. ID: $contactId");
        
        echo json_encode([
            'success' => true,
            'message' => 'Thank you for your message! We will get back to you soon.'
        ]);
    } catch (Exception $e) {
        logEvent('Failed to save contact submission: ' . $e->getMessage(), 'ERROR');
        echo json_encode([
            'success' => false,
            'message' => 'Failed to save your message. Please try again.'
        ]);
    }
}

/**
 * Handle newsletter subscription
 */
function handleNewsletterSubscription($input, $newsletterModel) {
    if (!isset($input['email']) || empty(trim($input['email']))) {
        echo json_encode([
            'success' => false,
            'message' => 'Email address is required'
        ]);
        return;
    }
    
    $email = sanitizeInput($input['email']);
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode([
            'success' => false,
            'message' => 'Please enter a valid email address'
        ]);
        return;
    }
    
    // Check if already subscribed
    if ($newsletterModel->isSubscribed($email)) {
        echo json_encode([
            'success' => false,
            'message' => 'This email is already subscribed to our newsletter'
        ]);
        return;
    }
    
    // Rate limiting
    if (!checkRateLimit($email)) {
        echo json_encode([
            'success' => false,
            'message' => 'Too many requests. Please wait before trying again.'
        ]);
        return;
    }
    
    try {
        $subscriberId = $newsletterModel->addSubscriber($email, '', $_SERVER['REMOTE_ADDR'] ?? null);
        if ($subscriberId) {
            echo json_encode([
                'success' => true,
                'message' => 'Thank you for subscribing to our newsletter!'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'This email is already subscribed to our newsletter'
            ]);
        }
    } catch (Exception $e) {
        logEvent('Newsletter subscription failed: ' . $e->getMessage(), 'ERROR');
        echo json_encode([
            'success' => false,
            'message' => 'Failed to subscribe. Please try again.'
        ]);
    }
}

/**
 * Sanitize input data
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Validate phone number
 */
function validatePhone($phone) {
    // Remove all non-digit characters
    $phone = preg_replace('/\D/', '', $phone);
    // Check if it's a valid length (7-15 digits)
    return strlen($phone) >= 7 && strlen($phone) <= 15;
}

/**
 * Simple rate limiting
 */
function checkRateLimit($identifier) {
    $rateLimitFile = DATA_DIR . '/rate_limit.json';
    $currentTime = time();
    $timeWindow = 300; // 5 minutes
    $maxRequests = 5;
    
    // Create data directory if it doesn't exist
    if (!file_exists(DATA_DIR)) {
        mkdir(DATA_DIR, 0755, true);
    }
    
    $rateLimitData = [];
    if (file_exists($rateLimitFile)) {
        $rateLimitData = json_decode(file_get_contents($rateLimitFile), true) ?: [];
    }
    
    // Clean old entries
    $rateLimitData = array_filter($rateLimitData, function($timestamp) use ($currentTime, $timeWindow) {
        return ($currentTime - $timestamp) < $timeWindow;
    });
    
    // Check current identifier
    $userRequests = array_filter($rateLimitData, function($timestamp, $key) use ($identifier) {
        return strpos($key, $identifier) === 0;
    }, ARRAY_FILTER_USE_BOTH);
    
    if (count($userRequests) >= $maxRequests) {
        return false;
    }
    
    // Add current request
    $rateLimitData[$identifier . '_' . uniqid()] = $currentTime;
    
    // Save updated data
    file_put_contents($rateLimitFile, json_encode($rateLimitData));
    
    return true;
}

/**
 * Save contact submission to file
 */
function saveContactSubmission($data) {
    try {
        $contactsFile = DATA_DIR . '/contacts.json';
        
        // Create data directory if it doesn't exist
        if (!file_exists(DATA_DIR)) {
            mkdir(DATA_DIR, 0755, true);
        }
        
        $contacts = [];
        if (file_exists($contactsFile)) {
            $contacts = json_decode(file_get_contents($contactsFile), true) ?: [];
        }
        
        $contacts[] = $data;
        
        return file_put_contents($contactsFile, json_encode($contacts, JSON_PRETTY_PRINT)) !== false;
    } catch (Exception $e) {
        error_log('Failed to save contact: ' . $e->getMessage());
        return false;
    }
}

/**
 * Add email to newsletter
 */
function addToNewsletter($email, $name = '') {
    try {
        $newsletterFile = DATA_DIR . '/newsletter.json';
        
        // Create data directory if it doesn't exist
        if (!file_exists(DATA_DIR)) {
            mkdir(DATA_DIR, 0755, true);
        }
        
        $subscribers = [];
        if (file_exists($newsletterFile)) {
            $subscribers = json_decode(file_get_contents($newsletterFile), true) ?: [];
        }
        
        // Check if already subscribed
        foreach ($subscribers as $subscriber) {
            if ($subscriber['email'] === $email) {
                return true; // Already subscribed
            }
        }
        
        $subscribers[] = [
            'email' => $email,
            'name' => $name,
            'subscribed_at' => date('Y-m-d H:i:s'),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];
        
        return file_put_contents($newsletterFile, json_encode($subscribers, JSON_PRETTY_PRINT)) !== false;
    } catch (Exception $e) {
        error_log('Failed to add to newsletter: ' . $e->getMessage());
        return false;
    }
}

/**
 * Check if email is already subscribed
 */
function isAlreadySubscribed($email) {
    $newsletterFile = DATA_DIR . '/newsletter.json';
    
    if (!file_exists($newsletterFile)) {
        return false;
    }
    
    $subscribers = json_decode(file_get_contents($newsletterFile), true) ?: [];
    
    foreach ($subscribers as $subscriber) {
        if ($subscriber['email'] === $email) {
            return true;
        }
    }
    
    return false;
}

/**
 * Send email notification
 */
function sendEmailNotification($data) {
    // Get email settings from environment or use defaults
    $adminEmail = $_ENV['ADMIN_EMAIL'] ?? 'admin@oakwood.edu';
    $fromEmail = $_ENV['FROM_EMAIL'] ?? 'noreply@oakwood.edu';
    
    $subject = 'New Contact Form Submission - ' . $data['subject'];
    
    $message = "
    New contact form submission received:
    
    Name: {$data['name']}
    Email: {$data['email']}
    Phone: {$data['phone']}
    Subject: {$data['subject']}
    
    Message:
    {$data['message']}
    
    Newsletter Subscription: " . ($data['newsletter'] ? 'Yes' : 'No') . "
    
    Submitted at: {$data['timestamp']}
    IP Address: {$data['ip_address']}
    ";
    
    $headers = "From: {$fromEmail}\r\n";
    $headers .= "Reply-To: {$data['email']}\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    // Send email (in production, use a proper email service)
    try {
        mail($adminEmail, $subject, $message, $headers);
    } catch (Exception $e) {
        error_log('Failed to send email notification: ' . $e->getMessage());
    }
}
?>
