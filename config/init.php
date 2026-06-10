<?php
/**
 * WorkTracker — Configuració compartida
 * Fitxer: config/init.php
 * Inclou: sessió, CSRF, funcions comunes
 */

// Iniciar sessió si no està iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================================
// CSRF TOKEN
// ============================================================

function csrf_token(): string {
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function csrf_field(): void {
    echo '<input type="hidden" name="_csrf_token" value="' . csrf_token() . '">';
}

function csrf_verify(): void {
    $token = $_POST['_csrf_token'] ?? '';
    if (empty($token) || !hash_equals($_SESSION['_csrf_token'] ?? '', $token)) {
        die('Error de seguretat: token CSRF invàlid.');
    }
    $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
}

// ============================================================
// ALERTES PER AL PANELL ADMIN
// ============================================================

function obtenir_alertes_incompliment(PDO $pdo): array {
    $stmt = $pdo->prepare(
        'SELECT u.id, u.nom, u.cognom, r.hora_entrada, r.hores_totals
         FROM usuaris u
         JOIN registres_hores r ON u.id = r.usuari_id AND r.data = CURDATE()
         WHERE u.rol = \'empleat\'
           AND r.hora_sortida IS NOT NULL
           AND (r.hores_totals IS NULL OR r.hores_totals < 8)
         ORDER BY u.nom ASC'
    );
    $stmt->execute();
    return $stmt->fetchAll();
}

// ============================================================
// FUNCIÓ: Convertir hores decimals a format rellotge (HH:MM:SS)
// Ex: 7.5 → "07:30:00", 1.25 → "01:15:00", 0.75 → "00:45:00"
// ============================================================

function format_hores_rellotge(?float $hores): string {
    if (is_null($hores)) return '—';
    $signe = $hores < 0 ? '-' : '';
    $hores = abs($hores);
    $total_segons = round($hores * 3600);
    $h = intdiv($total_segons, 3600);
    $resta = $total_segons % 3600;
    $m = intdiv($resta, 60);
    $s = $resta % 60;
    return $signe . sprintf('%02d:%02d:%02d', $h, $m, $s);
}