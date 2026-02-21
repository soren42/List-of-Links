<?php
/**
 * List of Links (LoL) - API Backend
 *
 * Handles CRUD operations for page configurations.
 *
 * Endpoints:
 *   GET  ?action=list                  — List all page slugs
 *   GET  ?action=get&slug=<slug>       — Get a page config
 *   POST ?action=save    (JSON body)   — Create or update a page
 *   POST ?action=delete  (JSON body)   — Delete a page
 *   POST ?action=upload  (multipart)   — Upload an avatar image
 */

header('Content-Type: application/json');

$pagesDir = __DIR__ . '/pages';
$uploadsDir = __DIR__ . '/uploads';

// Ensure directories exist
if (!is_dir($pagesDir)) {
    mkdir($pagesDir, 0755, true);
}
if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'list':
        handleList();
        break;
    case 'get':
        handleGet();
        break;
    case 'save':
        handleSave();
        break;
    case 'delete':
        handleDelete();
        break;
    case 'upload':
        handleUpload();
        break;
    default:
        jsonResponse(['error' => 'Invalid action'], 400);
}

/**
 * List all configured pages.
 */
function handleList() {
    global $pagesDir;
    $pages = [];
    $files = glob($pagesDir . '/*.json');

    foreach ($files as $file) {
        $data = json_decode(file_get_contents($file), true);
        if ($data) {
            $pages[] = [
                'slug'  => $data['slug'] ?? pathinfo($file, PATHINFO_FILENAME),
                'title' => $data['title'] ?? '',
                'bio'   => $data['bio'] ?? '',
                'avatar' => $data['avatar'] ?? '',
            ];
        }
    }

    jsonResponse(['pages' => $pages]);
}

/**
 * Get a single page configuration.
 */
function handleGet() {
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

    jsonResponse($data);
}

/**
 * Save (create or update) a page configuration.
 */
function handleSave() {
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

    $file = $pagesDir . '/' . $slug . '.json';
    $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    if (file_put_contents($file, $json . "\n") === false) {
        jsonResponse(['error' => 'Failed to save configuration'], 500);
        return;
    }

    jsonResponse(['success' => true, 'slug' => $slug]);
}

/**
 * Delete a page configuration.
 */
function handleDelete() {
    global $pagesDir;

    $input = json_decode(file_get_contents('php://input'), true);
    $slug = sanitizeSlug($input['slug'] ?? '');

    if (!$slug) {
        jsonResponse(['error' => 'Invalid or missing slug'], 400);
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

/**
 * Upload an avatar image.
 */
function handleUpload() {
    global $uploadsDir;

    if (!isset($_FILES['avatar'])) {
        jsonResponse(['error' => 'No file uploaded'], 400);
        return;
    }

    $file = $_FILES['avatar'];

    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes)) {
        jsonResponse(['error' => 'Invalid file type. Allowed: JPG, PNG, GIF, WebP'], 400);
        return;
    }

    // Validate file size (max 2MB)
    if ($file['size'] > 2 * 1024 * 1024) {
        jsonResponse(['error' => 'File too large. Maximum size: 2MB'], 400);
        return;
    }

    // Generate a safe filename
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

// --- Helper Functions ---

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
    // Allow common safe font declarations
    $font = trim($font);
    if (preg_match('/^[a-zA-Z\s,\'\"\-]+$/', $font)) return $font;
    return "'Inter', sans-serif";
}

function jsonResponse(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data);
    exit;
}
