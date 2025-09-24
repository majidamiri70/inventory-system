<?php
require_once '../../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// بررسی دسترسی ادمین
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'دسترسی غیر مجاز'], JSON_UNESCAPED_UNICODE);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

if (!file_exists('../../config/database.php')) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'فایل پیکربندی دیتابیس یافت نشد'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once '../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        // اعتبارسنجی CSRF Token
        if (!isset($_POST['csrf_token']) || !CSRF::validateToken($_POST['csrf_token'])) {
            echo json_encode(['success' => false, 'message' => 'توکن امنیتی نامعتبر'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        if (!isset($_POST['action']) || $_POST['action'] !== 'delete') {
            echo json_encode(['success' => false, 'message' => 'Action نامعتبر'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $customer_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        
        if ($customer_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID مشتری نامعتبر'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // بررسی وجود مشتری
        $check_query = "SELECT id, name FROM customers WHERE id = :id";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':id', $customer_id, PDO::PARAM_INT);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'message' => 'مشتری یافت نشد'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $customer = $check_stmt->fetch(PDO::FETCH_ASSOC);
        $customer_name = $customer['name'];
        
        // بررسی وجود مشتری در فاکتورها
        $check_invoices_query = "SELECT COUNT(*) as count FROM invoices WHERE customer_id = :customer_id";
        $check_invoices_stmt = $db->prepare($check_invoices_query);
        $check_invoices_stmt->bindParam(':customer_id', $customer_id, PDO::PARAM_INT);
        $check_invoices_stmt->execute();
        $invoice_usage_count = $check_invoices_stmt->fetch()['count'];
        
        // بررسی وجود مشتری در چک‌ها
        $check_checks_query = "SELECT COUNT(*) as count FROM checks WHERE customer_id = :customer_id";
        $check_checks_stmt = $db->prepare($check_checks_query);
        $check_checks_stmt->bindParam(':customer_id', $customer_id, PDO::PARAM_INT);
        $check_checks_stmt->execute();
        $checks_usage_count = $check_checks_stmt->fetch()['count'];
        
        $total_usage = $invoice_usage_count + $checks_usage_count;
        
        if ($total_usage > 0) {
            http_response_code(409); // Conflict
            echo json_encode([
                'success' => false, 
                'message' => 'این مشتری در ' . $invoice_usage_count . ' فاکتور و ' . $checks_usage_count . ' چک استفاده شده و قابل حذف نیست',
                'invoice_count' => $invoice_usage_count,
                'check_count' => $checks_usage_count,
                'total_usage' => $total_usage
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // حذف مشتری
        $delete_query = "DELETE FROM customers WHERE id = :id";
        $delete_stmt = $db->prepare($delete_query);
        $delete_stmt->bindParam(':id', $customer_id, PDO::PARAM_INT);
        
        if ($delete_stmt->execute()) {
            // ایجاد اعلان برای حذف مشتری
            require_once '../notifications.php';
            $notification = new Notification($db);
            $notification->createNotification(
                $_SESSION['user_id'],
                'حذف مشتری',
                'مشتری "' . $customer_name . '" از سیستم حذف شد',
                'warning'
            );
            
            echo json_encode([
                'success' => true, 
                'message' => 'مشتری "' . $customer_name . '" با موفقیت حذف شد',
                'customer_name' => $customer_name
            ], JSON_UNESCAPED_UNICODE);
        } else {
            throw new Exception('خطا در اجرای حذف مشتری');
        }
        
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'متد درخواست نامعتبر'], JSON_UNESCAPED_UNICODE);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'خطا: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

exit;
?>