<?php
try {
    $db = new PDO('sqlite:' . __DIR__ . '/database.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ۱. ساخت جداول پایه (اگر از قبل باشند، کدهای این بخش کلاً نادیده گرفته می‌شوند)
    $db->exec("CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT UNIQUE, password TEXT, role TEXT)");
    $db->exec("CREATE TABLE IF NOT EXISTS or_assignments (id INTEGER PRIMARY KEY AUTOINCREMENT, room_id INTEGER, shift_date TEXT, surgeon TEXT, anesthesia_attending TEXT, or_staff TEXT, anesthesia_staff TEXT, csr_staff TEXT, hit_staff TEXT, auxiliary_staff TEXT, host_staff TEXT, storekeeper TEXT, code_force TEXT)");
    
    $db->exec("CREATE TABLE IF NOT EXISTS form_fields (id INTEGER PRIMARY KEY AUTOINCREMENT, field_id TEXT UNIQUE, field_label TEXT, field_placeholder TEXT)");
    $db->exec("CREATE TABLE IF NOT EXISTS rooms (id INTEGER PRIMARY KEY AUTOINCREMENT, room_name TEXT UNIQUE, display_order INTEGER)");

    // ==========================================
    // ۲. هسته آپدیت هوشمند (بدون پاک شدن اطلاعات قبلی)
    // ==========================================
    
    // الف: گرفتن لیست تمام ستون‌های فعلی جدول از دیتابیس
    $columnsInfo = $db->query("PRAGMA table_info(or_assignments)")->fetchAll(PDO::FETCH_ASSOC);
    $existingColumns = array_column($columnsInfo, 'name');

    // ب: چک کردن ستون «ساعت عمل» - اگر نبود، اضافه‌اش کن
    if (!in_array('surgery_time', $existingColumns)) {
        $db->exec("ALTER TABLE or_assignments ADD COLUMN surgery_time TEXT");
    }
    
    // ج: چک کردن ستون «تاریخ عمل» - اگر نبود، اضافه‌اش کن
    if (!in_array('surgery_date', $existingColumns)) {
        $db->exec("ALTER TABLE or_assignments ADD COLUMN surgery_date TEXT");
    }

    // ==========================================

    // ۳. تزریق اطلاعات پیش‌فرض
    $db->exec("INSERT OR IGNORE INTO form_fields (field_id, field_label, field_placeholder) VALUES 
        ('surgeon', 'جراح', 'مثال: دکتر محمدی'),
        ('anesthesia_attending', 'اتند بیهوشی', ''),
        ('or_staff', 'پرسنل اتاق عمل', ''),
        ('anesthesia_staff', 'پرسنل هوشبری', ''),
        ('csr_staff', 'پرسنل CSR', ''),
        ('hit_staff', 'پرسنل HIT', ''),
        ('auxiliary_staff', 'پرسنل کمکی', ''),
        ('host_staff', 'پرسنل مهماندار', ''),
        ('storekeeper', 'انباردار', ''),
        ('code_force', 'نیروی کد', '')
    ");

    $db->exec("INSERT OR IGNORE INTO rooms (id, room_name, display_order) VALUES 
        (1, 'اتاق عمل ۱', 1), (2, 'اتاق عمل ۲', 2), (3, 'اتاق عمل ۳', 3), (4, 'اتاق عمل ۴', 4), (5, 'اتاق عمل ۵', 5), (6, 'ریکاوری', 6), (7, 'اتاق عمل تابان', 7)
    ");

    $pass = password_hash('admin123', PASSWORD_DEFAULT);
    $db->exec("INSERT OR IGNORE INTO users (username, password, role) VALUES ('admin', '$pass', 'superadmin')");

    echo "✅ سیستم با موفقیت اسکن شد. اگر فیلد جدیدی نیاز بود، بدون حذف دیتای قبلی اضافه گردید!";
} catch (Exception $e) {
    echo "خطا: " . $e->getMessage();
}
?>