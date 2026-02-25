<?php
/**
 * balances.php - Affichage des soldes par membre d'un groupe
 *
 * Pour chaque membre :
 *   solde = total_paye - total_des_parts_dues
 *   > 0 = on lui doit de l'argent
 *   < 0 = il doit de l'argent
 *
 * Inclut aussi la liste complete des depenses avec filtres.
 */
$page_title = 'Soldes du groupe';
require_once __DIR__ . '/header.php';
require_login();

$group_id = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;

// Charger le groupe
$stmt = $pdo->prepare('SELECT * FROM `groups` WHERE id = ?');
$stmt->execute([$group_id]);
$group = $stmt->fetch();

if (!$group) {
    set_flash('error', 'Groupe introuvable.');
    header('Location: dashboard.php');
    exit;
}

if (!is_group_owner_or_member($pdo, $group_id)) {
    set_flash('error', 'Acces refuse.');
    header('Location: dashboard.php');
    exit;
}

// Membres
$stmt = $pdo->prepare('SELECT * FROM group_members WHERE group_id = ? ORDER BY display_name');
$stmt->execute([$group_id]);
$members = $stmt->fetchAll();

// Calcul des soldes
$balances = [];
$total_expenses = 0;

foreach ($members as $m) {
    $mid = (int)$m['id'];

    // Total paye par ce membre
    $s = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE payer_member_id = ? AND group_id = ?');
    $s->execute([$mid, $group_id]);
    $paid = (float)$s->fetchColumn();

    // Total des parts dues
    $s = $pdo->prepare(
        'SELECT COALESCE(SUM(sp.share_amount), 0)
         FROM splits sp
         JOIN expenses e ON e.id = sp.expense_id
         WHERE sp.member_id = ? AND e.group_id = ?'
    );
    $s->execute([$mid, $group_id]);
    $owed = (float)$s->fetchColumn();

    $balance = round($paid - $owed, 2);
    $total_expenses += $paid;

    $balances[] = [
        'member_id' => $mid,
        'name'      => $m['display_name'],
        'paid'      => $paid,
        'owed'      => $owed,
        'balance'   => $balance,
    ];
}

// ----- FILTRES pour la liste des depenses -----
$filter_category = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$filter_date_from = trim($_GET['date_from'] ?? '');
$filter_date_to   = trim($_GET['date_to'] ?? '');
$sort_by          = $_GET['sort'] ?? 'date_desc';

// Charger les categories
$categories = $pdo->query('SELECT * FROM categories ORDER BY name')->fetchAll();

// Construire la requete des depenses
$sql = "SELECT e.*, gm.display_name AS payer_name, c.name AS category_name
        FROM expenses e
        JOIN group_members gm ON gm.id = e.payer_member_id
        JOIN categories c ON c.id = e.category_id
        WHERE e.group_id = ?";
$params = [$group_id];

if ($filter_category > 0) {
    $sql .= " AND e.category_id = ?";
    $params[] = $filter_category;
}
if ($filter_date_from !== '') {
    $sql .= " AND e.expense_date >= ?";
    $params[] = $filter_date_from;
}
if ($filter_date_to !== '') {
    $sql .= " AND e.expense_date <= ?";
    $params[] = $filter_date_to;
}

// Tri
switch ($sort_by) {
    case 'date_asc':    $sql .= " ORDER BY e.expense_date ASC"; break;
    case 'amount_desc': $sql .= " ORDER BY e.amount DESC"; break;
    case 'amount_asc':  $sql .= " ORDER BY e.amount ASC"; break;
    default:            $sql .= " ORDER BY e.expense_date DESC"; $sort_by = 'date_desc'; break;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$expenses = $stmt->fetchAll();
?>

<div class="page-header">
    <h1>Soldes &mdash; <?= h($group['name']) ?></h1>
    <div class="btn-group">
        <a href="group_view.php?id=<?= $group_id ?>" class="btn btn-secondary btn-sm">Retour au groupe</a>
        <a href="settlements.php?group_id=<?= $group_id ?>" class="btn btn-primary btn-sm">Voir les reglements</a>
    </div>
</div>

<!-- Statistiques globales -->
<div class="stats">
    <div class="stat-card">
        <div class="stat-label">Total depenses</div>
        <div class="stat-value"><?= number_format($total_expenses, 2, ',', ' ') ?> &euro;</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Nb membres</div>
        <div class="stat-value"><?= count($members) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Part moyenne / personne</div>
        <div class="stat-value">
            <?= count($members) > 0
                ? number_format($total_expenses / count($members), 2, ',', ' ')
                : '0,00' ?> &euro;
        </div>
    </div>
</div>

<!-- Tableau des soldes -->
<div class="card mb-2">
    <div class="card-header">
        <h3>Solde par membre</h3>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Membre</th>
                    <th class="text-right">Total paye</th>
                    <th class="text-right">Part due</th>
                    <th class="text-right">Solde net</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($balances as $b): ?>
                    <tr>
                        <td><strong><?= h($b['name']) ?></strong></td>
                        <td class="text-right"><?= number_format($b['paid'], 2, ',', ' ') ?> &euro;</td>
                        <td class="text-right"><?= number_format($b['owed'], 2, ',', ' ') ?> &euro;</td>
                        <td class="text-right">
                            <?php if ($b['balance'] > 0): ?>
                                <span style="color:var(--color-success);font-weight:700;">
                                    +<?= number_format($b['balance'], 2, ',', ' ') ?> &euro;
                                </span>
                            <?php elseif ($b['balance'] < 0): ?>
                                <span style="color:var(--color-danger);font-weight:700;">
                                    <?= number_format($b['balance'], 2, ',', ' ') ?> &euro;
                                </span>
                            <?php else: ?>
                                <span class="text-muted">0,00 &euro;</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($b['balance'] > 0): ?>
                                <span class="tag tag-primary">On lui doit</span>
                            <?php elseif ($b['balance'] < 0): ?>
                                <span class="tag" style="background:#fee2e2;color:#991b1b;border-color:#fecaca;">Doit payer</span>
                            <?php else: ?>
                                <span class="tag">Equilibre</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Filtres des depenses -->
<div class="card mb-2">
    <div class="card-header">
        <h3>Liste des depenses (<?= count($expenses) ?>)</h3>
    </div>
    <div class="card-body">
        <form method="get" action="balances.php" class="form-inline">
            <input type="hidden" name="group_id" value="<?= $group_id ?>">
            <div class="form-group">
                <label for="category">Categorie</label>
                <select id="category" name="category" class="form-control">
                    <option value="0">Toutes</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $filter_category == $c['id'] ? 'selected' : '' ?>>
                            <?= h($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="date_from">Du</label>
                <input type="date" id="date_from" name="date_from" class="form-control"
                       value="<?= h($filter_date_from) ?>">
            </div>
            <div class="form-group">
                <label for="date_to">Au</label>
                <input type="date" id="date_to" name="date_to" class="form-control"
                       value="<?= h($filter_date_to) ?>">
            </div>
            <div class="form-group">
                <label for="sort">Tri</label>
                <select id="sort" name="sort" class="form-control">
                    <option value="date_desc" <?= $sort_by === 'date_desc' ? 'selected' : '' ?>>Date (recent)</option>
                    <option value="date_asc" <?= $sort_by === 'date_asc' ? 'selected' : '' ?>>Date (ancien)</option>
                    <option value="amount_desc" <?= $sort_by === 'amount_desc' ? 'selected' : '' ?>>Montant (decroissant)</option>
                    <option value="amount_asc" <?= $sort_by === 'amount_asc' ? 'selected' : '' ?>>Montant (croissant)</option>
                </select>
            </div>
            <button type="submit" class="btn btn-secondary btn-sm">Filtrer</button>
            <a href="balances.php?group_id=<?= $group_id ?>" class="btn btn-secondary btn-sm">Reset</a>
        </form>
    </div>

    <?php if (empty($expenses)): ?>
        <div class="card-body">
            <p class="text-muted">Aucune depense correspond aux filtres.</p>
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
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($expenses as $e): ?>
                        <tr>
                            <td class="text-sm"><?= date('d/m/Y', strtotime($e['expense_date'])) ?></td>
                            <td><?= h($e['description'] ?: '-') ?></td>
                            <td><?= h($e['payer_name']) ?></td>
                            <td><span class="tag"><?= h($e['category_name']) ?></span></td>
                            <td class="text-right"><strong><?= number_format((float)$e['amount'], 2, ',', ' ') ?> &euro;</strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
