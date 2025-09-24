<?php
$page_title = "تنظیمات سیستم";
require_once '../includes/header.php';
require_once '../includes/csrf.php';

// بررسی دسترسی ادمین
if ($_SESSION['role'] != 'admin') {
    header("Location: ../dashboard.php");
    exit;
}

$success = $error = $password_success = $password_error = $user_success = $user_error = '';

// به روز رسانی تنظیمات
if (isset($_POST['update_settings'])) {
    if (!CSRF::validateToken($_POST['csrf_token'])) {
        $error = "توکن امنیتی نامعتبر است";
    } else {
        $company_name = sanitize_input($_POST['company_name']);
        $profit_margin = (float)$_POST['profit_margin'];
        $tax_rate = (float)$_POST['tax_rate'];
        $address = sanitize_input($_POST['address'] ?? '');
        $phone = sanitize_input($_POST['phone'] ?? '');
        $email = sanitize_input($_POST['email'] ?? '');
        
        // آپلود لوگو
        $logo_path = $settings['logo_path'] ?? '';
        if (!empty($_FILES['logo']['name'])) {
            $target_dir = "../uploads/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0755, true);
            }
            
            $file_extension = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'svg'];
            
            if (in_array($file_extension, $allowed_extensions)) {
                $new_file_name = 'logo_' . time() . '.' . $file_extension;
                $target_file = $target_dir . $new_file_name;
                
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $target_file)) {
                    $logo_path = "uploads/" . $new_file_name;
                    
                    // حذف لوگوی قبلی اگر وجود دارد
                    if (!empty($settings['logo_path']) && file_exists("../" . $settings['logo_path'])) {
                        unlink("../" . $settings['logo_path']);
                    }
                }
            } else {
                $error = "فرمت فایل مجاز نیست. فقط JPG, PNG, GIF, SVG مجاز هستند.";
            }
        }
        
        if (empty($error)) {
            try {
                // بررسی وجود رکورد تنظیمات
                $check_query = "SELECT COUNT(*) as count FROM settings";
                $check_stmt = $db->prepare($check_query);
                $check_stmt->execute();
                $settings_count = $check_stmt->fetch()['count'];
                
                if ($settings_count > 0) {
                    $query = "UPDATE settings SET 
                              company_name = :company_name, 
                              profit_margin = :profit_margin, 
                              tax_rate = :tax_rate, 
                              logo_path = :logo_path,
                              address = :address,
                              phone = :phone,
                              email = :email,
                              updated_at = NOW() 
                              WHERE id = 1";
                } else {
                    $query = "INSERT INTO settings (company_name, profit_margin, tax_rate, logo_path, address, phone, email) 
                              VALUES (:company_name, :profit_margin, :tax_rate, :logo_path, :address, :phone, :email)";
                }
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(':company_name', $company_name);
                $stmt->bindParam(':profit_margin', $profit_margin);
                $stmt->bindParam(':tax_rate', $tax_rate);
                $stmt->bindParam(':logo_path', $logo_path);
                $stmt->bindParam(':address', $address);
                $stmt->bindParam(':phone', $phone);
                $stmt->bindParam(':email', $email);
                
                if ($stmt->execute()) {
                    $success = "تنظیمات با موفقیت به روز شد.";
                    // به روز رسانی تنظیمات در session
                    $settings_query = "SELECT * FROM settings LIMIT 1";
                    $settings_stmt = $db->prepare($settings_query);
                    $settings_stmt->execute();
                    $settings = $settings_stmt->fetch(PDO::FETCH_ASSOC);
                } else {
                    $error = "خطا در به روز رسانی تنظیمات.";
                }
            } catch (Exception $e) {
                $error = "خطای دیتابیس: " . $e->getMessage();
            }
        }
    }
}

// تغییر رمز عبور
if (isset($_POST['change_password'])) {
    if (!CSRF::validateToken($_POST['csrf_token'])) {
        $password_error = "توکن امنیتی نامعتبر است";
    } else {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $password_error = "لطفا تمام فیلدهای رمز عبور را پر کنید";
        } elseif ($new_password !== $confirm_password) {
            $password_error = "رمز عبور جدید با تأییدیه مطابقت ندارد";
        } elseif (strlen($new_password) < 6) {
            $password_error = "رمز عبور باید حداقل 6 کاراکتر باشد";
        } else {
            try {
                // بررسی رمز عبور فعلی
                $query = "SELECT password FROM users WHERE id = :user_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->execute();
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user && password_verify($current_password, $user['password'])) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    $update_query = "UPDATE users SET password = :password, updated_at = NOW() WHERE id = :user_id";
                    $update_stmt = $db->prepare($update_query);
                    $update_stmt->bindParam(':password', $hashed_password);
                    $update_stmt->bindParam(':user_id', $_SESSION['user_id']);
                    
                    if ($update_stmt->execute()) {
                        $password_success = "رمز عبور با موفقیت تغییر یافت.";
                    } else {
                        $password_error = "خطا در تغییر رمز عبور.";
                    }
                } else {
                    $password_error = "رمز عبور فعلی اشتباه است.";
                }
            } catch (Exception $e) {
                $password_error = "خطای دیتابیس: " . $e->getMessage();
            }
        }
    }
}

// افزودن کاربر جدید
if (isset($_POST['add_user'])) {
    if (!CSRF::validateToken($_POST['csrf_token'])) {
        $user_error = "توکن امنیتی نامعتبر است";
    } else {
        $username = sanitize_input($_POST['username']);
        $password = $_POST['password'];
        $full_name = sanitize_input($_POST['full_name']);
        $role = sanitize_input($_POST['role']);
        
        if (empty($username) || empty($password) || empty($full_name)) {
            $user_error = "لطفا تمام فیلدهای ضروری را پر کنید";
        } elseif (strlen($password) < 6) {
            $user_error = "رمز عبور باید حداقل 6 کاراکتر باشد";
        } else {
            try {
                // بررسی تکراری نبودن نام کاربری
                $check_query = "SELECT COUNT(*) as count FROM users WHERE username = :username";
                $check_stmt = $db->prepare($check_query);
                $check_stmt->bindParam(':username', $username);
                $check_stmt->execute();
                $user_count = $check_stmt->fetch()['count'];
                
                if ($user_count > 0) {
                    $user_error = "نام کاربری قبلاً استفاده شده است";
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    $query = "INSERT INTO users (username, password, full_name, role) 
                              VALUES (:username, :password, :full_name, :role)";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':username', $username);
                    $stmt->bindParam(':password', $hashed_password);
                    $stmt->bindParam(':full_name', $full_name);
                    $stmt->bindParam(':role', $role);
                    
                    if ($stmt->execute()) {
                        $user_success = "کاربر با موفقیت افزوده شد.";
                    } else {
                        $user_error = "خطا در افزودن کاربر.";
                    }
                }
            } catch (Exception $e) {
                $user_error = "خطای دیتابیس: " . $e->getMessage();
            }
        }
    }
}

// دریافت لیست کاربران
try {
    $users_query = "SELECT id, username, full_name, role, created_at, active FROM users ORDER BY id DESC";
    $users_stmt = $db->prepare($users_query);
    $users_stmt->execute();
    $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $users = [];
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-gear me-2"></i>تنظیمات سیستم</h2>
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

<div class="row">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">تنظیمات فروشگاه</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="" enctype="multipart/form-data">
                    <?php echo CSRF::getTokenField(); ?>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="company_name" class="form-label">نام فروشگاه <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="company_name" name="company_name" 
                                       value="<?php echo htmlspecialchars($settings['company_name'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="logo" class="form-label">لوگوی فروشگاه</label>
                                <input type="file" class="form-control" id="logo" name="logo" accept="image/*">
                                <small class="text-muted">فرمت‌های مجاز: JPG, PNG, GIF, SVG (حداکثر 2MB)</small>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($settings['logo_path'])): ?>
                    <div class="mb-3">
                        <label class="form-label">لوگوی فعلی</label>
                        <div>
                            <img src="../<?php echo $settings['logo_path']; ?>" alt="لوگوی فروشگاه" 
                                 style="max-height: 100px; max-width: 200px;" class="img-thumbnail">
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="profit_margin" class="form-label">درصد سود پیش‌فرض <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="profit_margin" name="profit_margin" 
                                           value="<?php echo $settings['profit_margin'] ?? 20; ?>" step="0.1" min="0" max="100" required>
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="tax_rate" class="form-label">نرخ مالیات <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="tax_rate" name="tax_rate" 
                                           value="<?php echo $settings['tax_rate'] ?? 9; ?>" step="0.1" min="0" max="100" required>
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="address" class="form-label">آدرس</label>
                        <textarea class="form-control" id="address" name="address" rows="2"><?php echo htmlspecialchars($settings['address'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="phone" class="form-label">تلفن</label>
                                <input type="text" class="form-control" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($settings['phone'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">ایمیل</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($settings['email'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" name="update_settings" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i>ذخیره تنظیمات
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">تغییر رمز عبور</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <?php echo CSRF::getTokenField(); ?>
                    <div class="mb-3">
                        <label for="current_password" class="form-label">رمز عبور فعلی <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>
                    <div class="mb-3">
                        <label for="new_password" class="form-label">رمز عبور جدید <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required 
                               minlength="6" placeholder="حداقل 6 کاراکتر">
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">تکرار رمز عبور جدید <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                    
                    <?php if (!empty($password_success)): ?>
                    <div class="alert alert-success"><?php echo $password_success; ?></div>
                    <?php endif; ?>
                    <?php if (!empty($password_error)): ?>
                    <div class="alert alert-danger"><?php echo $password_error; ?></div>
                    <?php endif; ?>
                    
                    <button type="submit" name="change_password" class="btn btn-warning w-100">
                        <i class="bi bi-key me-1"></i>تغییر رمز عبور
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if ($_SESSION['role'] == 'admin'): ?>
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">مدیریت کاربران</h5>
            </div>
            <div class="card-body">
                <div class="mb-4">
                    <h6>افزودن کاربر جدید</h6>
                    <form method="POST" action="" class="row g-3 align-items-end">
                        <?php echo CSRF::getTokenField(); ?>
                        <div class="col-md-3">
                            <label class="form-label">نام کاربری <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="username" required 
                                   placeholder="نام کاربری">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">رمز عبور <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" name="password" required 
                                   minlength="6" placeholder="حداقل 6 کاراکتر">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">نام کامل <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="full_name" required 
                                   placeholder="نام و نام خانوادگی">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">نقش <span class="text-danger">*</span></label>
                            <select class="form-select" name="role">
                                <option value="user">کاربر عادی</option>
                                <option value="admin">مدیر سیستم</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" name="add_user" class="btn btn-primary w-100">
                                <i class="bi bi-person-plus me-1"></i>افزودن
                            </button>
                        </div>
                    </form>
                    
                    <?php if (!empty($user_success)): ?>
                    <div class="alert alert-success mt-2"><?php echo $user_success; ?></div>
                    <?php endif; ?>
                    <?php if (!empty($user_error)): ?>
                    <div class="alert alert-danger mt-2"><?php echo $user_error; ?></div>
                    <?php endif; ?>
                </div>

                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>نام کاربری</th>
                                <th>نام کامل</th>
                                <th>نقش</th>
                                <th>وضعیت</th>
                                <th>تاریخ ایجاد</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $user['role'] == 'admin' ? 'danger' : 'secondary'; ?>">
                                        <?php echo $user['role'] == 'admin' ? 'مدیر' : 'کاربر'; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $user['active'] ? 'success' : 'warning'; ?>">
                                        <?php echo $user['active'] ? 'فعال' : 'غیرفعال'; ?>
                                    </span>
                                </td>
                                <td><?php echo date('Y/m/d', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-warning" title="غیرفعال کردن">
                                            <i class="bi bi-power"></i>
                                        </button>
                                        <button class="btn btn-danger" title="حذف">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                    <?php else: ?>
                                    <span class="text-muted">کاربر جاری</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0">ابزارهای مدیریتی</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="backup.php" class="btn btn-info">
                        <i class="bi bi-cloud-arrow-down me-2"></i>
                        مدیریت پشتیبان‌گیری
                    </a>
                    <a href="../modules/reports.php" class="btn btn-success">
                        <i class="bi bi-bar-chart me-2"></i>
                        مشاهده گزارشات سیستم
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
require_once '../includes/footer.php';
?>