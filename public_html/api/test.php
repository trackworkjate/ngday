<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', '1');

$result = [
    'php_version' => PHP_VERSION,
    'session_status' => session_status(),
    'checks' => []
];

// Check 1: Session start
try {
    if (session_status() === PHP_SESSION_NONE) {
        $result['checks']['session_start'] = @session_start() ? 'SUCCESS' : 'FAILED';
    } else {
        $result['checks']['session_start'] = 'ALREADY_ACTIVE';
    }
} catch (Throwable $e) {
    $result['checks']['session_start'] = 'ERROR: ' . $e->getMessage();
}

// Check 2: auth_config.php
try {
    $cfgPath = __DIR__ . '/config/auth_config.php';
    if (file_exists($cfgPath)) {
        $cfg = include $cfgPath;
        $result['checks']['auth_config'] = 'EXISTS: ' . json_encode($cfg);
    } else {
        $result['checks']['auth_config'] = 'FILE_NOT_FOUND: ' . $cfgPath;
    }
} catch (Throwable $e) {
    $result['checks']['auth_config'] = 'ERROR: ' . $e->getMessage();
}

// Check 3: database.php
try {
    require_once __DIR__ . '/config/database.php';
    $result['checks']['database_file'] = 'LOADED';
    $pdo = Database::getConnection();
    $result['checks']['database_connection'] = 'CONNECTED_SUCCESS';
} catch (Throwable $e) {
    $result['checks']['database_connection'] = 'ERROR: ' . $e->getMessage();
}

// Check 4: AuthController.php
try {
    require_once __DIR__ . '/controllers/AuthController.php';
    $result['checks']['auth_controller_file'] = 'LOADED';
    $auth = new AuthController();
    $mock = $auth->mockLogin('admin');
    $result['checks']['mock_login_admin'] = 'SUCCESS: ' . json_encode($mock, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    $result['checks']['auth_controller'] = 'ERROR: ' . $e->getMessage();
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
