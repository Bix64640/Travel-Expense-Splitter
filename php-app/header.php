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
    <a href="index.php" class="navbar-brand">
        &#9992; <?= APP_NAME ?>
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
