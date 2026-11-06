<?php
header('Content-Type: application/json; charset=utf-8');

$host = 'localhost';
$db   = 'park_reservation_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // 1. Capacité totale
    $stmtCap = $pdo->query("SELECT SUM(capacity) AS total_capacity FROM zones");
    $totalCapacity = (int)($stmtCap->fetch()['total_capacity'] ?? 0);

    // 2. Disponibilité par zone
    $stmtZones = $pdo->query("
        SELECT z.code, z.label, z.capacity, 
               COUNT(r.id) AS occupied
        FROM zones z
        LEFT JOIN reservations r ON r.zone_id = z.id 
             AND r.reservation_date = CURDATE() 
             AND r.status IN ('confirmed', 'checked_in')
             AND r.start_time <= CURTIME() AND r.end_time >= CURTIME()
        GROUP BY z.id, z.code, z.label, z.capacity
        ORDER BY z.code ASC
    ");
    $zonesData = $stmtZones->fetchAll();

    $zones = [];
    $occupiedSpots = 0;
    foreach ($zonesData as $z) {
        $cap  = (int)$z['capacity'];
        $occ  = (int)$z['occupied'];
        $free = max(0, $cap - $occ);
        $percentFree = $cap > 0 ? round(($free / $cap) * 100) : 0;

        $occupiedSpots += $occ;

        $zones[] = [
            'code'         => $z['code'],
            'label'        => $z['label'],
            'capacity'     => $cap,
            'free'         => $free,
            'percent_free' => $percentFree
        ];
    }

    $freeSpots = max(0, $totalCapacity - $occupiedSpots);
    $occupancyRate = $totalCapacity > 0 ? round(($occupiedSpots / $totalCapacity) * 100) : 0;

    echo json_encode([
        'status' => 'success',
        'stats'  => [
            'total_spots'    => $totalCapacity,
            'free_spots'     => $freeSpots,
            'occupancy_rate' => $occupancyRate,
            'zones'          => $zones
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Impossible de charger les disponibilités.'
    ]);
}
?>