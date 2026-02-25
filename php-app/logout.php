<?php
/**
 * logout.php - Deconnexion
 */
session_start();
$_SESSION = [];
session_destroy();
header('Location: index.php');
exit;
