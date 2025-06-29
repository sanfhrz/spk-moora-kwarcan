<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../../config/database.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
    exit();
}

try {
    // Get kwarcan data
    $stmt = $pdo->prepare("SELECT * FROM kwarcan WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $kwarcan = $stmt->fetch();
    
    if (!$kwarcan) {
        echo json_encode(['success' => false, 'message' => 'Data tidak ditemukan']);
        exit();
    }
    
    // Get penilaian data
    $stmt = $pdo->prepare("
        SELECT p.*, k.kode_kriteria, k.nama_kriteria, k.bobot 
        FROM penilaian p 
        JOIN kriteria k ON p.kriteria_id = k.id 
        WHERE p.kwarcan_id = ? 
        ORDER BY k.kode_kriteria
    ");
    $stmt->execute([$_GET['id']]);
    $penilaian = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => $kwarcan,
        'penilaian' => $penilaian
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan: ' . $e->getMessage()
    ]);
}
?>