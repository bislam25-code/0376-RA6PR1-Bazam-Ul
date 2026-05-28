<?php
/*
 * WorkTracker — Dashboard d'empleat
 * Fitxer: auth/dashboard_empleat.php
 */

session_start();

// Protegir: només usuaris autenticats amb rol empleat
if (!isset($_SESSION['usuari']) || $_SESSION['usuari']['rol'] !== 'empleat') {
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
    <title>WorkTracker — Panell d'Empleat</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <div class="container">
        <h1>WorkTracker</h1>
        <h2>Panell d'Empleat</h2>
        <p>Benvingut, <strong><?= htmlspecialchars($usuari['nom'] . ' ' . $usuari['cognom'], ENT_QUOTES, 'UTF-8') ?></strong> (<?= $usuari['rol'] ?>)</p>

        <nav>
            <ul>
                <li><a href="registre_hora.php">Registrar hores</a></li>
                <li><a href="meus_registres.php">Els meus registres</a></li>
                <li><a href="logout.php">Tancar sessió</a></li>
            </ul>
        </nav>

        <p>Aquest és el teu panell personal. Des d'aquí pots registrar les teves hores de treball i consultar els teus registres.</p>
    </div>
</body>
</html>