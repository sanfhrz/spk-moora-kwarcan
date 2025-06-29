<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../admin/login.php');
    exit();
}

require_once '../config/database.php';

try {
    // Get matrix data
    $stmt = $pdo->query("
        SELECT 
            k.id,
            k.kode_kwarcan,
            k.nama_kwarcan,
            k.daerah,
            kr.id as kriteria_id,
            kr.nama_kriteria,
            kr.bobot,
            p.nilai
        FROM kwarcan k
        CROSS JOIN kriteria kr
        LEFT JOIN penilaian p ON k.id = p.kwarcan_id AND kr.id = p.kriteria_id
        WHERE k.status = 'aktif'
        ORDER BY k.kode_kwarcan, kr.id
    ");
    
    $data = $stmt->fetchAll();
    
    // Get kriteria list
    $stmt = $pdo->query("SELECT * FROM kriteria ORDER BY id");
    $kriteria_list = $stmt->fetchAll();
    
    // Organize data
    $matrix = [];
    foreach ($data as $row) {
        $kwarcan_id = $row['id'];
        if (!isset($matrix[$kwarcan_id])) {
            $matrix[$kwarcan_id] = [
                'kode' => $row['kode_kwarcan'],
                'nama' => $row['nama_kwarcan'],
                'daerah' => $row['daerah'],
                'nilai' => []
            ];
        }
        $matrix[$kwarcan_id]['nilai'][$row['kriteria_id']] = $row['nilai'] ?? '';
    }
    
} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}

// Export as CSV
$filename = 'matrix_penilaian_' . date('Y-m-d_H-i-s') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Expires: 0');

$output = fopen('php://output', 'w');

// Add BOM for UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Header row
$header = ['Kode', 'Nama Kwarcan', 'Daerah'];
foreach ($kriteria_list as $kriteria) {
    $header[] = $kriteria['nama_kriteria'] . ' (' . $kriteria['bobot'] . ')';
}
fputcsv($output, $header);

// Data rows
foreach ($matrix as $row) {
    $csv_row = [$row['kode'], $row['nama'], $row['daerah']];
    foreach ($kriteria_list as $kriteria) {
        $csv_row[] = $row['nilai'][$kriteria['id']] ?? '';
    }
    fputcsv($output, $csv_row);
}

fclose($output);
exit();
?>