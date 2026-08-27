<?php
declare(strict_types=1);

// Direct Zero-Dependency API Endpoint (Works on 100% of Web Servers without URL Rewrite)
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/services/DataPersistence.php';
require_once __DIR__ . '/controllers/BoardController.php';
require_once __DIR__ . '/controllers/ItemController.php';

// Parse Request Body & Parameters
$input = [];
$rawInput = file_get_contents('php://input');
if (!empty($rawInput)) {
    $decoded = json_decode($rawInput, true);
    if (is_array($decoded)) {
        $input = $decoded;
    }
}
if (!empty($_POST)) {
    $input = array_merge($input, $_POST);
}
if (!empty($_GET)) {
    $input = array_merge($input, $_GET);
}

$action = $input['action'] ?? $_GET['action'] ?? '';

try {
    $response = ['success' => false, 'error' => 'Invalid action'];

    switch ($action) {
        case 'update_cell':
            $itemId = $input['item_id'] ?? '';
            $colId = $input['column_id'] ?? '';
            $value = $input['value'] ?? null;
            $ctrl = new ItemController();
            $response = $ctrl->updateCell($itemId, ['column_id' => $colId, 'value' => $value]);
            break;

        case 'update_name':
            $itemId = $input['item_id'] ?? '';
            $name = $input['name'] ?? '';
            $ctrl = new ItemController();
            $response = $ctrl->updateName($itemId, $name);
            break;

        case 'delete_item':
            $itemId = $input['item_id'] ?? '';
            $ctrl = new ItemController();
            $response = $ctrl->delete($itemId);
            break;

        case 'delete_column':
            $colId = $input['column_id'] ?? '';
            $boardId = (int)($input['board_id'] ?? 1);
            $ctrl = new BoardController();
            $response = $ctrl->deleteColumn($boardId, (string)$colId);
            break;

        case 'create_column':
            $boardId = (int)($input['board_id'] ?? 1);
            $ctrl = new BoardController();
            $response = $ctrl->createColumn($boardId, $input);
            break;

        case 'update_group_timeline':
            $groupId = $input['group_id'] ?? '';
            $field = $input['field'] ?? '';
            $value = $input['value'] ?? null;
            $ctrl = new BoardController();
            $response = $ctrl->updateGroupTimeline($groupId, ['field' => $field, 'value' => $value]);
            break;

        case 'save_board':
            $boardData = $input['board_data'] ?? $input;
            if (isset($boardData['action'])) unset($boardData['action']);
            $saved = DataPersistence::saveBoardJson($boardData);
            $response = ['success' => $saved, 'saved_at' => date('Y-m-d H:i:s')];
            break;

        case 'get_board':
        default:
            $boardId = (int)($input['board_id'] ?? 1);
            $ctrl = new BoardController();
            $response = $ctrl->getFull($boardId);
            break;
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
}
