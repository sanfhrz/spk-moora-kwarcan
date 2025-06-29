<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit();
}

require_once '../../config/database.php';

// Get filter parameters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Build query
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(nama_kwarcan LIKE ? OR daerah LIKE ? OR kode_kwarcan LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($status_filter)) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

try {
    $query = "SELECT k.kode_kwarcan, k.nama_kwarcan, k.daerah, k.kontak, k.status, k.keterangan,
              (SELECT COUNT(*) FROM penilaian p WHERE p.kwarcan_id = k.id) as penilaian_count,
              (SELECT AVG(p.nilai) FROM penilaian p WHERE p.kwarcan_id = k.id) as rata_nilai,
              k.created_at
              FROM kwarcan k 
              $where_clause 
              ORDER BY k.kode_kwarcan ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $data = $stmt->fetchAll();
    
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="data_kwarcan_' . date('Y-m-d_H-i-s') . '.csv"');
    
    // Create file pointer
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Add CSV headers
    fputcsv($output, [
        'Kode Kwarcan',
        'Nama Kwarcan', 
        'Daerah',
        'Kontak',
        'Status',
        'Keterangan',
        'Jumlah Penilaian',
        'Rata-rata Nilai',
        'Tanggal Dibuat'
    ]);
    
    // Add data rows
    foreach ($data as $row) {
        fputcsv($output, [
            $row['kode_kwarcan'],
            $row['nama_kwarcan'],
            $row['daerah'],
            $row['kontak'] ?: '-',
            ucfirst($row['status']),
            $row['keterangan'] ?: '-',
            $row['penilaian_count'],
            $row['rata_nilai'] ? number_format($row['rata_nilai'], 2) : '-',
            date('d/m/Y H:i', strtotime($row['created_at']))
        ]);
    }
    
    fclose($output);
    
} catch (Exception $e) {
    header('Content-Type: text/html');
    echo "Error: " . $e->getMessage();
}
?>