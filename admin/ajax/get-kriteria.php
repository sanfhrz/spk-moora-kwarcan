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
    $stmt = $pdo->prepare("SELECT * FROM kriteria WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $kriteria = $stmt->fetch();
    
    if (!$kriteria) {
        echo json_encode(['success' => false, 'message' => 'Data tidak ditemukan']);
        exit();
    }
    
    echo json_encode([
        'success' => true,
        'data' => $kriteria
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan: ' . $e->getMessage()
    ]);
}
?>