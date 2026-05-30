<?php
/*
 * WorkTracker — Pàgina principal
 * Fitxer: index.php
 *
 * Redirigeix automàticament segons l'estat de l'usuari:
 * - Autenticat com a admin → auth/dashboard_admin.php
 * - Autenticat com a empleat → empleat/dashboard.php
 * - No autenticat → auth/login.php
 */

session_start();

if (isset($_SESSION['usuari'])) {
    if ($_SESSION['usuari']['rol'] === 'admin') {
        header('Location: auth/dashboard_admin.php');
    } else {
        header('Location: empleat/dashboard.php');
    }
} else {
    header('Location: auth/login.php');
}
exit;
