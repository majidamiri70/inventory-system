<?php

class Database {
    private $host = DB_HOST;
    private $db_name = DB_NAME;
    private $username = DB_USER;
    private $password = DB_PASS;
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4", 
                $this->username, 
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        } catch(PDOException $exception) {
            throw new Exception("Connection error: " . $exception->getMessage());
        }
        return $this->conn;
    }

    public function backup($file_path) {
        try {
            $command = "mysqldump --user={$this->username} --password={$this->password} --host={$this->host} {$this->db_name} > {$file_path}";
            system($command, $output);
            return $output === 0;
        } catch (Exception $e) {
            error_log("Backup error: " . $e->getMessage());
            return false;
        }
    }

    public function restore($file_path) {
        try {
            $command = "mysql --user={$this->username} --password={$this->password} --host={$this->host} {$this->db_name} < {$file_path}";
            system($command, $output);
            return $output === 0;
        } catch (Exception $e) {
            error_log("Restore error: " . $e->getMessage());
            return false;
        }
    }

    public function checkConnection() {
        try {
            $conn = $this->getConnection();
            return $conn !== null;
        } catch (Exception $e) {
            return false;
        }
    }
}
?>