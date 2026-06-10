<?php
/*
 * WorkTracker — Panell d'Empleat
 * Fitxer: empleat/dashboard.php
 */

require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../config/db.php';

// Protegir: si no autenticat, login
if (!isset($_SESSION['usuari'])) {
    header('Location: ../auth/login.php');
    exit;
}

// Si és admin, redirigir al seu panell
if ($_SESSION['usuari']['rol'] === 'admin') {
    header('Location: ../admin/dashboard.php');
    exit;
}

// Si no és empleat, login
if ($_SESSION['usuari']['rol'] !== 'empleat') {
    header('Location: ../auth/login.php');
    exit;
}

$usuari = $_SESSION['usuari'];
$usuari_id = $usuari['id'];

$error   = '';
$success = '';

// ============================================================
// 1) PROCÉS: Fichar entrada
// ============================================================
if (isset($_POST['fichar_entrada'])) {
    csrf_verify();
    $projecte_id = (int) ($_POST['projecte_id'] ?? 0);
    $notes       = trim($_POST['notes_entrada'] ?? '');

    if ($projecte_id <= 0) {
        $error = 'Selecciona un projecte abans de fitxar.';
    } else {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM registres_hores WHERE usuari_id = :uid AND data = CURDATE()'
        );
        $stmt->execute([':uid' => $usuari_id]);
        $ja_fitxat_avui = (int) $stmt->fetchColumn() > 0;

        if ($ja_fitxat_avui) {
            $error = 'Ja has fitxat avui. No pots fitxar dues vegades el mateix dia.';
        } else {
            $ara_text = date('d/m/Y H:i:s');
            $notes_finals = $notes
                ? "Fitxat entrada: $ara_text — $notes"
                : "Fitxat entrada: $ara_text";

            $stmt = $pdo->prepare(
                'INSERT INTO registres_hores (usuari_id, projecte_id, hora_entrada, data, notes)
                 VALUES (:uid, :pid, NOW(), CURDATE(), :notes)'
            );
            $stmt->execute([
                ':uid'   => $usuari_id,
                ':pid'   => $projecte_id,
                ':notes' => $notes_finals,
            ]);
            $success = 'Entrada fitxada correctament a les ' . date('H:i:s') . '.';
        }
    }
}

// ============================================================
// 2) PROCÉS: Fichar sortida
// ============================================================
if (isset($_POST['fichar_sortida'])) {
    csrf_verify();
    $stmt = $pdo->prepare(
        'SELECT id, hora_entrada, notes FROM registres_hores
         WHERE usuari_id = :uid AND data = CURDATE() AND hora_sortida IS NULL
         LIMIT 1'
    );
    $stmt->execute([':uid' => $usuari_id]);
    $registre_obert = $stmt->fetch();

    if (!$registre_obert) {
        $error = 'No tens cap registre obert per fitxar la sortida.';
    } else {
        $notes_antigues = $registre_obert['notes'] ?? '';
        $ara_text       = date('d/m/Y H:i:s');
        $notes_finals   = $notes_antigues
            ? $notes_antigues . " | Sortida: $ara_text"
            : "Sortida: $ara_text";

        $stmt = $pdo->prepare(
            'UPDATE registres_hores
             SET hora_sortida  = NOW(),
                 hores_totals  = ROUND(TIMESTAMPDIFF(SECOND, hora_entrada, NOW()) / 3600, 2),
                 notes         = :notes
             WHERE id = :rid'
        );
        $stmt->execute([
            ':notes' => $notes_finals,
            ':rid'   => $registre_obert['id'],
        ]);

        $stmt = $pdo->prepare(
            'SELECT hores_totals FROM registres_hores WHERE id = :rid'
        );
        $stmt->execute([':rid' => $registre_obert['id']]);
        $reg_actualitzat = $stmt->fetch();

        $success = 'Sortida fitxada correctament. Total: ' . format_hores_rellotge((float)$reg_actualitzat['hores_totals']) . '.';
    }
}

// ============================================================
// OBTENIR DADES PER A LA VISTA
// ============================================================

$stmt = $pdo->query('SELECT id, nom FROM projectes ORDER BY nom ASC');
$projectes = $stmt->fetchAll();

$stmt = $pdo->prepare(
    'SELECT id, hora_entrada FROM registres_hores
     WHERE usuari_id = :uid AND data = CURDATE() AND hora_sortida IS NULL
     LIMIT 1'
);
$stmt->execute([':uid' => $usuari_id]);
$registre_obert = $stmt->fetch();

$dilluns  = date('Y-m-d', strtotime('monday this week'));
$diumenge = date('Y-m-d', strtotime('sunday this week'));

$stmt = $pdo->prepare(
    'SELECT r.id, r.data, r.hora_entrada, r.hora_sortida, r.hores_totals, r.notes,
            p.nom AS projecte_nom
     FROM registres_hores r
     JOIN projectes p ON r.projecte_id = p.id
     WHERE r.usuari_id = :uid AND r.data BETWEEN :dilluns AND :diumenge
     ORDER BY r.data DESC, r.hora_entrada DESC'
);
$stmt->execute([
    ':uid'     => $usuari_id,
    ':dilluns' => $dilluns,
    ':diumenge' => $diumenge,
]);
$registres_setmana = $stmt->fetchAll();

// Hores totals
$stmt = $pdo->prepare(
    "SELECT COALESCE(SUM(hores_totals), 0) AS total
     FROM registres_hores
     WHERE usuari_id = :uid AND data = CURDATE() AND hora_sortida IS NOT NULL"
);
$stmt->execute([':uid' => $usuari_id]);
$total_avui = $stmt->fetch();

$stmt = $pdo->prepare(
    "SELECT COALESCE(SUM(hores_totals), 0) AS total
     FROM registres_hores
     WHERE usuari_id = :uid AND data BETWEEN :dilluns AND :diumenge AND hora_sortida IS NOT NULL"
);
$stmt->execute([
    ':uid'     => $usuari_id,
    ':dilluns' => $dilluns,
    ':diumenge' => $diumenge,
]);
$total_setmana = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WorkTracker — Panell d&#39;Empleat</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        body { background: linear-gradient(135deg, #f5e6d0 0%, #f0f4ff 50%, #ffffff 100%); }
        .container { box-shadow: 0 10px 40px rgba(139,90,43,0.12); }
        h1, .card h3, .card-rapid .num, .resum-card .num { color: #8B5A2B; }
        h2 { color: #a0785a; }
        .tabs a.active { color: #8B5A2B; border-bottom-color: #8B5A2B; }
        button, .btn { background: linear-gradient(135deg, #8B5A2B 0%, #a0785a 100%); }
        button:hover, .btn:hover { box-shadow: 0 4px 15px rgba(139,90,43,0.4); }
        .btn-success { background: linear-gradient(135deg, #8B5A2B 0%, #a0785a 100%); }
        .btn-success:hover { box-shadow: 0 4px 15px rgba(139,90,43,0.4); }
        .btn-secondary { background: linear-gradient(135deg, #b0c4de 0%, #87ceeb 100%); }
        .btn-warning { background: linear-gradient(135deg, #cda052 0%, #d4a574 100%); }
        .btn-danger { background: linear-gradient(135deg, #c0392b 0%, #e74c3c 100%); }
        a { color: #8B5A2B; }
        a:hover { color: #a0785a; }
        nav { border-top: 1px solid #f0e6d8; border-bottom: 1px solid #f0e6d8; }
        nav a { color: #8B5A2B; }
        th { background: #fff8f0; color: #8B5A2B; }
        tr:hover { background: #faf5ef; }
        input:focus, select:focus, textarea:focus { border-color: #8B5A2B; box-shadow: 0 0 0 3px rgba(139,90,43,0.12); }
        .badge-empleat { background: #fff8f0; color: #8B5A2B; }
        .badge-admin { background: #e8f0fe; color: #3b5998; }
        .dashboard-layout { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px; }
        .dashboard-layout .full-width { grid-column: 1 / -1; }
        .btn-group { display: flex; gap: 10px; margin-top: 15px; }
        .btn-group button { flex: 1; }
        @media (max-width: 768px) { .dashboard-layout { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="container" style="max-width: 960px;">
        <h1>WorkTracker</h1>
        <h2>Panell d&#39;Empleat</h2>
        <p>
            Benvingut, <strong><?= htmlspecialchars($usuari['nom'] . ' ' . $usuari['cognom'], ENT_QUOTES, 'UTF-8') ?></strong>
            (<?= htmlspecialchars($usuari['rol'], ENT_QUOTES, 'UTF-8') ?>)
        </p>

        <nav>
            <a href="../auth/logout.php">Tancar sessió</a>
        </nav>

        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <div class="dashboard-layout">

            <!-- FITXAR -->
            <div class="card">
                <h3>Fitxar</h3>
                <form method="POST" action="">
                    <?php csrf_field(); ?>
                    <div>
                        <label for="projecte_fitxar">Projecte</label>
                        <select name="projecte_id" id="projecte_fitxar" required>
                            <option value="">-- Selecciona un projecte --</option>
                            <?php foreach ($projectes as $p): ?>
                                <option value="<?= (int) $p['id'] ?>">
                                    <?= htmlspecialchars($p['nom'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php if (!$registre_obert): ?>
                    <div>
                        <label for="notes_entrada">Notes (opcional)</label>
                        <textarea name="notes_entrada" id="notes_entrada" placeholder="Què faràs?"></textarea>
                        <p class="small">La data i hora s'afegiran automàticament.</p>
                    </div>
                    <?php endif; ?>

                    <div class="btn-group">
                        <?php if (!$registre_obert): ?>
                            <button type="submit" name="fichar_entrada" class="btn-success">&#x1f4c5; Fichar entrada</button>
                        <?php else: ?>
                            <button type="submit" name="fichar_sortida" class="btn-secondary">&#x1f4c6; Fichar sortida</button>
                        <?php endif; ?>
                    </div>
                </form>

                <?php if ($registre_obert): ?>
                    <p class="small">Has fitxat entrada a les <strong><?= date('H:i:s', strtotime($registre_obert['hora_entrada'])) ?></strong>. Ja pots fitxar la sortida.</p>
                <?php else: ?>
                    <p class="small">Encara no has fitxat avui.</p>
                <?php endif; ?>
            </div>

            <!-- RESUM -->
            <div class="card">
                <h3>Resum d&#39;avui</h3>
                <p><strong>Hores d&#39;avui:</strong> <?= format_hores_rellotge((float)$total_avui['total']) ?></p>
                <p><strong>Hores aquesta setmana:</strong> <?= format_hores_rellotge((float)$total_setmana['total']) ?></p>
            </div>

            <!-- HISTORIAL -->
            <div class="card full-width">
                <h3>Historial de la setmana actual</h3>
                <p class="small">Del <?= date('d/m/Y', strtotime($dilluns)) ?> al <?= date('d/m/Y', strtotime($diumenge)) ?></p>

                <?php if (count($registres_setmana) > 0): ?>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr><th>Data</th><th>Projecte</th><th>Entrada</th><th>Sortida</th><th>Hores totals</th><th>Estat</th><th>Notes</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($registres_setmana as $reg): ?>
                                    <?php $es_obert = is_null($reg['hora_sortida']); ?>
                                    <tr>
                                        <td><?= date('d/m/Y', strtotime($reg['data'])) ?></td>
                                        <td><?= htmlspecialchars($reg['projecte_nom'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= date('H:i:s', strtotime($reg['hora_entrada'])) ?></td>
                                        <td><?= $es_obert ? '—' : date('H:i:s', strtotime($reg['hora_sortida'])) ?></td>
                                        <td><?= $es_obert ? '—' : format_hores_rellotge((float) $reg['hores_totals']) ?></td>
                                        <td><span class="badge <?= $es_obert ? 'badge-obert' : 'badge-tancat' ?>"><?= $es_obert ? 'Obert' : 'Tancat' ?></span></td>
                                        <td><?= nl2br(htmlspecialchars($reg['notes'] ?? '—', ENT_QUOTES, 'UTF-8')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="small">No hi ha registres per a la setmana actual.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>