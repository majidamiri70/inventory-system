<?php
require_once '../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'لطفا ابتدا وارد شوید'], JSON_UNESCAPED_UNICODE);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

if (!file_exists('../config/database.php')) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'فایل پیکربندی دیتابیس یافت نشد'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        // اعتبارسنجی CSRF Token
        if (!isset($_POST['csrf_token']) || !CSRF::validateToken($_POST['csrf_token'])) {
            echo json_encode(['success' => false, 'message' => 'توکن امنیتی نامعتبر'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        if (!isset($_POST['action']) || $_POST['action'] !== 'change_status') {
            echo json_encode(['success' => false, 'message' => 'Action نامعتبر'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $invoice_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $new_status = isset($_POST['status']) ? $_POST['status'] : '';
        
        if ($invoice_id <= 0 || !in_array($new_status, ['paid', 'pending'])) {
            echo json_encode(['success' => false, 'message' => 'داده‌های ورودی نامعتبر'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // بررسی وجود فاکتور
        $check_query = "SELECT i.*, c.name as customer_name 
                       FROM invoices i 
                       LEFT JOIN customers c ON i.customer_id = c.id 
                       WHERE i.id = :id";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':id', $invoice_id, PDO::PARAM_INT);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'message' => 'فاکتور یافت نشد'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $invoice = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        // اگر وضعیت تغییر نکرده باشد
        if ($invoice['status'] === $new_status) {
            echo json_encode(['success' => false, 'message' => 'وضعیت فاکتور قبلاً تغییر کرده است'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // تغییر وضعیت
        $db->beginTransaction();
        
        try {
            $update_query = "UPDATE invoices SET status = :status, updated_at = NOW() WHERE id = :id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':status', $new_status);
            $update_stmt->bindParam(':id', $invoice_id, PDO::PARAM_INT);
            
            if ($update_stmt->execute()) {
                // ایجاد اعلان برای تغییر وضعیت
                require_once 'notifications.php';
                $notification = new Notification($db);
                
                $status_text = $new_status == 'paid' ? 'پرداخت شده' : 'در انتظار پرداخت';
                $message = "وضعیت فاکتور شماره #{$invoice_id} مشتری {$invoice['customer_name']} به '{$status_text}' تغییر کرد";
                
                $notification->createNotification(
                    $_SESSION['user_id'],
                    'تغییر وضعیت فاکتور',
                    $message,
                    $new_status == 'paid' ? 'success' : 'warning'
                );
                
                $db->commit();
                
                $status_text = $new_status == 'paid' ? 'پرداخت شده' : 'در انتظار پرداخت';
                echo json_encode([
                    'success' => true, 
                    'message' => 'وضعیت فاکتور به ' . $status_text . ' تغییر کرد',
                    'new_status' => $new_status,
                    'status_text' => $status_text,
                    'invoice_id' => $invoice_id
                ], JSON_UNESCAPED_UNICODE);
            } else {
                throw new Exception('خطا در تغییر وضعیت فاکتور');
            }
            
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
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