<?php
/**
 * Database Connection and Management
 * Handles PostgreSQL database connections and common operations
 */

require_once 'config.php';

class Database {
    private static $instance = null;
    private $connection;
    private $host;
    private $port;
    private $database;
    private $username;
    private $password;

    private function __construct() {
        // Get database configuration from environment variables
        $this->host = $_ENV['PGHOST'] ?? 'localhost';
        $this->port = $_ENV['PGPORT'] ?? '5432';
        $this->database = $_ENV['PGDATABASE'] ?? 'oakwood_university';
        $this->username = $_ENV['PGUSER'] ?? 'postgres';
        $this->password = $_ENV['PGPASSWORD'] ?? '';
        
        $this->connect();
    }

    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Connect to PostgreSQL database
     */
    private function connect() {
        try {
            $dsn = "pgsql:host={$this->host};port={$this->port};dbname={$this->database}";
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false
            ];

            $this->connection = new PDO($dsn, $this->username, $this->password, $options);
            
            logEvent("Database connection established successfully");
            
        } catch (PDOException $e) {
            logEvent("Database connection failed: " . $e->getMessage(), 'ERROR');
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }

    /**
     * Get database connection
     */
    public function getConnection() {
        // Check if connection is still alive
        if ($this->connection === null) {
            $this->connect();
        }
        
        try {
            $this->connection->query('SELECT 1');
        } catch (PDOException $e) {
            logEvent("Database connection lost, reconnecting...", 'WARNING');
            $this->connect();
        }
        
        return $this->connection;
    }

    /**
     * Execute a prepared statement
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            logEvent("Database query error: " . $e->getMessage(), 'ERROR');
            throw new Exception("Database query failed: " . $e->getMessage());
        }
    }

    /**
     * Fetch all results
     */
    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * Fetch single result
     */
    public function fetchOne($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }

    /**
     * Insert data and return the inserted ID
     */
    public function insert($table, $data) {
        $columns = array_keys($data);
        $placeholders = array_map(function($col) { return ":$col"; }, $columns);
        
        $sql = "INSERT INTO $table (" . implode(', ', $columns) . ") 
                VALUES (" . implode(', ', $placeholders) . ") 
                RETURNING id";
        
        $stmt = $this->query($sql, $data);
        $result = $stmt->fetch();
        
        return $result ? $result['id'] : null;
    }

    /**
     * Update data
     */
    public function update($table, $data, $where, $whereParams = []) {
        $setClause = array_map(function($col) { return "$col = :$col"; }, array_keys($data));
        
        $sql = "UPDATE $table SET " . implode(', ', $setClause) . " WHERE $where";
        
        $params = array_merge($data, $whereParams);
        return $this->query($sql, $params)->rowCount();
    }

    /**
     * Delete data
     */
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM $table WHERE $where";
        return $this->query($sql, $params)->rowCount();
    }

    /**
     * Begin transaction
     */
    public function beginTransaction() {
        return $this->getConnection()->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public function commit() {
        return $this->getConnection()->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollback() {
        return $this->getConnection()->rollback();
    }

    /**
     * Get table schema information
     */
    public function getTableSchema($tableName) {
        $sql = "SELECT column_name, data_type, is_nullable, column_default 
                FROM information_schema.columns 
                WHERE table_name = :table_name 
                ORDER BY ordinal_position";
        
        return $this->fetchAll($sql, ['table_name' => $tableName]);
    }

    /**
     * Check if table exists
     */
    public function tableExists($tableName) {
        $sql = "SELECT EXISTS (
                    SELECT FROM information_schema.tables 
                    WHERE table_schema = 'public' 
                    AND table_name = :table_name
                )";
        
        $result = $this->fetchOne($sql, ['table_name' => $tableName]);
        return $result['exists'] ?? false;
    }

    /**
     * Get database statistics
     */
    public function getDatabaseStats() {
        $stats = [];
        
        // Get table row counts
        $tables = ['news', 'contact_submissions', 'newsletter_subscribers', 
                  'academic_programs', 'faculty', 'students', 'admissions_applications', 'events'];
        
        foreach ($tables as $table) {
            if ($this->tableExists($table)) {
                $result = $this->fetchOne("SELECT COUNT(*) as count FROM $table");
                $stats[$table] = $result['count'] ?? 0;
            }
        }
        
        return $stats;
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserialization
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

/**
 * News Model - Database operations for news
 */
class NewsModel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getAllNews($limit = 50, $offset = 0) {
        $sql = "SELECT * FROM news ORDER BY date DESC, created_at DESC LIMIT :limit OFFSET :offset";
        return $this->db->fetchAll($sql, ['limit' => $limit, 'offset' => $offset]);
    }

    public function getFeaturedNews($limit = 3) {
        $sql = "SELECT * FROM news WHERE featured = true ORDER BY date DESC LIMIT :limit";
        return $this->db->fetchAll($sql, ['limit' => $limit]);
    }

    public function getNewsByCategory($category, $limit = 20) {
        $sql = "SELECT * FROM news WHERE category = :category ORDER BY date DESC LIMIT :limit";
        return $this->db->fetchAll($sql, ['category' => $category, 'limit' => $limit]);
    }

    public function searchNews($query, $limit = 20) {
        $sql = "SELECT * FROM news 
                WHERE title ILIKE :query OR excerpt ILIKE :query 
                ORDER BY 
                    CASE WHEN title ILIKE :query THEN 1 ELSE 2 END,
                    date DESC 
                LIMIT :limit";
        
        $searchTerm = '%' . $query . '%';
        return $this->db->fetchAll($sql, ['query' => $searchTerm, 'limit' => $limit]);
    }

    public function createNews($data) {
        return $this->db->insert('news', $data);
    }
}

/**
 * Contact Model - Database operations for contact submissions
 */
class ContactModel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function saveContactSubmission($data) {
        return $this->db->insert('contact_submissions', $data);
    }

    public function getContactSubmissions($limit = 50, $offset = 0) {
        $sql = "SELECT * FROM contact_submissions ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        return $this->db->fetchAll($sql, ['limit' => $limit, 'offset' => $offset]);
    }

    public function updateSubmissionStatus($id, $status) {
        return $this->db->update('contact_submissions', 
            ['status' => $status, 'updated_at' => 'NOW()'], 
            'id = :id', 
            ['id' => $id]
        );
    }
}

/**
 * Newsletter Model - Database operations for newsletter subscribers
 */
class NewsletterModel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function addSubscriber($email, $name = '', $ipAddress = null) {
        $data = [
            'email' => $email,
            'name' => $name,
            'ip_address' => $ipAddress,
            'unsubscribe_token' => bin2hex(random_bytes(32))
        ];

        try {
            return $this->db->insert('newsletter_subscribers', $data);
        } catch (Exception $e) {
            // Handle duplicate email error
            if (strpos($e->getMessage(), 'duplicate key') !== false) {
                return false; // Already subscribed
            }
            throw $e;
        }
    }

    public function isSubscribed($email) {
        $sql = "SELECT id FROM newsletter_subscribers WHERE email = :email AND active = true";
        $result = $this->db->fetchOne($sql, ['email' => $email]);
        return $result !== false;
    }

    public function getSubscribers($limit = 100, $offset = 0) {
        $sql = "SELECT * FROM newsletter_subscribers WHERE active = true ORDER BY subscription_date DESC LIMIT :limit OFFSET :offset";
        return $this->db->fetchAll($sql, ['limit' => $limit, 'offset' => $offset]);
    }

    public function unsubscribe($token) {
        return $this->db->update('newsletter_subscribers', 
            ['active' => false], 
            'unsubscribe_token = :token', 
            ['token' => $token]
        );
    }
}

// Initialize database connection on include
try {
    Database::getInstance();
} catch (Exception $e) {
    logEvent("Failed to initialize database: " . $e->getMessage(), 'ERROR');
    // Fallback to file-based storage if database is not available
}
?>