<?php
// api/zones.php
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/config.php';

$date = $_GET['date'] ?? date('Y-m-d');
$startTime = $_GET['start_time'] ?? date('H:i');

try {
    $db = getDB();

    // 1. Récupérer toutes les zones
    $stmt = $db->query("SELECT id, code, label, capacity FROM zones");
    $zones = $stmt->fetchAll();

    // 2. Compter les réservations actives pour la date et l'heure sélectionnées
    $sqlCount = "
        SELECT zone_id, COUNT(*) as reserved_count
        FROM reservations
        WHERE reservation_date = :res_date
          AND status IN ('confirmed', 'checked_in')
          AND :start_time BETWEEN start_time AND end_time
        GROUP BY zone_id
    ";
    $stmtCount = $db->prepare($sqlCount);
    $stmtCount->execute([
        'res_date'   => $date,
        'start_time' => $startTime
    ]);
    $reservedData = $stmtCount->fetchAll(PDO::FETCH_KEY_PAIR); // [zone_id => count]

    $responseZones = [];

    foreach ($zones as $zone) {
        $zoneId = $zone['id'];
        $capacity = (int)$zone['capacity'];
        $occupied = (int)($reservedData[$zoneId] ?? 0);
        $available = max(0, $capacity - $occupied);

        // Découpage du label pour sous-titre
        $parts = explode('—', $zone['label']);
        $subtitle = isset($parts[1]) ? trim($parts[1]) : '';

        $responseZones[] = [
            'id'        => $zoneId,
            'code'      => $zone['code'],
            'label'     => trim($parts[0]),
            'subtitle'  => $subtitle,
            'capacity'  => $capacity,
            'available' => $available,
            'occupied'  => $occupied
        ];
    }

    echo json_encode([
        'status' => 'success',
        'date'   => $date,
        'time'   => $startTime,
        'zones'  => $responseZones
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Erreur serveur lors de la récupération des zones : ' . $e->getMessage()
    ]);
}