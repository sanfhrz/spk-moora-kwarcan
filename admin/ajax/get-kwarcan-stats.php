<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../../config/database.php';

try {
    $stats_query = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'aktif' THEN 1 ELSE 0 END) as aktif,
        SUM(CASE WHEN status = 'nonaktif' THEN 1 ELSE 0 END) as nonaktif,
        (SELECT COUNT(DISTINCT kwarcan_id) FROM penilaian) as sudah_dinilai
        FROM kwarcan";
    
    $stmt = $pdo->query($stats_query);
    $stats = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'stats' => $stats
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan: ' . $e->getMessage()
    ]);
}
?>