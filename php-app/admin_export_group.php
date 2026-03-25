<?php
/**
 * admin_export_group.php - Export des donnees d'un groupe (CSV ou TXT)
 * Accessible uniquement aux admins.
 * Parametres : id (group id), format (csv|txt)
 */
require_once __DIR__ . '/header.php';
require_role('admin');

$group_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$format = strtolower($_GET['format'] ?? 'csv');

if ($group_id <= 0) {
    set_flash('error', 'ID de groupe invalide.');
    header('Location: admin_groups.php');
    exit;
}

// Charger le groupe
$stmt = $pdo->prepare('SELECT g.*, u.name AS owner_name, u.email AS owner_email FROM `groups` g JOIN users u ON u.id = g.owner_id WHERE g.id = ?');
$stmt->execute([$group_id]);
$group = $stmt->fetch();
if (!$group) {
    set_flash('error', 'Groupe introuvable.');
    header('Location: admin_groups.php');
    exit;
}

// Charger membres
$stmt = $pdo->prepare('SELECT gm.*, u.email AS user_email FROM group_members gm LEFT JOIN users u ON u.id = gm.user_id WHERE gm.group_id = ? ORDER BY gm.created_at');
$stmt->execute([$group_id]);
$members = $stmt->fetchAll();

// Charger depenses et splits
$stmt = $pdo->prepare(
    'SELECT e.*, c.name AS category_name, gm.display_name AS payer_name
     FROM expenses e
     JOIN categories c ON c.id = e.category_id
     JOIN group_members gm ON gm.id = e.payer_member_id
     WHERE e.group_id = ?
     ORDER BY e.expense_date, e.created_at'
);
$stmt->execute([$group_id]);
$expenses = $stmt->fetchAll();

// Rassembler les splits par expense
$splits_stmt = $pdo->prepare(
    'SELECT s.*, gm.display_name AS member_name FROM splits s JOIN group_members gm ON gm.id = s.member_id WHERE s.expense_id = ?'
);

// Prepare filename
$safe_name = preg_replace('/[^A-Za-z0-9_-]/', '_', substr($group['name'], 0, 40));
$date = date('Ymd_His');
$filename_base = 'group_' . $group_id . '_' . $safe_name . '_' . $date;

if ($format === 'txt') {
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename_base . '.txt"');

    // Entete
    echo "Groupe: " . $group['name'] . "\n";
    echo "Owner: " . $group['owner_name'] . " <" . $group['owner_email'] . ">\n";
    echo "Cree le: " . $group['created_at'] . "\n";
    echo str_repeat('=', 60) . "\n\n";

    echo "Membres:\n";
    foreach ($members as $m) {
        echo " - " . $m['display_name'];
        if (!empty($m['user_email'])) echo " <" . $m['user_email'] . ">";
        echo "\n";
    }
    echo "\n" . str_repeat('-', 60) . "\n\n";

    echo "Depenses:\n";
    if (empty($expenses)) {
        echo "Aucune depense.\n";
    } else {
        foreach ($expenses as $e) {
            echo "#" . $e['id'] . " - " . $e['expense_date'] . " - " . $e['category_name'] . " - ";
            echo $e['payer_name'] . " - " . number_format((float)$e['amount'], 2, ',', ' ') . " EUR\n";
            if (!empty($e['description'])) echo "  Desc: " . $e['description'] . "\n";

            // splits
            $splits_stmt->execute([$e['id']]);
            $splits = $splits_stmt->fetchAll();
            foreach ($splits as $s) {
                echo "    * " . $s['member_name'] . " => " . number_format((float)$s['share_amount'], 2, ',', ' ') . " EUR\n";
            }
            echo "\n";
        }
    }
    exit;

} else { // default CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename_base . '.csv"');

    $out = fopen('php://output', 'w');
    // BOM for Excel compatibility
    echo "\xEF\xBB\xBF";

    // Section 1: members
    fputcsv($out, ['SECTION', 'MEMBERS']);
    fputcsv($out, ['id', 'display_name', 'user_email', 'created_at']);
    foreach ($members as $m) {
        fputcsv($out, [$m['id'], $m['display_name'], $m['user_email'], $m['created_at']]);
    }
    fputcsv($out, []);

    // Section 2: expenses + splits (one row per split)
    fputcsv($out, ['SECTION', 'EXPENSES_AND_SPLITS']);
    fputcsv($out, ['expense_id', 'expense_date', 'category', 'payer_name', 'amount', 'description', 'split_member', 'split_amount']);
    foreach ($expenses as $e) {
        $splits_stmt->execute([$e['id']]);
        $splits = $splits_stmt->fetchAll();
        if (empty($splits)) {
            fputcsv($out, [$e['id'], $e['expense_date'], $e['category_name'], $e['payer_name'], $e['amount'], $e['description'], '', '']);
        } else {
            foreach ($splits as $s) {
                fputcsv($out, [$e['id'], $e['expense_date'], $e['category_name'], $e['payer_name'], $e['amount'], $e['description'], $s['member_name'], $s['share_amount']]);
            }
        }
    }
    fclose($out);
    exit;
}

?>
