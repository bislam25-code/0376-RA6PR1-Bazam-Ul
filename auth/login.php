<?php
/*
 * WorkTracker — Inici de sessió
 * Fitxer: auth/login.php
 */

session_start();

// Si ja està autenticat, redirigir segons el rol
if (isset($_SESSION['usuari'])) {
    if ($_SESSION['usuari']['rol'] === 'admin') {
        header('Location: ../admin/dashboard.php');
    } else {
        header('Location: dashboard_empleat.php');
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../config/db.php';

    $email    = htmlspecialchars(trim($_POST['email'] ?? ''), ENT_QUOTES, 'UTF-8');
    $password = $_POST['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'El correu electrònic no és vàlid.';
    } elseif (empty($password)) {
        $error = 'La contrasenya és obligatòria.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM usuaris WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $usuari = $stmt->fetch();

        if ($usuari && password_verify($password, $usuari['password_hash'])) {
            // Inici de sessió exitosa
            $_SESSION['usuari'] = [
                'id'     => $usuari['id'],
                'nom'    => $usuari['nom'],
                'cognom' => $usuari['cognom'],
                'email'  => $usuari['email'],
                'rol'    => $usuari['rol'],
            ];
            session_regenerate_id(true);

            if ($usuari['rol'] === 'admin') {
                header('Location: ../admin/dashboard.php');
            } else {
                header('Location: dashboard_empleat.php');
            }
            exit;
        } else {
            $error = 'Credencials incorrectes.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WorkTracker — Inici de sessió</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <div class="container">
        <h1>WorkTracker</h1>
        <h2>Inici de sessió</h2>

        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div>
                <label for="email">Correu electrònic</label>
                <input type="email" name="email" id="email" required
                       value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div>
                <label for="password">Contrasenya</label>
                <input type="password" name="password" id="password" required>
            </div>
            <button type="submit">Entrar</button>
        </form>

        <p>No tens compte? <a href="register.php">Registra't</a></p>
    </div>
</body>
</html>