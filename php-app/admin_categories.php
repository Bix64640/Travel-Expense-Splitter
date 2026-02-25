<?php
/**
 * admin_categories.php - Gestion des categories de depenses
 *
 * Ajout, edition, suppression avec confirmation.
 * Accessible uniquement aux admins.
 */
$page_title = 'Admin - Categories';
require_once __DIR__ . '/header.php';
require_role('admin');

$errors = [];
$action = $_GET['action'] ?? '';
$cat_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ============================================================
// ACTION : Ajouter une categorie
// ============================================================
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        $errors[] = 'Le nom de la categorie est obligatoire.';
    } else {
        // Unicite
        $stmt = $pdo->prepare('SELECT id FROM categories WHERE name = ?');
        $stmt->execute([$name]);
        if ($stmt->fetch()) {
            $errors[] = 'Cette categorie existe deja.';
        } else {
            $pdo->prepare('INSERT INTO categories (name) VALUES (?)')->execute([$name]);
            set_flash('success', 'Categorie ajoutee.');
            header('Location: admin_categories.php');
            exit;
        }
    }
}

// ============================================================
// ACTION : Editer une categorie
// ============================================================
if ($action === 'edit' && $cat_id > 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        $errors[] = 'Le nom est obligatoire.';
    } else {
        // Unicite (exclure l'actuel)
        $stmt = $pdo->prepare('SELECT id FROM categories WHERE name = ? AND id != ?');
        $stmt->execute([$name, $cat_id]);
        if ($stmt->fetch()) {
            $errors[] = 'Ce nom existe deja.';
        } else {
            $pdo->prepare('UPDATE categories SET name = ? WHERE id = ?')->execute([$name, $cat_id]);
            set_flash('success', 'Categorie mise a jour.');
            header('Location: admin_categories.php');
            exit;
        }
    }
}

// ============================================================
// ACTION : Supprimer une categorie
// ============================================================
if ($action === 'delete' && $cat_id > 0) {
    // Verifier si des depenses utilisent cette categorie
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM expenses WHERE category_id = ?');
    $stmt->execute([$cat_id]);
    $usage = (int)$stmt->fetchColumn();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
        if ($usage > 0) {
            set_flash('error', 'Impossible de supprimer : ' . $usage . ' depense(s) utilisent cette categorie.');
        } else {
            $pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$cat_id]);
            set_flash('success', 'Categorie supprimee.');
        }
        header('Location: admin_categories.php');
        exit;
    }

    // Afficher la confirmation
    $stmt = $pdo->prepare('SELECT * FROM categories WHERE id = ?');
    $stmt->execute([$cat_id]);
    $cat = $stmt->fetch();

    if (!$cat) {
        set_flash('error', 'Categorie introuvable.');
        header('Location: admin_categories.php');
        exit;
    }
    ?>

    <div class="page-header">
        <h1>Supprimer la categorie</h1>
    </div>
    <div class="card">
        <div class="card-body">
            <p>Voulez-vous vraiment supprimer la categorie <strong><?= h($cat['name']) ?></strong> ?</p>
            <?php if ($usage > 0): ?>
                <div class="alert alert-warning">
                    Attention : <?= $usage ?> depense(s) utilisent cette categorie.
                    La suppression sera bloquee.
                </div>
            <?php endif; ?>
            <form method="post" action="admin_categories.php?action=delete&id=<?= $cat_id ?>">
                <div class="btn-group">
                    <button type="submit" name="confirm" value="1" class="btn btn-danger">Confirmer</button>
                    <a href="admin_categories.php" class="btn btn-secondary">Annuler</a>
                </div>
            </form>
        </div>
    </div>
    <?php
    require_once __DIR__ . '/footer.php';
    exit;
}

// ============================================================
// LISTE DES CATEGORIES
// ============================================================
$categories = $pdo->query(
    'SELECT c.*, (SELECT COUNT(*) FROM expenses WHERE category_id = c.id) AS usage_count
     FROM categories c
     ORDER BY c.name'
)->fetchAll();

// Si on edite, charger la categorie
$edit_cat = null;
if ($action === 'edit' && $cat_id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM categories WHERE id = ?');
    $stmt->execute([$cat_id]);
    $edit_cat = $stmt->fetch();
}
?>

<div class="page-header">
    <h1>Administration - Categories</h1>
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

<!-- Formulaire d'ajout ou d'edition -->
<div class="card mb-2">
    <div class="card-header">
        <h3><?= $edit_cat ? 'Modifier la categorie' : 'Ajouter une categorie' ?></h3>
    </div>
    <div class="card-body">
        <form method="post"
              action="admin_categories.php?action=<?= $edit_cat ? 'edit&id=' . $edit_cat['id'] : 'add' ?>"
              class="form-inline">
            <div class="form-group">
                <label for="name">Nom</label>
                <input type="text" id="name" name="name" class="form-control"
                       value="<?= h($edit_cat['name'] ?? '') ?>" required
                       placeholder="ex : Hebergement">
            </div>
            <button type="submit" class="btn btn-primary btn-sm">
                <?= $edit_cat ? 'Enregistrer' : 'Ajouter' ?>
            </button>
            <?php if ($edit_cat): ?>
                <a href="admin_categories.php" class="btn btn-secondary btn-sm">Annuler</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Liste -->
<div class="card">
    <div class="card-header">
        <h3>Categories existantes (<?= count($categories) ?>)</h3>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nom</th>
                    <th>Nb depenses</th>
                    <th>Cree le</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $c): ?>
                    <tr>
                        <td><?= $c['id'] ?></td>
                        <td><strong><?= h($c['name']) ?></strong></td>
                        <td><?= (int)$c['usage_count'] ?></td>
                        <td class="text-sm text-muted"><?= date('d/m/Y', strtotime($c['created_at'])) ?></td>
                        <td>
                            <div class="btn-group">
                                <a href="admin_categories.php?action=edit&id=<?= $c['id'] ?>"
                                   class="btn btn-secondary btn-sm">Modifier</a>
                                <a href="admin_categories.php?action=delete&id=<?= $c['id'] ?>"
                                   class="btn btn-danger btn-sm">Supprimer</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
