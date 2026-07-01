<?php
session_start();

// دریافت اطلاعات ارسالی از فرم لاگین
$user = $_POST['username'] ?? '';
$pass = $_POST['password'] ?? '';

try {
    // ۱. اتصال به دیتابیس با مسیر مطلق (رفع مشکل IIS در پیدا کردن فایل)
    $db = new PDO('sqlite:' . __DIR__ . '/database.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ۲. جستجوی نام کاربری در جدول کاربران
    $stmt = $db->prepare("SELECT * FROM users WHERE username = :user");
    $stmt->execute([':user' => $user]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);

    // ۳. بررسی تطابق رمز عبور
    if ($userData && password_verify($pass, $userData['password'])) {
        // ورود موفق: ایجاد کارت تردد (سشن) برای این کاربر
        $_SESSION['is_logged_in'] = true;
        $_SESSION['username'] = $userData['username'];
        $_SESSION['role'] = $userData['role'];
        
        // فرستادن پیام موفقیت به جاوااسکریپتِ صفحه لاگین
        echo 'success';
    } else {
        // رمز یا نام کاربری اشتباه است
        echo 'error';
    }
} catch (PDOException $e) {
    // در صورت قطعی یا ارور دیتابیس، متن دقیق ارور ارسال می‌شود
    echo 'خطای سرور/دیتابیس: ' . $e->getMessage();
}
?>