<?php
/**
 * auth.php - Fonctions d'authentification et de controle d'acces
 *
 * A inclure apres db.php dans les pages qui necessitent une session.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// -----------------------------------------------------------------
// Verifier si l'utilisateur est connecte
// -----------------------------------------------------------------
function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

// -----------------------------------------------------------------
// Obtenir l'utilisateur connecte (tableau associatif) ou null
// -----------------------------------------------------------------
function current_user(): ?array
{
    if (!is_logged_in()) {
        return null;
    }
    // On stocke en session pour eviter des requetes repetees
    if (!isset($_SESSION['user_data'])) {
        return null;
    }
    return $_SESSION['user_data'];
}

// -----------------------------------------------------------------
// Recharger les donnees utilisateur depuis la BDD (apres un changement)
// -----------------------------------------------------------------
function refresh_user(PDO $pdo): void
{
    if (!is_logged_in()) return;
    $stmt = $pdo->prepare('SELECT id, name, email, role, is_active FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if ($user) {
        $_SESSION['user_data'] = $user;
    }
}

// -----------------------------------------------------------------
// Exiger que l'utilisateur soit connecte (sinon redirection)
// -----------------------------------------------------------------
function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

// -----------------------------------------------------------------
// Exiger un role minimum
// -----------------------------------------------------------------
function require_role(string $role): void
{
    require_login();
    $user = current_user();
    if (!$user) {
        header('Location: login.php');
        exit;
    }

    // Hierarchie : admin > user > visitor
    $hierarchy = ['visitor' => 0, 'user' => 1, 'admin' => 2];
    $user_level    = $hierarchy[$user['role']] ?? 0;
    $required_level = $hierarchy[$role] ?? 0;

    if ($user_level < $required_level) {
        $_SESSION['flash_error'] = 'Acces refuse : droits insuffisants.';
        header('Location: dashboard.php');
        exit;
    }
}

// -----------------------------------------------------------------
// Verifier si l'utilisateur est admin
// -----------------------------------------------------------------
function is_admin(): bool
{
    $user = current_user();
    return $user && $user['role'] === 'admin';
}

// -----------------------------------------------------------------
// Verifier que l'utilisateur est proprietaire ou membre d'un groupe
// -----------------------------------------------------------------
function is_group_owner_or_member(PDO $pdo, int $group_id): bool
{
    $user = current_user();
    if (!$user) return false;
    if (is_admin()) return true;

    // Proprietaire ?
    $stmt = $pdo->prepare('SELECT id FROM `groups` WHERE id = ? AND owner_id = ?');
    $stmt->execute([$group_id, $user['id']]);
    if ($stmt->fetch()) return true;

    // Membre ?
    $stmt = $pdo->prepare('SELECT id FROM group_members WHERE group_id = ? AND user_id = ?');
    $stmt->execute([$group_id, $user['id']]);
    return (bool)$stmt->fetch();
}

// -----------------------------------------------------------------
// Verifier que l'utilisateur est proprietaire du groupe
// -----------------------------------------------------------------
function is_group_owner(PDO $pdo, int $group_id): bool
{
    $user = current_user();
    if (!$user) return false;
    if (is_admin()) return true;

    $stmt = $pdo->prepare('SELECT id FROM `groups` WHERE id = ? AND owner_id = ?');
    $stmt->execute([$group_id, $user['id']]);
    return (bool)$stmt->fetch();
}

// -----------------------------------------------------------------
// Nombre de notifications non lues
// -----------------------------------------------------------------
function unread_notification_count(PDO $pdo): int
{
    $user = current_user();
    if (!$user) return 0;
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$user['id']]);
    return (int)$stmt->fetchColumn();
}

// -----------------------------------------------------------------
// Creer une notification
// -----------------------------------------------------------------
function create_notification(PDO $pdo, int $user_id, string $message, string $link = ''): void
{
    $stmt = $pdo->prepare('INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)');
    $stmt->execute([$user_id, $message, $link]);
}

// -----------------------------------------------------------------
// Notifier tous les membres d'un groupe (sauf un)
// -----------------------------------------------------------------
function notify_group_members(PDO $pdo, int $group_id, string $message, string $link = '', int $except_user_id = 0): void
{
    $stmt = $pdo->prepare('SELECT user_id FROM group_members WHERE group_id = ? AND user_id IS NOT NULL');
    $stmt->execute([$group_id]);
    while ($row = $stmt->fetch()) {
        if ((int)$row['user_id'] !== $except_user_id) {
            create_notification($pdo, (int)$row['user_id'], $message, $link);
        }
    }
}

// -----------------------------------------------------------------
// Message flash (erreur / succes)
// -----------------------------------------------------------------
function set_flash(string $type, string $message): void
{
    $_SESSION['flash_' . $type] = $message;
}

function get_flash(string $type): string
{
    if (isset($_SESSION['flash_' . $type])) {
        $msg = $_SESSION['flash_' . $type];
        unset($_SESSION['flash_' . $type]);
        return $msg;
    }
    return '';
}

// -----------------------------------------------------------------
// Echapper pour le HTML (protection XSS)
// -----------------------------------------------------------------
function h(string $str): string
{
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
