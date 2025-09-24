<?php
class Notification {
    private $db;
    
    public function __construct($db_connection) {
        $this->db = $db_connection;
    }
    
    // دریافت اعلان‌های موجودی کم
    public function getLowStockNotifications() {
        $query = "SELECT name, stock, min_stock 
                  FROM products 
                  WHERE stock <= min_stock AND stock > 0
                  ORDER BY stock ASC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // دریافت چک‌های نزدیک به سررسید
    public function getDueChecksNotifications() {
        $query = "SELECT check_number, bank_name, amount, due_date 
                  FROM checks 
                  WHERE status = 'pending' 
                  AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                  ORDER BY due_date ASC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // دریافت فاکتورهای پرداخت نشده قدیمی
    public function getOverdueInvoices() {
        $query = "SELECT i.id, i.total, c.name as customer_name, i.invoice_date 
                  FROM invoices i 
                  LEFT JOIN customers c ON i.customer_id = c.id 
                  WHERE i.status = 'pending' 
                  AND i.invoice_date < DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                  ORDER BY i.invoice_date ASC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // ایجاد اعلان جدید
    public function createNotification($user_id, $title, $message, $type = 'info') {
        $query = "INSERT INTO notifications (user_id, title, message, type, is_read) 
                  VALUES (:user_id, :title, :message, :type, 0)";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':message', $message);
        $stmt->bindParam(':type', $type);
        
        return $stmt->execute();
    }
    
    // دریافت اعلان‌های کاربر
    public function getUserNotifications($user_id, $unread_only = false) {
        $query = "SELECT * FROM notifications 
                  WHERE user_id = :user_id " . 
                  ($unread_only ? "AND is_read = 0" : "") . 
                  " ORDER BY created_at DESC 
                  LIMIT 50";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // علامت گذاری به عنوان خوانده شده
    public function markAsRead($notification_id, $user_id) {
        $query = "UPDATE notifications SET is_read = 1 
                  WHERE id = :id AND user_id = :user_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $notification_id);
        $stmt->bindParam(':user_id', $user_id);
        
        return $stmt->execute();
    }
    
    // علامت گذاری همه به عنوان خوانده شده
    public function markAllAsRead($user_id) {
        $query = "UPDATE notifications SET is_read = 1 
                  WHERE user_id = :user_id AND is_read = 0";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        
        return $stmt->execute();
    }
    
    // حذف اعلان
    public function deleteNotification($notification_id, $user_id) {
        $query = "DELETE FROM notifications 
                  WHERE id = :id AND user_id = :user_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $notification_id);
        $stmt->bindParam(':user_id', $user_id);
        
        return $stmt->execute();
    }
    
    // بررسی اعلان‌های سیستم
    public function checkSystemNotifications() {
        $notifications = [];
        
        // بررسی موجودی کم
        $low_stock = $this->getLowStockNotifications();
        if (!empty($low_stock)) {
            foreach ($low_stock as $product) {
                $notifications[] = [
                    'type' => 'warning',
                    'title' => 'موجودی کم',
                    'message' => 'موجودی ' . $product['name'] . ' به ' . $product['stock'] . ' عدد رسیده است.'
                ];
            }
        }
        
        // بررسی چک‌های سررسید شده
        $due_checks = $this->getDueChecksNotifications();
        if (!empty($due_checks)) {
            foreach ($due_checks as $check) {
                $due_date = new DateTime($check['due_date']);
                $today = new DateTime();
                $days_until_due = $today->diff($due_date)->days;
                
                $notifications[] = [
                    'type' => 'danger',
                    'title' => 'چک نزدیک به سررسید',
                    'message' => 'چک شماره ' . $check['check_number'] . ' به مبلغ ' . number_format($check['amount']) . ' تومان تا ' . $days_until_due . ' روز دیگر سررسید می‌شود.'
                ];
            }
        }
        
        // بررسی فاکتورهای معوقه
        $overdue_invoices = $this->getOverdueInvoices();
        if (!empty($overdue_invoices)) {
            foreach ($overdue_invoices as $invoice) {
                $notifications[] = [
                    'type' => 'danger',
                    'title' => 'فاکتور معوقه',
                    'message' => 'فاکتور شماره ' . $invoice['id'] . ' مشتری ' . $invoice['customer_name'] . ' به مبلغ ' . number_format($invoice['total']) . ' تومان بیش از 30 روز معوقه است.'
                ];
            }
        }
        
        return $notifications;
    }
    
    // دریافت آمار اعلان‌ها
    public function getNotificationStats($user_id) {
        $query = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread,
                    SUM(CASE WHEN type = 'warning' THEN 1 ELSE 0 END) as warnings,
                    SUM(CASE WHEN type = 'danger' THEN 1 ELSE 0 END) as dangers
                  FROM notifications 
                  WHERE user_id = :user_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // پاک کردن اعلان‌های قدیمی
    public function cleanupOldNotifications($days = 30) {
        $query = "DELETE FROM notifications 
                  WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':days', $days, PDO::PARAM_INT);
        
        return $stmt->execute();
    }
}

// تابع کمکی برای نمایش اعلان‌ها
function displayNotification($type, $title, $message) {
    $icons = [
        'success' => '✅',
        'warning' => '⚠️',
        'danger' => '❌',
        'info' => 'ℹ️'
    ];
    
    $icon = $icons[$type] ?? $icons['info'];
    
    return "
    <div class='alert alert-{$type} alert-dismissible fade show'>
        <strong>{$icon} {$title}:</strong> {$message}
        <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
    </div>";
}
?>