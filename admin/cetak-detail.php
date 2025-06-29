<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit();
}

require_once '../config/database.php';

$kwarcan_id = $_GET['id'] ?? 0;

// Get kwarcan info
$stmt = $pdo->prepare("SELECT * FROM kwarcan WHERE id = ?");
$stmt->execute([$kwarcan_id]);
$kwarcan = $stmt->fetch();

if (!$kwarcan) {
    die('Kwarcan tidak ditemukan');
}

// Get MOORA result
$stmt = $pdo->prepare("
    SELECT nilai_optimasi, ranking 
    FROM hasil_moora 
    WHERE kwarcan_id = ?
");
$stmt->execute([$kwarcan_id]);
$moora_result = $stmt->fetch();

// Get detailed penilaian
$stmt = $pdo->prepare("
    SELECT p.*, k.nama_kriteria, k.bobot, k.jenis
    FROM penilaian p
    JOIN kriteria k ON p.kriteria_id = k.id
    WHERE p.kwarcan_id = ?
    ORDER BY k.id
");
$stmt->execute([$kwarcan_id]);
$penilaian = $stmt->fetchAll();

// Get summary stats
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_kriteria,
        AVG(p.nilai) as rata_nilai,
        SUM(p.nilai_terbobot) as total_terbobot,
        MIN(p.nilai) as min_nilai,
        MAX(p.nilai) as max_nilai
    FROM penilaian p
    WHERE p.kwarcan_id = ?
");
$stmt->execute([$kwarcan_id]);
$summary = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Kwarcan - <?= htmlspecialchars($kwarcan['nama_kwarcan']) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background: white;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #667eea;
        }
        
        .header h1 {
            color: #667eea;
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .header p {
            color: #666;
            font-size: 1.1rem;
        }
        
        .print-info {
            text-align: right;
            margin-bottom: 20px;
            font-size: 0.9rem;
            color: #888;
        }
        
        .kwarcan-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border-left: 5px solid #667eea;
        }
        
        .kwarcan-info h2 {
            color: #333;
            margin-bottom: 15px;
            font-size: 1.5rem;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .info-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .info-label {
            font-weight: 600;
            color: #555;
            min-width: 100px;
        }
        
        .info-value {
            color: #333;
        }
        
        .ranking-section {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .ranking-section h3 {
            margin-bottom: 15px;
            font-size: 1.3rem;
        }
        
        .ranking-display {
            display: flex;
            justify-content: center;
            gap: 40px;
            align-items: center;
        }
        
        .rank-item {
            text-align: center;
        }
        
        .rank-number {
            font-size: 2.5rem;
            font-weight: 700;
            display: block;
            margin-bottom: 5px;
        }
        
        .rank-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .summary-section {
            margin-bottom: 30px;
        }
        
        .summary-section h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 1.3rem;
            border-bottom: 2px solid #667eea;
            padding-bottom: 5px;
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .summary-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            border: 1px solid #dee2e6;
        }
        
        .summary-card h4 {
            font-size: 1.5rem;
            color: #667eea;
            margin-bottom: 5px;
        }
        
        .summary-card p {
            color: #666;
            font-size: 0.9rem;
        }
        
        .penilaian-section {
            margin-bottom: 30px;
        }
        
        .penilaian-section h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 1.3rem;
            border-bottom: 2px solid #667eea;
            padding-bottom: 5px;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .table th,
        .table td {
            padding: 12px;
            text-align: left;
            border: 1px solid #dee2e6;
        }
        
        .table th {
            background: #667eea;
            color: white;
            font-weight: 600;
            text-align: center;
        }
        
        .table tbody tr:nth-child(even) {
            background: #f8f9fa;
        }
        
        .table tbody tr:hover {
            background: #e9ecef;
        }
        
        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .badge-benefit {
            background: #28a745;
            color: white;
        }
        
        .badge-cost {
            background: #ffc107;
            color: #212529;
        }
        
        .nilai-cell {
            text-align: center;
            font-weight: 600;
        }
        
        .nilai-normal {
            color: #007bff;
            font-family: 'Courier New', monospace;
        }
        
        .nilai-terbobot {
            color: #28a745;
            font-family: 'Courier New', monospace;
        }
        
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #dee2e6;
            text-align: center;
            color: #666;
        }
        
        .no-print {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
        
        .btn {
            padding: 10px 20px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
            margin: 0 5px;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn:hover {
            background: #5a6fd8;
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            
            body {
                padding: 0;
                font-size: 12px;
            }
            
            .header h1 {
                font-size: 1.5rem;
            }
            
            .ranking-section {
                background: #f8f9fa !important;
                color: #333 !important;
                border: 2px solid #667eea;
            }
            
            .table th {
                background: #f8f9fa !important;
                color: #333 !important;
                border: 2px solid #333 !important;
            }
            
            .summary-card h4 {
                color: #333 !important;
            }
        }
        
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .ranking-display {
                flex-direction: column;
                gap: 20px;
            }
            
            .summary-grid {
                grid-template-columns: 1fr;
            }
            
            .table {
                font-size: 0.8rem;
            }
            
            .table th,
            .table td {
                padding: 8px 4px;
            }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="btn" onclick="window.print()">
            <i class="fas fa-print"></i> Cetak
        </button>
        <button class="btn btn-secondary" onclick="window.close()">
            <i class="fas fa-times"></i> Tutup
        </button>
    </div>

    <div class="header">
        <h1>SISTEM PENDUKUNG KEPUTUSAN MOORA</h1>
        <p>Detail Penilaian Kwarcan</p>
    </div>

    <div class="print-info">
        <p>Dicetak pada: <?= date('d/m/Y H:i:s') ?></p>
    </div>

    <div class="kwarcan-info">
        <h2>Informasi Kwarcan</h2>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Nama:</span>
                <span class="info-value"><?= htmlspecialchars($kwarcan['nama_kwarcan']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Kode:</span>
                <span class="info-value"><?= htmlspecialchars($kwarcan['kode_kwarcan']) ?></span>
                            </div>
            <div class="info-item">
                <span class="info-label">Daerah:</span>
                <span class="info-value"><?= htmlspecialchars($kwarcan['daerah']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Alamat:</span>
                <span class="info-value"><?= htmlspecialchars($kwarcan['alamat']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Kontak:</span>
                <span class="info-value"><?= htmlspecialchars($kwarcan['kontak']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Email:</span>
                <span class="info-value"><?= htmlspecialchars($kwarcan['email']) ?></span>
            </div>
        </div>
    </div>

    <?php if ($moora_result): ?>
    <div class="ranking-section">
        <h3>Hasil Perhitungan MOORA</h3>
        <div class="ranking-display">
            <div class="rank-item">
                <span class="rank-number">#<?= $moora_result['ranking'] ?></span>
                <span class="rank-label">Peringkat</span>
            </div>
            <div class="rank-item">
                <span class="rank-number"><?= number_format($moora_result['nilai_optimasi'], 4) ?></span>
                <span class="rank-label">Nilai Optimasi</span>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($summary): ?>
    <div class="summary-section">
        <h3>Ringkasan Penilaian</h3>
        <div class="summary-grid">
            <div class="summary-card">
                <h4><?= $summary['total_kriteria'] ?></h4>
                <p>Total Kriteria</p>
            </div>
            <div class="summary-card">
                <h4><?= number_format($summary['rata_nilai'], 2) ?></h4>
                <p>Rata-rata Nilai</p>
            </div>
            <div class="summary-card">
                <h4><?= number_format($summary['total_terbobot'], 4) ?></h4>
                <p>Total Nilai Terbobot</p>
            </div>
            <div class="summary-card">
                <h4><?= $summary['min_nilai'] ?> - <?= $summary['max_nilai'] ?></h4>
                <p>Rentang Nilai</p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="penilaian-section">
        <h3>Detail Penilaian per Kriteria</h3>
        
        <?php if (empty($penilaian)): ?>
            <div style="text-align: center; padding: 40px; background: #f8f9fa; border-radius: 8px;">
                <h4 style="color: #666; margin-bottom: 10px;">Belum Ada Penilaian</h4>
                <p style="color: #888;">Kwarcan ini belum memiliki data penilaian untuk kriteria apapun.</p>
            </div>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 5%;">No</th>
                        <th style="width: 30%;">Nama Kriteria</th>
                        <th style="width: 10%;">Jenis</th>
                        <th style="width: 10%;">Bobot</th>
                        <th style="width: 15%;">Nilai Normal</th>
                        <th style="width: 15%;">Nilai Terbobot</th>
                        <th style="width: 15%;">Tanggal Input</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($penilaian as $index => $item): ?>
                    <tr>
                        <td style="text-align: center;"><?= $index + 1 ?></td>
                        <td>
                            <strong><?= htmlspecialchars($item['nama_kriteria']) ?></strong>
                        </td>
                        <td style="text-align: center;">
                            <span class="badge <?= $item['jenis'] == 'benefit' ? 'badge-benefit' : 'badge-cost' ?>">
                                <?= ucfirst($item['jenis']) ?>
                            </span>
                        </td>
                        <td style="text-align: center;">
                            <strong><?= number_format($item['bobot'], 2) ?></strong>
                        </td>
                        <td class="nilai-cell">
                            <span class="nilai-normal"><?= number_format($item['nilai'], 2) ?></span>
                        </td>
                        <td class="nilai-cell">
                            <span class="nilai-terbobot"><?= number_format($item['nilai_terbobot'], 4) ?></span>
                        </td>
                        <td style="text-align: center;">
                            <?= date('d/m/Y H:i', strtotime($item['created_at'])) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div style="margin-top: 20px; padding: 15px; background: #e9ecef; border-radius: 8px;">
                <h4 style="margin-bottom: 10px; color: #333;">Keterangan:</h4>
                <ul style="margin: 0; padding-left: 20px; color: #666;">
                    <li><strong>Benefit:</strong> Kriteria yang semakin tinggi nilainya semakin baik</li>
                    <li><strong>Cost:</strong> Kriteria yang semakin rendah nilainya semakin baik</li>
                    <li><strong>Nilai Normal:</strong> Nilai asli yang diinputkan</li>
                    <li><strong>Nilai Terbobot:</strong> Nilai setelah dikalikan dengan bobot kriteria</li>
                </ul>
            </div>
        <?php endif; ?>
    </div>

    <div class="footer">
        <p><strong>Sistem Pendukung Keputusan MOORA</strong></p>
        <p>Dokumen ini digenerate secara otomatis pada <?= date('d F Y, H:i:s') ?></p>
        <p style="margin-top: 10px; font-size: 0.8rem; color: #888;">
            © <?= date('Y') ?> SPK MOORA - Semua hak dilindungi
        </p>
    </div>

    <script>
        // Auto print when page loads (optional)
        // window.onload = function() {
        //     setTimeout(function() {
        //         window.print();
        //     }, 500);
        // };
        
        // Close window after printing
        window.onafterprint = function() {
            // window.close();
        };
    </script>
</body>
</html>
