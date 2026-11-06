<?php
// api/reservations.php
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/config.php';

try {
    $db = getDB();

    $code        = isset($_GET['code']) ? trim($_GET['code']) : '';
    $plateDigits = isset($_GET['plate_digits']) ? trim($_GET['plate_digits']) : '';
    $plateLeft   = isset($_GET['plate_left']) ? trim($_GET['plate_left']) : '';
    $plateRight  = isset($_GET['plate_right']) ? trim($_GET['plate_right']) : '';

    // Reconstitution de plate_digits si non fourni directement
    if (empty($plateDigits) && !empty($plateLeft) && !empty($plateRight)) {
        $plateDigits = $plateLeft . $plateRight;
    }

    if (empty($code) && empty($plateDigits)) {
        throw new Exception("Veuillez fournir un code de réservation ou une plaque d'immatriculation.");
    }

    // Construction dynamique de la requête SQL
    $where = [];
    $params = [];

    if (!empty($code)) {
        $where[] = "r.code = :code";
        $params['code'] = $code;
    }

    if (!empty($plateDigits)) {
        $where[] = "r.plate_digits = :plate_digits";
        $params['plate_digits'] = $plateDigits;
    }

    $sql = "
        SELECT 
            r.id,
            r.code,
            r.plate_left,
            r.plate_right,
            r.reservation_date,
            r.start_time,
            r.end_time,
            r.grace_minutes,
            r.price_tnd,
            r.status,
            z.code AS zone_code,
            z.label AS zone_label
        FROM reservations r
        JOIN zones z ON r.zone_id = z.id
        WHERE " . implode(' OR ', $where) . "
        ORDER BY r.created_at DESC
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $reservations = [];

    foreach ($rows as $row) {
        $formattedPlate = $row['plate_left'] . ' TN ' . $row['plate_right'];
        
        // Calcul de la limite de tolérance (start_time + grace_minutes)
        $startTimestamp = strtotime($row['reservation_date'] . ' ' . $row['start_time']);
        $graceDeadlineTimestamp = $startTimestamp + ($row['grace_minutes'] * 60);
        $graceDeadline = date('H:i', $graceDeadlineTimestamp);

        $reservations[] = [
            'id'               => $row['id'],
            'code'             => $row['code'],
            'plate'            => $formattedPlate,
            'zone_code'        => $row['zone_code'],
            'zone_label'       => $row['zone_label'],
            'reservation_date' => date('d/m/Y', strtotime($row['reservation_date'])),
            'start_time'       => substr($row['start_time'], 0, 5),
            'ends_at'          => substr($row['end_time'], 0, 5),
            'grace_deadline'   => $graceDeadline,
            'price_tnd'        => number_format($row['price_tnd'], 2, '.', ''),
            'status'           => $row['status']
        ];
    }

    echo json_encode([
        'status'       => 'success',
        'count'        => count($reservations),
        'reservations' => $reservations
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}