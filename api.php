<?php
/**
 * List of Links (LoL) - API Backend
 *
 * Handles CRUD operations for page configurations and authentication.
 *
 * Endpoints:
 *   GET  ?action=list                  — List all page slugs
 *   GET  ?action=get&slug=<slug>       — Get a page config
 *   POST ?action=save    (JSON body)   — Create or update a page
 *   POST ?action=delete  (JSON body)   — Delete a page
 *   POST ?action=upload  (multipart)   — Upload an avatar image
 *   GET  ?action=check-auth            — Check current auth status
 *   POST ?action=setup   (JSON body)   — Initial system password setup
 *   POST ?action=login   (JSON body)   — Authenticate (system or user)
 *   POST ?action=logout                — Destroy session
 */

session_start();
header('Content-Type: application/json');

$pagesDir   = __DIR__ . '/pages';
$uploadsDir = __DIR__ . '/uploads';
$configFile = __DIR__ . '/config.json';

// Ensure directories exist
if (!is_dir($pagesDir))   mkdir($pagesDir, 0755, true);
if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0755, true);

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'list':       handleList(); break;
    case 'get':        handleGet(); break;
    case 'save':       requireAuth(); handleSave(); break;
    case 'delete':     requireAuth(); handleDelete(); break;
    case 'upload':     requireAuth(); handleUpload(); break;
    case 'check-auth': handleCheckAuth(); break;
    case 'setup':      handleSetup(); break;
    case 'login':      handleLogin(); break;
    case 'logout':     handleLogout(); break;
    case 'needs-setup': handleNeedsSetup(); break;
    case 'import-linktree': requireAuth(); handleImportLinktree(); break;
    default:           jsonResponse(['error' => 'Invalid action'], 400);
}

// ─── Authentication Helpers ──────────────────────────────────────────

/**
 * Hash a password using the project's scheme:
 *   md5( md5(password) + md5(password) + salt )
 */
function hashPassword(string $password, string $salt): string {
    $md5 = md5($password);
    return md5($md5 . $md5 . $salt);
}

/**
 * Load the system config (salt + system password hash).
 */
function loadConfig(): ?array {
    global $configFile;
    if (!file_exists($configFile)) return null;
    $data = json_decode(file_get_contents($configFile), true);
    return is_array($data) ? $data : null;
}

/**
 * Save the system config.
 */
function saveConfig(array $data): bool {
    global $configFile;
    return file_put_contents(
        $configFile,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
    ) !== false;
}

/**
 * Require that the user is authenticated. Returns 401 if not.
 */
function requireAuth(): void {
    if (empty($_SESSION['lol_auth'])) {
        jsonResponse(['error' => 'Authentication required'], 401);
    }
}

/**
 * Check whether the current session can edit a given slug.
 */
function canEditSlug(string $slug): bool {
    if (empty($_SESSION['lol_auth'])) return false;
    $auth = $_SESSION['lol_auth'];
    if ($auth['type'] === 'system') return true;
    return $auth['type'] === 'user' && $auth['slug'] === $slug;
}

// ─── Auth Endpoints ──────────────────────────────────────────────────

function handleNeedsSetup(): void {
    $config = loadConfig();
    jsonResponse(['needs_setup' => ($config === null)]);
}

function handleSetup(): void {
    $config = loadConfig();
    if ($config !== null) {
        jsonResponse(['error' => 'System is already configured'], 400);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $password = $input['password'] ?? '';

    if (strlen($password) < 4) {
        jsonResponse(['error' => 'Password must be at least 4 characters'], 400);
        return;
    }

    // Generate a random salt
    $salt = bin2hex(random_bytes(16));
    $hash = hashPassword($password, $salt);

    $configData = [
        'salt'                 => $salt,
        'system_password_hash' => $hash,
    ];

    if (!saveConfig($configData)) {
        jsonResponse(['error' => 'Failed to save configuration'], 500);
        return;
    }

    // Auto-login as system admin after setup
    $_SESSION['lol_auth'] = ['type' => 'system'];

    jsonResponse(['success' => true]);
}

function handleLogin(): void {
    $config = loadConfig();
    if ($config === null) {
        jsonResponse(['error' => 'System not configured. Run setup first.'], 400);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $password = $input['password'] ?? '';
    $salt = $config['salt'];
    $hash = hashPassword($password, $salt);

    // Check system password
    if ($hash === $config['system_password_hash']) {
        $_SESSION['lol_auth'] = ['type' => 'system'];
        jsonResponse(['success' => true, 'auth_type' => 'system']);
        return;
    }

    // Check per-user passwords across all pages
    global $pagesDir;
    $files = glob($pagesDir . '/*.json');
    foreach ($files as $file) {
        $pageData = json_decode(file_get_contents($file), true);
        if (!$pageData) continue;
        $userHash = $pageData['user_password_hash'] ?? '';
        if ($userHash && $hash === $userHash) {
            $slug = $pageData['slug'] ?? pathinfo($file, PATHINFO_FILENAME);
            $_SESSION['lol_auth'] = ['type' => 'user', 'slug' => $slug];
            jsonResponse(['success' => true, 'auth_type' => 'user', 'slug' => $slug]);
            return;
        }
    }

    jsonResponse(['error' => 'Invalid password'], 401);
}

function handleLogout(): void {
    unset($_SESSION['lol_auth']);
    session_destroy();
    jsonResponse(['success' => true]);
}

function handleCheckAuth(): void {
    if (!empty($_SESSION['lol_auth'])) {
        jsonResponse([
            'authenticated' => true,
            'auth_type'     => $_SESSION['lol_auth']['type'],
            'slug'          => $_SESSION['lol_auth']['slug'] ?? null,
        ]);
    } else {
        $config = loadConfig();
        jsonResponse([
            'authenticated' => false,
            'needs_setup'   => ($config === null),
        ]);
    }
}

// ─── CRUD Endpoints ──────────────────────────────────────────────────

function handleList(): void {
    global $pagesDir;
    $pages = [];
    $files = glob($pagesDir . '/*.json');

    $auth = $_SESSION['lol_auth'] ?? null;

    foreach ($files as $file) {
        $data = json_decode(file_get_contents($file), true);
        if (!$data) continue;

        $slug = $data['slug'] ?? pathinfo($file, PATHINFO_FILENAME);

        // If user-level auth, only list their page
        if ($auth && $auth['type'] === 'user' && $auth['slug'] !== $slug) {
            continue;
        }

        $pages[] = [
            'slug'   => $slug,
            'title'  => $data['title'] ?? '',
            'bio'    => $data['bio'] ?? '',
            'avatar' => $data['avatar'] ?? '',
        ];
    }

    jsonResponse(['pages' => $pages]);
}

function handleGet(): void {
    global $pagesDir;
    $slug = sanitizeSlug($_GET['slug'] ?? '');

    if (!$slug) {
        jsonResponse(['error' => 'Missing slug parameter'], 400);
        return;
    }

    $file = $pagesDir . '/' . $slug . '.json';
    if (!file_exists($file)) {
        jsonResponse(['error' => 'Page not found'], 404);
        return;
    }

    $data = json_decode(file_get_contents($file), true);
    if (!$data) {
        jsonResponse(['error' => 'Error reading page configuration'], 500);
        return;
    }

    // Never send the password hash to the client
    unset($data['user_password_hash']);

    // Add a flag indicating whether a user password is set
    $raw = json_decode(file_get_contents($file), true);
    $data['has_user_password'] = !empty($raw['user_password_hash']);

    jsonResponse($data);
}

function handleSave(): void {
    global $pagesDir;

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        jsonResponse(['error' => 'Invalid JSON body'], 400);
        return;
    }

    $slug = sanitizeSlug($input['slug'] ?? '');
    if (!$slug) {
        jsonResponse(['error' => 'Invalid or missing slug'], 400);
        return;
    }

    // Check permissions
    if (!canEditSlug($slug)) {
        jsonResponse(['error' => 'You do not have permission to edit this page'], 403);
        return;
    }

    // Load existing page data to preserve password hash if not changing
    $existingFile = $pagesDir . '/' . $slug . '.json';
    $existingData = null;
    if (file_exists($existingFile)) {
        $existingData = json_decode(file_get_contents($existingFile), true);
    }

    // Build the config object with validation
    $config = [
        'slug'   => $slug,
        'title'  => trim($input['title'] ?? ''),
        'bio'    => trim($input['bio'] ?? ''),
        'avatar' => trim($input['avatar'] ?? ''),
        'theme'  => [
            'background_color'     => sanitizeColor($input['theme']['background_color'] ?? '#780016'),
            'background_color_end' => sanitizeColor($input['theme']['background_color_end'] ?? ''),
            'text_color'           => sanitizeColor($input['theme']['text_color'] ?? '#FFFFFF'),
            'button_style'         => sanitizeButtonStyle($input['theme']['button_style'] ?? 'outline'),
            'button_color'         => sanitizeColor($input['theme']['button_color'] ?? '#FFFFFF'),
            'button_text_color'    => sanitizeColor($input['theme']['button_text_color'] ?? '#FFFFFF'),
            'button_radius'        => sanitizeRadius($input['theme']['button_radius'] ?? '50px'),
            'font_family'          => sanitizeFontFamily($input['theme']['font_family'] ?? "'Inter', sans-serif"),
        ],
        'links' => [],
    ];

    // Process links
    if (isset($input['links']) && is_array($input['links'])) {
        foreach ($input['links'] as $link) {
            if (!empty($link['title']) && !empty($link['url'])) {
                $config['links'][] = [
                    'title' => trim($link['title']),
                    'url'   => trim($link['url']),
                ];
            }
        }
    }

    // Handle user password
    $sysConfig = loadConfig();
    $salt = $sysConfig['salt'] ?? '';

    if (!empty($input['user_password'])) {
        // New password provided — hash and store it
        $config['user_password_hash'] = hashPassword($input['user_password'], $salt);
    } elseif ($existingData && !empty($existingData['user_password_hash'])) {
        // Preserve existing password hash
        $config['user_password_hash'] = $existingData['user_password_hash'];
    } else {
        $config['user_password_hash'] = '';
    }

    $file = $pagesDir . '/' . $slug . '.json';
    $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    if (file_put_contents($file, $json . "\n") === false) {
        jsonResponse(['error' => 'Failed to save configuration'], 500);
        return;
    }

    jsonResponse(['success' => true, 'slug' => $slug]);
}

function handleDelete(): void {
    global $pagesDir;

    $input = json_decode(file_get_contents('php://input'), true);
    $slug = sanitizeSlug($input['slug'] ?? '');

    if (!$slug) {
        jsonResponse(['error' => 'Invalid or missing slug'], 400);
        return;
    }

    if (!canEditSlug($slug)) {
        jsonResponse(['error' => 'You do not have permission to delete this page'], 403);
        return;
    }

    $file = $pagesDir . '/' . $slug . '.json';
    if (!file_exists($file)) {
        jsonResponse(['error' => 'Page not found'], 404);
        return;
    }

    if (!unlink($file)) {
        jsonResponse(['error' => 'Failed to delete page'], 500);
        return;
    }

    jsonResponse(['success' => true]);
}

function handleUpload(): void {
    global $uploadsDir;

    if (!isset($_FILES['avatar'])) {
        jsonResponse(['error' => 'No file uploaded'], 400);
        return;
    }

    $file = $_FILES['avatar'];

    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes)) {
        jsonResponse(['error' => 'Invalid file type. Allowed: JPG, PNG, GIF, WebP'], 400);
        return;
    }

    if ($file['size'] > 2 * 1024 * 1024) {
        jsonResponse(['error' => 'File too large. Maximum size: 2MB'], 400);
        return;
    }

    $ext = match ($mimeType) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
        default      => 'jpg',
    };
    $filename = bin2hex(random_bytes(8)) . '.' . $ext;
    $destination = $uploadsDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        jsonResponse(['error' => 'Failed to save uploaded file'], 500);
        return;
    }

    jsonResponse(['success' => true, 'url' => 'uploads/' . $filename]);
}

// ─── Linktree Import ─────────────────────────────────────────────────

function handleImportLinktree(): void {
    $input = json_decode(file_get_contents('php://input'), true);
    $url = trim($input['url'] ?? '');

    if (!$url) {
        jsonResponse(['error' => 'Please provide a Linktree URL'], 400);
        return;
    }

    // Accept flexible input: @username, linktr.ee/user, full URL
    if (preg_match('/^@?([a-zA-Z0-9_.]+)$/', $url, $m)) {
        $url = 'https://linktr.ee/' . $m[1];
    } elseif (strpos($url, 'linktr.ee/') !== false && strpos($url, '://') === false) {
        $url = 'https://' . $url;
    }

    $parsed = parse_url($url);
    if (!$parsed || !isset($parsed['host']) || !preg_match('/linktr\.ee$/i', $parsed['host'])) {
        jsonResponse(['error' => 'Please provide a valid Linktree URL (e.g. https://linktr.ee/username)'], 400);
        return;
    }

    // Extract username for slug suggestion
    $suggestedSlug = '';
    if (!empty($parsed['path'])) {
        $suggestedSlug = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($parsed['path'], '/')));
    }

    $data = scrapeLinktree($url);

    if (!$data) {
        jsonResponse(['error' => 'Failed to fetch or parse the Linktree page. It may be private or the URL may be incorrect.'], 400);
        return;
    }

    $data['suggested_slug'] = $suggestedSlug;
    jsonResponse(['success' => true, 'data' => $data]);
}

function scrapeLinktree(string $url): ?array {
    $context = stream_context_create([
        'http' => [
            'method'          => 'GET',
            'header'          => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36\r\nAccept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8\r\n",
            'timeout'         => 15,
            'follow_location' => true,
            'max_redirects'   => 5,
        ],
        'ssl' => [
            'verify_peer' => false,
        ],
    ]);

    $html = @file_get_contents($url, false, $context);
    if ($html === false) return null;

    // Try __NEXT_DATA__ first (most reliable)
    $data = parseLinktreeNextData($html);
    if ($data) return $data;

    // Fallback to meta tags + HTML parsing
    return parseLinktreeMetaTags($html);
}

function parseLinktreeNextData(string $html): ?array {
    if (!preg_match('/<script\s+id="__NEXT_DATA__"\s+type="application\/json">\s*({.+?})\s*<\/script>/s', $html, $m)) {
        return null;
    }

    $nextData = json_decode($m[1], true);
    if (!$nextData) return null;

    $pageProps = $nextData['props']['pageProps'] ?? null;
    if (!$pageProps) return null;

    $account = $pageProps['account'] ?? [];
    $links   = $pageProps['links'] ?? [];
    $theme   = $pageProps['theme'] ?? [];

    $result = [
        'title'  => $account['profileTitle'] ?? ('@' . ($account['username'] ?? '')),
        'bio'    => $account['description'] ?? '',
        'avatar' => $account['profilePictureUrl'] ?? '',
        'links'  => [],
        'theme'  => extractLinktreeTheme($theme),
    ];

    foreach ($links as $link) {
        $title = $link['title'] ?? '';
        $url   = $link['url'] ?? '';
        if ($title || $url) {
            $result['links'][] = [
                'title' => $title,
                'url'   => $url,
            ];
        }
    }

    return $result;
}

function parseLinktreeMetaTags(string $html): ?array {
    $result = [
        'title'  => '',
        'bio'    => '',
        'avatar' => '',
        'links'  => [],
        'theme'  => [
            'background_color'     => '#43e660',
            'background_color_end' => '',
            'text_color'           => '#000000',
            'button_style'         => 'filled',
            'button_color'         => '#ffffff',
            'button_text_color'    => '#000000',
            'button_radius'        => '50px',
            'font_family'          => "'Inter', sans-serif",
        ],
    ];

    if (preg_match('/<meta\s+property="og:title"\s+content="([^"]*)"/i', $html, $m)) {
        $result['title'] = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
    }
    if (preg_match('/<meta\s+property="og:description"\s+content="([^"]*)"/i', $html, $m)) {
        $result['bio'] = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
    }
    if (preg_match('/<meta\s+property="og:image"\s+content="([^"]*)"/i', $html, $m)) {
        $result['avatar'] = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
    }

    // Try extracting links from data attributes or anchor tags
    if (preg_match_all('/<a[^>]+href="(https?:\/\/[^"]+)"[^>]*>.*?<p[^>]*>([^<]*)<\/p>/si', $html, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $linkUrl   = html_entity_decode(trim($match[1]), ENT_QUOTES, 'UTF-8');
            $linkTitle = html_entity_decode(trim($match[2]), ENT_QUOTES, 'UTF-8');
            // Skip internal Linktree links
            if (strpos($linkUrl, 'linktr.ee') !== false) continue;
            if ($linkTitle || $linkUrl) {
                $result['links'][] = ['title' => $linkTitle, 'url' => $linkUrl];
            }
        }
    }

    if ($result['title'] || !empty($result['links'])) {
        return $result;
    }
    return null;
}

function extractLinktreeTheme(array $theme): array {
    $result = [
        'background_color'     => '#43e660',
        'background_color_end' => '',
        'text_color'           => '#000000',
        'button_style'         => 'filled',
        'button_color'         => '#ffffff',
        'button_text_color'    => '#000000',
        'button_radius'        => '50px',
        'font_family'          => "'Inter', sans-serif",
    ];

    // Background
    $bg = $theme['background'] ?? [];
    if (!empty($bg['color'])) {
        $result['background_color'] = $bg['color'];
    }
    if (($bg['style'] ?? '') === 'GRADIENT' && !empty($bg['gradient'])) {
        $gradient = $bg['gradient'];
        if (is_array($gradient)) {
            $colors = $gradient['colors'] ?? [];
            if (count($colors) >= 2) {
                $result['background_color'] = $colors[0];
                $result['background_color_end'] = $colors[count($colors) - 1];
            }
        }
    }

    // Text color from luminance
    $luminance = $theme['luminance'] ?? 'LIGHT';
    $result['text_color'] = ($luminance === 'LIGHT') ? '#000000' : '#FFFFFF';

    // Button style
    $buttonStyle = $theme['buttonStyle'] ?? [];
    $type = strtolower($buttonStyle['type'] ?? 'FILL');
    if (strpos($type, 'outline') !== false) {
        $result['button_style'] = 'outline';
    } elseif (strpos($type, 'shadow') !== false || strpos($type, 'soft') !== false) {
        $result['button_style'] = 'shadow';
    } else {
        $result['button_style'] = 'filled';
    }

    if (!empty($buttonStyle['color'])) {
        $result['button_color'] = $buttonStyle['color'];
    }
    if (!empty($buttonStyle['fontColor'])) {
        $result['button_text_color'] = $buttonStyle['fontColor'];
    }

    // Button radius
    $borderRadius = strtolower($buttonStyle['borderRadius'] ?? 'round');
    if (strpos($borderRadius, 'round') !== false) {
        $result['button_radius'] = '50px';
    } elseif (strpos($borderRadius, 'slight') !== false) {
        $result['button_radius'] = '12px';
    } elseif (strpos($borderRadius, 'square') !== false) {
        $result['button_radius'] = '0px';
    } else {
        $result['button_radius'] = '50px';
    }

    return $result;
}

// ─── Sanitization Helpers ────────────────────────────────────────────

function sanitizeSlug(string $slug): string {
    return preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($slug)));
}

function sanitizeColor(string $color): string {
    $color = trim($color);
    if ($color === '') return '';
    if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $color)) return $color;
    return '';
}

function sanitizeButtonStyle(string $style): string {
    $allowed = ['outline', 'filled', 'shadow'];
    return in_array($style, $allowed) ? $style : 'outline';
}

function sanitizeRadius(string $radius): string {
    if (preg_match('/^\d+(px|rem|em|%)$/', $radius)) return $radius;
    return '50px';
}

function sanitizeFontFamily(string $font): string {
    $font = trim($font);
    if (preg_match('/^[a-zA-Z\s,\'\"\-]+$/', $font)) return $font;
    return "'Inter', sans-serif";
}

function jsonResponse(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data);
    exit;
}
