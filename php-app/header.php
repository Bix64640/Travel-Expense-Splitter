<?php
/**
 * header.php - En-tete HTML commune a toutes les pages
 *
 * Avant d'inclure ce fichier, definir $page_title (optionnel).
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

$page_title = isset($page_title) ? $page_title . ' | ' . APP_NAME : APP_NAME;
$user       = current_user();
$notif_count = is_logged_in() ? unread_notification_count($pdo) : 0;

// Charger les parametres de theme (texte du header et logo)
$theme_settings = [
    'text_color' => 'default',
    'logo' => 'plane'
];

// Preferer la lecture depuis la base si disponible (table settings)
try {
    $stmt = $pdo->prepare("SELECT k, v FROM settings WHERE k IN ('theme_text_color','theme_logo')");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    if (!empty($rows)) {
        if (isset($rows['theme_text_color'])) $theme_settings['text_color'] = $rows['theme_text_color'];
        if (isset($rows['theme_logo'])) $theme_settings['logo'] = $rows['theme_logo'];
    } else {
        // fallback to PHP file
        $settings_file = __DIR__ . '/theme_settings.php';
        if (file_exists($settings_file)) {
            $data = @include $settings_file;
            if (is_array($data)) {
                $theme_settings = array_merge($theme_settings, $data);
            }
        }
    }
} catch (Exception $e) {
    // si la table n'existe pas ou erreur, on utilise le fichier
    $settings_file = __DIR__ . '/theme_settings.php';
    if (file_exists($settings_file)) {
        $data = @include $settings_file;
        if (is_array($data)) {
            $theme_settings = array_merge($theme_settings, $data);
        }
    }
}

// Map text color choice to CSS color
$color_map = [
    'default' => '',
    'red'     => '#dc2626',
    'blue'    => '#2563eb',
    'orange'  => '#f97316',
    'pink'    => '#ec4899'
];
$header_text_color_css = '';
if (!empty($theme_settings['text_color']) && $theme_settings['text_color'] !== 'default') {
    $c = $color_map[$theme_settings['text_color']] ?? '';
    if ($c) $header_text_color_css = 'color: ' . $c . ';';
}

// Map logo choice to emoji (keeps it simple and portable)
$logo_map = [
    'plane' => '&#9992;', // ✈
    'bike'  => '&#128690;', // 🚲
    'car'   => '&#128663;', // 🚗
    'boat'  => '&#128674;'  // 🚢
];
$logo_html = $logo_map[$theme_settings['logo']] ?? $logo_map['plane'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($page_title) ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<nav class="navbar">
    <a href="index.php" class="navbar-brand" style="<?= $header_text_color_css ?>">
        <?= $logo_html ?> <?= APP_NAME ?>
    </a>
    <ul class="navbar-nav">
        <li><a href="catalog.php">Catalogue</a></li>
        <?php if (is_logged_in()): ?>
            <li><a href="dashboard.php">Tableau de bord</a></li>
            <li>
                <a href="notifications.php">
                    Notifications
                    <?php if ($notif_count > 0): ?>
                        <span class="badge"><?= $notif_count ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <?php if (is_admin()): ?>
                <li><a href="admin_users.php">Admin</a></li>
                <li><a href="admin_settings.php">Theme</a></li>
            <?php endif; ?>
            <li><a href="logout.php">Deconnexion (<?= h($user['name']) ?>)</a></li>
        <?php else: ?>
            <li><a href="login.php">Connexion</a></li>
            <li><a href="signup.php" class="btn btn-primary btn-sm">Inscription</a></li>
        <?php endif; ?>
    </ul>
</nav>

<div class="container">
    <?php
    // Afficher les messages flash
    $flash_success = get_flash('success');
    $flash_error   = get_flash('error');
    if ($flash_success): ?>
        <div class="alert alert-success"><?= h($flash_success) ?></div>
    <?php endif; ?>
    <?php if ($flash_error): ?>
        <div class="alert alert-error"><?= h($flash_error) ?></div>
    <?php endif; ?>
