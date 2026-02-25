<?php
/**
 * dashboard.php - Tableau de bord de l'utilisateur connecte
 *
 * Affiche : stats rapides, liste de ses groupes, dernieres notifications.
 */
$page_title = 'Tableau de bord';
require_once __DIR__ . '/header.php';
require_login();

$uid = (int)$user['id'];

// Groupes dont l'utilisateur est proprietaire
$stmt = $pdo->prepare(
    "SELECT g.*,
            (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) AS member_count,
            (SELECT COALESCE(SUM(amount),0) FROM expenses WHERE group_id = g.id) AS total_expenses
     FROM `groups` g
     WHERE g.owner_id = ?
     ORDER BY g.created_at DESC"
);
$stmt->execute([$uid]);
$owned_groups = $stmt->fetchAll();

// Groupes dont l'utilisateur est membre (pas proprietaire)
$stmt = $pdo->prepare(
    "SELECT g.*,
            (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) AS member_count,
            (SELECT COALESCE(SUM(amount),0) FROM expenses WHERE group_id = g.id) AS total_expenses,
            u.name AS owner_name
     FROM `groups` g
     JOIN group_members gm ON gm.group_id = g.id
     JOIN users u ON u.id = g.owner_id
     WHERE gm.user_id = ? AND g.owner_id != ?
     ORDER BY g.created_at DESC"
);
$stmt->execute([$uid, $uid]);
$member_groups = $stmt->fetchAll();

// Statistiques
$total_groups   = count($owned_groups) + count($member_groups);
$total_expenses_sum = 0;
foreach (array_merge($owned_groups, $member_groups) as $g) {
    $total_expenses_sum += (float)$g['total_expenses'];
}

// Dernieres notifications
$stmt = $pdo->prepare(
    'SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5'
);
$stmt->execute([$uid]);
$recent_notifs = $stmt->fetchAll();
?>

<div class="page-header">
    <h1>Bonjour, <?= h($user['name']) ?></h1>
    <a href="groups.php?action=create" class="btn btn-primary">+ Nouveau groupe</a>
</div>

<!-- Stats -->
<div class="stats">
    <div class="stat-card">
        <div class="stat-label">Mes groupes</div>
        <div class="stat-value"><?= $total_groups ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Total des depenses</div>
        <div class="stat-value"><?= number_format($total_expenses_sum, 2, ',', ' ') ?> &euro;</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Notifications non lues</div>
        <div class="stat-value"><?= $notif_count ?></div>
    </div>
</div>

<!-- Groupes possedes -->
<div class="card mb-2">
    <div class="card-header">
        <h3>Mes groupes (proprietaire)</h3>
    </div>
    <?php if (empty($owned_groups)): ?>
        <div class="card-body">
            <p class="text-muted">Vous n'avez pas encore cree de groupe.
               <a href="groups.php?action=create">Creer un groupe</a>.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Membres</th>
                        <th>Depenses totales</th>
                        <th>Public</th>
                        <th>Cree le</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($owned_groups as $g): ?>
                        <tr>
                            <td><a href="group_view.php?id=<?= $g['id'] ?>"><?= h($g['name']) ?></a></td>
                            <td><?= (int)$g['member_count'] ?></td>
                            <td><?= number_format((float)$g['total_expenses'], 2, ',', ' ') ?> &euro;</td>
                            <td><?= $g['is_public'] ? 'Oui' : 'Non' ?></td>
                            <td class="text-sm text-muted"><?= date('d/m/Y', strtotime($g['created_at'])) ?></td>
                            <td>
                                <a href="group_view.php?id=<?= $g['id'] ?>" class="btn btn-secondary btn-sm">Voir</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Groupes en tant que membre -->
<?php if (!empty($member_groups)): ?>
<div class="card mb-2">
    <div class="card-header">
        <h3>Groupes ou je suis membre</h3>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Proprietaire</th>
                    <th>Membres</th>
                    <th>Depenses totales</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($member_groups as $g): ?>
                    <tr>
                        <td><a href="group_view.php?id=<?= $g['id'] ?>"><?= h($g['name']) ?></a></td>
                        <td><?= h($g['owner_name']) ?></td>
                        <td><?= (int)$g['member_count'] ?></td>
                        <td><?= number_format((float)$g['total_expenses'], 2, ',', ' ') ?> &euro;</td>
                        <td>
                            <a href="group_view.php?id=<?= $g['id'] ?>" class="btn btn-secondary btn-sm">Voir</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Dernieres notifications -->
<?php if (!empty($recent_notifs)): ?>
<div class="card">
    <div class="card-header">
        <h3>Dernieres notifications</h3>
        <a href="notifications.php" class="btn btn-secondary btn-sm">Tout voir</a>
    </div>
    <div>
        <?php foreach ($recent_notifs as $n): ?>
            <div class="notif-item <?= $n['is_read'] ? '' : 'unread' ?>">
                <?php if (!$n['is_read']): ?>
                    <div class="notif-dot"></div>
                <?php endif; ?>
                <div class="notif-content">
                    <div><?= h($n['message']) ?></div>
                    <div class="notif-date"><?= date('d/m/Y H:i', strtotime($n['created_at'])) ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>
