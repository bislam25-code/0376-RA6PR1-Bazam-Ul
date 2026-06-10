<?php
/*
 * WorkTracker — Panell d'Administrador
 * Fitxer: admin/dashboard.php
 */

require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../config/db.php';

// Protegir: només usuaris amb rol admin
if (!isset($_SESSION['usuari']) || $_SESSION['usuari']['rol'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$usuari    = $_SESSION['usuari'];
$error     = '';
$success   = '';
$seccio    = $_GET['seccio'] ?? 'resum';

// ============================================================
// GESTIÓ D'USUARIS
// ============================================================

if (isset($_POST['crear_usuari'])) { csrf_verify();
    $nom = trim($_POST['nom'] ?? ''); $cognom = trim($_POST['cognom'] ?? '');
    $email = trim($_POST['email'] ?? ''); $password = $_POST['password'] ?? ''; $rol = $_POST['rol'] ?? 'empleat';
    if (empty($nom)||empty($cognom)||empty($email)||empty($password)) $error = 'Tots els camps obligatoris.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'Email no vàlid.';
    elseif (strlen($password) < 6) $error = 'Mínim 6 caràcters.';
    else {
        $stmt = $pdo->prepare('SELECT id FROM usuaris WHERE email=:email LIMIT 1'); $stmt->execute([':email'=>$email]);
        if ($stmt->fetch()) $error = 'Email ja registrat.';
        else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO usuaris (nom,cognom,email,password_hash,rol) VALUES (:n,:c,:e,:h,:r)');
            $stmt->execute([':n'=>$nom,':c'=>$cognom,':e'=>$email,':h'=>$hash,':r'=>$rol]);
            $success = "Usuari $nom $cognom creat.";
        }
    }
}

if (isset($_POST['editar_usuari'])) { csrf_verify();
    $uid=(int)($_POST['uid']??0); $nom=trim($_POST['nom']??''); $cognom=trim($_POST['cognom']??'');
    $email=trim($_POST['email']??''); $rol=$_POST['rol']??'empleat'; $new_pwd=$_POST['new_password']??'';
    if ($uid<=0||empty($nom)||empty($cognom)||empty($email)) $error='Dades incompletes.';
    elseif (!filter_var($email,FILTER_VALIDATE_EMAIL)) $error='Email no vàlid.';
    else {
        if (!empty($new_pwd)) {
            if (strlen($new_pwd)<6) $error='Mínim 6 caràcters.';
            else {
                $hash=password_hash($new_pwd,PASSWORD_DEFAULT);
                $stmt=$pdo->prepare('UPDATE usuaris SET nom=:n,cognom=:c,email=:e,rol=:r,password_hash=:h WHERE id=:uid');
                $stmt->execute([':n'=>$nom,':c'=>$cognom,':e'=>$email,':r'=>$rol,':h'=>$hash,':uid'=>$uid]);
                $success='Usuari actualitzat.';
            }
        } else {
            $stmt=$pdo->prepare('UPDATE usuaris SET nom=:n,cognom=:c,email=:e,rol=:r WHERE id=:uid');
            $stmt->execute([':n'=>$nom,':c'=>$cognom,':e'=>$email,':r'=>$rol,':uid'=>$uid]);
            $success='Usuari actualitzat.';
        }
    }
}

if (isset($_POST['eliminar_usuari'])) { csrf_verify();
    $uid=(int)($_POST['uid']??0);
    if ($uid>0 && $uid!==(int)$usuari['id']) {
        $pdo->prepare('DELETE FROM usuaris WHERE id=:uid')->execute([':uid'=>$uid]);
        $success='Usuari eliminat.';
    } else $error='No et pots eliminar a tu mateix.';
}

// ============================================================
// GESTIÓ DE PROJECTES
// ============================================================

if (isset($_POST['crear_projecte'])) { csrf_verify();
    $nom=trim($_POST['nom']??''); $descripcio=trim($_POST['descripcio']??''); $hores_est=(float)($_POST['hores_estimades']??0);
    if (empty($nom)) $error='El nom és obligatori.';
    elseif ($hores_est<0) $error='Hores no negatives.';
    else {
        $stmt=$pdo->prepare('INSERT INTO projectes (nom,descripcio,hores_estimades) VALUES (:n,:d,:h)');
        $stmt->execute([':n'=>$nom,':d'=>$descripcio?:null,':h'=>$hores_est]);
        $success="Projecte '$nom' creat.";
    }
}

if (isset($_POST['editar_projecte'])) { csrf_verify();
    $pid=(int)($_POST['pid']??0); $nom=trim($_POST['nom']??''); $descripcio=trim($_POST['descripcio']??'');
    $hores_est=(float)($_POST['hores_estimades']??0);
    if ($pid<=0||empty($nom)) $error='Dades incompletes.';
    else {
        $stmt=$pdo->prepare('UPDATE projectes SET nom=:n,descripcio=:d,hores_estimades=:h WHERE id=:pid');
        $stmt->execute([':n'=>$nom,':d'=>$descripcio?:null,':h'=>$hores_est,':pid'=>$pid]);
        $success='Projecte actualitzat.';
    }
}

if (isset($_POST['eliminar_projecte'])) { csrf_verify();
    $pid=(int)($_POST['pid']??0);
    if ($pid>0) { $pdo->prepare('DELETE FROM projectes WHERE id=:pid')->execute([':pid'=>$pid]); $success='Projecte eliminat.'; }
}

// ============================================================
// CONSULTES
// ============================================================

$stmt = $pdo->query('SELECT id,nom,cognom,email,rol,creat_at FROM usuaris ORDER BY id ASC');
$usuaris = $stmt->fetchAll();

$stmt = $pdo->query('SELECT id,nom,descripcio,hores_estimades,creat_at FROM projectes ORDER BY nom ASC');
$projectes = $stmt->fetchAll();

$stmt = $pdo->query(
    'SELECT u.id,u.nom,u.cognom,COALESCE(SUM(r.hores_totals),0) AS hores_totals,COUNT(r.id) AS total_registres,COUNT(DISTINCT r.data) AS dies_treballats
     FROM usuaris u LEFT JOIN registres_hores r ON u.id=r.usuari_id AND r.hora_sortida IS NOT NULL
     GROUP BY u.id,u.nom,u.cognom ORDER BY u.rol ASC,u.nom ASC');
$resum_usuaris = $stmt->fetchAll();

$stmt = $pdo->query(
    'SELECT p.id,p.nom,p.hores_estimades,COALESCE(SUM(r.hores_totals),0) AS hores_realitzades,
            COUNT(DISTINCT r.usuari_id) AS usuaris_assignats,COUNT(DISTINCT r.data) AS dies_treballats
     FROM projectes p LEFT JOIN registres_hores r ON p.id=r.projecte_id AND r.hora_sortida IS NOT NULL
     GROUP BY p.id,p.nom,p.hores_estimades ORDER BY p.nom ASC');
$resum_projectes = $stmt->fetchAll();

$stmt = $pdo->prepare(
    'SELECT u.id,u.nom,u.cognom,u.email,r.hora_entrada,r.hora_sortida,r.hores_totals
     FROM usuaris u LEFT JOIN registres_hores r ON u.id=r.usuari_id AND r.data=CURDATE()
     WHERE u.rol=\'empleat\' AND (r.id IS NULL OR r.hores_totals IS NULL OR r.hores_totals<8)
     ORDER BY u.nom ASC');
$stmt->execute();
$incomplidors = $stmt->fetchAll();

// Alertes
$alertes = obtenir_alertes_incompliment($pdo);
?>
<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WorkTracker — Administració</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; background:#f4f7f6; color:#333; padding:20px; }
        .container { max-width:1200px; margin:0 auto; background:#fff; border-radius:8px; box-shadow:0 2px 10px rgba(0,0,0,0.1); padding:30px; }
        h1 { font-size:1.8rem; color:#2c3e50; }
        h2 { font-size:1.2rem; color:#7f8c8d; font-weight:400; margin-bottom:20px; }
        .tabs { display:flex; gap:0; margin-bottom:20px; border-bottom:2px solid #ecf0f1; flex-wrap:wrap; }
        .tabs a { padding:12px 24px; text-decoration:none; color:#7f8c8d; font-weight:600; border-bottom:2px solid transparent; margin-bottom:-2px; transition:0.2s; }
        .tabs a:hover { color:#2c3e50; }
        .tabs a.active { color:#3498db; border-bottom-color:#3498db; }
        .card { background:#f9f9f9; border:1px solid #e0e0e0; border-radius:6px; padding:20px; margin-bottom:20px; }
        .card h3 { margin-bottom:15px; color:#2c3e50; }
        .table-wrapper { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; }
        th,td { padding:10px 12px; text-align:left; border-bottom:1px solid #e0e0e0; }
        th { background:#ecf0f1; font-weight:600; color:#2c3e50; white-space:nowrap; }
        tr:hover { background:#f5f5f5; }
        button,.btn { background:#3498db; color:#fff; border:none; padding:8px 16px; font-size:0.9rem; border-radius:4px; cursor:pointer; transition:0.2s; display:inline-block; }
        button:hover { background:#2980b9; }
        .btn-danger { background:#e74c3c; }
        .btn-danger:hover { background:#c0392b; }
        .btn-success { background:#27ae60; }
        .btn-success:hover { background:#219a52; }
        .btn-warning { background:#f39c12; }
        .btn-warning:hover { background:#d68910; }
        .btn-sm { padding:5px 10px; font-size:0.8rem; }
        .error { background:#fdecea; color:#c0392b; border:1px solid #c0392b; padding:10px 15px; border-radius:4px; margin-bottom:20px; }
        .success { background:#e8f8f5; color:#27ae60; border:1px solid #27ae60; padding:10px 15px; border-radius:4px; margin-bottom:20px; }
        .small { font-size:0.85rem; color:#7f8c8d; }
        .badge { display:inline-block; padding:3px 10px; border-radius:12px; font-size:0.8rem; font-weight:600; }
        .badge-admin { background:#fcf3cf; color:#7d6608; }
        .badge-empleat { background:#d5f5e3; color:#1e8449; }
        .resum-rapid { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:12px; margin-bottom:20px; }
        .resum-rapid .card-rapid { background:#f9f9f9; border:1px solid #e0e0e0; border-radius:6px; padding:12px; text-align:center; }
        .resum-rapid .card-rapid .num { font-size:1.6rem; font-weight:700; color:#3498db; }
        .resum-rapid .card-rapid .label { font-size:0.8rem; color:#7f8c8d; margin-top:2px; }
        .resum-rapid .card-rapid.danger .num { color:#e74c3c; }
        .resum-rapid .card-rapid.success .num { color:#27ae60; }
        .resum-grid { display:grid; grid-template-columns:1fr 1fr 1fr; gap:15px; margin-bottom:20px; }
        .resum-card { background:#f9f9f9; border:1px solid #e0e0e0; border-radius:6px; padding:20px; text-align:center; }
        .resum-card .num { font-size:2.2rem; font-weight:700; color:#3498db; }
        .resum-card .label { color:#7f8c8d; font-size:0.9rem; }
        .resum-card.danger .num { color:#e74c3c; }
        .modal-overlay { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:999; }
        .modal-overlay:target { display:flex; align-items:center; justify-content:center; }
        .modal { background:#fff; border-radius:8px; padding:25px; max-width:500px; width:90%; max-height:80vh; overflow-y:auto; }
        .modal h3 { margin-bottom:15px; }
        .modal form div { margin-bottom:12px; }
        .modal label { display:block; font-weight:600; margin-bottom:4px; }
        .modal input,.modal select,.modal textarea { width:100%; padding:8px 10px; border:1px solid #ccc; border-radius:4px; }
        .modal .btn-group { display:flex; gap:10px; margin-top:15px; }
        .modal .btn-group button { flex:1; }
        .cerrar-modal { float:right; font-size:1.5rem; text-decoration:none; color:#7f8c8d; }
        .cerrar-modal:hover { color:#333; }
        nav { margin-bottom:20px; }
        nav a { color:#3498db; text-decoration:none; }
        nav a:hover { text-decoration:underline; }
        .form-inline { display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin:5px 0; }
        .form-inline input,.form-inline select,.form-inline textarea { padding:8px 10px; border:1px solid #ccc; border-radius:4px; font-size:0.9rem; }
        .form-inline button { padding:8px 16px; }
        @media (max-width:768px) { .resum-rapid { grid-template-columns:1fr 1fr; } .resum-grid { grid-template-columns:1fr; } }
    </style>
</head>
<body>
<div class="container">
    <h1>WorkTracker</h1>
    <h2>Panell d'Administrador</h2>
    <p>Benvingut, <strong><?= htmlspecialchars($usuari['nom'].' '.$usuari['cognom'],ENT_QUOTES,'UTF-8') ?></strong></p>
    <nav><a href="../auth/logout.php">Tancar sessió</a></nav>

    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error,ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>
    <?php if ($success): ?><div class="success"><?= htmlspecialchars($success,ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>

    <div class="tabs">
        <a href="?seccio=resum" class="<?=$seccio==='resum'?'active':''?>">📊 Resum</a>
        <a href="?seccio=usuaris" class="<?=$seccio==='usuaris'?'active':''?>">👥 Usuaris</a>
        <a href="?seccio=projectes" class="<?=$seccio==='projectes'?'active':''?>">📁 Projectes</a>
        <a href="?seccio=incomplidors" class="<?=$seccio==='incomplidors'?'active':''?>">🚨 Llista vermella</a>
        <a href="reports.php">📈 Reports</a>
    </div>

    <!-- ALERTES D'INCOMPLIMENT (sempre visible) -->
    <?php if (count($alertes) > 0): ?>
    <div class="error">
        <strong>⚠️ Alertes d'incompliment:</strong>
        <ul style="margin:5px 0 0 15px;">
        <?php foreach ($alertes as $a): ?>
            <li><?= htmlspecialchars($a['nom'].' '.$a['cognom'],ENT_QUOTES,'UTF-8') ?>
                — fitxat a les <?= date('H:i',strtotime($a['hora_entrada'])) ?>,
                total: <?= number_format((float)$a['hores_totals'],2) ?> h
            </li>
        <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- RESUM RÀPID -->
    <?php
    $stmt_rr=$pdo->prepare("SELECT COUNT(DISTINCT usuari_id) FROM registres_hores WHERE data=CURDATE() AND hora_entrada IS NOT NULL");
    $stmt_rr->execute(); $actius_avui=(int)$stmt_rr->fetchColumn();
    $stmt_rr=$pdo->query("SELECT COUNT(*) FROM (SELECT p.id,COALESCE(SUM(r.hores_totals),0)-p.hores_estimades AS diff FROM projectes p LEFT JOIN registres_hores r ON p.id=r.projecte_id AND r.hora_sortida IS NOT NULL GROUP BY p.id,p.hores_estimades HAVING diff<=0) AS sub");
    $projectes_superavit=(int)$stmt_rr->fetchColumn();
    $stmt_rr=$pdo->prepare("SELECT COUNT(*) FROM usuaris u WHERE u.rol='empleat' AND NOT EXISTS (SELECT 1 FROM registres_hores r WHERE r.usuari_id=u.id AND r.data=CURDATE())");
    $stmt_rr->execute(); $sense_fitxar=(int)$stmt_rr->fetchColumn();
    ?>
    <div class="resum-rapid">
        <div class="card-rapid success"><div class="num"><?=$actius_avui?></div><div class="label">✅ Actius avui</div></div>
        <div class="card-rapid"><div class="num"><?=$projectes_superavit?></div><div class="label">📊 Superàvit</div></div>
        <div class="card-rapid <?=$sense_fitxar>0?'danger':'success'?>"><div class="num"><?=$sense_fitxar?></div><div class="label"><?=$sense_fitxar>0?'⛔ Sense fitxar':'✅ Tots'?></div></div>
    </div>

    <!-- SECCIÓ: RESUM -->
    <?php if ($seccio==='resum'): ?>
    <div class="resum-grid">
        <div class="resum-card"><div class="num"><?=count($resum_usuaris)?></div><div class="label">Total usuaris</div></div>
        <div class="resum-card"><div class="num"><?=count($resum_projectes)?></div><div class="label">Total projectes</div></div>
        <div class="resum-card <?=count($incomplidors)>0?'danger':''?>"><div class="num"><?=count($incomplidors)?></div><div class="label">Incomplidors avui</div></div>
    </div>
    <div class="card">
        <h3>Hores per usuari</h3>
        <?php if (count($resum_usuaris)>0): ?>
        <div class="table-wrapper"><table><thead><tr><th>Usuari</th><th>Rol</th><th>Total hores</th><th>Registres</th><th>Dies</th><th>Mitjana h/dia</th></tr></thead>
        <tbody><?php foreach($resum_usuaris as $ru):
            $rol_u='empleat'; foreach($usuaris as $u) { if((int)$u['id']===(int)$ru['id']){$rol_u=$u['rol'];break;} }
            $dies=max((int)$ru['dies_treballats'],1); $mitjana=round((float)$ru['hores_totals']/$dies,2);
        ?><tr><td><?=htmlspecialchars($ru['nom'].' '.$ru['cognom'],ENT_QUOTES,'UTF-8')?></td>
        <td><span class="badge <?=$rol_u==='admin'?'badge-admin':'badge-empleat'?>"><?=$rol_u?></span></td>
        <td><strong><?=number_format((float)$ru['hores_totals'],2)?> h</strong></td>
        <td><?=(int)$ru['total_registres']?></td><td><?=$dies?></td><td><?=number_format($mitjana,2)?> h</td></tr>
        <?php endforeach;?></tbody></table></div>
        <?php else: ?><p class="small">No hi ha dades.</p><?php endif; ?>
    </div>
    <div class="card">
        <h3>Hores per projecte</h3>
        <?php if (count($resum_projectes)>0): ?>
        <div class="table-wrapper"><table><thead><tr><th>Projecte</th><th>H. estimades</th><th>H. realitzades</th><th>%</th><th>Usuaris</th><th>Dies</th></tr></thead>
        <tbody><?php foreach($resum_projectes as $rp):
            $est=(float)$rp['hores_estimades'];$real=(float)$rp['hores_realitzades'];$pct=$est>0?round(($real/$est)*100,1):0;
            $color=$pct>100?'#e74c3c':($pct>75?'#f39c12':'#27ae60');
        ?><tr><td><strong><?=htmlspecialchars($rp['nom'],ENT_QUOTES,'UTF-8')?></strong></td>
        <td><?=number_format($est,2)?> h</td><td><?=number_format($real,2)?> h</td>
        <td><span style="color:<?=$color?>;font-weight:700;"><?=$pct?>%</span></td>
        <td><?=(int)$rp['usuaris_assignats']?></td><td><?=(int)$rp['dies_treballats']?></td></tr>
        <?php endforeach;?></tbody></table></div>
        <?php else: ?><p class="small">No hi ha dades.</p><?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- SECCIÓ: USUARIS -->
    <?php if ($seccio==='usuaris'): ?>
    <div class="card">
        <h3>Crear empleat</h3>
        <form method="POST" action="?seccio=usuaris" class="form-inline">
            <?php csrf_field(); ?>
            <input type="text" name="nom" placeholder="Nom" required>
            <input type="text" name="cognom" placeholder="Cognom" required>
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Contrasenya" required minlength="6">
            <select name="rol"><option value="empleat">Empleat</option><option value="admin">Admin</option></select>
            <button type="submit" name="crear_usuari" class="btn-success">Crear</button>
        </form>
    </div>
    <div class="card">
        <h3>Llistat d'usuaris</h3>
        <?php if (count($usuaris)>0): ?>
        <div class="table-wrapper"><table><thead><tr><th>ID</th><th>Nom</th><th>Email</th><th>Rol</th><th>Creat</th><th>Accions</th></tr></thead>
        <tbody><?php foreach($usuaris as $u): ?>
        <tr><td><?=(int)$u['id']?></td>
        <td><?=htmlspecialchars($u['nom'].' '.$u['cognom'],ENT_QUOTES,'UTF-8')?></td>
        <td><?=htmlspecialchars($u['email'],ENT_QUOTES,'UTF-8')?></td>
        <td><span class="badge <?=$u['rol']==='admin'?'badge-admin':'badge-empleat'?>"><?=$u['rol']?></span></td>
        <td><?=date('d/m/Y',strtotime($u['creat_at']))?></td>
        <td>
            <a href="#editar_u_<?=$u['id']?>" class="btn btn-warning btn-sm">✏️</a>
            <?php if((int)$u['id']!==(int)$usuari['id']): ?>
            <form method="POST" action="?seccio=usuaris" style="display:inline" onsubmit="return confirm('Eliminar usuari?')">
                <?php csrf_field(); ?>
                <input type="hidden" name="uid" value="<?=$u['id']?>">
                <button type="submit" name="eliminar_usuari" class="btn-danger btn-sm">🗑️</button>
            </form>
            <?php endif; ?>
        </td></tr>
        <div id="editar_u_<?=$u['id']?>" class="modal-overlay"><div class="modal">
            <a href="#" class="cerrar-modal">&times;</a>
            <h3>Editar: <?=htmlspecialchars($u['nom'].' '.$u['cognom'],ENT_QUOTES,'UTF-8')?></h3>
            <form method="POST" action="?seccio=usuaris">
                <?php csrf_field(); ?>
                <input type="hidden" name="uid" value="<?=$u['id']?>">
                <div><label>Nom</label><input type="text" name="nom" value="<?=htmlspecialchars($u['nom'],ENT_QUOTES,'UTF-8')?>" required></div>
                <div><label>Cognom</label><input type="text" name="cognom" value="<?=htmlspecialchars($u['cognom'],ENT_QUOTES,'UTF-8')?>" required></div>
                <div><label>Email</label><input type="email" name="email" value="<?=htmlspecialchars($u['email'],ENT_QUOTES,'UTF-8')?>" required></div>
                <div><label>Nova contrasenya (buit=no canviar)</label><input type="password" name="new_password" placeholder="Mínim 6 caràcters" minlength="6"></div>
                <div><label>Rol</label><select name="rol"><option value="empleat" <?=$u['rol']==='empleat'?'selected':''?>>Empleat</option><option value="admin" <?=$u['rol']==='admin'?'selected':''?>>Admin</option></select></div>
                <div class="btn-group"><button type="submit" name="editar_usuari" class="btn-success">Desar</button><a href="#" class="btn" style="text-align:center;text-decoration:none;background:#95a5a6;">Cancel·lar</a></div>
            </form>
        </div></div>
        <?php endforeach;?></tbody></table></div>
        <?php else: ?><p class="small">No hi ha usuaris.</p><?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- SECCIÓ: PROJECTES -->
    <?php if ($seccio==='projectes'): ?>
    <div class="card">
        <h3>Crear projecte</h3>
        <form method="POST" action="?seccio=projectes" class="form-inline">
            <?php csrf_field(); ?>
            <input type="text" name="nom" placeholder="Nom del projecte" required style="min-width:200px">
            <input type="text" name="descripcio" placeholder="Descripció" style="min-width:250px">
            <input type="number" step="0.01" min="0" name="hores_estimades" placeholder="H. estimades" value="0" style="width:120px">
            <button type="submit" name="crear_projecte" class="btn-success">Crear</button>
        </form>
    </div>
    <div class="card">
        <h3>Llistat de projectes</h3>
        <?php if (count($projectes)>0): ?>
        <div class="table-wrapper"><table><thead><tr><th>ID</th><th>Nom</th><th>Descripció</th><th>H. estimades</th><th>Creat</th><th>Accions</th></tr></thead>
        <tbody><?php foreach($projectes as $p): ?>
        <tr><td><?=(int)$p['id']?></td><td><strong><?=htmlspecialchars($p['nom'],ENT_QUOTES,'UTF-8')?></strong></td>
        <td><?=htmlspecialchars($p['descripcio']??'—',ENT_QUOTES,'UTF-8')?></td>
        <td><?=number_format((float)$p['hores_estimades'],2)?> h</td><td><?=date('d/m/Y',strtotime($p['creat_at']))?></td>
        <td>
            <a href="#editar_p_<?=$p['id']?>" class="btn btn-warning btn-sm">✏️</a>
            <form method="POST" action="?seccio=projectes" style="display:inline" onsubmit="return confirm('Eliminar projecte?')">
                <?php csrf_field(); ?>
                <input type="hidden" name="pid" value="<?=$p['id']?>">
                <button type="submit" name="eliminar_projecte" class="btn-danger btn-sm">🗑️</button>
            </form>
        </td></tr>
        <div id="editar_p_<?=$p['id']?>" class="modal-overlay"><div class="modal">
            <a href="#" class="cerrar-modal">&times;</a>
            <h3>Editar projecte: <?=htmlspecialchars($p['nom'],ENT_QUOTES,'UTF-8')?></h3>
            <form method="POST" action="?seccio=projectes">
                <?php csrf_field(); ?>
                <input type="hidden" name="pid" value="<?=$p['id']?>">
                <div><label>Nom</label><input type="text" name="nom" value="<?=htmlspecialchars($p['nom'],ENT_QUOTES,'UTF-8')?>" required></div>
                <div><label>Descripció</label><textarea name="descripcio"><?=htmlspecialchars($p['descripcio']??'',ENT_QUOTES,'UTF-8')?></textarea></div>
                <div><label>Hores estimades</label><input type="number" step="0.01" min="0" name="hores_estimades" value="<?=(float)$p['hores_estimades']?>"></div>
                <div class="btn-group"><button type="submit" name="editar_projecte" class="btn-success">Desar</button><a href="#" class="btn" style="text-align:center;text-decoration:none;background:#95a5a6;">Cancel·lar</a></div>
            </form>
        </div></div>
        <?php endforeach;?></tbody></table></div>
        <?php else: ?><p class="small">No hi ha projectes.</p><?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- SECCIÓ: LLISTA VERMELLA -->
    <?php if ($seccio==='incomplidors'): ?>
    <div class="error" style="border:2px solid #e74c3c;">
        <h3 style="color:#c0392b;margin-bottom:10px;">🚨 Llista vermella d'incompliment</h3>
        <p class="small" style="margin-bottom:15px;">Empleats que <strong>avui</strong> no han fitxat o han treballat menys de 8 h.</p>
        <?php if (count($incomplidors)>0): ?>
        <div class="table-wrapper"><table><thead><tr><th>Empleat</th><th>Email</th><th>Entrada</th><th>Sortida</th><th>Hores</th><th>Motiu</th></tr></thead>
        <tbody><?php foreach($incomplidors as $inc):
            $he=$inc['hora_entrada']??null;$hs=$inc['hora_sortida']??null;$h=$inc['hores_totals']??null;
            if(is_null($he))$motiu='❌ No ha fitxat entrada';
            elseif(is_null($h))$motiu='⏳ Sense sortida';
            elseif((float)$h<8)$motiu='⚠️ Menys de 8h ('.number_format((float)$h,2).' h)';
            else $motiu='';
        ?><tr><td><?=htmlspecialchars($inc['nom'].' '.$inc['cognom'],ENT_QUOTES,'UTF-8')?></td>
        <td><?=htmlspecialchars($inc['email'],ENT_QUOTES,'UTF-8')?></td>
        <td><?=$he?date('H:i:s',strtotime($he)):'—'?></td>
        <td><?=$hs?date('H:i:s',strtotime($hs)):'—'?></td>
        <td><?=$h?number_format((float)$h,2).' h':'—'?></td>
        <td><span class="badge" style="background:#fdecea;color:#c0392b;"><?=$motiu?></span></td></tr>
        <?php endforeach;?></tbody></table></div>
        <?php else: ?><p style="color:#27ae60;font-weight:700;">✅ Tots han fitxat correctament.</p><?php endif; ?>
    </div>
    <?php endif; ?>
</div>
</body>
</html>