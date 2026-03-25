<?php
/**
 * create_admin.php
 * Utilitaire de creation d'un compte administrateur.
 *
 * Attention : pour des raisons de securite ce script ne fonctionne que si
 * la constante ADMIN_CREDENTIALS_VISIBLE est active (mode dev).
 * Supprimez ce fichier apres usage.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

if (!defined('ADMIN_CREDENTIALS_VISIBLE') || !ADMIN_CREDENTIALS_VISIBLE) {
    http_response_code(403);
    echo "Access forbidden. Enable ADMIN_CREDENTIALS_VISIBLE in config.php to use this tool.";
    exit;
}

// Tentative automatique de creation si l'admin n'existe pas (pratique pour dev)
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? 'Admin');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
} else {
    // GET : utiliser les valeurs par defaut depuis config
    $name = 'Admin';
    $email = ADMIN_EMAIL;
    $password = ADMIN_PASS;
}

// Validation simple
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $msg = 'Email invalide.';
} elseif (strlen($password) < 6) {
    $msg = 'Le mot de passe doit faire au moins 6 caracteres.';
} else {
    // verifier existence
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $msg = 'Un utilisateur avec cet email existe deja. Vous pouvez vous connecter.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role, is_active) VALUES (?, ?, ?, ?, 1)');
        $stmt->execute([$name, $email, $hash, 'admin']);
        $msg = 'Compte administrateur cree avec succes. ID = ' . $pdo->lastInsertId();
    }
}

?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Creer un administrateur - Tool</title>
    <link rel="stylesheet" href="style.css">
    <style>body{padding:1rem}.card{max-width:520px;margin:1rem auto}</style>
</head>
<body>
    <div class="card">
        <div class="card-header"><h2>Creer un compte administrateur (dev)</h2></div>
        <div class="card-body">
            <?php if ($msg): ?>
                <div class="alert alert-info"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>
            <form method="post">
                <div class="form-group">
                    <label for="name">Nom</label>
                    <input id="name" name="name" class="form-control" value="Admin">
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" class="form-control" value="<?= htmlspecialchars(ADMIN_EMAIL) ?>">
                </div>
                <div class="form-group">
                    <label for="password">Mot de passe</label>
                    <input id="password" name="password" type="password" class="form-control" value="<?= htmlspecialchars(ADMIN_PASS) ?>">
                </div>
                <div class="btn-group">
                    <button class="btn btn-primary" type="submit">Creer l'admin</button>
                    <a href="login.php" class="btn btn-secondary">Retour</a>
                </div>
            </form>
            <p class="text-sm text-muted mt-2">Supprimez ce fichier apres usage pour des raisons de securite.</p>
        </div>
    </div>
</body>
</html>
