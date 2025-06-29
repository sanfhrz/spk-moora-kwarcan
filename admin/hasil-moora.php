<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

require_once '../config/database.php';

$success = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'delete_results':
                try {
                    $pdo->exec("DELETE FROM hasil_moora");
                    $success = 'Semua hasil perhitungan berhasil dihapus!';
                } catch (Exception $e) {
                    $error = 'Error: ' . $e->getMessage();
                }
                break;
                
            case 'recalculate':
                header('Location: hitung-moora.php');
                exit();
                break;
        }
    }
}

// Get results data
try {
    // Get ranking results
    $stmt = $pdo->query("
        SELECT 
            h.id,
            h.kwarcan_id,
            h.nilai_optimasi,
            h.ranking,
            h.created_at,
            k.kode_kwarcan,
            k.nama_kwarcan,
            k.daerah
        FROM hasil_moora h
        JOIN kwarcan k ON h.kwarcan_id = k.id
        ORDER BY h.ranking ASC
    ");
    $ranking_results = $stmt->fetchAll();
    
    // Get criteria info
    $stmt = $pdo->query("SELECT * FROM kriteria ORDER BY id");
    $kriteria_list = $stmt->fetchAll();
    
    // Get assessment data for top performers
    $top_kwarcan_ids = array_slice(array_column($ranking_results, 'kwarcan_id'), 0, 5);
    if (!empty($top_kwarcan_ids)) {
        $placeholders = str_repeat('?,', count($top_kwarcan_ids) - 1) . '?';
        $stmt = $pdo->prepare("
            SELECT 
                p.kwarcan_id,
                p.kriteria_id,
                p.nilai,
                kr.nama_kriteria,
                kr.bobot,
                kr.jenis
            FROM penilaian p
            JOIN kriteria kr ON p.kriteria_id = kr.id
            WHERE p.kwarcan_id IN ($placeholders)
            ORDER BY p.kwarcan_id, kr.id
        ");
        $stmt->execute($top_kwarcan_ids);
        $assessment_data = $stmt->fetchAll();
    } else {
        $assessment_data = [];
    }
    
    // Get statistics
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM hasil_moora");
    $total_results = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT created_at FROM hasil_moora ORDER BY created_at DESC LIMIT 1");
    $last_calculation = $stmt->fetch();
    
} catch (Exception $e) {
    $ranking_results = [];
    $kriteria_list = [];
    $assessment_data = [];
    $total_results = 0;
    $last_calculation = null;
    $error = 'Error mengambil data: ' . $e->getMessage();
}

// Organize assessment data by kwarcan
$assessment_by_kwarcan = [];
foreach ($assessment_data as $row) {
    $assessment_by_kwarcan[$row['kwarcan_id']][$row['kriteria_id']] = $row;
}

$page_title = "Hasil MOORA - SPK Kwarcan";
?>

<?php include '../includes/header.php'; ?>

<div class="dashboard-container">
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="content">
        <!-- Header -->
        <div class="content-header">
            <div class="header-title">
                                <h1><i class="fas fa-trophy"></i> Hasil Perhitungan MOORA</h1>
                <p>Hasil perangkingan Kwarcan berdasarkan metode Multi-Objective Optimization by Ratio Analysis</p>
            </div>
            <div class="header-actions">
                <a href="hitung-moora.php" class="btn btn-primary">
                    <i class="fas fa-calculator"></i> Hitung Ulang
                </a>
                <button class="btn btn-success" onclick="exportResults()">
                    <i class="fas fa-download"></i> Export
                </button>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if (empty($ranking_results)): ?>
            <!-- No Results State -->
            <div class="no-results-container">
                <div class="no-results-card">
                    <div class="no-results-icon">
                        <i class="fas fa-calculator"></i>
                    </div>
                    <h2>Belum Ada Hasil Perhitungan</h2>
                    <p>Silakan jalankan perhitungan MOORA terlebih dahulu untuk melihat hasil perangkingan Kwarcan.</p>
                    <div class="no-results-actions">
                        <a href="hitung-moora.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-play"></i> Mulai Perhitungan
                        </a>
                        <a href="penilaian.php" class="btn btn-secondary btn-lg">
                            <i class="fas fa-star"></i> Input Penilaian
                        </a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Results Summary -->
            <div class="results-summary">
                <div class="summary-card">
                    <div class="summary-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="summary-content">
                        <h3><?= count($ranking_results) ?></h3>
                        <p>Kwarcan Dirangking</p>
                    </div>
                </div>

                <div class="summary-card">
                    <div class="summary-icon">
                        <i class="fas fa-list-check"></i>
                    </div>
                    <div class="summary-content">
                        <h3><?= count($kriteria_list) ?></h3>
                        <p>Kriteria Penilaian</p>
                    </div>
                </div>

                <div class="summary-card">
                    <div class="summary-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="summary-content">
                        <h3><?= $last_calculation ? date('d/m/Y', strtotime($last_calculation['created_at'])) : '-' ?></h3>
                        <p>Terakhir Dihitung</p>
                    </div>
                </div>

                <div class="summary-card">
                    <div class="summary-icon">
                        <i class="fas fa-crown"></i>
                    </div>
                    <div class="summary-content">
                        <h3><?= !empty($ranking_results) ? htmlspecialchars($ranking_results[0]['kode_kwarcan']) : '-' ?></h3>
                        <p>Kwarcan Terbaik</p>
                    </div>
                </div>
            </div>

            <!-- Top 3 Winners -->
            <div class="winners-section">
                <div class="section-header">
                    <h2><i class="fas fa-medal"></i> Top 3 Kwarcan Terbaik</h2>
                </div>
                <div class="winners-podium">
                    <?php foreach (array_slice($ranking_results, 0, 3) as $index => $winner): ?>
                        <div class="winner-card position-<?= $index + 1 ?>">
                            <div class="winner-position">
                                <div class="position-number"><?= $winner['ranking'] ?></div>
                                <div class="position-icon">
                                    <?php if ($winner['ranking'] == 1): ?>
                                        <i class="fas fa-crown"></i>
                                    <?php elseif ($winner['ranking'] == 2): ?>
                                        <i class="fas fa-medal"></i>
                                    <?php else: ?>
                                        <i class="fas fa-award"></i>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="winner-info">
                                <h3><?= htmlspecialchars($winner['nama_kwarcan']) ?></h3>
                                <p><?= htmlspecialchars($winner['daerah']) ?></p>
                                <div class="winner-score">
                                    <span>Nilai: <?= number_format($winner['nilai_optimasi'], 4) ?></span>
                                </div>
                            </div>
                            <div class="winner-badge">
                                <?php if ($winner['ranking'] == 1): ?>
                                    <span class="badge badge-gold">Juara 1</span>
                                <?php elseif ($winner['ranking'] == 2): ?>
                                    <span class="badge badge-silver">Juara 2</span>
                                <?php else: ?>
                                    <span class="badge badge-bronze">Juara 3</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Complete Ranking Table -->
            <div class="ranking-table-section">
                <div class="section-header">
                    <h2><i class="fas fa-list-ol"></i> Perangkingan Lengkap</h2>
                    <div class="section-actions">
                        <button class="btn btn-info" onclick="toggleDetails()">
                            <i class="fas fa-eye"></i> <span id="detailsToggleText">Tampilkan Detail</span>
                        </button>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Yakin ingin menghapus semua hasil?')">
                            <input type="hidden" name="action" value="delete_results">
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-trash"></i> Hapus Hasil
                            </button>
                        </form>
                    </div>
                </div>

                <div class="table-container">
                    <table class="ranking-table" id="rankingTable">
                        <thead>
                            <tr>
                                <th>Ranking</th>
                                <th>Kode</th>
                                <th>Nama Kwarcan</th>
                                <th>Daerah</th>
                                <th>Nilai Optimasi</th>
                                <th>Status</th>
                                <th class="details-column" style="display: none;">Detail Penilaian</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ranking_results as $result): ?>
                                <tr class="ranking-row" data-ranking="<?= $result['ranking'] ?>">
                                    <td class="ranking-cell">
                                        <div class="ranking-badge rank-<?= $result['ranking'] ?>">
                                            <?= $result['ranking'] ?>
                                            <?php if ($result['ranking'] <= 3): ?>
                                                <i class="fas fa-<?= $result['ranking'] == 1 ? 'crown' : ($result['ranking'] == 2 ? 'medal' : 'award') ?>"></i>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="kode-cell">
                                        <strong><?= htmlspecialchars($result['kode_kwarcan']) ?></strong>
                                    </td>
                                    <td class="nama-cell">
                                        <?= htmlspecialchars($result['nama_kwarcan']) ?>
                                    </td>
                                    <td class="daerah-cell">
                                        <?= htmlspecialchars($result['daerah']) ?>
                                    </td>
                                    <td class="nilai-cell">
                                        <span class="nilai-badge"><?= number_format($result['nilai_optimasi'], 4) ?></span>
                                    </td>
                                    <td class="status-cell">
                                        <?php if ($result['ranking'] == 1): ?>
                                            <span class="status-badge status-terbaik">Terbaik</span>
                                        <?php elseif ($result['ranking'] <= 3): ?>
                                            <span class="status-badge status-top">Top 3</span>
                                        <?php elseif ($result['ranking'] <= 5): ?>
                                            <span class="status-badge status-baik">Top 5</span>
                                        <?php else: ?>
                                            <span class="status-badge status-standar">Standar</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="details-column" style="display: none;">
                                        <?php if (isset($assessment_by_kwarcan[$result['kwarcan_id']])): ?>
                                            <div class="assessment-details">
                                                <?php foreach ($assessment_by_kwarcan[$result['kwarcan_id']] as $assessment): ?>
                                                    <div class="assessment-item">
                                                        <span class="kriteria-name"><?= htmlspecialchars($assessment['nama_kriteria']) ?>:</span>
                                                        <span class="nilai-value"><?= $assessment['nilai'] ?></span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="no-data">Data tidak tersedia</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Performance Chart -->
            <div class="chart-section">
                <div class="section-header">
                    <h2><i class="fas fa-chart-bar"></i> Grafik Perbandingan</h2>
                    <div class="chart-controls">
                        <select id="chartType" onchange="updateChart()">
                            <option value="bar">Bar Chart</option>
                            <option value="line">Line Chart</option>
                            <option value="radar">Radar Chart</option>
                        </select>
                        <select id="chartLimit" onchange="updateChart()">
                            <option value="5">Top 5</option>
                            <option value="10">Top 10</option>
                            <option value="0">Semua</option>
                        </select>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="performanceChart"></canvas>
                </div>
            </div>

            <!-- Criteria Analysis -->
            <div class="criteria-analysis-section">
                <div class="section-header">
                    <h2><i class="fas fa-analytics"></i> Analisis per Kriteria</h2>
                </div>
                <div class="criteria-grid">
                    <?php foreach ($kriteria_list as $kriteria): ?>
                        <div class="criteria-card">
                            <div class="criteria-header">
                                <h3><?= htmlspecialchars($kriteria['nama_kriteria']) ?></h3>
                                <div class="criteria-meta">
                                    <span class="criteria-type <?= $kriteria['jenis'] ?>"><?= ucfirst($kriteria['jenis']) ?></span>
                                    <span class="criteria-weight">Bobot: <?= $kriteria['bobot'] ?></span>
                                </div>
                            </div>
                            <div class="criteria-stats">
                                <?php
                                // Calculate stats for this criteria
                                $kriteria_values = [];
                                foreach ($assessment_data as $assessment) {
                                    if ($assessment['kriteria_id'] == $kriteria['id']) {
                                        $kriteria_values[] = $assessment['nilai'];
                                    }
                                }
                                
                                if (!empty($kriteria_values)) {
                                    $avg = array_sum($kriteria_values) / count($kriteria_values);
                                    $max = max($kriteria_values);
                                    $min = min($kriteria_values);
                                } else {
                                    $avg = $max = $min = 0;
                                }
                                ?>
                                <div class="stat-item">
                                    <span class="stat-label">Rata-rata:</span>
                                    <span class="stat-value"><?= number_format($avg, 2) ?></span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-label">Tertinggi:</span>
                                    <span class="stat-value"><?= number_format($max, 2) ?></span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-label">Terendah:</span>
                                    <span class="stat-value"><?= number_format($min, 2) ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Export Options -->
            <div class="export-section">
                <div class="section-header">
                    <h2><i class="fas fa-download"></i> Export Hasil</h2>
                </div>
                <div class="export-options">
                    <button class="export-btn" onclick="exportToPDF()">
                        <i class="fas fa-file-pdf"></i>
                        <span>Export PDF</span>
                        <small>Laporan lengkap</small>
                    </button>
                    <button class="export-btn" onclick="exportToExcel()">
                        <i class="fas fa-file-excel"></i>
                        <span>Export Excel</span>
                        <small>Data spreadsheet</small>
                    </button>
                    <button class="export-btn" onclick="exportToCSV()">
                        <i class="fas fa-file-csv"></i>
                        <span>Export CSV</span>
                        <small>Data mentah</small>
                    </button>
                    <button class="export-btn" onclick="printResults()">
                        <i class="fas fa-print"></i>
                        <span>Print</span>
                                                <small>Cetak langsung</small>
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    .dashboard-container {
        display: flex;
        min-height: 100vh;
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    }

    .content {
        flex: 1;
        padding: 30px;
        margin-left: 280px;
        transition: margin-left 0.3s ease;
    }

    .content-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 30px;
        flex-wrap: wrap;
        gap: 20px;
    }

    .header-title h1 {
        color: #333;
        font-size: 2rem;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .header-title p {
        color: #666;
        font-size: 1rem;
        line-height: 1.5;
    }

    .header-actions {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
    }

    /* No Results State */
    .no-results-container {
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 60vh;
    }

    .no-results-card {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(20px);
        border-radius: 25px;
        padding: 60px 40px;
        text-align: center;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        max-width: 500px;
    }

    .no-results-icon {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 30px;
    }

    .no-results-icon i {
        font-size: 3rem;
        color: #667eea;
    }

    .no-results-card h2 {
        color: #333;
        font-size: 1.8rem;
        margin-bottom: 15px;
    }

    .no-results-card p {
        color: #666;
        font-size: 1.1rem;
        line-height: 1.6;
        margin-bottom: 30px;
    }

    .no-results-actions {
        display: flex;
        gap: 15px;
        justify-content: center;
        flex-wrap: wrap;
    }

    /* Results Summary */
    .results-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 40px;
    }

    .summary-card {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(20px);
        border-radius: 20px;
        padding: 25px;
        display: flex;
        align-items: center;
        gap: 20px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
    }

    .summary-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
    }

    .summary-icon {
        width: 60px;
        height: 60px;
        border-radius: 15px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.5rem;
    }

    .summary-content h3 {
        color: #333;
        font-size: 1.8rem;
        font-weight: 700;
        margin-bottom: 5px;
    }

    .summary-content p {
        color: #666;
        font-size: 0.9rem;
        margin: 0;
    }

    /* Winners Section */
    .winners-section {
        margin-bottom: 40px;
    }

    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        flex-wrap: wrap;
        gap: 15px;
    }

    .section-header h2 {
        color: #333;
        font-size: 1.5rem;
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 0;
    }

    .section-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .winners-podium {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 25px;
    }

    .winner-card {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(20px);
        border-radius: 20px;
        padding: 30px;
        text-align: center;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .winner-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 5px;
    }

    .winner-card.position-1::before {
        background: linear-gradient(135deg, #ffd700, #ffed4e);
    }

    .winner-card.position-2::before {
        background: linear-gradient(135deg, #c0c0c0, #e5e5e5);
    }

    .winner-card.position-3::before {
        background: linear-gradient(135deg, #cd7f32, #daa520);
    }

    .winner-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
    }

    .winner-position {
        margin-bottom: 20px;
    }

    .position-number {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        font-weight: 700;
        margin: 0 auto 10px;
        color: white;
    }

    .position-1 .position-number {
        background: linear-gradient(135deg, #ffd700, #ffed4e);
        color: #333;
    }

    .position-2 .position-number {
        background: linear-gradient(135deg, #c0c0c0, #e5e5e5);
        color: #333;
    }

    .position-3 .position-number {
        background: linear-gradient(135deg, #cd7f32, #daa520);
    }

    .position-icon i {
        font-size: 1.5rem;
        color: #ffd700;
    }

    .winner-info h3 {
        color: #333;
        font-size: 1.3rem;
        margin-bottom: 8px;
    }

    .winner-info p {
        color: #666;
        font-size: 1rem;
        margin-bottom: 15px;
    }

    .winner-score span {
        background: rgba(102, 126, 234, 0.1);
        color: #667eea;
        padding: 8px 16px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.9rem;
    }

    .winner-badge {
        margin-top: 20px;
    }

    .badge {
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .badge-gold {
        background: linear-gradient(135deg, #ffd700, #ffed4e);
        color: #333;
    }

    .badge-silver {
        background: linear-gradient(135deg, #c0c0c0, #e5e5e5);
        color: #333;
    }

    .badge-bronze {
        background: linear-gradient(135deg, #cd7f32, #daa520);
        color: white;
    }

    /* Ranking Table */
    .ranking-table-section {
        margin-bottom: 40px;
    }

    .table-container {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(20px);
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
    }

    .ranking-table {
        width: 100%;
        border-collapse: collapse;
    }

    .ranking-table th {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        padding: 20px 15px;
        text-align: left;
        font-weight: 600;
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .ranking-table td {
        padding: 20px 15px;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        vertical-align: middle;
    }

    .ranking-row:hover {
        background: rgba(102, 126, 234, 0.05);
    }

    .ranking-badge {
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: 700;
        font-size: 1.1rem;
    }

    .ranking-badge.rank-1 {
        color: #ffd700;
    }

    .ranking-badge.rank-2 {
        color: #c0c0c0;
    }

    .ranking-badge.rank-3 {
        color: #cd7f32;
    }

    .kode-cell strong {
        color: #333;
        font-size: 1rem;
    }

    .nama-cell {
        font-weight: 600;
        color: #333;
    }

    .daerah-cell {
        color: #666;
        font-size: 0.9rem;
    }

    .nilai-badge {
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
        color: #667eea;
        padding: 6px 12px;
        border-radius: 15px;
        font-weight: 600;
        font-size: 0.9rem;
    }

    .status-badge {
        padding: 6px 12px;
        border-radius: 15px;
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .status-terbaik {
        background: linear-gradient(135deg, #ffd700, #ffed4e);
        color: #333;
    }

    .status-top {
        background: linear-gradient(135deg, #28a745, #20c997);
        color: white;
    }

    .status-baik {
        background: linear-gradient(135deg, #17a2b8, #6f42c1);
        color: white;
    }

    .status-standar {
        background: linear-gradient(135deg, #6c757d, #495057);
        color: white;
    }

    .assessment-details {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    .assessment-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 4px 8px;
        background: rgba(102, 126, 234, 0.05);
        border-radius: 8px;
        font-size: 0.8rem;
    }

    .kriteria-name {
        color: #666;
        font-weight: 500;
    }

    .nilai-value {
        color: #333;
        font-weight: 600;
    }

    /* Chart Section */
    .chart-section {
        margin-bottom: 40px;
    }

    .chart-controls {
        display: flex;
        gap: 10px;
    }

    .chart-controls select {
        padding: 8px 12px;
        border: 1px solid rgba(0, 0, 0, 0.2);
        border-radius: 8px;
        background: white;
        font-size: 0.9rem;
    }

    .chart-container {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(20px);
        border-radius: 20px;
        padding: 30px;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        height: 400px;
    }

    /* Criteria Analysis */
    .criteria-analysis-section {
        margin-bottom: 40px;
    }

    .criteria-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
    }

    .criteria-card {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(20px);
        border-radius: 20px;
        padding: 25px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
    }

    .criteria-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
    }

    .criteria-header {
        margin-bottom: 20px;
    }

    .criteria-header h3 {
        color: #333;
        font-size: 1.2rem;
        margin-bottom: 10px;
    }

    .criteria-meta {
                display: flex;
        gap: 15px;
        flex-wrap: wrap;
    }

    .criteria-type {
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .criteria-type.benefit {
        background: rgba(40, 167, 69, 0.1);
        color: #28a745;
    }

    .criteria-type.cost {
        background: rgba(220, 53, 69, 0.1);
        color: #dc3545;
    }

    .criteria-weight {
        color: #667eea;
        font-weight: 600;
        font-size: 0.9rem;
    }

    .criteria-stats {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .stat-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 0;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    }

    .stat-label {
        color: #666;
        font-size: 0.9rem;
    }

    .stat-value {
        color: #333;
        font-weight: 600;
        font-size: 0.9rem;
    }

    /* Export Section */
    .export-section {
        margin-bottom: 40px;
    }

    .export-options {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
    }

    .export-btn {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(20px);
        border: 2px solid rgba(102, 126, 234, 0.2);
        border-radius: 20px;
        padding: 25px 20px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
    }

    .export-btn:hover {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        transform: translateY(-5px);
        box-shadow: 0 15px 35px rgba(102, 126, 234, 0.3);
    }

    .export-btn i {
        font-size: 2rem;
        color: #667eea;
        transition: color 0.3s ease;
    }

    .export-btn:hover i {
        color: white;
    }

    .export-btn span {
        font-weight: 600;
        font-size: 1rem;
        color: #333;
        transition: color 0.3s ease;
    }

    .export-btn:hover span {
        color: white;
    }

    .export-btn small {
        color: #666;
        font-size: 0.8rem;
        transition: color 0.3s ease;
    }

    .export-btn:hover small {
        color: rgba(255, 255, 255, 0.8);
    }

    /* Buttons */
    .btn {
        padding: 10px 20px;
        border: none;
        border-radius: 12px;
        font-size: 0.9rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
    }

    .btn-lg {
        padding: 15px 30px;
        font-size: 1.1rem;
    }

    .btn-primary {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
    }

    .btn-success {
        background: linear-gradient(135deg, #28a745, #20c997);
        color: white;
        box-shadow: 0 8px 25px rgba(40, 167, 69, 0.3);
    }

    .btn-info {
        background: linear-gradient(135deg, #17a2b8, #6f42c1);
        color: white;
        box-shadow: 0 8px 25px rgba(23, 162, 184, 0.3);
    }

    .btn-danger {
        background: linear-gradient(135deg, #dc3545, #c82333);
        color: white;
        box-shadow: 0 8px 25px rgba(220, 53, 69, 0.3);
    }

    .btn-secondary {
        background: linear-gradient(135deg, #6c757d, #495057);
        color: white;
        box-shadow: 0 8px 25px rgba(108, 117, 125, 0.3);
    }

    .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 35px rgba(0, 0, 0, 0.2);
    }

    .alert {
        padding: 15px 20px;
        border-radius: 12px;
        margin-bottom: 25px;
        display: flex;
        align-items: flex-start;
        gap: 12px;
    }

    .alert-success {
        background: rgba(40, 167, 69, 0.1);
        border: 1px solid rgba(40, 167, 69, 0.2);
        color: #155724;
    }

    .alert-danger {
        background: rgba(220, 53, 69, 0.1);
        border: 1px solid rgba(220, 53, 69, 0.2);
        color: #721c24;
    }

    .alert i {
        font-size: 1.2rem;
        margin-top: 2px;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .content {
            margin-left: 0;
            padding: 20px 15px;
        }

        .content-header {
            flex-direction: column;
            align-items: flex-start;
        }

        .header-actions {
            width: 100%;
            justify-content: flex-start;
        }

        .results-summary {
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }

        .winners-podium {
            grid-template-columns: 1fr;
        }

        .section-header {
            flex-direction: column;
            align-items: flex-start;
        }

        .section-actions {
            width: 100%;
            justify-content: flex-start;
        }

        .ranking-table {
            font-size: 0.8rem;
        }

        .ranking-table th,
        .ranking-table td {
            padding: 12px 8px;
        }

        .criteria-grid {
            grid-template-columns: 1fr;
        }

        .export-options {
            grid-template-columns: repeat(2, 1fr);
        }

        .chart-controls {
            flex-direction: column;
        }
    }

    @media (max-width: 480px) {
        .export-options {
            grid-template-columns: 1fr;
        }

        .no-results-actions {
            flex-direction: column;
        }
    }
</style>

<script>
    let performanceChart = null;
    const rankingData = <?= json_encode($ranking_results) ?>;
    const criteriaData = <?= json_encode($kriteria_list) ?>;

    // Toggle details column
    function toggleDetails() {
        const detailsColumns = document.querySelectorAll('.details-column');
        const toggleText = document.getElementById('detailsToggleText');
        const isVisible = detailsColumns[0].style.display !== 'none';
        
        detailsColumns.forEach(col => {
            col.style.display = isVisible ? 'none' : 'table-cell';
        });
        
        toggleText.textContent = isVisible ? 'Tampilkan Detail' : 'Sembunyikan Detail';
    }

    // Initialize chart
    function initChart() {
        const ctx = document.getElementById('performanceChart');
        if (!ctx || rankingData.length === 0) return;

        updateChart();
    }

    // Update chart based on controls
    function updateChart() {
        const chartType = document.getElementById('chartType').value;
        const chartLimit = parseInt(document.getElementById('chartLimit').value);
        
        let data = rankingData;
        if (chartLimit > 0) {
            data = data.slice(0, chartLimit);
        }

        const ctx = document.getElementById('performanceChart');
        
        if (performanceChart) {
            performanceChart.destroy();
        }

        const chartData = {
            labels: data.map(item => item.kode_kwarcan),
            datasets: [{
                label: 'Nilai Optimasi',
                data: data.map(item => parseFloat(item.nilai_optimasi)),
                backgroundColor: data.map((item, index) => {
                    if (item.ranking === 1) return 'rgba(255, 215, 0, 0.8)';
                    if (item.ranking === 2) return 'rgba(192, 192, 192, 0.8)';
                    if (item.ranking === 3) return 'rgba(205, 127, 50, 0.8)';
                    return `rgba(102, 126, 234, ${0.8 - (index * 0.1)})`;
                }),
                borderColor: data.map((item, index) => {
                    if (item.ranking === 1) return '#ffd700';
                    if (item.ranking === 2) return '#c0c0c0';
                    if (item.ranking === 3) return '#cd7f32';
                    return '#667eea';
                }),
                borderWidth: 2,
                borderRadius: chartType === 'bar' ? 8 : 0,
                tension: chartType === 'line' ? 0.4 : 0
            }]
        };

        const config = {
            type: chartType === 'radar' ? 'radar' : (chartType === 'line' ? 'line' : 'bar'),
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: 'rgba(102, 126, 234, 0.5)',
                        borderWidth: 1,
                        cornerRadius: 8,
                        displayColors: false,
                        callbacks: {
                            title: function(context) {
                                const index = context[0].dataIndex;
                                return data[index].nama_kwarcan;
                            },
                            label: function(context) {
                                const index = context.dataIndex;
                                return [
                                    `Ranking: ${data[index].ranking}`,
                                    `Nilai: ${context.parsed.y.toFixed(4)}`,
                                    `Daerah: ${data[index].daerah}`
                                ];
                            }
                        }
                    }
                },
                scales: chartType === 'radar' ? {
                    r: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        },
                        pointLabels: {
                            font: {
                                size: 12
                            }
                        }
                    }
                } : {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)',
                            drawBorder: false
                        },
                        ticks: {
                            color: '#666',
                            font: {
                                size: 12
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#666',
                            font: {
                                size: 12
                            },
                            maxRotation: 45
                        }
                    }
                },
                animation: {
                    duration: 1000,
                    easing: 'easeOutQuart'
                }
            }
        };

        performanceChart = new Chart(ctx, config);
    }

    // Export functions
    function exportResults() {
        exportToCSV();
    }

    function exportToCSV() {
        let csvContent = 'Ranking,Kode,Nama Kwarcan,Daerah,Nilai Optimasi\n';
        
        rankingData.forEach(item => {
            csvContent += `${item.ranking},"${item.kode_kwarcan}","${item.nama_kwarcan}","${item.daerah}",${item.nilai_optimasi}\n`;
        });
        
        downloadFile(csvContent, `ranking_moora_${new Date().toISOString().split('T')[0]}.csv`, 'text/csv');
    }

    function exportToExcel() {
        // Create HTML table for Excel
        let htmlContent = `
            <table border="1">
                <tr>
                    <th>Ranking</th>
                    <th>Kode</th>
                    <th>Nama Kwarcan</th>
                    <th>Daerah</th>
                    <th>Nilai Optimasi</th>
                </tr>
        `;
        
        rankingData.forEach(item => {
            htmlContent += `
                <tr>
                    <td>${item.ranking}</td>
                    <td>${item.kode_kwarcan}</td>
                    <td>${item.nama_kwarcan}</td>
                    <td>${item.daerah}</td>
                    <td>${item.nilai_optimasi}</td>
                </tr>
            `;
        });
        
        htmlContent += '</table>';
        
        downloadFile(htmlContent, `ranking_moora_${new Date().toISOString().split('T')[0]}.xls`, 'application/vnd.ms-excel');
    }

    function exportToPDF() {
        window.open('laporan.php?type=pdf', '_blank');
    }

    function printResults() {
        const printContent = document.querySelector('.ranking-table-section').innerHTML;
        const printWindow = window.open('', '_blank');
        
        printWindow.document.write(`
            <html>
                <head>
                    <title>Hasil Ranking MOORA</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                                                table { width: 100%; border-collapse: collapse; }
                        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        th { background-color: #f2f2f2; }
                        .ranking-badge { font-weight: bold; }
                        .nilai-badge { background: #e9ecef; padding: 4px 8px; border-radius: 4px; }
                        .status-badge { padding: 4px 8px; border-radius: 4px; font-size: 0.8em; }
                        .status-terbaik { background: #ffd700; }
                        .status-top { background: #28a745; color: white; }
                        .status-baik { background: #17a2b8; color: white; }
                        .status-standar { background: #6c757d; color: white; }
                        h1 { text-align: center; color: #333; }
                        .print-date { text-align: center; margin-bottom: 20px; color: #666; }
                    </style>
                </head>
                <body>
                    <h1>Hasil Perangkingan MOORA</h1>
                    <div class="print-date">Dicetak pada: ${new Date().toLocaleDateString('id-ID')}</div>
                    ${printContent}
                </body>
            </html>
        `);
        
        printWindow.document.close();
        printWindow.print();
    }

    function downloadFile(content, filename, mimeType) {
        const blob = new Blob([content], { type: mimeType });
        const link = document.createElement('a');
        
        if (link.download !== undefined) {
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', filename);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    }

    // Initialize everything
    document.addEventListener('DOMContentLoaded', function() {
        initChart();
        
        // Add loading states to export buttons
        document.querySelectorAll('.export-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const originalContent = this.innerHTML;
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Processing...</span><small>Please wait</small>';
                this.style.pointerEvents = 'none';
                
                setTimeout(() => {
                    this.innerHTML = originalContent;
                    this.style.pointerEvents = 'auto';
                }, 2000);
            });
        });

        // Add search functionality
        addSearchFunctionality();
        
        // Add sorting functionality
        addSortingFunctionality();
    });

    // Search functionality
    function addSearchFunctionality() {
        const searchInput = document.createElement('input');
        searchInput.type = 'text';
        searchInput.placeholder = 'Cari kwarcan...';
        searchInput.className = 'search-input';
        searchInput.style.cssText = `
            padding: 10px 15px;
            border: 1px solid rgba(0,0,0,0.2);
            border-radius: 10px;
            font-size: 0.9rem;
            width: 250px;
            margin-right: 10px;
        `;

        const tableSection = document.querySelector('.ranking-table-section .section-header');
        if (tableSection) {
            const actionsDiv = tableSection.querySelector('.section-actions');
            if (actionsDiv) {
                actionsDiv.insertBefore(searchInput, actionsDiv.firstChild);
            }
        }

        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('.ranking-row');
            
            rows.forEach(row => {
                const nama = row.querySelector('.nama-cell').textContent.toLowerCase();
                const daerah = row.querySelector('.daerah-cell').textContent.toLowerCase();
                const kode = row.querySelector('.kode-cell').textContent.toLowerCase();
                
                if (nama.includes(searchTerm) || daerah.includes(searchTerm) || kode.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }

    // Sorting functionality
    function addSortingFunctionality() {
        const headers = document.querySelectorAll('.ranking-table th');
        headers.forEach((header, index) => {
            if (index < 5) { // Only for sortable columns
                header.style.cursor = 'pointer';
                header.style.userSelect = 'none';
                header.addEventListener('click', () => sortTable(index));
                
                // Add sort indicator
                const sortIcon = document.createElement('i');
                sortIcon.className = 'fas fa-sort sort-icon';
                sortIcon.style.marginLeft = '8px';
                sortIcon.style.opacity = '0.5';
                header.appendChild(sortIcon);
            }
        });
    }

    function sortTable(columnIndex) {
        const table = document.querySelector('.ranking-table');
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        
        // Determine sort direction
        const header = table.querySelectorAll('th')[columnIndex];
        const sortIcon = header.querySelector('.sort-icon');
        const isAscending = !header.classList.contains('sort-asc');
        
        // Reset all sort indicators
        table.querySelectorAll('th').forEach(th => {
            th.classList.remove('sort-asc', 'sort-desc');
            const icon = th.querySelector('.sort-icon');
            if (icon) {
                icon.className = 'fas fa-sort sort-icon';
                icon.style.opacity = '0.5';
            }
        });
        
        // Set current sort indicator
        header.classList.add(isAscending ? 'sort-asc' : 'sort-desc');
        sortIcon.className = `fas fa-sort-${isAscending ? 'up' : 'down'} sort-icon`;
        sortIcon.style.opacity = '1';
        
        // Sort rows
        rows.sort((a, b) => {
            let aVal = a.cells[columnIndex].textContent.trim();
            let bVal = b.cells[columnIndex].textContent.trim();
            
            // Handle numeric columns
            if (columnIndex === 0 || columnIndex === 4) { // Ranking or Nilai
                aVal = parseFloat(aVal.replace(/[^\d.-]/g, ''));
                bVal = parseFloat(bVal.replace(/[^\d.-]/g, ''));
            }
            
            if (aVal < bVal) return isAscending ? -1 : 1;
            if (aVal > bVal) return isAscending ? 1 : -1;
            return 0;
        });
        
        // Reorder rows in DOM
        rows.forEach(row => tbody.appendChild(row));
    }

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey) {
            switch(e.key) {
                case 'p':
                    e.preventDefault();
                    printResults();
                    break;
                case 's':
                    e.preventDefault();
                    exportToCSV();
                    break;
                case 'e':
                    e.preventDefault();
                    exportToExcel();
                    break;
                case 'r':
                    e.preventDefault();
                    window.location.href = 'hitung-moora.php';
                    break;
            }
        }
        
        // ESC to close details
        if (e.key === 'Escape') {
            const detailsColumns = document.querySelectorAll('.details-column');
            if (detailsColumns[0].style.display !== 'none') {
                toggleDetails();
            }
        }
    });

    // Auto-refresh functionality
    function setupAutoRefresh() {
        const refreshInterval = 300000; // 5 minutes
        
        setInterval(() => {
            fetch('ajax/check-results-update.php')
                .then(response => response.json())
                .then(data => {
                    if (data.updated) {
                        showUpdateNotification();
                    }
                })
                .catch(error => console.error('Error checking updates:', error));
        }, refreshInterval);
    }

    function showUpdateNotification() {
        const notification = document.createElement('div');
        notification.className = 'update-notification';
        notification.innerHTML = `
            <div class="notification-content">
                <i class="fas fa-info-circle"></i>
                <span>Data hasil telah diperbarui</span>
                <button onclick="location.reload()" class="btn btn-sm btn-primary">Refresh</button>
                <button onclick="this.parentElement.parentElement.remove()" class="btn btn-sm btn-secondary">Tutup</button>
            </div>
        `;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 15px 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            animation: slideInRight 0.3s ease;
        `;
        
        document.body.appendChild(notification);
        
        // Auto remove after 10 seconds
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 10000);
    }

    // Initialize auto-refresh
    setupAutoRefresh();

    // Performance monitoring
    function trackPerformance() {
        const startTime = performance.now();
        
        window.addEventListener('load', () => {
            const loadTime = performance.now() - startTime;
            console.log(`Page loaded in ${loadTime.toFixed(2)}ms`);
            
            // Track chart rendering time
            if (performanceChart) {
                console.log('Chart rendered successfully');
            }
        });
    }

    trackPerformance();
</script>

<?php include '../includes/footer.php'; ?>