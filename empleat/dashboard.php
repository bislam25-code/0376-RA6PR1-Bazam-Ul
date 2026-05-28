<?php
/*
 * WorkTracker — Panell d'Empleat
 * Fitxer: empleat/dashboard.php
 */

session_start();

// Protegir: només usuaris autenticats amb rol empleat
if (!isset($_SESSION['usuari']) || $_SESSION['usuari']['rol'] !== 'empleat') {
    header('Location: ../auth/login.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';

$usuari = $_SESSION['usuari'];
$usuari_id = $usuari['id'];

$error   = '';
$success = '';

// ============================================================
// 1) PROCÉS: Fichar entrada
// ============================================================
if (isset($_POST['fichar_entrada'])) {
    $projecte_id = (int) ($_POST['projecte_id'] ?? 0);

    if ($projecte_id <= 0) {
        $error = 'Selecciona un projecte abans de fitxar.';
    } else {
        // Comprovar si ja ha fitxat avui (qualsevol registre amb data = avui)
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM registres_hores WHERE usuari_id = :uid AND data = CURDATE()'
        );
        $stmt->execute([':uid' => $usuari_id]);
        $ja_fitxat_avui = (int) $stmt->fetchColumn() > 0;

        if ($ja_fitxat_avui) {
            $error = 'Ja has fitxat avui. No pots fitxar dues vegades el mateix dia.';
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO registres_hores (usuari_id, projecte_id, hora_entrada, data)
                 VALUES (:uid, :pid, NOW(), CURDATE())'
            );
            $stmt->execute([
                ':uid' => $usuari_id,
                ':pid' => $projecte_id,
            ]);
            $success = 'Entrada fitxada correctament a les ' . date('H:i:s') . '.';
        }
    }
}

// ============================================================
// 2) PROCÉS: Fichar sortida
// ============================================================
if (isset($_POST['fichar_sortida'])) {
    // Buscar el registre obert d'avui (sense hora_sortida)
    $stmt = $pdo->prepare(
        'SELECT id, hora_entrada FROM registres_hores
         WHERE usuari_id = :uid AND data = CURDATE() AND hora_sortida IS NULL
         LIMIT 1'
    );
    $stmt->execute([':uid' => $usuari_id]);
    $registre_obert = $stmt->fetch();

    if (!$registre_obert) {
        $error = 'No tens cap registre obert per fitxar la sortida.';
    } else {
        // Calcular hores_totals com a diferència decimal
        $hora_entrada_ts = strtotime($registre_obert['hora_entrada']);
        $hora_sortida_ts = time();
        $hores_totals = round(($hora_sortida_ts - $hora_entrada_ts) / 3600, 2);

        $stmt = $pdo->prepare(
            'UPDATE registres_hores
             SET hora_sortida = NOW(), hores_totals = :hores
             WHERE id = :rid'
        );
        $stmt->execute([
            ':hores' => $hores_totals,
            ':rid'   => $registre_obert['id'],
        ]);
        $success = 'Sortida fitxada correctament. Total: ' . number_format($hores_totals, 2) . ' h.';
    }
}

// ============================================================
// 3) PROCÉS: Assignar hores a un projecte (manual)
// ============================================================
if (isset($_POST['assignar_hores'])) {
    $projecte_id  = (int) ($_POST['projecte_id'] ?? 0);
    $hores_manual = (float) ($_POST['hores_manual'] ?? 0);
    $notes        = trim($_POST['notes'] ?? '');

    if ($projecte_id <= 0) {
        $error = 'Selecciona un projecte.';
    } elseif ($hores_manual <= 0) {
        $error = 'Les hores han de ser un valor positiu.';
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO registres_hores (usuari_id, projecte_id, hora_entrada, hora_sortida, hores_totals, data, notes)
             VALUES (:uid, :pid, :hentrada, :hsortida, :hores, CURDATE(), :notes)'
        );
        $stmt->execute([
            ':uid'       => $usuari_id,
            ':pid'       => $projecte_id,
            ':hentrada'  => date('Y-m-d') . ' 00:00:00',
            ':hsortida'  => date('Y-m-d') . ' 00:00:00',
            ':hores'     => $hores_manual,
            ':notes'     => $notes ?: null,
        ]);
        $success = 'S\'han assignat ' . number_format($hores_manual, 2) . ' h al projecte correctament.';
    }
}

// ============================================================
// OBTENIR DADES PER A LA VISTA
// ============================================================

// Llistat de projectes per al selector
$stmt = $pdo->query('SELECT id, nom FROM projectes ORDER BY nom ASC');
$projectes = $stmt->fetchAll();

// Saber si hi ha un registre obert avui (per mostrar el botó de sortida)
$stmt = $pdo->prepare(
    'SELECT id, hora_entrada FROM registres_hores
     WHERE usuari_id = :uid AND data = CURDATE() AND hora_sortida IS NULL
     LIMIT 1'
);
$stmt->execute([':uid' => $usuari_id]);
$registre_obert = $stmt->fetch();

// Historial de la setmana actual (dilluns a diumenge)
$dilluns = date('Y-m-d', strtotime('monday this week'));
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
?>
<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WorkTracker — Panell d&#39;Empleat</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }
        .dashboard-grid .full-width {
            grid-column: 1 / -1;
        }
        .card {
            background: #f9f9f9;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            padding: 20px;
        }
        .card h3 {
            margin-bottom: 15px;
            color: #2c3e50;
        }
        select, textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 1rem;
            font-family: inherit;
            transition: border-color 0.3s;
        }
        select:focus, textarea:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 5px rgba(52, 152, 219, 0.3);
        }
        textarea {
            resize: vertical;
            min-height: 80px;
        }
        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        .btn-group button {
            flex: 1;
        }
        button.btn-secondary {
            background-color: #95a5a6;
        }
        button.btn-secondary:hover {
            background-color: #7f8c8d;
        }
        button.btn-success {
            background-color: #27ae60;
        }
        button.btn-success:hover {
            background-color: #219a52;
        }
        .table-wrapper {
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th, td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        th {
            background-color: #ecf0f1;
            font-weight: 600;
            color: #2c3e50;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .badge-obert {
            background-color: #fcf3cf;
            color: #7d6608;
        }
        .badge-tancat {
            background-color: #d5f5e3;
            color: #1e8449;
        }
        .small {
            font-size: 0.9rem;
            color: #7f8c8d;
            margin-top: 5px;
        }
        nav {
            margin: 20px 0;
        }
        nav a {
            color: #3498db;
            text-decoration: none;
        }
        nav a:hover {
            text-decoration: underline;
        }
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

        <!-- ==================================================== -->
        <!-- GRAELLA DE TARGETES                                  -->
        <!-- ==================================================== -->
        <div class="dashboard-grid">

            <!-- -------- TARGETA: FITXAR (entrada / sortida) -------- -->
            <div class="card">
                <h3>Fitxar</h3>

                <form method="POST" action="">
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

                    <div class="btn-group">
                        <?php if (!$registre_obert): ?>
                            <button type="submit" name="fichar_entrada" class="btn-success">
                                &#x1f4c5; Fichar entrada
                            </button>
                        <?php else: ?>
                            <button type="submit" name="fichar_sortida" class="btn-secondary">
                                &#x1f4c6; Fichar sortida
                            </button>
                        <?php endif; ?>
                    </div>
                </form>

                <?php if ($registre_obert): ?>
                    <p class="small">
                        Has fitxat entrada a les
                        <strong><?= date('H:i:s', strtotime($registre_obert['hora_entrada'])) ?></strong>.
                        Ja pots fitxar la sortida.
                    </p>
                <?php else: ?>
                    <p class="small">Encara no has fitxat avui.</p>
                <?php endif; ?>
            </div>

            <!-- -------- TARGETA: ASSIGNAR HORES A PROJECTE -------- -->
            <div class="card">
                <h3>Assignar hores a projecte</h3>

                <form method="POST" action="">
                    <div>
                        <label for="projecte_assignar">Projecte</label>
                        <select name="projecte_id" id="projecte_assignar" required>
                            <option value="">-- Selecciona un projecte --</option>
                            <?php foreach ($projectes as $p): ?>
                                <option value="<?= (int) $p['id'] ?>">
                                    <?= htmlspecialchars($p['nom'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="hores_manual">Hores treballades</label>
                        <input type="number" step="0.01" min="0.01" name="hores_manual" id="hores_manual"
                               placeholder="Ex: 7.5" required>
                    </div>

                    <div>
                        <label for="notes">Notes (opcional)</label>
                        <textarea name="notes" id="notes" placeholder="Comentaris addicionals..."></textarea>
                    </div>

                    <button type="submit" name="assignar_hores">Desar registre</button>
                </form>
            </div>

            <!-- -------- TARGETA: HISTORIAL DE LA SETMANA -------- -->
            <div class="card full-width">
                <h3>Historial de la setmana actual</h3>
                <p class="small">Del <?= date('d/m/Y', strtotime($dilluns)) ?> al <?= date('d/m/Y', strtotime($diumenge)) ?></p>

                <?php if (count($registres_setmana) > 0): ?>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Projecte</th>
                                    <th>Entrada</th>
                                    <th>Sortida</th>
                                    <th>Hores totals</th>
                                    <th>Estat</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($registres_setmana as $reg): ?>
                                    <?php
                                        $es_obert = is_null($reg['hora_sortida']);
                                    ?>
                                    <tr>
                                        <td><?= date('d/m/Y', strtotime($reg['data'])) ?></td>
                                        <td><?= htmlspecialchars($reg['projecte_nom'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= date('H:i:s', strtotime($reg['hora_entrada'])) ?></td>
                                        <td>
                                            <?= $es_obert ? '—' : date('H:i:s', strtotime($reg['hora_sortida'])) ?>
                                        </td>
                                        <td>
                                            <?= $es_obert ? '—' : number_format((float) $reg['hores_totals'], 2) . ' h' ?>
                                        </td>
                                        <td>
                                            <span class="badge <?= $es_obert ? 'badge-obert' : 'badge-tancat' ?>">
                                                <?= $es_obert ? 'Obert' : 'Tancat' ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($reg['notes'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
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