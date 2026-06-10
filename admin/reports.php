<?php
/*
 * WorkTracker — Reports i Gràfics
 * Fitxer: admin/reports.php
 */

require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['usuari']) || $_SESSION['usuari']['rol'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$usuari = $_SESSION['usuari'];

// Filtres de dates
$filtre_data  = $_GET['filtre'] ?? 'setmana';
$data_inici   = $_GET['data_inici'] ?? date('Y-m-d', strtotime('monday this week'));
$data_fi      = $_GET['data_fi'] ?? date('Y-m-d', strtotime('sunday this week'));

if ($filtre_data === 'setmana') {
    $data_inici = date('Y-m-d', strtotime('monday this week'));
    $data_fi    = date('Y-m-d', strtotime('sunday this week'));
} elseif ($filtre_data === 'mes') {
    $data_inici = date('Y-m-01');
    $data_fi    = date('Y-m-t');
}

// ============================================================
// CONSULTA 1: Hores per projecte (SUM + GROUP BY)
// ============================================================
$stmt = $pdo->query(
    'SELECT p.id, p.nom,
            p.hores_estimades,
            COALESCE(SUM(r.hores_totals), 0) AS hores_consumides,
            COALESCE(SUM(r.hores_totals), 0) - p.hores_estimades AS diferencia
     FROM projectes p
     LEFT JOIN registres_hores r ON p.id = r.projecte_id AND r.hora_sortida IS NOT NULL
     GROUP BY p.id, p.nom, p.hores_estimades
     ORDER BY diferencia ASC'
);
$reports_projectes = $stmt->fetchAll();

// ============================================================
// CONSULTA 2: Hores per empleat filtrable (setmana/mes)
// ============================================================
$stmt = $pdo->prepare(
    'SELECT u.id, u.nom, u.cognom,
            COALESCE(SUM(r.hores_totals), 0) AS hores_totals,
            COUNT(r.id) AS total_registres,
            COUNT(DISTINCT r.data) AS dies_treballats
     FROM usuaris u
     LEFT JOIN registres_hores r ON u.id = r.usuari_id
         AND r.hora_sortida IS NOT NULL
         AND r.data BETWEEN :inici AND :fi
     WHERE u.rol = \'empleat\'
     GROUP BY u.id, u.nom, u.cognom
     ORDER BY u.nom ASC'
);
$stmt->execute([':inici' => $data_inici, ':fi' => $data_fi]);
$reports_empleats = $stmt->fetchAll();

// ============================================================
// CONSULTA 3: Evolució diària (per al gràfic de línies)
// ============================================================
$stmt = $pdo->query(
    'SELECT r.data,
            COALESCE(SUM(r.hores_totals), 0) AS hores_dia
     FROM registres_hores r
     WHERE r.hora_sortida IS NOT NULL
     GROUP BY r.data
     ORDER BY r.data ASC
     LIMIT 60'
);
$evolucio_diaria = $stmt->fetchAll();

// ============================================================
// CONSULTA 4: Resum ràpid
// ============================================================

// Empleats actius avui (han fitxat entrada avui)
$stmt = $pdo->prepare(
    'SELECT COUNT(DISTINCT usuari_id) AS actius
     FROM registres_hores
     WHERE data = CURDATE() AND hora_entrada IS NOT NULL'
);
$stmt->execute();
$actius_avui = (int) $stmt->fetchColumn();

// Projectes en superàvit d'hores (hores_consumides <= hores_estimades)
$stmt = $pdo->prepare(
    'SELECT COUNT(*) AS superavit
     FROM (
         SELECT p.id,
                COALESCE(SUM(r.hores_totals), 0) - p.hores_estimades AS diff
         FROM projectes p
         LEFT JOIN registres_hores r ON p.id = r.projecte_id AND r.hora_sortida IS NOT NULL
         GROUP BY p.id, p.hores_estimades
         HAVING diff <= 0
     ) AS sub'
);
$stmt->execute();
$projectes_superavit = (int) $stmt->fetchColumn();

// Empleats sense fitxar avui
$stmt = $pdo->prepare(
    'SELECT COUNT(*) FROM usuaris u
     WHERE u.rol = \'empleat\'
       AND NOT EXISTS (
           SELECT 1 FROM registres_hores r
           WHERE r.usuari_id = u.id AND r.data = CURDATE()
       )'
);
$stmt->execute();
$sense_fitxar = (int) $stmt->fetchColumn();

// JSON per al gràfic de barres
$labels_barres = [];
$estimades_barres = [];
$consumides_barres = [];
foreach ($reports_projectes as $rp) {
    $labels_barres[] = $rp['nom'];
    $estimades_barres[] = (float) $rp['hores_estimades'];
    $consumides_barres[] = (float) $rp['hores_consumides'];
}

// JSON per al gràfic de línies
$labels_linies = [];
$hores_linies = [];
foreach ($evolucio_diaria as $ed) {
    $labels_linies[] = date('d/m', strtotime($ed['data']));
    $hores_linies[] = (float) $ed['hores_dia'];
}
?>
<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WorkTracker — Reports</title>
    <link rel="stylesheet" href="../assets/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f7f6; color: #333; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 30px; }
        h1 { font-size: 1.8rem; color: #2c3e50; }
        h2 { font-size: 1.2rem; color: #7f8c8d; font-weight: 400; margin-bottom: 20px; }

        /* Resum ràpid */
        .resum-rapid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .resum-rapid .card-rapid {
            background: #f9f9f9;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            padding: 15px;
            text-align: center;
        }
        .resum-rapid .card-rapid .num { font-size: 1.8rem; font-weight: 700; color: #3498db; }
        .resum-rapid .card-rapid .label { font-size: 0.85rem; color: #7f8c8d; margin-top: 3px; }
        .resum-rapid .card-rapid.danger .num { color: #e74c3c; }
        .resum-rapid .card-rapid.success .num { color: #27ae60; }

        .charts-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        .chart-card {
            background: #f9f9f9;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            padding: 20px;
        }
        .chart-card h3 { margin-bottom: 15px; color: #2c3e50; }
        .chart-card canvas { max-height: 300px; }

        .card { background: #f9f9f9; border: 1px solid #e0e0e0; border-radius: 6px; padding: 20px; margin-bottom: 20px; }
        .card h3 { margin-bottom: 15px; color: #2c3e50; }

        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #e0e0e0; }
        th { background: #ecf0f1; font-weight: 600; color: #2c3e50; white-space: nowrap; }
        tr:hover { background: #f5f5f5; }

        nav { margin-bottom: 20px; }
        nav a { color: #3498db; text-decoration: none; }
        nav a:hover { text-decoration: underline; }

        .small { font-size: 0.85rem; color: #7f8c8d; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 0.8rem; font-weight: 600; }
        .badge-positiu { background: #e8f8f5; color: #27ae60; }
        .badge-negatiu { background: #fdecea; color: #c0392b; }

        .filtre-form {
            display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-bottom: 15px;
        }
        .filtre-form select, .filtre-form input {
            padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; font-size: 0.9rem;
        }
        .filtre-form button {
            padding: 8px 16px; background: #3498db; color: #fff; border: none; border-radius: 4px;
        }

        @media (max-width: 768px) {
            .charts-grid { grid-template-columns: 1fr; }
            .resum-rapid { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>
<div class="container">
    <h1>WorkTracker</h1>
    <h2>Reports i Gràfics</h2>
    <p><a href="dashboard.php">← Tornar al panell</a></p>

    <!-- ============================================================ -->
    <!-- RESUM RÀPID A LA CAPÇALERA -->
    <!-- ============================================================ -->
    <div class="resum-rapid">
        <div class="card-rapid success">
            <div class="num"><?= $actius_avui ?></div>
            <div class="label">✅ Actius avui</div>
        </div>
        <div class="card-rapid">
            <div class="num"><?= $projectes_superavit ?></div>
            <div class="label">📊 Projectes en superàvit</div>
        </div>
        <div class="card-rapid <?= $sense_fitxar > 0 ? 'danger' : 'success' ?>">
            <div class="num"><?= $sense_fitxar ?></div>
            <div class="label"><?= $sense_fitxar > 0 ? '⛔ Sense fitxar avui' : '✅ Tots han fitxat' ?></div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- GRÀFICS (Chart.js) -->
    <!-- ============================================================ -->
    <div class="charts-grid">
        <div class="chart-card">
            <h3>Hores per projecte: estimades vs consumides</h3>
            <canvas id="chartBarres"></canvas>
        </div>
        <div class="chart-card">
            <h3>Evolució d'hores diàries</h3>
            <canvas id="chartLinies"></canvas>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- CONSULTA 1: Hores per projecte (amb diferència) -->
    <!-- ============================================================ -->
    <div class="card">
        <h3>Hores per projecte</h3>
        <?php if (count($reports_projectes) > 0): ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr><th>Projecte</th><th>H. estimades</th><th>H. consumides</th><th>Diferència</th><th>Estat</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($reports_projectes as $rp):
                        $diff = (float) $rp['diferencia'];
                        $positiu = $diff >= 0;
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($rp['nom'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                        <td><?= format_hores_rellotge((float)$rp['hores_estimades']) ?></td>
                        <td><?= format_hores_rellotge((float)$rp['hores_consumides']) ?></td>
                        <td style="color:<?= $positiu ? '#e74c3c' : '#27ae60' ?>;font-weight:700;">
                            <?= $positiu ? '+' : '' ?><?= format_hores_rellotge($diff) ?>
                        </td>
                        <td>
                            <span class="badge <?= $positiu ? 'badge-negatiu' : 'badge-positiu' ?>">
                                <?= $positiu ? '🔴 Dèficit' : '🟢 Superàvit' ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <p class="small">No hi ha projectes.</p>
        <?php endif; ?>
    </div>

    <!-- ============================================================ -->
    <!-- CONSULTA 2: Hores per empleat (filtrable per dates) -->
    <!-- ============================================================ -->
    <div class="card">
        <h3>Hores per empleat</h3>
        <form method="GET" action="" class="filtre-form">
            <select name="filtre" onchange="this.form.submit()">
                <option value="setmana" <?= $filtre_data === 'setmana' ? 'selected' : '' ?>>Aquesta setmana</option>
                <option value="mes"     <?= $filtre_data === 'mes'     ? 'selected' : '' ?>>Aquest mes</option>
                <option value="personalitzat" <?= $filtre_data === 'personalitzat' ? 'selected' : '' ?>>Personalitzat</option>
            </select>
            <input type="date" name="data_inici" value="<?= $data_inici ?>">
            <input type="date" name="data_fi"    value="<?= $data_fi ?>">
            <button type="submit">Filtrar</button>
        </form>

        <?php if (count($reports_empleats) > 0): ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr><th>Empleat</th><th>Total hores</th><th>Registres</th><th>Dies treballats</th><th>Mitjana h/dia</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($reports_empleats as $re):
                        $dies = max((int)$re['dies_treballats'], 1);
                        $mitjana = round((float)$re['hores_totals'] / $dies, 2);
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($re['nom'] . ' ' . $re['cognom'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><strong><?= format_hores_rellotge((float)$re['hores_totals']) ?></strong></td>
                        <td><?= (int)$re['total_registres'] ?></td>
                        <td><?= (int)$re['dies_treballats'] ?></td>
                        <td><?= format_hores_rellotge($mitjana) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <p class="small">No hi ha registres per al període seleccionat.</p>
        <?php endif; ?>
    </div>
</div>

<script>
// ============================================================
// GRÀFIC DE BARRES
// ============================================================
const ctxBar = document.getElementById('chartBarres').getContext('2d');
new Chart(ctxBar, {
    type: 'bar',
    data: {
        labels: <?= json_encode($labels_barres) ?>,
        datasets: [
            {
                label: 'H. estimades',
                data: <?= json_encode($estimades_barres) ?>,
                backgroundColor: 'rgba(52, 152, 219, 0.7)',
                borderColor: 'rgba(52, 152, 219, 1)',
                borderWidth: 1
            },
            {
                label: 'H. consumides',
                data: <?= json_encode($consumides_barres) ?>,
                backgroundColor: 'rgba(231, 76, 60, 0.7)',
                borderColor: 'rgba(231, 76, 60, 1)',
                borderWidth: 1
            }
        ]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'top' } },
        scales: {
            y: { beginAtZero: true, title: { display: true, text: 'Hores' } },
            x: { title: { display: true, text: 'Projectes' } }
        }
    }
});

// ============================================================
// GRÀFIC DE LÍNIES
// ============================================================
const ctxLin = document.getElementById('chartLinies').getContext('2d');
new Chart(ctxLin, {
    type: 'line',
    data: {
        labels: <?= json_encode($labels_linies) ?>,
        datasets: [{
            label: 'Hores diàries',
            data: <?= json_encode($hores_linies) ?>,
            borderColor: 'rgba(39, 174, 96, 1)',
            backgroundColor: 'rgba(39, 174, 96, 0.1)',
            fill: true,
            tension: 0.3,
            pointRadius: 3
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'top' } },
        scales: {
            y: { beginAtZero: true, title: { display: true, text: 'Hores totals' } },
            x: { title: { display: true, text: 'Data' } }
        }
    }
});
</script>
</body>
</html>