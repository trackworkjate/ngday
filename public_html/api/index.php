<?php
declare(strict_types=1);

// Standard CORS & JSON API Headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/controllers/BoardController.php';
require_once __DIR__ . '/controllers/ItemController.php';
require_once __DIR__ . '/controllers/UpdateController.php';
require_once __DIR__ . '/controllers/ImportController.php';

// Parse Request Method & URI Path
$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Normalize path: strip script directory if present
$scriptDir = dirname($_SERVER['SCRIPT_NAME']);
if ($scriptDir !== '/' && str_starts_with($uri, $scriptDir)) {
    $uri = substr($uri, strlen($scriptDir));
}
$uri = '/' . trim($uri, '/');

// Parse JSON Body for POST/PATCH
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

try {
    $response = null;

    // Route: GET /boards or GET /
    if ($method === 'GET' && ($uri === '' || $uri === '/' || $uri === '/boards')) {
        $controller = new BoardController();
        $response = $controller->index();
    }
    // Route: GET /boards/{id}/full
    elseif ($method === 'GET' && preg_match('#^/boards/([^/]+)/full$#', $uri, $matches)) {
        $controller = new BoardController();
        $response = $controller->getFull((int)$matches[1]);
    }
    // Route: POST /boards/{id}/save (Full Sync State)
    elseif ($method === 'POST' && preg_match('#^/boards/([^/]+)/save$#', $uri, $matches)) {
        $controller = new BoardController();
        $response = $controller->saveBoardState((int)$matches[1], $input);
    }
    // Route: GET /boards/{id}/sync?since=...
    elseif ($method === 'GET' && preg_match('#^/boards/([^/]+)/sync$#', $uri, $matches)) {
        $since = $_GET['since'] ?? date('Y-m-d H:i:s', strtotime('-1 minute'));
        $controller = new BoardController();
        $response = $controller->sync((int)$matches[1], $since);
    }
    // Route: POST /boards/{id}/groups
    elseif ($method === 'POST' && preg_match('#^/boards/([^/]+)/groups$#', $uri, $matches)) {
        $controller = new BoardController();
        $response = $controller->createGroup((int)$matches[1], $input);
    }
    // Route: PATCH /groups/{id}/timeline
    elseif ($method === 'PATCH' && preg_match('#^/groups/([^/]+)/timeline$#', $uri, $matches)) {
        $controller = new BoardController();
        $response = $controller->updateGroupTimeline($matches[1], $input);
    }
    // Route: POST /boards/{id}/columns
    elseif ($method === 'POST' && preg_match('#^/boards/([^/]+)/columns$#', $uri, $matches)) {
        $controller = new BoardController();
        $response = $controller->createColumn((int)$matches[1], $input);
    }
    // Route: DELETE /boards/{id}/columns/{colId}
    elseif ($method === 'DELETE' && preg_match('#^/boards/([^/]+)/columns/([^/]+)$#', $uri, $matches)) {
        $controller = new BoardController();
        $response = $controller->deleteColumn((int)$matches[1], $matches[2]);
    }
    // Route: POST /items (Create Item / Subitem)
    elseif ($method === 'POST' && $uri === '/items') {
        $controller = new ItemController();
        $response = $controller->create($input);
    }
    // Route: PATCH /items/{id}/cell (Dynamic JSON cell update)
    elseif ($method === 'PATCH' && preg_match('#^/items/([^/]+)/cell$#', $uri, $matches)) {
        $controller = new ItemController();
        $response = $controller->updateCell($matches[1], $input);
    }
    // Route: PATCH /items/{id}/name (Rename item)
    elseif ($method === 'PATCH' && preg_match('#^/items/([^/]+)/name$#', $uri, $matches)) {
        $controller = new ItemController();
        $response = $controller->updateName($matches[1], $input['name'] ?? '');
    }
    // Route: DELETE /items/{id}
    elseif ($method === 'DELETE' && preg_match('#^/items/([^/]+)$#', $uri, $matches)) {
        $controller = new ItemController();
        $response = $controller->delete($matches[1]);
    }
    // Route: GET /items/{id}/updates
    elseif ($method === 'GET' && preg_match('#^/items/([^/]+)/updates$#', $uri, $matches)) {
        $controller = new UpdateController();
        $response = $controller->getUpdates((int)$matches[1]);
    }
    // Route: POST /items/{id}/updates
    elseif ($method === 'POST' && preg_match('#^/items/([^/]+)/updates$#', $uri, $matches)) {
        $controller = new UpdateController();
        $response = $controller->createUpdate((int)$matches[1], $input);
    }
    // Route: POST /import/monday-excel
    elseif ($method === 'POST' && ($uri === '/import/monday-excel' || $uri === '/import')) {
        $controller = new ImportController();
        $response = $controller->importExcel();
    }
    else {
        http_response_code(404);
        $response = [
            'success' => false,
            'error' => "Endpoint not found: {$method} {$uri}"
        ];
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
}
