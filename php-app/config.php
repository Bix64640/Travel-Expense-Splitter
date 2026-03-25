<?php
/**
 * config.php - Configuration globale de l'application
 * 
 * Modifier les constantes ci-dessous selon votre environnement.
 */

// ---- Base de donnees ----
define('DB_HOST', 'localhost');
define('DB_NAME', 'tai_etu_bixente_boucon_v2'); // A adapter
define('DB_USER', 'tai_etu_bixente_boucon_v2');
define('DB_PASS', 'R8LNZNUKVX_v2');          // A adapter
define('DB_CHARSET', 'utf8mb4');

// ---- Debug (mettre false en production) ----
define('DEBUG', FALSE);
if (defined('DEBUG') && DEBUG) {
	ini_set('display_errors', '1');
	ini_set('display_startup_errors', '1');
	error_reporting(E_ALL);
}

// ---- Identifiants admin (a utiliser seulement en dev) ----
// Ces valeurs servent a afficher une rubrique sur la page de connexion
// pour faciliter les tests. CHANGEZ/ENLEVEZ ces valeurs en production.
define('ADMIN_EMAIL', 'admin@example.com');
define('ADMIN_PASS',  'admin123');
define('ADMIN_CREDENTIALS_VISIBLE', true); // true => affiche la rubrique sur login.php

// ---- Application ----
define('APP_NAME', 'Travel Expense Splitter');
define('APP_URL',  'http://localhost/php-app'); // Sans slash final

// ---- Roles ----
define('ROLE_VISITOR',  'visitor');
define('ROLE_USER',     'user');
define('ROLE_ADMIN',    'admin');
