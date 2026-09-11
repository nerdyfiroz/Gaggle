<?php
/**
 * AJAX: Toggle Task
 */
require_once dirname(__DIR__, 2) . '/app/middleware/AuthMiddleware.php';
require_admin_auth();

require_once APP_PATH . '/models/Task.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$id = (int) ($input['id'] ?? 0);

if (!$id) {
    json_response(['success' => false, 'message' => 'Invalid task ID.'], 400);
}

$taskModel = new Task();
$taskModel->toggle($id);

json_response(['success' => true, 'message' => 'Task toggled.']);
