<?php
// config/config.php
session_start();

// تنظیمات پایه
define('BASE_URL', 'http://localhost/inventory-system');
define('ROOT_PATH', dirname(__DIR__));
define('SITE_NAME', 'سیستم مدیریت انبار');

// تنظیمات دیتابیس
define('DB_HOST', 'localhost');
define('DB_NAME', 'inventory_accounting2');
define('DB_USER', 'root');
define('DB_PASS', '');

// تنظیمات امنیتی
define('CSRF_TOKEN_NAME', 'csrf_token');

// auto-loader برای کلاس‌ها
spl_autoload_register(function($class_name) {
    $file = ROOT_PATH . '/classes/' . $class_name . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// مدیریت خطاها
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("Error [$errno]: $errstr in $errfile on line $errline");
    if (ini_get('display_errors')) {
        echo "<div class='alert alert-danger'>خطای سیستم رخ داده است</div>";
    }
    return true;
});

// تابع برای جلوگیری از XSS
function sanitize_input($data) {
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// تابع برای ریدایرکت
function redirect($url) {
    header("Location: $url");
    exit;
}

// بررسی آیا نصب شده است
function is_installed() {
    return file_exists(ROOT_PATH . '/config/database.php');
}
?>