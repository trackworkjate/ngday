<?php
declare(strict_types=1);

require_once __DIR__ . '/../services/MondayExcelImporter.php';

class ImportController {
    public function importExcel(): array {
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            return [
                'success' => false,
                'error' => 'No file uploaded or upload error occurred. Error Code: ' . ($_FILES['file']['error'] ?? 'NONE')
            ];
        }

        $file = $_FILES['file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if ($ext !== 'xlsx') {
            http_response_code(400);
            return ['success' => false, 'error' => 'Only .xlsx Excel files exported from Monday.com are supported.'];
        }

        // Custom Board Name if provided
        $customName = !empty($_POST['board_name']) ? trim($_POST['board_name']) : null;

        try {
            $importer = new MondayExcelImporter();
            $result = $importer->import($file['tmp_name'], $customName);
            return $result;
        } catch (Exception $e) {
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Import failed: ' . $e->getMessage()
            ];
        }
    }
}
