<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit();
}

require_once '../../config/database.php';

// Get filter parameters
$search = $_GET['search'] ?? '';
$jenis_filter = $_GET['jenis'] ?? '';

$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(nama_kriteria LIKE ? OR kode_kriteria LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($jenis_filter)) {
    $where_conditions[] = "jenis = ?";
    $params[] = $jenis_filter;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

try {
    $query = "SELECT k.*, 
              (SELECT COUNT(*) FROM penilaian p WHERE p.kriteria_id = k.id) as penilaian_count,
              (SELECT AVG(p.nilai) FROM penilaian p WHERE p.kriteria_id = k.id) as rata_nilai
              FROM kriteria k 
              $where_clause 
              ORDER BY k.kode_kriteria ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $kriteria_list = $stmt->fetchAll();
    
    // Set headers for CSV download
    $filename = 'kriteria_' . date('Y-m-d_H-i-s') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // CSV headers
    fputcsv($output, [
        'No',
        'Kode Kriteria',
        'Nama Kriteria',
        'Bobot',
        'Persentase Bobot',
        'Jenis',
        'Jumlah Penilaian',
        'Rata-rata Nilai',
        'Keterangan',
        'Tanggal Dibuat',
        'Terakhir Diupdate'
    ]);
    
    // CSV data
    foreach ($kriteria_list as $index => $kriteria) {
        fputcsv($output, [
            $index + 1,
            $kriteria['kode_kriteria'],
            $kriteria['nama_kriteria'],
            $kriteria['bobot'],
            number_format($kriteria['bobot'] * 100, 1) . '%',
            ucfirst($kriteria['jenis']),
            $kriteria['penilaian_count'],
            $kriteria['rata_nilai'] ? number_format($kriteria['rata_nilai'], 2) : '-',
            $kriteria['keterangan'] ?? '',
            date('d/m/Y H:i', strtotime($kriteria['created_at'])),
            date('d/m/Y H:i', strtotime($kriteria['updated_at']))
        ]);
    }
    
    fclose($output);
    
} catch (Exception $e) {
    header('Content-Type: text/html');
    echo "Error: " . $e->getMessage();
}
?>