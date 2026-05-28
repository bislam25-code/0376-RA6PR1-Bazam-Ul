<?php
/*
 * WorkTracker — Dashboard d'administrador
 * Fitxer: auth/dashboard_admin.php
 */

session_start();

// Protegir: només usuaris autenticats amb rol admin
if (!isset($_SESSION['usuari']) || $_SESSION['usuari']['rol'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$usuari = $_SESSION['usuari'];
?>
<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WorkTracker — Panell d'Administrador</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <div class="container">
        <h1>WorkTracker</h1>
        <h2>Panell d'Administrador</h2>
        <p>Benvingut, <strong><?= htmlspecialchars($usuari['nom'] . ' ' . $usuari['cognom'], ENT_QUOTES, 'UTF-8') ?></strong> (<?= $usuari['rol'] ?>)</p>

        <nav>
            <ul>
                <li><a href="gestio_usuaris.php">Gestionar usuaris</a></li>
                <li><a href="gestio_projectes.php">Gestionar projectes</a></li>
                <li><a href="informes.php">Informes</a></li>
                <li><a href="logout.php">Tancar sessió</a></li>
            </ul>
        </nav>

        <p>Aquest és el panell d'administració. Des d'aquí pots gestionar usuaris, projectes i generar informes.</p>
    </div>
</body>
</html>