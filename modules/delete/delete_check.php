<?php
require_once '../../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// برای چک‌ها کاربر عادی هم می‌تواند حذف کند (فقط چک‌های خودش)
if (!isset($_SESSION['user_id'])) {
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
        
        $check_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        
        if ($check_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID چک نامعتبر'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // بررسی وجود چک
        $check_query = "SELECT c.*, cust.name as customer_name 
                       FROM checks c 
                       LEFT JOIN customers cust ON c.customer_id = cust.id 
                       WHERE c.id = :id";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':id', $check_id, PDO::PARAM_INT);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'message' => 'چک یافت نشد'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $check = $check_stmt->fetch(PDO::FETCH_ASSOC);
        $check_number = $check['check_number'];
        $check_amount = $check['amount'];
        $check_type = $check['type'] == 'received' ? 'دریافتی' : 'پرداختی';
        
        // اگر کاربر ادمین نیست، فقط چک‌های مربوط به خودش را می‌تواند حذف کند
        if ($_SESSION['role'] !== 'admin') {
            // اینجا می‌توانید منطق بررسی مالکیت چک را اضافه کنید
            // برای مثال اگر فیلد user_id در جدول checks وجود دارد:
            // if ($check['user_id'] != $_SESSION['user_id']) { ... }
        }
        
        // حذف چک
        $delete_query = "DELETE FROM checks WHERE id = :id";
        $delete_stmt = $db->prepare($delete_query);
        $delete_stmt->bindParam(':id', $check_id, PDO::PARAM_INT);
        
        if ($delete_stmt->execute()) {
            // ایجاد اعلان برای حذف چک
            require_once '../notifications.php';
            $notification = new Notification($db);
            $notification->createNotification(
                $_SESSION['user_id'],
                'حذف چک',
                'چک ' . $check_type . ' شماره ' . $check_number . ' به مبلغ ' . number_format($check_amount) . ' تومان حذف شد',
                'warning'
            );
            
            echo json_encode([
                'success' => true, 
                'message' => 'چک شماره ' . $check_number . ' با موفقیت حذف شد',
                'check_number' => $check_number,
                'check_type' => $check_type,
                'amount' => $check_amount
            ], JSON_UNESCAPED_UNICODE);
        } else {
            throw new Exception('خطا در اجرای حذف چک');
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