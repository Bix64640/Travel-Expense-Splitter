<?php
/**
 * notifications.php - Centre de notifications de l'utilisateur
 *
 * Liste toutes les notifications, avec possibilite de marquer comme lue.
 */
$page_title = 'Notifications';
require_once __DIR__ . '/header.php';
require_login();

$uid = (int)$user['id'];

// Marquer une notification comme lue
if (isset($_GET['mark_read'])) {
    $nid = (int)$_GET['mark_read'];
    $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?')
        ->execute([$nid, $uid]);
    header('Location: notifications.php');
    exit;
}

// Marquer toutes comme lues
if (isset($_GET['mark_all_read'])) {
    $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')
        ->execute([$uid]);
    set_flash('success', 'Toutes les notifications ont ete marquees comme lues.');
    header('Location: notifications.php');
    exit;
}

// Charger les notifications
$stmt = $pdo->prepare(
    'SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 100'
);
$stmt->execute([$uid]);
$notifications = $stmt->fetchAll();

$unread = 0;
foreach ($notifications as $n) {
    if (!$n['is_read']) $unread++;
}
?>

<div class="page-header">
    <h1>Notifications</h1>
    <?php if ($unread > 0): ?>
        <a href="notifications.php?mark_all_read=1" class="btn btn-secondary btn-sm">
            Tout marquer comme lu (<?= $unread ?>)
        </a>
    <?php endif; ?>
</div>

<?php if (empty($notifications)): ?>
    <div class="empty">
        <div class="empty-icon">&#128276;</div>
        <p>Aucune notification.</p>
    </div>
<?php else: ?>
    <div class="card">
        <?php foreach ($notifications as $n): ?>
            <div class="notif-item <?= $n['is_read'] ? '' : 'unread' ?>">
                <?php if (!$n['is_read']): ?>
                    <div class="notif-dot"></div>
                <?php else: ?>
                    <div style="width:.5rem;flex-shrink:0;"></div>
                <?php endif; ?>

                <div class="notif-content">
                    <div>
                        <?= h($n['message']) ?>
                        <?php if ($n['link']): ?>
                            &mdash; <a href="<?= h($n['link']) ?>">Voir</a>
                        <?php endif; ?>
                    </div>
                    <div class="notif-date"><?= date('d/m/Y H:i', strtotime($n['created_at'])) ?></div>
                </div>

                <?php if (!$n['is_read']): ?>
                    <a href="notifications.php?mark_read=<?= $n['id'] ?>" class="btn btn-secondary btn-sm"
                       title="Marquer comme lu">Lu</a>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>
