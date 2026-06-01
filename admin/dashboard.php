<?php
/*
 * WorkTracker — Panell d'Administrador
 * Fitxer: admin/dashboard.php
 */

session_start();

// Protegir: només usuaris amb rol admin
if (!isset($_SESSION['usuari']) || $_SESSION['usuari']['rol'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';

$usuari    = $_SESSION['usuari'];
$error     = '';
$success   = '';
$seccio    = $_GET['seccio'] ?? 'resum';

// ============================================================
// GESTIÓ D'USUARIS
// ============================================================

// Crear empleat
if (isset($_POST['crear_usuari'])) {
    $nom      = trim($_POST['nom'] ?? '');
    $cognom   = trim($_POST['cognom'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $rol      = $_POST['rol'] ?? 'empleat';

    if (empty($nom) || empty($cognom) || empty($email) || empty($password)) {
        $error = 'Tots els camps són obligatoris.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'L\'email no és vàlid.';
    } elseif (strlen($password) < 6) {
        $error = 'La contrasenya ha de tenir almenys 6 caràcters.';
    } else {
        $stmt = $pdo->prepare('SELECT id FROM usuaris WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) {
            $error = 'Aquest email ja està registrat.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare(
                'INSERT INTO usuaris (nom, cognom, email, password_hash, rol)
                 VALUES (:nom, :cognom, :email, :hash, :rol)'
            );
            $stmt->execute([
                ':nom'    => $nom,
                ':cognom' => $cognom,
                ':email'  => $email,
                ':hash'   => $hash,
                ':rol'    => $rol,
            ]);
            $success = "Usuari $nom $cognom creat correctament.";
        }
    }
}

// Editar usuari
if (isset($_POST['editar_usuari'])) {
    $uid     = (int) ($_POST['uid'] ?? 0);
    $nom     = trim($_POST['nom'] ?? '');
    $cognom  = trim($_POST['cognom'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $rol     = $_POST['rol'] ?? 'empleat';
    $new_pwd = $_POST['new_password'] ?? '';

    if ($uid <= 0 || empty($nom) || empty($cognom) || empty($email)) {
        $error = 'Dades incompletes.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email no vàlid.';
    } else {
        if (!empty($new_pwd)) {
            if (strlen($new_pwd) < 6) {
                $error = 'La contrasenya ha de tenir almenys 6 caràcters.';
            } else {
                $hash = password_hash($new_pwd, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare(
                    'UPDATE usuaris SET nom = :nom, cognom = :cognom, email = :email, rol = :rol, password_hash = :hash
                     WHERE id = :uid'
                );
                $stmt->execute([
                    ':nom'    => $nom,
                    ':cognom' => $cognom,
                    ':email'  => $email,
                    ':rol'    => $rol,
                    ':hash'   => $hash,
                    ':uid'    => $uid,
                ]);
                $success = 'Usuari actualitzat (amb nova contrasenya).';
            }
        } else {
            $stmt = $pdo->prepare(
                'UPDATE usuaris SET nom = :nom, cognom = :cognom, email = :email, rol = :rol
                 WHERE id = :uid'
            );
            $stmt->execute([
                ':nom'    => $nom,
                ':cognom' => $cognom,
                ':email'  => $email,
                ':rol'    => $rol,
                ':uid'    => $uid,
            ]);
            $success = 'Usuari actualitzat.';
        }
    }
}

// Eliminar usuari
if (isset($_POST['eliminar_usuari'])) {
    $uid = (int) ($_POST['uid'] ?? 0);
    if ($uid > 0 && $uid !== (int)$usuari['id']) {
        $stmt = $pdo->prepare('DELETE FROM usuaris WHERE id = :uid');
        $stmt->execute([':uid' => $uid]);
        $success = 'Usuari eliminat.';
    } else {
        $error = 'No et pots eliminar a tu mateix.';
    }
}

// ============================================================
// GESTIÓ DE PROJECTES
// ============================================================

// Crear projecte
if (isset($_POST['crear_projecte'])) {
    $nom        = trim($_POST['nom'] ?? '');
    $descripcio = trim($_POST['descripcio'] ?? '');
    $hores_est  = (float) ($_POST['hores_estimades'] ?? 0);

    if (empty($nom)) {
        $error = 'El nom del projecte és obligatori.';
    } elseif ($hores_est < 0) {
        $error = 'Les hores estimades no poden ser negatives.';
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO projectes (nom, descripcio, hores_estimades)
             VALUES (:nom, :desc, :hores)'
        );
        $stmt->execute([
            ':nom'   => $nom,
            ':desc'  => $descripcio ?: null,
            ':hores' => $hores_est,
        ]);
        $success = "Projecte '$nom' creat correctament.";
    }
}

// Editar projecte
if (isset($_POST['editar_projecte'])) {
    $pid        = (int) ($_POST['pid'] ?? 0);
    $nom        = trim($_POST['nom'] ?? '');
    $descripcio = trim($_POST['descripcio'] ?? '');
    $hores_est  = (float) ($_POST['hores_estimades'] ?? 0);

    if ($pid <= 0 || empty($nom)) {
        $error = 'Dades incompletes.';
    } else {
        $stmt = $pdo->prepare(
            'UPDATE projectes SET nom = :nom, descripcio = :desc, hores_estimades = :hores WHERE id = :pid'
        );
        $stmt->execute([
            ':nom'   => $nom,
            ':desc'  => $descripcio ?: null,
            ':hores' => $hores_est,
            ':pid'   => $pid,
        ]);
        $success = 'Projecte actualitzat.';
    }
}

// Eliminar projecte
if (isset($_POST['eliminar_projecte'])) {
    $pid = (int) ($_POST['pid'] ?? 0);
    if ($pid > 0) {
        $stmt = $pdo->prepare('DELETE FROM projectes WHERE id = :pid');
        $stmt->execute([':pid' => $pid]);
        $success = 'Projecte eliminat.';
    }
}

// ============================================================
// CONSULTES PER A LA VISTA
// ============================================================

// SELECT específic (id, nom, cognom, email, rol) — sense *
$stmt = $pdo->query(
    'SELECT id, nom, cognom, email, rol, creat_at FROM usuaris ORDER BY id ASC'
);
$usuaris = $stmt->fetchAll();

// Tots els projectes
$stmt = $pdo->query(
    'SELECT id, nom, descripcio, hores_estimades, creat_at FROM projectes ORDER BY nom ASC'
);
$projectes = $stmt->fetchAll();

// RESUM: hores totals per usuari
$stmt = $pdo->query(
    'SELECT u.id, u.nom, u.cognom,
            COALESCE(SUM(r.hores_totals), 0) AS hores_totals,
            COUNT(r.id) AS total_registres,
            COUNT(DISTINCT r.data) AS dies_treballats
     FROM usuaris u
     LEFT JOIN registres_hores r ON u.id = r.usuari_id AND r.hora_sortida IS NOT NULL
     GROUP BY u.id, u.nom, u.cognom
     ORDER BY u.rol ASC, u.nom ASC'
);
$resum_usuaris = $stmt->fetchAll();

// RESUM: hores per projecte
$stmt = $pdo->query(
    'SELECT p.id, p.nom, p.hores_estimades,
            COALESCE(SUM(r.hores_totals), 0) AS hores_realitzades,
            COUNT(DISTINCT r.usuari_id) AS usuaris_assignats,
            COUNT(DISTINCT r.data) AS dies_treballats
     FROM projectes p
     LEFT JOIN registres_hores r ON p.id = r.projecte_id AND r.hora_sortida IS NOT NULL
     GROUP BY p.id, p.nom, p.hores_estimades
     ORDER BY p.nom ASC'
);
$resum_projectes = $stmt->fetchAll();

// LLISTA VERMELLA D'INCOMPLIMENT:
// Empleats que avui no han fitxat entrada O han fitxat menys de 8 hores
$stmt = $pdo->prepare(
    'SELECT u.id, u.nom, u.cognom, u.email,
            r.hora_entrada, r.hora_sortida, r.hores_totals
     FROM usuaris u
     LEFT JOIN registres_hores r ON u.id = r.usuari_id AND r.data = CURDATE()
     WHERE u.rol = \'empleat\'
       AND (
           r.id IS NULL
           OR r.hores_totals IS NULL
           OR r.hores_totals < 8
       )
     ORDER BY u.nom ASC'
);
$stmt->execute();
$incomplidors = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WorkTracker — Administració</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f7f6; color: #333; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 30px; }
        h1 { font-size: 1.8rem; color: #2c3e50; }
        h2 { font-size: 1.2rem; color: #7f8c8d; font-weight: 400; margin-bottom: 20px; }

        .tabs { display: flex; gap: 0; margin-bottom: 20px; border-bottom: 2px solid #ecf0f1; flex-wrap: wrap; }
        .tabs a {
            padding: 12px 24px; text-decoration: none; color: #7f8c8d; font-weight: 600;
            border-bottom: 2px solid transparent; margin-bottom: -2px; transition: 0.2s;
        }
        .tabs a:hover { color: #2c3e50; }
        .tabs a.active { color: #3498db; border-bottom-color: #3498db; }

        .form-inline { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin: 5px 0; }
        .form-inline input, .form-inline select, .form-inline textarea {
            padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 0.9rem;
        }
        .form-inline button { padding: 8px 16px; }

        .card { background: #f9f9f9; border: 1px solid #e0e0e0; border-radius: 6px; padding: 20px; margin-bottom: 20px; }
        .card h3 { margin-bottom: 15px; color: #2c3e50; }

        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #e0e0e0; }
        th { background: #ecf0f1; font-weight: 600; color: #2c3e50; white-space: nowrap; }
        tr:hover { background: #f5f5f5; }

        button, .btn {
            background: #3498db; color: #fff; border: none; padding: 8px 16px; font-size: 0.9rem;
            border-radius: 4px; cursor: pointer; transition: 0.2s; display: inline-block;
        }
        button:hover { background: #2980b9; }
        .btn-danger { background: #e74c3c; }
        .btn-danger:hover { background: #c0392b; }
        .btn-success { background: #27ae60; }
        .btn-success:hover { background: #219a52; }
        .btn-warning { background: #f39c12; }
        .btn-warning:hover { background: #d68910; }
        .btn-sm { padding: 5px 10px; font-size: 0.8rem; }

        /* ----- LLISTA VERMELLA ----- */
        .llista-vermella { background: #fff5f5; border: 2px solid #e74c3c; border-radius: 8px; padding: 20px; margin-bottom: 20px; }
        .llista-vermella h3 { color: #c0392b; margin-bottom: 15px; }
        .llista-vermella table { background: #fff; }
        .llista-vermella th { background: #fdecea; color: #c0392b; }
        .llista-vermella tr.incomplidor {
            background: #fdecea !important;
            border-left: 4px solid #e74c3c;
        }
        .llista-vermella tr.incomplidor td:first-child {
            font-weight: 700;
            color: #c0392b;
        }
        .badge-risc {
            display: inline-block; padding: 3px 10px; border-radius: 12px;
            background: #fdecea; color: #c0392b; font-weight: 700; font-size: 0.8rem;
        }

        .error { background: #fdecea; color: #c0392b; border: 1px solid #c0392b; padding: 10px 15px; border-radius: 4px; margin-bottom: 20px; }
        .success { background: #e8f8f5; color: #27ae60; border: 1px solid #27ae60; padding: 10px 15px; border-radius: 4px; margin-bottom: 20px; }

        .resum-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 20px; }
        .resum-card { background: #f9f9f9; border: 1px solid #e0e0e0; border-radius: 6px; padding: 20px; text-align: center; }
        .resum-card .num { font-size: 2.2rem; font-weight: 700; color: #3498db; }
        .resum-card .label { color: #7f8c8d; font-size: 0.9rem; }
        .resum-card.danger .num { color: #e74c3c; }

        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 999; }
        .modal-overlay:target { display: flex; align-items: center; justify-content: center; }
        .modal { background: #fff; border-radius: 8px; padding: 25px; max-width: 500px; width: 90%; max-height: 80vh; overflow-y: auto; }
        .modal h3 { margin-bottom: 15px; }
        .modal form div { margin-bottom: 12px; }
        .modal label { display: block; font-weight: 600; margin-bottom: 4px; }
        .modal input, .modal select, .modal textarea { width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; }
        .modal .btn-group { display: flex; gap: 10px; margin-top: 15px; }
        .modal .btn-group button { flex: 1; }
        .cerrar-modal { float: right; font-size: 1.5rem; text-decoration: none; color: #7f8c8d; }
        .cerrar-modal:hover { color: #333; }

        nav { margin-bottom: 20px; }
        nav a { color: #3498db; text-decoration: none; }
        nav a:hover { text-decoration: underline; }
        .small { font-size: 0.85rem; color: #7f8c8d; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 0.8rem; font-weight: 600; }
        .badge-admin { background: #fcf3cf; color: #7d6608; }
        .badge-empleat { background: #d5f5e3; color: #1e8449; }
    </style>
</head>
<body>
<div class="container">
    <h1>WorkTracker</h1>
    <h2>Panell d'Administrador</h2>
    <p>Benvingut, <strong><?= htmlspecialchars($usuari['nom'] . ' ' . $usuari['cognom'], ENT_QUOTES, 'UTF-8') ?></strong></p>

    <nav><a href="../auth/logout.php">Tancar sessió</a></nav>

    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($success): ?><div class="success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

    <div class="tabs">
        <a href="?seccio=resum"  class="<?= $seccio === 'resum'  ? 'active' : '' ?>">📊 Resum</a>
        <a href="?seccio=usuaris"  class="<?= $seccio === 'usuaris'  ? 'active' : '' ?>">👥 Usuaris</a>
        <a href="?seccio=projectes" class="<?= $seccio === 'projectes' ? 'active' : '' ?>">📁 Projectes</a>
        <a href="?seccio=incomplidors" class="<?= $seccio === 'incomplidors' ? 'active' : '' ?>">🚨 Llista vermella</a>
    </div>

    <!-- ================================================ -->
    <!-- SECCIÓ: RESUM -->
    <!-- ================================================ -->
    <?php if ($seccio === 'resum'): ?>
    <div class="resum-grid">
        <div class="resum-card">
            <div class="num"><?= count($resum_usuaris) ?></div>
            <div class="label">Total usuaris</div>
        </div>
        <div class="resum-card">
            <div class="num"><?= count($resum_projectes) ?></div>
            <div class="label">Total projectes</div>
        </div>
        <div class="resum-card <?= count($incomplidors) > 0 ? 'danger' : '' ?>">
            <div class="num"><?= count($incomplidors) ?></div>
            <div class="label">Incomplidors avui</div>
        </div>
    </div>

    <div class="card">
        <h3>Hores per usuari</h3>
        <?php if (count($resum_usuaris) > 0): ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr><th>Usuari</th><th>Rol</th><th>Total hores</th><th>Registres</th><th>Dies treballats</th><th>Mitjana h/dia</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($resum_usuaris as $ru): ?>
                    <?php
                        $nom_complet = $ru['nom'] . ' ' . $ru['cognom'];
                        // Obtenir el rol d'aquest usuari
                        $rol_usuari = 'empleat';
                        foreach ($usuaris as $u) {
                            if ((int)$u['id'] === (int)$ru['id']) { $rol_usuari = $u['rol']; break; }
                        }
                        $dies = (int)$ru['dies_treballats'];
                        $mitjana = $dies > 0 ? round((float)$ru['hores_totals'] / $dies, 2) : 0;
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($nom_complet, ENT_QUOTES, 'UTF-8') ?></td>
                        <td><span class="badge <?= $rol_usuari === 'admin' ? 'badge-admin' : 'badge-empleat' ?>"><?= $rol_usuari ?></span></td>
                        <td><strong><?= number_format((float)$ru['hores_totals'], 2) ?> h</strong></td>
                        <td><?= (int)$ru['total_registres'] ?></td>
                        <td><?= $dies ?></td>
                        <td><?= number_format($mitjana, 2) ?> h</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <p class="small">No hi ha dades disponibles.</p>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3>Hores per projecte</h3>
        <?php if (count($resum_projectes) > 0): ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr><th>Projecte</th><th>H. estimades</th><th>H. realitzades</th><th>% Completat</th><th>Usuaris</th><th>Dies treballats</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($resum_projectes as $rp): ?>
                    <?php
                        $est = (float)$rp['hores_estimades'];
                        $real = (float)$rp['hores_realitzades'];
                        $pct = $est > 0 ? round(($real / $est) * 100, 1) : 0;
                        $color = $pct > 100 ? '#e74c3c' : ($pct > 75 ? '#f39c12' : '#27ae60');
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($rp['nom'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                        <td><?= number_format($est, 2) ?> h</td>
                        <td><?= number_format($real, 2) ?> h</td>
                        <td><span style="color:<?= $color ?>;font-weight:700;"><?= $pct ?>%</span></td>
                        <td><?= (int)$rp['usuaris_assignats'] ?></td>
                        <td><?= (int)$rp['dies_treballats'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <p class="small">No hi ha dades disponibles.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ================================================ -->
    <!-- SECCIÓ: USUARIS -->
    <!-- ================================================ -->
    <?php if ($seccio === 'usuaris'): ?>
    <div class="card">
        <h3>Crear empleat</h3>
        <form method="POST" action="?seccio=usuaris" class="form-inline">
            <input type="text"     name="nom"     placeholder="Nom" required>
            <input type="text"     name="cognom"  placeholder="Cognom" required>
            <input type="email"    name="email"   placeholder="Email" required>
            <input type="password" name="password" placeholder="Contrasenya" required minlength="6">
            <select name="rol">
                <option value="empleat">Empleat</option>
                <option value="admin">Admin</option>
            </select>
            <button type="submit" name="crear_usuari" class="btn-success">Crear</button>
        </form>
    </div>

    <div class="card">
        <h3>Llistat d'usuaris</h3>
        <?php if (count($usuaris) > 0): ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr><th>ID</th><th>Nom complet</th><th>Email</th><th>Rol</th><th>Creat</th><th>Accions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($usuaris as $u): ?>
                    <tr>
                        <td><?= (int)$u['id'] ?></td>
                        <td><?= htmlspecialchars($u['nom'] . ' ' . $u['cognom'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><span class="badge <?= $u['rol'] === 'admin' ? 'badge-admin' : 'badge-empleat' ?>"><?= $u['rol'] ?></span></td>
                        <td><?= date('d/m/Y', strtotime($u['creat_at'])) ?></td>
                        <td>
                            <a href="#editar_u_<?= $u['id'] ?>" class="btn btn-warning btn-sm">✏️</a>
                            <?php if ((int)$u['id'] !== (int)$usuari['id']): ?>
                            <form method="POST" action="?seccio=usuaris" style="display:inline" onsubmit="return confirm('Eliminar usuari?')">
                                <input type="hidden" name="uid" value="<?= $u['id'] ?>">
                                <button type="submit" name="eliminar_usuari" class="btn-danger btn-sm">🗑️</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <div id="editar_u_<?= $u['id'] ?>" class="modal-overlay">
                        <div class="modal">
                            <a href="#" class="cerrar-modal">&times;</a>
                            <h3>Editar: <?= htmlspecialchars($u['nom'] . ' ' . $u['cognom'], ENT_QUOTES, 'UTF-8') ?></h3>
                            <form method="POST" action="?seccio=usuaris">
                                <input type="hidden" name="uid" value="<?= $u['id'] ?>">
                                <div><label>Nom</label><input type="text" name="nom" value="<?= htmlspecialchars($u['nom'], ENT_QUOTES, 'UTF-8') ?>" required></div>
                                <div><label>Cognom</label><input type="text" name="cognom" value="<?= htmlspecialchars($u['cognom'], ENT_QUOTES, 'UTF-8') ?>" required></div>
                                <div><label>Email</label><input type="email" name="email" value="<?= htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8') ?>" required></div>
                                <div><label>Nova contrasenya (deixar buit = no canviar)</label><input type="password" name="new_password" placeholder="Mínim 6 caràcters" minlength="6"></div>
                                <div><label>Rol</label>
                                    <select name="rol">
                                        <option value="empleat" <?= $u['rol'] === 'empleat' ? 'selected' : '' ?>>Empleat</option>
                                        <option value="admin"   <?= $u['rol'] === 'admin'   ? 'selected' : '' ?>>Admin</option>
                                    </select>
                                </div>
                                <div class="btn-group">
                                    <button type="submit" name="editar_usuari" class="btn-success">Desar</button>
                                    <a href="#" class="btn" style="text-align:center;text-decoration:none;background:#95a5a6;">Cancel·lar</a>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <p class="small">No hi ha usuaris.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ================================================ -->
    <!-- SECCIÓ: PROJECTES -->
    <!-- ================================================ -->
    <?php if ($seccio === 'projectes'): ?>
    <div class="card">
        <h3>Crear projecte</h3>
        <form method="POST" action="?seccio=projectes" class="form-inline">
            <input type="text"     name="nom"             placeholder="Nom del projecte" required style="min-width:200px">
            <input type="text"     name="descripcio"      placeholder="Descripció (opcional)" style="min-width:250px">
            <input type="number"   step="0.01" min="0"    name="hores_estimades" placeholder="H. estimades" value="0" style="width:120px">
            <button type="submit"  name="crear_projecte"  class="btn-success">Crear</button>
        </form>
    </div>

    <div class="card">
        <h3>Llistat de projectes</h3>
        <?php if (count($projectes) > 0): ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr><th>ID</th><th>Nom</th><th>Descripció</th><th>H. estimades</th><th>Creat</th><th>Accions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($projectes as $p): ?>
                    <tr>
                        <td><?= (int)$p['id'] ?></td>
                        <td><strong><?= htmlspecialchars($p['nom'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                        <td><?= htmlspecialchars($p['descripcio'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= number_format((float)$p['hores_estimades'], 2) ?> h</td>
                        <td><?= date('d/m/Y', strtotime($p['creat_at'])) ?></td>
                        <td>
                            <a href="#editar_p_<?= $p['id'] ?>" class="btn btn-warning btn-sm">✏️</a>
                            <form method="POST" action="?seccio=projectes" style="display:inline" onsubmit="return confirm('Eliminar projecte?')">
                                <input type="hidden" name="pid" value="<?= $p['id'] ?>">
                                <button type="submit" name="eliminar_projecte" class="btn-danger btn-sm">🗑️</button>
                            </form>
                        </td>
                    </tr>
                    <div id="editar_p_<?= $p['id'] ?>" class="modal-overlay">
                        <div class="modal">
                            <a href="#" class="cerrar-modal">&times;</a>
                            <h3>Editar projecte: <?= htmlspecialchars($p['nom'], ENT_QUOTES, 'UTF-8') ?></h3>
                            <form method="POST" action="?seccio=projectes">
                                <input type="hidden" name="pid" value="<?= $p['id'] ?>">
                                <div><label>Nom</label><input type="text" name="nom" value="<?= htmlspecialchars($p['nom'], ENT_QUOTES, 'UTF-8') ?>" required></div>
                                <div><label>Descripció</label><textarea name="descripcio"><?= htmlspecialchars($p['descripcio'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea></div>
                                <div><label>Hores estimades</label><input type="number" step="0.01" min="0" name="hores_estimades" value="<?= (float)$p['hores_estimades'] ?>"></div>
                                <div class="btn-group">
                                    <button type="submit" name="editar_projecte" class="btn-success">Desar</button>
                                    <a href="#" class="btn" style="text-align:center;text-decoration:none;background:#95a5a6;">Cancel·lar</a>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <p class="small">No hi ha projectes.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ================================================ -->
    <!-- SECCIÓ: LLISTA VERMELLA D'INCOMPLIMENT -->
    <!-- ================================================ -->
    <?php if ($seccio === 'incomplidors'): ?>
    <div class="llista-vermella">
        <h3>🚨 Llista vermella d'incompliment</h3>
        <p class="small" style="margin-bottom:15px;">
            Empleats que <strong>avui</strong> no han fitxat entrada o han treballat menys de 8 hores.
        </p>

        <?php if (count($incomplidors) > 0): ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Empleat</th>
                        <th>Email</th>
                        <th>Hora entrada</th>
                        <th>Hora sortida</th>
                        <th>Hores totals</th>
                        <th>Motiu</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($incomplidors as $inc): ?>
                    <?php
                        $hora_entrada = $inc['hora_entrada'] ?? null;
                        $hora_sortida = $inc['hora_sortida'] ?? null;
                        $hores        = $inc['hores_totals'] ?? null;

                        if (is_null($hora_entrada)) {
                            $motiu = '❌ No ha fitxat entrada';
                        } elseif (is_null($hores)) {
                            $motiu = '⏳ Ha fitxat entrada però no sortida (registre obert)';
                        } elseif ((float)$hores < 8) {
                            $motiu = '⚠️ Menys de 8 h (' . number_format((float)$hores, 2) . ' h)';
                        } else {
                            $motiu = '';
                        }
                    ?>
                    <tr class="incomplidor">
                        <td><?= htmlspecialchars($inc['nom'] . ' ' . $inc['cognom'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($inc['email'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= $hora_entrada ? date('H:i:s', strtotime($hora_entrada)) : '—' ?></td>
                        <td><?= $hora_sortida ? date('H:i:s', strtotime($hora_sortida)) : '—' ?></td>
                        <td><?= $hores ? number_format((float)$hores, 2) . ' h' : '—' ?></td>
                        <td><span class="badge-risc"><?= $motiu ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <p style="color:#27ae60;font-weight:700;">✅ Tots els empleats han fitxat correctament avui (8+ hores).</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
</body>
</html>