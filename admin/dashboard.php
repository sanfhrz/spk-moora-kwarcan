<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

require_once '../config/database.php';

// Get statistics
try {
    // Total Kwarcan
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM kwarcan WHERE status = 'aktif'");
    $total_kwarcan = $stmt->fetch()['total'];
    
    // Total Kriteria
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM kriteria");
    $total_kriteria = $stmt->fetch()['total'];
    
    // Total Penilaian (unique kwarcan yang sudah dinilai)
    $stmt = $pdo->query("SELECT COUNT(DISTINCT kwarcan_id) as total FROM penilaian");
    $total_penilaian = $stmt->fetch()['total'];
    
    // Total Hasil MOORA
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM hasil_moora");
    $total_hasil = $stmt->fetch()['total'];
    
    // Progress penilaian (persentase kwarcan yang sudah dinilai)
    $progress_penilaian = $total_kwarcan > 0 ? round(($total_penilaian / $total_kwarcan) * 100, 1) : 0;
    
    // Get recent activities (5 kwarcan terakhir yang dinilai)
    $stmt = $pdo->query("
        SELECT k.nama_kwarcan, k.daerah, p.created_at, COUNT(p.kriteria_id) as jumlah_kriteria
        FROM kwarcan k 
        JOIN penilaian p ON k.id = p.kwarcan_id 
        GROUP BY k.id, k.nama_kwarcan, k.daerah, p.created_at
        ORDER BY p.created_at DESC 
        LIMIT 5
    ");
    $recent_activities = $stmt->fetchAll();
    
    // Get top 5 ranking (jika sudah ada hasil MOORA)
    $stmt = $pdo->query("
        SELECT k.nama_kwarcan, k.daerah, h.nilai_optimasi, h.ranking
        FROM hasil_moora h
        JOIN kwarcan k ON h.kwarcan_id = k.id
        ORDER BY h.ranking ASC
        LIMIT 5
    ");
    $top_ranking = $stmt->fetchAll();
    
    // Get kriteria dengan bobot
    $stmt = $pdo->query("SELECT nama_kriteria, bobot, jenis FROM kriteria ORDER BY bobot DESC");
    $kriteria_list = $stmt->fetchAll();
    
    // Chart data untuk penilaian per kriteria
    $stmt = $pdo->query("
        SELECT kr.nama_kriteria, AVG(p.nilai) as rata_nilai, COUNT(p.nilai) as jumlah_penilaian
        FROM kriteria kr
        LEFT JOIN penilaian p ON kr.id = p.kriteria_id
        GROUP BY kr.id, kr.nama_kriteria
        ORDER BY kr.id
    ");
    $chart_data = $stmt->fetchAll();
    
} catch (Exception $e) {
    $total_kwarcan = $total_kriteria = $total_penilaian = $total_hasil = 0;
    $progress_penilaian = 0;
    $recent_activities = [];
    $top_ranking = [];
    $kriteria_list = [];
    $chart_data = [];
}

$page_title = "Dashboard - SPK MOORA Kwarcan";
?>

<?php include '../includes/header.php'; ?>

<div class="dashboard-container">
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="content">
        <!-- Header -->
        <div class="content-header">
            <div class="header-title">
                <h1><i class="fas fa-tachometer-alt"></i> Dashboard</h1>
                <p>Selamat datang, <?= htmlspecialchars($_SESSION['admin_nama']) ?>! Kelola sistem SPK MOORA untuk pemilihan Kwarcan terbaik.</p>
            </div>
            <div class="header-actions">
                <div class="datetime">
                    <i class="fas fa-calendar-alt"></i>
                    <span id="current-datetime"></span>
                </div>
            </div>
        </div>

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
                        <i class="fas fa-arrow-up"></i>
                        <span>Aktif</span>
                    </div>
                </div>
                <div class="stat-progress">
                    <div class="progress-bar" style="width: 100%"></div>
                </div>
            </div>

            <div class="stat-card success">
                <div class="stat-icon">
                    <i class="fas fa-list-check"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $total_kriteria ?></h3>
                    <p>Kriteria Penilaian</p>
                    <div class="stat-trend">
                        <i class="fas fa-check-circle"></i>
                        <span>Siap</span>
                    </div>
                </div>
                <div class="stat-progress">
                    <div class="progress-bar" style="width: 100%"></div>
                </div>
            </div>

            <div class="stat-card warning">
                <div class="stat-icon">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $total_penilaian ?></h3>
                    <p>Sudah Dinilai</p>
                    <div class="stat-trend">
                        <i class="fas fa-percentage"></i>
                        <span><?= $progress_penilaian ?>%</span>
                    </div>
                </div>
                <div class="stat-progress">
                    <div class="progress-bar" style="width: <?= $progress_penilaian ?>%"></div>
                </div>
            </div>

            <div class="stat-card info">
                <div class="stat-icon">
                    <i class="fas fa-trophy"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $total_hasil ?></h3>
                    <p>Hasil MOORA</p>
                    <div class="stat-trend">
                        <i class="fas fa-calculator"></i>
                        <span>Tersedia</span>
                    </div>
                </div>
                <div class="stat-progress">
                    <div class="progress-bar" style="width: <?= $total_hasil > 0 ? '100' : '0' ?>%"></div>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="dashboard-grid">
            <!-- Chart Section -->
            <div class="dashboard-card chart-card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-bar"></i> Rata-rata Penilaian per Kriteria</h3>
                    <div class="card-actions">
                        <button class="btn-icon" onclick="refreshChart()">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                </div>
                <div class="card-content">
                    <div class="chart-container">
                        <canvas id="criteriaChart"></canvas>
                    </div>
                    <?php if (empty($chart_data)): ?>
                        <div class="empty-state">
                            <i class="fas fa-chart-bar"></i>
                            <h4>Belum Ada Data</h4>
                            <p>Mulai input penilaian untuk melihat grafik</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Progress Section -->
            <div class="dashboard-card progress-card">
                <div class="card-header">
                    <h3><i class="fas fa-tasks"></i> Progress Sistem</h3>
                </div>
                <div class="card-content">
                    <div class="progress-item">
                        <div class="progress-info">
                            <span>Setup Kriteria</span>
                            <span><?= $total_kriteria ?>/5</span>
                        </div>
                        <div class="progress-bar-container">
                            <div class="progress-bar" style="width: <?= min(($total_kriteria/5)*100, 100) ?>%"></div>
                        </div>
                    </div>

                    <div class="progress-item">
                        <div class="progress-info">
                            <span>Input Penilaian</span>
                            <span><?= $total_penilaian ?>/<?= $total_kwarcan ?></span>
                        </div>
                        <div class="progress-bar-container">
                            <div class="progress-bar" style="width: <?= $progress_penilaian ?>%"></div>
                        </div>
                    </div>

                    <div class="progress-item">
                        <div class="progress-info">
                            <span>Perhitungan MOORA</span>
                            <span><?= $total_hasil > 0 ? 'Selesai' : 'Belum' ?></span>
                        </div>
                        <div class="progress-bar-container">
                            <div class="progress-bar" style="width: <?= $total_hasil > 0 ? '100' : '0' ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activities -->
            <div class="dashboard-card activity-card">
                <div class="card-header">
                    <h3><i class="fas fa-clock"></i> Aktivitas Terbaru</h3>
                    <a href="penilaian.php" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus"></i> Input Penilaian
                    </a>
                </div>
                <div class="card-content">
                    <?php if (!empty($recent_activities)): ?>
                        <div class="activity-list">
                            <?php foreach ($recent_activities as $activity): ?>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <i class="fas fa-star"></i>
                                    </div>
                                    <div class="activity-content">
                                        <h4><?= htmlspecialchars($activity['nama_kwarcan']) ?></h4>
                                        <p><?= htmlspecialchars($activity['daerah']) ?></p>
                                        <small><?= $activity['jumlah_kriteria'] ?> kriteria dinilai</small>
                                    </div>
                                    <div class="activity-time">
                                        <small><?= date('d/m/Y H:i', strtotime($activity['created_at'])) ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-clipboard-list"></i>
                            <h4>Belum Ada Aktivitas</h4>
                            <p>Mulai input penilaian untuk melihat aktivitas</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Top Ranking -->
            <div class="dashboard-card ranking-card">
                <div class="card-header">
                    <h3><i class="fas fa-trophy"></i> Top 5 Ranking</h3>
                    <a href="hasil-moora.php" class="btn btn-sm btn-success">
                        <i class="fas fa-eye"></i> Lihat Semua
                    </a>
                </div>
                <div class="card-content">
                    <?php if (!empty($top_ranking)): ?>
                        <div class="ranking-list">
                            <?php foreach ($top_ranking as $index => $rank): ?>
                                <div class="ranking-item">
                                    <div class="ranking-position">
                                        <span class="rank-number rank-<?= $index + 1 ?>"><?= $rank['ranking'] ?></span>
                                    </div>
                                    <div class="ranking-content">
                                        <h4><?= htmlspecialchars($rank['nama_kwarcan']) ?></h4>
                                        <p><?= htmlspecialchars($rank['daerah']) ?></p>
                                    </div>
                                    <div class="ranking-score">
                                        <span><?= number_format($rank['nilai_optimasi'], 4) ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-calculator"></i>
                            <h4>Belum Ada Hasil</h4>
                            <p>Jalankan perhitungan MOORA untuk melihat ranking</p>
                            <a href="hitung-moora.php" class="btn btn-primary">
                                <i class="fas fa-play"></i> Hitung Sekarang
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Kriteria Overview -->
            <div class="dashboard-card criteria-card">
                <div class="card-header">
                    <h3><i class="fas fa-list"></i> Kriteria Penilaian</h3>
                    <a href="kriteria.php" class="btn btn-sm btn-info">
                        <i class="fas fa-cog"></i> Kelola
                    </a>
                </div>
                <div class="card-content">
                    <?php if (!empty($kriteria_list)): ?>
                        <div class="criteria-list">
                            <?php foreach ($kriteria_list as $kriteria): ?>
                                <div class="criteria-item">
                                    <div class="criteria-info">
                                        <h4><?= htmlspecialchars($kriteria['nama_kriteria']) ?></h4>
                                        <span class="criteria-type <?= $kriteria['jenis'] ?>">
                                            <?= ucfirst($kriteria['jenis']) ?>
                                        </span>
                                    </div>
                                    <div class="criteria-weight">
                                        <span><?= $kriteria['bobot'] ?></span>
                                        <div class="weight-bar">
                                                                                        <div class="weight-fill" style="width: <?= $kriteria['bobot'] * 100 ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-plus-circle"></i>
                            <h4>Belum Ada Kriteria</h4>
                            <p>Tambahkan kriteria penilaian terlebih dahulu</p>
                            <a href="kriteria.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Tambah Kriteria
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="dashboard-card actions-card">
                <div class="card-header">
                    <h3><i class="fas fa-bolt"></i> Aksi Cepat</h3>
                </div>
                <div class="card-content">
                    <div class="quick-actions">
                        <a href="kwarcan.php" class="action-btn">
                            <i class="fas fa-users"></i>
                            <span>Kelola Kwarcan</span>
                        </a>
                        <a href="penilaian.php" class="action-btn">
                            <i class="fas fa-star"></i>
                            <span>Input Penilaian</span>
                        </a>
                        <a href="hitung-moora.php" class="action-btn">
                            <i class="fas fa-calculator"></i>
                            <span>Hitung MOORA</span>
                        </a>
                        <a href="laporan.php" class="action-btn">
                            <i class="fas fa-file-alt"></i>
                            <span>Cetak Laporan</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
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

    .datetime {
        background: rgba(255, 255, 255, 0.9);
        padding: 12px 20px;
        border-radius: 15px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        display: flex;
        align-items: center;
        gap: 10px;
        color: #333;
        font-weight: 600;
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

    .stat-card.primary::before {
        background: linear-gradient(135deg, #667eea, #764ba2);
    }

    .stat-card.success::before {
        background: linear-gradient(135deg, #28a745, #20c997);
    }

    .stat-card.warning::before {
        background: linear-gradient(135deg, #ffc107, #fd7e14);
    }

    .stat-card.info::before {
        background: linear-gradient(135deg, #17a2b8, #6f42c1);
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

    .stat-progress {
        margin-top: 20px;
        height: 4px;
        background: rgba(0, 0, 0, 0.1);
        border-radius: 2px;
        overflow: hidden;
    }

    .progress-bar {
        height: 100%;
        background: linear-gradient(135deg, #667eea, #764ba2);
        border-radius: 2px;
        transition: width 0.3s ease;
    }

    .dashboard-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 25px;
    }

    .dashboard-card {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(20px);
        border-radius: 20px;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
        overflow: hidden;
    }

    .dashboard-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
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

    .chart-container {
        height: 300px;
        position: relative;
    }

    .progress-item {
        margin-bottom: 20px;
    }

    .progress-info {
        display: flex;
        justify-content: space-between;
        margin-bottom: 8px;
        font-size: 0.9rem;
        color: #666;
    }

    .progress-bar-container {
        height: 8px;
        background: rgba(0, 0, 0, 0.1);
        border-radius: 4px;
        overflow: hidden;
    }

    .activity-list, .ranking-list, .criteria-list {
        max-height: 350px;
        overflow-y: auto;
    }

    .activity-item, .ranking-item, .criteria-item {
        display: flex;
        align-items: center;
        padding: 15px 0;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        transition: all 0.3s ease;
    }

    .activity-item:hover, .ranking-item:hover, .criteria-item:hover {
        background: rgba(102, 126, 234, 0.05);
        border-radius: 10px;
        padding-left: 10px;
        padding-right: 10px;
    }

    .activity-icon, .ranking-position {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 15px;
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
    }

    .activity-content, .ranking-content, .criteria-info {
        flex: 1;
    }

    .activity-content h4, .ranking-content h4, .criteria-info h4 {
        color: #333;
        font-size: 1rem;
        margin-bottom: 4px;
    }

    .activity-content p, .ranking-content p {
        color: #666;
        font-size: 0.85rem;
        margin-bottom: 2px;
    }

    .activity-time, .ranking-score {
        font-size: 0.8rem;
        color: #999;
    }

    .rank-number {
        font-weight: 700;
        font-size: 1.2rem;
        color: white;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .rank-1 { background: linear-gradient(135deg, #ffd700, #ffed4e); }
    .rank-2 { background: linear-gradient(135deg, #c0c0c0, #e5e5e5); }
    .rank-3 { background: linear-gradient(135deg, #cd7f32, #daa520); }
    .rank-4, .rank-5 { background: linear-gradient(135deg, #667eea, #764ba2); }

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
        text-align: right;
    }

    .criteria-weight span {
        font-weight: 700;
        color: #333;
    }

    .weight-bar {
        width: 60px;
        height: 4px;
        background: rgba(0, 0, 0, 0.1);
        border-radius: 2px;
        margin-top: 5px;
        overflow: hidden;
    }

    .weight-fill {
        height: 100%;
        background: linear-gradient(135deg, #667eea, #764ba2);
        border-radius: 2px;
    }

    .quick-actions {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
    }

    .action-btn {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 20px 15px;
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
        border-radius: 15px;
        text-decoration: none;
        color: #333;
        transition: all 0.3s ease;
        border: 2px solid transparent;
    }

    .action-btn:hover {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        transform: translateY(-3px);
        box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
    }

    .action-btn i {
        font-size: 1.5rem;
        margin-bottom: 8px;
    }

    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #999;
    }

    .empty-state i {
        font-size: 3rem;
        color: #ddd;
        margin-bottom: 15px;
    }

    .empty-state h4 {
        color: #666;
        margin-bottom: 8px;
    }

    .empty-state p {
        color: #999;
        font-size: 0.9rem;
        margin-bottom: 20px;
    }

    .btn {
        padding: 8px 16px;
        border: none;
        border-radius: 10px;
        font-size: 0.9rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
    }

    .btn-sm {
        padding: 6px 12px;
        font-size: 0.8rem;
    }

    .btn-primary {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
    }

    .btn-success {
        background: linear-gradient(135deg, #28a745, #20c997);
        color: white;
                box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
    }

    .btn-info {
        background: linear-gradient(135deg, #17a2b8, #6f42c1);
        color: white;
        box-shadow: 0 4px 15px rgba(23, 162, 184, 0.3);
    }

    .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
    }

    .btn-icon {
        width: 35px;
        height: 35px;
        border: none;
        border-radius: 8px;
        background: rgba(102, 126, 234, 0.1);
        color: #667eea;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .btn-icon:hover {
        background: #667eea;
        color: white;
        transform: scale(1.1);
    }

    /* Responsive Design */
    @media (max-width: 1200px) {
        .dashboard-grid {
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
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

        .stats-grid {
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .dashboard-grid {
            grid-template-columns: 1fr;
            gap: 20px;
        }

        .stat-card, .dashboard-card {
            padding: 20px;
        }

        .card-header, .card-content {
            padding: 20px;
        }

        .quick-actions {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 480px) {
        .header-title h1 {
            font-size: 1.5rem;
        }

        .stat-content h3 {
            font-size: 2rem;
        }

        .quick-actions {
            grid-template-columns: 1fr;
        }
    }
</style>

<script>
    // Update datetime
    function updateDateTime() {
        const now = new Date();
        const options = {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        };
        document.getElementById('current-datetime').textContent = 
            now.toLocaleDateString('id-ID', options);
    }

    // Initialize chart
    function initChart() {
        const ctx = document.getElementById('criteriaChart');
        if (!ctx) return;

        const chartData = <?= json_encode($chart_data) ?>;
        
        if (chartData.length === 0) return;

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: chartData.map(item => item.nama_kriteria),
                datasets: [{
                    label: 'Rata-rata Nilai',
                    data: chartData.map(item => parseFloat(item.rata_nilai) || 0),
                    backgroundColor: [
                        'rgba(102, 126, 234, 0.8)',
                        'rgba(118, 75, 162, 0.8)',
                        'rgba(40, 167, 69, 0.8)',
                        'rgba(255, 193, 7, 0.8)',
                        'rgba(23, 162, 184, 0.8)'
                    ],
                    borderColor: [
                        'rgba(102, 126, 234, 1)',
                        'rgba(118, 75, 162, 1)',
                        'rgba(40, 167, 69, 1)',
                        'rgba(255, 193, 7, 1)',
                        'rgba(23, 162, 184, 1)'
                    ],
                    borderWidth: 2,
                    borderRadius: 8,
                    borderSkipped: false,
                }]
            },
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
                            label: function(context) {
                                return `Rata-rata: ${context.parsed.y.toFixed(2)}`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 50,
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
        });
    }

    // Refresh chart
    function refreshChart() {
        location.reload();
    }

    // Animate counters
    function animateCounters() {
        const counters = document.querySelectorAll('.stat-content h3');
        
        counters.forEach(counter => {
            const target = parseInt(counter.textContent);
            const increment = target / 50;
            let current = 0;
            
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    counter.textContent = target;
                    clearInterval(timer);
                } else {
                    counter.textContent = Math.floor(current);
                }
            }, 30);
        });
    }

    // Auto refresh data every 5 minutes
    function autoRefresh() {
        setInterval(() => {
            // Update badges in sidebar
            fetch('ajax/get-stats.php')
                .then(response => response.json())
                .then(data => {
                    // Update badges if needed
                    console.log('Stats updated:', data);
                })
                .catch(error => console.error('Error updating stats:', error));
        }, 300000); // 5 minutes
    }

    // Initialize everything
    document.addEventListener('DOMContentLoaded', function() {
        updateDateTime();
        setInterval(updateDateTime, 60000); // Update every minute
        
        initChart();
        animateCounters();
        autoRefresh();
        
        // Add loading states to buttons
        document.querySelectorAll('.action-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                if (this.href && !this.href.includes('#')) {
                    this.style.opacity = '0.7';
                    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Loading...</span>';
                }
            });
        });
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey) {
            switch(e.key) {
                case '1':
                    e.preventDefault();
                    window.location.href = 'kwarcan.php';
                    break;
                case '2':
                    e.preventDefault();
                    window.location.href = 'kriteria.php';
                    break;
                case '3':
                    e.preventDefault();
                    window.location.href = 'penilaian.php';
                    break;
                case '4':
                    e.preventDefault();
                    window.location.href = 'hitung-moora.php';
                    break;
            }
        }
    });
</script>

<?php include '../includes/footer.php'; ?>
