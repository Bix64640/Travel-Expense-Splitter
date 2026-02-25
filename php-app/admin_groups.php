<?php
/**
 * admin_groups.php - Administration des groupes
 *
 * Liste tous les groupes, possibilite de supprimer.
 * Accessible uniquement aux admins.
 */
$page_title = 'Admin - Groupes';
require_once __DIR__ . '/header.php';
require_role('admin');

$action   = $_GET['action'] ?? '';
$group_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ============================================================
// ACTION : Supprimer un groupe
// ============================================================
if ($action === 'delete' && $group_id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM `groups` WHERE id = ?');
    $stmt->execute([$group_id]);
    $group = $stmt->fetch();

    if (!$group) {
        set_flash('error', 'Groupe introuvable.');
        header('Location: admin_groups.php');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
        $pdo->prepare('DELETE FROM `groups` WHERE id = ?')->execute([$group_id]);
        set_flash('success', 'Groupe "' . $group['name'] . '" supprime.');
        header('Location: admin_groups.php');
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
                Proprietaire : <?= h($group['owner_id']) ?>
                &mdash; Toutes les donnees associees seront supprimees.
            </p>
            <form method="post" action="admin_groups.php?action=delete&id=<?= $group_id ?>">
                <div class="btn-group">
                    <button type="submit" name="confirm" value="1" class="btn btn-danger">Confirmer</button>
                    <a href="admin_groups.php" class="btn btn-secondary">Annuler</a>
                </div>
            </form>
        </div>
    </div>
    <?php
    require_once __DIR__ . '/footer.php';
    exit;
}

// ============================================================
// LISTE DES GROUPES
// ============================================================
$groups_list = $pdo->query(
    "SELECT g.*, u.name AS owner_name,
            (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) AS member_count,
            (SELECT COUNT(*) FROM expenses WHERE group_id = g.id) AS expense_count,
            (SELECT COALESCE(SUM(amount),0) FROM expenses WHERE group_id = g.id) AS total_amount
     FROM `groups` g
     JOIN users u ON u.id = g.owner_id
     ORDER BY g.created_at DESC"
)->fetchAll();
?>

<div class="page-header">
    <h1>Administration - Groupes</h1>
    <div class="btn-group">
        <a href="admin_users.php" class="btn btn-secondary btn-sm">Utilisateurs</a>
        <a href="admin_categories.php" class="btn btn-secondary btn-sm">Categories</a>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>Tous les groupes (<?= count($groups_list) ?>)</h3>
    </div>
    <?php if (empty($groups_list)): ?>
        <div class="card-body">
            <p class="text-muted">Aucun groupe.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nom</th>
                        <th>Proprietaire</th>
                        <th>Membres</th>
                        <th>Depenses</th>
                        <th>Total</th>
                        <th>Public</th>
                        <th>Cree le</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($groups_list as $g): ?>
                        <tr>
                            <td><?= $g['id'] ?></td>
                            <td>
                                <a href="group_view.php?id=<?= $g['id'] ?>">
                                    <strong><?= h($g['name']) ?></strong>
                                </a>
                            </td>
                            <td class="text-sm"><?= h($g['owner_name']) ?></td>
                            <td><?= (int)$g['member_count'] ?></td>
                            <td><?= (int)$g['expense_count'] ?></td>
                            <td><?= number_format((float)$g['total_amount'], 2, ',', ' ') ?> &euro;</td>
                            <td><?= $g['is_public'] ? 'Oui' : 'Non' ?></td>
                            <td class="text-sm text-muted"><?= date('d/m/Y', strtotime($g['created_at'])) ?></td>
                            <td>
                                <div class="btn-group">
                                    <a href="group_view.php?id=<?= $g['id'] ?>" class="btn btn-secondary btn-sm">Voir</a>
                                    <a href="groups.php?action=edit&id=<?= $g['id'] ?>" class="btn btn-secondary btn-sm">Modifier</a>
                                    <a href="admin_groups.php?action=delete&id=<?= $g['id'] ?>" class="btn btn-danger btn-sm">Supprimer</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
