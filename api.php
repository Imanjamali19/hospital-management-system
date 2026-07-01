<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

try {
    $db = new PDO('sqlite:' . __DIR__ . '/database.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $action = $_GET['action'] ?? '';

    if ($action === 'get_rooms') {
        $stmt = $db->query("SELECT id, room_name FROM rooms ORDER BY display_order ASC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    if ($action === 'get_form_fields') {
        $stmt = $db->query("SELECT field_id as id, field_label as label, field_placeholder as placeholder FROM form_fields");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    if ($action === 'get_today') {
        $today = date('Y-m-d');
        $stmt = $db->prepare("SELECT * FROM or_assignments WHERE shift_date = :today AND IFNULL(is_active, 1) = 1 ORDER BY id ASC");
        $stmt->execute([':today' => $today]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    if ($action === 'save_surgery') {
        // ساخت ستون‌های جدید (مثل shift_type) به صورت خودکار
        $columnsInfo = $db->query("PRAGMA table_info(or_assignments)")->fetchAll(PDO::FETCH_ASSOC);
        $existingColumns = array_column($columnsInfo, 'name');

        foreach ($_POST as $key => $value) {
            $safeKey = preg_replace('/[^a-zA-Z0-9_]/', '', $key); 
            if (!empty($safeKey) && !in_array($safeKey, $existingColumns)) {
                $db->exec("ALTER TABLE or_assignments ADD COLUMN {$safeKey} TEXT");
                $existingColumns[] = $safeKey; 
            }
        }

        $fields = ['shift_date', 'is_active'];
        $placeholders = [':shift_date', ':is_active'];
        $params = [':shift_date' => date('Y-m-d'), ':is_active' => 1];

        foreach ($_POST as $key => $value) {
            $safeKey = preg_replace('/[^a-zA-Z0-9_]/', '', $key);
            if (!empty($safeKey)) {
                $fields[] = $safeKey;
                $placeholders[] = ":{$safeKey}";
                $params[":{$safeKey}"] = $value;
            }
        }

        $fieldList = implode(', ', $fields);
        $placeholderList = implode(', ', $placeholders);

        $query = "INSERT INTO or_assignments ($fieldList) VALUES ($placeholderList)";
        $stmt = $db->prepare($query);
        $stmt->execute($params);

        echo json_encode(['status' => 'success', 'message' => 'اطلاعات با موفقیت ذخیره شد.']);
        exit;
    }

    if ($action === 'clear_board') {
        $today = date('Y-m-d');
        $stmt = $db->prepare("UPDATE or_assignments SET is_active = 0 WHERE shift_date = :today");
        $stmt->execute([':today' => $today]);

        echo json_encode(['status' => 'success', 'message' => 'برد با موفقیت پاکسازی شد.']);
        exit;
    }

    if ($action === 'save_schedule') {
        $db->exec("CREATE TABLE IF NOT EXISTS schedules (
            id INTEGER PRIMARY KEY AUTOINCREMENT, 
            schedule_date TEXT, 
            morning_shift TEXT, 
            afternoon_shift TEXT, 
            night_shift TEXT, 
            is_active INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $schedule_date = $_POST['schedule_date'] ?? '';
        $morning_shift = $_POST['morning_shift'] ?? '';
        $afternoon_shift = $_POST['afternoon_shift'] ?? '';
        $night_shift = $_POST['night_shift'] ?? '';

        $db->exec("UPDATE schedules SET is_active = 0 WHERE is_active = 1");

        $query = "INSERT INTO schedules (schedule_date, morning_shift, afternoon_shift, night_shift, is_active) 
                  VALUES (:schedule_date, :morning_shift, :afternoon_shift, :night_shift, 1)";
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':schedule_date' => $schedule_date,
            ':morning_shift' => $morning_shift,
            ':afternoon_shift' => $afternoon_shift,
            ':night_shift' => $night_shift
        ]);

        echo json_encode(['status' => 'success', 'message' => 'برنامه شیفت با موفقیت ذخیره شد.']);
        exit;
    }

    if ($action === 'clear_schedule') {
        $db->exec("CREATE TABLE IF NOT EXISTS schedules (id INTEGER PRIMARY KEY AUTOINCREMENT, schedule_date TEXT, morning_shift TEXT, afternoon_shift TEXT, night_shift TEXT, is_active INTEGER DEFAULT 1, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
        $db->exec("UPDATE schedules SET is_active = 0 WHERE is_active = 1");
        echo json_encode(['status' => 'success', 'message' => 'برنامه شیفت با موفقیت بایگانی شد.']);
        exit;
    }

    if ($action === 'upload_code_image') {
        $type = $_POST['code_type'] ?? '';
        if (!in_array($type, ['98', '99'])) { echo json_encode(['status' => 'error', 'message' => 'نوع کد نامعتبر است.']); exit; }
        if (!isset($_FILES['code_image']) || $_FILES['code_image']['error'] !== UPLOAD_ERR_OK) { echo json_encode(['status' => 'error', 'message' => 'عکسی انتخاب نشده یا آپلود با خطا مواجه شد.']); exit; }

        $uploadDir = __DIR__ . '/uploads/';
        if (!is_dir($uploadDir)) { mkdir($uploadDir, 0777, true); }

        $oldFiles = glob($uploadDir . 'code_' . $type . '.*');
        if ($oldFiles) { foreach ($oldFiles as $file) { unlink($file); } }

        $fileInfo = pathinfo($_FILES['code_image']['name']);
        $ext = strtolower($fileInfo['extension']);
        if (!in_array($ext, ['jpg', 'jpeg', 'png'])) { echo json_encode(['status' => 'error', 'message' => 'فقط فرمت‌های JPG و PNG مجاز هستند.']); exit; }

        $fileName = 'code_' . $type . '.' . $ext;
        if (move_uploaded_file($_FILES['code_image']['tmp_name'], $uploadDir . $fileName)) {
            echo json_encode(['status' => 'success']);
        } else { echo json_encode(['status' => 'error', 'message' => 'خطا در ذخیره فایل در سرور.']); }
        exit;
    }

    if ($action === 'get_code_image') {
        $type = $_GET['type'] ?? '';
        $uploadDir = __DIR__ . '/uploads/';
        $files = glob($uploadDir . 'code_' . $type . '.*');
        
        if (!empty($files)) {
            $file = $files[0];
            $ext = pathinfo($file, PATHINFO_EXTENSION);
            $mime = ($ext == 'png') ? 'image/png' : 'image/jpeg';
            header('Content-Type: ' . $mime);
            readfile($file);
            exit;
        } else {
            header("HTTP/1.0 404 Not Found"); echo "تصویری یافت نشد."; exit;
        }
    }

    echo json_encode(['status' => 'error', 'message' => 'عملیات نامشخص است']);
    exit;

} catch (PDOException $e) { echo json_encode(['status' => 'error', 'message' => 'خطای دیتابیس: ' . $e->getMessage()]); }
catch (Exception $e) { echo json_encode(['status' => 'error', 'message' => 'خطای سیستمی: ' . $e->getMessage()]); }
?>