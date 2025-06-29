<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../admin/login.php');
    exit();
}

require_once '../config/database.php';

// Get kriteria and kwarcan data
try {
    $stmt = $pdo->query("SELECT id, nama_kriteria, bobot FROM kriteria ORDER BY id");
    $kriteria_list = $stmt->fetchAll();
    
    $stmt = $pdo->query("SELECT id, kode_kwarcan, nama_kwarcan, daerah FROM kwarcan WHERE status = 'aktif' ORDER BY kode_kwarcan");
    $kwarcan_list = $stmt->fetchAll();
    
} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}

// Create CSV content
$filename = 'template_penilaian_kwarcan_' . date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Expires: 0');

$output = fopen('php://output', 'w');

// Add BOM for UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Header row
$header = ['Kode Kwarcan', 'Nama Kwarcan', 'Daerah'];
foreach ($kriteria_list as $kriteria) {
    $header[] = $kriteria['nama_kriteria'] . ' (Bobot: ' . $kriteria['bobot'] . ')';
}
fputcsv($output, $header);

// Data rows
foreach ($kwarcan_list as $kwarcan) {
    $row = [
        $kwarcan['kode_kwarcan'],
        $kwarcan['nama_kwarcan'],
        $kwarcan['daerah']
    ];
    
    // Add empty cells for criteria values
    foreach ($kriteria_list as $kriteria) {
        $row[] = ''; // Empty value for user to fill
    }
    
    fputcsv($output, $row);
}

// Add instruction rows
fputcsv($output, []);
fputcsv($output, ['PETUNJUK PENGISIAN:']);
fputcsv($output, ['1. Isi nilai untuk setiap kriteria (0-50)']);
fputcsv($output, ['2. Jangan mengubah kolom Kode, Nama, dan Daerah']);
fputcsv($output, ['3. Simpan file dalam format .xlsx atau .csv']);
fputcsv($output, ['4. Upload kembali ke sistem']);

fclose($output);
exit();
?>