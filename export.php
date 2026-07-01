<?php
session_start();

// ۱. بررسی لاگین بودن کاربر
if (!isset($_SESSION['username'])) {
    header("Location: login.html");
    exit;
}

// جلوگیری از ورود کاربران با دسترسی محدود
if (isset($_SESSION['role']) && $_SESSION['role'] === 'limited' && $_SESSION['username'] !== 'iman') {
    die("خطای امنیتی: شما دسترسی لازم برای ورود به این بخش را ندارید.");
}

$db_path = __DIR__ . '/database.sqlite';

// ==========================================
// پردازش درخواست‌های دانلود فایل اکسل (CSV) با فیلتر تاریخ
// ==========================================
if (isset($_GET['action'])) {
    try {
        $db = new PDO('sqlite:' . $db_path);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $action = $_GET['action'];

        // دریافت تاریخ‌های فیلتر شده از URL
        $start_date = trim($_GET['start_date'] ?? '');
        $end_date = trim($_GET['end_date'] ?? '');

        // تابع هوشمند برای تبدیل جداول به اکسل با قابلیت اعمال فیلتر تاریخ
        function generateCSVExport($db, $tableName, $filePrefix, $dateColumn = null, $customHeaders = null, $customQuery = null) {
            global $start_date, $end_date;
            
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=' . $filePrefix . '_export_' . date('Y-m-d') . '.csv');
            
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM برای فارسی

            // چاپ هدرها
            if ($customHeaders) {
                fputcsv($output, $customHeaders);
            } else {
                $stmt = $db->query("PRAGMA table_info($tableName)");
                $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $headers = array_column($columns, 'name');
                fputcsv($output, $headers);
            }

            // سیستم ساخت Query هوشمند بر اساس تاریخ وارد شده
            if ($customQuery) {
                $query = $customQuery;
                $stmt = $db->prepare($query);
                $stmt->execute();
            } else {
                $whereClause = "";
                $params = [];

                if ($dateColumn && (!empty($start_date) || !empty($end_date))) {
                    $conditions = [];
                    if (!empty($start_date)) {
                        $conditions[] = "$dateColumn >= :start";
                        $params[':start'] = $start_date;
                    }
                    if (!empty($end_date)) {
                        $conditions[] = "$dateColumn <= :end";
                        $params[':end'] = $end_date;
                    }
                    $whereClause = " WHERE " . implode(" AND ", $conditions);
                }

                $query = "SELECT * FROM $tableName $whereClause ORDER BY id DESC";
                $stmt = $db->prepare($query);
                $stmt->execute($params);
            }

            // نوشتن ردیف‌ها در فایل
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($output, $row);
            }
            fclose($output);
            exit;
        }

        // --- ۱. دانلود جراحی‌ها ---
        if ($action === 'export_surgeries') {
            generateCSVExport($db, 'or_assignments', 'surgeries', 'shift_date');
        }

        // --- ۲. دانلود برنامه تقویم شیفت‌ها ---
        if ($action === 'export_schedules') {
            $db->exec("CREATE TABLE IF NOT EXISTS schedules (id INTEGER PRIMARY KEY AUTOINCREMENT, schedule_date TEXT, morning_shift TEXT, afternoon_shift TEXT, night_shift TEXT, morning_reg TEXT, afternoon_reg TEXT, night_reg TEXT)");
            generateCSVExport($db, 'schedules', 'schedules', 'schedule_date');
        }

        // --- ۳. دانلود گزارشات بالینی اتاق عمل ---
        if ($action === 'export_reports') {
            $db->exec("CREATE TABLE IF NOT EXISTS reports_cal (id INTEGER PRIMARY KEY AUTOINCREMENT, report_date TEXT, morning_report TEXT, afternoon_report TEXT, night_report TEXT, morning_reg TEXT, afternoon_reg TEXT, night_reg TEXT)");
            generateCSVExport($db, 'reports_cal', 'or_clinical_reports', 'report_date');
        }

        // --- ۴. دانلود گزارشات واحد CSR ---
        if ($action === 'export_csr_reports') {
            $db->exec("CREATE TABLE IF NOT EXISTS csr_reports_cal (id INTEGER PRIMARY KEY AUTOINCREMENT, report_date TEXT, morning_report TEXT, afternoon_report TEXT, night_report TEXT, morning_reg TEXT, afternoon_reg TEXT, night_reg TEXT)");
            generateCSVExport($db, 'csr_reports_cal', 'csr_unit_reports', 'report_date');
        }

        // --- ۵. دانلود لیست کاربران (منحصراً برای یوزر iman) ---
        if ($action === 'export_users') {
            if ($_SESSION['username'] !== 'iman') {
                die("خطای امنیتی: دسترسی غیرمجاز! این فایل محرمانه فقط در اختیار مدیریت کل سیستم است.");
            }
            
            $columnsInfo = $db->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC);
            $existingColumns = array_column($columnsInfo, 'name');
            if (!in_array('created_at', $existingColumns)) { $db->exec("ALTER TABLE users ADD COLUMN created_at TEXT"); }
            if (!in_array('full_name', $existingColumns)) { $db->exec("ALTER TABLE users ADD COLUMN full_name TEXT"); }
            if (!in_array('department', $existingColumns)) { $db->exec("ALTER TABLE users ADD COLUMN department TEXT"); }

            generateCSVExport(
                $db, 'users', 'system_users', null, 
                ['شناسه', 'نام کاربری', 'نام و نام خانوادگی', 'بخش مربوطه', 'سطح دسترسی', 'تاریخ ثبت نام'], 
                "SELECT id, username, full_name, department, role, created_at FROM users ORDER BY id DESC"
            );
        }

    } catch (Exception $e) {
        die("خطا در سیستم پردازش دیتابیس: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>مرکز مدیریت خروجی و پشتیبان‌گیری سیستم</title>
    <style>
        body {
            font-family: Tahoma, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            margin: 0; padding: 20px; display: flex; justify-content: center; align-items: center;
            min-height: 100vh; color: #333; box-sizing: border-box;
        }
        .container {
            background: #ffffff; padding: 40px; border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1); text-align: center; width: 100%; max-width: 550px;
            border-top: 6px solid #f1c40f;
        }
        .icon { font-size: 3.5rem; margin-bottom: 10px; }
        h1 { color: #2c3e50; font-size: 1.6rem; margin: 0 0 10px 0; }
        p { color: #7f8c8d; font-size: 0.95rem; margin-bottom: 20px; line-height: 1.6; }
        
        /* استایل باکس فیلتر تاریخ */
        .filter-box {
            background: #f8f9fa; border: 1px solid #e1e8ed; border-radius: 12px;
            padding: 20px; margin-bottom: 25px; display: flex; gap: 15px; 
            justify-content: center; align-items: center; flex-wrap: wrap;
        }
        .filter-group { display: flex; flex-direction: column; text-align: right; gap: 5px; }
        .filter-group label { font-size: 0.85rem; font-weight: bold; color: #34495e; }
        .filter-group input {
            padding: 10px; border: 2px solid #cbd5e1; border-radius: 8px;
            font-family: Tahoma; width: 160px; outline: none; transition: 0.3s; text-align: center;
        }
        .filter-group input:focus { border-color: #f1c40f; }

        .export-grid { display: flex; flex-direction: column; gap: 12px; }
        .export-btn {
            display: flex; align-items: center; justify-content: space-between;
            width: 100%; padding: 14px 20px; border-radius: 12px; font-size: 1rem; font-weight: bold; 
            text-decoration: none; transition: all 0.25s ease; box-sizing: border-box;
            border: 2px solid #e1e8ed; background: #fff; color: #2c3e50; cursor: pointer;
        }
        .export-btn:hover { background: #edf2f7; border-color: #cbd5e1; transform: translateY(-2px); }
        .export-btn span.emoji { font-size: 1.3rem; }
        .export-btn span.download-text { flex-grow: 1; text-align: right; margin-right: 15px; }
        .export-btn::after { content: '📥'; font-size: 1rem; opacity: 0.6; }

        .btn-users { background: #fff3cd; color: #856404; border-color: #ffeeba; }
        .btn-users:hover { background: #ffe8a1; border-color: #ffe293; }
        .btn-users::after { content: '👑'; }

        .back-link { display: inline-block; margin-top: 25px; color: #95a5a6; text-decoration: none; font-weight: bold; transition: 0.2s; font-size: 0.95rem; }
        .back-link:hover { color: #34495e; }
        .hint { font-size: 0.8rem; color: #e74c3c; margin-top: 10px; text-align: center; width: 100%; }
    </style>
</head>
<body>

    <div class="container">
        <div class="icon">📊</div>
        <h1>مرکز پشتیبان‌گیری سیستم</h1>
        <p>بخش مورد نظر را انتخاب کنید. در صورت تمایل می‌توانید دریافت فایل را به یک بازه تاریخی خاص محدود کنید.</p>

        <div class="filter-box">
            <div class="filter-group">
                <label>از تاریخ:</label>
                <input type="text" id="start_date" placeholder="مثال: 1405/03/01">
            </div>
            <div class="filter-group">
                <label>تا تاریخ:</label>
                <input type="text" id="end_date" placeholder="مثال: 1405/03/30">
            </div>
            <div class="hint">توجه: فرمت تاریخ را دقیقاً مطابق با آنچه در سیستم ثبت کرده‌اید وارد کنید (شمسی یا میلادی). در صورت خالی گذاشتن، کل اطلاعات استخراج می‌شود.</div>
        </div>

        <div class="export-grid">
            <button onclick="downloadExport('export_surgeries')" class="export-btn">
                <span class="emoji">🏥</span> <span class="download-text">بایگانی جراحی‌ها و مانیتور اتاق عمل</span>
            </button>

            <button onclick="downloadExport('export_schedules')" class="export-btn">
                <span class="emoji">📅</span> <span class="download-text">برنامه تقویم شیفت‌های پرسنل</span>
            </button>

            <button onclick="downloadExport('export_reports')" class="export-btn">
                <span class="emoji">📝</span> <span class="download-text">دفترچه گزارشات بالینی اتاق عمل</span>
            </button>

            <button onclick="downloadExport('export_csr_reports')" class="export-btn">
                <span class="emoji">📦</span> <span class="download-text">دفترچه عملکرد و گزارشات واحد CSR</span>
            </button>

            <?php if ($_SESSION['username'] === 'iman'): ?>
            <button onclick="downloadExport('export_users')" class="export-btn btn-users">
                <span class="emoji">👤</span> <span class="download-text">لیست پرسنل و دسترسی‌های حساب (کل کاربران)</span>
            </button>
            <?php endif; ?>
        </div>

        <a href="dashboard.html" class="back-link">🔙 بازگشت به داشبورد مدیریتی</a>
    </div>

    <script>
        // اسکریپت جاوااسکریپت برای چسباندن تاریخ‌ها به لینک دانلود
        function downloadExport(actionName) {
            const startDate = document.getElementById('start_date').value.trim();
            const endDate = document.getElementById('end_date').value.trim();
            
            let url = `?action=${actionName}`;
            
            if (startDate !== '') {
                url += `&start_date=${encodeURIComponent(startDate)}`;
            }
            if (endDate !== '') {
                url += `&end_date=${encodeURIComponent(endDate)}`;
            }
            
            // هدایت مرورگر به لینک دانلود
            window.location.href = url;
        }
    </script>

</body>
</html>