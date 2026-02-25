<?php
/**
 * db.php - Connexion a la base de donnees MySQL
 *
 * Inclure ce fichier dans chaque page qui a besoin de la BDD.
 * La variable $pdo est une instance PDO prete a l'emploi.
 */

require_once __DIR__ . '/config.php';

try {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    die('Erreur de connexion a la base de donnees : ' . $e->getMessage());
}
