<!DOCTYPE html>
<?php
define('BASE_PATH', dirname(__DIR__)); 

require_once BASE_PATH . '/constants.php';



if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}


// اتصال به دیتابیس
$database = new Database();
$db = $database->getConnection();

// دریافت تنظیمات شرکت
$settings = [];
try {
    $settings_query = "SELECT * FROM settings LIMIT 1";
    $settings_stmt = $db->prepare($settings_query);
    $settings_stmt->execute();
    $settings = $settings_stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $settings = [
        'company_name' => 'سیستم مدیریت انبار',
        'logo_path' => '<?php echo $includes_url; ?>/images/logo.png',
        'profit_margin' => 20,
        'tax_rate' => 9
    ];
}

// بررسی اعلان‌های سیستم

$notification = new Notification($db);
$system_notifications = $notification->checkSystemNotifications();

$page_title = $page_title ?? 'سیستم مدیریت انبار';
$datatable = $datatable ?? false;
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title . ' - ' . $settings['company_name']; ?></title>
  <?php  echo CSRF::getTokenMeta(); ?>
    
    <link href="<?php echo $css_url; ?>/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $css_url; ?>/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo $css_url; ?>/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="<?php echo $css_url; ?>/style.css">
    
    <style>
        .sidebar {
            background: linear-gradient(180deg, #2c3e50 0%, #3498db 100%);
        }
        .sidebar .nav-link {
            color: #ecf0f1;
            border-radius: 5px;
            margin: 2px 0;
            transition: all 0.3s;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background-color: #34495e;
            color: #fff;
            transform: translateX(-5px);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- نوار کناری -->
            <nav class="col-md-3 col-lg-2 sidebar d-md-block bg-dark text-white p-0" style="min-height: 100vh;">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <?php if (!empty($settings['logo_path'])): ?>
                            <img src="<?php echo $settings['logo_path']; ?>" alt="لوگوی شرکت" 
                                 class="img-fluid " style="max-height: 100px;">
                        <?php endif; ?>
                        <h5 class="mt-2"><?php echo $settings['company_name']; ?></h5>
                    </div>
                    
                    <hr class="text-white">
                    
                    <ul class="nav nav-pills flex-column">
                        <li class="nav-item">
                            <a href="../dashboard.php" 
                               class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                                <i class="bi bi-speedometer2 me-2"></i>داشبورد
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="../modules/products.php" 
                               class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'products.php' ? 'active' : ''; ?>">
                                <i class="bi bi-box me-2"></i>محصولات
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="../modules/customers.php" 
                               class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'customers.php' ? 'active' : ''; ?>">
                                <i class="bi bi-people me-2"></i>مشتریان
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="../modules/invoices.php" 
                               class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'invoices.php' ? 'active' : ''; ?>">
                                <i class="bi bi-receipt me-2"></i>فاکتورها
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="../modules/checks.php" 
                               class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'checks.php' ? 'active' : ''; ?>">
                                <i class="bi bi-wallet2 me-2"></i>مدیریت چک‌ها
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="../modules/reports.php" 
                               class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                                <i class="bi bi-bar-chart me-2"></i>گزارشات
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="../modules/settings.php" 
                               class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
                                <i class="bi bi-gear me-2"></i>تنظیمات
                            </a>
                        </li>
                        
                        <?php if ($_SESSION['role'] == 'admin'): ?>
                        <li class="nav-item">
                            <a href="../modules/backup.php" 
                               class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'backup.php' ? 'active' : ''; ?>">
                                <i class="bi bi-cloud-arrow-down me-2"></i>پشتیبان‌گیری
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <li class="nav-item mt-4">
                            <a href="../logout.php" class="nav-link text-warning">
                                <i class="bi bi-box-arrow-left me-2"></i>خروج
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- محتوای اصلی -->
            <main class="col-md-9 col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="h3"><?php echo $page_title; ?></h2>
                    <div class="d-flex align-items-center">
                        <span class="badge bg-primary me-3">
                            <i class="bi bi-person-circle me-1"></i>
                            <?php echo $_SESSION['full_name']; ?> 
                            (<?php echo $_SESSION['role'] == 'admin' ? 'مدیر' : 'کاربر'; ?>)
                        </span>
						
						<span class="badge bg-dark me-3">
                            <i class="bi bi-calendar me-1"></i>
                            <?php echo jdate('Y/m/d'); ?>
                        </span>

                    </div>
                </div>

                <!-- نمایش اعلان‌های سیستم -->
                <?php if (!empty($system_notifications)): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="alert alert-warning">
                            <h6><i class="bi bi-bell me-2"></i>اعلان‌های سیستم</h6>
                            <ul class="mb-0">
                                <?php foreach ($system_notifications as $notif): ?>
                                <li class="text-<?php echo $notif['type']; ?>">
                                    <strong><?php echo $notif['title']; ?>:</strong>
                                    <?php echo $notif['message']; ?>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
                <?php endif; ?>