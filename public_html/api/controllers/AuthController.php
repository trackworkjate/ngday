<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class AuthController {
    private static string $configFile = __DIR__ . '/../config/auth_config.php';

    public static function getConfig(): array {
        $defaults = [
            'google_client_id' => '',
            'allowed_domain' => '',
            'default_role' => 'member',
            'mock_mode_enabled' => true
        ];

        if (file_exists(self::$configFile)) {
            $saved = include self::$configFile;
            if (is_array($saved)) {
                return array_merge($defaults, $saved);
            }
        }
        return $defaults;
    }

    public static function getPublicConfig(): array {
        $cfg = self::getConfig();
        return [
            'google_client_id' => $cfg['google_client_id'],
            'allowed_domain' => $cfg['allowed_domain'],
            'default_role' => $cfg['default_role'],
            'mock_mode_enabled' => (bool)$cfg['mock_mode_enabled']
        ];
    }

    public static function saveConfig(array $newConfig): bool {
        $current = self::getConfig();
        $updated = [
            'google_client_id' => trim((string)($newConfig['google_client_id'] ?? $current['google_client_id'])),
            'allowed_domain' => trim((string)($newConfig['allowed_domain'] ?? $current['allowed_domain'])),
            'default_role' => in_array($newConfig['default_role'] ?? '', ['admin', 'manager', 'member', 'viewer'], true) 
                ? $newConfig['default_role'] : $current['default_role'],
            'mock_mode_enabled' => isset($newConfig['mock_mode_enabled']) 
                ? (bool)$newConfig['mock_mode_enabled'] : $current['mock_mode_enabled']
        ];

        $content = "<?php\n// Auto-generated Auth Configuration for Nigiwai PM\nreturn " . var_export($updated, true) . ";\n";
        return file_put_contents(self::$configFile, $content) !== false;
    }

    public static function ensureTables(PDO $pdo): void {
        $userTableSql = "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            google_id VARCHAR(100) NULL UNIQUE,
            email VARCHAR(191) NOT NULL UNIQUE,
            name VARCHAR(255) NOT NULL,
            avatar VARCHAR(500) NULL,
            role ENUM('admin', 'manager', 'member', 'viewer') NOT NULL DEFAULT 'member',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            last_login DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user_email (email),
            INDEX idx_user_role (role)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        $pdo->exec($userTableSql);

        $settingsSql = "CREATE TABLE IF NOT EXISTS system_settings (
            setting_key VARCHAR(64) PRIMARY KEY,
            setting_value TEXT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        $pdo->exec($settingsSql);

        // Check if any user exists; if completely empty, seed a default admin
        $stmt = $pdo->query("SELECT COUNT(*) FROM users");
        $count = (int)$stmt->fetchColumn();
        if ($count === 0) {
            $ins = $pdo->prepare("INSERT INTO users (name, email, role, avatar, is_active) VALUES (?, ?, 'admin', ?, 1)");
            $ins->execute([
                'Nigiwai Admin',
                'admin@nigiwaigroup.com',
                'https://ui-avatars.com/api/?name=Nigiwai+Admin&background=0D8ABC&color=fff&rounded=true'
            ]);
        }
    }

    private function getPdoSafe(): ?PDO {
        try {
            $pdo = Database::getInstance();
            self::ensureTables($pdo);
            return $pdo;
        } catch (Throwable $e) {
            return null;
        }
    }

    private function startSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
    }

    public function getCurrentUser(): array {
        $this->startSession();
        $user = $_SESSION['user'] ?? null;
        $config = self::getPublicConfig();

        if ($user) {
            $pdo = $this->getPdoSafe();
            if ($pdo && !empty($user['id'])) {
                $stmt = $pdo->prepare("SELECT id, google_id, email, name, avatar, role, is_active FROM users WHERE id = ?");
                $stmt->execute([$user['id']]);
                $dbUser = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($dbUser) {
                    if ((int)$dbUser['is_active'] === 0) {
                        unset($_SESSION['user']);
                        return [
                            'success' => false,
                            'logged_in' => false,
                            'error' => 'บัญชีผู้ใช้นี้ถูกระงับการใช้งาน',
                            'user' => null,
                            'config' => $config
                        ];
                    }
                    $user = array_merge($user, $dbUser);
                    $_SESSION['user'] = $user;
                }
            }

            return [
                'success' => true,
                'logged_in' => true,
                'user' => $user,
                'config' => $config
            ];
        }

        return [
            'success' => true,
            'logged_in' => false,
            'user' => null,
            'config' => $config
        ];
    }

    public function googleLogin(string $idToken): array {
        $this->startSession();
        $idToken = trim($idToken);
        if (empty($idToken)) {
            return ['success' => false, 'error' => 'Token ไม่ถูกต้องหรือว่างเปล่า'];
        }

        // Verify token with Google TokenInfo API (Zero external library dependencies)
        $verifyUrl = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken);
        $response = @file_get_contents($verifyUrl);
        if ($response === false) {
            // Try cURL fallback if allow_url_fopen is off
            if (function_exists('curl_init')) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $verifyUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                $response = curl_exec($ch);
                curl_close($ch);
            }
        }

        if (empty($response)) {
            return ['success' => false, 'error' => 'ไม่สามารถเชื่อมต่อเพื่อยืนยันตัวตนกับ Google ได้'];
        }

        $payload = json_decode($response, true);
        if (!is_array($payload) || !empty($payload['error_description']) || empty($payload['sub'])) {
            return ['success' => false, 'error' => 'Google Token ไม่ถูกต้องหรือหมดอายุ: ' . ($payload['error_description'] ?? '')];
        }

        $googleId = (string)$payload['sub'];
        $email = strtolower(trim((string)($payload['email'] ?? '')));
        $name = trim((string)($payload['name'] ?? $email));
        $avatar = (string)($payload['picture'] ?? ("https://ui-avatars.com/api/?name=" . urlencode($name) . "&background=random"));
        $domain = (string)($payload['hd'] ?? '');

        // Domain restriction check
        $config = self::getConfig();
        if (!empty($config['allowed_domain'])) {
            $reqDomain = strtolower(ltrim($config['allowed_domain'], '@'));
            $emailDomain = substr(strrchr($email, "@") ?: '', 1);
            if ($domain !== $reqDomain && $emailDomain !== $reqDomain) {
                return [
                    'success' => false,
                    'error' => "กรุณาใช้อีเมลองค์กร @{$reqDomain} เพื่อเข้าสู่ระบบเท่านั้น"
                ];
            }
        }

        // Connect DB and Upsert User
        $pdo = $this->getPdoSafe();
        $userRecord = null;

        if ($pdo) {
            // Find by email or google_id
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR google_id = ? LIMIT 1");
            $stmt->execute([$email, $googleId]);
            $userRecord = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($userRecord) {
                if ((int)$userRecord['is_active'] === 0) {
                    return ['success' => false, 'error' => 'บัญชีผู้ใช้นี้ถูกระงับการใช้งาน กรุณาติดต่อผู้ดูแลระบบ'];
                }

                // Update latest name, avatar, google_id and last_login
                $upd = $pdo->prepare("UPDATE users SET name = ?, avatar = ?, google_id = ?, last_login = NOW() WHERE id = ?");
                $upd->execute([$name, $avatar, $googleId, $userRecord['id']]);
                $userRecord['name'] = $name;
                $userRecord['avatar'] = $avatar;
                $userRecord['google_id'] = $googleId;
            } else {
                // Determine initial role: First user is admin, otherwise default_role
                $cntStmt = $pdo->query("SELECT COUNT(*) FROM users");
                $totalUsers = (int)$cntStmt->fetchColumn();
                $role = ($totalUsers === 0) ? 'admin' : ($config['default_role'] ?: 'member');

                $ins = $pdo->prepare("INSERT INTO users (google_id, email, name, avatar, role, is_active, last_login) VALUES (?, ?, ?, ?, ?, 1, NOW())");
                $ins->execute([$googleId, $email, $name, $avatar, $role]);
                $newId = (int)$pdo->lastInsertId();

                $userRecord = [
                    'id' => $newId,
                    'google_id' => $googleId,
                    'email' => $email,
                    'name' => $name,
                    'avatar' => $avatar,
                    'role' => $role,
                    'is_active' => 1
                ];
            }
        } else {
            // Fallback session without DB
            $userRecord = [
                'id' => 1,
                'google_id' => $googleId,
                'email' => $email,
                'name' => $name,
                'avatar' => $avatar,
                'role' => 'admin',
                'is_active' => 1
            ];
        }

        $_SESSION['user'] = $userRecord;

        return [
            'success' => true,
            'message' => 'เข้าสู่ระบบสำเร็จ',
            'user' => $userRecord
        ];
    }

    public function mockLogin(string $role, ?string $name = null, ?string $email = null): array {
        $this->startSession();
        $validRoles = ['admin', 'manager', 'member', 'viewer'];
        if (!in_array($role, $validRoles, true)) {
            $role = 'member';
        }

        $defaultNames = [
            'admin' => 'ผู้ดูแลระบบ (Admin)',
            'manager' => 'ผู้จัดการโครงการ (Manager)',
            'member' => 'ผู้รับผิดชอบงาน (Member)',
            'viewer' => 'ผู้เข้าชม (Viewer)'
        ];

        $defaultEmails = [
            'admin' => 'admin@nigiwaigroup.com',
            'manager' => 'manager@nigiwaigroup.com',
            'member' => 'member@nigiwaigroup.com',
            'viewer' => 'viewer@nigiwaigroup.com'
        ];

        $roleColors = [
            'admin' => '6366F1',
            'manager' => '0EA5E9',
            'member' => '10B981',
            'viewer' => '8B5CF6'
        ];

        $finalName = $name ?: $defaultNames[$role];
        $finalEmail = $email ?: $defaultEmails[$role];
        $finalAvatar = "https://ui-avatars.com/api/?name=" . urlencode($finalName) . "&background=" . $roleColors[$role] . "&color=fff&bold=true";

        $pdo = $this->getPdoSafe();
        $userRecord = null;

        if ($pdo) {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$finalEmail]);
            $userRecord = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($userRecord) {
                $upd = $pdo->prepare("UPDATE users SET role = ?, name = ?, avatar = ?, last_login = NOW(), is_active = 1 WHERE id = ?");
                $upd->execute([$role, $finalName, $finalAvatar, $userRecord['id']]);
                $userRecord['role'] = $role;
                $userRecord['name'] = $finalName;
                $userRecord['avatar'] = $finalAvatar;
            } else {
                $ins = $pdo->prepare("INSERT INTO users (email, name, avatar, role, is_active, last_login) VALUES (?, ?, ?, ?, 1, NOW())");
                $ins->execute([$finalEmail, $finalName, $finalAvatar, $role]);
                $userRecord = [
                    'id' => (int)$pdo->lastInsertId(),
                    'email' => $finalEmail,
                    'name' => $finalName,
                    'avatar' => $finalAvatar,
                    'role' => $role,
                    'is_active' => 1
                ];
            }
        } else {
            $userRecord = [
                'id' => 999,
                'email' => $finalEmail,
                'name' => $finalName,
                'avatar' => $finalAvatar,
                'role' => $role,
                'is_active' => 1
            ];
        }

        $_SESSION['user'] = $userRecord;

        return [
            'success' => true,
            'message' => "เข้าสู่ระบบในฐานะ {$role} สำเร็จ",
            'user' => $userRecord
        ];
    }

    public function logout(): array {
        $this->startSession();
        unset($_SESSION['user']);
        @session_destroy();
        return ['success' => true, 'message' => 'ออกจากระบบเรียบร้อยแล้ว'];
    }

    public function listUsers(): array {
        $this->startSession();
        $curr = $_SESSION['user'] ?? null;
        if (!$curr || ($curr['role'] ?? '') !== 'admin') {
            return ['success' => false, 'error' => 'เฉพาะผู้ดูแลระบบ (Admin) เท่านั้นที่สามารถดูรายชื่อผู้ใช้งานได้'];
        }

        $pdo = $this->getPdoSafe();
        if (!$pdo) {
            return ['success' => true, 'users' => []];
        }

        $stmt = $pdo->query("SELECT id, google_id, email, name, avatar, role, is_active, last_login, created_at FROM users ORDER BY role ASC, name ASC");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ['success' => true, 'users' => $users];
    }

    public function updateUserRole(int $userId, string $newRole, ?int $isActive = null): array {
        $this->startSession();
        $curr = $_SESSION['user'] ?? null;
        if (!$curr || ($curr['role'] ?? '') !== 'admin') {
            return ['success' => false, 'error' => 'เฉพาะผู้ดูแลระบบ (Admin) เท่านั้นที่สามารถเปลี่ยนสิทธิ์ผู้ใช้งานได้'];
        }

        $validRoles = ['admin', 'manager', 'member', 'viewer'];
        if (!in_array($newRole, $validRoles, true)) {
            return ['success' => false, 'error' => 'บทบาท (Role) ไม่ถูกต้อง'];
        }

        $pdo = $this->getPdoSafe();
        if (!$pdo) {
            return ['success' => false, 'error' => 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้'];
        }

        if ($isActive !== null) {
            $upd = $pdo->prepare("UPDATE users SET role = ?, is_active = ? WHERE id = ?");
            $upd->execute([$newRole, (int)$isActive, $userId]);
        } else {
            $upd = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
            $upd->execute([$newRole, $userId]);
        }

        // If updating the currently logged-in user, refresh their session
        if ($curr && (int)($curr['id'] ?? 0) === $userId) {
            $_SESSION['user']['role'] = $newRole;
        }

        return ['success' => true, 'message' => 'อัปเดตสิทธิ์ผู้ใช้งานเรียบร้อยแล้ว'];
    }

    public function saveAuthConfig(array $config): array {
        $this->startSession();
        $curr = $_SESSION['user'] ?? null;
        if (!$curr || ($curr['role'] ?? '') !== 'admin') {
            return ['success' => false, 'error' => 'เฉพาะผู้ดูแลระบบ (Admin) เท่านั้นที่สามารถตั้งค่าระบบได้'];
        }

        $saved = self::saveConfig($config);
        return [
            'success' => $saved,
            'message' => $saved ? 'บันทึกการตั้งค่า Google Sign-In สำเร็จ' : 'ไม่สามารถบันทึกไฟล์ config ได้',
            'config' => self::getPublicConfig()
        ];
    }
}
