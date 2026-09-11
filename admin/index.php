<?php
/**
 * Gaggle NFT — Admin Dashboard
 */
require_once dirname(__DIR__) . '/app/middleware/AuthMiddleware.php';
require_admin_auth();

require_once APP_PATH . '/models/Application.php';
require_once APP_PATH . '/services/RateLimitService.php';

$appModel = new Application();
$stats = $appModel->getStats();
$dailyStats = $appModel->getDailyStats(30);

$rateLimiter = new RateLimitService();
$rlStats = $rateLimiter->getStats();

$pageTitle = 'Dashboard';

// Prepare chart data
$chartLabels = [];
$chartData = [];
$dailyMap = [];
foreach ($dailyStats as $row) {
    $dailyMap[$row['date']] = (int) $row['count'];
}
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $chartLabels[] = date('M j', strtotime($date));
    $chartData[] = $dailyMap[$date] ?? 0;
}

ob_start();
?>

<!-- Stat Cards -->
<div class="stat-cards">
    <div class="stat-card-admin">
        <div class="stat-icon green"><i class="bi bi-file-earmark-text"></i></div>
        <div class="stat-number"><?= number_format($stats['total']) ?></div>
        <div class="stat-title">Total Applications</div>
    </div>
    <div class="stat-card-admin">
        <div class="stat-icon gold"><i class="bi bi-hourglass-split"></i></div>
        <div class="stat-number"><?= number_format($stats['pending']) ?></div>
        <div class="stat-title">Pending</div>
    </div>
    <div class="stat-card-admin">
        <div class="stat-icon green"><i class="bi bi-check-circle"></i></div>
        <div class="stat-number"><?= number_format($stats['approved']) ?></div>
        <div class="stat-title">Approved</div>
    </div>
    <div class="stat-card-admin">
        <div class="stat-icon red"><i class="bi bi-x-circle"></i></div>
        <div class="stat-number"><?= number_format($stats['rejected']) ?></div>
        <div class="stat-title">Rejected</div>
    </div>
    <div class="stat-card-admin">
        <div class="stat-icon blue"><i class="bi bi-calendar-check"></i></div>
        <div class="stat-number"><?= number_format($stats['today']) ?></div>
        <div class="stat-title">Today</div>
    </div>
    <div class="stat-card-admin">
        <div class="stat-icon gold"><i class="bi bi-calendar-week"></i></div>
        <div class="stat-number"><?= number_format($stats['this_week']) ?></div>
        <div class="stat-title">This Week</div>
    </div>
    <div class="stat-card-admin">
        <div class="stat-icon green"><i class="bi bi-wallet2"></i></div>
        <div class="stat-number"><?= number_format($stats['unique_wallets']) ?></div>
        <div class="stat-title">Unique Wallets</div>
    </div>
    <div class="stat-card-admin">
        <div class="stat-icon blue"><i class="bi bi-hdd-network"></i></div>
        <div class="stat-number"><?= number_format($stats['unique_ips']) ?></div>
        <div class="stat-title">Unique IPs</div>
    </div>
</div>

<!-- Charts -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
    <div class="admin-card">
        <div class="admin-card-header">
            <h3>Applications (Last 30 Days)</h3>
        </div>
        <div class="chart-container">
            <canvas id="dailyChart"></canvas>
        </div>
    </div>
    <div class="admin-card">
        <div class="admin-card-header">
            <h3>Status Distribution</h3>
        </div>
        <div class="chart-container">
            <canvas id="statusChart"></canvas>
        </div>
    </div>
</div>

<!-- Recent Applications -->
<div class="admin-card" style="margin-top: 20px;">
    <div class="admin-card-header">
        <h3>Recent Applications</h3>
        <a href="/admin/applications.php" class="btn-admin btn-admin-secondary btn-admin-sm">View All</a>
    </div>
    <div class="admin-card-body" style="padding: 0;">
        <?php
        $recent = $appModel->getAll([], 1, 10);
        if (!empty($recent['data'])):
        ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Wallet</th>
                    <th>Twitter</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent['data'] as $app): ?>
                <tr>
                    <td><a href="/admin/application-view.php?id=<?= (int)$app['id'] ?>"><?= e($app['application_id']) ?></a></td>
                    <td><code style="font-size: 0.8rem;"><?= e(mask_wallet($app['wallet_address'])) ?></code></td>
                    <td><?= $app['twitter_username'] ? '@' . e($app['twitter_username']) : '—' ?></td>
                    <td><?= status_badge($app['status']) ?></td>
                    <td><?= e(time_ago($app['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <p style="padding: 20px; color: var(--admin-text-muted);">No applications yet.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
    // Daily applications chart
    const dailyCtx = document.getElementById('dailyChart').getContext('2d');
    new Chart(dailyCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode($chartLabels) ?>,
            datasets: [{
                label: 'Applications',
                data: <?= json_encode($chartData) ?>,
                borderColor: '#7db36a',
                backgroundColor: 'rgba(125, 179, 106, 0.1)',
                fill: true,
                tension: 0.3,
                pointRadius: 2,
                pointHoverRadius: 5,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { color: '#21262d' }, ticks: { color: '#7d8590', maxTicksLimit: 10 } },
                y: { grid: { color: '#21262d' }, ticks: { color: '#7d8590', precision: 0 }, beginAtZero: true }
            }
        }
    });

    // Status distribution chart
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: ['Pending', 'Approved', 'Rejected', 'Blacklisted', 'Review'],
            datasets: [{
                data: [<?= $stats['pending'] ?>, <?= $stats['approved'] ?>, <?= $stats['rejected'] ?>, <?= $stats['blacklisted'] ?>, <?= $stats['review'] ?>],
                backgroundColor: ['#d29922', '#7db36a', '#da3633', '#7d8590', '#58a6ff'],
                borderWidth: 0,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { color: '#7d8590', padding: 12 } }
            }
        }
    });
</script>

<?php
$content = ob_get_clean();
include VIEWS_PATH . '/layouts/admin.php';
?>
