<!DOCTYPE html>
<?php
// تعریف مسیر پایه (ریشه پروژه)


// تعریف مسیرهای پایه
$base_url = "http://" . $_SERVER['HTTP_HOST'];
$includes_url = $base_url . "/includes";
$modules_url = $base_url . "/modules";
$delete_url = $modules_url . "/delete";
$js_url = $includes_url . "/js";
$css_url = $includes_url . "/css";





require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/includes/csrf.php';
require_once BASE_PATH . '/includes/notifications.php';
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/includes/jdate.php'; // ✅ تاریخ شمسی


?>