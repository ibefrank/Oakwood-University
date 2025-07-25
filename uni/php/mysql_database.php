<?php
/**
 * MySQL Database Connection and Management
 * Handles MySQL database connections and common operations
 */

require_once 'config.php';

class MySQLDatabase {
    private static $instance = null;
    private $connection;
    private $host;
    private $port;
    private $database;
    private $username;
    private $password;

    private function __construct() {
        // Get database configuration
        $this->host = 'localhost';
        $this->port = '3306';
        $this->database = 'oakwood_university';
        $this->username = 'root';
        $this->password = '';
        
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
     * Connect to MySQL database
     */
    private function connect() {
        try {
            $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->database};charset=utf8mb4";
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ];

            $this->connection = new PDO($dsn, $this->username, $this->password, $options);
            
            logEvent("MySQL database connection established successfully");
            
        } catch (PDOException $e) {
            logEvent("MySQL database connection failed: " . $e->getMessage(), 'ERROR');
            
            // Try to create database if it doesn't exist
            try {
                $dsn = "mysql:host={$this->host};port={$this->port};charset=utf8mb4";
                $tempConn = new PDO($dsn, $this->username, $this->password, $options);
                $tempConn->exec("CREATE DATABASE IF NOT EXISTS `{$this->database}`");
                $tempConn = null;
                
                // Retry connection
                $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->database};charset=utf8mb4";
                $this->connection = new PDO($dsn, $this->username, $this->password, $options);
                logEvent("MySQL database created and connected successfully");
                
            } catch (PDOException $e2) {
                throw new Exception("MySQL database connection failed: " . $e2->getMessage());
            }
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
            logEvent("MySQL connection lost, reconnecting...", 'WARNING');
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
            logEvent("MySQL query error: " . $e->getMessage(), 'ERROR');
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
        // Generate UUID for id if not provided
        if (!isset($data['id'])) {
            $data['id'] = $this->generateUUID();
        }
        
        $columns = array_keys($data);
        $placeholders = array_map(function($col) { return ":$col"; }, $columns);
        
        $sql = "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) 
                VALUES (" . implode(', ', $placeholders) . ")";
        
        $this->query($sql, $data);
        
        return $data['id'];
    }

    /**
     * Update data
     */
    public function update($table, $data, $where, $whereParams = []) {
        $setClause = array_map(function($col) { return "`$col` = :$col"; }, array_keys($data));
        
        $sql = "UPDATE `$table` SET " . implode(', ', $setClause) . " WHERE $where";
        
        $params = array_merge($data, $whereParams);
        return $this->query($sql, $params)->rowCount();
    }

    /**
     * Delete data
     */
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM `$table` WHERE $where";
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
     * Generate UUID for MySQL
     */
    private function generateUUID() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    /**
     * Get table schema information
     */
    public function getTableSchema($tableName) {
        $sql = "SHOW COLUMNS FROM `$tableName`";
        return $this->fetchAll($sql);
    }

    /**
     * Check if table exists
     */
    public function tableExists($tableName) {
        $sql = "SHOW TABLES LIKE :table_name";
        $result = $this->fetchOne($sql, ['table_name' => $tableName]);
        return $result !== false;
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
                $result = $this->fetchOne("SELECT COUNT(*) as count FROM `$table`");
                $stats[$table] = $result['count'] ?? 0;
            }
        }
        
        return $stats;
    }

    /**
     * Initialize database schema
     */
    public function initializeSchema() {
        try {
            $schemaFile = __DIR__ . '/../database/mysql_schema.sql';
            if (file_exists($schemaFile)) {
                $sql = file_get_contents($schemaFile);
                
                // Split by semicolons and execute each statement
                $statements = array_filter(array_map('trim', explode(';', $sql)));
                
                foreach ($statements as $statement) {
                    if (!empty($statement) && !preg_match('/^--/', $statement)) {
                        $this->getConnection()->exec($statement);
                    }
                }
                
                logEvent("MySQL database schema initialized successfully");
                return true;
            }
        } catch (Exception $e) {
            logEvent("Failed to initialize MySQL schema: " . $e->getMessage(), 'ERROR');
            return false;
        }
        
        return false;
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
 * MySQL News Model - Database operations for news
 */
class MySQLNewsModel {
    private $db;

    public function __construct() {
        $this->db = MySQLDatabase::getInstance();
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
                WHERE title LIKE :query OR excerpt LIKE :query 
                ORDER BY 
                    CASE WHEN title LIKE :query THEN 1 ELSE 2 END,
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
 * MySQL Contact Model - Database operations for contact submissions
 */
class MySQLContactModel {
    private $db;

    public function __construct() {
        $this->db = MySQLDatabase::getInstance();
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
            ['status' => $status], 
            'id = :id', 
            ['id' => $id]
        );
    }
}

/**
 * MySQL Newsletter Model - Database operations for newsletter subscribers
 */
class MySQLNewsletterModel {
    private $db;

    public function __construct() {
        $this->db = MySQLDatabase::getInstance();
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
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
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

// Initialize MySQL database connection on include
try {
    $mysqlDb = MySQLDatabase::getInstance();
    // Initialize schema if tables don't exist
    if (!$mysqlDb->tableExists('news')) {
        $mysqlDb->initializeSchema();
    }
} catch (Exception $e) {
    logEvent("Failed to initialize MySQL database: " . $e->getMessage(), 'ERROR');
    // Fallback to file-based storage if database is not available
}
?>