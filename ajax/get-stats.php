<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../config/database.php';

try {
    // Get statistics
    $stats = [];
    
    // Total Kwarcan
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM kwarcan WHERE status = 'aktif'");
    $stats['total_kwarcan'] = $stmt->fetch()['total'];
    
    // Total Kriteria
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM kriteria");
    $stats['total_kriteria'] = $stmt->fetch()['total'];
    
    // Total Penilaian (unique kwarcan yang sudah dinilai)
    $stmt = $pdo->query("SELECT COUNT(DISTINCT kwarcan_id) as total FROM penilaian");
    $stats['total_penilaian'] = $stmt->fetch()['total'];
    
    // Progress penilaian
    $stats['progress_penilaian'] = $stats['total_kwarcan'] > 0 ? 
        round(($stats['total_penilaian'] / $stats['total_kwarcan']) * 100, 1) : 0;
    
    // Recent activities count
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT p.kwarcan_id) as count
        FROM penilaian p 
        WHERE p.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $stats['recent_activities'] = $stmt->fetch()['count'];
    
    echo json_encode([
        'success' => true,
        'stats' => $stats
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>