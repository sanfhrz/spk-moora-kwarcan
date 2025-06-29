<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

require_once '../config/database.php';

$success = '';
$error = '';
$calculation_results = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'calculate':
                try {
                    // Get all assessment data
                    $stmt = $pdo->query("
                        SELECT 
                            k.id as kwarcan_id,
                            k.kode_kwarcan,
                            k.nama_kwarcan,
                            k.daerah,
                            kr.id as kriteria_id,
                            kr.nama_kriteria,
                            kr.bobot,
                            kr.jenis,
                            COALESCE(p.nilai, 0) as nilai
                        FROM kwarcan k
                        CROSS JOIN kriteria kr
                        LEFT JOIN penilaian p ON k.id = p.kwarcan_id AND kr.id = p.kriteria_id
                        WHERE k.status = 'aktif'
                        ORDER BY k.id, kr.id
                    ");
                    
                    $raw_data = $stmt->fetchAll();
                    
                    if (empty($raw_data)) {
                        throw new Exception('Tidak ada data penilaian yang ditemukan');
                    }
                    
                    // Organize data for calculation
                    $matrix = [];
                    $kwarcan_info = [];
                    $kriteria_info = [];
                    
                    foreach ($raw_data as $row) {
                        $kwarcan_id = $row['kwarcan_id'];
                        $kriteria_id = $row['kriteria_id'];
                        
                        // Store kwarcan info
                        if (!isset($kwarcan_info[$kwarcan_id])) {
                            $kwarcan_info[$kwarcan_id] = [
                                'kode' => $row['kode_kwarcan'],
                                'nama' => $row['nama_kwarcan'],
                                'daerah' => $row['daerah']
                            ];
                        }
                        
                        // Store kriteria info
                        if (!isset($kriteria_info[$kriteria_id])) {
                            $kriteria_info[$kriteria_id] = [
                                'nama' => $row['nama_kriteria'],
                                'bobot' => (float)$row['bobot'],
                                'jenis' => $row['jenis']
                            ];
                        }
                        
                        // Store matrix values
                        $matrix[$kwarcan_id][$kriteria_id] = (float)$row['nilai'];
                    }
                    
                    // Perform MOORA calculation
                    $calculation_results = calculateMOORA($matrix, $kriteria_info, $kwarcan_info);
                    
                    $success = 'Perhitungan MOORA berhasil dilakukan!';
                    
                } catch (Exception $e) {
                    $error = 'Error: ' . $e->getMessage();
                }
                break;
                
            case 'save_results':
                try {
                    if (!isset($_POST['results_data'])) {
                        throw new Exception('Data hasil tidak ditemukan');
                    }
                    
                    $results_data = json_decode($_POST['results_data'], true);
                    if (!$results_data) {
                        throw new Exception('Data hasil tidak valid');
                    }
                    
                    // Clear previous results
                    $pdo->exec("DELETE FROM hasil_moora");
                    
                    // Insert new results
                    $stmt = $pdo->prepare("
                        INSERT INTO hasil_moora (kwarcan_id, nilai_optimasi, ranking, created_at) 
                        VALUES (?, ?, ?, NOW())
                    ");
                    
                    foreach ($results_data['ranking'] as $result) {
                        $stmt->execute([
                            $result['kwarcan_id'],
                            $result['nilai_optimasi'],
                            $result['ranking']
                        ]);
                    }
                    
                    $success = 'Hasil perhitungan berhasil disimpan ke database!';
                    
                } catch (Exception $e) {
                    $error = 'Error menyimpan hasil: ' . $e->getMessage();
                }
                break;
        }
    }
}

// Get statistics
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM kwarcan WHERE status = 'aktif'");
    $total_kwarcan = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM kriteria");
    $total_kriteria = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(DISTINCT kwarcan_id) as total FROM penilaian");
    $total_assessed = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM hasil_moora");
    $has_results = $stmt->fetch()['total'] > 0;
    
} catch (Exception $e) {
    $total_kwarcan = $total_kriteria = $total_assessed = 0;
    $has_results = false;
}

// MOORA Calculation Function
function calculateMOORA($matrix, $kriteria_info, $kwarcan_info) {
    $results = [
        'original_matrix' => $matrix,
        'normalized_matrix' => [],
        'weighted_matrix' => [],
        'optimization_values' => [],
        'ranking' => [],
        'kriteria_info' => $kriteria_info,
        'kwarcan_info' => $kwarcan_info
    ];
    
    // Step 1: Calculate normalization denominators (√∑x²)
    $denominators = [];
    foreach ($kriteria_info as $kriteria_id => $info) {
        $sum_squares = 0;
        foreach ($matrix as $kwarcan_id => $values) {
            $sum_squares += pow($values[$kriteria_id], 2);
        }
        $denominators[$kriteria_id] = sqrt($sum_squares);
    }
    
    // Step 2: Normalize matrix
    foreach ($matrix as $kwarcan_id => $values) {
        foreach ($values as $kriteria_id => $value) {
            if ($denominators[$kriteria_id] != 0) {
                $results['normalized_matrix'][$kwarcan_id][$kriteria_id] = 
                    $value / $denominators[$kriteria_id];
            } else {
                $results['normalized_matrix'][$kwarcan_id][$kriteria_id] = 0;
            }
        }
    }
    
    // Step 3: Apply weights
    foreach ($results['normalized_matrix'] as $kwarcan_id => $values) {
        foreach ($values as $kriteria_id => $normalized_value) {
            $weight = $kriteria_info[$kriteria_id]['bobot'];
            $results['weighted_matrix'][$kwarcan_id][$kriteria_id] = 
                $normalized_value * $weight;
        }
    }
    
    // Step 4: Calculate optimization values (Benefit - Cost)
    foreach ($results['weighted_matrix'] as $kwarcan_id => $values) {
        $benefit_sum = 0;
        $cost_sum = 0;
        
        foreach ($values as $kriteria_id => $weighted_value) {
            if ($kriteria_info[$kriteria_id]['jenis'] == 'benefit') {
                $benefit_sum += $weighted_value;
            } else {
                $cost_sum += $weighted_value;
            }
        }
        
        $results['optimization_values'][$kwarcan_id] = $benefit_sum - $cost_sum;
    }
    
    // Step 5: Create ranking
    $ranking_data = [];
    foreach ($results['optimization_values'] as $kwarcan_id => $nilai_optimasi) {
        $ranking_data[] = [
            'kwarcan_id' => $kwarcan_id,
            'kode' => $kwarcan_info[$kwarcan_id]['kode'],
            'nama' => $kwarcan_info[$kwarcan_id]['nama'],
            'daerah' => $kwarcan_info[$kwarcan_id]['daerah'],
            'nilai_optimasi' => $nilai_optimasi
        ];
    }
    
    // Sort by optimization value (descending)
    usort($ranking_data, function($a, $b) {
        return $b['nilai_optimasi'] <=> $a['nilai_optimasi'];
    });
    
    // Add ranking numbers
    foreach ($ranking_data as $index => &$item) {
        $item['ranking'] = $index + 1;
    }
    
    $results['ranking'] = $ranking_data;
    
    return $results;
}

$page_title = "Hitung MOORA - SPK Kwarcan";
?>

<?php include '../includes/header.php'; ?>

<div class="dashboard-container">
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="content">
        <!-- Header -->
        <div class="content-header">
            <div class="header-title">
                <h1><i class="fas fa-calculator"></i> Perhitungan MOORA</h1>
                <p>Multi-Objective Optimization by Ratio Analysis untuk menentukan ranking Kwarcan terbaik</p>
            </div>
            <div class="header-actions">
                <a href="hasil-moora.php" class="btn btn-info">
                    <i class="fas fa-trophy"></i> Lihat Hasil
                </a>
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

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $total_kwarcan ?></h3>
                    <p>Total Kwarcan</p>
                    <div class="stat-trend">
                        <i class="fas fa-check-circle"></i>
                        <span>Siap</span>
                    </div>
                </div>
            </div>

            <div class="stat-card success">
                <div class="stat-icon">
                    <i class="fas fa-list-check"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $total_kriteria ?></h3>
                    <p>Kriteria</p>
                    <div class="stat-trend">
                        <i class="fas fa-cog"></i>
                        <span>Aktif</span>
                    </div>
                </div>
            </div>

            <div class="stat-card warning">
                <div class="stat-icon">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $total_assessed ?></h3>
                    <p>Sudah Dinilai</p>
                    <div class="stat-trend">
                        <i class="fas fa-percentage"></i>
                        <span><?= $total_kwarcan > 0 ? round(($total_assessed / $total_kwarcan) * 100, 1) : 0 ?>%</span>
                    </div>
                </div>
            </div>

            <div class="stat-card info">
                <div class="stat-icon">
                    <i class="fas fa-trophy"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $has_results ? 'Ada' : 'Belum' ?></h3>
                    <p>Hasil MOORA</p>
                    <div class="stat-trend">
                        <i class="fas fa-<?= $has_results ? 'check' : 'clock' ?>"></i>
                        <span><?= $has_results ? 'Tersedia' : 'Menunggu' ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="calculation-container">
            <!-- Prerequisites Check -->
            <div class="prerequisites-card">
                <div class="card-header">
                    <h3><i class="fas fa-clipboard-check"></i> Prasyarat Perhitungan</h3>
                </div>
                <div class="card-content">
                    <div class="prerequisites-grid">
                        <div class="prerequisite-item <?= $total_kwarcan > 0 ? 'complete' : 'incomplete' ?>">
                            <div class="prerequisite-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="prerequisite-content">
                                <h4>Data Kwarcan</h4>
                                <p><?= $total_kwarcan ?> Kwarcan tersedia</p>
                            </div>
                            <div class="prerequisite-status">
                                <i class="fas fa-<?= $total_kwarcan > 0 ? 'check-circle' : 'times-circle' ?>"></i>
                            </div>
                        </div>

                        <div class="prerequisite-item <?= $total_kriteria >= 3 ? 'complete' : 'incomplete' ?>">
                            <div class="prerequisite-icon">
                                <i class="fas fa-list"></i>
                            </div>
                            <div class="prerequisite-content">
                                <h4>Kriteria Penilaian</h4>
                                <p><?= $total_kriteria ?> Kriteria (min. 3)</p>
                            </div>
                            <div class="prerequisite-status">
                                <i class="fas fa-<?= $total_kriteria >= 3 ? 'check-circle' : 'times-circle' ?>"></i>
                            </div>
                        </div>

                        <div class="prerequisite-item <?= $total_assessed >= 3 ? 'complete' : 'incomplete' ?>">
                            <div class="prerequisite-icon">
                                <i class="fas fa-star"></i>
                            </div>
                            <div class="prerequisite-content">
                                <h4>Data Penilaian</h4>
                                <p><?= $total_assessed ?> Kwarcan dinilai (min. 3)</p>
                            </div>
                            <div class="prerequisite-status">
                                                                <i class="fas fa-<?= $total_assessed >= 3 ? 'check-circle' : 'times-circle' ?>"></i>
                            </div>
                        </div>
                    </div>

                    <?php 
                    $can_calculate = ($total_kwarcan > 0 && $total_kriteria >= 3 && $total_assessed >= 3);
                    ?>

                    <div class="prerequisites-summary">
                        <?php if ($can_calculate): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i>
                                <strong>Siap untuk perhitungan!</strong> Semua prasyarat telah terpenuhi.
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                <strong>Belum siap!</strong> Lengkapi data yang diperlukan terlebih dahulu.
                                <?php if ($total_kwarcan == 0): ?>
                                    <br>• Tambahkan data Kwarcan
                                <?php endif; ?>
                                <?php if ($total_kriteria < 3): ?>
                                    <br>• Tambahkan minimal 3 kriteria penilaian
                                <?php endif; ?>
                                <?php if ($total_assessed < 3): ?>
                                    <br>• Input penilaian untuk minimal 3 Kwarcan
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- MOORA Steps Explanation -->
            <div class="steps-card">
                <div class="card-header">
                    <h3><i class="fas fa-route"></i> Langkah-langkah Metode MOORA</h3>
                </div>
                <div class="card-content">
                    <div class="steps-timeline">
                        <div class="step-item">
                            <div class="step-number">1</div>
                            <div class="step-content">
                                <h4>Matriks Keputusan</h4>
                                <p>Menyusun matriks keputusan dari data penilaian yang telah diinput</p>
                            </div>
                        </div>

                        <div class="step-item">
                            <div class="step-number">2</div>
                            <div class="step-content">
                                <h4>Normalisasi Matriks</h4>
                                <p>Menormalisasi matriks menggunakan rumus: x<sub>ij</sub> / √(Σx<sub>ij</sub>²)</p>
                            </div>
                        </div>

                        <div class="step-item">
                            <div class="step-number">3</div>
                            <div class="step-content">
                                <h4>Pembobotan</h4>
                                <p>Mengalikan nilai normalisasi dengan bobot masing-masing kriteria</p>
                            </div>
                        </div>

                        <div class="step-item">
                            <div class="step-number">4</div>
                            <div class="step-content">
                                <h4>Optimasi</h4>
                                <p>Menghitung nilai optimasi: Σ(benefit) - Σ(cost)</p>
                            </div>
                        </div>

                        <div class="step-item">
                            <div class="step-number">5</div>
                            <div class="step-content">
                                <h4>Perangkingan</h4>
                                <p>Mengurutkan alternatif berdasarkan nilai optimasi tertinggi</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Calculation Form -->
            <div class="calculation-form-card">
                <div class="card-header">
                    <h3><i class="fas fa-play"></i> Jalankan Perhitungan MOORA</h3>
                </div>
                <div class="card-content">
                    <form method="POST" id="calculationForm">
                        <input type="hidden" name="action" value="calculate">
                        
                        <div class="calculation-options">
                            <div class="option-group">
                                <label>
                                    <input type="checkbox" name="show_steps" value="1" checked>
                                    <span class="checkmark"></span>
                                    Tampilkan langkah-langkah perhitungan
                                </label>
                            </div>
                            
                            <div class="option-group">
                                <label>
                                    <input type="checkbox" name="save_results" value="1" checked>
                                    <span class="checkmark"></span>
                                    Simpan hasil ke database
                                </label>
                            </div>
                        </div>

                        <div class="calculation-actions">
                            <button type="submit" class="btn btn-primary btn-lg" 
                                    <?= !$can_calculate ? 'disabled' : '' ?> id="calculateBtn">
                                <i class="fas fa-calculator"></i>
                                Hitung MOORA
                            </button>
                            
                            <?php if ($has_results): ?>
                                <a href="hasil-moora.php" class="btn btn-success btn-lg">
                                    <i class="fas fa-trophy"></i>
                                    Lihat Hasil Terakhir
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Calculation Results -->
            <?php if ($calculation_results): ?>
                <div class="results-container" id="calculationResults">
                    <!-- Original Matrix -->
                    <div class="matrix-card">
                        <div class="card-header">
                            <h3><i class="fas fa-table"></i> 1. Matriks Keputusan Awal</h3>
                            <button class="btn btn-sm btn-secondary" onclick="exportMatrix('original')">
                                <i class="fas fa-download"></i> Export
                            </button>
                        </div>
                        <div class="card-content">
                            <div class="table-responsive">
                                <table class="matrix-table">
                                    <thead>
                                        <tr>
                                            <th>Kwarcan</th>
                                            <?php foreach ($calculation_results['kriteria_info'] as $kriteria): ?>
                                                <th><?= htmlspecialchars($kriteria['nama']) ?><br>
                                                    <small>(<?= $kriteria['jenis'] ?>, <?= $kriteria['bobot'] ?>)</small>
                                                </th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($calculation_results['original_matrix'] as $kwarcan_id => $values): ?>
                                            <tr>
                                                <td class="kwarcan-name">
                                                    <strong><?= htmlspecialchars($calculation_results['kwarcan_info'][$kwarcan_id]['kode']) ?></strong><br>
                                                    <small><?= htmlspecialchars($calculation_results['kwarcan_info'][$kwarcan_id]['nama']) ?></small>
                                                </td>
                                                <?php foreach ($values as $value): ?>
                                                    <td><?= number_format($value, 2) ?></td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Normalized Matrix -->
                    <div class="matrix-card">
                        <div class="card-header">
                            <h3><i class="fas fa-balance-scale"></i> 2. Matriks Normalisasi</h3>
                            <button class="btn btn-sm btn-secondary" onclick="exportMatrix('normalized')">
                                <i class="fas fa-download"></i> Export
                            </button>
                        </div>
                        <div class="card-content">
                            <div class="normalization-formula">
                                <p><strong>Rumus Normalisasi:</strong> x<sub>ij</sub> / √(Σx<sub>ij</sub>²)</p>
                            </div>
                            <div class="table-responsive">
                                <table class="matrix-table">
                                    <thead>
                                        <tr>
                                            <th>Kwarcan</th>
                                            <?php foreach ($calculation_results['kriteria_info'] as $kriteria): ?>
                                                <th><?= htmlspecialchars($kriteria['nama']) ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($calculation_results['normalized_matrix'] as $kwarcan_id => $values): ?>
                                            <tr>
                                                <td class="kwarcan-name">
                                                    <strong><?= htmlspecialchars($calculation_results['kwarcan_info'][$kwarcan_id]['kode']) ?></strong>
                                                </td>
                                                <?php foreach ($values as $value): ?>
                                                    <td><?= number_format($value, 4) ?></td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Weighted Matrix -->
                    <div class="matrix-card">
                        <div class="card-header">
                            <h3><i class="fas fa-weight"></i> 3. Matriks Terbobot</h3>
                            <button class="btn btn-sm btn-secondary" onclick="exportMatrix('weighted')">
                                <i class="fas fa-download"></i> Export
                            </button>
                        </div>
                        <div class="card-content">
                            <div class="table-responsive">
                                <table class="matrix-table">
                                    <thead>
                                        <tr>
                                            <th>Kwarcan</th>
                                            <?php foreach ($calculation_results['kriteria_info'] as $kriteria): ?>
                                                <th><?= htmlspecialchars($kriteria['nama']) ?><br>
                                                    <small>Bobot: <?= $kriteria['bobot'] ?></small>
                                                </th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($calculation_results['weighted_matrix'] as $kwarcan_id => $values): ?>
                                            <tr>
                                                <td class="kwarcan-name">
                                                    <strong><?= htmlspecialchars($calculation_results['kwarcan_info'][$kwarcan_id]['kode']) ?></strong>
                                                </td>
                                                <?php foreach ($values as $value): ?>
                                                    <td><?= number_format($value, 4) ?></td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Optimization Values -->
                    <div class="optimization-card">
                        <div class="card-header">
                            <h3><i class="fas fa-chart-line"></i> 4. Nilai Optimasi</h3>
                        </div>
                        <div class="card-content">
                            <div class="optimization-formula">
                                <p><strong>Rumus Optimasi:</strong> Y<sub>i</sub> = Σ(Benefit) - Σ(Cost)</p>
                            </div>
                            <div class="optimization-table">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Kwarcan</th>
                                            <th>Benefit Sum</th>
                                            <th>Cost Sum</th>
                                            <th>Nilai Optimasi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($calculation_results['optimization_values'] as $kwarcan_id => $nilai_optimasi): ?>
                                            <?php
                                            // Calculate benefit and cost sums
                                            $benefit_sum = 0;
                                            $cost_sum = 0;
                                            foreach ($calculation_results['weighted_matrix'][$kwarcan_id] as $kriteria_id => $value) {
                                                if ($calculation_results['kriteria_info'][$kriteria_id]['jenis'] == 'benefit') {
                                                    $benefit_sum += $value;
                                                } else {
                                                    $cost_sum += $value;
                                                }
                                            }
                                            ?>
                                            <tr>
                                                <td class="kwarcan-name">
                                                    <strong><?= htmlspecialchars($calculation_results['kwarcan_info'][$kwarcan_id]['kode']) ?></strong><br>
                                                    <small><?= htmlspecialchars($calculation_results['kwarcan_info'][$kwarcan_id]['nama']) ?></small>
                                                </td>
                                                <td class="benefit-value"><?= number_format($benefit_sum, 4) ?></td>
                                                <td class="cost-value"><?= number_format($cost_sum, 4) ?></td>
                                                <td class="optimization-value">
                                                    <strong><?= number_format($nilai_optimasi, 4) ?></strong>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Final Ranking -->
                    <div class="ranking-card">
                        <div class="card-header">
                            <h3><i class="fas fa-trophy"></i> 5. Hasil Perangkingan</h3>
                            <div class="card-actions">
                                <button class="btn btn-success" onclick="saveResults()">
                                    <i class="fas fa-save"></i> Simpan Hasil
                                </button>
                                <button class="btn btn-info" onclick="exportRanking()">
                                    <i class="fas fa-download"></i> Export
                                </button>
                            </div>
                        </div>
                        <div class="card-content">
                            <div class="ranking-grid">
                                <?php foreach ($calculation_results['ranking'] as $index => $result): ?>
                                    <div class="ranking-item rank-<?= $result['ranking'] ?>">
                                        <div class="ranking-position">
                                                                                        <span class="rank-number"><?= $result['ranking'] ?></span>
                                            <?php if ($result['ranking'] <= 3): ?>
                                                <i class="fas fa-<?= $result['ranking'] == 1 ? 'crown' : ($result['ranking'] == 2 ? 'medal' : 'award') ?>"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="ranking-info">
                                            <h4><?= htmlspecialchars($result['nama']) ?></h4>
                                            <p><?= htmlspecialchars($result['daerah']) ?></p>
                                            <div class="ranking-score">
                                                <span>Nilai: <?= number_format($result['nilai_optimasi'], 4) ?></span>
                                            </div>
                                        </div>
                                        <div class="ranking-badge">
                                            <?php if ($result['ranking'] == 1): ?>
                                                <span class="badge badge-gold">Terbaik</span>
                                            <?php elseif ($result['ranking'] <= 3): ?>
                                                <span class="badge badge-silver">Top 3</span>
                                            <?php elseif ($result['ranking'] <= 5): ?>
                                                <span class="badge badge-bronze">Top 5</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Save Results Form -->
                    <form method="POST" id="saveResultsForm" style="display: none;">
                        <input type="hidden" name="action" value="save_results">
                        <input type="hidden" name="results_data" id="resultsData">
                    </form>
                </div>
            <?php endif; ?>
        </div>
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

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 25px;
        margin-bottom: 40px;
    }

    .stat-card {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(20px);
        border-radius: 20px;
        padding: 30px;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(135deg, #667eea, #764ba2);
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
        margin-bottom: 20px;
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
    }

    .stat-icon i {
        font-size: 1.8rem;
        color: #667eea;
    }

    .stat-content h3 {
        font-size: 2.5rem;
        color: #333;
        margin-bottom: 8px;
        font-weight: 700;
    }

    .stat-content p {
        color: #666;
        font-size: 1rem;
        margin-bottom: 15px;
    }

    .stat-trend {
        display: flex;
        align-items: center;
        gap: 8px;
        color: #28a745;
        font-size: 0.9rem;
        font-weight: 600;
    }

    .calculation-container {
        display: flex;
        flex-direction: column;
        gap: 30px;
    }

    .prerequisites-card, .steps-card, .calculation-form-card, .matrix-card, 
    .optimization-card, .ranking-card {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(20px);
        border-radius: 20px;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        overflow: hidden;
    }

    .card-header {
        padding: 25px 30px 20px;
        border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }

    .card-header h3 {
        color: #333;
        font-size: 1.3rem;
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 0;
    }

    .card-content {
        padding: 25px 30px 30px;
    }

    .prerequisites-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
        margin-bottom: 25px;
    }

    .prerequisite-item {
        display: flex;
        align-items: center;
        padding: 20px;
        border-radius: 15px;
        transition: all 0.3s ease;
    }

    .prerequisite-item.complete {
        background: rgba(40, 167, 69, 0.1);
        border: 2px solid rgba(40, 167, 69, 0.2);
    }

    .prerequisite-item.incomplete {
        background: rgba(255, 193, 7, 0.1);
        border: 2px solid rgba(255, 193, 7, 0.2);
    }

    .prerequisite-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 15px;
        background: rgba(102, 126, 234, 0.1);
    }

    .prerequisite-icon i {
        font-size: 1.5rem;
        color: #667eea;
    }

    .prerequisite-content {
        flex: 1;
    }

    .prerequisite-content h4 {
        color: #333;
        font-size: 1.1rem;
        margin-bottom: 5px;
    }

    .prerequisite-content p {
        color: #666;
        font-size: 0.9rem;
        margin: 0;
    }

    .prerequisite-status i {
        font-size: 1.5rem;
    }

    .prerequisite-item.complete .prerequisite-status i {
        color: #28a745;
    }

    .prerequisite-item.incomplete .prerequisite-status i {
        color: #ffc107;
    }

    .steps-timeline {
        display: flex;
        flex-direction: column;
        gap: 25px;
    }

    .step-item {
        display: flex;
        align-items: flex-start;
        gap: 20px;
    }

    .step-number {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 1.1rem;
        flex-shrink: 0;
    }

    .step-content h4 {
        color: #333;
        font-size: 1.1rem;
        margin-bottom: 8px;
    }

    .step-content p {
        color: #666;
        font-size: 0.95rem;
        line-height: 1.5;
        margin: 0;
    }

    .calculation-options {
        display: flex;
        flex-direction: column;
        gap: 15px;
        margin-bottom: 30px;
    }

    .option-group {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .option-group label {
        display: flex;
        align-items: center;
        gap: 12px;
        cursor: pointer;
        font-size: 1rem;
        color: #333;
    }

    .option-group input[type="checkbox"] {
        width: 20px;
        height: 20px;
        accent-color: #667eea;
    }

    .calculation-actions {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
    }

    .btn {
        padding: 12px 24px;
        border: none;
        border-radius: 12px;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 10px;
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

    .btn-secondary {
        background: linear-gradient(135deg, #6c757d, #495057);
        color: white;
        box-shadow: 0 8px 25px rgba(108, 117, 125, 0.3);
    }

    .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 35px rgba(0, 0, 0, 0.2);
    }

    .btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }

    .table-responsive {
        overflow-x: auto;
        margin: 20px 0;
    }

    .matrix-table, .table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.9rem;
    }

    .matrix-table th, .matrix-table td,
    .table th, .table td {
        padding: 12px 8px;
        text-align: center;
        border: 1px solid rgba(0, 0, 0, 0.1);
    }

    .matrix-table th, .table th {
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
        font-weight: 600;
        color: #333;
    }

    .kwarcan-name {
        text-align: left !important;
        min-width: 150px;
    }

    .kwarcan-name strong {
        color: #333;
        font-size: 1rem;
    }

    .kwarcan-name small {
        color: #666;
        font-size: 0.8rem;
    }

    .normalization-formula, .optimization-formula {
        background: rgba(102, 126, 234, 0.1);
        padding: 15px;
        border-radius: 10px;
        margin-bottom: 20px;
        text-align: center;
    }

    .benefit-value {
        color: #28a745;
        font-weight: 600;
    }

    .cost-value {
        color: #dc3545;
        font-weight: 600;
    }

    .optimization-value {
        background: rgba(102, 126, 234, 0.1);
        font-weight: 700;
        color: #667eea;
    }

    .ranking-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
    }

    .ranking-item {
        display: flex;
        align-items: center;
        padding: 20px;
        border-radius: 15px;
        background: rgba(255, 255, 255, 0.5);
        border: 2px solid rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
    }

    .ranking-item:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    }

    .ranking-item.rank-1 {
        background: linear-gradient(135deg, rgba(255, 215, 0, 0.2), rgba(255, 223, 0, 0.1));
        border-color: #ffd700;
    }

    .ranking-item.rank-2 {
        background: linear-gradient(135deg, rgba(192, 192, 192, 0.2), rgba(192, 192, 192, 0.1));
        border-color: #c0c0c0;
    }

    .ranking-item.rank-3 {
                background: linear-gradient(135deg, rgba(205, 127, 50, 0.2), rgba(205, 127, 50, 0.1));
        border-color: #cd7f32;
    }

    .ranking-position {
        display: flex;
        flex-direction: column;
        align-items: center;
        margin-right: 20px;
    }

    .rank-number {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        font-weight: 700;
        color: white;
        background: linear-gradient(135deg, #667eea, #764ba2);
        margin-bottom: 8px;
    }

    .rank-1 .rank-number {
        background: linear-gradient(135deg, #ffd700, #ffed4e);
        color: #333;
    }

    .rank-2 .rank-number {
        background: linear-gradient(135deg, #c0c0c0, #e5e5e5);
        color: #333;
    }

    .rank-3 .rank-number {
        background: linear-gradient(135deg, #cd7f32, #daa520);
    }

    .ranking-position i {
        font-size: 1rem;
        color: #ffd700;
    }

    .ranking-info {
        flex: 1;
    }

    .ranking-info h4 {
        color: #333;
        font-size: 1.1rem;
        margin-bottom: 5px;
    }

    .ranking-info p {
        color: #666;
        font-size: 0.9rem;
        margin-bottom: 8px;
    }

    .ranking-score span {
        background: rgba(102, 126, 234, 0.1);
        color: #667eea;
        padding: 4px 8px;
        border-radius: 8px;
        font-size: 0.85rem;
        font-weight: 600;
    }

    .ranking-badge {
        margin-left: 15px;
    }

    .badge {
        padding: 6px 12px;
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

    .alert-warning {
        background: rgba(255, 193, 7, 0.1);
        border: 1px solid rgba(255, 193, 7, 0.2);
        color: #856404;
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

        .stats-grid {
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .prerequisites-grid {
            grid-template-columns: 1fr;
        }

        .calculation-actions {
            flex-direction: column;
        }

        .ranking-grid {
            grid-template-columns: 1fr;
        }

        .matrix-table, .table {
            font-size: 0.8rem;
        }

        .matrix-table th, .matrix-table td,
        .table th, .table td {
            padding: 8px 4px;
        }
    }
</style>

<script>
    // Form submission with loading state
    document.getElementById('calculationForm').addEventListener('submit', function(e) {
        const btn = document.getElementById('calculateBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menghitung...';
        
        // Show progress indicator
        showCalculationProgress();
    });

    // Show calculation progress
    function showCalculationProgress() {
        const progressHtml = `
            <div class="calculation-progress" id="calculationProgress">
                <div class="progress-card">
                    <div class="card-header">
                        <h3><i class="fas fa-cog fa-spin"></i> Sedang Menghitung...</h3>
                    </div>
                    <div class="card-content">
                        <div class="progress-steps">
                            <div class="progress-step active">
                                <div class="step-icon"><i class="fas fa-database"></i></div>
                                <div class="step-text">Mengambil Data</div>
                            </div>
                            <div class="progress-step active">
                                <div class="step-icon"><i class="fas fa-table"></i></div>
                                <div class="step-text">Menyusun Matriks</div>
                            </div>
                            <div class="progress-step active">
                                <div class="step-icon"><i class="fas fa-balance-scale"></i></div>
                                <div class="step-text">Normalisasi</div>
                            </div>
                            <div class="progress-step">
                                <div class="step-icon"><i class="fas fa-weight"></i></div>
                                <div class="step-text">Pembobotan</div>
                            </div>
                            <div class="progress-step">
                                <div class="step-icon"><i class="fas fa-chart-line"></i></div>
                                <div class="step-text">Optimasi</div>
                            </div>
                            <div class="progress-step">
                                <div class="step-icon"><i class="fas fa-trophy"></i></div>
                                <div class="step-text">Perangkingan</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.querySelector('.calculation-container').insertAdjacentHTML('beforeend', progressHtml);
    }

    // Save results function
    function saveResults() {
        const resultsData = <?= $calculation_results ? json_encode($calculation_results) : 'null' ?>;
        
        if (!resultsData) {
            alert('Tidak ada data hasil untuk disimpan');
            return;
        }
        
        document.getElementById('resultsData').value = JSON.stringify(resultsData);
        document.getElementById('saveResultsForm').submit();
    }

    // Export functions
    function exportMatrix(type) {
        const resultsData = <?= $calculation_results ? json_encode($calculation_results) : 'null' ?>;
        
        if (!resultsData) {
            alert('Tidak ada data untuk diekspor');
            return;
        }
        
        // Create CSV content
        let csvContent = '';
        let matrix = resultsData[type + '_matrix'];
        let headers = ['Kwarcan'];
        
        // Add criteria headers
        Object.values(resultsData.kriteria_info).forEach(kriteria => {
            headers.push(kriteria.nama);
        });
        
        csvContent += headers.join(',') + '\n';
        
        // Add data rows
        Object.entries(matrix).forEach(([kwarcanId, values]) => {
            let row = [resultsData.kwarcan_info[kwarcanId].nama];
            Object.values(values).forEach(value => {
                row.push(value.toFixed(4));
            });
            csvContent += row.join(',') + '\n';
        });
        
        // Download CSV
        downloadCSV(csvContent, `matrix_${type}_${new Date().toISOString().split('T')[0]}.csv`);
    }

    function exportRanking() {
        const resultsData = <?= $calculation_results ? json_encode($calculation_results) : 'null' ?>;
        
        if (!resultsData) {
            alert('Tidak ada data untuk diekspor');
            return;
        }
        
        let csvContent = 'Ranking,Kode,Nama Kwarcan,Daerah,Nilai Optimasi\n';
        
        resultsData.ranking.forEach(item => {
            csvContent += `${item.ranking},${item.kode},"${item.nama}","${item.daerah}",${item.nilai_optimasi.toFixed(4)}\n`;
        });
        
        downloadCSV(csvContent, `ranking_moora_${new Date().toISOString().split('T')[0]}.csv`);
    }

    function downloadCSV(content, filename) {
        const blob = new Blob([content], { type: 'text/csv;charset=utf-8;' });
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

    // Auto-scroll to results
    <?php if ($calculation_results): ?>
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(() => {
                document.getElementById('calculationResults').scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }, 500);
        });
    <?php endif; ?>

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey) {
            switch(e.key) {
                case 'Enter':
                    e.preventDefault();
                    if (!document.getElementById('calculateBtn').disabled) {
                        document.getElementById('calculationForm').submit();
                    }
                    break;
                case 's':
                    e.preventDefault();
                    if (<?= $calculation_results ? 'true' : 'false' ?>) {
                        saveResults();
                    }
                    break;
            }
        }
    });
</script>

<?php include '../includes/footer.php'; ?>
