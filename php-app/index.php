<?php
/**
 * index.php - Page d'accueil
 */
$page_title = 'Accueil';
require_once __DIR__ . '/header.php';
?>

<div class="hero">
    <h1>Partagez vos depenses de voyage</h1>
    <p>
        Creez un groupe, ajoutez vos compagnons de voyage, enregistrez les depenses
        et laissez l'application calculer qui doit combien a qui.
    </p>
    <?php if (!is_logged_in()): ?>
        <a href="signup.php" class="btn btn-primary">Commencer gratuitement</a>
        <a href="catalog.php" class="btn btn-secondary" style="margin-left:.5rem">Voir les groupes publics</a>
    <?php else: ?>
        <a href="dashboard.php" class="btn btn-primary">Mon tableau de bord</a>
    <?php endif; ?>

    <div class="features">
        <div class="feature-card">
            <div class="feature-icon">&#128101;</div>
            <h3>Groupes de voyage</h3>
            <p>Creez des groupes et invitez vos amis pour suivre les depenses ensemble.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon">&#128176;</div>
            <h3>Suivi des depenses</h3>
            <p>Enregistrez chaque depense avec son payeur, sa categorie et sa date.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon">&#9878;</div>
            <h3>Reglements automatiques</h3>
            <p>L'application calcule les soldes et propose les virements pour equilibrer.</p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
