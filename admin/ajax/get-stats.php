<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    exit();
}

require_once '../../config/database.php';

try {
    // Get all statistics
    $stats = [];
    
    // Total Kwarcan
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM kwarcan WHERE status = 'aktif'");
    $stats['total_kwarcan'] = $stmt->fetch()['total'];
    
    // Total Kriteria
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM kriteria");
    $stats['total_kriteria'] = $stmt->fetch()['total'];
    
    // Total Penilaian
    $stmt = $pdo->query("SELECT COUNT(DISTINCT kwarcan_id) as total FROM penilaian");
    $stats['total_penilaian'] = $stmt->fetch()['total'];
    
    // Total Hasil MOORA
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM hasil_moora");
    $stats['total_hasil'] = $stmt->fetch()['total'];
    
    // Progress penilaian
    $stats['progress_penilaian'] = $stats['total_kwarcan'] > 0 ? 
        round(($stats['total_penilaian'] / $stats['total_kwarcan']) * 100, 1) : 0;
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'data' => $stats
    ]);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching stats: ' . $e->getMessage()
    ]);
}
?>
