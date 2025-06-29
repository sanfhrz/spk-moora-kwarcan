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
    
    // Get MOORA result
    $stmt = $pdo->prepare("
        SELECT nilai_optimasi, ranking 
        FROM hasil_moora 
        WHERE kwarcan_id = ?
    ");
    $stmt->execute([$kwarcan_id]);
    $moora_result = $stmt->fetch();
    
    // Get penilaian summary
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_kriteria,
               AVG(p.nilai) as rata_nilai,
               SUM(p.nilai_terbobot) as total_terbobot
        FROM penilaian p
        WHERE p.kwarcan_id = ?
    ");
    $stmt->execute([$kwarcan_id]);
    $summary = $stmt->fetch();
    
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
    
    ob_start();
    ?>
    <div class="kwarcan-detail">
        <div class="detail-header">
            <div class="kwarcan-info">
                <h3><?= htmlspecialchars($kwarcan['nama_kwarcan']) ?></h3>
                <p class="kwarcan-meta">
                    <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($kwarcan['daerah']) ?></span>
                    <span><i class="fas fa-tag"></i> <?= $kwarcan['kode_kwarcan'] ?></span>
                </p>
                <?php if ($kwarcan['alamat']): ?>
                    <p class="kwarcan-address">
                        <i class="fas fa-home"></i> <?= htmlspecialchars($kwarcan['alamat']) ?>
                    </p>
                <?php endif; ?>
            </div>
            
            <?php if ($moora_result): ?>
                <div class="ranking-info">
                    <div class="rank-display">
                        <span class="rank-number">#<?= $moora_result['ranking'] ?></span>
                        <span class="rank-label">Ranking</span>
                    </div>
                    <div class="score-display">
                        <span class="score-number"><?= number_format($moora_result['nilai_optimasi'], 4) ?></span>
                        <span class="score-label">Skor MOORA</span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if ($summary && $summary['total_kriteria'] > 0): ?>
            <div class="summary-cards">
                <div class="summary-card">
                    <div class="summary-icon">
                        <i class="fas fa-list-check"></i>
                    </div>
                    <div class="summary-content">
                        <h4><?= $summary['total_kriteria'] ?></h4>
                        <p>Total Kriteria</p>
                    </div>
                </div>
                
                <div class="summary-card">
                    <div class="summary-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="summary-content">
                        <h4><?= number_format($summary['rata_nilai'], 2) ?></h4>
                        <p>Rata-rata Nilai</p>
                    </div>
                </div>
                
                <div class="summary-card">
                    <div class="summary-icon">
                        <i class="fas fa-calculator"></i>
                    </div>
                    <div class="summary-content">
                        <h4><?= number_format($summary['total_terbobot'], 4) ?></h4>
                        <p>Total Terbobot</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($penilaian)): ?>
            <div class="penilaian-section">
                <h4><i class="fas fa-chart-line"></i> Detail Penilaian</h4>
                <div class="penilaian-table-wrapper">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Kriteria</th>
                                <th>Bobot</th>
                                <th>Jenis</th>
                                <th>Nilai</th>
                                <th>Normalisasi</th>
                                <th>Terbobot</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($penilaian as $index => $p): ?>
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
                                        <span class="nilai-display"><?= $p['nilai'] ?></span>
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
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="no-penilaian">
                <i class="fas fa-exclamation-circle"></i>
                <h4>Belum Ada Penilaian</h4>
                <p>Kwarcan ini belum memiliki data penilaian.</p>
                <a href="penilaian.php?kwarcan=<?= $kwarcan_id ?>" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Tambah Penilaian
                </a>
            </div>
        <?php endif; ?>
        
        <div class="detail-actions">
            <a href="penilaian.php?kwarcan=<?= $kwarcan_id ?>" class="btn btn-primary">
                <i class="fas fa-edit"></i> Edit Penilaian
            </a>
            <a href="kwarcan.php?edit=<?= $kwarcan_id ?>" class="btn btn-secondary">
                <i class="fas fa-user-edit"></i> Edit Kwarcan
            </a>
            <button onclick="cetakDetail(<?= $kwarcan_id ?>)" class="btn btn-info">
                <i class="fas fa-print"></i> Cetak Detail
            </button>
        </div>
    </div>
    
    <style>
        .kwarcan-detail {
            max-width: 100%;
        }
        
        .detail-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
        }
        
        .kwarcan-info h3 {
            margin: 0 0 1rem 0;
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        .kwarcan-meta {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 0.5rem;
            flex-wrap: wrap;
        }
        
        .kwarcan-meta span {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            opacity: 0.9;
        }
        
        .kwarcan-address {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0;
            opacity: 0.8;
            font-size: 0.9rem;
        }
        
        .ranking-info {
            display: flex;
            gap: 1.5rem;
            align-items: center;
        }
        
        .rank-display, .score-display {
            text-align: center;
            background: rgba(255,255,255,0.15);
            padding: 1rem;
            border-radius: 8px;
            backdrop-filter: blur(10px);
        }
        
        .rank-number, .score-number {
            display: block;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        
        .rank-label, .score-label {
            font-size: 0.8rem;
            opacity: 0.8;
        }
        
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .summary-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 1rem;
            border-left: 4px solid #667eea;
        }
        
        .summary-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        
        .summary-content h4 {
            margin: 0 0 0.25rem 0;
            font-size: 1.5rem;
            font-weight: 700;
            color: #333;
        }
        
        .summary-content p {
            margin: 0;
            color: #666;
            font-size: 0.9rem;
        }
        
        .penilaian-section {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .penilaian-section h4 {
            margin: 0 0 1.5rem 0;
            color: #333;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .penilaian-table-wrapper {
            overflow-x: auto;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            background: white;
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
            position: sticky;
            top: 0;
        }
        
        .table-striped tbody tr:nth-of-type(odd) {
            background-color: rgba(102, 126, 234, 0.05);
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
        
        .nilai-display {
            font-weight: 600;
            color: #495057;
            font-size: 1rem;
        }
        
        .nilai-normal {
            color: #007bff;
            font-family: 'Courier New', monospace;
            font-weight: 500;
        }
        
        .nilai-terbobot {
            color: #28a745;
            font-weight: 600;
            font-family: 'Courier New', monospace;
        }
        
        .no-penilaian {
            text-align: center;
            padding: 3rem 2rem;
            background: #f8f9fa;
            border-radius: 12px;
            margin-bottom: 2rem;
        }
        
        .no-penilaian i {
            font-size: 3rem;
            color: #ffc107;
            margin-bottom: 1rem;
        }
        
        .no-penilaian h4 {
            margin: 0 0 0.5rem 0;
            color: #495057;
        }
        
        .no-penilaian p {
            margin: 0 0 1.5rem 0;
            color: #6c757d;
        }
        
        .detail-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            padding-top: 1rem;
            border-top: 1px solid #dee2e6;
        }
        
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5a6fd8;
            transform: translateY(-1px);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-1px);
        }
        
        .btn-info {
            background: #17a2b8;
            color: white;
        }
        
        .btn-info:hover {
            background: #138496;
            transform: translateY(-1px);
        }
        
        @media (max-width: 768px) {
            .detail-header {
                flex-direction: column;
                gap: 1.5rem;
            }
            
            .ranking-info {
                align-self: stretch;
                justify-content: space-around;
            }
            
            .kwarcan-meta {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .summary-cards {
                grid-template-columns: 1fr;
            }
            
            .table {
                font-size: 0.85rem;
            }
            
            .table th,
            .table td {
                padding: 0.5rem;
            }
            
            .detail-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
    
    <script>
        function cetakDetail(kwarcanId) {
            window.open(`cetak-detail.php?id=${kwarcanId}`, '_blank');
        }
    </script>
    
    <?php
    $html = ob_get_clean();
    echo json_encode(['success' => true, 'html' => $html]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>