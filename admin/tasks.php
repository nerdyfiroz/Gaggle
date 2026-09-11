<?php
/**
 * Gaggle NFT — Admin Task Management
 */
require_once dirname(__DIR__) . '/app/middleware/AuthMiddleware.php';
require_admin_auth();

require_once APP_PATH . '/models/Task.php';

$taskModel = new Task();
$log = new AdminLog();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'create':
        case 'update':
            $data = [
                'title'       => sanitize($_POST['title'] ?? ''),
                'description' => sanitize($_POST['description'] ?? ''),
                'type'        => sanitize($_POST['type'] ?? 'custom'),
                'url'         => sanitize($_POST['url'] ?? ''),
                'required'    => isset($_POST['required']) ? 1 : 0,
                'enabled'     => isset($_POST['enabled']) ? 1 : 0,
            ];

            if (empty($data['title'])) {
                flash('error', 'Task title is required.');
                break;
            }

            if ($action === 'create') {
                $id = $taskModel->create($data);
                $log->log('create_task', 'task', (string) $id, $data['title']);
                flash('success', 'Task created.');
            } else {
                $id = (int) ($_POST['task_id'] ?? 0);
                $taskModel->update($id, $data);
                $log->log('update_task', 'task', (string) $id, $data['title']);
                flash('success', 'Task updated.');
            }
            break;

        case 'delete':
            $id = (int) ($_POST['task_id'] ?? 0);
            $task = $taskModel->findById($id);
            if ($task) {
                $taskModel->delete($id);
                $log->log('delete_task', 'task', (string) $id, $task['title']);
                flash('success', 'Task deleted.');
            }
            break;
    }

    redirect('/admin/tasks.php');
}

$tasks = $taskModel->getAll();
$taskTypes = [
    'twitter_follow'  => 'Twitter/X Follow',
    'twitter_like'    => 'Twitter/X Like',
    'twitter_repost'  => 'Twitter/X Repost',
    'discord_join'    => 'Discord Join',
    'telegram_join'   => 'Telegram Join',
    'website_visit'   => 'Website Visit',
    'custom'          => 'Custom Task',
];

$pageTitle = 'Tasks';
$extraJs = ['https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js'];
ob_start();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <p style="color: var(--admin-text-muted);"><?= count($tasks) ?> tasks configured. Drag to reorder.</p>
    <button class="btn-admin btn-admin-primary" data-modal="task-modal" onclick="openTaskModal()">
        <i class="bi bi-plus-lg"></i> New Task
    </button>
</div>

<!-- Task List -->
<div class="admin-card">
    <div class="admin-card-body" style="padding: 0;">
        <table class="admin-table" id="task-sortable-list">
            <thead>
                <tr>
                    <th style="width: 40px;"></th>
                    <th>Title</th>
                    <th>Type</th>
                    <th>Required</th>
                    <th>Enabled</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tasks as $task): ?>
                <tr data-task-id="<?= (int)$task['id'] ?>">
                    <td><span class="task-handle">⠿</span></td>
                    <td>
                        <strong><?= e($task['title']) ?></strong>
                        <?php if ($task['url']): ?>
                            <br><small style="color: var(--admin-text-muted);"><?= e($task['url']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge badge-info"><?= e($taskTypes[$task['type']] ?? $task['type']) ?></span></td>
                    <td><?= $task['required'] ? '<i class="bi bi-check-circle" style="color: var(--admin-primary);"></i>' : '<i class="bi bi-dash" style="color: var(--admin-text-muted);"></i>' ?></td>
                    <td>
                        <label class="toggle-switch">
                            <input type="checkbox" class="task-toggle" data-task-id="<?= (int)$task['id'] ?>" <?= $task['enabled'] ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </td>
                    <td>
                        <button class="btn-admin btn-admin-secondary btn-admin-sm" onclick='editTask(<?= json_encode($task) ?>)'>
                            <i class="bi bi-pencil"></i>
                        </button>
                        <form method="POST" style="display: inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="task_id" value="<?= (int)$task['id'] ?>">
                            <button class="btn-admin btn-admin-danger btn-admin-sm btn-confirm-delete"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Task Modal -->
<div class="modal-overlay" id="task-modal">
    <div class="modal-content" onclick="event.stopPropagation()">
        <div class="modal-header">
            <h3 id="modal-title">New Task</h3>
            <button class="modal-close" onclick="closeTaskModal()">×</button>
        </div>
        <form method="POST" class="admin-form" id="task-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" id="task-action" value="create">
            <input type="hidden" name="task_id" id="task-id" value="">

            <div class="form-group">
                <label>Title *</label>
                <input type="text" name="title" id="task-title" required>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" id="task-description" rows="3"></textarea>
            </div>
            <div class="form-group">
                <label>Type</label>
                <select name="type" id="task-type">
                    <?php foreach ($taskTypes as $val => $label): ?>
                        <option value="<?= e($val) ?>"><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>URL</label>
                <input type="url" name="url" id="task-url" placeholder="https://...">
            </div>
            <div class="form-group" style="display: flex; gap: 24px;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="required" id="task-required" checked> Required
                </label>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="enabled" id="task-enabled" checked> Enabled
                </label>
            </div>
            <button type="submit" class="btn-admin btn-admin-primary" style="width: 100%; justify-content: center;">
                <i class="bi bi-check-lg"></i> Save Task
            </button>
        </form>
    </div>
</div>

<script>
function openTaskModal() {
    document.getElementById('modal-title').textContent = 'New Task';
    document.getElementById('task-action').value = 'create';
    document.getElementById('task-id').value = '';
    document.getElementById('task-title').value = '';
    document.getElementById('task-description').value = '';
    document.getElementById('task-type').value = 'custom';
    document.getElementById('task-url').value = '';
    document.getElementById('task-required').checked = true;
    document.getElementById('task-enabled').checked = true;
    document.getElementById('task-modal').classList.add('show');
}

function editTask(task) {
    document.getElementById('modal-title').textContent = 'Edit Task';
    document.getElementById('task-action').value = 'update';
    document.getElementById('task-id').value = task.id;
    document.getElementById('task-title').value = task.title;
    document.getElementById('task-description').value = task.description || '';
    document.getElementById('task-type').value = task.type;
    document.getElementById('task-url').value = task.url || '';
    document.getElementById('task-required').checked = task.required == 1;
    document.getElementById('task-enabled').checked = task.enabled == 1;
    document.getElementById('task-modal').classList.add('show');
}

function closeTaskModal() {
    document.getElementById('task-modal').classList.remove('show');
}
</script>

<?php
$content = ob_get_clean();
include VIEWS_PATH . '/layouts/admin.php';
?>
