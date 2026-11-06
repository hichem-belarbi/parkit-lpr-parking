<?php
// api/access_checker.php
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Africa/Tunis');
require_once __DIR__ . '/config.php';

function respond($access, $message, $extra = []) {
    echo json_encode(
        array_merge(['access' => $access, 'message' => $message], $extra),
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

// --- Méthode HTTP ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    respond(false, "Méthode non autorisée : utilisez POST");
}

// --- Authentification ---
$providedKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if (!$providedKey || !hash_equals(API_KEY, $providedKey)) {
    http_response_code(401);
    respond(false, "Non autorisé : clé API invalide ou manquante");
}

// --- Lecture des paramètres ---
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$plateLeft   = trim($input['plate_left'] ?? '');
$plateRight  = trim($input['plate_right'] ?? '');
$plateFormat = trim($input['plate_format'] ?? 'STD');

if (!in_array($plateFormat, ['STD', 'RS', 'GOV'], true)) {
    $plateFormat = 'STD';
}

if (!$plateRight || ($plateFormat !== 'RS' && !$plateLeft)) {
    respond(false, "Format de plaque incomplet");
}

// --- Construction plate_digits / affichage ---
if ($plateFormat === 'RS') {
    $plateDigits  = "RS{$plateRight}";
    $displayPlate = "RS {$plateRight}";
} elseif ($plateFormat === 'GOV') {
    $plateDigits  = "{$plateLeft}{$plateRight}";
    $displayPlate = "{$plateLeft} - {$plateRight}";
} else {
    $plateDigits  = "{$plateLeft}{$plateRight}";
    $displayPlate = "{$plateLeft} تونس {$plateRight}";
}

try {
    $pdo = getDB();
    $today     = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day')); // pour les réservations qui passent minuit

    // --- 1. Réservations actives (aujourd'hui + hier) pour ce véhicule ---
    $stmt = $pdo->prepare("
        SELECT id, code, reservation_date, start_time, end_time,
               status, zone_id, owner_name, plate_format
        FROM reservations
        WHERE (plate_digits = :plate_digits OR (plate_left = :plate_left AND plate_right = :plate_right))
          AND plate_format = :plate_format
          AND reservation_date IN (:today, :yesterday)
          AND status IN ('confirmed', 'checked_in')
        ORDER BY reservation_date ASC, start_time ASC
    ");
    $stmt->execute([
        'plate_digits' => $plateDigits,
        'plate_left'   => $plateLeft,
        'plate_right'  => $plateRight,
        'plate_format' => $plateFormat,
        'today'        => $today,
        'yesterday'    => $yesterday,
    ]);
    $reservations = $stmt->fetchAll();

    if (!$reservations) {
        respond(false, "ACCÈS REFUSÉ | Aucune réservation active pour {$displayPlate}");
    }

    $now = new DateTime();
    $earliestFuture = null; // prochain créneau à venir
    $lastEnd        = null; // fin du dernier créneau expiré

    // --- 2. Évaluation de TOUS les créneaux ---
    foreach ($reservations as $res) {
        $startDt = new DateTime("{$res['reservation_date']} {$res['start_time']}");
        $endDt   = new DateTime("{$res['reservation_date']} {$res['end_time']}");

        // Réservation qui passe minuit (ex: 23:00 -> 00:39) : la fin est le lendemain
        if ($endDt <= $startDt) {
            $endDt->modify('+1 day');
        }

        // Créneau valide : on autorise immédiatement
        if ($now >= $startDt && $now <= $endDt) {
            if ($res['status'] === 'confirmed') {
                $upd = $pdo->prepare("UPDATE reservations SET status = 'checked_in' WHERE id = :id");
                $upd->execute(['id' => $res['id']]);
            }

            respond(true, "ACCÈS ACCORDÉ | Code: {$res['code']} | Zone ID: {$res['zone_id']} | Valide jusqu'à " . $endDt->format('H:i'), [
                'code'        => $res['code'],
                'zone_id'     => $res['zone_id'],
                'valid_until' => $endDt->format('H:i'),
            ]);
        }

        // Sinon, on mémorise pour choisir le meilleur message à la fin
        if ($now < $startDt && ($earliestFuture === null || $startDt < $earliestFuture)) {
            $earliestFuture = $startDt;
        }
        if ($now > $endDt && ($lastEnd === null || $endDt > $lastEnd)) {
            $lastEnd = $endDt;
        }
    }

    // --- 3. Aucun créneau valide : message le plus utile ---
    if ($earliestFuture) {
        respond(false, "ACCÈS REFUSÉ | Trop tôt : début à " . $earliestFuture->format('H:i'));
    }
    if ($lastEnd) {
        respond(false, "ACCÈS REFUSÉ | Période expirée (fin à " . $lastEnd->format('H:i') . ")");
    }
    respond(false, "ACCÈS REFUSÉ | Hors période de réservation");

} catch (Throwable $e) {
    error_log("[access_checker] " . $e->getMessage());
    http_response_code(500);
    respond(false, "Erreur serveur");
}