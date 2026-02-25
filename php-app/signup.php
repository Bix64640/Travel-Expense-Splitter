<?php
/**
 * signup.php - Inscription d'un nouvel utilisateur
 */
$page_title = 'Inscription';
require_once __DIR__ . '/header.php';

$errors = [];
$name = $email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';

    // Validation
    if ($name === '')     $errors[] = 'Le nom est obligatoire.';
    if ($email === '')    $errors[] = "L'email est obligatoire.";
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Format d'email invalide.";
    if (strlen($password) < 6) $errors[] = 'Le mot de passe doit faire au moins 6 caracteres.';
    if ($password !== $confirm)  $errors[] = 'Les mots de passe ne correspondent pas.';

    // Verifier unicite email
    if (empty($errors)) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'Cet email est deja utilise.';
        }
    }

    // Creer le compte
    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)');
        $stmt->execute([$name, $email, $hash, 'user']);

        set_flash('success', 'Compte cree avec succes ! Connectez-vous.');
        header('Location: login.php');
        exit;
    }
}
?>

<div class="auth-wrapper">
    <div class="card auth-card">
        <div class="card-header">
            <h2>Creer un compte</h2>
        </div>
        <div class="card-body">

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <ul style="margin:0;padding-left:1.2rem;">
                        <?php foreach ($errors as $err): ?>
                            <li><?= h($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" action="signup.php">
                <div class="form-group">
                    <label for="name">Nom</label>
                    <input type="text" id="name" name="name" class="form-control"
                           value="<?= h($name) ?>" required>
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-control"
                           value="<?= h($email) ?>" required>
                </div>
                <div class="form-group">
                    <label for="password">Mot de passe (min. 6 caracteres)</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="password_confirm">Confirmer le mot de passe</label>
                    <input type="password" id="password_confirm" name="password_confirm" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%">S'inscrire</button>
            </form>

            <p class="text-center mt-2 text-sm text-muted">
                Deja un compte ? <a href="login.php">Se connecter</a>
            </p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
