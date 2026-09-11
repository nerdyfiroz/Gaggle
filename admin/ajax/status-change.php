<?php
/**
 * AJAX: Status Change
 */
require_once dirname(__DIR__, 2) . '/app/middleware/AuthMiddleware.php';
require_admin_auth();

require_once APP_PATH . '/models/Application.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$id = (int) ($input['id'] ?? 0);
$status = sanitize($input['status'] ?? '');

if (!$id || !$status) {
    json_response(['success' => false, 'message' => 'Invalid parameters.'], 400);
}

$appModel = new Application();
$app = $appModel->findById($id);

if (!$app) {
    json_response(['success' => false, 'message' => 'Application not found.'], 404);
}

if ($appModel->updateStatus($id, $status)) {
    $log = new AdminLog();
    $log->log('status_change', 'application', $app['application_id'], "Changed to: {$status}");
    json_response(['success' => true, 'message' => "Application {$status}."]);
} else {
    json_response(['success' => false, 'message' => 'Failed to update status.'], 500);
}
