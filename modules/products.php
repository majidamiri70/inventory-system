
<?php
$page_title = "مدیریت محصولات";
$datatable = true;
require_once '../includes/header.php';

// ایجاد شیء Notification
$notification = new Notification($db);

$success = $error = '';

// افزودن محصول جدید
if (isset($_POST['add_product'])) {
    if (!CSRF::validateToken($_POST['csrf_token'])) {
        $error = "توکن امنیتی نامعتبر است";
    } else {
        $name = sanitize_input($_POST['name']);
        $sku = sanitize_input($_POST['sku']);
        $purchase_price = (float)$_POST['purchase_price'];
        $stock = (int)$_POST['stock'];
        $min_stock = (int)$_POST['min_stock'];
        $description = sanitize_input($_POST['description']);
        
        // اعتبارسنجی
        if (empty($name) || empty($sku) || $purchase_price <= 0) {
            $error = "لطفا اطلاعات ضروری را وارد کنید";
        } else {
            // محاسبه قیمت فروش بر اساس درصد سود
            $profit_margin = $settings['profit_margin'] ?? 20;
            $selling_price = $purchase_price * (1 + ($profit_margin / 100));
            
            try {
                $query = "INSERT INTO products (name, sku, purchase_price, selling_price, stock, min_stock, description, created_by) 
                          VALUES (:name, :sku, :purchase_price, :selling_price, :stock, :min_stock, :description, :created_by)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':sku', $sku);
                $stmt->bindParam(':purchase_price', $purchase_price);
                $stmt->bindParam(':selling_price', $selling_price);
                $stmt->bindParam(':stock', $stock);
                $stmt->bindParam(':min_stock', $min_stock);
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':created_by', $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    // اگر موجودی کم بود، اعلان ایجاد کن
                    if ($stock <= $min_stock) {
                        $notification->createNotification(
                            $_SESSION['user_id'],
                            'موجودی کم',
                            'محصول ' . $name . ' با موجودی کم (' . $stock . ') افزوده شد.',
                            'warning'
                        );
                    }
                    
                    $success = "محصول با موفقیت افزوده شد.";
                } else {
                    $error = "خطا در افزودن محصول.";
                }
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = "SKU تکراری است. لطفا SKU دیگری انتخاب کنید.";
                } else {
                    $error = "خطای دیتابیس: " . $e->getMessage();
                }
            }
        }
    }
}

// دریافت لیست محصولات - ✅ تغییر شده
try {
    $query = "SELECT p.*, u.username as created_by_name 
              FROM products p 
              LEFT JOIN users u ON p.created_by = u.id 
              ORDER BY p.id DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $products = [];
    error_log("Products error: " . $e->getMessage());
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-box-seam me-2"></i>مدیریت محصولات</h2>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
        <i class="bi bi-plus-circle me-1"></i>افزودن محصول
    </button>
</div>

<?php if (!empty($success)): ?>
<div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle me-2"></i><?php echo $success; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (!empty($error)): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <i class="bi bi-exclamation-triangle me-2"></i><?php echo $error; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">لیست محصولات</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="dataTable" class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>ردیف</th>
                        <th>نام محصول</th>
                        <th>SKU</th>
                        <th>قیمت خرید</th>
                        <th>قیمت فروش</th>
                        <th>موجودی</th>
                        <th>وضعیت</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $index => $product): 
                        $stock_status = '';
                        if ($product['stock'] == 0) {
                            $stock_status = '<span class="badge bg-danger">ناموجود</span>';
                        } elseif ($product['stock'] <= $product['min_stock']) {
                            $stock_status = '<span class="badge bg-warning">موجودی کم</span>';
                        } else {
                            $stock_status = '<span class="badge bg-success">موجود</span>';
                        }
                    ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                            <?php if (!empty($product['description'])): ?>
                            <br><small class="text-muted"><?php echo substr(htmlspecialchars($product['description']), 0, 50); ?>...</small>
                            <?php endif; ?>
                            <!-- ✅ این خط اضافه شد -->
                            <br><small class="text-info">📝 ثبت توسط: <?php echo $product['created_by_name'] ?? 'سیستم'; ?></small>
                        </td>
                        <td><code><?php echo htmlspecialchars($product['sku']); ?></code></td>
                        <td><?php echo number_format($product['purchase_price']); ?> تومان</td>
                        <td><?php echo number_format($product['selling_price']); ?> تومان</td>
                        <td>
                            <span class="<?php echo $product['stock'] <= $product['min_stock'] ? 'text-danger fw-bold' : ''; ?>">
                                <?php echo number_format($product['stock']); ?>
                            </span>
                        </td>
                        <td><?php echo $stock_status; ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="edit/edit_product.php?id=<?php echo $product['id']; ?>" 
                                   class="btn btn-info" title="ویرایش">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button class="btn btn-danger delete-product" 
                                        data-id="<?php echo $product['id']; ?>" 
                                        data-name="<?php echo htmlspecialchars($product['name']); ?>"
                                        title="حذف">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- مودال افزودن محصول -->
<div class="modal fade" id="addProductModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">افزودن محصول جدید</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <?php echo CSRF::getTokenField(); ?>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">نام محصول <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" required 
                                       placeholder="نام کامل محصول">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="sku" class="form-label">کد محصول (SKU) <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="sku" name="sku" required 
                                       placeholder="کد یکتا محصول">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="purchase_price" class="form-label">قیمت خرید <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="purchase_price" name="purchase_price" 
                                           step="100" min="0" required placeholder="0">
                                    <span class="input-group-text">تومان</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="stock" class="form-label">موجودی اولیه <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="stock" name="stock" 
                                       min="0" required value="0">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="min_stock" class="form-label">حداقل موجودی <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="min_stock" name="min_stock" 
                                       min="0" required value="5">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">توضیحات</label>
                        <textarea class="form-control" id="description" name="description" rows="3" 
                                  placeholder="توضیحات اختیاری درباره محصول"></textarea>
                    </div>
                    
                    <div class="alert alert-info">
                        <small>
                            <i class="bi bi-info-circle me-1"></i>
                            قیمت فروش به صورت خودکار با اعمال <?php echo $settings['profit_margin'] ?? 20; ?>٪ سود محاسبه خواهد شد.
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="submit" name="add_product" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i>ذخیره محصول
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// مدیریت حذف محصول - نسخه ساده شده
$(document).on('click', '.delete-product', function() {
    const productId = $(this).data('id');
    const productName = $(this).data('name');
    
    if (confirm(`آیا از حذف محصول "${productName}" اطمینان دارید؟`)) {
        // حالا تابع deleteItem به صورت global در دسترس است
        if (typeof deleteItem === 'function') {
            deleteItem('product', productId, productName);
        } else {
            // fallback اگر تابع وجود نداشت
            alert('تابع حذف در دسترس نیست. لطفا صفحه را رفرش کنید.');
        }
    }
});






function deleteItem(type, id, name = '') {
    const button = $(`.delete-${type}[data-id="${id}"]`);
    const originalHTML = button.html();
    
    // نمایش loading
    button.html('<i class="bi bi-hourglass-split"></i> در حال حذف...');
    button.prop('disabled', true);
    
    // ساخت URL - دیباگ کنید
    const url = `modules/delete/delete_${type}.php`;
    console.log('📤 ارسال درخواست به:', url);
    console.log('📦 داده‌های ارسالی:', { id, action: 'delete' });
    
    $.ajax({
        url: url,
        method: 'POST',
        data: { 
            id: id,
            action: 'delete'
        },
        success: function(response) {
            console.log('✅ پاسخ خام سرور:', response);
            console.log('📊 نوع پاسخ:', typeof response);
            
            // بررسی اگر پاسخ HTML است (با خطا شروع شود)
            if (response.trim().startsWith('<!DOCTYPE') || response.trim().startsWith('<')) {
                console.error('❌ سرور صفحه HTML برگردانده (خطای 404/500)');
                showNotification('خطا در سرور: فایل حذف یافت نشد یا خطای PHP دارد', 'danger');
                button.html(originalHTML);
                button.prop('disabled', false);
                return;
            }
            
            try {
                const result = JSON.parse(response);
                console.log('✅ JSON پارس شده:', result);
                
                if (result.success) {
                    showNotification(result.message, 'success', 3000);
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    showNotification(result.message, 'warning', 5000);
                    button.html(originalHTML);
                    button.prop('disabled', false);
                }
            } catch (e) {
                console.error('❌ خطای پردازش JSON:', e);
                console.error('❌ پاسخ مشکل‌دار:', response.substring(0, 200) + '...');
                showNotification('پاسخ سرور معتبر نیست. لطفا کنسول را بررسی کنید.', 'danger');
                button.html(originalHTML);
                button.prop('disabled', false);
            }
        },
        error: function(xhr, status, error) {
            console.error('❌ خطای AJAX:');
            console.error('Status:', status);
            console.error('Error:', error);
            console.error('Response Text:', xhr.responseText.substring(0, 500));
            
            showNotification(`خطای شبکه: ${error} (کد: ${xhr.status})`, 'danger');
            button.html(originalHTML);
            button.prop('disabled', false);
        }
    });
}
</script>


<?php
require_once '../includes/footer.php';
?>