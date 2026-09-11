<?php
/**
 * Gaggle NFT — Admin Layout Template
 */
$projectName = get_setting('project_name', 'general', 'Gaggle');
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Admin') ?> — <?= e($projectName) ?> Admin</title>
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
    <?php if (!empty($extraCss)): foreach ($extraCss as $css): ?>
        <link rel="stylesheet" href="<?= e($css) ?>">
    <?php endforeach; endif; ?>
    <link rel="icon" type="image/jpeg" href="/assets/images/hero-pfp.jpg">
</head>
<body class="admin-body">
    <!-- Sidebar Toggle (Mobile) -->
    <button class="sidebar-toggle" aria-label="Toggle sidebar"><i class="bi bi-list"></i></button>

    <div class="admin-wrapper">
        <!-- Sidebar -->
        <aside class="admin-sidebar" id="admin-sidebar">
            <div class="sidebar-header">
                <img src="/assets/images/hero-pfp.jpg" alt="<?= e($projectName) ?>">
                <span class="brand-text"><?= e($projectName) ?></span>
            </div>

            <nav class="sidebar-nav">
                <div class="sidebar-section">Main</div>
                <a href="/admin/index.php" class="<?= $currentPage === 'index.php' ? 'active' : '' ?>">
                    <i class="bi bi-grid-1x2"></i> Dashboard
                </a>
                <a href="/admin/applications.php" class="<?= $currentPage === 'applications.php' || $currentPage === 'application-view.php' ? 'active' : '' ?>">
                    <i class="bi bi-file-earmark-text"></i> Applications
                </a>
                <a href="/admin/tasks.php" class="<?= $currentPage === 'tasks.php' ? 'active' : '' ?>">
                    <i class="bi bi-list-check"></i> Tasks
                </a>

                <div class="sidebar-section">Security</div>
                <a href="/admin/blacklist.php" class="<?= $currentPage === 'blacklist.php' ? 'active' : '' ?>">
                    <i class="bi bi-shield-x"></i> Blacklist
                </a>
                <a href="/admin/ips.php" class="<?= $currentPage === 'ips.php' ? 'active' : '' ?>">
                    <i class="bi bi-hdd-network"></i> IP Management
                </a>

                <div class="sidebar-section">System</div>
                <a href="/admin/settings.php" class="<?= $currentPage === 'settings.php' ? 'active' : '' ?>">
                    <i class="bi bi-gear"></i> Settings
                </a>
                <a href="/admin/logs.php" class="<?= $currentPage === 'logs.php' ? 'active' : '' ?>">
                    <i class="bi bi-journal-text"></i> Audit Logs
                </a>
                <a href="/admin/export.php" class="<?= $currentPage === 'export.php' ? 'active' : '' ?>">
                    <i class="bi bi-download"></i> Export
                </a>
            </nav>

            <div class="sidebar-footer">
                <a href="/admin/logout.php"><i class="bi bi-box-arrow-left"></i> Logout</a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="admin-main">
            <div class="admin-topbar">
                <h1><?= e($pageTitle ?? 'Dashboard') ?></h1>
                <div class="admin-user">
                    <i class="bi bi-person-circle"></i>
                    <?= e(admin_username() ?? 'Admin') ?>
                </div>
            </div>

            <!-- Flash Messages -->
            <?php if ($msg = get_flash('success')): ?>
                <div class="alert alert-success"><?= e($msg) ?></div>
            <?php endif; ?>
            <?php if ($msg = get_flash('error')): ?>
                <div class="alert alert-error"><?= e($msg) ?></div>
            <?php endif; ?>
            <?php if ($msg = get_flash('warning')): ?>
                <div class="alert alert-warning"><?= e($msg) ?></div>
            <?php endif; ?>

            <?= $content ?? '' ?>
        </main>
    </div>

    <script src="/assets/js/admin.js"></script>
    <?php if (!empty($extraJs)): foreach ($extraJs as $js): ?>
        <script src="<?= e($js) ?>"></script>
    <?php endforeach; endif; ?>
</body>
</html>
