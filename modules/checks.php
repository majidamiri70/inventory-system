<?php
$page_title = "مدیریت چک‌ها";
$datatable = true;
require_once '../includes/header.php';


// ایجاد شیء Notification

$notification = new Notification($db);

$success = $error = '';

// افزودن چک جدید
if (isset($_POST['add_check'])) {
    if (!CSRF::validateToken($_POST['csrf_token'])) {
        $error = "توکن امنیتی نامعتبر است";
    } else {
        $type = sanitize_input($_POST['type']);
        $check_number = sanitize_input($_POST['check_number']);
        $bank_name = sanitize_input($_POST['bank_name']);
        $amount = (float)$_POST['amount'];
        $issue_date = sanitize_input($_POST['issue_date']);
        $due_date = sanitize_input($_POST['due_date']);
        $customer_id = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
        $notes = sanitize_input($_POST['notes'] ?? '');
        
        // اعتبارسنجی
        if (empty($check_number) || empty($bank_name) || $amount <= 0 || empty($due_date)) {
            $error = "لطفا اطلاعات ضروری را وارد کنید";
        } else {
            try {
                $query = "INSERT INTO checks (type, check_number, bank_name, amount, issue_date, due_date, customer_id, notes) 
                          VALUES (:type, :check_number, :bank_name, :amount, :issue_date, :due_date, :customer_id, :notes)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':type', $type);
                $stmt->bindParam(':check_number', $check_number);
                $stmt->bindParam(':bank_name', $bank_name);
                $stmt->bindParam(':amount', $amount);
                $stmt->bindParam(':issue_date', $issue_date);
                $stmt->bindParam(':due_date', $due_date);
                $stmt->bindParam(':customer_id', $customer_id);
                $stmt->bindParam(':notes', $notes);
                
                if ($stmt->execute()) {
                    // اعلان برای چک‌های نزدیک به سررسید
                    $due_date_obj = new DateTime($due_date);
                    $today = new DateTime();
                    $days_until_due = $today->diff($due_date_obj)->days;
                    
                    if ($days_until_due <= 7) {
                        $notification->createNotification(
                            $_SESSION['user_id'],
                            'چک نزدیک به سررسید',
                            'چک شماره ' . $check_number . ' تا ' . $days_until_due . ' روز دیگر سررسید می‌شود.',
                            'warning'
                        );
                    }
                    
                    $success = "چک با موفقیت ثبت شد.";
                } else {
                    $error = "خطا در ثبت چک.";
                }
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = "شماره چک تکراری است.";
                } else {
                    $error = "خطای دیتابیس: " . $e->getMessage();
                }
            }
        }
    }
}

// دریافت لیست چک‌ها
try {
    $query = "SELECT c.*, cust.name as customer_name 
              FROM checks c 
              LEFT JOIN customers cust ON c.customer_id = cust.id 
              ORDER BY c.due_date ASC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $checks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $checks = [];
    error_log("Checks error: " . $e->getMessage());
}

// دریافت لیست مشتریان
try {
    $customers_query = "SELECT id, name FROM customers ORDER BY name";
    $customers_stmt = $db->prepare($customers_query);
    $customers_stmt->execute();
    $customers = $customers_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $customers = [];
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-wallet2 me-2"></i>مدیریت چک‌ها</h2>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCheckModal">
        <i class="bi bi-plus-circle me-1"></i>افزودن چک
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
        <h5 class="card-title mb-0">لیست چک‌ها</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="dataTable" class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>ردیف</th>
                        <th>نوع چک</th>
                        <th>شماره چک</th>
                        <th>بانک</th>
                        <th>مبلغ</th>
                        <th>تاریخ سررسید</th>
                        <th>وضعیت</th>
                        <th>مشتری</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($checks as $index => $check): 
                        $due_date = new DateTime($check['due_date']);
                        $today = new DateTime();
                        $is_due_soon = $due_date->diff($today)->days <= 3;
                        $is_overdue = $due_date < $today;
                        
                        $row_class = '';
                        if ($is_overdue && $check['status'] == 'pending') {
                            $row_class = 'table-danger';
                        } elseif ($is_due_soon && $check['status'] == 'pending') {
                            $row_class = 'table-warning';
                        }
                        
                        $status_badge = '';
                        switch ($check['status']) {
                            case 'cleared':
                                $status_badge = '<span class="badge bg-success">وصول شده</span>';
                                break;
                            case 'bounced':
                                $status_badge = '<span class="badge bg-danger">برگشتی</span>';
                                break;
                            default:
                                $status_badge = '<span class="badge bg-warning">در انتظار</span>';
                        }
                    ?>
                    <tr class="<?php echo $row_class; ?>">
                        <td><?php echo $index + 1; ?></td>
                        <td>
                            <span class="badge bg-<?php echo $check['type'] == 'received' ? 'success' : 'danger'; ?>">
                                <?php echo $check['type'] == 'received' ? 'دریافتی' : 'پرداختی'; ?>
                            </span>
                        </td>
                        <td><strong><?php echo htmlspecialchars($check['check_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars($check['bank_name']); ?></td>
                        <td><?php echo number_format($check['amount']); ?> تومان</td>
                        <td>
                            <?php echo $check['due_date']; ?>
                            <?php if ($is_overdue && $check['status'] == 'pending'): ?>
                                <br><small class="text-danger">(معوقه)</small>
                            <?php elseif ($is_due_soon && $check['status'] == 'pending'): ?>
                                <br><small class="text-warning">(نزدیک سررسید)</small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $status_badge; ?></td>
                        <td><?php echo $check['customer_name'] ? htmlspecialchars($check['customer_name']) : '<span class="text-muted">ندارد</span>'; ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-info" title="ویرایش" onclick="editCheck(<?php echo $check['id']; ?>)">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-danger delete-check" 
                                        data-id="<?php echo $check['id']; ?>" 
                                        data-number="<?php echo htmlspecialchars($check['check_number']); ?>"
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

<!-- مودال افزودن چک -->
<div class="modal fade" id="addCheckModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">افزودن چک جدید</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <?php echo CSRF::getTokenField(); ?>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="type" class="form-label">نوع چک <span class="text-danger">*</span></label>
                                <select class="form-select" id="type" name="type" required>
                                    <option value="received">چک دریافتی</option>
                                    <option value="issued">چک پرداختی</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="check_number" class="form-label">شماره چک <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="check_number" name="check_number" 
                                       required placeholder="شماره چک">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="bank_name" class="form-label">نام بانک <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="bank_name" name="bank_name" 
                                       required placeholder="نام بانک صادر کننده">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="amount" class="form-label">مبلغ <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="amount" name="amount" 
                                           required min="0" step="1000" placeholder="0">
                                    <span class="input-group-text">تومان</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="issue_date" class="form-label">تاریخ صدور</label>
                                <input type="date" class="form-control" id="issue_date" name="issue_date" 
                                       value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="due_date" class="form-label">تاریخ سررسید <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="due_date" name="due_date" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="customer_id" class="form-label">مشتری (اختیاری)</label>
                        <select class="form-select" id="customer_id" name="customer_id">
                            <option value="">انتخاب مشتری</option>
                            <?php foreach ($customers as $customer): ?>
                            <option value="<?php echo $customer['id']; ?>"><?php echo htmlspecialchars($customer['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">توضیحات</label>
                        <textarea class="form-control" id="notes" name="notes" rows="2" 
                                  placeholder="توضیحات اختیاری"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="submit" name="add_check" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i>ذخیره چک
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// مدیریت حذف چک
$(document).on('click', '.delete-check', function() {
    const checkId = $(this).data('id');
    const checkNumber = $(this).data('number');
    
    if (confirm(`آیا از حذف چک شماره ${checkNumber} اطمینان دارید؟`)) {
        deleteItem('check', checkId, checkNumber);
    }
});

function editCheck(checkId) {
    alert('ویرایش چک با شناسه ' + checkId);
    // این قسمت می‌تواند به صفحه ویرایش چک ریدایرکت کند
    // window.location.href = 'edit_check.php?id=' + checkId;
}

// تنظیم تاریخ سررسید پیش‌فرض (3 ماه بعد)
document.addEventListener('DOMContentLoaded', function() {
    const today = new Date();
    const dueDate = new Date();
    dueDate.setMonth(today.getMonth() + 3);
    
    const dueDateFormatted = dueDate.toISOString().split('T')[0];
    document.getElementById('due_date').value = dueDateFormatted;
});
</script>

<?php
require_once '../includes/footer.php';
?>