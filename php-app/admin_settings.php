<?php
/**
 * admin_settings.php - Page pour modifier le style du header (texte + logo)
 */
$page_title = 'Admin - Theme';
require_once __DIR__ . '/header.php';
require_role('admin');

$settings_file = __DIR__ . '/theme_settings.php';
$data = [
    'text_color' => 'default',
    'logo' => 'plane'
];

// Tenter de charger depuis la base de donnees si la table existe
try {
    // Creer la table settings si elle n'existe pas (safe)
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS settings (
            k VARCHAR(191) PRIMARY KEY,
            v TEXT,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $stmt = $pdo->prepare("SELECT k,v FROM settings WHERE k IN ('theme_text_color','theme_logo')");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    if (!empty($rows)) {
        if (isset($rows['theme_text_color'])) $data['text_color'] = $rows['theme_text_color'];
        if (isset($rows['theme_logo'])) $data['logo'] = $rows['theme_logo'];
    } else {
        // fallback to php file
        if (file_exists($settings_file)) {
            $d = @include $settings_file;
            if (is_array($d)) $data = array_merge($data, $d);
        }
    }
} catch (Exception $e) {
    // si erreur, fallback fichier
    if (file_exists($settings_file)) {
        $d = @include $settings_file;
        if (is_array($d)) $data = array_merge($data, $d);
    }
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $text_color = $_POST['text_color'] ?? 'default';
    $logo = $_POST['logo'] ?? 'plane';
    $allowed_colors = ['default','red','blue','orange','pink'];
    $allowed_logos  = ['plane','bike','car','boat'];
    if (!in_array($text_color, $allowed_colors)) $errors[] = 'Couleur invalide.';
    if (!in_array($logo, $allowed_logos)) $errors[] = 'Logo invalide.';
    if (empty($errors)) {
        // Sauvegarder en base (upsert)
        try {
            $up = $pdo->prepare("INSERT INTO settings (k,v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)");
            $up->execute(['theme_text_color', $text_color]);
            $up->execute(['theme_logo', $logo]);
            set_flash('success', 'Parametres sauvegardes.');
            header('Location: admin_settings.php');
            exit;
        } catch (Exception $e) {
            // En cas de probleme DB, on tombe back sur l'ecriture fichier
            $new = ['text_color' => $text_color, 'logo' => $logo];
            $php = "<?php\nreturn ".var_export($new, true).";\n";
            file_put_contents($settings_file, $php);
            set_flash('success', 'Parametres sauvegardes (fichier).');
            header('Location: admin_settings.php');
            exit;
        }
    }
}

?>
<div class="page-header">
    <h1>Theme - Header</h1>
    <div class="btn-group">
        <a href="admin_users.php" class="btn btn-secondary btn-sm">Utilisateurs</a>
        <a href="admin_groups.php" class="btn btn-secondary btn-sm">Groupes</a>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <ul style="margin:0;padding-left:1.2rem;">
            <?php foreach ($errors as $err): ?>
                <li><?= h($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><h3>Personnaliser le header</h3></div>
    <div class="card-body">
        <form method="post">
            <div class="form-group">
                <label>Couleur du texte :</label>
                <select name="text_color" class="form-control">
                    <option value="default" <?= $data['text_color']==='default' ? 'selected' : '' ?>>Couleur d'origine</option>
                    <option value="red" <?= $data['text_color']==='red' ? 'selected' : '' ?>>Rouge</option>
                    <option value="blue" <?= $data['text_color']==='blue' ? 'selected' : '' ?>>Bleu</option>
                    <option value="orange" <?= $data['text_color']==='orange' ? 'selected' : '' ?>>Orange</option>
                    <option value="pink" <?= $data['text_color']==='pink' ? 'selected' : '' ?>>Rose</option>
                </select>
            </div>

            <div class="form-group">
                <label>Logo :</label>
                <div class="form-row">
                    <label style="margin-right:1rem;"><input type="radio" name="logo" value="plane" <?= $data['logo']==='plane' ? 'checked' : '' ?>> &#9992; Avion</label>
                    <label style="margin-right:1rem;"><input type="radio" name="logo" value="bike" <?= $data['logo']==='bike' ? 'checked' : '' ?>> &#128690; Vélo</label>
                    <label style="margin-right:1rem;"><input type="radio" name="logo" value="car" <?= $data['logo']==='car' ? 'checked' : '' ?>> &#128663; Voiture</label>
                    <label><input type="radio" name="logo" value="boat" <?= $data['logo']==='boat' ? 'checked' : '' ?>> &#128674; Bateau</label>
                </div>
            </div>

            <div class="btn-group">
                <button type="submit" class="btn btn-primary">Enregistrer</button>
                <a href="admin.php" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
