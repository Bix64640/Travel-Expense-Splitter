<?php
/**
 * catalog.php - Liste des groupes publics (accessible a tous)
 */
$page_title = 'Catalogue des groupes';
require_once __DIR__ . '/header.php';

// Filtre par nom
$search = trim($_GET['q'] ?? '');

$sql = "SELECT g.*, u.name AS owner_name,
               (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) AS member_count
        FROM `groups` g
        JOIN users u ON u.id = g.owner_id
        WHERE g.is_public = 1";
$params = [];

if ($search !== '') {
    $sql .= " AND g.name LIKE ?";
    $params[] = '%' . $search . '%';
}
$sql .= " ORDER BY g.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$groups = $stmt->fetchAll();
?>

<div class="page-header">
    <h1>Groupes publics</h1>
</div>

<form method="get" action="catalog.php" class="form-inline mb-2">
    <div class="form-group">
        <input type="text" name="q" class="form-control" placeholder="Rechercher un groupe..."
               value="<?= h($search) ?>">
    </div>
    <button type="submit" class="btn btn-secondary">Rechercher</button>
    <?php if ($search !== ''): ?>
        <a href="catalog.php" class="btn btn-secondary">Effacer</a>
    <?php endif; ?>
</form>

<?php if (empty($groups)): ?>
    <div class="empty">
        <div class="empty-icon">&#128269;</div>
        <p>Aucun groupe public trouve.</p>
    </div>
<?php else: ?>
    <div style="display:flex;flex-wrap:wrap;gap:1rem;">
        <?php foreach ($groups as $g): ?>
            <div class="card" style="flex:1;min-width:18rem;max-width:24rem;">
                <div class="card-body">
                    <h3 style="margin-bottom:.35rem;">
                        <a href="group_view.php?id=<?= $g['id'] ?>"><?= h($g['name']) ?></a>
                    </h3>
                    <p class="text-sm text-muted mb-1">
                        <?= h($g['description'] ?: 'Pas de description.') ?>
                    </p>
                    <p class="text-sm">
                        <span class="tag"><?= (int)$g['member_count'] ?> membre(s)</span>
                        <span class="text-muted" style="margin-left:.5rem;">
                            par <?= h($g['owner_name']) ?>
                        </span>
                    </p>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>
