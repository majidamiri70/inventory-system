<?php
$page_title = "ویرایش مشتری";
require_once '../../includes/header.php';
require_once '../../includes/csrf.php';

$customer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$customer = null;
$success = $error = '';

// دریافت اطلاعات مشتری
if ($customer_id > 0) {
    try {
        $query = "SELECT * FROM customers WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $customer_id, PDO::PARAM_INT);
        $stmt->execute();
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error = "خطا در دریافت اطلاعات مشتری: " . $e->getMessage();
    }
}

if (!$customer) {
    header("Location: ../customers.php");
    exit;
}

// ویرایش مشتری
if (isset($_POST['update_customer'])) {
    if (!CSRF::validateToken($_POST['csrf_token'])) {
        $error = "توکن امنیتی نامعتبر است";
    } else {
        $name = sanitize_input($_POST['name']);
        $phone = sanitize_input($_POST['phone'] ?? '');
        $email = sanitize_input($_POST['email'] ?? '');
        $address = sanitize_input($_POST['address'] ?? '');
        
        if (empty($name)) {
            $error = "لطفا نام مشتری را وارد کنید";
        } else {
            try {
                $query = "UPDATE customers SET 
                         name = :name, 
                         phone = :phone, 
                         email = :email, 
                         address = :address,
                         updated_at = NOW() 
                         WHERE id = :id";
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':phone', $phone);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':address', $address);
                $stmt->bindParam(':id', $customer_id, PDO::PARAM_INT);
                
                if ($stmt->execute()) {
                    $success = "مشتری با موفقیت ویرایش شد.";
                    
                    // بروزرسانی اطلاعات
                    $customer['name'] = $name;
                    $customer['phone'] = $phone;
                    $customer['email'] = $email;
                    $customer['address'] = $address;
                } else {
                    $error = "خطا در ویرایش مشتری.";
                }
            } catch (PDOException $e) {
                $error = "خطای دیتابیس: " . $e->getMessage();
            }
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-person-gear me-2"></i>ویرایش مشتری</h2>
    <a href="../customers.php" class="btn btn-secondary">
        <i class="bi bi-arrow-right me-1"></i>بازگشت به لیست مشتریان
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
    <div class="card-header bg-success text-white">
        <h5 class="card-title mb-0">ویرایش اطلاعات مشتری</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <?php echo CSRF::getTokenField(); ?>
            
            <div class="mb-3">
                <label for="name" class="form-label">نام کامل مشتری <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="name" name="name" 
                       value="<?php echo htmlspecialchars($customer['name']); ?>" required 
                       placeholder="نام و نام خانوادگی مشتری">
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="phone" class="form-label">شماره تماس</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($customer['phone']); ?>" 
                                   placeholder="09xxxxxxxxx">
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="email" class="form-label">آدرس ایمیل</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($customer['email']); ?>" 
                                   placeholder="example@domain.com">
                        </div>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label for="address" class="form-label">آدرس کامل</label>
                <textarea class="form-control" id="address" name="address" rows="4" 
                          placeholder="آدرس کامل پستی مشتری"><?php echo htmlspecialchars($customer['address']); ?></textarea>
            </div>

            <!-- آمار مشتری -->
            <div class="alert alert-info">
                <h6><i class="bi bi-graph-up me-2"></i>آمار مشتری</h6>
                <?php
                try {
                    // تعداد فاکتورها
                    $invoice_count_query = "SELECT COUNT(*) as count FROM invoices WHERE customer_id = :customer_id";
                    $invoice_count_stmt = $db->prepare($invoice_count_query);
                    $invoice_count_stmt->bindParam(':customer_id', $customer_id);
                    $invoice_count_stmt->execute();
                    $invoice_count = $invoice_count_stmt->fetch()['count'];
                    
                    // جمع خریدها
                    $total_purchases_query = "SELECT COALESCE(SUM(total), 0) as total FROM invoices WHERE customer_id = :customer_id AND status = 'paid'";
                    $total_purchases_stmt = $db->prepare($total_purchases_query);
                    $total_purchases_stmt->bindParam(':customer_id', $customer_id);
                    $total_purchases_stmt->execute();
                    $total_purchases = $total_purchases_stmt->fetch()['total'];
                    
                    echo "<div class='row'>";
                    echo "<div class='col-md-6'><strong>تعداد فاکتورها:</strong> " . number_format($invoice_count) . " فاکتور</div>";
                    echo "<div class='col-md-6'><strong>جمع خریدها:</strong> " . number_format($total_purchases) . " تومان</div>";
                    echo "</div>";
                    
                } catch (Exception $e) {
                    echo "<p class='text-muted'>خطا در دریافت آمار</p>";
                }
                ?>
            </div>

            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                <a href="../customers.php" class="btn btn-secondary me-md-2">
                    <i class="bi bi-x-circle me-1"></i>انصراف
                </a>
                <button type="submit" name="update_customer" class="btn btn-success">
                    <i class="bi bi-check-circle me-1"></i>ذخیره تغییرات
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// اعتبارسنجی فرم
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const phone = document.getElementById('phone');
    const email = document.getElementById('email');
    
    // اعتبارسنجی شماره تلفن
    phone.addEventListener('input', function() {
        this.value = this.value.replace(/[^\d]/g, '');
        if (this.value.length > 11) {
            this.value = this.value.slice(0, 11);
        }
    });
    
    // اعتبارسنجی ایمیل
    email.addEventListener('blur', function() {
        if (this.value && !this.value.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
            alert('❌ فرمت ایمیل نامعتبر است');
            this.focus();
        }
    });
    
    // اعتبارسنجی قبل از ارسال
    form.addEventListener('submit', function(e) {
        const name = document.getElementById('name').value.trim();
        
        if (name.length < 2) {
            e.preventDefault();
            alert('❌ نام مشتری باید حداقل ۲ کاراکتر باشد');
            return false;
        }
        
        if (email.value && !email.value.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
            e.preventDefault();
            alert('❌ فرمت ایمیل نامعتبر است');
            return false;
        }
    });
});
</script>

<?php
require_once '../../includes/footer.php';
?>