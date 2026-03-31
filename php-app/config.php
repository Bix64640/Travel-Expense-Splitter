<?php
/**
 * config.php - Configuration globale de l'application
 * 
 * Modifier les constantes ci-dessous selon votre environnement.
 */

// ---- Base de donnees ----
define('DB_HOST', 'localhost');
define('DB_NAME', 'tai_bixente_boucon_v2'); // A adapter
define('DB_USER', 'tai_bixente_boucon_v2');
define('DB_PASS', 'R8LNZNUKVX_v2');          // A adapter
define('DB_CHARSET', 'utf8mb4');

// ---- Debug (mettre false en production) ----
// Mode DEBUG temporairement active pour diagnostic. Remettez a FALSE ensuite.
define('DEBUG', false);
if (defined('DEBUG') && DEBUG) {
	ini_set('display_errors', '1');
	ini_set('display_startup_errors', '1');
	ini_set('log_errors', '1');
	ini_set('error_log', __DIR__ . '/error.log');
	error_reporting(E_ALL);
}

// ---- Identifiants admin (a utiliser seulement en dev) ----
// Ces valeurs servent a afficher une rubrique sur la page de connexion
// pour faciliter les tests. CHANGEZ/ENLEVEZ ces valeurs en production.
define('ADMIN_EMAIL', 'admin@gmail.com');
define('ADMIN_PASS',  'admin123');
define('ADMIN_CREDENTIALS_VISIBLE', true); // false => desactive les outils/dev qui affichent les identifiants

// ---- Application ----
define('APP_NAME', 'Travel Expense Splitter');
define('APP_URL',  'http://localhost/php-app'); // Sans slash final

// ---- Comptes de test (utilisateurs) ----
// Remplissez ces valeurs pour fournir des exemples sur la page de connexion.
define('SAMPLE_USERS_VISIBLE', true);
define('SAMPLE_USER_1_EMAIL', 'PAUL@gmail.com');
define('SAMPLE_USER_1_PASS',  'PAUL64');
define('SAMPLE_USER_2_EMAIL', 'PIERRE@gmail.com');
define('SAMPLE_USER_2_PASS',  'PIERRE64');
define('SAMPLE_USER_3_EMAIL', 'LILIANE@gmail.com');
define('SAMPLE_USER_3_PASS',  'LILIANE64');

// ---- Roles ----
define('ROLE_VISITOR',  'visitor');
define('ROLE_USER',     'user');
define('ROLE_ADMIN',    'admin');
