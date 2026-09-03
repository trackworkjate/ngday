<?php
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
header('Content-Type: text/html; charset=utf-8');

$messages = [];
try {
    require_once __DIR__ . '/api/config/database.php';
    $pdo = Database::getConnection();
    if ($pdo) {
        $stmt = $pdo->prepare("UPDATE users SET role = 'admin', name = 'Kraijate Sompong' WHERE email LIKE '%kraijate%' OR email LIKE '%krajjate%' OR email LIKE '%jate%' OR email = 'admin@nigiwaigroup.com'");
        $stmt->execute();
        $affected = $stmt->rowCount();
        $messages[] = "✅ อัปเดตฐานข้อมูล MySQL เรียบร้อย (จำนวนผู้ใช้ที่ปรับเป็น Admin: " . $affected . " บัญชี)";

        $chk = $pdo->query("SELECT id, name, email, role FROM users WHERE email LIKE '%kraijate%' OR email LIKE '%jate%' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($chk) {
            $messages[] = "👤 ข้อมูลในตาราง users ล่าสุด: <strong>" . htmlspecialchars($chk['name']) . "</strong> (" . htmlspecialchars($chk['email']) . ") => 👑 Role: <strong>" . htmlspecialchars($chk['role']) . "</strong>";
        }
    }
} catch (Throwable $e) {
    $messages[] = "⚠️ แจ้งเตือนฐานข้อมูล: " . htmlspecialchars($e->getMessage());
}

if (isset($_SESSION['user'])) {
    $_SESSION['user']['role'] = 'admin';
    $_SESSION['user']['name'] = 'Kraijate Sompong';
    $messages[] = "✅ อัปเดต Session ในเบราว์เซอร์ปัจจุบันเป็น 👑 ADMIN เรียบร้อย";
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>อัปเกรดสิทธิ์ Admin - Nigiwai PM</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f3f4f6; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; }
        .card { background: #fff; border-radius: 16px; padding: 32px; max-width: 520px; width: 100%; box-shadow: 0 10px 25px rgba(0,0,0,0.08); text-align: center; }
        h1 { font-size: 20px; color: #1e1b4b; margin-bottom: 16px; }
        .box { background: #e0e7ff; color: #3730a3; padding: 12px; border-radius: 10px; font-weight: bold; margin-bottom: 20px; font-size: 16px; }
        .list { text-align: left; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px; font-size: 13px; line-height: 1.6; margin-bottom: 24px; }
        .btn { display: inline-block; background: #4f46e5; color: #fff; padding: 12px 28px; border-radius: 10px; font-weight: bold; text-decoration: none; transition: background 0.2s; font-size: 14px; }
        .btn:hover { background: #4338ca; }
    </style>
</head>
<body>
    <div class="card">
        <h1>👑 ระบบตั้งค่าสิทธิ์ผู้ดูแลสูงสุด (Admin)</h1>
        <div class="box">บัญชี Kraijate Sompong ได้รับสิทธิ์ ADMIN แล้ว</div>
        <div class="list">
            <?php foreach ($messages as $m): ?>
                <div><?= $m ?></div>
            <?php endforeach; ?>
        </div>
        <a href="index.php" class="btn">🚀 เข้าสู่หน้ากระดาน Nigiwai PM</a>
    </div>
</body>
</html>
