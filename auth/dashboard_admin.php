<?php
/*
 * WorkTracker — Dashboard d'administrador (antic)
 * Fitxer: auth/dashboard_admin.php
 *
 * Redirigeix al nou panell complet a admin/dashboard.php
 */

session_start();

if (!isset($_SESSION['usuari']) || $_SESSION['usuari']['rol'] !== 'admin') {
    header('Location: login.php');
    exit;
}

header('Location: ../admin/dashboard.php');
exit;