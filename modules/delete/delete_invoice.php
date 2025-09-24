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
        
        $invoice_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        
        if ($invoice_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID فاکتور نامعتبر'], JSON_UNESCAPED_UNICODE);
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
        
        // شروع تراکنش برای حذف ایمن
        $db->beginTransaction();
        
        try {
            // 1. بازگرداندن موجودی محصولات
            $items_query = "SELECT product_id, quantity FROM invoice_items WHERE invoice_id = :invoice_id";
            $items_stmt = $db->prepare($items_query);
            $items_stmt->bindParam(':invoice_id', $invoice_id, PDO::PARAM_INT);
            $items_stmt->execute();
            $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($items as $item) {
                $restore_query = "UPDATE products SET stock = stock + :quantity WHERE id = :product_id";
                $restore_stmt = $db->prepare($restore_query);
                $restore_stmt->bindParam(':quantity', $item['quantity'], PDO::PARAM_INT);
                $restore_stmt->bindParam(':product_id', $item['product_id'], PDO::PARAM_INT);
                $restore_stmt->execute();
                
                // بررسی و ایجاد اعلان برای موجودی کم
                $check_stock_query = "SELECT name, stock, min_stock FROM products WHERE id = :product_id";
                $check_stock_stmt = $db->prepare($check_stock_query);
                $check_stock_stmt->bindParam(':product_id', $item['product_id'], PDO::PARAM_INT);
                $check_stock_stmt->execute();
                $product = $check_stock_stmt->fetch();
                
                if ($product && $product['stock'] <= $product['min_stock']) {
                    require_once '../notifications.php';
                    $notification = new Notification($db);
                    $notification->createNotification(
                        $_SESSION['user_id'],
                        'موجودی کم',
                        'موجودی محصول ' . $product['name'] . ' پس از بازگشت فاکتور به ' . $product['stock'] . ' کاهش یافت',
                        'warning'
                    );
                }
            }
            
            // 2. حذف آیتم‌های فاکتور
            $delete_items_query = "DELETE FROM invoice_items WHERE invoice_id = :invoice_id";
            $delete_items_stmt = $db->prepare($delete_items_query);
            $delete_items_stmt->bindParam(':invoice_id', $invoice_id, PDO::PARAM_INT);
            $delete_items_stmt->execute();
            
            // 3. حذف فاکتور
            $delete_invoice_query = "DELETE FROM invoices WHERE id = :invoice_id";
            $delete_invoice_stmt = $db->prepare($delete_invoice_query);
            $delete_invoice_stmt->bindParam(':invoice_id', $invoice_id, PDO::PARAM_INT);
            $delete_invoice_stmt->execute();
            
            $db->commit();
            
            // ایجاد اعلان برای حذف فاکتور
            require_once '../notifications.php';
            $notification = new Notification($db);
            $notification->createNotification(
                $_SESSION['user_id'],
                'حذف فاکتور',
                'فاکتور شماره ' . $invoice_id . ' مشتری ' . $invoice['customer_name'] . ' به مبلغ ' . number_format($invoice['total']) . ' تومان حذف شد',
                'danger'
            );
            
            echo json_encode([
                'success' => true, 
                'message' => 'فاکتور شماره ' . $invoice_id . ' با موفقیت حذف شد و موجودی محصولات بازگردانی شد',
                'invoice_id' => $invoice_id,
                'customer_name' => $invoice['customer_name'],
                'total_amount' => $invoice['total']
            ], JSON_UNESCAPED_UNICODE);
            
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