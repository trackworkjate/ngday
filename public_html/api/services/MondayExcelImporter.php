<?php
declare(strict_types=1);

require_once __DIR__ . '/SimpleXLSX.php';
require_once __DIR__ . '/../config/database.php';

class MondayExcelImporter {
    private PDO $pdo;

    public function __construct(?PDO $pdo = null) {
        $this->pdo = $pdo ?: Database::getConnection();
    }

    /**
     * Parse and import Monday.com Excel export into MySQL database
     */
    public function import(string $filePath, ?string $customBoardName = null): array {
        $xlsx = SimpleXLSX::parse($filePath);
        if (!$xlsx) {
            throw new Exception("Unable to parse Excel file. Please ensure it is a valid .xlsx file.");
        }

        $sheetNames = $xlsx->getSheetNames();
        if (empty($sheetNames)) {
            throw new Exception("Excel file contains no readable sheets.");
        }

        $mainSheet = $sheetNames[0];
        $rows = $xlsx->rows($mainSheet);
        if (empty($rows)) {
            throw new Exception("Main data sheet is empty.");
        }

        // 1. Board Name & Description
        $boardName = $customBoardName ?: ($rows[1][0] ?? 'Monday Imported Board');
        $boardDesc = $rows[2][0] ?? '';

        $this->pdo->beginTransaction();

        try {
            // Create or update Board
            $stmt = $this->pdo->prepare("INSERT INTO boards (name, description) VALUES (:name, :desc)");
            $stmt->execute([':name' => $boardName, ':desc' => $boardDesc]);
            $boardId = (int)$this->pdo->lastInsertId();

            $mainColMap = []; // title -> col_id
            $subColMap = [];  // title -> col_id
            $allMainCols = [];
            $allSubCols = [];

            $mainHeaders = [];
            $subHeaders = [];

            $currentGroupId = null;
            $currentParentItemId = null;
            $groupPosition = 1.0;
            $itemPosition = 1.0;
            $subitemPosition = 1.0;

            $groupCount = 0;
            $mainItemCount = 0;
            $subitemCount = 0;

            $itemMapping = []; // monday_item_id -> database_item_id
            $colors = ['#579BFC', '#00C875', '#E2445C', '#A25DDC', '#FDAB3D', '#FF642E', '#0086C0', '#784BD1'];

            $totalRows = count($rows);
            for ($r = 1; $r <= $totalRows; $r++) {
                if ($r <= 3) continue;

                $row = $rows[$r] ?? [];
                $c1 = $row[0] ?? null;
                $c2 = $row[1] ?? null;

                // Detect Group Header
                if ($c1 !== null && $c2 === null) {
                    $nextRow = $rows[$r + 1] ?? [];
                    if (($nextRow[0] ?? '') === 'Name') {
                        $groupCount++;
                        $groupTitle = trim((string)$c1);
                        $color = $colors[($groupCount - 1) % count($colors)];

                        $stmtGroup = $this->pdo->prepare("INSERT INTO board_groups (board_id, title, color, position) VALUES (:bid, :title, :color, :pos)");
                        $stmtGroup->execute([
                            ':bid' => $boardId,
                            ':title' => $groupTitle,
                            ':color' => $color,
                            ':pos' => $groupPosition++
                        ]);
                        $currentGroupId = (int)$this->pdo->lastInsertId();
                        $currentParentItemId = null;
                        $itemPosition = 1.0;
                        continue;
                    }
                }

                // Detect Main Column Headers
                if ($c1 === 'Name') {
                    $mainHeaders = [];
                    foreach ($row as $colIdx => $title) {
                        if ($title !== null && trim((string)$title) !== '') {
                            $t = trim((string)$title);
                            $mainHeaders[$colIdx] = $t;
                            if ($t !== 'Name' && !isset($mainColMap[$t])) {
                                $colId = 'col_' . (count($mainColMap) + 1);
                                $colType = $this->detectColumnType($t);
                                $mainColMap[$t] = $colId;

                                $stmtCol = $this->pdo->prepare("INSERT INTO board_columns (id, board_id, title, type, is_subitem, position) VALUES (:id, :bid, :title, :type, 0, :pos)");
                                $stmtCol->execute([
                                    ':id' => $colId,
                                    ':bid' => $boardId,
                                    ':title' => $t,
                                    ':type' => $colType,
                                    ':pos' => (float)(count($allMainCols) + 1)
                                ]);
                                $allMainCols[$t] = $colId;
                            }
                        }
                    }
                    continue;
                }

                // Detect Subitem Column Headers
                if ($c1 === 'Subitems') {
                    $subHeaders = [];
                    foreach ($row as $colIdx => $title) {
                        if ($title !== null && trim((string)$title) !== '') {
                            $t = trim((string)$title);
                            $subHeaders[$colIdx] = $t;
                            if ($t !== 'Subitems' && $t !== 'Name' && !isset($subColMap[$t])) {
                                $colId = 'sub_col_' . (count($subColMap) + 1);
                                $colType = $this->detectColumnType($t);
                                $subColMap[$t] = $colId;

                                $stmtCol = $this->pdo->prepare("INSERT INTO board_columns (id, board_id, title, type, is_subitem, position) VALUES (:id, :bid, :title, :type, 1, :pos)");
                                $stmtCol->execute([
                                    ':id' => $colId,
                                    ':bid' => $boardId,
                                    ':title' => $t,
                                    ':type' => $colType,
                                    ':pos' => (float)(count($allSubCols) + 1)
                                ]);
                                $allSubCols[$t] = $colId;
                            }
                        }
                    }
                    continue;
                }

                // Main Item Row
                if ($c1 !== null && $c1 !== $boardName && $c1 !== 'Name' && $c1 !== 'Subitems' && !str_starts_with((string)$c1, 'Track')) {
                    if (!$currentGroupId) {
                        // Fallback group if none exists
                        $groupCount++;
                        $stmtGroup = $this->pdo->prepare("INSERT INTO board_groups (board_id, title, color, position) VALUES (:bid, 'General Tasks', '#579BFC', 1.0)");
                        $stmtGroup->execute([':bid' => $boardId]);
                        $currentGroupId = (int)$this->pdo->lastInsertId();
                    }

                    $mainItemCount++;
                    $itemName = trim((string)$c1);
                    $colValues = [];
                    $mondayItemId = null;

                    foreach ($mainHeaders as $colIdx => $colTitle) {
                        $val = $row[$colIdx] ?? null;
                        if (str_contains($colTitle, 'Item ID') && $val) {
                            $mondayItemId = (string)$val;
                        } elseif (isset($mainColMap[$colTitle]) && $val !== null && trim((string)$val) !== '') {
                            $colValues[$mainColMap[$colTitle]] = trim((string)$val);
                        }
                    }

                    $stmtItem = $this->pdo->prepare("INSERT INTO items (monday_item_id, board_id, group_id, parent_id, name, column_values, position) VALUES (:mid, :bid, :gid, NULL, :name, :vals, :pos)");
                    $stmtItem->execute([
                        ':mid' => $mondayItemId,
                        ':bid' => $boardId,
                        ':gid' => $currentGroupId,
                        ':name' => $itemName,
                        ':vals' => json_encode($colValues, JSON_UNESCAPED_UNICODE),
                        ':pos' => $itemPosition++
                    ]);
                    $currentParentItemId = (int)$this->pdo->lastInsertId();
                    if ($mondayItemId) {
                        $itemMapping[$mondayItemId] = $currentParentItemId;
                    }
                    $subitemPosition = 1.0;
                    continue;
                }

                // Subitem Row
                if ($c1 === null && $c2 !== null && $currentParentItemId !== null) {
                    $subitemCount++;
                    $subitemName = trim((string)$c2);
                    $subColValues = [];
                    $mondaySubItemId = null;

                    foreach ($subHeaders as $colIdx => $colTitle) {
                        $val = $row[$colIdx] ?? null;
                        if (str_contains($colTitle, 'Item ID') && $val) {
                            $mondaySubItemId = (string)$val;
                        } elseif (isset($subColMap[$colTitle]) && $val !== null && trim((string)$val) !== '') {
                            $subColValues[$subColMap[$colTitle]] = trim((string)$val);
                        }
                    }

                    $stmtSub = $this->pdo->prepare("INSERT INTO items (monday_item_id, board_id, group_id, parent_id, name, column_values, position) VALUES (:mid, :bid, :gid, :pid, :name, :vals, :pos)");
                    $stmtSub->execute([
                        ':mid' => $mondaySubItemId,
                        ':bid' => $boardId,
                        ':gid' => $currentGroupId,
                        ':pid' => $currentParentItemId,
                        ':name' => $subitemName,
                        ':vals' => json_encode($subColValues, JSON_UNESCAPED_UNICODE),
                        ':pos' => $subitemPosition++
                    ]);
                    $dbSubId = (int)$this->pdo->lastInsertId();
                    if ($mondaySubItemId) {
                        $itemMapping[$mondaySubItemId] = $dbSubId;
                    }
                }
            }

            // 2. Import Updates Sheet if present
            $updateCount = 0;
            if (in_array('updates', $sheetNames)) {
                $updateRows = $xlsx->rows('updates');
                $totalUpdateRows = count($updateRows);

                $stmtUpdate = $this->pdo->prepare("INSERT INTO item_updates (item_id, monday_post_id, user_name, content, likes_count) VALUES (:item_id, :post_id, :user, :content, :likes)");

                for ($ur = 3; $ur <= $totalUpdateRows; $ur++) {
                    $uRow = $updateRows[$ur] ?? [];
                    $mItemId = isset($uRow[0]) ? trim((string)$uRow[0]) : null;
                    if (!$mItemId || !isset($itemMapping[$mItemId])) {
                        continue;
                    }

                    $targetDbId = $itemMapping[$mItemId];
                    $user = $uRow[4] ?? 'Unknown User';
                    $content = $uRow[6] ?? '';
                    $likes = isset($uRow[7]) ? (int)$uRow[7] : 0;
                    $postId = isset($uRow[9]) ? (string)$uRow[9] : null;

                    $stmtUpdate->execute([
                        ':item_id' => $targetDbId,
                        ':post_id' => $postId,
                        ':user' => (string)$user,
                        ':content' => (string)$content,
                        ':likes' => $likes
                    ]);
                    $updateCount++;
                }
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'board_id' => $boardId,
                'board_name' => $boardName,
                'total_groups' => $groupCount,
                'total_main_items' => $mainItemCount,
                'total_subitems' => $subitemCount,
                'total_updates' => $updateCount,
                'total_columns' => count($allMainCols) + count($allSubCols)
            ];

        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function detectColumnType(string $title): string {
        $t = mb_strtolower(trim($title), 'UTF-8');
        if (str_contains($t, 'status') || str_contains($t, 'priority') || str_contains($t, 'state')) {
            return 'status';
        }
        if (str_contains($t, 'date') || str_contains($t, 'timeline') || str_contains($t, 'opening') || str_contains($t, 'start') || str_contains($t, 'end') || str_contains($t, 'due') || str_contains($t, 'updated')) {
            return 'date';
        }
        if (str_contains($t, 'progress') || str_contains($t, '%') || str_contains($t, 'overall')) {
            return 'progress';
        }
        if (str_contains($t, 'duration') || str_contains($t, 'formula') || str_contains($t, 'number') || str_contains($t, 'no')) {
            return 'number';
        }
        if (str_contains($t, 'owner') || str_contains($t, 'people') || str_contains($t, 'assing') || str_contains($t, 'assign') || str_contains($t, 'contacts') || str_contains($t, 'user')) {
            return 'people';
        }
        return 'text';
    }
}
