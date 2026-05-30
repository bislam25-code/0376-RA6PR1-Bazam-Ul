<?php
/*
 * WorkTracker — Panell d'Empleat
 * Fitxer: empleat/dashboard.php
 * 
 * Projecte independent: usa config/db.php, BD worktracker, sessions $_SESSION['usuari']
 */

session_start();

// Protegir: si no autenticat, login
if (!isset($_SESSION['usuari'])) {
    header('Location: ../auth/login.php');
    exit;
}

// Si és admin, redirigir al seu panell
if ($_SESSION['usuari']['rol'] === 'admin') {
    header('Location: ../auth/dashboard_admin.php');
    exit;
}

// Si no és empleat, login
if ($_SESSION['usuari']['rol'] !== 'empleat') {
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
    $notes       = trim($_POST['notes_entrada'] ?? '');

    if ($projecte_id <= 0) {
        $error = 'Selecciona un projecte abans de fitxar.';
    } else {
        // Comprovar si ja ha fitxat avui
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
// 2) PROCÉS: Fichar sortida (càlcul amb MySQL)
// ============================================================
if (isset($_POST['fichar_sortida'])) {
    // Buscar registre obert d'avui
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

        // Calcular hores amb MySQL: TIMESTAMPDIFF en segons / 3600
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

        // Recuperar el valor calculat per mostrar-lo
        $stmt = $pdo->prepare(
            'SELECT hores_totals FROM registres_hores WHERE id = :rid'
        );
        $stmt->execute([':rid' => $registre_obert['id']]);
        $reg_actualitzat = $stmt->fetch();

        $success = 'Sortida fitxada correctament. Total: ' . number_format((float)$reg_actualitzat['hores_totals'], 2) . ' h.';
    }
}

// ============================================================
// OBTENIR DADES PER A LA VISTA
// ============================================================

// Llistat de projectes per al selector
$stmt = $pdo->query('SELECT id, nom FROM projectes ORDER BY nom ASC');
$projectes = $stmt->fetchAll();

// Saber si hi ha un registre obert avui
$stmt = $pdo->prepare(
    'SELECT id, hora_entrada FROM registres_hores
     WHERE usuari_id = :uid AND data = CURDATE() AND hora_sortida IS NULL
     LIMIT 1'
);
$stmt->execute([':uid' => $usuari_id]);
$registre_obert = $stmt->fetch();

// Historial de la setmana actual
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
?>
<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WorkTracker — Panell d&#39;Empleat</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .dashboard-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }
        .dashboard-layout .full-width {
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

        <div class="dashboard-layout">

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

                    <?php if (!$registre_obert): ?>
                    <div>
                        <label for="notes_entrada">Notes (opcional)</label>
                        <textarea name="notes_entrada" id="notes_entrada"
                                  placeholder="Què faràs? Ex: Revisió de codi, reunió..."></textarea>
                        <p class="small">La data i hora s'afegiran automàticament.</p>
                    </div>
                    <?php endif; ?>

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

            <!-- -------- TARGETA BUIT (espai per al futur) -------- -->
            <div class="card">
                <h3>Resum d&#39;avui</h3>
                <?php
                    // Calcular hores totals d'avui
                    $stmt = $pdo->prepare(
                        "SELECT COALESCE(SUM(hores_totals), 0) AS total
                         FROM registres_hores
                         WHERE usuari_id = :uid AND data = CURDATE() AND hora_sortida IS NOT NULL"
                    );
                    $stmt->execute([':uid' => $usuari_id]);
                    $total_avui = $stmt->fetch();

                    // Calcular hores totals de la setmana
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
                <p><strong>Hores d&#39;avui:</strong> <?= number_format((float)$total_avui['total'], 2) ?> h</p>
                <p><strong>Hores aquesta setmana:</strong> <?= number_format((float)$total_setmana['total'], 2) ?> h</p>
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
                                    <?php $es_obert = is_null($reg['hora_sortida']); ?>
                                    <tr>
                                        <td><?= date('d/m/Y', strtotime($reg['data'])) ?></td>
                                        <td><?= htmlspecialchars($reg['projecte_nom'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= date('H:i:s', strtotime($reg['hora_entrada'])) ?></td>
                                        <td><?= $es_obert ? '—' : date('H:i:s', strtotime($reg['hora_sortida'])) ?></td>
                                        <td><?= $es_obert ? '—' : number_format((float) $reg['hores_totals'], 2) . ' h' ?></td>
                                        <td>
                                            <span class="badge <?= $es_obert ? 'badge-obert' : 'badge-tancat' ?>">
                                                <?= $es_obert ? 'Obert' : 'Tancat' ?>
                                            </span>
                                        </td>
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