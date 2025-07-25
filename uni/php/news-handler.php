<?php
/**
 * News Handler
 * Handles news data retrieval and management using PostgreSQL database
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

// Get the action parameter
$action = $_GET['action'] ?? 'get_news';

try {
    $newsModel = new NewsModel();
    
    switch ($action) {
        case 'get_news':
            getNews($newsModel);
            break;
        case 'get_featured':
            getFeaturedNews($newsModel);
            break;
        case 'get_by_category':
            getNewsByCategory($newsModel);
            break;
        case 'search':
            searchNews($newsModel);
            break;
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    error_log('News handler error: ' . $e->getMessage());
    
    // Fallback to JSON file if database fails
    if (strpos($e->getMessage(), 'Database') !== false) {
        logEvent('Database error, falling back to JSON file: ' . $e->getMessage(), 'WARNING');
        handleWithJsonFallback($action);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'An error occurred while processing your request',
            'news' => []
        ]);
    }
}

/**
 * Get all news articles from database
 */
function getNews($newsModel) {
    try {
        $limit = (int)($_GET['limit'] ?? 20);
        $offset = (int)($_GET['offset'] ?? 0);
        
        $newsData = $newsModel->getAllNews($limit, $offset);
        
        // Format dates for frontend
        foreach ($newsData as &$article) {
            $article['formatted_date'] = date('F j, Y', strtotime($article['date']));
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'News loaded successfully from database',
            'news' => $newsData,
            'total' => count($newsData)
        ]);
        
    } catch (Exception $e) {
        error_log('Error loading news: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Get featured news articles from database
 */
function getFeaturedNews($newsModel) {
    try {
        $limit = (int)($_GET['limit'] ?? 3);
        $newsData = $newsModel->getFeaturedNews($limit);
        
        // Format dates for frontend
        foreach ($newsData as &$article) {
            $article['formatted_date'] = date('F j, Y', strtotime($article['date']));
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Featured news loaded successfully',
            'news' => $newsData
        ]);
        
    } catch (Exception $e) {
        error_log('Error loading featured news: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Get news articles by category from database
 */
function getNewsByCategory($newsModel) {
    try {
        $category = $_GET['category'] ?? '';
        $limit = (int)($_GET['limit'] ?? 20);
        
        if (empty($category)) {
            throw new Exception('Category parameter is required');
        }
        
        $newsData = $newsModel->getNewsByCategory($category, $limit);
        
        // Format dates for frontend
        foreach ($newsData as &$article) {
            $article['formatted_date'] = date('F j, Y', strtotime($article['date']));
        }
        
        echo json_encode([
            'success' => true,
            'message' => "News for category '$category' loaded successfully",
            'news' => $newsData
        ]);
        
    } catch (Exception $e) {
        error_log('Error loading news by category: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Search news articles in database
 */
function searchNews($newsModel) {
    try {
        $query = $_GET['query'] ?? '';
        $limit = (int)($_GET['limit'] ?? 20);
        
        if (empty($query)) {
            throw new Exception('Search query parameter is required');
        }
        
        $newsData = $newsModel->searchNews($query, $limit);
        
        // Format dates for frontend
        foreach ($newsData as &$article) {
            $article['formatted_date'] = date('F j, Y', strtotime($article['date']));
        }
        
        echo json_encode([
            'success' => true,
            'message' => "Search results for '$query' loaded successfully",
            'news' => $newsData
        ]);
        
    } catch (Exception $e) {
        error_log('Error searching news: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Fallback to JSON file if database is unavailable
 */
function handleWithJsonFallback($action) {
    try {
        $newsFile = DATA_DIR . '/news.json';
        
        if (!file_exists($newsFile)) {
            echo json_encode([
                'success' => false,
                'message' => 'News data not available',
                'news' => []
            ]);
            return;
        }
        
        $newsData = json_decode(file_get_contents($newsFile), true);
        
        if (!$newsData) {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to load news data',
                'news' => []
            ]);
            return;
        }
        
        // Sort by date (newest first)
        usort($newsData, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
        
        // Handle different actions
        switch ($action) {
            case 'get_featured':
                $newsData = array_filter($newsData, function($article) {
                    return $article['featured'] ?? false;
                });
                $newsData = array_slice($newsData, 0, 3);
                break;
                
            case 'get_by_category':
                $category = $_GET['category'] ?? '';
                if ($category) {
                    $newsData = array_filter($newsData, function($article) use ($category) {
                        return $article['category'] === $category;
                    });
                }
                break;
                
            case 'search':
                $query = $_GET['query'] ?? '';
                if ($query) {
                    $newsData = array_filter($newsData, function($article) use ($query) {
                        return stripos($article['title'], $query) !== false || 
                               stripos($article['excerpt'], $query) !== false;
                    });
                }
                break;
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'News loaded successfully (fallback mode)',
            'news' => array_values($newsData)
        ]);
        
    } catch (Exception $e) {
        error_log('Error in JSON fallback: ' . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Failed to load news',
            'news' => []
        ]);
    }
}
?>