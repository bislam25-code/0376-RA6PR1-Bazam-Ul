<?php
/*
 * WorkTracker — Pàgina principal
 * Fitxer: index.php
 *
 * Redirigeix automàticament segons l'estat de l'usuari:
 * - Autenticat com a admin → dashboard_admin.php
 * - Autenticat com a empleat → dashboard_empleat.php
 * - No autenticat → auth/login.php
 */

session_start();

if (isset($_SESSION['usuari'])) {
    if ($_SESSION['usuari']['rol'] === 'admin') {
        header('Location: auth/dashboard_admin.php');
    } else {
        header('Location: auth/dashboard_empleat.php');
    }
} else {
    header('Location: auth/login.php');
}
exit;