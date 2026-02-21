<?php
/**
 * List of Links (LoL) - Page Renderer
 *
 * Renders a configured link page from its JSON configuration file.
 * Usage: index.php?page=slug  OR  /slug (with .htaccess rewrite)
 */

// Determine the requested page slug
$slug = $_GET['page'] ?? null;

// Try PATH_INFO for clean URLs
if (!$slug && !empty($_SERVER['PATH_INFO'])) {
    $slug = trim($_SERVER['PATH_INFO'], '/');
}

// No page requested — redirect to admin
if (!$slug) {
    header('Location: admin.php');
    exit;
}

// Sanitize slug: lowercase alphanumeric and hyphens only
$slug = preg_replace('/[^a-z0-9\-]/', '', strtolower($slug));

// Load page configuration
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
        .error a { color: #64ffda; }
    </style>
</head>
<body>
    <div class="error">
        <h1>404</h1>
        <p>This page doesn't exist. <a href="admin.php">Go to admin</a></p>
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

// Extract configuration values with defaults
$title = htmlspecialchars($config['title'] ?? 'My Links', ENT_QUOTES, 'UTF-8');
$bio = htmlspecialchars($config['bio'] ?? '', ENT_QUOTES, 'UTF-8');
$avatar = htmlspecialchars($config['avatar'] ?? '', ENT_QUOTES, 'UTF-8');
$links = $config['links'] ?? [];

// Theme settings
$theme = $config['theme'] ?? [];
$bgColor = htmlspecialchars($theme['background_color'] ?? '#780016', ENT_QUOTES, 'UTF-8');
$bgColorEnd = htmlspecialchars($theme['background_color_end'] ?? '', ENT_QUOTES, 'UTF-8');
$textColor = htmlspecialchars($theme['text_color'] ?? '#FFFFFF', ENT_QUOTES, 'UTF-8');
$buttonStyle = htmlspecialchars($theme['button_style'] ?? 'outline', ENT_QUOTES, 'UTF-8');
$buttonColor = htmlspecialchars($theme['button_color'] ?? '#FFFFFF', ENT_QUOTES, 'UTF-8');
$buttonTextColor = htmlspecialchars($theme['button_text_color'] ?? '#FFFFFF', ENT_QUOTES, 'UTF-8');
$buttonRadius = htmlspecialchars($theme['button_radius'] ?? '50px', ENT_QUOTES, 'UTF-8');
$fontFamily = htmlspecialchars($theme['font_family'] ?? "'Inter', sans-serif", ENT_QUOTES, 'UTF-8');

// Build background CSS
$bgCss = $bgColorEnd ? "linear-gradient(180deg, {$bgColor} 0%, {$bgColorEnd} 100%)" : $bgColor;
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

        <div class="links">
            <?php foreach ($links as $link):
                $linkTitle = htmlspecialchars($link['title'] ?? '', ENT_QUOTES, 'UTF-8');
                $linkUrl = htmlspecialchars($link['url'] ?? '#', ENT_QUOTES, 'UTF-8');
            ?>
            <a href="<?php echo $linkUrl; ?>" class="link-button <?php echo $buttonStyle; ?>" target="_blank" rel="noopener noreferrer">
                <?php echo $linkTitle; ?>
            </a>
            <?php endforeach; ?>
        </div>

        <div class="footer">
            <a href="admin.php" class="admin-link">List of Links</a>
        </div>
    </div>
</body>
</html>
