<?php
/**
 * login.php - Connexion
 */
$page_title = 'Connexion';
require_once __DIR__ . '/header.php';

$errors = [];
$email  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '')    $errors[] = "L'email est obligatoire.";
    if ($password === '') $errors[] = 'Le mot de passe est obligatoire.';

    if (empty($errors)) {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user_row = $stmt->fetch();

        if (!$user_row || !password_verify($password, $user_row['password_hash'])) {
            $errors[] = 'Email ou mot de passe incorrect.';
        } elseif (!$user_row['is_active']) {
            $errors[] = 'Votre compte a ete desactive. Contactez un administrateur.';
        } else {
            // Connexion reussie
            $_SESSION['user_id']   = $user_row['id'];
            $_SESSION['user_data'] = [
                'id'        => $user_row['id'],
                'name'      => $user_row['name'],
                'email'     => $user_row['email'],
                'role'      => $user_row['role'],
                'is_active' => $user_row['is_active'],
            ];

            set_flash('success', 'Bienvenue, ' . $user_row['name'] . ' !');
            header('Location: dashboard.php');
            exit;
        }
    }
}
?>

<div class="auth-wrapper">
    <div class="card auth-card">
        <div class="card-header">
            <h2>Se connecter</h2>
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

            <form method="post" action="login.php">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-control"
                           value="<?= h($email) ?>" required>
                </div>
                <div class="form-group">
                    <label for="password">Mot de passe</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%">Se connecter</button>
            </form>

            <p class="text-center mt-2 text-sm text-muted">
                Pas encore de compte ? <a href="signup.php">S'inscrire</a>
            </p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
