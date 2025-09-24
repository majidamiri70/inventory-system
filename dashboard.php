<?php
$page_title = "داشبورد";
require_once 'includes/header.php';

// اتصال به دیتابیس
$database = new Database();
$db = $database->getConnection();

// دریافت آمار کلی
try {
    // تعداد محصولات
    $products_count = $db->query("SELECT COUNT(*) as count FROM products")->fetch()['count'];
    
    // تعداد مشتریان
    $customers_count = $db->query("SELECT COUNT(*) as count FROM customers")->fetch()['count'];
    
    // فاکتورهای امروز
    $invoices_today = $db->query("SELECT COUNT(*) as count FROM invoices WHERE DATE(created_at) = CURDATE()")->fetch()['count'];
    
    // موجودی کم
    $low_stock_count = $db->query("SELECT COUNT(*) as count FROM products WHERE stock <= min_stock")->fetch()['count'];
    
    // جمع فروش امروز
    $sales_today = $db->query("SELECT COALESCE(SUM(total), 0) as total FROM invoices WHERE DATE(created_at) = CURDATE() AND status = 'paid'")->fetch()['total'];
    
    // چک‌های نزدیک به سررسید
    $due_checks = $db->query("SELECT COUNT(*) as count FROM checks WHERE status = 'pending' AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)")->fetch()['count'];
    
} catch (Exception $e) {
    $products_count = $customers_count = $invoices_today = $low_stock_count = $sales_today = $due_checks = 0;
    error_log("Dashboard error: " . $e->getMessage());
}
?>

<div class="row">
    <!-- کارت آمار محصولات -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            تعداد محصولات</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($products_count); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-box-seam fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- کارت آمار مشتریان -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            تعداد مشتریان</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($customers_count); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-people fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- کارت فاکتورهای امروز -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            فاکتورهای امروز</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($invoices_today); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-receipt fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- کارت موجودی کم -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            هشدار موجودی</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($low_stock_count); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-exclamation-triangle fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- کارت فروش امروز -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-danger shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                            فروش امروز</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($sales_today); ?> تومان</div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-currency-dollar fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- کارت چک‌های سررسید -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-secondary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">
                            چک‌های نزدیک سررسید</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($due_checks); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-clock fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">آخرین فاکتورها</h5>
            </div>
            <div class="card-body">
                <?php
                try {
                    $recent_invoices = $db->query("
                        SELECT i.*, c.name as customer_name 
                        FROM invoices i 
                        LEFT JOIN customers c ON i.customer_id = c.id 
                        ORDER BY i.created_at DESC 
                        LIMIT 5
                    ")->fetchAll();
                    
                    if (!empty($recent_invoices)) {
                        echo '<div class="table-responsive">';
                        echo '<table class="table table-striped">';
                        echo '<thead><tr><th>شماره</th><th>مشتری</th><th>تاریخ</th><th>مبلغ</th><th>وضعیت</th></tr></thead>';
                        echo '<tbody>';
                        foreach ($recent_invoices as $invoice) {
                            $status_badge = $invoice['status'] == 'paid' ? 
                                '<span class="badge bg-success">پرداخت شده</span>' : 
                                '<span class="badge bg-warning">در انتظار</span>';
                            
                            echo "<tr>
                                <td>#{$invoice['id']}</td>
                                <td>{$invoice['customer_name']}</td>
                                <td>" . date('Y/m/d', strtotime($invoice['invoice_date'])) . "</td>
                                <td>" . number_format($invoice['total']) . " تومان</td>
                                <td>{$status_badge}</td>
                            </tr>";
                        }
                        echo '</tbody></table></div>';
                    } else {
                        echo '<p class="text-center text-muted">هیچ فاکتوری ثبت نشده است</p>';
                    }
                } catch (Exception $e) {
                    echo '<p class="text-center text-danger">خطا در بارگذاری اطلاعات</p>';
                }
                ?>
            </div>
        </div>
    </div>
</div>

<div class="alert alert-info mt-4">
    <h6><i class="bi bi-info-circle me-2"></i>راهنمای سریع</h6>
    <ul class="mb-0">
        <li>برای افزودن محصول جدید از منوی <strong>محصولات</strong> استفاده کنید</li>
        <li>برای ثبت فاکتور جدید به بخش <strong>فاکتورها</strong> مراجعه کنید</li>
        <li>گزارشات کامل در بخش <strong>گزارشات</strong> قابل مشاهده است</li>
    </ul>
</div>

<?php
require_once 'includes/footer.php';
?>