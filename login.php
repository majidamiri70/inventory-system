<?php
require_once 'config/config.php';
require_once 'includes/auth.php';

$auth = new Auth();

if ($auth->isLoggedIn()) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitize_input($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = "لطفا نام کاربری و رمز عبور را وارد کنید";
    } else {
        if ($auth->login($username, $password)) {
            header("Location: dashboard.php");
            exit;
        } else {
            $error = "نام کاربری یا رمز عبور اشتباه است";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود به سیستم - <?php echo SITE_NAME; ?></title>
    <link href="./includes/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="./includes/css/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-container {
            max-width: 400px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .login-header {
            background: linear-gradient(135deg, #3498db, #2c3e50);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .login-body {
            padding: 2rem;
        }
        .form-control {
            border-radius: 8px;
            padding: 0.75rem;
        }
        .btn-login {
            background: linear-gradient(135deg, #3498db, #2c3e50);
            border: none;
            border-radius: 8px;
            padding: 0.75rem;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="login-header">
                <h3><i class="bi bi-box-seam me-2"></i>سیستم مدیریت انبار</h3>
                <p class="mb-0">لطفا وارد شوید</p>
            </div>
            <div class="login-body">
                <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="username" class="form-label">نام کاربری</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" class="form-control" id="username" name="username" 
                                   value="<?php echo $username; ?>" required autofocus>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">رمز عبور</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-login text-white w-100">
                        <i class="bi bi-box-arrow-in-right me-2"></i>ورود به سیستم
                    </button>
                </form>
                
                <div class="text-center mt-3">
                    <small class="text-muted">نسخه ۱.۰.۰</small>
                </div>
            </div>
        </div>
    </div>
</body>
</html>