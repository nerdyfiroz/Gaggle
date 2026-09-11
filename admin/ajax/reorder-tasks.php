<?php
/**
 * AJAX: Reorder Tasks
 */
require_once dirname(__DIR__, 2) . '/app/middleware/AuthMiddleware.php';
require_admin_auth();

require_once APP_PATH . '/models/Task.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$order = $input['order'] ?? [];

if (empty($order) || !is_array($order)) {
    json_response(['success' => false, 'message' => 'Invalid order data.'], 400);
}

$taskModel = new Task();
$taskModel->reorder($order);

json_response(['success' => true, 'message' => 'Tasks reordered.']);
