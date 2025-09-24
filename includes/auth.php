<?php
class Auth {
    private $db;
    private $conn;

    public function __construct() {
        require_once ROOT_PATH . '/config/database.php';
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
    }

    public function login($username, $password) {
        $username = sanitize_input($username);
        
        $query = "SELECT id, username, password, full_name, role, active FROM users WHERE username = :username";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();

        if ($stmt->rowCount() == 1) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user['active'] != 1) {
                return false;
            }
            
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['last_activity'] = time();
                $_SESSION['login_time'] = date('Y-m-d H:i:s');
                
                // ثبت لاگ ورود
                $this->logLogin($user['id'], true, 'ورود موفق');
                return true;
            }
        }
        
        // ثبت لاگ ورود ناموفق
        $this->logLogin(null, false, 'نام کاربری یا رمز عبور اشتباه');
        return false;
    }

    public function isLoggedIn() {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['last_activity'])) {
            return false;
        }
        
        // بررسی timeout (30 دقیقه)
        if (time() - $_SESSION['last_activity'] > 1800) {
            $this->logout();
            return false;
        }
        
        $_SESSION['last_activity'] = time();
        return true;
    }

    public function logout() {
        if (isset($_SESSION['user_id'])) {
            $this->logLogin($_SESSION['user_id'], true, 'خروج از سیستم');
        }
        
        session_unset();
        session_destroy();
        session_start();
    }

    public function hasAccess($requiredRole) {
        if (!$this->isLoggedIn()) return false;
        if ($_SESSION['role'] == 'admin') return true;
        return $_SESSION['role'] == $requiredRole;
    }

    public function getCurrentUser() {
        if (!$this->isLoggedIn()) return null;
        
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'full_name' => $_SESSION['full_name'],
            'role' => $_SESSION['role']
        ];
    }

    private function logLogin($user_id, $success, $message) {
        $query = "INSERT INTO login_logs (user_id, success, message, ip_address, user_agent) 
                  VALUES (:user_id, :success, :message, :ip_address, :user_agent)";
        $stmt = $this->conn->prepare($query);
        
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':success', $success, PDO::PARAM_BOOL);
        $stmt->bindParam(':message', $message);
        $stmt->bindParam(':ip_address', $ip_address);
        $stmt->bindParam(':user_agent', $user_agent);
        
        $stmt->execute();
    }
}
?>