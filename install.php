<?php
// install.php - صفحه نصب سیستم
session_start();
define('ROOT_PATH', dirname(__DIR__));

// اگر قبلاً نصب شده باشد
if (file_exists('config/database.php') && file_exists('config/config.php')) {
    header('Location: login.php');
    exit;
}

$error = $success = '';
$db_host = $db_name = $db_user = $db_pass = $admin_user = $admin_pass = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $db_host = $_POST['db_host'] ?? 'localhost';
    $db_name = $_POST['db_name'] ?? 'inventory_accounting';
    $db_user = $_POST['db_user'] ?? 'root';
    $db_pass = $_POST['db_pass'] ?? '';
    $admin_user = $_POST['admin_user'] ?? 'admin';
    $admin_pass = $_POST['admin_pass'] ?? 'password123';
    
    // اعتبارسنجی
    if (empty($db_host) || empty($db_name) || empty($db_user) || empty($admin_user) || empty($admin_pass)) {
        $error = "لطفا تمام فیلدهای ضروری را پر کنید";
    } else {
        try {
            // تست اتصال به دیتابیس
            $pdo = new PDO("mysql:host=$db_host", $db_user, $db_pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // ایجاد دیتابیس اگر وجود ندارد
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_persian_ci");
            $pdo->exec("USE `$db_name`");
            
            // اجرای فایل SQL
            $sql_file = file_get_contents('database.sql');
            $pdo->exec($sql_file);
            
            // ایجاد فایل config
            $config_content = "<?php
define('DB_HOST', '$db_host');
define('DB_NAME', '$db_name');
define('DB_USER', '$db_user');
define('DB_PASS', '$db_pass');
define('SITE_NAME', 'سیستم مدیریت انبار');
?>";
            
            file_put_contents('config/config.php', $config_content);
            
            // ایجاد فایل database.php
            $db_content = "<?php
class Database {
    private \$host = '$db_host';
    private \$db_name = '$db_name';
    private \$username = '$db_user';
    private \$password = '$db_pass';
    public \$conn;

    public function getConnection() {
        \$this->conn = null;
        try {
            \$this->conn = new PDO(\"mysql:host=\" . \$this->host . \";dbname=\" . \$this->db_name . \";charset=utf8mb4\", \$this->username, \$this->password);
            \$this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException \$exception) {
            throw new Exception(\"Connection error: \" . \$exception->getMessage());
        }
        return \$this->conn;
    }
}
?>";
            
            file_put_contents('config/database.php', $db_content);
            
            $success = "✅ سیستم با موفقیت نصب شد. اکنون می‌توانید وارد شوید.";
            
        } catch (Exception $e) {
            $error = "خطا در نصب: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نصب سیستم مدیریت انبار</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .install-container {
            max-width: 600px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="install-container mx-auto p-4">
            <div class="text-center mb-4">
                <h2><i class="bi bi-box-seam text-primary"></i> نصب سیستم مدیریت انبار</h2>
                <p class="text-muted">لطفا اطلاعات دیتابیس و ادمین را وارد کنید</p>
            </div>
            
            <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo $success; ?>
                <br><a href="login.php" class="btn btn-success mt-2">ورود به سیستم</a>
            </div>
            <?php else: ?>
            
            <form method="POST" action="">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">هاست دیتابیس</label>
                            <input type="text" class="form-control" name="db_host" value="<?php echo $db_host; ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">نام دیتابیس</label>
                            <input type="text" class="form-control" name="db_name" value="<?php echo $db_name; ?>" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">نام کاربری دیتابیس</label>
                            <input type="text" class="form-control" name="db_user" value="<?php echo $db_user; ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">رمز عبور دیتابیس</label>
                            <input type="password" class="form-control" name="db_pass" value="<?php echo $db_pass; ?>">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">نام کاربری ادمین</label>
                            <input type="text" class="form-control" name="admin_user" value="<?php echo $admin_user; ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">رمز عبور ادمین</label>
                            <input type="password" class="form-control" name="admin_pass" value="<?php echo $admin_pass; ?>" required>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-gear"></i> نصب سیستم
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>