<?php
/**
 * groups.php - Creation et edition de groupe
 *
 * ?action=create  -> formulaire de creation
 * ?action=edit&id=X -> formulaire d'edition
 * ?action=delete&id=X -> suppression avec confirmation
 * ?action=add_member&id=X -> ajout d'un membre
 */
$page_title = 'Gestion des groupes';
require_once __DIR__ . '/header.php';
require_login();

$action   = $_GET['action'] ?? '';
$group_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$errors   = [];
$uid      = (int)$user['id'];

// ============================================================
// ACTION : Creer un groupe
// ============================================================
if ($action === 'create') {
    $name = $description = '';
    $is_public = 0;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name        = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $is_public   = isset($_POST['is_public']) ? 1 : 0;

        if ($name === '') $errors[] = 'Le nom du groupe est obligatoire.';

        if (empty($errors)) {
            $stmt = $pdo->prepare(
                'INSERT INTO `groups` (name, description, owner_id, is_public) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$name, $description, $uid, $is_public]);
            $new_id = $pdo->lastInsertId();

            // Ajouter le proprietaire comme membre
            $stmt = $pdo->prepare(
                'INSERT INTO group_members (group_id, user_id, display_name) VALUES (?, ?, ?)'
            );
            $stmt->execute([$new_id, $uid, $user['name']]);

            set_flash('success', 'Groupe cree avec succes !');
            header('Location: group_view.php?id=' . $new_id);
            exit;
        }
    }
    ?>
    <div class="page-header">
        <h1>Creer un groupe</h1>
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
        <div class="card-body">
            <form method="post" action="groups.php?action=create">
                <div class="form-group">
                    <label for="name">Nom du groupe *</label>
                    <input type="text" id="name" name="name" class="form-control"
                           value="<?= h($name) ?>" required>
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" class="form-control"><?= h($description) ?></textarea>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_public" value="1" <?= $is_public ? 'checked' : '' ?>>
                        Groupe public (visible dans le catalogue)
                    </label>
                </div>
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">Creer le groupe</button>
                    <a href="dashboard.php" class="btn btn-secondary">Annuler</a>
                </div>
            </form>
        </div>
    </div>
    <?php

// ============================================================
// ACTION : Editer un groupe
// ============================================================
} elseif ($action === 'edit' && $group_id > 0) {

    // Verifier que l'utilisateur est proprietaire ou admin
    if (!is_group_owner($pdo, $group_id)) {
        set_flash('error', 'Acces refuse : vous ne pouvez modifier que vos propres groupes.');
        header('Location: dashboard.php');
        exit;
    }

    $stmt = $pdo->prepare('SELECT * FROM `groups` WHERE id = ?');
    $stmt->execute([$group_id]);
    $group = $stmt->fetch();

    if (!$group) {
        set_flash('error', 'Groupe introuvable.');
        header('Location: dashboard.php');
        exit;
    }

    $name        = $group['name'];
    $description = $group['description'];
    $is_public   = $group['is_public'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name        = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $is_public   = isset($_POST['is_public']) ? 1 : 0;

        if ($name === '') $errors[] = 'Le nom du groupe est obligatoire.';

        if (empty($errors)) {
            $stmt = $pdo->prepare(
                'UPDATE `groups` SET name = ?, description = ?, is_public = ? WHERE id = ?'
            );
            $stmt->execute([$name, $description, $is_public, $group_id]);

            set_flash('success', 'Groupe mis a jour.');
            header('Location: group_view.php?id=' . $group_id);
            exit;
        }
    }
    ?>
    <div class="page-header">
        <h1>Modifier le groupe</h1>
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
        <div class="card-body">
            <form method="post" action="groups.php?action=edit&id=<?= $group_id ?>">
                <div class="form-group">
                    <label for="name">Nom du groupe *</label>
                    <input type="text" id="name" name="name" class="form-control"
                           value="<?= h($name) ?>" required>
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" class="form-control"><?= h($description) ?></textarea>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_public" value="1" <?= $is_public ? 'checked' : '' ?>>
                        Groupe public
                    </label>
                </div>
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                    <a href="group_view.php?id=<?= $group_id ?>" class="btn btn-secondary">Annuler</a>
                </div>
            </form>
        </div>
    </div>
    <?php

// ============================================================
// ACTION : Supprimer un groupe
// ============================================================
} elseif ($action === 'delete' && $group_id > 0) {

    if (!is_group_owner($pdo, $group_id)) {
        set_flash('error', 'Acces refuse.');
        header('Location: dashboard.php');
        exit;
    }

    $stmt = $pdo->prepare('SELECT * FROM `groups` WHERE id = ?');
    $stmt->execute([$group_id]);
    $group = $stmt->fetch();

    if (!$group) {
        set_flash('error', 'Groupe introuvable.');
        header('Location: dashboard.php');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
        $pdo->prepare('DELETE FROM `groups` WHERE id = ?')->execute([$group_id]);
        set_flash('success', 'Groupe supprime.');
        header('Location: dashboard.php');
        exit;
    }
    ?>
    <div class="page-header">
        <h1>Supprimer le groupe</h1>
    </div>
    <div class="card">
        <div class="card-body">
            <p>Voulez-vous vraiment supprimer le groupe <strong><?= h($group['name']) ?></strong> ?</p>
            <p class="text-sm text-muted mb-2">
                Toutes les depenses, splits, reglements et membres associes seront supprimes.
            </p>
            <form method="post" action="groups.php?action=delete&id=<?= $group_id ?>">
                <div class="btn-group">
                    <button type="submit" name="confirm" value="1" class="btn btn-danger">Confirmer la suppression</button>
                    <a href="group_view.php?id=<?= $group_id ?>" class="btn btn-secondary">Annuler</a>
                </div>
            </form>
        </div>
    </div>
    <?php

// ============================================================
// ACTION : Ajouter un membre
// ============================================================
} elseif ($action === 'add_member' && $group_id > 0) {

    if (!is_group_owner_or_member($pdo, $group_id)) {
        set_flash('error', 'Acces refuse.');
        header('Location: dashboard.php');
        exit;
    }

    $stmt = $pdo->prepare('SELECT * FROM `groups` WHERE id = ?');
    $stmt->execute([$group_id]);
    $group = $stmt->fetch();

    if (!$group) {
        set_flash('error', 'Groupe introuvable.');
        header('Location: dashboard.php');
        exit;
    }

    $display_name = '';
    $member_email = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $display_name = trim($_POST['display_name'] ?? '');
        $member_email = trim($_POST['email'] ?? '');

        if ($display_name === '') $errors[] = "Le nom d'affichage est obligatoire.";

        // Chercher l'utilisateur par email (optionnel)
        $linked_user_id = null;
        if ($member_email !== '') {
            if (!filter_var($member_email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Format d'email invalide.";
            } else {
                $stmt = $pdo->prepare('SELECT id, name FROM users WHERE email = ?');
                $stmt->execute([$member_email]);
                $found_user = $stmt->fetch();
                if ($found_user) {
                    $linked_user_id = (int)$found_user['id'];
                    // Verifier qu'il n'est pas deja membre
                    $stmt = $pdo->prepare('SELECT id FROM group_members WHERE group_id = ? AND user_id = ?');
                    $stmt->execute([$group_id, $linked_user_id]);
                    if ($stmt->fetch()) {
                        $errors[] = 'Cet utilisateur est deja membre du groupe.';
                    }
                }
            }
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare(
                'INSERT INTO group_members (group_id, user_id, display_name) VALUES (?, ?, ?)'
            );
            $stmt->execute([$group_id, $linked_user_id, $display_name]);

            // Notification si l'utilisateur est lie
            if ($linked_user_id) {
                create_notification(
                    $pdo,
                    $linked_user_id,
                    'Vous avez ete ajoute au groupe "' . $group['name'] . '".',
                    'group_view.php?id=' . $group_id
                );
            }

            set_flash('success', 'Membre ajoute au groupe.');
            header('Location: group_view.php?id=' . $group_id);
            exit;
        }
    }
    ?>
    <div class="page-header">
        <h1>Ajouter un membre a &laquo; <?= h($group['name']) ?> &raquo;</h1>
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
        <div class="card-body">
            <form method="post" action="groups.php?action=add_member&id=<?= $group_id ?>">
                <div class="form-group">
                    <label for="display_name">Nom d'affichage dans le groupe *</label>
                    <input type="text" id="display_name" name="display_name" class="form-control"
                           value="<?= h($display_name) ?>" required
                           placeholder="ex : Jean, Marie, Papa...">
                </div>
                <div class="form-group">
                    <label for="email">Email de l'utilisateur (optionnel)</label>
                    <input type="email" id="email" name="email" class="form-control"
                           value="<?= h($member_email) ?>"
                           placeholder="Si l'utilisateur a un compte, il verra le groupe">
                    <p class="text-sm text-muted mt-1">
                        Laissez vide si la personne n'a pas de compte sur le site.
                    </p>
                </div>
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">Ajouter</button>
                    <a href="group_view.php?id=<?= $group_id ?>" class="btn btn-secondary">Annuler</a>
                </div>
            </form>
        </div>
    </div>
    <?php

// ============================================================
// PAS D'ACTION -> Redirection vers le dashboard
// ============================================================
} else {
    header('Location: dashboard.php');
    exit;
}
?>

<?php require_once __DIR__ . '/footer.php'; ?>
