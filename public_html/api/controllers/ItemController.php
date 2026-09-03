<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/DataPersistence.php';

class ItemController {
    private ?PDO $pdo = null;

    public function __construct() {
        try {
            $this->pdo = Database::getConnection();
        } catch (Throwable $e) {
            $this->pdo = null;
        }
    }

    public function create(array $data): array {
        $boardId = (int)($data['board_id'] ?? 1);
        $groupId = (int)($data['group_id'] ?? 1);
        $parentId = !empty($data['parent_id']) ? (int)$data['parent_id'] : null;
        $name = trim($data['name'] ?? 'New Item');
        $columnValues = !empty($data['column_values']) ? $data['column_values'] : new stdClass();

        $itemId = (int)(round(microtime(true) * 1000) % 2147483647);

        if ($this->pdo) {
            try {
                // Calculate next position
                if ($parentId === null) {
                    $stmtPos = $this->pdo->prepare("SELECT COALESCE(MAX(position), 0) + 1.0 AS next_pos FROM items WHERE group_id = :gid AND parent_id IS NULL");
                    $stmtPos->execute([':gid' => $groupId]);
                } else {
                    $stmtPos = $this->pdo->prepare("SELECT COALESCE(MAX(position), 0) + 1.0 AS next_pos FROM items WHERE parent_id = :pid");
                    $stmtPos->execute([':pid' => $parentId]);
                }
                $nextPos = (float)$stmtPos->fetch()['next_pos'];

                $stmt = $this->pdo->prepare("
                    INSERT INTO items (board_id, group_id, parent_id, name, column_values, position) 
                    VALUES (:bid, :gid, :pid, :name, :vals, :pos)
                ");
                $stmt->execute([
                    ':bid' => $boardId,
                    ':gid' => $groupId,
                    ':pid' => $parentId,
                    ':name' => $name,
                    ':vals' => json_encode($columnValues, JSON_UNESCAPED_UNICODE),
                    ':pos' => $nextPos
                ]);

                $itemId = (int)$this->pdo->lastInsertId();
            } catch (Throwable $e) {
                // Fallback
            }
        }

        return [
            'success' => true,
            'item' => [
                'id' => $itemId,
                'board_id' => $boardId,
                'group_id' => $groupId,
                'parent_id' => $parentId,
                'name' => $name,
                'column_values' => $columnValues,
                'position' => 1.0,
                'subitems' => [],
                'update_count' => 0
            ]
        ];
    }

    public function updateCell($itemId, array $data): array {
        $columnId = $data['column_id'] ?? '';
        $value = $data['value'] ?? null;

        if (empty($columnId)) {
            http_response_code(400);
            return ['success' => false, 'error' => 'Column ID is required'];
        }

        // 1. Always update JSON storage directly for instant disk persistence
        DataPersistence::updateCellInJson($itemId, $columnId, $value);

        // 2. Also update MySQL if connected
        if ($this->pdo) {
            try {
                $jsonPath = '$.' . $columnId;
                if ($value === null || $value === '') {
                    $stmt = $this->pdo->prepare("
                        UPDATE items 
                        SET column_values = JSON_REMOVE(COALESCE(column_values, '{}'), :path)
                        WHERE id = :id OR monday_item_id = :mid
                    ");
                    $stmt->execute([':path' => $jsonPath, ':id' => (int)$itemId, ':mid' => (string)$itemId]);
                } else {
                    $stmt = $this->pdo->prepare("
                        UPDATE items 
                        SET column_values = JSON_SET(COALESCE(column_values, '{}'), :path, :value)
                        WHERE id = :id OR monday_item_id = :mid
                    ");
                    $stmt->execute([
                        ':path' => $jsonPath,
                        ':value' => is_scalar($value) ? (string)$value : json_encode($value),
                        ':id' => (int)$itemId,
                        ':mid' => (string)$itemId
                    ]);
                }
            } catch (Throwable $e) {
                // Ignore DB error, JSON has persisted
            }
        }

        return [
            'success' => true,
            'item_id' => $itemId,
            'column_id' => $columnId,
            'value' => $value
        ];
    }

    public function updateName($itemId, string $name): array {
        $name = trim($name);
        if (empty($name)) {
            http_response_code(400);
            return ['success' => false, 'error' => 'Item name cannot be empty'];
        }

        DataPersistence::updateItemNameInJson($itemId, $name);

        if ($this->pdo) {
            try {
                $stmt = $this->pdo->prepare("UPDATE items SET name = :name WHERE id = :id OR monday_item_id = :mid");
                $stmt->execute([':name' => $name, ':id' => (int)$itemId, ':mid' => (string)$itemId]);
            } catch (Throwable $e) {}
        }

        return ['success' => true, 'id' => $itemId, 'name' => $name];
    }

    public function delete($itemId): array {
        DataPersistence::deleteItemsInJson([$itemId]);

        if ($this->pdo) {
            try {
                $stmt = $this->pdo->prepare("DELETE FROM items WHERE id = :id OR monday_item_id = :mid");
                $stmt->execute([':id' => (int)$itemId, ':mid' => (string)$itemId]);
            } catch (Throwable $e) {}
        }

        return ['success' => true, 'deleted_id' => $itemId];
    }
}
