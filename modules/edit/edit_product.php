<!DOCTYPE html>
<?php
$page_title = "ویرایش محصول";
require_once '../../includes/header.php';

// بررسی دسترسی
if ($_SESSION['role'] != 'admin') {
    header("Location: ../products.php");
    exit;
}

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$product = null;
$success = $error = '';

// دریافت اطلاعات محصول
if ($product_id > 0) {
    try {
        $query = "SELECT * FROM products WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $product_id, PDO::PARAM_INT);
        $stmt->execute();
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error = "خطا در دریافت اطلاعات محصول: " . $e->getMessage();
    }
}

if (!$product) {
    header("Location: ../products.php");
    exit;
}

// تبدیل مقادیر اعشاری به عدد صحیح برای نمایش
$purchase_price = (int)$product['purchase_price'];
$selling_price = (int)$product['selling_price'];

// ویرایش محصول
if (isset($_POST['update_product'])) {
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
            // بررسی تکراری نبودن SKU
            $check_sku_query = "SELECT COUNT(*) as count FROM products WHERE sku = :sku AND id != :id";
            $check_sku_stmt = $db->prepare($check_sku_query);
            $check_sku_stmt->bindParam(':sku', $sku);
            $check_sku_stmt->bindParam(':id', $product_id);
            $check_sku_stmt->execute();
            $sku_count = $check_sku_stmt->fetch()['count'];
            
            if ($sku_count > 0) {
                $error = "SKU تکراری است. لطفا SKU دیگری انتخاب کنید";
            } else {
                // محاسبه قیمت فروش
                $profit_margin = $settings['profit_margin'] ?? 20;
                $selling_price = $purchase_price * (1 + ($profit_margin / 100));
                
                try {
                    $query = "UPDATE products SET 
                             name = :name, 
                             sku = :sku, 
                             purchase_price = :purchase_price, 
                             selling_price = :selling_price, 
                             stock = :stock, 
                             min_stock = :min_stock, 
                             description = :description,
                             updated_at = NOW() 
                             WHERE id = :id";
                    
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':name', $name);
                    $stmt->bindParam(':sku', $sku);
                    $stmt->bindParam(':purchase_price', $purchase_price);
                    $stmt->bindParam(':selling_price', $selling_price);
                    $stmt->bindParam(':stock', $stock);
                    $stmt->bindParam(':min_stock', $min_stock);
                    $stmt->bindParam(':description', $description);
                    $stmt->bindParam(':id', $product_id, PDO::PARAM_INT);
                    
                    if ($stmt->execute()) {
                        $success = "محصول با موفقیت ویرایش شد.";
                        
                        // ایجاد اعلان اگر موجودی کم شد
                        if ($stock <= $min_stock) {

                            $notification = new Notification($db);
                            $notification->createNotification(
                                $_SESSION['user_id'],
                                'موجودی کم',
                                'موجودی محصول ' . $name . ' به ' . $stock . ' کاهش یافت',
                                'warning'
                            );
                        }
                        
                        // بروزرسانی اطلاعات
                        $product['name'] = $name;
                        $product['sku'] = $sku;
                        $product['purchase_price'] = $purchase_price;
                        $product['stock'] = $stock;
                        $product['min_stock'] = $min_stock;
                        $product['description'] = $description;
                        $product['selling_price'] = $selling_price;
                        
                    } else {
                        $error = "خطا در ویرایش محصول.";
                    }
                } catch (PDOException $e) {
                    $error = "خطای دیتابیس: " . $e->getMessage();
                }
            }
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-pencil-square me-2"></i>ویرایش محصول</h2>
    <a href="../products.php" class="btn btn-secondary">
        <i class="bi bi-arrow-right me-1"></i>بازگشت به لیست محصولات
    </a>
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
    <div class="card-header bg-primary text-white">
        <h5 class="card-title mb-0">ویرایش محصول: <?php echo htmlspecialchars($product['name']); ?></h5>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <?php echo CSRF::getTokenField(); ?>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="name" class="form-label">نام محصول <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" 
                               value="<?php echo htmlspecialchars($product['name']); ?>" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="sku" class="form-label">کد محصول (SKU) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="sku" name="sku" 
                               value="<?php echo htmlspecialchars($product['sku']); ?>" required>
                        <small class="text-muted">کد یکتای محصول برای شناسایی</small>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="purchase_price" class="form-label">قیمت خرید <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="purchase_price" name="purchase_price" 
                                   value="<?php echo $purchase_price; ?>" step="100" min="0" required>
                            <span class="input-group-text">تومان</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="stock" class="form-label">موجودی <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="stock" name="stock" 
                               value="<?php echo $product['stock']; ?>" min="0" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="min_stock" class="form-label">حداقل موجودی <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="min_stock" name="min_stock" 
                               value="<?php echo $product['min_stock']; ?>" min="0" required>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label for="description" class="form-label">توضیحات محصول</label>
                <textarea class="form-control" id="description" name="description" rows="4" 
                          placeholder="توضیحات کامل درباره محصول"><?php echo htmlspecialchars($product['description']); ?></textarea>
            </div>

            <div class="alert alert-info">
                <h6><i class="bi bi-info-circle me-2"></i>اطلاعات محاسباتی</h6>
                <div class="row">
                    <div class="col-md-6">
                        <strong>قیمت فروش:</strong> 
                        <span class="text-success fw-bold"><?php echo number_format($selling_price); ?> تومان</span>
                    </div>
                    <div class="col-md-6">
                        <strong>درصد سود:</strong> 
                        <span class="text-primary"><?php echo $settings['profit_margin'] ?? 20; ?>%</span>
                    </div>
                </div>
                <small class="text-muted">قیمت فروش به صورت خودکار بر اساس درصد سود محاسبه می‌شود</small>
            </div>

            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                <a href="../products.php" class="btn btn-secondary me-md-2">
                    <i class="bi bi-x-circle me-1"></i>انصراف
                </a>
                <button type="submit" name="update_product" class="btn btn-primary">
                    <i class="bi bi-check-circle me-1"></i>ذخیره تغییرات
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// اعتبارسنجی client-side
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const purchasePrice = document.getElementById('purchase_price');
    const stock = document.getElementById('stock');
    const minStock = document.getElementById('min_stock');
    
    // جلوگیری از وارد کردن اعشار
    purchasePrice.addEventListener('input', function() {
        this.value = this.value.replace(/\./g, '');
    });
    
    // اعتبارسنجی حداقل موجودی
    minStock.addEventListener('change', function() {
        if (parseInt(stock.value) < parseInt(this.value)) {
            alert('⚠️ موجودی نمی‌تواند از حداقل موجودی کمتر باشد');
            this.value = stock.value;
        }
    });
    
    // اعتبارسنجی فرم قبل از ارسال
    form.addEventListener('submit', function(e) {
        if (parseInt(stock.value) < parseInt(minStock.value)) {
            e.preventDefault();
            alert('❌ موجودی نمی‌تواند از حداقل موجودی کمتر باشد');
            return false;
        }
        
        if (parseInt(purchasePrice.value) <= 0) {
            e.preventDefault();
            alert('❌ قیمت خرید باید بیشتر از صفر باشد');
            return false;
        }
    });
});
console.log('آدرس فعلی:', window.location.pathname);
console.log('مسیر حذف محصول:', getDeleteUrl('product'));
console.log('مسیر حذف مشتری:', getDeleteUrl('customer'));





</script>

<?php
require_once '../../includes/footer.php';
?>