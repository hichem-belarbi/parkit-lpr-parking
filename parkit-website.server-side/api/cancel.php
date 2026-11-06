<?php
// api/cancel.php
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Réponse immédiate pour les requêtes de pré-vérification CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/config.php';

try {
    // 1. Lire le corps de la requête JSON
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);

    $code = isset($data['code']) ? trim($data['code']) : '';

    if (empty($code)) {
        throw new Exception("Le code de réservation est requis pour effectuer l'annulation.");
    }

    $db = getDB();

    // 2. Vérifier si la réservation existe
    $stmt = $db->prepare("SELECT id, status, reservation_date, start_time FROM reservations WHERE code = :code");
    $stmt->execute(['code' => $code]);
    $reservation = $stmt->fetch();

    if (!$reservation) {
        throw new Exception("Aucune réservation trouvée avec le code : " . htmlspecialchars($code));
    }

    // 3. Contrôle des règles métier avant annulation
    if ($reservation['status'] === 'cancelled') {
        throw new Exception("Cette réservation est déjà annulée.");
    }

    if ($reservation['status'] === 'checked_in') {
        throw new Exception("Impossible d'annuler une réservation déjà validée à l'entrée du parking.");
    }

    if ($reservation['status'] === 'completed' || $reservation['status'] === 'expired') {
        throw new Exception("Impossible d'annuler une réservation déjà terminée ou expirée.");
    }

    // 4. Mettre à jour le statut dans la BDD
    $updateStmt = $db->prepare("
        UPDATE reservations 
        SET status = 'cancelled' 
        WHERE id = :id
    ");
    $updateStmt->execute(['id' => $reservation['id']]);

    // 5. Réponse de succès
    echo json_encode([
        'status'  => 'success',
        'message' => 'La réservation ' . $code . ' a été annulée avec succès.',
        'code'    => $code
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}