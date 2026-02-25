<?php
/**
 * expense_edit.php - Modifier une depense existante
 *
 * Recalcule les splits apres modification.
 */
$page_title = 'Modifier une depense';
require_once __DIR__ . '/header.php';
require_login();

$expense_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Charger la depense
$stmt = $pdo->prepare(
    "SELECT e.*, g.name AS group_name, g.owner_id
     FROM expenses e
     JOIN `groups` g ON g.id = e.group_id
     WHERE e.id = ?"
);
$stmt->execute([$expense_id]);
$expense = $stmt->fetch();

if (!$expense) {
    set_flash('error', 'Depense introuvable.');
    header('Location: dashboard.php');
    exit;
}

$group_id = (int)$expense['group_id'];

// Controle d'acces : proprietaire du groupe ou admin
if (!is_group_owner_or_member($pdo, $group_id)) {
    set_flash('error', "Vous n'avez pas le droit de modifier cette depense.");
    header('Location: dashboard.php');
    exit;
}

// Membres et categories
$stmt = $pdo->prepare('SELECT * FROM group_members WHERE group_id = ? ORDER BY display_name');
$stmt->execute([$group_id]);
$members = $stmt->fetchAll();

$categories = $pdo->query('SELECT * FROM categories ORDER BY name')->fetchAll();

$errors          = [];
$payer_member_id = (int)$expense['payer_member_id'];
$category_id     = (int)$expense['category_id'];
$amount          = $expense['amount'];
$description     = $expense['description'];
$expense_date    = $expense['expense_date'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payer_member_id = (int)($_POST['payer_member_id'] ?? 0);
    $category_id     = (int)($_POST['category_id'] ?? 0);
    $amount          = $_POST['amount'] ?? '';
    $description     = trim($_POST['description'] ?? '');
    $expense_date    = trim($_POST['expense_date'] ?? '');

    if ($payer_member_id <= 0) $errors[] = 'Selectionnez un payeur.';
    if ($category_id <= 0)     $errors[] = 'Selectionnez une categorie.';
    if (!is_numeric($amount) || (float)$amount <= 0) {
        $errors[] = 'Le montant doit etre un nombre positif.';
    }
    if ($expense_date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expense_date)) {
        $errors[] = 'Date invalide.';
    }

    if (empty($errors)) {
        $amount_val = round((float)$amount, 2);

        // Mettre a jour la depense
        $stmt = $pdo->prepare(
            'UPDATE expenses SET payer_member_id = ?, category_id = ?, amount = ?, description = ?, expense_date = ?
             WHERE id = ?'
        );
        $stmt->execute([$payer_member_id, $category_id, $amount_val, $description, $expense_date, $expense_id]);

        // Recalculer les splits
        $pdo->prepare('DELETE FROM splits WHERE expense_id = ?')->execute([$expense_id]);

        $member_count = count($members);
        $share     = round($amount_val / $member_count, 2);
        $remainder = round($amount_val - ($share * $member_count), 2);

        $stmt_split = $pdo->prepare(
            'INSERT INTO splits (expense_id, member_id, share_amount) VALUES (?, ?, ?)'
        );
        foreach ($members as $i => $m) {
            $member_share = $share;
            if ($i === 0) $member_share = round($share + $remainder, 2);
            $stmt_split->execute([$expense_id, $m['id'], $member_share]);
        }

        set_flash('success', 'Depense mise a jour.');
        header('Location: group_view.php?id=' . $group_id);
        exit;
    }
}
?>

<div class="page-header">
    <h1>Modifier une depense</h1>
    <a href="group_view.php?id=<?= $group_id ?>" class="btn btn-secondary btn-sm">Retour</a>
</div>

<p class="text-muted mb-2">Groupe : <strong><?= h($expense['group_name']) ?></strong></p>

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
        <form method="post" action="expense_edit.php?id=<?= $expense_id ?>">

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
                       value="<?= h($description) ?>">
            </div>

            <div class="btn-group">
                <button type="submit" class="btn btn-primary">Enregistrer</button>
                <a href="group_view.php?id=<?= $group_id ?>" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
