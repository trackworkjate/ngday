<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class DataPersistence {
    private static string $jsonFile = __DIR__ . '/../../data/board_data.json';

    public static function getJsonFilePath(): string {
        return self::$jsonFile;
    }

    public static function loadBoardJson(): array {
        if (!file_exists(self::$jsonFile)) {
            return ['board' => ['id' => 1, 'name' => 'Branch Planning 2026'], 'columns' => [], 'groups' => []];
        }
        $raw = file_get_contents(self::$jsonFile);
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : ['board' => ['id' => 1, 'name' => 'Branch Planning 2026'], 'columns' => [], 'groups' => []];
    }

    public static function saveBoardJson(array $data): bool {
        $dir = dirname(self::$jsonFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $res = file_put_contents(self::$jsonFile, $encoded, LOCK_EX);
        if ($res !== false) {
            @chmod(self::$jsonFile, 0666);
            return true;
        }
        return false;
    }

    public static function updateCellInJson(string|int $itemId, string $colId, $value): bool {
        $data = self::loadBoardJson();
        $strItemId = (string)$itemId;
        $updated = false;

        foreach ($data['groups'] as &$group) {
            foreach ($group['items'] as &$item) {
                if ((string)$item['id'] === $strItemId || (string)($item['monday_item_id'] ?? '') === $strItemId) {
                    if (!isset($item['column_values']) || !is_array($item['column_values'])) {
                        $item['column_values'] = [];
                    }
                    if ($value === null || $value === '') {
                        unset($item['column_values'][$colId]);
                    } else {
                        $item['column_values'][$colId] = $value;
                    }
                    $updated = true;
                    break 2;
                }

                if (!empty($item['subitems']) && is_array($item['subitems'])) {
                    foreach ($item['subitems'] as &$sub) {
                        if ((string)$sub['id'] === $strItemId || (string)($sub['monday_item_id'] ?? '') === $strItemId) {
                            if (!isset($sub['column_values']) || !is_array($sub['column_values'])) {
                                $sub['column_values'] = [];
                            }
                            if ($value === null || $value === '') {
                                unset($sub['column_values'][$colId]);
                            } else {
                                $sub['column_values'][$colId] = $value;
                            }
                            $updated = true;
                            break 3;
                        }
                    }
                }
            }
        }

        if ($updated) {
            return self::saveBoardJson($data);
        }
        return false;
    }

    public static function updateItemNameInJson(string|int $itemId, string $name): bool {
        $data = self::loadBoardJson();
        $strItemId = (string)$itemId;
        $updated = false;

        foreach ($data['groups'] as &$group) {
            foreach ($group['items'] as &$item) {
                if ((string)$item['id'] === $strItemId || (string)($item['monday_item_id'] ?? '') === $strItemId) {
                    $item['name'] = $name;
                    $updated = true;
                    break 2;
                }
                if (!empty($item['subitems']) && is_array($item['subitems'])) {
                    foreach ($item['subitems'] as &$sub) {
                        if ((string)$sub['id'] === $strItemId || (string)($sub['monday_item_id'] ?? '') === $strItemId) {
                            $sub['name'] = $name;
                            $updated = true;
                            break 3;
                        }
                    }
                }
            }
        }

        if ($updated) {
            return self::saveBoardJson($data);
        }
        return false;
    }

    public static function createColumnInJson(array $column): bool {
        $data = self::loadBoardJson();
        if (!isset($data['columns'])) {
            $data['columns'] = [];
        }
        $data['columns'][] = $column;
        return self::saveBoardJson($data);
    }

    public static function deleteColumnInJson(string $columnId): bool {
        $data = self::loadBoardJson();
        if (isset($data['columns']) && is_array($data['columns'])) {
            $data['columns'] = array_values(array_filter($data['columns'], function($col) use ($columnId) {
                return (string)($col['id'] ?? '') !== (string)$columnId;
            }));
        }

        foreach ($data['groups'] as &$group) {
            foreach ($group['items'] as &$item) {
                if (isset($item['column_values'][$columnId])) {
                    unset($item['column_values'][$columnId]);
                }
                if (!empty($item['subitems']) && is_array($item['subitems'])) {
                    foreach ($item['subitems'] as &$sub) {
                        if (isset($sub['column_values'][$columnId])) {
                            unset($sub['column_values'][$columnId]);
                        }
                    }
                }
            }
        }

        return self::saveBoardJson($data);
    }

    public static function updateGroupTimelineInJson(string|int $groupId, string $field, ?string $dateVal): bool {
        $data = self::loadBoardJson();
        $strGroupId = (string)$groupId;
        $updated = false;

        foreach ($data['groups'] as &$group) {
            if ((string)$group['id'] === $strGroupId) {
                $group[$field] = $dateVal;
                $colId = $field === 'soft_opening' ? 'col_5' : ($field === 'grand_opening' ? 'col_6' : null);
                if ($colId) {
                    foreach ($group['items'] as &$item) {
                        if (!isset($item['column_values'])) $item['column_values'] = [];
                        $item['column_values'][$colId] = $dateVal;
                    }
                }
                $updated = true;
                break;
            }
        }

        if ($updated) {
            return self::saveBoardJson($data);
        }
        return false;
    }

    public static function deleteItemsInJson(array $itemIds): bool {
        $data = self::loadBoardJson();
        $idMap = array_flip(array_map('strval', $itemIds));

        foreach ($data['groups'] as &$group) {
            $newItems = [];
            foreach ($group['items'] as $item) {
                $itemIdStr = (string)$item['id'];
                if (isset($idMap[$itemIdStr])) {
                    continue;
                }
                if (!empty($item['subitems']) && is_array($item['subitems'])) {
                    $item['subitems'] = array_values(array_filter($item['subitems'], function($sub) use ($idMap) {
                        return !isset($idMap[(string)$sub['id']]);
                    }));
                }
                $newItems[] = $item;
            }
            $group['items'] = $newItems;
        }

        return self::saveBoardJson($data);
    }
}
