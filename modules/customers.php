<?php
$page_title = "مدیریت مشتریان";
$datatable = true;
require_once '../includes/header.php';

$success = $error = '';

// افزودن مشتری جدید
if (isset($_POST['add_customer'])) {
    if (!CSRF::validateToken($_POST['csrf_token'])) {
        $error = "توکن امنیتی نامعتبر است";
    } else {
        $name = sanitize_input($_POST['name']);
        $phone = sanitize_input($_POST['phone'] ?? '');
        $email = sanitize_input($_POST['email'] ?? '');
        $address = sanitize_input($_POST['address'] ?? '');
        $notes = sanitize_input($_POST['notes'] ?? ''); // ✅ فیلد جدید
        
        if (empty($name)) {
            $error = "لطفا نام مشتری را وارد کنید";
        } else {
            try {
                $query = "INSERT INTO customers (name, phone, email, address, notes, created_by) 
                          VALUES (:name, :phone, :email, :address, :notes, :created_by)"; // ✅ تغییر شده
                $stmt = $db->prepare($query);
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':phone', $phone);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':address', $address);
                $stmt->bindParam(':notes', $notes); // ✅ فیلد جدید
                $stmt->bindParam(':created_by', $_SESSION['user_id']); // ✅ ثبت‌کننده
                
                if ($stmt->execute()) {
                    $success = "مشتری با موفقیت افزوده شد.";
                } else {
                    $error = "خطا در افزودن مشتری.";
                }
            } catch (PDOException $e) {
                $error = "خطای دیتابیس: " . $e->getMessage();
            }
        }
    }
}

// دریافت لیست مشتریان - ✅ تغییر شده
try {
    $query = "SELECT c.*, u.username as created_by_name 
              FROM customers c 
              LEFT JOIN users u ON c.created_by = u.id 
              ORDER BY c.id DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $customers = [];
    error_log("Customers error: " . $e->getMessage());
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-people me-2"></i>مدیریت مشتریان</h2>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
        <i class="bi bi-plus-circle me-1"></i>افزودن مشتری
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
        <h5 class="card-title mb-0">لیست مشتریان</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="dataTable" class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>ردیف</th>
                        <th>نام مشتری</th>
                        <th>شماره تماس</th>
                        <th>ایمیل</th>
                        <th>آدرس</th>
                        <th>توضیحات</th> <!-- ✅ ستون جدید -->
                        <th>ثبت کننده</th> <!-- ✅ ستون جدید -->
                        <th>تاریخ ثبت</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $index => $customer): ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($customer['name']); ?></strong>
                        </td>
                        <td>
                            <?php if (!empty($customer['phone'])): ?>
                                <a href="tel:<?php echo $customer['phone']; ?>" class="text-decoration-none">
                                    <i class="bi bi-telephone me-1"></i><?php echo htmlspecialchars($customer['phone']); ?>
                                </a>
                            <?php else: ?>
                                <span class="text-muted">ثبت نشده</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($customer['email'])): ?>
                                <a href="mailto:<?php echo $customer['email']; ?>" class="text-decoration-none">
                                    <i class="bi bi-envelope me-1"></i><?php echo htmlspecialchars($customer['email']); ?>
                                </a>
                            <?php else: ?>
                                <span class="text-muted">ثبت نشده</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($customer['address'])): ?>
                                <span title="<?php echo htmlspecialchars($customer['address']); ?>">
                                    <?php echo substr(htmlspecialchars($customer['address']), 0, 30); ?>...
                                </span>
                            <?php else: ?>
                                <span class="text-muted">ثبت نشده</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($customer['notes'])): ?>
                                <span title="<?php echo htmlspecialchars($customer['notes']); ?>">
                                    📝 <?php echo substr(htmlspecialchars($customer['notes']), 0, 20); ?>...
                                </span>
                            <?php else: ?>
                                <span class="text-muted">بدون توضیح</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <small class="text-info"><?php echo $customer['created_by_name'] ?? 'سیستم'; ?></small>
                        </td>
                        <td><?php echo date('Y/m/d', strtotime($customer['created_at'])); ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="edit/edit_customer.php?id=<?php echo $customer['id']; ?>" 
                                   class="btn btn-info" title="ویرایش">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button class="btn btn-danger delete-customer" 
                                        data-id="<?php echo $customer['id']; ?>" 
                                        data-name="<?php echo htmlspecialchars($customer['name']); ?>"
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

<!-- مودال افزودن مشتری - ✅ با فیلد notes -->
<div class="modal fade" id="addCustomerModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">افزودن مشتری جدید</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <?php echo CSRF::getTokenField(); ?>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">نام مشتری <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" required 
                               placeholder="نام کامل مشتری">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="phone" class="form-label">شماره تماس</label>
                                <input type="text" class="form-control" id="phone" name="phone" 
                                       placeholder="09xxxxxxxxx">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">آدرس ایمیل</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       placeholder="example@domain.com">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="address" class="form-label">آدرس</label>
                        <textarea class="form-control" id="address" name="address" rows="2" 
                                  placeholder="آدرس کامل مشتری"></textarea>
                    </div>

                    <!-- ✅ فیلد جدید notes -->
                    <div class="mb-3">
                        <label for="notes" class="form-label">توضیحات و یادداشت</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" 
                                  placeholder="توضیحات اضافی درباره مشتری - مثلاً: معرفی کننده، تاریخ مهم، توضیحات خاص و..."></textarea>
                        <small class="text-muted">مانند: معرفی شده توسط آقای احمدی - مشتری ویژه - تخفیف 10% دارد</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="submit" name="add_customer" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i>ذخیره مشتری
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// مدیریت حذف مشتری
$(document).on('click', '.delete-customer', function() {
    const customerId = $(this).data('id');
    const customerName = $(this).data('name');
    
    if (confirm(`آیا از حذف مشتری "${customerName}" اطمینان دارید؟`)) {
        deleteItem('customer', customerId, customerName);
    }
});
</script>

<?php
require_once '../includes/footer.php';
?>