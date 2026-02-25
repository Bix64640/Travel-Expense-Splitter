<?php
/**
 * group_view.php - Detail d'un groupe
 *
 * Accessible a :
 *   - Tout le monde si le groupe est public (lecture seule)
 *   - Proprietaire / membres / admin (actions)
 */
$page_title = 'Detail du groupe';
require_once __DIR__ . '/header.php';

$group_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Charger le groupe
$stmt = $pdo->prepare(
    "SELECT g.*, u.name AS owner_name
     FROM `groups` g
     JOIN users u ON u.id = g.owner_id
     WHERE g.id = ?"
);
$stmt->execute([$group_id]);
$group = $stmt->fetch();

if (!$group) {
    set_flash('error', 'Groupe introuvable.');
    header('Location: dashboard.php');
    exit;
}

// Controle d'acces : public OU membre/proprietaire/admin
$is_member = is_logged_in() && is_group_owner_or_member($pdo, $group_id);
$can_view  = $group['is_public'] || $is_member;

if (!$can_view) {
    set_flash('error', "Ce groupe est prive. Vous n'avez pas acces.");
    header('Location: catalog.php');
    exit;
}

// Membres du groupe
$stmt = $pdo->prepare(
    "SELECT gm.*, u.email AS user_email
     FROM group_members gm
     LEFT JOIN users u ON u.id = gm.user_id
     WHERE gm.group_id = ?
     ORDER BY gm.created_at ASC"
);
$stmt->execute([$group_id]);
$members = $stmt->fetchAll();

// Dernieres depenses (10)
$stmt = $pdo->prepare(
    "SELECT e.*, gm.display_name AS payer_name, c.name AS category_name
     FROM expenses e
     JOIN group_members gm ON gm.id = e.payer_member_id
     JOIN categories c ON c.id = e.category_id
     WHERE e.group_id = ?
     ORDER BY e.expense_date DESC, e.created_at DESC
     LIMIT 10"
);
$stmt->execute([$group_id]);
$recent_expenses = $stmt->fetchAll();

// Total depenses
$stmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM expenses WHERE group_id = ?');
$stmt->execute([$group_id]);
$total_expenses = (float)$stmt->fetchColumn();

// Calcul rapide des soldes pour chaque membre
// Solde = total paye - total du (parts)
$balances = [];
foreach ($members as $m) {
    $mid = (int)$m['id'];
    // Total paye
    $s = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM expenses WHERE payer_member_id = ?');
    $s->execute([$mid]);
    $paid = (float)$s->fetchColumn();

    // Total du (parts)
    $s = $pdo->prepare('SELECT COALESCE(SUM(share_amount),0) FROM splits WHERE member_id = ?');
    $s->execute([$mid]);
    $owed = (float)$s->fetchColumn();

    $balances[$mid] = [
        'name'    => $m['display_name'],
        'paid'    => $paid,
        'owed'    => $owed,
        'balance' => round($paid - $owed, 2),
    ];
}
?>

<div class="page-header">
    <h1><?= h($group['name']) ?></h1>
    <?php if ($is_member): ?>
        <div class="btn-group">
            <a href="expense_add.php?group_id=<?= $group_id ?>" class="btn btn-primary btn-sm">+ Depense</a>
            <a href="groups.php?action=add_member&id=<?= $group_id ?>" class="btn btn-secondary btn-sm">+ Membre</a>
            <a href="balances.php?group_id=<?= $group_id ?>" class="btn btn-secondary btn-sm">Soldes</a>
            <a href="settlements.php?group_id=<?= $group_id ?>" class="btn btn-secondary btn-sm">Reglements</a>
            <?php if (is_group_owner($pdo, $group_id)): ?>
                <a href="groups.php?action=edit&id=<?= $group_id ?>" class="btn btn-secondary btn-sm">Modifier</a>
                <a href="groups.php?action=delete&id=<?= $group_id ?>" class="btn btn-danger btn-sm">Supprimer</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($group['description']): ?>
    <p class="text-muted mb-2"><?= h($group['description']) ?></p>
<?php endif; ?>

<p class="text-sm text-muted mb-2">
    Cree par <strong><?= h($group['owner_name']) ?></strong>
    le <?= date('d/m/Y', strtotime($group['created_at'])) ?>
    &mdash; <?= $group['is_public'] ? 'Public' : 'Prive' ?>
</p>

<!-- Stats -->
<div class="stats">
    <div class="stat-card">
        <div class="stat-label">Membres</div>
        <div class="stat-value"><?= count($members) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Total depenses</div>
        <div class="stat-value"><?= number_format($total_expenses, 2, ',', ' ') ?> &euro;</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Depense / personne (moy.)</div>
        <div class="stat-value">
            <?= count($members) > 0
                ? number_format($total_expenses / count($members), 2, ',', ' ')
                : '0,00' ?> &euro;
        </div>
    </div>
</div>

<!-- Membres -->
<div class="card mb-2">
    <div class="card-header">
        <h3>Membres (<?= count($members) ?>)</h3>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Compte lie</th>
                    <th>Paye</th>
                    <th>Part due</th>
                    <th>Solde</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($members as $m):
                    $mid = (int)$m['id'];
                    $b = $balances[$mid] ?? ['paid'=>0,'owed'=>0,'balance'=>0];
                ?>
                    <tr>
                        <td><strong><?= h($m['display_name']) ?></strong></td>
                        <td class="text-sm text-muted"><?= $m['user_email'] ? h($m['user_email']) : '-' ?></td>
                        <td><?= number_format($b['paid'], 2, ',', ' ') ?> &euro;</td>
                        <td><?= number_format($b['owed'], 2, ',', ' ') ?> &euro;</td>
                        <td>
                            <?php if ($b['balance'] > 0): ?>
                                <span style="color:var(--color-success);font-weight:600;">
                                    +<?= number_format($b['balance'], 2, ',', ' ') ?> &euro;
                                </span>
                            <?php elseif ($b['balance'] < 0): ?>
                                <span style="color:var(--color-danger);font-weight:600;">
                                    <?= number_format($b['balance'], 2, ',', ' ') ?> &euro;
                                </span>
                            <?php else: ?>
                                <span class="text-muted">0,00 &euro;</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Dernieres depenses -->
<div class="card">
    <div class="card-header">
        <h3>Dernieres depenses</h3>
        <?php if ($is_member): ?>
            <a href="expense_add.php?group_id=<?= $group_id ?>" class="btn btn-primary btn-sm">+ Ajouter</a>
        <?php endif; ?>
    </div>
    <?php if (empty($recent_expenses)): ?>
        <div class="card-body">
            <p class="text-muted">Aucune depense enregistree.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Payeur</th>
                        <th>Categorie</th>
                        <th class="text-right">Montant</th>
                        <?php if ($is_member): ?><th></th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_expenses as $e): ?>
                        <tr>
                            <td class="text-sm"><?= date('d/m/Y', strtotime($e['expense_date'])) ?></td>
                            <td><?= h($e['description'] ?: '-') ?></td>
                            <td><?= h($e['payer_name']) ?></td>
                            <td><span class="tag"><?= h($e['category_name']) ?></span></td>
                            <td class="text-right"><strong><?= number_format((float)$e['amount'], 2, ',', ' ') ?> &euro;</strong></td>
                            <?php if ($is_member): ?>
                                <td>
                                    <div class="btn-group">
                                        <a href="expense_edit.php?id=<?= $e['id'] ?>" class="btn btn-secondary btn-sm">Modifier</a>
                                        <a href="expense_delete.php?id=<?= $e['id'] ?>" class="btn btn-danger btn-sm">Suppr.</a>
                                    </div>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
