<?php
/**
 * expense_delete.php - Supprimer une depense avec confirmation
 */
$page_title = 'Supprimer une depense';
require_once __DIR__ . '/header.php';
require_login();

$expense_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Charger la depense
$stmt = $pdo->prepare(
    "SELECT e.*, g.name AS group_name, g.owner_id, gm.display_name AS payer_name, c.name AS category_name
     FROM expenses e
     JOIN `groups` g ON g.id = e.group_id
     JOIN group_members gm ON gm.id = e.payer_member_id
     JOIN categories c ON c.id = e.category_id
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

// Controle d'acces
if (!is_group_owner_or_member($pdo, $group_id)) {
    set_flash('error', "Vous n'avez pas le droit de supprimer cette depense.");
    header('Location: dashboard.php');
    exit;
}

// Confirmation de suppression
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    // Les splits seront supprimes en cascade (ON DELETE CASCADE)
    $pdo->prepare('DELETE FROM expenses WHERE id = ?')->execute([$expense_id]);

    set_flash('success', 'Depense supprimee.');
    header('Location: group_view.php?id=' . $group_id);
    exit;
}
?>

<div class="page-header">
    <h1>Supprimer une depense</h1>
</div>

<div class="card">
    <div class="card-body">
        <p>Voulez-vous vraiment supprimer cette depense ?</p>

        <table class="mt-1 mb-2" style="max-width:30rem;">
            <tr>
                <th style="width:8rem;">Groupe</th>
                <td><?= h($expense['group_name']) ?></td>
            </tr>
            <tr>
                <th>Payeur</th>
                <td><?= h($expense['payer_name']) ?></td>
            </tr>
            <tr>
                <th>Montant</th>
                <td><strong><?= number_format((float)$expense['amount'], 2, ',', ' ') ?> &euro;</strong></td>
            </tr>
            <tr>
                <th>Categorie</th>
                <td><?= h($expense['category_name']) ?></td>
            </tr>
            <tr>
                <th>Date</th>
                <td><?= date('d/m/Y', strtotime($expense['expense_date'])) ?></td>
            </tr>
            <tr>
                <th>Description</th>
                <td><?= h($expense['description'] ?: '-') ?></td>
            </tr>
        </table>

        <form method="post" action="expense_delete.php?id=<?= $expense_id ?>">
            <div class="btn-group">
                <button type="submit" name="confirm" value="1" class="btn btn-danger">Confirmer la suppression</button>
                <a href="group_view.php?id=<?= $group_id ?>" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
