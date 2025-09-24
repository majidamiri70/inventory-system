<?php
$page_title = "مشاهده فاکتور";
require_once '../includes/header.php';

$invoice_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$invoice = null;
$invoice_items = [];
$customer_name = 'نامشخص';

// دریافت اطلاعات فاکتور
if ($invoice_id > 0) {
    try {
        // اطلاعات فاکتور
        $query = "SELECT i.*, c.name as customer_name, c.phone, c.address 
                  FROM invoices i 
                  LEFT JOIN customers c ON i.customer_id = c.id 
                  WHERE i.id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $invoice_id, PDO::PARAM_INT);
        $stmt->execute();
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($invoice) {
            $customer_name = $invoice['customer_name'];
            
            // آیتم‌های فاکتور
            $items_query = "SELECT ii.*, p.name as product_name, p.sku, p.description
                            FROM invoice_items ii 
                            LEFT JOIN products p ON ii.product_id = p.id 
                            WHERE ii.invoice_id = :invoice_id";
            $items_stmt = $db->prepare($items_query);
            $items_stmt->bindParam(':invoice_id', $invoice_id, PDO::PARAM_INT);
            $items_stmt->execute();
            $invoice_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        $error = "خطا در دریافت اطلاعات فاکتور: " . $e->getMessage();
        error_log("View invoice error: " . $e->getMessage());
    }
}

if (!$invoice) {
    echo '<div class="alert alert-danger">فاکتور یافت نشد</div>';
    echo '<a href="invoices.php" class="btn btn-secondary">بازگشت به لیست فاکتورها</a>';
    require_once '../includes/footer.php';
    exit;
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-receipt me-2"></i>فاکتور شماره #<?php echo $invoice['id']; ?></h2>
    <div>
        <button onclick="window.print()" class="btn btn-primary me-2">
            <i class="bi bi-printer me-1"></i> چاپ فاکتور
        </button>
        <a href="invoices.php" class="btn btn-secondary">
            <i class="bi bi-arrow-right me-1"></i> بازگشت
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">اطلاعات فاکتور</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-5 fw-bold">
                        <p>شماره فاکتور:</p>
                        <p>تاریخ فاکتور:</p>
                        <p>مشتری:</p>
                        <p>تلفن مشتری:</p>
                        <p>وضعیت:</p>
                    </div>
                    <div class="col-7">
                        <p>#<?php echo $invoice['id']; ?></p>
                        <p><?php echo jdate('Y/m/d', strtotime($invoice['invoice_date'])); ?></p>
                        <p><?php echo htmlspecialchars($customer_name); ?></p>
                        <p><?php echo $invoice['phone'] ? htmlspecialchars($invoice['phone']) : 'ثبت نشده'; ?></p>
                        <p>
                            <span class="badge bg-<?php echo $invoice['status'] == 'paid' ? 'success' : 'warning'; ?>">
                                <?php echo $invoice['status'] == 'paid' ? 'پرداخت شده' : 'در انتظار پرداخت'; ?>
                            </span>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="card-title mb-0">خلاصه مالی</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-6 fw-bold">
                        <p>جمع کل:</p>
                        <p>مالیات:</p>
                        <p>تخفیف:</p>
                        <p class="fs-5">مبلغ نهایی:</p>
                    </div>
                    <div class="col-6">
                        <p><?php echo number_format($invoice['subtotal']); ?> تومان</p>
                        <p><?php echo number_format($invoice['tax']); ?> تومان</p>
                        <p><?php echo number_format($invoice['discount']); ?> تومان</p>
                        <p class="fs-5 fw-bold text-success"><?php echo number_format($invoice['total']); ?> تومان</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-info text-white">
        <h5 class="card-title mb-0">آیتم‌های فاکتور</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ردیف</th>
                        <th>محصول</th>
                        <th>کد محصول</th>
                        <th>تعداد</th>
                        <th>قیمت واحد</th>
                        <th>جمع</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoice_items as $index => $item): ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($item['product_name']); ?></strong>
                            <?php if (!empty($item['description'])): ?>
                            <br><small class="text-muted"><?php echo htmlspecialchars($item['description']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><code><?php echo htmlspecialchars($item['sku']); ?></code></td>
                        <td><?php echo number_format($item['quantity']); ?></td>
                        <td><?php echo number_format($item['unit_price']); ?> تومان</td>
                        <td><?php echo number_format($item['total_price']); ?> تومان</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="table-secondary">
                        <td colspan="5" class="text-end fw-bold">جمع کل:</td>
                        <td class="fw-bold"><?php echo number_format($invoice['subtotal']); ?> تومان</td>
                    </tr>
                    <tr class="table-secondary">
                        <td colspan="5" class="text-end fw-bold">مالیات:</td>
                        <td class="fw-bold">+ <?php echo number_format($invoice['tax']); ?> تومان</td>
                    </tr>
                    <tr class="table-secondary">
                        <td colspan="5" class="text-end fw-bold">تخفیف:</td>
                        <td class="fw-bold">- <?php echo number_format($invoice['discount']); ?> تومان</td>
                    </tr>
                    <tr class="table-success">
                        <td colspan="5" class="text-end fw-bold fs-5">مبلغ نهایی:</td>
                        <td class="fw-bold fs-5"><?php echo number_format($invoice['total']); ?> تومان</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php if (!empty($invoice['address'])): ?>
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">آدرس مشتری</h5>
    </div>
    <div class="card-body">
        <p class="card-text"><?php echo htmlspecialchars($invoice['address']); ?></p>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($invoice['notes'])): ?>
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">توضیحات</h5>
    </div>
    <div class="card-body">
        <p class="card-text"><?php echo nl2br(htmlspecialchars($invoice['notes'])); ?></p>
    </div>
</div>
<?php endif; ?>

<style>
@media print {
    .btn, .card-header, .d-print-none, .sidebar, .navbar {
        display: none !important;
    }
    .card {
        border: 1px solid #000 !important;
        box-shadow: none !important;
        margin-bottom: 20px !important;
    }
    body {
        font-size: 12px;
        background: white !important;
    }
    .table {
        font-size: 10px;
    }
}

/* استایل‌های زیبا برای نمایش */
.invoice-header {
    border-bottom: 2px solid #3498db;
    padding-bottom: 10px;
    margin-bottom: 20px;
}
</style>

<script>
// تابع تبدیل تاریخ میلادی به شمسی (ساده)
function jdate(format, timestamp) {
    if (!timestamp) timestamp = new Date();
    const date = new Date(timestamp);
    return date.toLocaleDateString('fa-IR');
}

// مدیریت چاپ
function printInvoice() {
    window.print();
}

// دانلود فاکتور به صورت PDF (قابلیت آینده)
function downloadPDF() {
    alert('قابلیت دانلود PDF به زودی اضافه خواهد شد');
}
</script>

<?php
require_once '../includes/footer.php';
?>