<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../config/database.php';

if (!isset($_FILES['excel_file'])) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded']);
    exit();
}

$file = $_FILES['excel_file'];

// Validate file
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Upload error']);
    exit();
}

$allowedTypes = [
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-excel',
    'text/csv'
];

if (!in_array($file['type'], $allowedTypes)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type']);
    exit();
}

// Simple CSV parser (for basic implementation)
if ($file['type'] === 'text/csv' || pathinfo($file['name'], PATHINFO_EXTENSION) === 'csv') {
    try {
        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            throw new Exception('Cannot open file');
        }
        
        // Get header row
        $header = fgetcsv($handle);
        if (!$header) {
            throw new Exception('Invalid file format');
        }
        
        // Get kriteria mapping
        $stmt = $pdo->query("SELECT id, nama_kriteria FROM kriteria ORDER BY id");
        $kriteria_map = [];
        while ($row = $stmt->fetch()) {
            $kriteria_map[$row['nama_kriteria']] = $row['id'];
        }
        
        $imported = 0;
        $errors = [];
        
        while (($data = fgetcsv($handle)) !== FALSE) {
            if (empty($data[0]) || $data[0] === 'PETUNJUK PENGISIAN:') {
                continue; // Skip empty or instruction rows
            }
            
            $kode_kwarcan = trim($data[0]);
            
            // Find kwarcan by code
            $stmt = $pdo->prepare("SELECT id FROM kwarcan WHERE kode_kwarcan = ?");
            $stmt->execute([$kode_kwarcan]);
            $kwarcan = $stmt->fetch();
            
            if (!$kwarcan) {
                $errors[] = "Kwarcan dengan kode {$kode_kwarcan} tidak ditemukan";
                continue;
            }
            
            $kwarcan_id = $kwarcan['id'];
            
            // Process criteria values (starting from column 3)
            $kriteria_index = 0;
            for ($i = 3; $i < count($data); $i++) {
                $nilai = trim($data[$i]);
                if ($nilai === '' || !is_numeric($nilai)) {
                    continue;
                }
                
                $nilai = (float)$nilai;
                if ($nilai < 0 || $nilai > 50) {
                    $errors[] = "Nilai {$nilai} untuk {$kode_kwarcan} tidak valid (harus 0-50)";
                    continue;
                }
                
                // Get kriteria ID (assuming order matches)
                $kriteria_ids = array_values($kriteria_map);
                if (isset($kriteria_ids[$kriteria_index])) {
                    $kriteria_id = $kriteria_ids[$kriteria_index];
                    
                    // Insert or update penilaian
                    $stmt = $pdo->prepare("
                        INSERT INTO penilaian (kwarcan_id, kriteria_id, nilai) 
                        VALUES (?, ?, ?)
                        ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)
                    ");
                    $stmt->execute([$kwarcan_id, $kriteria_id, $nilai]);
                }
                
                $kriteria_index++;
            }
            
            $imported++;
        }
        
        fclose($handle);
        
        if ($imported > 0) {
            echo json_encode([
                'success' => true,
                'message' => "Berhasil import {$imported} data penilaian",
                'errors' => $errors
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Tidak ada data yang berhasil diimport',
                'errors' => $errors
            ]);
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error processing file: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Excel file processing requires additional library (PHPSpreadsheet)'
    ]);
}
?>