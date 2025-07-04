<?php
// 1. KONFIGURASI DAN KONEKSI DATABASE
class Database {
    private $host = 'localhost';
    private $db_name = 'db_pos_uas';
    private $username = 'root';
    private $password = '';
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $exception) {
            die("Kesalahan Koneksi Database: " . $exception->getMessage());
        }
        return $this->conn;
    }
}
