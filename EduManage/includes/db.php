<?php
// Database Connection
class Database {
    private $host = DB_HOST;
    private $db_name = DB_NAME;
    private $user = DB_USER;
    private $pass = DB_PASS;
    private $conn;

    public function connect() {
        $this->conn = new mysqli($this->host, $this->user, $this->pass, $this->db_name);

        if ($this->conn->connect_error) {
            die('Connection Error: ' . $this->conn->connect_error);
        }

        $this->conn->set_charset("utf8");
        return $this->conn;
    }

    public function getConnection() {
        return $this->conn;
    }
}

// Initialize Database
$database = new Database();
$conn = $database->connect();
?>