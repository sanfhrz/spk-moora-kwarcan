<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../config/database.php';

if (!isset($_GET['kwarcan_id'])) {
    echo json_encode(['success' => false, 'message' => 'Kwarcan ID required']);
    exit();
}

$kwarcan_id = (int)$_GET['kwarcan_id'];

try {
    $stmt = $pdo->prepare("
        SELECT p.kriteria_id, p.nilai, k.nama_kriteria, k.bobot
        FROM penilaian p
        JOIN kriteria k ON p.kriteria_id = k.id
        WHERE p.kwarcan_id = ?
        ORDER BY k.id
    ");
    $stmt->execute([$kwarcan_id]);
    $assessment = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'assessment' => $assessment
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>