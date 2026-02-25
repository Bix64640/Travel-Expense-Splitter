<?php
/**
 * settlements.php - Propositions de reglements pour equilibrer un groupe
 *
 * Algorithme : "greedy" simpliste
 *   1. Calculer le solde net de chaque membre (paye - du)
 *   2. Trier les debiteurs (solde < 0) et crediteurs (solde > 0)
 *   3. Le plus gros debiteur rembourse le plus gros crediteur,
 *      du montant minimum entre les deux.
 *   4. Repeter jusqu'a equilibre.
 *
 * Possibilite de sauvegarder les propositions en base.
 */
$page_title = 'Propositions de reglements';
require_once __DIR__ . '/header.php';
require_login();

$group_id = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;

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
$balances_raw = [];
foreach ($members as $m) {
    $mid = (int)$m['id'];

    $s = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE payer_member_id = ? AND group_id = ?');
    $s->execute([$mid, $group_id]);
    $paid = (float)$s->fetchColumn();

    $s = $pdo->prepare(
        'SELECT COALESCE(SUM(sp.share_amount), 0)
         FROM splits sp
         JOIN expenses e ON e.id = sp.expense_id
         WHERE sp.member_id = ? AND e.group_id = ?'
    );
    $s->execute([$mid, $group_id]);
    $owed = (float)$s->fetchColumn();

    $balances_raw[$mid] = [
        'name'    => $m['display_name'],
        'balance' => round($paid - $owed, 2),
    ];
}

// ---- Algorithme de reglement greedy ----
function compute_settlements(array $balances_raw): array
{
    $debtors   = []; // solde < 0 : doivent de l'argent
    $creditors = []; // solde > 0 : on leur doit

    foreach ($balances_raw as $mid => $data) {
        if ($data['balance'] < -0.01) {
            $debtors[] = ['id' => $mid, 'name' => $data['name'], 'amount' => abs($data['balance'])];
        } elseif ($data['balance'] > 0.01) {
            $creditors[] = ['id' => $mid, 'name' => $data['name'], 'amount' => $data['balance']];
        }
    }

    // Trier par montant decroissant
    usort($debtors, fn($a, $b) => $b['amount'] <=> $a['amount']);
    usort($creditors, fn($a, $b) => $b['amount'] <=> $a['amount']);

    $settlements = [];
    $i = 0; // index debiteur
    $j = 0; // index crediteur

    while ($i < count($debtors) && $j < count($creditors)) {
        $transfer = min($debtors[$i]['amount'], $creditors[$j]['amount']);
        $transfer = round($transfer, 2);

        if ($transfer > 0.01) {
            $settlements[] = [
                'from_id'   => $debtors[$i]['id'],
                'from_name' => $debtors[$i]['name'],
                'to_id'     => $creditors[$j]['id'],
                'to_name'   => $creditors[$j]['name'],
                'amount'    => $transfer,
            ];
        }

        $debtors[$i]['amount']   = round($debtors[$i]['amount'] - $transfer, 2);
        $creditors[$j]['amount'] = round($creditors[$j]['amount'] - $transfer, 2);

        if ($debtors[$i]['amount'] < 0.01) $i++;
        if ($creditors[$j]['amount'] < 0.01) $j++;
    }

    return $settlements;
}

$settlements = compute_settlements($balances_raw);

// Sauvegarder les propositions en base si POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settlements'])) {
    // Supprimer les anciennes propositions
    $pdo->prepare('DELETE FROM settlements WHERE group_id = ?')->execute([$group_id]);

    $stmt_ins = $pdo->prepare(
        'INSERT INTO settlements (group_id, from_member_id, to_member_id, amount) VALUES (?, ?, ?, ?)'
    );
    foreach ($settlements as $s) {
        $stmt_ins->execute([$group_id, $s['from_id'], $s['to_id'], $s['amount']]);
    }

    set_flash('success', 'Propositions de reglement enregistrees en base.');
    header('Location: settlements.php?group_id=' . $group_id);
    exit;
}

// Charger les reglements sauvegardes
$stmt = $pdo->prepare(
    "SELECT st.*, gm_from.display_name AS from_name, gm_to.display_name AS to_name
     FROM settlements st
     JOIN group_members gm_from ON gm_from.id = st.from_member_id
     JOIN group_members gm_to   ON gm_to.id   = st.to_member_id
     WHERE st.group_id = ?
     ORDER BY st.amount DESC"
);
$stmt->execute([$group_id]);
$saved_settlements = $stmt->fetchAll();
?>

<div class="page-header">
    <h1>Reglements &mdash; <?= h($group['name']) ?></h1>
    <div class="btn-group">
        <a href="group_view.php?id=<?= $group_id ?>" class="btn btn-secondary btn-sm">Retour au groupe</a>
        <a href="balances.php?group_id=<?= $group_id ?>" class="btn btn-secondary btn-sm">Voir les soldes</a>
    </div>
</div>

<!-- Propositions calculees -->
<div class="card mb-2">
    <div class="card-header">
        <h3>Propositions de reglements</h3>
    </div>
    <div class="card-body">
        <?php if (empty($settlements)): ?>
            <div class="alert alert-success" style="margin-bottom:0;">
                Tout le monde est a jour ! Aucun reglement necessaire.
            </div>
        <?php else: ?>
            <p class="text-muted mb-1">
                Pour equilibrer le groupe, voici les virements proposes :
            </p>
            <?php foreach ($settlements as $s): ?>
                <div class="settlement-item">
                    <strong><?= h($s['from_name']) ?></strong>
                    <span class="settlement-arrow">&rarr;</span>
                    <strong><?= h($s['to_name']) ?></strong>
                    <span class="settlement-amount">
                        <?= number_format($s['amount'], 2, ',', ' ') ?> &euro;
                    </span>
                </div>
            <?php endforeach; ?>

            <form method="post" action="settlements.php?group_id=<?= $group_id ?>" class="mt-2">
                <button type="submit" name="save_settlements" value="1" class="btn btn-primary btn-sm">
                    Sauvegarder ces propositions
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Reglements sauvegardes -->
<?php if (!empty($saved_settlements)): ?>
<div class="card">
    <div class="card-header">
        <h3>Reglements sauvegardes</h3>
    </div>
    <div class="card-body">
        <p class="text-sm text-muted mb-1">
            Derniere sauvegarde : <?= date('d/m/Y H:i', strtotime($saved_settlements[0]['created_at'])) ?>
        </p>
        <?php foreach ($saved_settlements as $s): ?>
            <div class="settlement-item">
                <strong><?= h($s['from_name']) ?></strong>
                <span class="settlement-arrow">&rarr;</span>
                <strong><?= h($s['to_name']) ?></strong>
                <span class="settlement-amount">
                    <?= number_format((float)$s['amount'], 2, ',', ' ') ?> &euro;
                </span>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Resume des soldes -->
<div class="card mt-2">
    <div class="card-header">
        <h3>Resume des soldes</h3>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Membre</th><th class="text-right">Solde net</th></tr>
            </thead>
            <tbody>
                <?php foreach ($balances_raw as $b): ?>
                    <tr>
                        <td><?= h($b['name']) ?></td>
                        <td class="text-right">
                            <?php if ($b['balance'] > 0): ?>
                                <span style="color:var(--color-success);font-weight:700;">+<?= number_format($b['balance'], 2, ',', ' ') ?> &euro;</span>
                            <?php elseif ($b['balance'] < 0): ?>
                                <span style="color:var(--color-danger);font-weight:700;"><?= number_format($b['balance'], 2, ',', ' ') ?> &euro;</span>
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

<?php require_once __DIR__ . '/footer.php'; ?>
