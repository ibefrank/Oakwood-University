<?php
/**
 * Admin Handler
 * Handles administrative requests and database management
 */

require_once 'config.php';
require_once 'database.php';

// Set content type to JSON
header('Content-Type: application/json');

// Enable CORS if needed
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Only allow GET requests for admin data
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get the action parameter
$action = $_GET['action'] ?? 'get_stats';

try {
    $db = Database::getInstance();
    
    switch ($action) {
        case 'get_stats':
            getStats($db);
            break;
        case 'get_contacts':
            getContacts($db);
            break;
        case 'get_newsletter':
            getNewsletter($db);
            break;
        case 'get_programs':
            getPrograms($db);
            break;
        case 'get_faculty':
            getFaculty($db);
            break;
        case 'get_events':
            getEvents($db);
            break;
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    error_log('Admin handler error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while processing your request'
    ]);
}

/**
 * Get database statistics
 */
function getStats($db) {
    try {
        $stats = $db->getDatabaseStats();
        
        echo json_encode([
            'success' => true,
            'message' => 'Statistics loaded successfully',
            'stats' => $stats
        ]);
        
    } catch (Exception $e) {
        error_log('Error loading stats: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Get contact submissions
 */
function getContacts($db) {
    try {
        $contactModel = new ContactModel();
        $limit = (int)($_GET['limit'] ?? 50);
        $offset = (int)($_GET['offset'] ?? 0);
        
        $contacts = $contactModel->getContactSubmissions($limit, $offset);
        
        echo json_encode([
            'success' => true,
            'message' => 'Contact submissions loaded successfully',
            'contacts' => $contacts
        ]);
        
    } catch (Exception $e) {
        error_log('Error loading contacts: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Get newsletter subscribers
 */
function getNewsletter($db) {
    try {
        $newsletterModel = new NewsletterModel();
        $limit = (int)($_GET['limit'] ?? 50);
        $offset = (int)($_GET['offset'] ?? 0);
        
        $subscribers = $newsletterModel->getSubscribers($limit, $offset);
        
        echo json_encode([
            'success' => true,
            'message' => 'Newsletter subscribers loaded successfully',
            'subscribers' => $subscribers
        ]);
        
    } catch (Exception $e) {
        error_log('Error loading newsletter subscribers: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Get academic programs
 */
function getPrograms($db) {
    try {
        $sql = "SELECT * FROM academic_programs WHERE active = true ORDER BY department, name";
        $programs = $db->fetchAll($sql);
        
        echo json_encode([
            'success' => true,
            'message' => 'Academic programs loaded successfully',
            'programs' => $programs
        ]);
        
    } catch (Exception $e) {
        error_log('Error loading programs: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Get faculty members
 */
function getFaculty($db) {
    try {
        $sql = "SELECT * FROM faculty WHERE active = true ORDER BY department, last_name, first_name";
        $faculty = $db->fetchAll($sql);
        
        echo json_encode([
            'success' => true,
            'message' => 'Faculty members loaded successfully',
            'faculty' => $faculty
        ]);
        
    } catch (Exception $e) {
        error_log('Error loading faculty: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Get events
 */
function getEvents($db) {
    try {
        $sql = "SELECT * FROM events ORDER BY event_date DESC";
        $events = $db->fetchAll($sql);
        
        echo json_encode([
            'success' => true,
            'message' => 'Events loaded successfully',
            'events' => $events
        ]);
        
    } catch (Exception $e) {
        error_log('Error loading events: ' . $e->getMessage());
        throw $e;
    }
}
?>