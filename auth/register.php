<?php
/**
 * WorkTracker — Registre de nou usuari
 * Fitxer: auth/register.php
 */

require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../config/db.php';

// Si ja està autenticat, redirigir
if (isset($_SESSION['usuari'])) {
    if ($_SESSION['usuari']['rol'] === 'admin') {
        header('Location: ../admin/dashboard.php');
    } else {
        header('Location: ../empleat/dashboard.php');
    }
    exit;
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $nom      = htmlspecialchars(trim($_POST['nom'] ?? ''), ENT_QUOTES, 'UTF-8');
    $cognom   = htmlspecialchars(trim($_POST['cognom'] ?? ''), ENT_QUOTES, 'UTF-8');
    $email    = htmlspecialchars(trim($_POST['email'] ?? ''), ENT_QUOTES, 'UTF-8');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if (empty($nom) || empty($cognom)) {
        $error = 'El nom i el cognom són obligatoris.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'El correu electrònic no és vàlid.';
    } elseif (strlen($password) < 6) {
        $error = 'La contrasenya ha de tenir almenys 6 caràcters.';
    } elseif ($password !== $confirm) {
        $error = 'Les contrasenyes no coincideixen.';
    } else {
        $stmt = $pdo->prepare('SELECT id FROM usuaris WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) {
            $error = 'Aquest correu ja està registrat.';
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare(
                'INSERT INTO usuaris (nom, cognom, email, password_hash, rol)
                 VALUES (:nom, :cognom, :email, :password_hash, :rol)'
            );
            $stmt->execute([
                ':nom'           => $nom,
                ':cognom'        => $cognom,
                ':email'         => $email,
                ':password_hash' => $password_hash,
                ':rol'           => 'empleat',
            ]);

            $success = 'Registre completat. Ja pots <a href="login.php">iniciar sessió</a>.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WorkTracker — Registre</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <div class="container">
        <h1>WorkTracker</h1>
        <h2>Registre d'usuari</h2>

        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success"><?= $success ?></div>
        <?php else: ?>
            <form method="POST" action="">
                <?php csrf_field(); ?>
                <div>
                    <label for="nom">Nom</label>
                    <input type="text" name="nom" id="nom" required
                           value="<?= htmlspecialchars($_POST['nom'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div>
                    <label for="cognom">Cognom</label>
                    <input type="text" name="cognom" id="cognom" required
                           value="<?= htmlspecialchars($_POST['cognom'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div>
                    <label for="email">Correu electrònic</label>
                    <input type="email" name="email" id="email" required
                           value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div>
                    <label for="password">Contrasenya</label>
                    <input type="password" name="password" id="password" required minlength="6">
                </div>
                <div>
                    <label for="confirm">Confirma la contrasenya</label>
                    <input type="password" name="confirm" id="confirm" required minlength="6">
                </div>
                <button type="submit">Registrar-se</button>
            </form>
            <p>Ja tens compte? <a href="login.php">Inicia sessió</a></p>
        <?php endif; ?>
    </div>
</body>
</html>