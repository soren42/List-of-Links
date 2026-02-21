<?php
/**
 * List of Links (LoL) - Page Renderer & Router
 *
 * Behavior:
 *   ?page=slug  or  /slug   → Render that user's link page
 *   No page specified:
 *     - 0 pages configured  → Redirect to admin.php (initial setup)
 *     - 1 page configured   → Render that single page
 *     - 2+ pages configured → Show a directory listing of all pages
 */

// --- Icon Map: domain patterns → FontAwesome 6 Free icon classes ---
function getServiceIcon(string $url): ?string {
    $iconMap = [
        'discord.gg'        => 'fa-brands fa-discord',
        'discord.com'       => 'fa-brands fa-discord',
        'tiktok.com'        => 'fa-brands fa-tiktok',
        'vm.tiktok.com'     => 'fa-brands fa-tiktok',
        'twitch.tv'         => 'fa-brands fa-twitch',
        'youtube.com'       => 'fa-brands fa-youtube',
        'youtu.be'          => 'fa-brands fa-youtube',
        'instagram.com'     => 'fa-brands fa-instagram',
        'twitter.com'       => 'fa-brands fa-x-twitter',
        'x.com'             => 'fa-brands fa-x-twitter',
        'facebook.com'      => 'fa-brands fa-facebook',
        'fb.com'            => 'fa-brands fa-facebook',
        'github.com'        => 'fa-brands fa-github',
        'linkedin.com'      => 'fa-brands fa-linkedin',
        'snapchat.com'      => 'fa-brands fa-snapchat',
        'reddit.com'        => 'fa-brands fa-reddit',
        'pinterest.com'     => 'fa-brands fa-pinterest',
        'spotify.com'       => 'fa-brands fa-spotify',
        'open.spotify.com'  => 'fa-brands fa-spotify',
        'soundcloud.com'    => 'fa-brands fa-soundcloud',
        'patreon.com'       => 'fa-brands fa-patreon',
        'steam.com'         => 'fa-brands fa-steam',
        'steampowered.com'  => 'fa-brands fa-steam',
        'store.steampowered.com' => 'fa-brands fa-steam',
        'tumblr.com'        => 'fa-brands fa-tumblr',
        'whatsapp.com'      => 'fa-brands fa-whatsapp',
        'wa.me'             => 'fa-brands fa-whatsapp',
        'telegram.org'      => 'fa-brands fa-telegram',
        't.me'              => 'fa-brands fa-telegram',
        'twitch.com'        => 'fa-brands fa-twitch',
        'kick.com'          => 'fa-brands fa-kickstarter',
        'vimeo.com'         => 'fa-brands fa-vimeo',
        'behance.net'       => 'fa-brands fa-behance',
        'dribbble.com'      => 'fa-brands fa-dribbble',
        'deviantart.com'    => 'fa-brands fa-deviantart',
        'etsy.com'          => 'fa-brands fa-etsy',
        'paypal.com'        => 'fa-brands fa-paypal',
        'paypal.me'         => 'fa-brands fa-paypal',
        'ko-fi.com'         => 'fa-solid fa-mug-hot',
        'cash.app'          => 'fa-solid fa-dollar-sign',
        'venmo.com'         => 'fa-solid fa-dollar-sign',
        'apple.com'         => 'fa-brands fa-apple',
        'music.apple.com'   => 'fa-brands fa-itunes-note',
        'threads.net'       => 'fa-brands fa-threads',
        'mastodon.social'   => 'fa-brands fa-mastodon',
        'bsky.app'          => 'fa-brands fa-bluesky',
    ];

    $host = parse_url($url, PHP_URL_HOST);
    if (!$host) return null;
    $host = strtolower(preg_replace('/^www\./', '', $host));

    // Exact match first
    if (isset($iconMap[$host])) return $iconMap[$host];

    // Try parent domain (e.g. vm.tiktok.com → tiktok.com)
    $parts = explode('.', $host);
    if (count($parts) > 2) {
        $parent = implode('.', array_slice($parts, -2));
        if (isset($iconMap[$parent])) return $iconMap[$parent];
    }

    return null;
}

/**
 * Load all page configs from /pages directory.
 */
function loadAllPages(): array {
    $pagesDir = __DIR__ . '/pages';
    $pages = [];
    $files = glob($pagesDir . '/*.json');
    foreach ($files as $file) {
        $data = json_decode(file_get_contents($file), true);
        if ($data) {
            $pages[] = $data;
        }
    }
    return $pages;
}

// ─── Routing ─────────────────────────────────────────────────────────

$slug = $_GET['page'] ?? null;

if (!$slug && !empty($_SERVER['PATH_INFO'])) {
    $slug = trim($_SERVER['PATH_INFO'], '/');
}

// If a slug is provided, sanitize and render that page
if ($slug) {
    $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower($slug));
    $configFile = __DIR__ . '/pages/' . $slug . '.json';

    if (!file_exists($configFile)) {
        http_response_code(404);
        ?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Not Found</title>
    <style>
        body { min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Inter', sans-serif; background: #1a1a2e; color: #fff; }
        .error { text-align: center; }
        .error h1 { font-size: 72px; margin-bottom: 8px; opacity: 0.3; }
        .error p { font-size: 18px; opacity: 0.7; }
    </style>
</head>
<body>
    <div class="error">
        <h1>404</h1>
        <p>This page doesn't exist.</p>
    </div>
</body>
</html><?php
        exit;
    }

    $config = json_decode(file_get_contents($configFile), true);
    if (!$config) {
        http_response_code(500);
        echo 'Error loading page configuration.';
        exit;
    }

    renderLinkPage($config);
    exit;
}

// No slug — decide what to show
$allPages = loadAllPages();
$pageCount = count($allPages);

if ($pageCount === 0) {
    // No pages at all — redirect to admin for initial setup
    header('Location: admin.php');
    exit;
} elseif ($pageCount === 1) {
    // Exactly one page — render it directly
    renderLinkPage($allPages[0]);
    exit;
} else {
    // Multiple pages — show directory listing
    renderDirectory($allPages);
    exit;
}

// ─── Render Functions ────────────────────────────────────────────────

function renderLinkPage(array $config): void {
    $title      = htmlspecialchars($config['title'] ?? 'My Links', ENT_QUOTES, 'UTF-8');
    $bio        = htmlspecialchars($config['bio'] ?? '', ENT_QUOTES, 'UTF-8');
    $avatar     = htmlspecialchars($config['avatar'] ?? '', ENT_QUOTES, 'UTF-8');
    $slug       = htmlspecialchars($config['slug'] ?? '', ENT_QUOTES, 'UTF-8');
    $links      = $config['links'] ?? [];

    $theme          = $config['theme'] ?? [];
    $bgColor        = htmlspecialchars($theme['background_color'] ?? '#780016', ENT_QUOTES, 'UTF-8');
    $bgColorEnd     = htmlspecialchars($theme['background_color_end'] ?? '', ENT_QUOTES, 'UTF-8');
    $textColor      = htmlspecialchars($theme['text_color'] ?? '#FFFFFF', ENT_QUOTES, 'UTF-8');
    $buttonStyle    = htmlspecialchars($theme['button_style'] ?? 'outline', ENT_QUOTES, 'UTF-8');
    $buttonColor    = htmlspecialchars($theme['button_color'] ?? '#FFFFFF', ENT_QUOTES, 'UTF-8');
    $buttonTextColor = htmlspecialchars($theme['button_text_color'] ?? '#FFFFFF', ENT_QUOTES, 'UTF-8');
    $buttonRadius   = htmlspecialchars($theme['button_radius'] ?? '50px', ENT_QUOTES, 'UTF-8');
    $fontFamily     = htmlspecialchars($theme['font_family'] ?? "'Inter', sans-serif", ENT_QUOTES, 'UTF-8');

    $bgCss = $bgColorEnd
        ? "linear-gradient(180deg, {$bgColor} 0%, {$bgColorEnd} 100%)"
        : $bgColor;

    // Detect well-known service icons from links
    $socialIcons = [];
    foreach ($links as $link) {
        $icon = getServiceIcon($link['url'] ?? '');
        if ($icon) {
            $socialIcons[] = [
                'icon' => $icon,
                'url'  => htmlspecialchars($link['url'], ENT_QUOTES, 'UTF-8'),
                'title' => htmlspecialchars($link['title'] ?? '', ENT_QUOTES, 'UTF-8'),
            ];
        }
    }
    ?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?></title>
    <meta name="description" content="<?php echo $bio; ?>">
    <meta property="og:title" content="<?php echo $title; ?>">
    <meta property="og:description" content="<?php echo $bio; ?>">
    <?php if ($avatar): ?>
    <meta property="og:image" content="<?php echo $avatar; ?>">
    <?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="assets/css/page.css">
    <style>
        :root {
            --bg: <?php echo $bgCss; ?>;
            --text-color: <?php echo $textColor; ?>;
            --button-color: <?php echo $buttonColor; ?>;
            --button-text-color: <?php echo $buttonTextColor; ?>;
            --button-radius: <?php echo $buttonRadius; ?>;
            --font-family: <?php echo $fontFamily; ?>;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($avatar): ?>
        <div class="avatar">
            <img src="<?php echo $avatar; ?>" alt="<?php echo $title; ?>">
        </div>
        <?php endif; ?>

        <h1 class="title"><?php echo $title; ?></h1>

        <?php if ($bio): ?>
        <p class="bio"><?php echo $bio; ?></p>
        <?php endif; ?>

        <?php if (!empty($socialIcons)): ?>
        <div class="social-icons">
            <?php foreach ($socialIcons as $si): ?>
            <a href="<?php echo $si['url']; ?>" class="social-icon" target="_blank" rel="noopener noreferrer" title="<?php echo $si['title']; ?>">
                <i class="<?php echo $si['icon']; ?>"></i>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="links">
            <?php foreach ($links as $link):
                $linkTitle = htmlspecialchars($link['title'] ?? '', ENT_QUOTES, 'UTF-8');
                $linkUrl   = htmlspecialchars($link['url'] ?? '#', ENT_QUOTES, 'UTF-8');
            ?>
            <a href="<?php echo $linkUrl; ?>" class="link-button <?php echo $buttonStyle; ?>" target="_blank" rel="noopener noreferrer">
                <?php echo $linkTitle; ?>
            </a>
            <?php endforeach; ?>
        </div>

        <div class="footer">
            <a href="admin.php<?php echo $slug ? '?edit=' . urlencode($slug) : ''; ?>" class="admin-link">Settings</a>
        </div>
    </div>
</body>
</html>
<?php
}

function renderDirectory(array $pages): void {
    ?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>List of Links</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/page.css">
    <style>
        :root {
            --bg: linear-gradient(180deg, #1a1a2e 0%, #0f0f1a 100%);
            --text-color: #FFFFFF;
            --font-family: 'Inter', sans-serif;
        }
    </style>
</head>
<body>
    <div class="container directory-container">
        <h1 class="title" style="margin-bottom: 8px;">List of Links</h1>
        <p class="bio">Choose a page to visit</p>

        <div class="directory-grid">
            <?php foreach ($pages as $page):
                $pSlug   = htmlspecialchars($page['slug'] ?? '', ENT_QUOTES, 'UTF-8');
                $pTitle  = htmlspecialchars($page['title'] ?? $page['slug'] ?? '', ENT_QUOTES, 'UTF-8');
                $pBio    = htmlspecialchars($page['bio'] ?? '', ENT_QUOTES, 'UTF-8');
                $pAvatar = htmlspecialchars($page['avatar'] ?? '', ENT_QUOTES, 'UTF-8');
            ?>
            <a href="index.php?page=<?php echo urlencode($pSlug); ?>" class="directory-card">
                <div class="directory-card-avatar">
                    <?php if ($pAvatar): ?>
                    <img src="<?php echo $pAvatar; ?>" alt="<?php echo $pTitle; ?>">
                    <?php else: ?>
                    <span class="directory-card-initial"><?php echo strtoupper(substr(strip_tags($pTitle), 0, 1)); ?></span>
                    <?php endif; ?>
                </div>
                <div class="directory-card-info">
                    <div class="directory-card-title"><?php echo $pTitle; ?></div>
                    <?php if ($pBio): ?>
                    <div class="directory-card-bio"><?php echo $pBio; ?></div>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>
<?php
}
