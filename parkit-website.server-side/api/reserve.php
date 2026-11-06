<?php
// api/reserve.php
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/config.php';

try {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);

    if (!$data) {
        throw new Exception("Données reçues invalides (JSON attendu).");
    }

    $plateFormat = $data['plate_format'] ?? 'STD';
    $plateLeft   = strtoupper(trim($data['plate_left'] ?? ''));
    $plateRight  = preg_replace('/\D/', '', $data['plate_right'] ?? '');
    $ownerName   = !empty($data['owner_name']) ? trim($data['owner_name']) : null;
    $phone       = !empty($data['phone']) ? trim($data['phone']) : null;
    $zoneCode    = $data['zone'] ?? 'A';
    $date        = $data['date'] ?? '';
    $startTime   = $data['start_time'] ?? '';
    $forfaitId   = (int)($data['forfait_id'] ?? 1);
    $duration    = (int)($data['duration'] ?? 60);

    if (empty($plateLeft) || empty($plateRight)) {
        throw new Exception("Veuillez saisir un numéro d'immatriculation valide.");
    }
    if (empty($date) || empty($startTime)) {
        throw new Exception("Veuillez sélectionner une date et une heure de début.");
    }

    // Génération de plate_digits selon le format
    $plateDigits = $data['plate_digits'] ?? ($plateFormat === 'RS' ? "RS{$plateRight}" : "{$plateLeft}{$plateRight}");

    $db = getDB();

    // 1. Zone
    $stmtZone = $db->prepare("SELECT id, label, capacity FROM zones WHERE code = :code");
    $stmtZone->execute(['code' => $zoneCode]);
    $zone = $stmtZone->fetch();
    if (!$zone) throw new Exception("La zone sélectionnée est introuvable.");

    // 2. Forfait
    $stmtForfait = $db->prepare("SELECT id, nom, type, duree_minutes, prix_tnd, grace_minutes FROM forfaits WHERE id = :id AND actif = 1");
    $stmtForfait->execute(['id' => $forfaitId]);
    $forfait = $stmtForfait->fetch();
    if (!$forfait) throw new Exception("Le forfait sélectionné est invalide.");

    // 3. Durée & Heures
    $graceMinutes = (int)$forfait['grace_minutes'];
    if ($forfait['type'] === 'horaire') {
        $hours = $duration / 60;
        $price = $hours * (float)$forfait['prix_tnd'];
        $effectiveDuration = $duration;
    } else {
        $price = (float)$forfait['prix_tnd'];
        $effectiveDuration = (int)$forfait['duree_minutes'];
    }

    $startTimestamp = strtotime("$date $startTime");
    $endTimestamp   = $startTimestamp + ($effectiveDuration * 60);
    $endTime        = date('H:i:s', $endTimestamp);

    // 4. Disponibilité
    $stmtCheck = $db->prepare("
        SELECT COUNT(*) FROM reservations
        WHERE zone_id = :zone_id
          AND reservation_date = :res_date
          AND status IN ('confirmed', 'checked_in')
          AND (:start_time < end_time AND :end_time > start_time)
    ");
    $stmtCheck->execute([
        'zone_id'    => $zone['id'],
        'res_date'   => $date,
        'start_time' => $startTime,
        'end_time'   => $endTime
    ]);
    if ($stmtCheck->fetchColumn() >= $zone['capacity']) {
        throw new Exception("Désolé, la zone " . $zoneCode . " est complète pour ce créneau.");
    }

    // 5. Code Réservation
    $resCode = 'PN-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));

    // 6. Insertion
    $sqlInsert = "
        INSERT INTO reservations (
            code, plate_format, plate_left, plate_right, plate_digits,
            owner_name, phone, forfait_id, zone_id,
            reservation_date, start_time, end_time, grace_minutes, price_tnd, status
        ) VALUES (
            :code, :plate_format, :plate_left, :plate_right, :plate_digits,
            :owner_name, :phone, :forfait_id, :zone_id,
            :res_date, :start_time, :end_time, :grace_minutes, :price_tnd, 'confirmed'
        )
    ";

    $stmtInsert = $db->prepare($sqlInsert);
    $stmtInsert->execute([
        'code'             => $resCode,
        'plate_format'     => $plateFormat,
        'plate_left'       => $plateLeft,
        'plate_right'      => $plateRight,
        'plate_digits'     => $plateDigits,
        'owner_name'       => $ownerName,
        'phone'            => $phone,
        'forfait_id'       => $forfait['id'],
        'zone_id'          => $zone['id'],
        'res_date'         => $date,
        'start_time'       => $startTime,
        'end_time'         => $endTime,
        'grace_minutes'    => $graceMinutes,
        'price_tnd'        => $price
    ]);

    $formattedPlate = ($plateFormat === 'RS') ? "RS {$plateRight}" : "{$plateLeft} TN {$plateRight}";

    echo json_encode([
        'status' => 'success',
        'reservation' => [
            'code'          => $resCode,
            'plate'         => $formattedPlate,
            'zone'          => $zoneCode,
            'date'          => $date,
            'start_time'    => substr($startTime, 0, 5),
            'end_time'      => substr($endTime, 0, 5),
            'price_tnd'     => number_format($price, 2, '.', '')
        ]
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}