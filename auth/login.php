<?php
/*
 * WorkTracker — Inici de sessió
 * Fitxer: auth/login.php
 */

require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../config/db.php';

// Si ja està autenticat, redirigir segons el rol
if (isset($_SESSION['usuari'])) {
    if ($_SESSION['usuari']['rol'] === 'admin') {
        header('Location: ../admin/dashboard.php');
    } else {
        header('Location: ../empleat/dashboard.php');
    }
 
    exit;
}

$error      = '';
$email_recordat = $_COOKIE['worktracker_email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $email    = htmlspecialchars(trim($_POST['email'] ?? ''), ENT_QUOTES, 'UTF-8');
    $password = $_POST['password'] ?? '';
    $recordar = isset($_POST['recordar']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'El correu electrònic no és vàlid.';
    } elseif (empty($password)) {
        $error = 'La contrasenya és obligatòria.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM usuaris WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $usuari = $stmt->fetch();

        if ($usuari && password_verify($password, $usuari['password_hash'])) {
            // Recordar email (mai la contrasenya) — 7 dies
            if ($recordar) {
                setcookie('worktracker_email', $email, time() + 86400 * 7, '/', '', false, true);
            } else {
                setcookie('worktracker_email', '', time() - 3600, '/');
            }

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
                header('Location: ../empleat/dashboard.php');
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
    <style>
        body { background: linear-gradient(135deg, #f5e6d0 0%, #f0f4ff 50%, #ffffff 100%); }
        .container { max-width: 450px; box-shadow: 0 10px 40px rgba(139,90,43,0.12); }
        h1, h2 { color: #8B5A2B; }
        h2 { color: #a0785a; }
        button { background: linear-gradient(135deg, #8B5A2B 0%, #a0785a 100%); }
        button:hover { box-shadow: 0 4px 15px rgba(139,90,43,0.4); }
        a { color: #8B5A2B; }
        a:hover { color: #a0785a; }
        input:focus { border-color: #8B5A2B; box-shadow: 0 0 0 3px rgba(139,90,43,0.12); }
        label.checkbox-label { display: flex; align-items: center; gap: 8px; font-weight: 400; cursor: pointer; }
        label.checkbox-label input[type="checkbox"] { width: 18px; height: 18px; accent-color: #8B5A2B; }
    </style>
</head>
<body>
    <div class="container small">
        <div style="text-align:center;margin-bottom:20px;">
            <h1>WorkTracker</h1>
            <h2>Inici de sessió</h2>
        </div>

        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <?php csrf_field(); ?>
            <div>
                <label for="email">Correu electrònic</label>
                <input type="email" name="email" id="email" required
                       value="<?= htmlspecialchars($email_recordat ?: ($_POST['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div>
                <label for="password">Contrasenya</label>
                <input type="password" name="password" id="password" required>
            </div>
            <div>
                <label>
                    <input type="checkbox" name="recordar" value="1"
                        <?= $email_recordat ? 'checked' : '' ?>>
                    Recordar el meu correu
                </label>
            </div>
            <button type="submit">Entrar</button>
        </form>

        <p>No tens compte? <a href="register.php">Registra't</a></p>
    </div>
</body>
</html>