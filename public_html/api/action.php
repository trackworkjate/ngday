<?php
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(200);
        echo json_encode(['success' => false, 'fatal_error' => $error], JSON_UNESCAPED_UNICODE);
    }
});

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$errors = [];

try {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
} catch (Throwable $t) {
    $errors[] = "session: " . $t->getMessage();
}

try {
    require_once __DIR__ . '/services/DataPersistence.php';
} catch (Throwable $t) {
    $errors[] = "DataPersistence.php (line " . $t->getLine() . "): " . $t->getMessage();
}

try {
    require_once __DIR__ . '/controllers/BoardController.php';
} catch (Throwable $t) {
    $errors[] = "BoardController.php (line " . $t->getLine() . "): " . $t->getMessage();
}

try {
    require_once __DIR__ . '/controllers/ItemController.php';
} catch (Throwable $t) {
    $errors[] = "ItemController.php (line " . $t->getLine() . "): " . $t->getMessage();
}

try {
    require_once __DIR__ . '/controllers/AuthController.php';
} catch (Throwable $t) {
    $errors[] = "AuthController.php (line " . $t->getLine() . "): " . $t->getMessage();
}

if (!empty($errors)) {
    http_response_code(200);
    echo json_encode([
        'success' => false,
        'error' => implode(' | ', $errors)
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

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

// RBAC Role-Based Permissions Guard
$currentUser = $_SESSION['user'] ?? null;
$userRole = $currentUser['role'] ?? null;

// Viewer is strictly read-only for board modifications
$modifyingActions = ['update_cell', 'update_name', 'delete_item', 'delete_column', 'create_column', 'update_group_timeline', 'save_board'];
if ($userRole === 'viewer' && in_array($action, $modifyingActions, true)) {
    echo json_encode([
        'success' => false,
        'error' => 'สิทธิ์ผู้เข้าชม (Viewer) สามารถดูข้อมูลได้อย่างเดียว ไม่สามารถแก้ไขหรือลบข้อมูลได้'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Member cannot delete or add structure columns
if ($userRole === 'member' && in_array($action, ['delete_column', 'create_column'], true)) {
    echo json_encode([
        'success' => false,
        'error' => 'สิทธิ์พนักงาน (Member) ไม่สามารถเพิ่มหรือลบคอลัมน์ได้ กรุณาติดต่อ Manager หรือ Admin'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $response = ['success' => false, 'error' => 'Invalid action'];

    switch ($action) {
        // --- AUTHENTICATION & USER MANAGEMENT ACTIONS ---
        case 'get_current_user':
            $auth = new AuthController();
            $response = $auth->getCurrentUser();
            break;

        case 'google_login':
            $credential = (string)($input['credential'] ?? '');
            $auth = new AuthController();
            $response = $auth->googleLogin($credential);
            break;

        case 'mock_login':
            $role = (string)($input['role'] ?? 'member');
            $name = isset($input['name']) ? (string)$input['name'] : null;
            $email = isset($input['email']) ? (string)$input['email'] : null;
            $auth = new AuthController();
            $response = $auth->mockLogin($role, $name, $email);
            break;

        case 'logout':
            $auth = new AuthController();
            $response = $auth->logout();
            break;

        case 'list_users':
            $auth = new AuthController();
            $response = $auth->listUsers();
            break;

        case 'update_user_role':
            $userId = (int)($input['user_id'] ?? 0);
            $newRole = (string)($input['role'] ?? '');
            $isActive = isset($input['is_active']) ? (int)$input['is_active'] : null;
            $auth = new AuthController();
            $response = $auth->updateUserRole($userId, $newRole, $isActive);
            break;

        case 'save_auth_config':
            $config = (array)($input['config'] ?? $input);
            $auth = new AuthController();
            $response = $auth->saveAuthConfig($config);
            break;

        // --- BOARD & ITEM ACTIONS ---
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

        case 'get_updates':
            require_once __DIR__ . '/controllers/UpdateController.php';
            $itemId = (int)($input['item_id'] ?? 0);
            $ctrl = new UpdateController();
            $response = $ctrl->getUpdates($itemId);
            break;

        case 'add_update':
            require_once __DIR__ . '/controllers/UpdateController.php';
            $itemId = (int)($input['item_id'] ?? 0);
            $ctrl = new UpdateController();
            $response = $ctrl->createUpdate($itemId, $input);
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
    http_response_code(200);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage() . ' in ' . basename($e->getFile()) . ' on line ' . $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
}
