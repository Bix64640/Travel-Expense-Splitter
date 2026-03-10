<?php
/**
 * config.php - Configuration globale de l'application
 * 
 * Modifier les constantes ci-dessous selon votre environnement.
 */

// ---- Base de donnees ----
define('DB_HOST', 'localhost');
define('DB_NAME', 'tai_etu_bixente_boucon'); // A adapter
define('DB_USER', 'tai_etu_bixente_boucon');
define('DB_PASS', 'R8LNZNUKVX');          // A adapter
define('DB_CHARSET', 'utf8mb4');

// ---- Debug (mettre false en production) ----
define('DEBUG', FALSE);
if (defined('DEBUG') && DEBUG) {
	ini_set('display_errors', '1');
	ini_set('display_startup_errors', '1');
	error_reporting(E_ALL);
}

// ---- Application ----
define('APP_NAME', 'Travel Expense Splitter');
define('APP_URL',  'http://localhost/php-app'); // Sans slash final

// ---- Roles ----
define('ROLE_VISITOR',  'visitor');
define('ROLE_USER',     'user');
define('ROLE_ADMIN',    'admin');
