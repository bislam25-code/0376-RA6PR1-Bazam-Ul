<?php
/**
 * WorkTracker — Tancament de sessió
 * Fitxer: auth/logout.php
 */

session_start();

// Netejar totes les variables de sessió
$_SESSION = [];

// Destruir la sessió
session_unset();
session_destroy();

// Redirigir a la pàgina de login
header('Location: login.php');
exit;