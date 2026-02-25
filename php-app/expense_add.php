<?php
/**
 * expense_add.php - Ajouter une depense a un groupe
 *
 * Le split est par defaut egal entre tous les membres du groupe.
 */
$page_title = 'Ajouter une depense';
require_once __DIR__ . '/header.php';
require_login();

$group_id = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;

// Verifier le groupe et les droits
$stmt = $pdo->prepare('SELECT * FROM `groups` WHERE id = ?');
$stmt->execute([$group_id]);
$group = $stmt->fetch();

if (!$group) {
    set_flash('error', 'Groupe introuvable.');
    header('Location: dashboard.php');
    exit;
}

if (!is_group_owner_or_member($pdo, $group_id)) {
    set_flash('error', "Vous n'avez pas acces a ce groupe.");
    header('Location: dashboard.php');
    exit;
}

// Charger les membres du groupe
$stmt = $pdo->prepare('SELECT * FROM group_members WHERE group_id = ? ORDER BY display_name');
$stmt->execute([$group_id]);
$members = $stmt->fetchAll();

if (empty($members)) {
    set_flash('error', 'Ajoutez au moins un membre avant de creer une depense.');
    header('Location: group_view.php?id=' . $group_id);
    exit;
}

// Charger les categories
$categories = $pdo->query('SELECT * FROM categories ORDER BY name')->fetchAll();

$errors = [];
$payer_member_id = '';
$category_id     = '';
$amount          = '';
$description     = '';
$expense_date    = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payer_member_id = (int)($_POST['payer_member_id'] ?? 0);
    $category_id     = (int)($_POST['category_id'] ?? 0);
    $amount          = $_POST['amount'] ?? '';
    $description     = trim($_POST['description'] ?? '');
    $expense_date    = trim($_POST['expense_date'] ?? '');

    // Validation
    if ($payer_member_id <= 0) $errors[] = 'Selectionnez un payeur.';
    if ($category_id <= 0)     $errors[] = 'Selectionnez une categorie.';
    if (!is_numeric($amount) || (float)$amount <= 0) {
        $errors[] = 'Le montant doit etre un nombre positif.';
    }
    if ($expense_date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expense_date)) {
        $errors[] = 'La date est obligatoire et doit etre au format AAAA-MM-JJ.';
    }

    // Verifier que le payeur est bien membre du groupe
    if ($payer_member_id > 0) {
        $stmt = $pdo->prepare('SELECT id FROM group_members WHERE id = ? AND group_id = ?');
        $stmt->execute([$payer_member_id, $group_id]);
        if (!$stmt->fetch()) {
            $errors[] = 'Payeur invalide.';
        }
    }

    if (empty($errors)) {
        $amount_val = round((float)$amount, 2);

        // Inserer la depense
        $stmt = $pdo->prepare(
            'INSERT INTO expenses (group_id, payer_member_id, category_id, amount, description, expense_date)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$group_id, $payer_member_id, $category_id, $amount_val, $description, $expense_date]);
        $expense_id = $pdo->lastInsertId();

        // Calcul du split egal entre tous les membres
        $member_count = count($members);
        $share = round($amount_val / $member_count, 2);
        // Gerer le reste des centimes pour que la somme soit exacte
        $remainder = round($amount_val - ($share * $member_count), 2);

        $stmt_split = $pdo->prepare(
            'INSERT INTO splits (expense_id, member_id, share_amount) VALUES (?, ?, ?)'
        );

        foreach ($members as $i => $m) {
            $member_share = $share;
            // Ajouter le reste au premier membre
            if ($i === 0) {
                $member_share = round($share + $remainder, 2);
            }
            $stmt_split->execute([$expense_id, $m['id'], $member_share]);
        }

        // Notifier les membres du groupe
        $payer_stmt = $pdo->prepare('SELECT display_name FROM group_members WHERE id = ?');
        $payer_stmt->execute([$payer_member_id]);
        $payer_name = $payer_stmt->fetchColumn();

        notify_group_members(
            $pdo,
            $group_id,
            $payer_name . ' a ajoute une depense de ' . number_format($amount_val, 2, ',', ' ') . ' EUR dans "' . $group['name'] . '".',
            'group_view.php?id=' . $group_id,
            (int)$user['id']
        );

        set_flash('success', 'Depense ajoutee avec succes.');
        header('Location: group_view.php?id=' . $group_id);
        exit;
    }
}
?>

<div class="page-header">
    <h1>Ajouter une depense</h1>
    <a href="group_view.php?id=<?= $group_id ?>" class="btn btn-secondary btn-sm">Retour au groupe</a>
</div>

<p class="text-muted mb-2">
    Groupe : <strong><?= h($group['name']) ?></strong>
    &mdash; Le montant sera reparti equitablement entre les <?= count($members) ?> membre(s).
</p>

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
        <form method="post" action="expense_add.php?group_id=<?= $group_id ?>">

            <div class="form-row">
                <div class="form-group">
                    <label for="payer_member_id">Paye par *</label>
                    <select id="payer_member_id" name="payer_member_id" class="form-control" required>
                        <option value="">-- Choisir --</option>
                        <?php foreach ($members as $m): ?>
                            <option value="<?= $m['id'] ?>" <?= $payer_member_id == $m['id'] ? 'selected' : '' ?>>
                                <?= h($m['display_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="category_id">Categorie *</label>
                    <select id="category_id" name="category_id" class="form-control" required>
                        <option value="">-- Choisir --</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $category_id == $c['id'] ? 'selected' : '' ?>>
                                <?= h($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="amount">Montant (EUR) *</label>
                    <input type="number" id="amount" name="amount" class="form-control"
                           step="0.01" min="0.01" value="<?= h($amount) ?>" required>
                </div>
                <div class="form-group">
                    <label for="expense_date">Date *</label>
                    <input type="date" id="expense_date" name="expense_date" class="form-control"
                           value="<?= h($expense_date) ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <input type="text" id="description" name="description" class="form-control"
                       value="<?= h($description) ?>" placeholder="ex : Restaurant du soir, Train Paris-Lyon...">
            </div>

            <div class="btn-group">
                <button type="submit" class="btn btn-primary">Enregistrer la depense</button>
                <a href="group_view.php?id=<?= $group_id ?>" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
