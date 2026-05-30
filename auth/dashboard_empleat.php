<?php
/*
 * WorkTracker — Dashboard d'empleat
 * Fitxer: auth/dashboard_empleat.php
 * 
 * Redirigeix segons el rol:
 * - Admin → auth/dashboard_admin.php
 * - Empleat → ../empleat/dashboard.php
 * - No autenticat → login.php
 */

session_start();

if (!isset($_SESSION['usuari'])) {
    header('Location: login.php');
    exit;
}

if ($_SESSION['usuari']['rol'] === 'admin') {
    header('Location: dashboard_admin.php');
    exit;
}

// És empleat → redirigir al nou panell
header('Location: ../empleat/dashboard.php');
exit;
