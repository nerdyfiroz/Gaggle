<?php
/**
 * AJAX: Bulk Action
 */
require_once dirname(__DIR__, 2) . '/app/middleware/AuthMiddleware.php';
require_admin_auth();

require_once APP_PATH . '/models/Application.php';
require_once APP_PATH . '/models/Blacklist.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$ids = $input['ids'] ?? [];
$action = sanitize($input['action'] ?? '');

if (empty($ids) || !is_array($ids) || empty($action)) {
    json_response(['success' => false, 'message' => 'Invalid parameters.'], 400);
}

$appModel = new Application();
$log = new AdminLog();
$count = 0;

switch ($action) {
    case 'approve':
        $count = $appModel->bulkUpdateStatus($ids, 'approved');
        $log->log('bulk_approve', 'applications', null, "{$count} applications approved");
        break;
    case 'reject':
        $count = $appModel->bulkUpdateStatus($ids, 'rejected');
        $log->log('bulk_reject', 'applications', null, "{$count} applications rejected");
        break;
    case 'blacklist':
        $blacklist = new Blacklist();
        $applications = $appModel->findByIds($ids);
        foreach ($applications as $application) {
            $blacklist->add('wallet', $application['wallet_address'], 'Bulk blacklisted by admin');
            if (!empty($application['ip_address'])) $blacklist->add('ip', $application['ip_address'], 'Bulk blacklisted by admin');
            if (!empty($application['twitter_username'])) $blacklist->add('twitter', $application['twitter_username'], 'Bulk blacklisted by admin');
            if (!empty($application['discord_username'])) $blacklist->add('discord', $application['discord_username'], 'Bulk blacklisted by admin');
        }
        $count = $appModel->bulkUpdateStatus($ids, 'blacklisted');
        $log->log('bulk_blacklist', 'applications', null, "{$count} applications blacklisted");
        break;
    case 'delete':
        $count = $appModel->bulkDelete($ids);
        $log->log('bulk_delete', 'applications', null, "{$count} applications deleted");
        break;
    default:
        json_response(['success' => false, 'message' => 'Unknown action.'], 400);
}

json_response(['success' => true, 'message' => "{$count} application(s) {$action}ed.", 'count' => $count]);
