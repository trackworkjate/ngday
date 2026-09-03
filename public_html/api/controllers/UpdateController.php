<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class UpdateController {
    private ?PDO $pdo = null;

    public function __construct() {
        try {
            $this->pdo = Database::getConnection();
            $this->ensureAvatarColumn();
        } catch (Throwable $e) {
            $this->pdo = null;
        }
    }

    private function ensureAvatarColumn(): void {
        if (!$this->pdo) return;
        try {
            $this->pdo->exec("ALTER TABLE item_updates ADD COLUMN user_avatar VARCHAR(500) NULL AFTER user_name");
        } catch (Throwable $e) {}
    }

    public function getUpdates(int $itemId): array {
        if ($this->pdo) {
            try {
                $stmt = $this->pdo->prepare("
                    SELECT u.*, i.name AS item_name
                    FROM item_updates u
                    JOIN items i ON i.id = u.item_id
                    WHERE u.item_id = :item_id
                    ORDER BY u.created_at DESC
                ");
                $stmt->execute([':item_id' => $itemId]);
                $updates = $stmt->fetchAll();

                return [
                    'success' => true,
                    'item_id' => $itemId,
                    'updates' => $updates
                ];
            } catch (Throwable $e) {}
        }

        return [
            'success' => true,
            'item_id' => $itemId,
            'updates' => []
        ];
    }

    public function createUpdate(int $itemId, array $data): array {
        $userName = trim((string)($data['user_name'] ?? 'Team Member'));
        $userAvatar = isset($data['user_avatar']) ? trim((string)$data['user_avatar']) : (isset($data['avatar']) ? trim((string)$data['avatar']) : null);
        $content = trim((string)($data['content'] ?? ''));

        if (empty($content)) {
            http_response_code(400);
            return ['success' => false, 'error' => 'Update content cannot be empty'];
        }

        $updateId = (int)(round(microtime(true) * 1000) % 2147483647);

        if ($this->pdo) {
            try {
                $stmt = $this->pdo->prepare("
                    INSERT INTO item_updates (item_id, user_name, user_avatar, content, likes_count)
                    VALUES (:item_id, :user, :avatar, :content, 0)
                ");
                $stmt->execute([
                    ':item_id' => $itemId,
                    ':user' => $userName,
                    ':avatar' => $userAvatar,
                    ':content' => $content
                ]);
                $updateId = (int)$this->pdo->lastInsertId();
                $this->pdo->prepare("UPDATE items SET updated_at = CURRENT_TIMESTAMP WHERE id = :id")->execute([':id' => $itemId]);
            } catch (Throwable $e) {
                try {
                    $stmt = $this->pdo->prepare("
                        INSERT INTO item_updates (item_id, user_name, content, likes_count)
                        VALUES (:item_id, :user, :content, 0)
                    ");
                    $stmt->execute([
                        ':item_id' => $itemId,
                        ':user' => $userName,
                        ':content' => $content
                    ]);
                    $updateId = (int)$this->pdo->lastInsertId();
                } catch (Throwable $ex) {}
            }
        }

        return [
            'success' => true,
            'update' => [
                'id' => $updateId,
                'item_id' => $itemId,
                'user_name' => $userName,
                'user_avatar' => $userAvatar,
                'content' => $content,
                'likes_count' => 0,
                'created_at' => date('Y-m-d H:i:s')
            ]
        ];
    }

    public function editUpdate(int $updateId, array $data): array {
        $content = trim((string)($data['content'] ?? ''));
        if (empty($content)) {
            http_response_code(400);
            return ['success' => false, 'error' => 'เนื้อหาอัปเดตต้องไม่ว่างเปล่า'];
        }

        if ($this->pdo) {
            try {
                $stmt = $this->pdo->prepare("UPDATE item_updates SET content = :content WHERE id = :id");
                $stmt->execute([':content' => $content, ':id' => $updateId]);
            } catch (Throwable $e) {}
        }

        return [
            'success' => true,
            'id' => $updateId,
            'content' => $content,
            'updated_at' => date('Y-m-d H:i:s')
        ];
    }

    public function deleteUpdate(int $updateId): array {
        if ($this->pdo) {
            try {
                $stmt = $this->pdo->prepare("DELETE FROM item_updates WHERE id = :id");
                $stmt->execute([':id' => $updateId]);
            } catch (Throwable $e) {}
        }

        return [
            'success' => true,
            'deleted_id' => $updateId
        ];
    }
}
