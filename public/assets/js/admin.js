/**
 * Gaggle NFT — Admin Panel JavaScript
 */
document.addEventListener('DOMContentLoaded', () => {

    // ---- Sidebar Toggle (Mobile) ----
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    const sidebar = document.querySelector('.admin-sidebar');
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('show');
        });
        // Close on outside click
        document.addEventListener('click', (e) => {
            if (sidebar.classList.contains('show') && !sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                sidebar.classList.remove('show');
            }
        });
    }

    // ---- Bulk Action Checkboxes ----
    const selectAll = document.getElementById('select-all');
    const bulkActions = document.querySelector('.bulk-actions');
    const bulkCount = document.querySelector('.bulk-count');
    
    if (selectAll) {
        selectAll.addEventListener('change', () => {
            const checkboxes = document.querySelectorAll('.row-checkbox');
            checkboxes.forEach(cb => cb.checked = selectAll.checked);
            updateBulkActions();
        });
    }

    document.querySelectorAll('.row-checkbox').forEach(cb => {
        cb.addEventListener('change', updateBulkActions);
    });

    function updateBulkActions() {
        const checked = document.querySelectorAll('.row-checkbox:checked');
        if (bulkActions) {
            bulkActions.classList.toggle('show', checked.length > 0);
        }
        if (bulkCount) {
            bulkCount.textContent = `${checked.length} selected`;
        }
    }

    // ---- Bulk Action Buttons ----
    document.querySelectorAll('.btn-bulk-action').forEach(btn => {
        btn.addEventListener('click', async () => {
            const action = btn.dataset.action;
            const checked = document.querySelectorAll('.row-checkbox:checked');
            const ids = Array.from(checked).map(cb => cb.value);

            if (ids.length === 0) return;

            const destructive = ['delete', 'blacklist'];
            if (destructive.includes(action)) {
                if (!confirm(`Are you sure you want to ${action} ${ids.length} application(s)? This action cannot be undone.`)) {
                    return;
                }
            } else {
                if (!confirm(`${action.charAt(0).toUpperCase() + action.slice(1)} ${ids.length} application(s)?`)) {
                    return;
                }
            }

            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const response = await fetch('/admin/ajax/bulk-action.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': csrfToken,
                    },
                    body: JSON.stringify({ ids, action })
                });

                const result = await response.json();
                if (result.success) {
                    showNotification(result.message || 'Action completed.', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(result.message || 'Action failed.', 'error');
                }
            } catch (error) {
                showNotification('Network error. Please try again.', 'error');
            }
        });
    });

    // ---- Quick Status Change ----
    document.querySelectorAll('.btn-status-change').forEach(btn => {
        btn.addEventListener('click', async () => {
            const id = btn.dataset.id;
            const status = btn.dataset.status;
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

            try {
                const response = await fetch('/admin/ajax/status-change.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': csrfToken,
                    },
                    body: JSON.stringify({ id, status })
                });

                const result = await response.json();
                if (result.success) {
                    showNotification(`Application ${status}.`, 'success');
                    setTimeout(() => location.reload(), 800);
                } else {
                    showNotification(result.message || 'Failed.', 'error');
                }
            } catch (error) {
                showNotification('Network error.', 'error');
            }
        });
    });

    // ---- Confirm Delete ----
    document.querySelectorAll('.btn-confirm-delete').forEach(btn => {
        btn.addEventListener('click', (e) => {
            if (!confirm('Are you sure you want to delete this? This action cannot be undone.')) {
                e.preventDefault();
            }
        });
    });

    // ---- Admin Tabs ----
    document.querySelectorAll('.admin-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            const target = tab.dataset.tab;
            if (!target) return;

            document.querySelectorAll('.admin-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));

            tab.classList.add('active');
            const panel = document.getElementById(target);
            if (panel) panel.classList.add('active');
        });
    });

    // ---- Modal ----
    document.querySelectorAll('[data-modal]').forEach(trigger => {
        trigger.addEventListener('click', () => {
            const modal = document.getElementById(trigger.dataset.modal);
            if (modal) modal.classList.add('show');
        });
    });

    document.querySelectorAll('.modal-close, .modal-overlay').forEach(el => {
        el.addEventListener('click', (e) => {
            if (e.target === el) {
                el.closest('.modal-overlay')?.classList.remove('show');
            }
        });
    });

    // ---- Task Reorder (using SortableJS CDN) ----
    const taskList = document.getElementById('task-sortable-list');
    if (taskList && typeof Sortable !== 'undefined') {
        Sortable.create(taskList, {
            handle: '.task-handle',
            animation: 200,
            ghostClass: 'sortable-ghost',
            onEnd: async () => {
                const items = taskList.querySelectorAll('[data-task-id]');
                const order = Array.from(items).map(item => item.dataset.taskId);
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

                try {
                    await fetch('/admin/ajax/reorder-tasks.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-Token': csrfToken,
                        },
                        body: JSON.stringify({ order })
                    });
                } catch (error) {
                    console.error('Reorder failed:', error);
                }
            }
        });
    }

    // ---- Task Toggle ----
    document.querySelectorAll('.task-toggle').forEach(toggle => {
        toggle.addEventListener('change', async () => {
            const taskId = toggle.dataset.taskId;
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

            try {
                await fetch('/admin/ajax/toggle-task.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': csrfToken,
                    },
                    body: JSON.stringify({ id: taskId })
                });
            } catch (error) {
                toggle.checked = !toggle.checked;
                showNotification('Failed to toggle task.', 'error');
            }
        });
    });

    // ---- Notification Toast ----
    function showNotification(message, type = 'success') {
        const existing = document.querySelector('.admin-toast');
        if (existing) existing.remove();

        const toast = document.createElement('div');
        toast.className = `admin-toast admin-toast-${type}`;
        toast.textContent = message;
        toast.style.cssText = `
            position: fixed; top: 20px; right: 20px; z-index: 9999;
            padding: 12px 24px; border-radius: 8px; font-weight: 600; font-size: 0.9rem;
            animation: fadeInDown 0.3s ease-out; box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            background: ${type === 'success' ? '#1a4d1a' : '#4d1a1a'};
            color: ${type === 'success' ? '#7db36a' : '#da3633'};
            border: 1px solid ${type === 'success' ? '#2a6b2a' : '#6b2a2a'};
        `;
        document.body.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.3s';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    // Make notification available globally
    window.showNotification = showNotification;
});
