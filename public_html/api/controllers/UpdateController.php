<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class UpdateController {
    private PDO $pdo;

    public function __construct() {
        $this->pdo = Database::getConnection();
    }

    public function getUpdates(int $itemId): array {
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
    }

    public function createUpdate(int $itemId, array $data): array {
        $userName = trim($data['user_name'] ?? 'Team Member');
        $content = trim($data['content'] ?? '');

        if (empty($content)) {
            http_response_code(400);
            return ['success' => false, 'error' => 'Update content cannot be empty'];
        }

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

        // Also touch the parent item's updated_at timestamp so smart poller catches it
        $this->pdo->prepare("UPDATE items SET updated_at = CURRENT_TIMESTAMP WHERE id = :id")->execute([':id' => $itemId]);

        return [
            'success' => true,
            'update' => [
                'id' => $updateId,
                'item_id' => $itemId,
                'user_name' => $userName,
                'content' => $content,
                'likes_count' => 0,
                'created_at' => date('Y-m-d H:i:s')
            ]
        ];
    }
}
