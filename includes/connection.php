<?php
require_once '../config/database.php';

class Database {
    private $host = 'localhost';
    private $db_name = 'spk_kwarcan';
    private $username = 'root';
    private $password = '';
    private $connection;
    private $charset = 'utf8mb4';
    
    
    public function __construct() {
        $this->connect();
    }
    
    private function connect() {
        try {
            $this->connection = new PDO(
                "mysql:host={$this->host};dbname={$this->db_name};charset=utf8",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            die("Koneksi database gagal: " . $e->getMessage());
        }
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    // Test koneksi
    public function testConnection() {
        try {
            $stmt = $this->connection->query("SELECT 1");
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
}

// Instance global
$db = new Database();
$conn = $db->getConnection();
?>