<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../../config/database.php';

$kwarcan_id = $_GET['id'] ?? 0;

try {
    // Get kwarcan info
    $stmt = $pdo->prepare("SELECT * FROM kwarcan WHERE id = ?");
    $stmt->execute([$kwarcan_id]);
    $kwarcan = $stmt->fetch();
    
    if (!$kwarcan) {
        echo json_encode(['success' => false, 'message' => 'Kwarcan tidak ditemukan']);
        exit();
    }
    
    // Get detailed penilaian with normalization
    $stmt = $pdo->prepare("
        SELECT p.*, k.nama_kriteria, k.bobot, k.jenis,
               (p.nilai * k.bobot) as nilai_terbobot
        FROM penilaian p
        JOIN kriteria k ON p.kriteria_id = k.id
        WHERE p.kwarcan_id = ?
        ORDER BY k.id
    ");
    $stmt->execute([$kwarcan_id]);
    $penilaian = $stmt->fetchAll();
    
    // Get MOORA result
    $stmt = $pdo->prepare("
        SELECT nilai_optimasi, ranking 
        FROM hasil_moora 
        WHERE kwarcan_id = ?
    ");
    $stmt->execute([$kwarcan_id]);
    $moora_result = $stmt->fetch();
    
    ob_start();
    ?>
    <div class="penilaian-detail">
        <div class="kwarcan-header">
            <h4><?= htmlspecialchars($kwarcan['nama_kwarcan']) ?></h4>
            <p><?= htmlspecialchars($kwarcan['daerah']) ?> - <?= $kwarcan['kode_kwarcan'] ?></p>
            <?php if ($moora_result): ?>
                <div class="moora-badge">
                    <span class="rank-badge rank-<?= $moora_result['ranking'] <= 3 ? $moora_result['ranking'] : 'other' ?>">
                        #<?= $moora_result['ranking'] ?>
                    </span>
                    <span class="score-text">Skor: <?= number_format($moora_result['nilai_optimasi'], 4) ?></span>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($penilaian)): ?>
            <div class="penilaian-table-container">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Kriteria</th>
                            <th>Bobot</th>
                            <th>Jenis</th>
                            <th>Nilai Asli</th>
                            <th>Normalisasi</th>
                            <th>Terbobot</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_terbobot = 0;
                        foreach ($penilaian as $index => $p): 
                            $total_terbobot += $p['nilai_terbobot'];
                        ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($p['nama_kriteria']) ?></strong>
                                </td>
                                <td>
                                    <span class="badge badge-info"><?= $p['bobot'] ?></span>
                                </td>
                                <td>
                                    <span class="badge <?= $p['jenis'] == 'benefit' ? 'badge-success' : 'badge-warning' ?>">
                                        <?= ucfirst($p['jenis']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="nilai-asli"><?= $p['nilai'] ?></span>
                                </td>
                                <td>
                                    <span class="nilai-normal"><?= number_format($p['nilai_normalisasi'], 4) ?></span>
                                </td>
                                <td>
                                    <span class="nilai-terbobot"><?= number_format($p['nilai_terbobot'], 4) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-summary">
                            <td colspan="6"><strong>Total Nilai Terbobot:</strong></td>
                            <td><strong><?= number_format($total_terbobot, 4) ?></strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <div class="penilaian-summary">
                <div class="summary-item">
                    <label>Total Kriteria:</label>
                    <span><?= count($penilaian) ?></span>
                </div>
                <div class="summary-item">
                    <label>Rata-rata Nilai:</label>
                    <span><?= number_format(array_sum(array_column($penilaian, 'nilai')) / count($penilaian), 2) ?></span>
                </div>
                <div class="summary-item">
                    <label>Total Terbobot:</label>
                    <span><?= number_format($total_terbobot, 4) ?></span>
                </div>
            </div>
        <?php else: ?>
            <div class="no-data">
                <i class="fas fa-exclamation-triangle"></i>
                <h4>Belum Ada Penilaian</h4>
                <p>Kwarcan ini belum memiliki data penilaian.</p>
            </div>
        <?php endif; ?>
    </div>
    
        <style>
        .penilaian-detail {
            max-width: 100%;
        }
        
        .kwarcan-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        
        .kwarcan-header h4 {
            margin: 0 0 0.5rem 0;
            font-size: 1.3rem;
        }
        
        .kwarcan-header p {
            margin: 0;
            opacity: 0.9;
        }
        
        .moora-badge {
            margin-top: 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .score-text {
            background: rgba(255,255,255,0.2);
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.9rem;
        }
        
        .penilaian-table-container {
            overflow-x: auto;
            margin-bottom: 1.5rem;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .table th,
        .table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        
        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        
        .table-striped tbody tr:nth-of-type(odd) {
            background-color: rgba(0,0,0,0.05);
        }
        
        .table-summary {
            background: #e9ecef !important;
            font-weight: 600;
        }
        
        .badge {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .badge-info { background: #17a2b8; color: white; }
        .badge-success { background: #28a745; color: white; }
        .badge-warning { background: #ffc107; color: #212529; }
        
        .nilai-asli {
            font-weight: 600;
            color: #495057;
        }
        
        .nilai-normal {
            color: #007bff;
            font-family: monospace;
        }
        
        .nilai-terbobot {
            color: #28a745;
            font-weight: 600;
            font-family: monospace;
        }
        
        .penilaian-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
        }
        
        .summary-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .summary-item label {
            font-weight: 600;
            color: #495057;
        }
        
        .summary-item span {
            font-weight: 700;
            color: #333;
        }
        
        .no-data {
            text-align: center;
            padding: 3rem 2rem;
            color: #6c757d;
        }
        
        .no-data i {
            font-size: 3rem;
            color: #dee2e6;
            margin-bottom: 1rem;
        }
        
        .no-data h4 {
            margin: 0 0 0.5rem 0;
            color: #495057;
        }
        
        .no-data p {
            margin: 0;
        }
        
        @media (max-width: 768px) {
            .moora-badge {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            
            .penilaian-summary {
                grid-template-columns: 1fr;
            }
            
            .table {
                font-size: 0.85rem;
            }
            
            .table th,
            .table td {
                padding: 0.5rem;
            }
        }
    </style>
    
    <?php
    $html = ob_get_clean();
    echo json_encode(['success' => true, 'html' => $html]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
