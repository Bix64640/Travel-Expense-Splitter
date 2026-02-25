<?php
/**
 * admin_users.php - Administration des utilisateurs
 *
 * Actions : lister, changer le role, activer/desactiver.
 * Accessible uniquement aux admins.
 */
$page_title = 'Admin - Utilisateurs';
require_once __DIR__ . '/header.php';
require_role('admin');

// ---- ACTIONS POST ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $target_id = (int)($_POST['user_id'] ?? 0);
    $action    = $_POST['action'] ?? '';

    if ($target_id > 0 && $target_id !== $uid) { // On ne peut pas se modifier soi-meme
        switch ($action) {
            case 'toggle_active':
                $stmt = $pdo->prepare('SELECT is_active FROM users WHERE id = ?');
                $stmt->execute([$target_id]);
                $current = $stmt->fetchColumn();
                $new_val = $current ? 0 : 1;
                $pdo->prepare('UPDATE users SET is_active = ? WHERE id = ?')
                    ->execute([$new_val, $target_id]);
                set_flash('success', 'Statut mis a jour.');
                break;

            case 'change_role':
                $new_role = $_POST['new_role'] ?? '';
                if (in_array($new_role, ['visitor', 'user', 'admin'])) {
                    $pdo->prepare('UPDATE users SET role = ? WHERE id = ?')
                        ->execute([$new_role, $target_id]);
                    set_flash('success', 'Role mis a jour.');
                }
                break;
        }
    } elseif ($target_id === $uid) {
        set_flash('error', 'Vous ne pouvez pas modifier votre propre compte depuis ce panneau.');
    }

    header('Location: admin_users.php');
    exit;
}

// ---- LISTE ----
$users_list = $pdo->query(
    'SELECT u.*, 
            (SELECT COUNT(*) FROM `groups` WHERE owner_id = u.id) AS group_count
     FROM users u
     ORDER BY u.created_at DESC'
)->fetchAll();
?>

<div class="page-header">
    <h1>Administration - Utilisateurs</h1>
    <div class="btn-group">
        <a href="admin_categories.php" class="btn btn-secondary btn-sm">Categories</a>
        <a href="admin_groups.php" class="btn btn-secondary btn-sm">Groupes</a>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>Tous les utilisateurs (<?= count($users_list) ?>)</h3>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nom</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Actif</th>
                    <th>Groupes</th>
                    <th>Inscrit le</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users_list as $u): ?>
                    <tr>
                        <td><?= $u['id'] ?></td>
                        <td><strong><?= h($u['name']) ?></strong></td>
                        <td class="text-sm"><?= h($u['email']) ?></td>
                        <td>
                            <span class="tag <?= $u['role'] === 'admin' ? 'tag-primary' : '' ?>">
                                <?= h($u['role']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($u['is_active']): ?>
                                <span style="color:var(--color-success);">Oui</span>
                            <?php else: ?>
                                <span style="color:var(--color-danger);">Non</span>
                            <?php endif; ?>
                        </td>
                        <td><?= (int)$u['group_count'] ?></td>
                        <td class="text-sm text-muted"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                        <td>
                            <?php if ((int)$u['id'] !== $uid): ?>
                                <!-- Activer/Desactiver -->
                                <form method="post" action="admin_users.php" style="display:inline;">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <input type="hidden" name="action" value="toggle_active">
                                    <button type="submit" class="btn btn-secondary btn-sm">
                                        <?= $u['is_active'] ? 'Desactiver' : 'Activer' ?>
                                    </button>
                                </form>
                                <!-- Changer de role -->
                                <form method="post" action="admin_users.php" style="display:inline;">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <input type="hidden" name="action" value="change_role">
                                    <select name="new_role" class="form-control"
                                            style="display:inline;width:auto;padding:.2rem .4rem;font-size:.8rem;"
                                            onchange="this.form.submit()">
                                        <option value="visitor" <?= $u['role'] === 'visitor' ? 'selected' : '' ?>>visitor</option>
                                        <option value="user" <?= $u['role'] === 'user' ? 'selected' : '' ?>>user</option>
                                        <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>admin</option>
                                    </select>
                                </form>
                            <?php else: ?>
                                <span class="text-sm text-muted">(vous)</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
