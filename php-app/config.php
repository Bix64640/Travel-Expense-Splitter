<?php
/**
 * config.php - Configuration globale de l'application
 * 
 * Modifier les constantes ci-dessous selon votre environnement.
 */

// ---- Base de donnees ----
define('DB_HOST', 'localhost');
define('DB_NAME', 'travel_splitter');
define('DB_USER', 'root');
define('DB_PASS', '');          // A adapter
define('DB_CHARSET', 'utf8mb4');

// ---- Application ----
define('APP_NAME', 'Travel Expense Splitter');
define('APP_URL',  'http://localhost/php-app'); // Sans slash final

// ---- Roles ----
define('ROLE_VISITOR',  'visitor');
define('ROLE_USER',     'user');
define('ROLE_ADMIN',    'admin');
