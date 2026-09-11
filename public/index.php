<?php
/**
 * Gaggle NFT — Landing Page
 */
define('GAGGLE_ROOT', dirname(__DIR__));
require_once GAGGLE_ROOT . '/app/config/config.php';

// Load settings
$general = get_settings_group('general');
$projectName = $general['project_name'] ?? 'Gaggle';
$tagline = $general['project_tagline'] ?? 'The flock is coming.';
$description = $general['project_description'] ?? '';
$supply = $general['supply'] ?? '5,555';
$mintPrice = $general['mint_price'] ?? '0.05 ETH';
$blockchain = $general['blockchain'] ?? 'Ethereum';
$wlSpots = $general['wl_spots'] ?? '2,000';
$mintDate = $general['mint_date'] ?? 'TBA';
$twitterUrl = $general['twitter_url'] ?? '#';
$discordUrl = $general['discord_url'] ?? '#';
$telegramUrl = $general['telegram_url'] ?? '#';

$pageTitle = '';
$pfpImage = '/assets/images/hero-pfp.jpg';
$logoImage = '/assets/images/logo-banner.jpg';

// Start output buffering for content
ob_start();
?>

<!-- Hero Section -->
<section class="hero" id="hero">
    <!-- Floating PFPs -->
    <div class="floating-pfp float-1"><img src="<?= $pfpImage ?>" alt="Gaggle PFP" loading="lazy"></div>
    <div class="floating-pfp float-2"><img src="<?= $pfpImage ?>" alt="Gaggle PFP" loading="lazy"></div>
    <div class="floating-pfp float-3"><img src="<?= $pfpImage ?>" alt="Gaggle PFP" loading="lazy"></div>
    <div class="floating-pfp float-4"><img src="<?= $pfpImage ?>" alt="Gaggle PFP" loading="lazy"></div>
    <div class="floating-pfp float-5"><img src="<?= $pfpImage ?>" alt="Gaggle PFP" loading="lazy"></div>
    <div class="floating-pfp float-6"><img src="<?= $pfpImage ?>" alt="Gaggle PFP" loading="lazy"></div>

    <div class="hero-content">
        <div class="hero-logo">
            <img src="<?= $logoImage ?>" alt="<?= e($projectName) ?> Logo">
        </div>

        <div class="hero-tagline"><?= e($tagline) ?></div>

        <p class="hero-description"><?= e($description) ?></p>

        <div class="hero-buttons">
            <a href="/apply.php" class="btn-gaggle btn-gold" id="hero-apply-btn">
                <i class="bi bi-lightning-charge-fill"></i> Apply for Whitelist
            </a>
            <a href="#collection" class="btn-gaggle btn-secondary">
                <i class="bi bi-grid-3x3-gap-fill"></i> View Collection
            </a>
        </div>

        <div class="hero-socials">
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
</section>

<!-- Stats Bar -->
<section class="stats-bar">
    <div class="container-custom">
        <div class="stats-grid">
            <div class="stat-card reveal">
                <div class="stat-value" data-target="<?= e(str_replace(',', '', $supply)) ?>"><?= e($supply) ?></div>
                <div class="stat-label">Total Supply</div>
            </div>
            <div class="stat-card reveal">
                <div class="stat-value"><?= e($mintPrice) ?></div>
                <div class="stat-label">Mint Price</div>
            </div>
            <div class="stat-card reveal">
                <div class="stat-value"><?= e($blockchain) ?></div>
                <div class="stat-label">Blockchain</div>
            </div>
            <div class="stat-card reveal">
                <div class="stat-value" data-target="<?= e(str_replace(',', '', $wlSpots)) ?>"><?= e($wlSpots) ?></div>
                <div class="stat-label">WL Spots</div>
            </div>
            <div class="stat-card reveal">
                <div class="stat-value"><?= e($mintDate) ?></div>
                <div class="stat-label">Mint Date</div>
            </div>
        </div>
    </div>
</section>

<!-- Collection Preview -->
<section class="section section-dark" id="collection">
    <div class="container-custom text-center">
        <p class="section-title reveal">The Collection</p>
        <h2 class="section-subtitle reveal"><?= e($projectName) ?> PFPs</h2>
        <p class="section-desc reveal">Each <?= e($projectName) ?> is a unique, pixel-art masterpiece. No two are alike.</p>

        <div class="collection-grid">
            <?php for ($i = 1; $i <= 8; $i++): ?>
                <div class="collection-item reveal">
                    <img src="<?= $pfpImage ?>" alt="<?= e($projectName) ?> #<?= $i ?>" loading="lazy">
                    <div class="collection-label"><?= e($projectName) ?> #<?= str_pad($i, 4, '0', STR_PAD_LEFT) ?></div>
                </div>
            <?php endfor; ?>
        </div>
    </div>
</section>

<!-- About / Lore -->
<section class="section section-accent" id="about">
    <div class="container-custom">
        <div class="about-grid">
            <div class="about-image reveal">
                <img src="<?= $pfpImage ?>" alt="<?= e($projectName) ?> Character">
            </div>
            <div class="about-text">
                <p class="section-title reveal">The Lore</p>
                <h2 class="section-subtitle reveal">What is <?= e($projectName) ?>?</h2>
                <p class="reveal">
                    In a world dominated by apes, cats, and punks, a flock of rebellious pixel ducks decided it was time
                    to claim their rightful place on the blockchain. They call themselves the <strong style="color: var(--gaggle-gold);"><?= e($projectName) ?></strong>.
                </p>
                <p class="reveal">
                    Born in the pixelated marshlands of the decentralized web, each <?= e($projectName) ?> carries unique traits
                    forged by chaos and creativity. From crimson-hatted rogues to golden-chained nobles, no two ducks waddle the same way.
                </p>
                <p class="reveal">
                    The <?= e($projectName) ?> isn't just a collection — it's a movement. A community of degens, dreamers, and
                    diamond-handed believers who know that the flock is always stronger together.
                </p>
                <div class="reveal" style="margin-top: 24px;">
                    <a href="/apply.php" class="btn-gaggle btn-primary">
                        <i class="bi bi-lightning-charge-fill"></i> Join the Flock
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Roadmap -->
<section class="section section-dark" id="roadmap">
    <div class="container-custom text-center">
        <p class="section-title reveal">The Plan</p>
        <h2 class="section-subtitle reveal">Roadmap</h2>

        <div class="roadmap-timeline">
            <div class="roadmap-item reveal">
                <div class="roadmap-marker active"></div>
                <div class="roadmap-card">
                    <div class="roadmap-phase">Phase 1 — Genesis</div>
                    <div class="roadmap-title">The Flock Assembles</div>
                    <div class="roadmap-desc">Community building, social media launch, whitelist applications open, early holder rewards, and the reveal of the first <?= e($projectName) ?> PFPs.</div>
                </div>
            </div>

            <div class="roadmap-item reveal">
                <div class="roadmap-marker"></div>
                <div class="roadmap-card">
                    <div class="roadmap-phase">Phase 2 — The Mint</div>
                    <div class="roadmap-title">Whitelist & Public Mint</div>
                    <div class="roadmap-desc">WL mint followed by public sale. <?= e($supply) ?> unique pixel ducks released onto the blockchain. Rarity rankings and trait reveals.</div>
                </div>
            </div>

            <div class="roadmap-item reveal">
                <div class="roadmap-marker"></div>
                <div class="roadmap-card">
                    <div class="roadmap-phase">Phase 3 — Community</div>
                    <div class="roadmap-title">Holder Benefits & Merch</div>
                    <div class="roadmap-desc">Exclusive holder events, merch drops, collaborations with other projects, community fund, and DAO governance proposals.</div>
                </div>
            </div>

            <div class="roadmap-item reveal">
                <div class="roadmap-marker"></div>
                <div class="roadmap-card">
                    <div class="roadmap-phase">Phase 4 — Expansion</div>
                    <div class="roadmap-title">The Pond Expands</div>
                    <div class="roadmap-desc">Staking mechanism, companion collection, metaverse integrations, and cross-chain expansion. The flock never stops growing.</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Whitelist CTA -->
<section class="section">
    <div class="container-custom">
        <div class="wl-cta reveal">
            <p class="section-title">Whitelist</p>
            <h2 class="section-subtitle">Join the <?= e($projectName) ?></h2>
            <p class="section-desc">
                Complete the whitelist tasks and submit your application.
                Approved wallets get priority access to the mint.
            </p>
            <a href="/apply.php" class="btn-gaggle btn-gold" style="font-size: 1.1rem; padding: 18px 40px;">
                <i class="bi bi-lightning-charge-fill"></i> Apply for Whitelist
            </a>
        </div>
    </div>
</section>

<!-- FAQ -->
<section class="section section-dark" id="faq">
    <div class="container-custom text-center">
        <p class="section-title reveal">FAQ</p>
        <h2 class="section-subtitle reveal">Questions & Answers</h2>

        <div class="faq-list">
            <div class="faq-item reveal">
                <button class="faq-question">
                    What is <?= e($projectName) ?>?
                    <span class="faq-icon">+</span>
                </button>
                <div class="faq-answer">
                    <div class="faq-answer-inner">
                        <?= e($projectName) ?> is a unique PFP NFT collection of <?= e($supply) ?> pixel-art characters living on the <?= e($blockchain) ?> blockchain. Each character is procedurally generated with unique traits and attributes.
                    </div>
                </div>
            </div>

            <div class="faq-item reveal">
                <button class="faq-question">
                    How do I get whitelisted?
                    <span class="faq-icon">+</span>
                </button>
                <div class="faq-answer">
                    <div class="faq-answer-inner">
                        Click the "Apply for Whitelist" button, complete all required tasks (follow our socials, join Discord, etc.), fill in your wallet and social information, then submit your application. Approved applicants will get priority mint access.
                    </div>
                </div>
            </div>

            <div class="faq-item reveal">
                <button class="faq-question">
                    What is the mint price?
                    <span class="faq-icon">+</span>
                </button>
                <div class="faq-answer">
                    <div class="faq-answer-inner">
                        The whitelist mint price is <?= e($mintPrice) ?>. Public mint price may differ. Check our socials for the latest pricing information.
                    </div>
                </div>
            </div>

            <div class="faq-item reveal">
                <button class="faq-question">
                    When is the mint?
                    <span class="faq-icon">+</span>
                </button>
                <div class="faq-answer">
                    <div class="faq-answer-inner">
                        The mint date is currently: <strong><?= e($mintDate) ?></strong>. Follow us on X and join our Discord for real-time announcements.
                    </div>
                </div>
            </div>

            <div class="faq-item reveal">
                <button class="faq-question">
                    Which blockchain?
                    <span class="faq-icon">+</span>
                </button>
                <div class="faq-answer">
                    <div class="faq-answer-inner">
                        <?= e($projectName) ?> will be minted on <?= e($blockchain) ?>. Make sure your wallet supports <?= e($blockchain) ?> and has sufficient funds for the mint plus gas fees.
                    </div>
                </div>
            </div>

            <div class="faq-item reveal">
                <button class="faq-question">
                    How do I check my application status?
                    <span class="faq-icon">+</span>
                </button>
                <div class="faq-answer">
                    <div class="faq-answer-inner">
                        After applying you'll receive a unique application ID (e.g., WL-8F29A1C7). Visit the <a href="/status.php" style="color: var(--gaggle-green-dark);">Status Check</a> page and enter your ID to view your application status.
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Team -->
<section class="section section-accent" id="team">
    <div class="container-custom text-center">
        <p class="section-title reveal">The Team</p>
        <h2 class="section-subtitle reveal">Meet the Flock</h2>

        <div class="team-grid">
            <div class="team-card reveal">
                <div class="team-avatar">
                    <img src="<?= $pfpImage ?>" alt="Founder">
                </div>
                <div class="team-name">DuckLord</div>
                <div class="team-role">Founder</div>
                <div class="team-socials">
                    <a href="<?= e($twitterUrl) ?>" target="_blank" rel="noopener"><i class="bi bi-twitter-x"></i></a>
                </div>
            </div>

            <div class="team-card reveal">
                <div class="team-avatar">
                    <img src="<?= $pfpImage ?>" alt="Artist">
                </div>
                <div class="team-name">PixelQuack</div>
                <div class="team-role">Artist</div>
                <div class="team-socials">
                    <a href="#" target="_blank"><i class="bi bi-twitter-x"></i></a>
                </div>
            </div>

            <div class="team-card reveal">
                <div class="team-avatar">
                    <img src="<?= $pfpImage ?>" alt="Developer">
                </div>
                <div class="team-name">CodeGoose</div>
                <div class="team-role">Developer</div>
                <div class="team-socials">
                    <a href="#" target="_blank"><i class="bi bi-twitter-x"></i></a>
                </div>
            </div>

            <div class="team-card reveal">
                <div class="team-avatar">
                    <img src="<?= $pfpImage ?>" alt="Community">
                </div>
                <div class="team-name">WaddleWiz</div>
                <div class="team-role">Community Lead</div>
                <div class="team-socials">
                    <a href="#" target="_blank"><i class="bi bi-twitter-x"></i></a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
$content = ob_get_clean();
include VIEWS_PATH . '/layouts/public.php';
?>
