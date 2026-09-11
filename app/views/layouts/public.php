<?php
/**
 * Gaggle NFT — Public Layout Template
 * 
 * Variables available:
 *   $pageTitle - Page title
 *   $pageDescription - Meta description
 *   $bodyClass - Additional body class
 *   $extraCss - Additional CSS files array
 *   $extraJs - Additional JS files array
 */

$projectName = get_setting('project_name', 'general', 'Gaggle');
$pageTitle = ($pageTitle ?? '') ? "{$pageTitle} | {$projectName}" : "{$projectName} — NFT Collection";
$pageDescription = $pageDescription ?? get_setting('project_description', 'general', 'A unique PFP NFT collection.');
$twitterUrl = get_setting('twitter_url', 'general', '#');
$discordUrl = get_setting('discord_url', 'general', '#');
$telegramUrl = get_setting('telegram_url', 'general', '#');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($pageDescription) ?>">
    <meta name="robots" content="index, follow">

    <!-- Open Graph -->
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($pageDescription) ?>">
    <meta property="og:type" content="website">
    <meta property="og:image" content="<?= e(APP_URL) ?>/assets/images/logo-banner.jpg">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($pageTitle) ?>">
    <meta name="twitter:description" content="<?= e($pageDescription) ?>">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <!-- Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Styles -->
    <link rel="stylesheet" href="/assets/css/style.css">
    <?php if (!empty($extraCss)): foreach ($extraCss as $css): ?>
        <link rel="stylesheet" href="<?= e($css) ?>">
    <?php endforeach; endif; ?>

    <!-- Favicon -->
    <link rel="icon" type="image/jpeg" href="/assets/images/hero-pfp.jpg">
</head>
<body class="<?= e($bodyClass ?? '') ?>">

    <!-- Navigation -->
    <nav class="navbar-gaggle" id="navbar">
        <div class="nav-inner">
            <a href="/" class="nav-logo">
                <img src="/assets/images/hero-pfp.jpg" alt="<?= e($projectName) ?>">
                <span class="nav-logo-text"><?= e($projectName) ?></span>
            </a>

            <ul class="nav-links" id="nav-links">
                <li><a href="/#collection">Collection</a></li>
                <li><a href="/#about">About</a></li>
                <li><a href="/#roadmap">Roadmap</a></li>
                <li><a href="/#faq">FAQ</a></li>
                <li><a href="/#team">Team</a></li>
                <li><a href="/apply.php" class="btn-gaggle btn-primary btn-sm">Apply for WL</a></li>
            </ul>

            <button class="nav-toggle" id="nav-toggle" aria-label="Toggle menu">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>
    </nav>

    <!-- Main Content -->
    <?= $content ?? '' ?>

    <!-- Footer -->
    <footer class="footer">
        <div class="container-custom">
            <div class="footer-grid">
                <div class="footer-brand">
                    <img src="/assets/images/logo-banner.jpg" alt="<?= e($projectName) ?>" style="border-radius: 8px;">
                    <p><?= e(get_setting('project_description', 'general', '')) ?></p>
                    <div class="hero-socials" style="justify-content: flex-start;">
                        <?php if ($twitterUrl !== '#'): ?>
                            <a href="<?= e($twitterUrl) ?>" target="_blank" rel="noopener" aria-label="X/Twitter"><i class="bi bi-twitter-x"></i></a>
                        <?php endif; ?>
                        <?php if ($discordUrl !== '#'): ?>
                            <a href="<?= e($discordUrl) ?>" target="_blank" rel="noopener" aria-label="Discord"><i class="bi bi-discord"></i></a>
                        <?php endif; ?>
                        <?php if ($telegramUrl !== '#'): ?>
                            <a href="<?= e($telegramUrl) ?>" target="_blank" rel="noopener" aria-label="Telegram"><i class="bi bi-telegram"></i></a>
                        <?php endif; ?>
                    </div>
                </div>

                <div>
                    <h4 class="footer-heading">Quick Links</h4>
                    <ul class="footer-links">
                        <li><a href="/#collection">Collection</a></li>
                        <li><a href="/#about">About</a></li>
                        <li><a href="/#roadmap">Roadmap</a></li>
                        <li><a href="/#faq">FAQ</a></li>
                    </ul>
                </div>

                <div>
                    <h4 class="footer-heading">Whitelist</h4>
                    <ul class="footer-links">
                        <li><a href="/apply.php">Apply for Whitelist</a></li>
                        <li><a href="/status.php">Check Status</a></li>
                    </ul>
                </div>
            </div>

            <div class="footer-bottom">
                &copy; <?= date('Y') ?> <?= e($projectName) ?>. All rights reserved.
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="/assets/js/main.js"></script>
    <?php if (!empty($extraJs)): foreach ($extraJs as $js): ?>
        <script src="<?= e($js) ?>"></script>
    <?php endforeach; endif; ?>
</body>
</html>
