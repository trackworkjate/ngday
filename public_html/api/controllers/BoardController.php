<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/DataPersistence.php';

class BoardController {
    private ?PDO $pdo = null;

    public function __construct() {
        try {
            $this->pdo = Database::getConnection();
        } catch (Throwable $e) {
            $this->pdo = null;
        }
    }

    public function index(): array {
        if ($this->pdo) {
            try {
                $stmt = $this->pdo->query('SELECT * FROM boards ORDER BY id ASC');
                $boards = $stmt->fetchAll();
                if (!empty($boards)) {
                    return ['success' => true, 'boards' => $boards];
                }
            } catch (Throwable $e) {}
        }
        $data = DataPersistence::loadBoardJson();
        return ['success' => true, 'boards' => [$data['board'] ?? ['id' => 1, 'name' => 'Branch Planning 2026']]];
    }

    public function getFull(int $id): array {
        if ($this->pdo) {
            try {
                $stmt = $this->pdo->prepare('SELECT * FROM boards WHERE id = :id');
                $stmt->execute([':id' => $id]);
                $board = $stmt->fetch();

                if ($board) {
                    $stmtCols = $this->pdo->prepare('SELECT * FROM board_columns WHERE board_id = :bid ORDER BY position ASC');
                    $stmtCols->execute([':bid' => $id]);
                    $cols = $stmtCols->fetchAll();

                    $mainCols = [];
                    $subCols = [];
                    foreach ($cols as $col) {
                        $col['is_subitem'] = (bool)$col['is_subitem'];
                        $col['settings'] = !empty($col['settings']) ? json_decode($col['settings'], true) : new stdClass();
                        if ($col['is_subitem']) {
                            $subCols[] = $col;
                        } else {
                            $mainCols[] = $col;
                        }
                    }

                    $stmtGroups = $this->pdo->prepare('SELECT * FROM board_groups WHERE board_id = :bid ORDER BY position ASC');
                    $stmtGroups->execute([':bid' => $id]);
                    $groups = $stmtGroups->fetchAll();

                    if (!empty($groups)) {
                        $stmtItems = $this->pdo->prepare('
                            SELECT i.*, (SELECT COUNT(*) FROM item_updates u WHERE u.item_id = i.id) AS update_count
                            FROM items i
                            WHERE i.board_id = :bid
                            ORDER BY i.position ASC
                        ');
                        $stmtItems->execute([':bid' => $id]);
                        $allItems = $stmtItems->fetchAll();

                        $parentItemsByGroup = [];
                        $subitemsByParent = [];

                        foreach ($allItems as &$item) {
                            $item['column_values'] = !empty($item['column_values']) ? json_decode($item['column_values'], true) : new stdClass();
                            $item['update_count'] = (int)$item['update_count'];
                            $itemId = (int)$item['id'];
                            $gid = (int)$item['group_id'];

                            if ($item['parent_id'] === null || $item['parent_id'] === '0' || $item['parent_id'] === '') {
                                $item['subitems'] = [];
                                $parentItemsByGroup[$gid][] = $item;
                            } else {
                                $pid = (int)$item['parent_id'];
                                $subitemsByParent[$pid][] = $item;
                            }
                        }

                        foreach ($subitemsByParent as $pid => $subs) {
                            foreach ($parentItemsByGroup as $gid => &$pItems) {
                                foreach ($pItems as &$pItem) {
                                    if ((int)$pItem['id'] === $pid) {
                                        $pItem['subitems'] = $subs;
                                        break 2;
                                    }
                                }
                            }
                        }

                        $resultGroups = [];
                        foreach ($groups as $group) {
                            $gid = (int)$group['id'];
                            $group['items'] = isset($parentItemsByGroup[$gid]) ? array_values($parentItemsByGroup[$gid]) : [];
                            $resultGroups[] = $group;
                        }

                        return [
                            'success' => true,
                            'board' => $board,
                            'main_columns' => $mainCols,
                            'sub_columns' => $subCols,
                            'groups' => $resultGroups,
                            'server_time' => date('Y-m-d H:i:s')
                        ];
                    }
                }
            } catch (Throwable $e) {}
        }

        $data = DataPersistence::loadBoardJson();
        $cols = $data['columns'] ?? [];
        $mainCols = array_values(array_filter($cols, fn($c) => empty($c['is_subitem']) && strtolower($c['title'] ?? '') !== 'subtasks'));
        $subCols = array_values(array_filter($cols, fn($c) => !empty($c['is_subitem'])));

        return [
            'success' => true,
            'board' => $data['board'] ?? ['id' => 1, 'name' => 'Branch Planning 2026'],
            'main_columns' => $mainCols,
            'sub_columns' => $subCols,
            'groups' => $data['groups'] ?? [],
            'server_time' => date('Y-m-d H:i:s')
        ];
    }

    public function createColumn(int $boardId, array $data): array {
        $title = trim($data['title'] ?? 'New Column');
        $type = $data['type'] ?? 'text';
        $isSub = !empty($data['is_subitem']) ? 1 : 0;
        $colId = 'col_' . time() . '_' . rand(100, 999);

        $newCol = [
            'id' => $colId,
            'board_id' => $boardId,
            'title' => $title,
            'type' => $type,
            'is_subitem' => $isSub,
            'position' => 99.0,
            'settings' => new stdClass()
        ];

        DataPersistence::createColumnInJson($newCol);

        if ($this->pdo) {
            try {
                $stmt = $this->pdo->prepare('INSERT INTO board_columns (id, board_id, title, type, is_subitem, position, settings) VALUES (:id, :bid, :title, :type, :is_sub, :pos, '{}')');
                $stmt->execute([
                    ':id' => $colId,
                    ':bid' => $boardId,
                    ':title' => $title,
                    ':type' => $type,
                    ':is_sub' => $isSub,
                    ':pos' => 99.0
                ]);
            } catch (Throwable $e) {}
        }

        return [
            'success' => true,
            'column' => $newCol
        ];
    }

    public function deleteColumn(int $boardId, string $colId): array {
        DataPersistence::deleteColumnInJson($colId);

        if ($this->pdo) {
            try {
                $stmt = $this->pdo->prepare('DELETE FROM board_columns WHERE board_id = :bid AND id = :id');
                $stmt->execute([':bid' => $boardId, ':id' => $colId]);
            } catch (Throwable $e) {}
        }

        return ['success' => true, 'deleted_id' => $colId];
    }

    public function saveBoardState(int $boardId, array $data): array {
        DataPersistence::saveBoardJson($data);
        return ['success' => true, 'saved_at' => date('Y-m-d H:i:s')];
    }

    public function updateGroupTimeline($groupId, array $data): array {
        $field = $data['field'] ?? '';
        $value = $data['value'] ?? null;
        DataPersistence::updateGroupTimelineInJson($groupId, $field, $value);

        if ($this->pdo) {
            try {
                if (in_array($field, ['soft_opening', 'grand_opening', 'timeline_start', 'timeline_end'], true)) {
                    $stmt = $this->pdo->prepare('UPDATE board_groups SET ' . $field . ' = :val WHERE id = :id');
                    $stmt->execute([':val' => $value, ':id' => (int)$groupId]);
                }
            } catch (Throwable $e) {}
        }

        return ['success' => true, 'group_id' => $groupId, 'field' => $field, 'value' => $value];
    }
}
