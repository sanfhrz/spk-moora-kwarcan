<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

require_once '../config/database.php';

// Handle PDF generation
if (isset($_GET['type']) && $_GET['type'] === 'pdf') {
    require_once '../vendor/autoload.php'; // Assuming you have TCPDF or similar
    generatePDFReport();
    exit();
}

$success = '';
$error = '';

// Get filter parameters
$filter_periode = $_GET['periode'] ?? 'all';
$filter_ranking = $_GET['ranking'] ?? 'all';
$export_type = $_GET['export'] ?? '';

try {
    // Get ranking results with filters
    $where_conditions = [];
    $params = [];
    
    if ($filter_periode !== 'all') {
        switch ($filter_periode) {
            case 'today':
                $where_conditions[] = "DATE(h.created_at) = CURDATE()";
                break;
            case 'week':
                $where_conditions[] = "h.created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
                break;
            case 'month':
                $where_conditions[] = "h.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
                break;
        }
    }
    
    if ($filter_ranking !== 'all') {
        switch ($filter_ranking) {
            case 'top3':
                $where_conditions[] = "h.ranking <= 3";
                break;
            case 'top5':
                $where_conditions[] = "h.ranking <= 5";
                break;
            case 'top10':
                $where_conditions[] = "h.ranking <= 10";
                break;
        }
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    $stmt = $pdo->prepare("
        SELECT 
            h.id,
            h.kwarcan_id,
            h.nilai_optimasi,
            h.ranking,
            h.created_at,
            k.kode_kwarcan,
            k.nama_kwarcan,
            k.daerah,
            k.kontak
        FROM hasil_moora h
        JOIN kwarcan k ON h.kwarcan_id = k.id
        $where_clause
        ORDER BY h.ranking ASC
    ");
    $stmt->execute($params);
    $ranking_results = $stmt->fetchAll();
    
    // Get criteria information
    $stmt = $pdo->query("SELECT * FROM kriteria ORDER BY id");
    $kriteria_list = $stmt->fetchAll();
    
    // Get detailed assessment data
    if (!empty($ranking_results)) {
        $kwarcan_ids = array_column($ranking_results, 'kwarcan_id');
        $placeholders = str_repeat('?,', count($kwarcan_ids) - 1) . '?';
        
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
        $stmt->execute($kwarcan_ids);
        $assessment_data = $stmt->fetchAll();
    } else {
        $assessment_data = [];
    }
    
    // Get statistics
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_kwarcan,
            AVG(nilai_optimasi) as avg_nilai,
            MAX(nilai_optimasi) as max_nilai,
            MIN(nilai_optimasi) as min_nilai,
            STD(nilai_optimasi) as std_nilai
        FROM hasil_moora
    ");
    $statistics = $stmt->fetch();
    
    // Get calculation history
    $stmt = $pdo->query("
        SELECT 
            DATE(created_at) as tanggal,
            COUNT(*) as jumlah_hasil,
            MAX(created_at) as waktu_terakhir
        FROM hasil_moora
        GROUP BY DATE(created_at)
        ORDER BY tanggal DESC
        LIMIT 10
    ");
    $calculation_history = $stmt->fetchAll();
    
} catch (Exception $e) {
    $ranking_results = [];
    $kriteria_list = [];
    $assessment_data = [];
    $statistics = null;
    $calculation_history = [];
    $error = 'Error mengambil data: ' . $e->getMessage();
}

// Organize assessment data by kwarcan
$assessment_by_kwarcan = [];
foreach ($assessment_data as $row)
{
    $assessment_by_kwarcan[$row['kwarcan_id']][] = $row;
}

// Handle export requests
if ($export_type) {
    switch ($export_type) {
        case 'pdf':
            generatePDFReport($ranking_results, $kriteria_list, $assessment_by_kwarcan, $statistics);
            break;
        case 'excel':
            generateExcelReport($ranking_results, $kriteria_list, $assessment_by_kwarcan);
            break;
        case 'csv':
            generateCSVReport($ranking_results);
            break;
    }
    exit();
}

function generatePDFReport($ranking_results, $kriteria_list, $assessment_by_kwarcan, $statistics) {
    // Simple HTML to PDF conversion
    $html = generateReportHTML($ranking_results, $kriteria_list, $assessment_by_kwarcan, $statistics, true);
    
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="laporan_moora_' . date('Y-m-d') . '.pdf"');
    
    // For now, we'll use a simple HTML output
    // In production, you should use a proper PDF library like TCPDF or mPDF
    echo $html;
}

function generateExcelReport($ranking_results, $kriteria_list, $assessment_by_kwarcan) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="laporan_moora_' . date('Y-m-d') . '.xls"');
    
    echo '<table border="1">';
    echo '<tr><th colspan="' . (5 + count($kriteria_list)) . '">LAPORAN HASIL PERHITUNGAN MOORA</th></tr>';
    echo '<tr><th colspan="' . (5 + count($kriteria_list)) . '">Tanggal: ' . date('d/m/Y H:i:s') . '</th></tr>';
    echo '<tr><td></td></tr>';
    
    // Header
    echo '<tr>';
    echo '<th>Ranking</th>';
    echo '<th>Kode</th>';
    echo '<th>Nama Kwarcan</th>';
    echo '<th>Daerah</th>';
    echo '<th>Nilai Optimasi</th>';
    foreach ($kriteria_list as $kriteria) {
        echo '<th>' . htmlspecialchars($kriteria['nama_kriteria']) . '</th>';
    }
    echo '</tr>';
    
    // Data
    foreach ($ranking_results as $result) {
        echo '<tr>';
        echo '<td>' . $result['ranking'] . '</td>';
        echo '<td>' . htmlspecialchars($result['kode_kwarcan']) . '</td>';
        echo '<td>' . htmlspecialchars($result['nama_kwarcan']) . '</td>';
        echo '<td>' . htmlspecialchars($result['daerah']) . '</td>';
        echo '<td>' . number_format($result['nilai_optimasi'], 4) . '</td>';
        
        // Assessment values
        if (isset($assessment_by_kwarcan[$result['kwarcan_id']])) {
            $assessments = $assessment_by_kwarcan[$result['kwarcan_id']];
            foreach ($kriteria_list as $kriteria) {
                $nilai = '-';
                foreach ($assessments as $assessment) {
                    if ($assessment['kriteria_id'] == $kriteria['id']) {
                        $nilai = $assessment['nilai'];
                        break;
                    }
                }
                echo '<td>' . $nilai . '</td>';
            }
        } else {
            foreach ($kriteria_list as $kriteria) {
                echo '<td>-</td>';
            }
        }
        echo '</tr>';
    }
    echo '</table>';
}

function generateCSVReport($ranking_results) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="laporan_moora_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Header
    fputcsv($output, ['Ranking', 'Kode', 'Nama Kwarcan', 'Daerah', 'Nilai Optimasi', 'Tanggal']);
    
    // Data
    foreach ($ranking_results as $result) {
        fputcsv($output, [
            $result['ranking'],
            $result['kode_kwarcan'],
            $result['nama_kwarcan'],
            $result['daerah'],
            number_format($result['nilai_optimasi'], 4),
            date('d/m/Y H:i', strtotime($result['created_at']))
        ]);
    }
    
    fclose($output);
}

function generateReportHTML($ranking_results, $kriteria_list, $assessment_by_kwarcan, $statistics, $for_pdf = false) {
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Laporan Hasil MOORA</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .header { text-align: center; margin-bottom: 30px; }
            .stats-table { width: 100%; margin-bottom: 20px; border-collapse: collapse; }
            .stats-table th, .stats-table td { border: 1px solid #ddd; padding: 8px; }
            .ranking-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            .ranking-table th, .ranking-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            .ranking-table th { background-color: #f2f2f2; }
            .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>LAPORAN HASIL PERHITUNGAN MOORA</h1>
            <h2>Sistem Pendukung Keputusan Pemilihan Kwarcan</h2>
            <p>Tanggal: <?= date('d/m/Y H:i:s') ?></p>
        </div>
        
        <?php if ($statistics): ?>
        <h3>Statistik Hasil</h3>
        <table class="stats-table">
            <tr><th>Total Kwarcan</th><td><?= $statistics['total_kwarcan'] ?></td></tr>
            <tr><th>Nilai Rata-rata</th><td><?= number_format($statistics['avg_nilai'], 4) ?></td></tr>
            <tr><th>Nilai Tertinggi</th><td><?= number_format($statistics['max_nilai'], 4) ?></td></tr>
            <tr><th>Nilai Terendah</th><td><?= number_format($statistics['min_nilai'], 4) ?></td></tr>
            <tr><th>Standar Deviasi</th><td><?= number_format($statistics['std_nilai'], 4) ?></td></tr>
        </table>
        <?php endif; ?>
        
        <h3>Hasil Perangkingan</h3>
        <table class="ranking-table">
            <thead>
                <tr>
                    <th>Ranking</th>
                    <th>Kode</th>
                    <th>Nama Kwarcan</th>
                    <th>Daerah</th>
                    <th>Nilai Optimasi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ranking_results as $result): ?>
                <tr>
                    <td><?= $result['ranking'] ?></td>
                    <td><?= htmlspecialchars($result['kode_kwarcan']) ?></td>
                    <td><?= htmlspecialchars($result['nama_kwarcan']) ?></td>
                    <td><?= htmlspecialchars($result['daerah']) ?></td>
                    <td><?= number_format($result['nilai_optimasi'], 4) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="footer">
            <p>Laporan ini dibuat secara otomatis oleh Sistem SPK MOORA</p>
            <p>© <?= date('Y') ?> SPK MOORA Kwarcan</p>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

$page_title = "Laporan - SPK MOORA Kwarcan";
?>

<?php include '../includes/header.php'; ?>

<div class="dashboard-container">
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="content">
        <!-- Header -->
        <div class="content-header">
            <div class="header-title">
                <h1><i class="fas fa-file-alt"></i> Laporan Hasil MOORA</h1>
                <p>Generate dan export laporan hasil perhitungan MOORA dalam berbagai format</p>
            </div>
            <div class="header-actions">
                <button class="btn btn-primary" onclick="generateReport()">
                    <i class="fas fa-sync-alt"></i> Refresh Data
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

        <!-- Filter Section -->
        <div class="filter-section">
            <div class="filter-card">
                <div class="filter-header">
                    <h3><i class="fas fa-filter"></i> Filter Laporan</h3>
                </div>
                <div class="filter-content">
                    <form method="GET" id="filterForm">
                        <div class="filter-grid">
                            <div class="filter-group">
                                <label for="periode">Periode</label>
                                <select name="periode" id="periode" class="form-control">
                                    <option value="all" <?= $filter_periode === 'all' ? 'selected' : '' ?>>Semua Periode</option>
                                    <option value="today" <?= $filter_periode === 'today' ? 'selected' : '' ?>>Hari Ini</option>
                                    <option value="week" <?= $filter_periode === 'week' ? 'selected' : '' ?>>Minggu Ini</option>
                                    <option value="month" <?= $filter_periode === 'month' ? 'selected' : '' ?>>Bulan Ini</option>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label for="ranking">Ranking</label>
                                <select name="ranking" id="ranking" class="form-control">
                                    <option value="all" <?= $filter_ranking === 'all' ? 'selected' : '' ?>>Semua Ranking</option>
                                    <option value="top3" <?= $filter_ranking === 'top3' ? 'selected' : '' ?>>Top 3</option>
                                    <option value="top5" <?= $filter_ranking === 'top5' ? 'selected' : '' ?>>Top 5</option>
                                    <option value="top10" <?= $filter_ranking === 'top10' ? 'selected' : '' ?>>Top 10</option>
                                </select>
                            </div>

                            <div class="filter-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Filter
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="resetFilter()">
                                    <i class="fas fa-undo"></i> Reset
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php if (empty($ranking_results)): ?>
            <!-- No Data State -->
            <div class="no-data-container">
                <div class="no-data-card">
                    <div class="no-data-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <h2>Tidak Ada Data Laporan</h2>
                    <p>Belum ada hasil perhitungan MOORA yang dapat dilaporkan. Silakan jalankan perhitungan terlebih dahulu.</p>
                    <div class="no-data-actions">
                        <a href="hitung-moora.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-calculator"></i> Hitung MOORA
                        </a>
                        <a href="penilaian.php" class="btn btn-secondary btn-lg">
                            <i class="fas fa-star"></i> Input Penilaian
                        </a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Statistics Overview -->
            <?php if ($statistics): ?>
                <div class="statistics-section">
                    <div class="section-header">
                        <h2><i class="fas fa-chart-pie"></i> Statistik Hasil</h2>
                    </div>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?= $statistics['total_kwarcan'] ?></h3>
                                <p>Total Kwarcan</p>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-calculator"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?= number_format($statistics['avg_nilai'], 4) ?></h3>
                                <p>Nilai Rata-rata</p>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-arrow-up"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?= number_format($statistics['max_nilai'], 4) ?></h3>
                                <p>Nilai Tertinggi</p>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-arrow-down"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?= number_format($statistics['min_nilai'], 4) ?></h3>
                                                                <p>Nilai Terendah</p>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?= number_format($statistics['std_nilai'], 4) ?></h3>
                                <p>Standar Deviasi</p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Export Options -->
            <div class="export-section">
                <div class="section-header">
                    <h2><i class="fas fa-download"></i> Export Laporan</h2>
                    <div class="export-info">
                        <span class="data-count"><?= count($ranking_results) ?> data siap export</span>
                    </div>
                </div>
                <div class="export-grid">
                    <div class="export-card" onclick="exportReport('pdf')">
                        <div class="export-icon">
                            <i class="fas fa-file-pdf"></i>
                        </div>
                        <div class="export-content">
                            <h3>PDF Report</h3>
                            <p>Laporan lengkap dalam format PDF</p>
                            <div class="export-features">
                                <span><i class="fas fa-check"></i> Statistik lengkap</span>
                                <span><i class="fas fa-check"></i> Tabel ranking</span>
                                <span><i class="fas fa-check"></i> Siap cetak</span>
                            </div>
                        </div>
                        <div class="export-action">
                            <span>Download PDF</span>
                            <i class="fas fa-arrow-right"></i>
                        </div>
                    </div>

                    <div class="export-card" onclick="exportReport('excel')">
                        <div class="export-icon">
                            <i class="fas fa-file-excel"></i>
                        </div>
                        <div class="export-content">
                            <h3>Excel Report</h3>
                            <p>Data dalam format Excel untuk analisis</p>
                            <div class="export-features">
                                <span><i class="fas fa-check"></i> Data terstruktur</span>
                                <span><i class="fas fa-check"></i> Mudah diedit</span>
                                <span><i class="fas fa-check"></i> Formula siap</span>
                            </div>
                        </div>
                        <div class="export-action">
                            <span>Download Excel</span>
                            <i class="fas fa-arrow-right"></i>
                        </div>
                    </div>

                    <div class="export-card" onclick="exportReport('csv')">
                        <div class="export-icon">
                            <i class="fas fa-file-csv"></i>
                        </div>
                        <div class="export-content">
                            <h3>CSV Report</h3>
                            <p>Data mentah dalam format CSV</p>
                            <div class="export-features">
                                <span><i class="fas fa-check"></i> Format universal</span>
                                <span><i class="fas fa-check"></i> Ringan</span>
                                <span><i class="fas fa-check"></i> Import mudah</span>
                            </div>
                        </div>
                        <div class="export-action">
                            <span>Download CSV</span>
                            <i class="fas fa-arrow-right"></i>
                        </div>
                    </div>

                    <div class="export-card" onclick="printReport()">
                        <div class="export-icon">
                            <i class="fas fa-print"></i>
                        </div>
                        <div class="export-content">
                            <h3>Print Report</h3>
                            <p>Cetak langsung ke printer</p>
                            <div class="export-features">
                                <span><i class="fas fa-check"></i> Format cetak</span>
                                <span><i class="fas fa-check"></i> Tanpa download</span>
                                <span><i class="fas fa-check"></i> Cepat</span>
                            </div>
                        </div>
                        <div class="export-action">
                            <span>Print Now</span>
                            <i class="fas fa-arrow-right"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Report Preview -->
            <div class="preview-section">
                <div class="section-header">
                    <h2><i class="fas fa-eye"></i> Preview Laporan</h2>
                    <div class="preview-controls">
                        <button class="btn btn-secondary" onclick="togglePreviewMode()">
                            <i class="fas fa-expand-alt"></i> <span id="previewModeText">Fullscreen</span>
                        </button>
                    </div>
                </div>

                <div class="preview-container" id="previewContainer">
                    <div class="report-preview">
                        <!-- Report Header -->
                        <div class="report-header">
                            <div class="report-title">
                                <h1>LAPORAN HASIL PERHITUNGAN MOORA</h1>
                                <h2>Sistem Pendukung Keputusan Pemilihan Kwarcan</h2>
                                <div class="report-meta">
                                    <div class="meta-item">
                                        <i class="fas fa-calendar"></i>
                                        <span>Tanggal: <?= date('d/m/Y H:i:s') ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <i class="fas fa-user"></i>
                                        <span>Dibuat oleh: <?= htmlspecialchars($_SESSION['admin_nama']) ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <i class="fas fa-database"></i>
                                        <span>Total Data: <?= count($ranking_results) ?> Kwarcan</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Criteria Information -->
                        <div class="criteria-section">
                            <h3>Kriteria Penilaian</h3>
                            <div class="criteria-table">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Kode</th>
                                            <th>Nama Kriteria</th>
                                            <th>Bobot</th>
                                            <th>Jenis</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($kriteria_list as $kriteria): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($kriteria['kode_kriteria']) ?></td>
                                            <td><?= htmlspecialchars($kriteria['nama_kriteria']) ?></td>
                                            <td><?= $kriteria['bobot'] ?></td>
                                            <td>
                                                <span class="criteria-type <?= $kriteria['jenis'] ?>">
                                                    <?= ucfirst($kriteria['jenis']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Ranking Results -->
                        <div class="ranking-section">
                            <h3>Hasil Perangkingan</h3>
                            <div class="ranking-table">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Ranking</th>
                                            <th>Kode</th>
                                            <th>Nama Kwarcan</th>
                                            <th>Daerah</th>
                                            <th>Nilai Optimasi</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($ranking_results as $result): ?>
                                        <tr class="ranking-row">
                                            <td>
                                                <div class="ranking-badge rank-<?= min($result['ranking'], 3) ?>">
                                                    <?php if ($result['ranking'] <= 3): ?>
                                                        <i class="fas fa-trophy"></i>
                                                    <?php endif; ?>
                                                    <?= $result['ranking'] ?>
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
                                            <td>
                                                <span class="nilai-badge">
                                                    <?= number_format($result['nilai_optimasi'], 4) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php
                                                $status_class = 'standar';
                                                $status_text = 'Standar';
                                                if ($result['ranking'] == 1) {
                                                    $status_class = 'terbaik';
                                                    $status_text = 'Terbaik';
                                                } elseif ($result['ranking'] <= 3) {
                                                    $status_class = 'top';
                                                    $status_text = 'Top 3';
                                                } elseif ($result['ranking'] <= 5) {
                                                    $status_class = 'baik';
                                                    $status_text = 'Top 5';
                                                }
                                                ?>
                                                <span class="status-badge status-<?= $status_class ?>">
                                                    <?= $status_text ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Detailed Assessment (Top 5) -->
                        <div class="assessment-section">
                            <h3>Detail Penilaian (Top 5)</h3>
                            <?php 
                            $top_5 = array_slice($ranking_results, 0, 5);
                            foreach ($top_5 as $result): 
                                if (isset($assessment_by_kwarcan[$result['kwarcan_id']])):
                            ?>
                            <div class="assessment-card">
                                <div class="assessment-header">
                                    <div class="assessment-rank">
                                        <span class="rank-number"><?= $result['ranking'] ?></span>
                                    </div>
                                    <div class="assessment-info">
                                        <h4><?= htmlspecialchars($result['nama_kwarcan']) ?></h4>
                                        <p><?= htmlspecialchars($result['daerah']) ?></p>
                                        <span class="final-score">Nilai Akhir: <?= number_format($result['nilai_optimasi'], 4) ?></span>
                                    </div>
                                </div>
                                <div class="assessment-details">
                                    <?php foreach ($assessment_by_kwarcan[$result['kwarcan_id']] as $assessment): ?>
                                    <div class="assessment-item">
                                        <span class="kriteria-name"><?= htmlspecialchars($assessment['nama_kriteria']) ?></span>
                                        <div class="nilai-info">
                                            <span class="nilai-value"><?= $assessment['nilai'] ?></span>
                                            <span class="bobot-info">(Bobot: <?= $assessment['bobot'] ?>)</span>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </div>

                        <!-- Report Footer -->
                        <div class="report-footer">
                            <div class="footer-content">
                                <div class="footer-section">
                                    <h4>Metodologi</h4>
                                    <p>Laporan ini menggunakan metode MOORA (Multi-Objective Optimization by Ratio Analysis) untuk menentukan ranking kwarcan berdasarkan kriteria yang telah ditetapkan.</p>
                                </div>
                                <div class="footer-section">
                                    <h4>Keterangan</h4>
                                    <ul>
                                        <li>Nilai optimasi dihitung berdasarkan normalisasi dan pembobotan kriteria</li>
                                        <li>Ranking ditentukan dari nilai optimasi tertinggi ke terendah</li>
                                        <li>Semua kriteria menggunakan jenis benefit (semakin tinggi semakin baik)</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="footer-signature">
                                <p>Laporan ini dibuat secara otomatis oleh Sistem SPK MOORA</p>
                                <p>© <?= date('Y') ?> SPK MOORA Kwarcan - All Rights Reserved</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Calculation History -->
            <?php if (!empty($calculation_history)): ?>
            <div class="history-section">
                <div class="section-header">
                    <h2><i class="fas fa-history"></i> Riwayat Perhitungan</h2>
                </div>
                <div class="history-container">
                    <div class="history-timeline">
                        <?php foreach ($calculation_history as $history): ?>
                        <div class="history-item">
                            <div class="history-date">
                                <div class="date-circle">
                                    <i class="fas fa-calculator"></i>
                                </div>
                                <div class="date-info">
                                    <strong><?= date('d/m/Y', strtotime($history['tanggal'])) ?></strong>
                                    <small><?= date('H:i', strtotime($history['waktu_terakhir'])) ?></small>
                                </div>
                            </div>
                            <div class="history-content">
                                <h4>Perhitungan MOORA</h4>
                                <p><?= $history['jumlah_hasil'] ?> kwarcan berhasil dihitung</p>
                                                                <small>Terakhir diupdate: <?= date('d/m/Y H:i', strtotime($history['waktu_terakhir'])) ?></small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

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

    /* Filter Section */
    .filter-section {
        margin-bottom: 40px;
    }

    .filter-card {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(20px);
        border-radius: 20px;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        overflow: hidden;
    }

    .filter-header {
        padding: 25px 30px 20px;
        border-bottom: 1px solid rgba(0, 0, 0, 0.1);
    }

    .filter-header h3 {
        color: #333;
        font-size: 1.3rem;
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 0;
    }

    .filter-content {
        padding: 25px 30px;
    }

    .filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        align-items: end;
    }

    .filter-group label {
        display: block;
        color: #333;
        font-weight: 600;
        margin-bottom: 8px;
        font-size: 0.9rem;
    }

    .form-control {
        width: 100%;
        padding: 12px 15px;
        border: 2px solid rgba(0, 0, 0, 0.1);
        border-radius: 12px;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        background: white;
    }

    .form-control:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .filter-actions {
        display: flex;
        gap: 10px;
    }

    /* Statistics Section */
    .statistics-section {
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
        gap: 12px;
        margin: 0;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
    }

    .stat-card {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(20px);
        border-radius: 20px;
        padding: 25px;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
    }

    .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
    }

    .stat-icon i {
        font-size: 1.8rem;
        color: #667eea;
    }

    .stat-content h3 {
        font-size: 1.8rem;
        color: #333;
        margin-bottom: 5px;
        font-weight: 700;
    }

    .stat-content p {
        color: #666;
        font-size: 0.9rem;
        margin: 0;
    }

    /* Export Section */
    .export-section {
        margin-bottom: 40px;
    }

    .export-info {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .data-count {
        background: rgba(102, 126, 234, 0.1);
        color: #667eea;
        padding: 8px 15px;
        border-radius: 20px;
        font-size: 0.9rem;
        font-weight: 600;
    }

    .export-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 25px;
    }

    .export-card {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(20px);
        border-radius: 20px;
        padding: 30px;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
        cursor: pointer;
        position: relative;
        overflow: hidden;
    }

    .export-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        transform: scaleX(0);
        transition: transform 0.3s ease;
    }

    .export-card:hover::before {
        transform: scaleX(1);
    }

    .export-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
    }

    .export-icon {
        width: 70px;
        height: 70px;
        border-radius: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
        margin-bottom: 20px;
        transition: all 0.3s ease;
    }

    .export-card:hover .export-icon {
        background: linear-gradient(135deg, #667eea, #764ba2);
        transform: scale(1.1);
    }

    .export-icon i {
        font-size: 2rem;
        color: #667eea;
        transition: color 0.3s ease;
    }

    .export-card:hover .export-icon i {
        color: white;
    }

    .export-content h3 {
        color: #333;
        font-size: 1.3rem;
        margin-bottom: 8px;
        font-weight: 700;
    }

    .export-content p {
        color: #666;
        font-size: 0.95rem;
        margin-bottom: 15px;
        line-height: 1.5;
    }

    .export-features {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .export-features span {
        color: #28a745;
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .export-features i {
        font-size: 0.8rem;
    }

    .export-action {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid rgba(0, 0, 0, 0.1);
        color: #667eea;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .export-card:hover .export-action {
        color: #333;
    }

    .export-action i {
        transition: transform 0.3s ease;
    }

    .export-card:hover .export-action i {
        transform: translateX(5px);
    }

    /* Preview Section */
    .preview-section {
        margin-bottom: 40px;
    }

    .preview-controls {
        display: flex;
        gap: 10px;
    }

    .preview-container {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(20px);
        border-radius: 20px;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .preview-container.fullscreen {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 1000;
        border-radius: 0;
        overflow-y: auto;
    }

    .report-preview {
        padding: 40px;
        max-width: 100%;
        margin: 0 auto;
        background: white;
    }

    .report-header {
        text-align: center;
        margin-bottom: 40px;
        padding-bottom: 30px;
        border-bottom: 2px solid #667eea;
    }

    .report-title h1 {
        color: #333;
        font-size: 2rem;
        margin-bottom: 10px;
        font-weight: 700;
    }

    .report-title h2 {
        color: #667eea;
        font-size: 1.3rem;
        margin-bottom: 20px;
        font-weight: 600;
    }

    .report-meta {
        display: flex;
        justify-content: center;
        gap: 30px;
        flex-wrap: wrap;
    }

    .meta-item {
        display: flex;
        align-items: center;
        gap: 8px;
        color: #666;
        font-size: 0.9rem;
    }

    .meta-item i {
        color: #667eea;
    }

    /* Criteria Section */
    .criteria-section {
        margin-bottom: 40px;
    }

    .criteria-section h3 {
        color: #333;
        font-size: 1.3rem;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 1px solid rgba(0, 0, 0, 0.1);
    }

    .criteria-table table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
    }

    .criteria-table th,
    .criteria-table td {
        padding: 12px 15px;
        text-align: left;
        border-bottom: 1px solid rgba(0, 0, 0, 0.1);
    }

    .criteria-table th {
        background: rgba(102, 126, 234, 0.1);
        color: #333;
        font-weight: 600;
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

    /* Ranking Section */
    .ranking-section {
        margin-bottom: 40px;
    }

    .ranking-section h3 {
        color: #333;
        font-size: 1.3rem;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 1px solid rgba(0, 0, 0, 0.1);
    }

    .ranking-table table {
        width: 100%;
        border-collapse: collapse;
    }

    .ranking-table th,
    .ranking-table td {
        padding: 15px 12px;
        text-align: left;
        border-bottom: 1px solid rgba(0, 0, 0, 0.1);
    }

    .ranking-table th {
        background: rgba(102, 126, 234, 0.1);
        color: #333;
        font-weight: 600;
        position: sticky;
        top: 0;
    }

    .ranking-row:hover {
        background: rgba(102, 126, 234, 0.05);
    }

    .ranking-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        border-radius: 20px;
        font-weight: 700;
        font-size: 0.9rem;
        min-width: 50px;
        justify-content: center;
    }

    .ranking-badge.rank-1 {
        background: linear-gradient(135deg, #ffd700, #ffed4e);
        color: #333;
    }

    .ranking-badge.rank-2 {
        background: linear-gradient(135deg, #c0c0c0, #e5e5e5);
        color: #333;
    }

        .ranking-badge.rank-3 {
        background: linear-gradient(135deg, #cd7f32, #daa520);
        color: white;
    }

    .nilai-badge {
        background: rgba(102, 126, 234, 0.1);
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

    /* Assessment Section */
    .assessment-section {
        margin-bottom: 40px;
    }

    .assessment-section h3 {
        color: #333;
        font-size: 1.3rem;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 1px solid rgba(0, 0, 0, 0.1);
    }

    .assessment-card {
        background: rgba(102, 126, 234, 0.05);
        border-radius: 15px;
        padding: 25px;
        margin-bottom: 20px;
        border-left: 4px solid #667eea;
    }

    .assessment-header {
        display: flex;
        align-items: center;
        gap: 20px;
        margin-bottom: 20px;
    }

    .assessment-rank {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea, #764ba2);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 700;
        font-size: 1.2rem;
    }

    .assessment-info h4 {
        color: #333;
        font-size: 1.2rem;
        margin-bottom: 5px;
    }

    .assessment-info p {
        color: #666;
        margin-bottom: 8px;
    }

    .final-score {
        background: rgba(102, 126, 234, 0.1);
        color: #667eea;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 0.85rem;
        font-weight: 600;
    }

    .assessment-details {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
    }

    .assessment-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 15px;
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .kriteria-name {
        font-weight: 600;
        color: #333;
        font-size: 0.9rem;
    }

    .nilai-info {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 2px;
    }

    .nilai-value {
        font-weight: 700;
        color: #667eea;
        font-size: 1rem;
    }

    .bobot-info {
        font-size: 0.75rem;
        color: #999;
    }

    /* Report Footer */
    .report-footer {
        margin-top: 50px;
        padding-top: 30px;
        border-top: 2px solid #667eea;
    }

    .footer-content {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 30px;
        margin-bottom: 30px;
    }

    .footer-section h4 {
        color: #333;
        font-size: 1.1rem;
        margin-bottom: 15px;
        font-weight: 600;
    }

    .footer-section p {
        color: #666;
        line-height: 1.6;
        margin-bottom: 10px;
    }

    .footer-section ul {
        color: #666;
        line-height: 1.6;
        padding-left: 20px;
    }

    .footer-section li {
        margin-bottom: 5px;
    }

    .footer-signature {
        text-align: center;
        padding-top: 20px;
        border-top: 1px solid rgba(0, 0, 0, 0.1);
        color: #999;
        font-size: 0.9rem;
    }

    /* History Section */
    .history-section {
        margin-bottom: 40px;
    }

    .history-container {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(20px);
        border-radius: 20px;
        padding: 30px;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
    }

    .history-timeline {
        position: relative;
        padding-left: 30px;
    }

    .history-timeline::before {
        content: '';
        position: absolute;
        left: 25px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: linear-gradient(135deg, #667eea, #764ba2);
    }

    .history-item {
        display: flex;
        align-items: center;
        gap: 20px;
        margin-bottom: 25px;
        position: relative;
    }

    .history-date {
        display: flex;
        align-items: center;
        gap: 15px;
        min-width: 200px;
    }

    .date-circle {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea, #764ba2);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        position: relative;
        z-index: 2;
    }

    .date-info strong {
        color: #333;
        font-size: 0.95rem;
    }

    .date-info small {
        color: #666;
        font-size: 0.8rem;
    }

    .history-content {
        flex: 1;
        background: rgba(102, 126, 234, 0.05);
        padding: 20px;
        border-radius: 15px;
        border-left: 4px solid #667eea;
    }

    .history-content h4 {
        color: #333;
        font-size: 1.1rem;
        margin-bottom: 8px;
    }

    .history-content p {
        color: #666;
        margin-bottom: 5px;
    }

    .history-content small {
        color: #999;
        font-size: 0.8rem;
    }

    /* No Data State */
    .no-data-container {
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 400px;
    }

    .no-data-card {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(20px);
        border-radius: 25px;
        padding: 60px 40px;
        text-align: center;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        max-width: 500px;
    }

    .no-data-icon {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 30px;
    }

    .no-data-icon i {
        font-size: 3rem;
        color: #667eea;
    }

    .no-data-card h2 {
        color: #333;
        font-size: 1.8rem;
        margin-bottom: 15px;
        font-weight: 700;
    }

    .no-data-card p {
        color: #666;
        font-size: 1rem;
        line-height: 1.6;
        margin-bottom: 30px;
    }

    .no-data-actions {
        display: flex;
        gap: 15px;
        justify-content: center;
        flex-wrap: wrap;
    }

    /* Buttons */
    .btn {
        padding: 12px 20px;
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
        position: relative;
        overflow: hidden;
    }

    .btn::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
        transition: left 0.5s;
    }

    .btn:hover::before {
        left: 100%;
    }

    .btn-lg {
        padding: 15px 25px;
        font-size: 1rem;
    }

    .btn-primary {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
    }

    .btn-secondary {
        background: linear-gradient(135deg, #6c757d, #495057);
        color: white;
        box-shadow: 0 8px 25px rgba(108, 117, 125, 0.3);
    }

    .btn-success {
        background: linear-gradient(135deg, #28a745, #20c997);
        color: white;
        box-shadow: 0 8px 25px rgba(40, 167, 69, 0.3);
    }

    .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 35px rgba(0, 0, 0, 0.2);
    }

    .btn:active {
        transform: translateY(0);
    }

    /* Alert Messages */
    .alert {
        padding: 15px 20px;
        border-radius: 12px;
        margin-bottom: 25px;
        display: flex;
        align-items: center;
        gap: 12px;
        font-weight: 500;
    }

    .alert-success {
        background: rgba(40, 167, 69, 0.1);
        color: #155724;
        border: 1px solid rgba(40, 167, 69, 0.2);
    }

    .alert-danger {
        background: rgba(220, 53, 69, 0.1);
        color: #721c24;
        border: 1px solid rgba(220, 53, 69, 0.2);
    }

    /* Responsive Design */
    @media (max-width: 1200px) {
        .export-grid {
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        }
        
        .stats-grid {
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        }
    }

    @media (max-width: 768px) {
        .content {
            margin-left: 0;
            padding: 20px 15px;
        }

        .content-header {
            flex-direction: column;
            align-items: flex-start;
        }

        .filter-grid {
            grid-template-columns: 1fr;
        }

        .export-grid {
            grid-template-columns: 1fr;
        }

        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .report-meta {
            flex-direction: column;
            gap: 15px;
        }

        .assessment-details {
            grid-template-columns: 1fr;
        }

        .footer-content {
            grid-template-columns: 1fr;
        }

        .history-date {
            min-width: 150px;
        }

        .no-data-actions {
            flex-direction: column;
        }
    }

    @media (max-width: 480px) {
        .header-title h1 {
            font-size: 1.5rem;
        }

        .stats-grid {
            grid-template-columns: 1fr;
        }

        .stat-card {
            flex-direction: column;
            text-align: center;
        }

        .report-preview {
            padding: 20px;
        }

        .report-title h1 {
            font-size: 1.5rem;
        }

        .report-title h2 {
            font-size: 1.1rem;
        }
    }

    /* Print Styles */
    @media print {
        .content {
            margin-left: 0;
            padding: 0;
        }

        .filter-section,
        .export-section,
        .preview-controls,
        .history-section {
            display: none;
        }

        .preview-container {
            box-shadow: none;
            border-radius: 0;
        }

        .report-preview {
            padding: 20px;
        }

        .btn {
            display: none;
        }
    }
</style>

<script>
    // Export functions
    function exportReport(type) {
        const btn = event.currentTarget;
        const originalContent = btn.innerHTML;
        
        // Show loading state
        btn.style.pointerEvents = 'none';
        btn.innerHTML = `
            <div class="export-icon">
                <i class="fas fa-spinner fa-spin"></i>
            </div>
            <div class="export-content">
                <h3>Processing...</h3>
                <p>Generating ${type.toUpperCase()} report</p>
            </div>
        `;
        
        // Create download link
        const currentUrl = new URL(window.location);
        currentUrl.searchParams.set('export', type);
        
        // Trigger download
        window.location.href = currentUrl.toString();
        
        // Reset button after delay
        setTimeout(() => {
            btn.style.pointerEvents = 'auto';
            btn.innerHTML = originalContent;
        }, 3000);
    }

    // Print function
    function printReport() {
        const printContent = document.querySelector('.report-preview').innerHTML;
        const originalContent = document.body.innerHTML;
        
        document.body.innerHTML = `
            <div style="padding: 20px;">
                ${printContent}
            </div>
        `;
        
        window.print();
        document.body.innerHTML = originalContent;
        location.reload();
    }

    // Toggle preview mode
    let isFullscreen = false;
    function togglePreviewMode() {
        const container = document.getElementById('previewContainer');
        const modeText = document.getElementById('previewModeText');
        
        if (!isFullscreen) {
            container.classList.add('fullscreen');
            modeText.textContent = 'Exit Fullscreen';
            isFullscreen = true;
            
            // Add close button
            const closeBtn = document.createElement('button');
            closeBtn.className = 'btn btn-secondary';
            closeBtn.style.position = 'fixed';
            closeBtn.style.top = '20px';
            closeBtn.style.right = '20px';
            closeBtn.style.zIndex = '1001';
            closeBtn.innerHTML = '<i class="fas fa-times"></i> Close';
            closeBtn.onclick = togglePreviewMode;
            container.appendChild(closeBtn);
            
        } else {
            container.classList.remove('fullscreen');
            modeText.textContent = 'Fullscreen';
            isFullscreen = false;
            
            // Remove close button
            const closeBtn = container.querySelector('.btn-secondary');
            if (closeBtn) closeBtn.remove();
        }
    }

    // Reset filter
    function resetFilter() {
        const form = document.getElementById('filterForm');
        form.reset();
        form.submit();
    }

    // Generate report (refresh data)
    function generateReport() {
        const btn = event.currentTarget;
        const originalText = btn.innerHTML;
        
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Refreshing...';
        btn.disabled = true;
        
        // Reload page to refresh data
        setTimeout(() => {
            location.reload();
        }, 1000);
    }

    // Auto-hide alerts
    document.addEventListener('DOMContentLoaded', function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(() => {
                    alert.remove();
                }, 300);
            }, 5000);
        });
        
        // Initialize tooltips for export cards
        const exportCards = document.querySelectorAll('.export-card');
        exportCards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-8px) scale(1.02)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });
        
        // Smooth scroll for internal links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey) {
                switch(e.key) {
                    case 'p':
                        e.preventDefault();
                        printReport();
                        break;
                    case 'e':
                        e.preventDefault();
                        exportReport('excel');
                        break;
                    case 'f':
                        e.preventDefault();
                        togglePreviewMode();
                        break;
                }
            }
            
            // ESC to exit fullscreen
            if (e.key === 'Escape' && isFullscreen) {
                togglePreviewMode();
            }
        });
        
        // Add loading animation to tables
        const tables = document.querySelectorAll('table');
        tables.forEach(table => {
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach((row, index) => {
                row.style.opacity = '0';
                row.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    row.style.transition = 'all 0.3s ease';
                    row.style.opacity = '1';
                    row.style.transform = 'translateY(0)';
                }, index * 50);
            });
        });
        
        // Animate statistics cards
        const statCards = document.querySelectorAll('.stat-card');
        statCards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(30px)';
            setTimeout(() => {
                card.style.transition = 'all 0.5s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 100);
        });
        
        // Animate export cards
        const exportCardsAnim = document.querySelectorAll('.export-card');
        exportCardsAnim.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(30px)';
            setTimeout(() => {
                card.style.transition = 'all 0.5s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, (index * 100) + 500);
        });
    });

    // Progress bar animation for statistics
    function animateProgressBars() {
        const progressBars = document.querySelectorAll('.progress-bar');
        progressBars.forEach(bar => {
            const width = bar.style.width;
            bar.style.width = '0%';
            setTimeout(() => {
                bar.style.transition = 'width 1s ease-in-out';
                bar.style.width = width;
            }, 500);
        });
    }

    // Call animation on load
    window.addEventListener('load', animateProgressBars);

    // Add ripple effect to buttons
    function addRippleEffect(e) {
        const button = e.currentTarget;
        const ripple = document.createElement('span');
        const rect = button.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        const x = e.clientX - rect.left - size / 2;
        const y = e.clientY - rect.top - size / 2;
        
        ripple.style.width = ripple.style.height = size + 'px';
        ripple.style.left = x + 'px';
        ripple.style.top = y + 'px';
        ripple.classList.add('ripple');
        
        button.appendChild(ripple);
        
        setTimeout(() => {
            ripple.remove();
        }, 600);
    }

    // Add ripple effect to all buttons
    document.querySelectorAll('.btn, .export-card').forEach(btn => {
        btn.addEventListener('click', addRippleEffect);
    });

    // Add CSS for ripple effect
    const rippleStyle = document.createElement('style');
    rippleStyle.textContent = `
        .btn, .export-card {
            position: relative;
            overflow: hidden;
        }
        
        .ripple {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.6);
            transform: scale(0);
            animation: ripple-animation 0.6s linear;
            pointer-events: none;
        }
        
        @keyframes ripple-animation {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(rippleStyle);

    // Enhanced table interactions
    document.querySelectorAll('.ranking-table tbody tr').forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.style.backgroundColor = 'rgba(102, 126, 234, 0.1)';
            this.style.transform = 'scale(1.01)';
            this.style.boxShadow = '0 5px 15px rgba(0, 0, 0, 0.1)';
        });
        
        row.addEventListener('mouseleave', function() {
            this.style.backgroundColor = '';
            this.style.transform = 'scale(1)';
            this.style.boxShadow = '';
        });
    });

    // Add search functionality for large tables
    function addTableSearch() {
        const tables = document.querySelectorAll('.ranking-table');
        tables.forEach(table => {
            if (table.querySelectorAll('tbody tr').length > 10) {
                const searchContainer = document.createElement('div');
                searchContainer.className = 'table-search';
                searchContainer.innerHTML = `
                    <div style="margin-bottom: 15px;">
                        <input type="text" placeholder="Cari kwarcan..." 
                               style="padding: 10px 15px; border: 2px solid #ddd; border-radius: 10px; width: 300px; font-size: 0.9rem;"
                               onkeyup="filterTable(this, '${table.className}')">
                    </div>
                `;
                table.parentNode.insertBefore(searchContainer, table);
            }
        });
    }

    function filterTable(input, tableClass) {
        const filter = input.value.toLowerCase();
        const table = document.querySelector('.' + tableClass);
        const rows = table.querySelectorAll('tbody tr');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(filter) ? '' : 'none';
        });
    }

    // Initialize table search if needed
    addTableSearch();

    // Add export progress tracking
    function trackExportProgress(type) {
        const progressBar = document.createElement('div');
        progressBar.className = 'export-progress';
        progressBar.innerHTML = `
            <div style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); 
                        background: white; padding: 30px; border-radius: 15px; box-shadow: 0 20px 40px rgba(0,0,0,0.2); 
                        z-index: 10000; text-align: center; min-width: 300px;">
                <div style="margin-bottom: 20px;">
                    <i class="fas fa-download" style="font-size: 2rem; color: #667eea; margin-bottom: 15px;"></i>
                    <h3 style="margin: 0; color: #333;">Generating ${type.toUpperCase()} Report</h3>
                </div>
                <div style="width: 100%; height: 6px; background: #f0f0f0; border-radius: 3px; overflow: hidden;">
                    <div class="progress-fill" style="width: 0%; height: 100%; background: linear-gradient(135deg, #667eea, #764ba2); 
                                                     border-radius: 3px; transition: width 0.3s ease;"></div>
                </div>
                <p style="margin: 15px 0 0 0; color: #666; font-size: 0.9rem;">Please wait...</p>
            </div>
        `;
        
        document.body.appendChild(progressBar);
        
        // Simulate progress
        let progress = 0;
        const interval = setInterval(() => {
            progress += Math.random() * 30;
            if (progress > 100) progress = 100;
            
            const fill = progressBar.querySelector('.progress-fill');
            fill.style.width = progress + '%';
            
            if (progress >= 100) {
                clearInterval(interval);
                setTimeout(() => {
                    progressBar.remove();
                }, 1000);
            }
        }, 200);
        
        return progressBar;
    }

    // Update export functions to use progress tracking
    const originalExportReport = exportReport;
    exportReport = function(type) {
        trackExportProgress(type);
        originalExportReport(type);
    };
</script>

<?php include '../includes/footer.php'; ?>
