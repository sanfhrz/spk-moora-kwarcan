<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

require_once '../config/database.php';

$page_title = "Kriteria Penilaian - SPK MOORA";
$success = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                try {
                    // Generate kode kriteria otomatis
                    $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(kode_kriteria, 2) AS UNSIGNED)) as max_num FROM kriteria WHERE kode_kriteria LIKE 'C%'");
                    $result = $stmt->fetch();
                    $next_num = ($result['max_num'] ?? 0) + 1;
                    $kode_kriteria = 'C' . $next_num;

                    // Validasi total bobot tidak melebihi 1
                    $stmt = $pdo->query("SELECT SUM(bobot) as total_bobot FROM kriteria");
                    $current_total = $stmt->fetch()['total_bobot'] ?? 0;
                    $new_total = $current_total + floatval($_POST['bobot']);

                    if ($new_total > 1) {
                        $error = "Total bobot tidak boleh melebihi 1.000. Sisa bobot yang tersedia: " . number_format(1 - $current_total, 3);
                        break;
                    }

                    $stmt = $pdo->prepare("INSERT INTO kriteria (kode_kriteria, nama_kriteria, bobot, jenis, keterangan) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $kode_kriteria,
                        $_POST['nama_kriteria'],
                        $_POST['bobot'],
                        $_POST['jenis'],
                        $_POST['keterangan']
                    ]);

                    $success = "Kriteria berhasil ditambahkan dengan kode: $kode_kriteria";
                } catch (Exception $e) {
                    $error = "Gagal menambahkan kriteria: " . $e->getMessage();
                }
                break;

            case 'edit':
                try {
                    // Validasi total bobot
                    $stmt = $pdo->prepare("SELECT SUM(bobot) as total_bobot FROM kriteria WHERE id != ?");
                    $stmt->execute([$_POST['id']]);
                    $current_total = $stmt->fetch()['total_bobot'] ?? 0;
                    $new_total = $current_total + floatval($_POST['bobot']);

                    if ($new_total > 1) {
                        $error = "Total bobot tidak boleh melebihi 1.000. Sisa bobot yang tersedia: " . number_format(1 - $current_total, 3);
                        break;
                    }

                    $stmt = $pdo->prepare("UPDATE kriteria SET nama_kriteria = ?, bobot = ?, jenis = ?, keterangan = ? WHERE id = ?");
                    $stmt->execute([
                        $_POST['nama_kriteria'],
                        $_POST['bobot'],
                        $_POST['jenis'],
                        $_POST['keterangan'],
                        $_POST['id']
                    ]);

                    $success = "Kriteria berhasil diperbarui";
                } catch (Exception $e) {
                    $error = "Gagal memperbarui kriteria: " . $e->getMessage();
                }
                break;

            case 'delete':
                try {
                    // Check if kriteria has penilaian
                    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM penilaian WHERE kriteria_id = ?");
                    $stmt->execute([$_POST['id']]);
                    $penilaian_count = $stmt->fetch()['count'];

                    if ($penilaian_count > 0) {
                        $error = "Tidak dapat menghapus kriteria yang sudah digunakan dalam penilaian";
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM kriteria WHERE id = ?");
                        $stmt->execute([$_POST['id']]);
                        $success = "Kriteria berhasil dihapus";
                    }
                } catch (Exception $e) {
                    $error = "Gagal menghapus kriteria: " . $e->getMessage();
                }
                break;

            case 'reset_bobot':
                try {
                    // Reset semua bobot menjadi sama rata
                    $stmt = $pdo->query("SELECT COUNT(*) as total FROM kriteria");
                    $total_kriteria = $stmt->fetch()['total'];

                    if ($total_kriteria > 0) {
                        $bobot_rata = 1 / $total_kriteria;
                        $stmt = $pdo->prepare("UPDATE kriteria SET bobot = ?");
                        $stmt->execute([$bobot_rata]);
                        $success = "Bobot kriteria berhasil direset menjadi sama rata (" . number_format($bobot_rata, 3) . " per kriteria)";
                    }
                } catch (Exception $e) {
                    $error = "Gagal mereset bobot: " . $e->getMessage();
                }
                break;
        }
    }
}

// Get kriteria data
$search = $_GET['search'] ?? '';
$jenis_filter = $_GET['jenis'] ?? '';

$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(nama_kriteria LIKE ? OR kode_kriteria LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($jenis_filter)) {
    $where_conditions[] = "jenis = ?";
    $params[] = $jenis_filter;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

$query = "SELECT k.*, 
          (SELECT COUNT(*) FROM penilaian p WHERE p.kriteria_id = k.id) as penilaian_count,
          (SELECT AVG(p.nilai) FROM penilaian p WHERE p.kriteria_id = k.id) as rata_nilai
          FROM kriteria k 
          $where_clause 
          ORDER BY k.kode_kriteria ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$kriteria_list = $stmt->fetchAll();

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(bobot) as total_bobot,
    SUM(CASE WHEN jenis = 'benefit' THEN 1 ELSE 0 END) as benefit_count,
    SUM(CASE WHEN jenis = 'cost' THEN 1 ELSE 0 END) as cost_count,
    (SELECT COUNT(DISTINCT kriteria_id) FROM penilaian) as sudah_digunakan
    FROM kriteria";
$stmt = $pdo->query($stats_query);
$stats = $stmt->fetch();

$sisa_bobot = 1 - ($stats['total_bobot'] ?? 0);
?>

<?php include '../includes/header.php'; ?>

<div class="main-wrapper">
    <?php include '../includes/sidebar.php'; ?>

    <div class="content">
        <!-- Content Header -->
        <div class="content-header">
            <div class="header-title">
                <h1><i class="fas fa-list-check"></i> Kriteria Penilaian</h1>
                <p>Kelola kriteria dan bobot untuk penilaian Kwarcan</p>
            </div>
            <div class="header-actions">
                <button class="btn btn-warning btn-sm" onclick="resetBobot()" <?= $stats['total'] == 0 ? 'disabled' : '' ?>>
                    <i class="fas fa-balance-scale"></i> Reset Bobot
                </button>
                <button class="btn btn-primary" onclick="openAddModal()" <?= $sisa_bobot <= 0 ? 'disabled title="Total bobot sudah mencapai maksimum"' : '' ?>>
                    <i class="fas fa-plus"></i> Tambah Kriteria
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="stat-icon">
                    <i class="fas fa-list"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $stats['total'] ?></h3>
                    <p>Total Kriteria</p>
                </div>
            </div>

            <div class="stat-card success">
                <div class="stat-icon">
                    <i class="fas fa-arrow-up"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $stats['benefit_count'] ?></h3>
                    <p>Kriteria Benefit</p>
                </div>
            </div>

            <div class="stat-card danger">
                <div class="stat-icon">
                    <i class="fas fa-arrow-down"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $stats['cost_count'] ?></h3>
                    <p>Kriteria Cost</p>
                </div>
            </div>

            <div class="stat-card warning">
                <div class="stat-icon">
                    <i class="fas fa-percentage"></i>
                </div>
                <div class="stat-content">
                    <h3><?= number_format(($stats['total_bobot'] ?? 0) * 100, 1) ?>%</h3>
                    <p>Total Bobot</p>
                    <small class="<?= $sisa_bobot < 0 ? 'text-danger' : 'text-success' ?>">
                        Sisa: <?= number_format($sisa_bobot, 3) ?>
                    </small>
                </div>
            </div>
        </div>

        <!-- Bobot Progress Bar -->
        <div class="bobot-progress-card">
            <div class="progress-header">
                <h4><i class="fas fa-chart-line"></i> Progress Bobot Kriteria</h4>
                <span class="progress-text">
                    <?= number_format(($stats['total_bobot'] ?? 0) * 100, 1) ?>% dari 100%
                </span>
            </div>
            <div class="progress-bar-container">
                <div class="progress-bar <?= $stats['total_bobot'] > 1 ? 'over-limit' : '' ?>"
                    style="width: <?= min(($stats['total_bobot'] ?? 0) * 100, 100) ?>%">
                </div>
                <?php if ($stats['total_bobot'] > 1): ?>
                    <div class="progress-bar over-limit"
                        style="width: <?= (($stats['total_bobot'] - 1) * 100) ?>%; left: 100%">
                    </div>
                <?php endif; ?>
            </div>
            <div class="progress-labels">
                <span>0%</span>
                <span class="<?= $stats['total_bobot'] > 1 ? 'text-danger' : '' ?>">
                    <?= $stats['total_bobot'] > 1 ? 'MELEBIHI BATAS!' : '100%' ?>
                </span>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-section">
            <div class="filter-card">
                <form method="GET" class="filter-form">
                    <div class="filter-group">
                        <div class="form-group">
                            <label>Pencarian</label>
                            <div class="input-group">
                                <input type="text" name="search" class="form-control"
                                    placeholder="Cari nama atau kode kriteria..."
                                    value="<?= htmlspecialchars($search) ?>">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Jenis Kriteria</label>
                            <select name="jenis" class="form-control">
                                <option value="">Semua Jenis</option>
                                <option value="benefit" <?= $jenis_filter == 'benefit' ? 'selected' : '' ?>>Benefit</option>
                                <option value="cost" <?= $jenis_filter == 'cost' ? 'selected' : '' ?>>Cost</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>&nbsp;</label>
                            <div class="filter-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter"></i> Filter
                                </button>
                                <a href="kriteria.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Reset
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Data Table -->
        <div class="table-card">
            <div class="table-header">
                <h3>Daftar Kriteria</h3>
                <div class="table-actions">
                    <button class="btn btn-success btn-sm" onclick="exportData()">
                        <i class="fas fa-download"></i> Export
                    </button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Kode</th>
                            <th>Nama Kriteria</th>
                            <th>Bobot</th>
                            <th>Jenis</th>
                            <th>Penggunaan</th>
                            <th>Rata-rata Nilai</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($kriteria_list)): ?>
                            <tr>
                                <td colspan="8" class="text-center">
                                    <div class="empty-state">
                                        <i class="fas fa-list-check"></i>
                                        <h4>Belum ada kriteria</h4>
                                        <p>Tambahkan kriteria penilaian untuk memulai</p>
                                        <button class="btn btn-primary" onclick="openAddModal()">
                                            <i class="fas fa-plus"></i> Tambah Kriteria Pertama
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($kriteria_list as $index => $kriteria): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td>
                                        <span class="badge badge-primary"><?= htmlspecialchars($kriteria['kode_kriteria']) ?></span>
                                    </td>
                                    <td>
                                        <div class="kriteria-info">
                                            <strong><?= htmlspecialchars($kriteria['nama_kriteria']) ?></strong>
                                            <?php if ($kriteria['keterangan']): ?>
                                                <small class="d-block text-muted">
                                                    <?= htmlspecialchars(substr($kriteria['keterangan'], 0, 50)) ?>
                                                    <?= strlen($kriteria['keterangan']) > 50 ? '...' : '' ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="bobot-display">
                                            <span class="bobot-value"><?= number_format($kriteria['bobot'], 3) ?></span>
                                            <div class="bobot-bar">
                                                <div class="bobot-fill" style="width: <?= $kriteria['bobot'] * 100 ?>%"></div>
                                            </div>
                                            <small class="bobot-percent"><?= number_format($kriteria['bobot'] * 100, 1) ?>%</small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= $kriteria['jenis'] == 'benefit' ? 'success' : 'danger' ?>">
                                            <i class="fas fa-arrow-<?= $kriteria['jenis'] == 'benefit' ? 'up' : 'down' ?>"></i>
                                            <?= ucfirst($kriteria['jenis']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($kriteria['penilaian_count'] > 0): ?>
                                            <span class="usage-count">
                                                <i class="fas fa-star text-warning"></i>
                                                <?= $kriteria['penilaian_count'] ?> penilaian
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">
                                                <i class="fas fa-minus"></i> Belum digunakan
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($kriteria['rata_nilai']): ?>
                                            <span class="avg-score">
                                                <?= number_format($kriteria['rata_nilai'], 2) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-info btn-sm" onclick="viewDetail(<?= $kriteria['id'] ?>)" title="Detail">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-warning btn-sm" onclick="editKriteria(<?= $kriteria['id'] ?>)" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-danger btn-sm"
                                                onclick="deleteKriteria(<?= $kriteria['id'] ?>, '<?= htmlspecialchars($kriteria['nama_kriteria']) ?>', <?= $kriteria['penilaian_count'] ?>)"
                                                title="Hapus">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal" id="kriteriaModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Tambah Kriteria</h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form id="kriteriaForm" method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="kriteriaId">

                <div class="form-group">
                    <label for="nama_kriteria">Nama Kriteria <span class="required">*</span></label>
                    <input type="text" id="nama_kriteria" name="nama_kriteria" class="form-control" required
                        placeholder="Contoh: Kepemimpinan">
                </div>

                <div class="form-group">
                    <label for="bobot">Bobot <span class="required">*</span></label>
                    <div class="bobot-input-group">
                        <input type="number" id="bobot" name="bobot" class="form-control"
                            min="0.001" max="1" step="0.001" required
                            placeholder="0.250">
                        <div class="bobot-info">
                            <small class="text-muted">
                                Sisa bobot tersedia: <span id="sisaBobot"><?= number_format($sisa_bobot, 3) ?></span>
                            </small>
                            <div class="bobot-slider-container">
                                <input type="range" id="bobotSlider" min="0.001" max="<?= max($sisa_bobot, 0.001) ?>"
                                    step="0.001" value="0.1" class="bobot-slider">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="jenis">Jenis Kriteria <span class="required">*</span></label>
                    <select id="jenis" name="jenis" class="form-control" required>
                        <option value="">Pilih Jenis</option>
                        <option value="benefit">Benefit (Semakin tinggi semakin baik)</option>
                        <option value="cost">Cost (Semakin rendah semakin baik)</option>
                    </select>
                    <small class="form-text text-muted">
                        <strong>Benefit:</strong> Kriteria yang nilainya semakin tinggi semakin baik (contoh: Kepemimpinan)<br>
                        <strong>Cost:</strong> Kriteria yang nilainya semakin rendah semakin baik (contoh: Biaya)
                    </small>
                </div>

                <div class="form-group">
                    <label for="keterangan">Keterangan</label>
                    <textarea id="keterangan" name="keterangan" class="form-control" rows="3"
                        placeholder="Deskripsi kriteria penilaian..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Batal</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Detail Modal -->
<div class="modal" id="detailModal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h3>Detail Kriteria</h3>
            <button class="modal-close" onclick="closeDetailModal()">&times;</button>
        </div>
        <div class="modal-body" id="detailContent">
            <!-- Content will be loaded via AJAX -->
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeDetailModal()">Tutup</button>
        </div>
    </div>
</div>

<style>
    .main-wrapper {
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
    }

    .header-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(20px);
        border-radius: 15px;
        padding: 25px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
    }

    .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: white;
    }

    .stat-card.primary .stat-icon {
        background: linear-gradient(135deg, #667eea, #764ba2);
    }

    .stat-card.success .stat-icon {
        background: linear-gradient(135deg, #28a745, #20c997);
    }

    .stat-card.danger .stat-icon {
        background: linear-gradient(135deg, #dc3545, #c82333);
    }

    .stat-card.warning .stat-icon {
        background: linear-gradient(135deg, #ffc107, #fd7e14);
    }

    .stat-content h3 {
        font-size: 2rem;
        color: #333;
        margin-bottom: 5px;
        font-weight: 700;
    }

    .stat-content p {
        color: #666;
        font-size: 0.9rem;
        margin: 0;
    }

    .stat-content small {
        font-size: 0.8rem;
        margin-top: 5px;
        display: block;
    }

    .bobot-progress-card {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(20px);
        border-radius: 15px;
        padding: 25px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        margin-bottom: 30px;
    }

    .progress-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }

    .progress-header h4 {
        color: #333;
        font-size: 1.2rem;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .progress-text {
        font-weight: 600;
        color: #667eea;
    }

    .progress-bar-container {
        height: 20px;
        background: rgba(0, 0, 0, 0.1);
        border-radius: 10px;
        overflow: hidden;
        position: relative;
        margin-bottom: 10px;
    }

    .progress-bar {
        height: 100%;
        background: linear-gradient(135deg, #28a745, #20c997);
        border-radius: 10px;
        transition: width 0.3s ease;
        position: relative;
    }

    .progress-bar.over-limit {
        background: linear-gradient(135deg, #dc3545, #c82333);
        position: absolute;
        top: 0;
    }

    .progress-labels {
        display: flex;
        justify-content: space-between;
        font-size: 0.85rem;
        color: #666;
    }

    .filter-section {
        margin-bottom: 30px;
    }

    .filter-card {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(20px);
        border-radius: 15px;
        padding: 25px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    }

    .filter-form {
        margin: 0;
    }

    .filter-group {
        display: grid;
        grid-template-columns: 2fr 1fr auto;
        gap: 20px;
        align-items: end;
    }

    .form-group {
        margin-bottom: 0;
    }

    .form-group label {
        display: block;
        color: #333;
        font-weight: 600;
        margin-bottom: 8px;
        font-size: 0.9rem;
    }

    .form-control {
        width: 100%;
        padding: 12px 15px;
        border: 2px solid #e9ecef;
        border-radius: 10px;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        background: white;
    }

    .form-control:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .input-group {
        display: flex;
        gap: 0;
    }

    .input-group .form-control {
        border-top-right-radius: 0;
        border-bottom-right-radius: 0;
        border-right: none;
    }

    .input-group .btn {
        border-top-left-radius: 0;
        border-bottom-left-radius: 0;
    }

    .filter-actions {
        display: flex;
        gap: 10px;
    }

    .table-card {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(20px);
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        overflow: hidden;
    }

    .table-header {
        padding: 25px 30px 20px;
        border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .table-header h3 {
        color: #333;
        font-size: 1.3rem;
        margin: 0;
    }

    .table-actions {
        display: flex;
        gap: 10px;
    }

    .table-responsive {
        overflow-x: auto;
    }

    .table {
        width: 100%;
        border-collapse: collapse;
        margin: 0;
    }

    .table th,
    .table td {
        padding: 15px 20px;
        text-align: left;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    }

    .table th {
        background: rgba(102, 126, 234, 0.05);
        color: #333;
        font-weight: 600;
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .table tbody tr {
        transition: all 0.3s ease;
    }

    .table tbody tr:hover {
        background: rgba(102, 126, 234, 0.05);
    }

    .kriteria-info strong {
        color: #333;
        font-size: 1rem;
    }

    .kriteria-info small {
        color: #666;
        font-size: 0.85rem;
    }

    .bobot-display {
        text-align: center;
    }

    .bobot-value {
        font-weight: 700;
        color: #333;
        font-size: 1.1rem;
        display: block;
        margin-bottom: 5px;
    }

    .bobot-bar {
        width: 60px;
        height: 6px;
        background: rgba(0, 0, 0, 0.1);
        border-radius: 3px;
        overflow: hidden;
        margin: 0 auto 5px;
    }

    .bobot-fill {
        height: 100%;
        background: linear-gradient(135deg, #667eea, #764ba2);
        border-radius: 3px;
        transition: width 0.3s ease;
    }

    .bobot-percent {
        color: #666;
        font-size: 0.8rem;
    }

    .badge {
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    .badge-primary {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
    }

    .badge-success {
        background: linear-gradient(135deg, #28a745, #20c997);
        color: white;
    }

    .badge-danger {
        background: linear-gradient(135deg, #dc3545, #c82333);
        color: white;
    }

    .badge-warning {
        background: linear-gradient(135deg, #ffc107, #fd7e14);
        color: white;
    }

    .usage-count {
        display: flex;
        align-items: center;
        gap: 5px;
        color: #333;
        font-weight: 500;
    }

    .avg-score {
        font-weight: 700;
        color: #28a745;
        font-size: 1.1rem;
    }

    .action-buttons {
        display: flex;
        gap: 5px;
    }

    .btn {
        padding: 8px 16px;
        border: none;
        border-radius: 8px;
        font-size: 0.9rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        justify-content: center;
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

    .btn-secondary {
        background: linear-gradient(135deg, #6c757d, #5a6268);
        color: white;
        box-shadow: 0 4px 15px rgba(108, 117, 125, 0.3);
    }

    .btn-success {
        background: linear-gradient(135deg, #28a745, #20c997);
        color: white;
        box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
    }

    .btn-warning {
        background: linear-gradient(135deg, #ffc107, #fd7e14);
        color: white;
        box-shadow: 0 4px 15px rgba(255, 193, 7, 0.3);
    }

    .btn-danger {
        background: linear-gradient(135deg, #dc3545, #c82333);
        color: white;
        box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
    }

    .btn-info {
        background: linear-gradient(135deg, #17a2b8, #138496);
        color: white;
        box-shadow: 0 4px 15px rgba(23, 162, 184, 0.3);
    }

    .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
    }

    .btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }

    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(5px);
        z-index: 1000;
        align-items: center;
        justify-content: center;
    }

    .modal.show {
        display: flex;
    }

    .modal-content {
        background: white;
        border-radius: 15px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        width: 90%;
        max-width: 500px;
        max-height: 90vh;
        overflow-y: auto;
        animation: slideInUp 0.3s ease;
    }

    .modal-content.modal-lg {
        max-width: 800px;
    }

    .modal-header {
        padding: 25px 30px 20px;
        border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .modal-header h3 {
        color: #333;
        font-size: 1.3rem;
        margin: 0;
    }

    .modal-close {
        width: 35px;
        height: 35px;
        border: none;
        background: rgba(220, 53, 69, 0.1);
        color: #dc3545;
        border-radius: 50%;
        cursor: pointer;
        font-size: 1.2rem;
        transition: all 0.3s ease;
    }

    .modal-close:hover {
        background: #dc3545;
        color: white;
        transform: scale(1.1);
    }

    .modal-body {
        padding: 25px 30px;
    }

    .modal-footer {
        padding: 20px 30px 25px;
        border-top: 1px solid rgba(0, 0, 0, 0.1);
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }

    .bobot-input-group {
        position: relative;
    }

    .bobot-info {
        margin-top: 10px;
    }

    .bobot-slider-container {
        margin-top: 10px;
    }

    .bobot-slider {
        width: 100%;
        height: 6px;
        border-radius: 3px;
        background: #ddd;
        outline: none;
        -webkit-appearance: none;
    }

    .bobot-slider::-webkit-slider-thumb {
        -webkit-appearance: none;
        appearance: none;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea, #764ba2);
        cursor: pointer;
        box-shadow: 0 2px 10px rgba(102, 126, 234, 0.3);
    }

    .bobot-slider::-moz-range-thumb {
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea, #764ba2);
        cursor: pointer;
        border: none;
        box-shadow: 0 2px 10px rgba(102, 126, 234, 0.3);
    }

    .form-text {
        margin-top: 8px;
        font-size: 0.85rem;
        line-height: 1.4;
    }

    .required {
        color: #dc3545;
    }

    .text-center {
        text-align: center;
    }

    .text-muted {
        color: #6c757d !important;
    }

    .text-success {
        color: #28a745 !important;
    }

    .text-danger {
        color: #dc3545 !important;
    }

    .text-warning {
        color: #ffc107 !important;
    }

    .d-block {
        display: block !important;
    }

    /* Detail Modal Styles */
    .detail-section {
        margin-bottom: 30px;
    }

    .detail-section:last-child {
        margin-bottom: 0;
    }

    .detail-header {
        display: flex;
        align-items: center;
        gap: 20px;
        margin-bottom: 25px;
        padding-bottom: 20px;
        border-bottom: 2px solid rgba(102, 126, 234, 0.1);
    }

    .detail-avatar {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea, #764ba2);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 2rem;
        box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
    }

    .detail-info h4 {
        color: #333;
        font-size: 1.5rem;
        margin-bottom: 8px;
    }

    .detail-info p {
        color: #666;
        margin-bottom: 5px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .detail-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
    }

    .detail-item {
        background: rgba(102, 126, 234, 0.05);
        padding: 20px;
        border-radius: 12px;
        border-left: 4px solid #667eea;
    }

    .detail-item.full-width {
        grid-column: 1 / -1;
    }

    .detail-item label {
        display: block;
        color: #666;
        font-size: 0.85rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 8px;
    }

    .detail-item span {
        color: #333;
        font-size: 1.1rem;
        font-weight: 600;
    }

    .detail-item p {
        color: #333;
        font-size: 1rem;
        line-height: 1.5;
        margin: 0;
    }

    .detail-section h5 {
        color: #333;
        font-size: 1.2rem;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
        padding-bottom: 10px;
        border-bottom: 1px solid rgba(0, 0, 0, 0.1);
    }

    .penilaian-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 20px;
    }

    .summary-item {
        text-align: center;
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        border-top: 3px solid #667eea;
    }

    .summary-item label {
        display: block;
        color: #666;
        font-size: 0.85rem;
        font-weight: 600;
        margin-bottom: 10px;
    }

    .score-avg {
        font-size: 2rem;
        font-weight: 700;
        color: #667eea;
    }

    @media (max-width: 768px) {
        .detail-header {
            flex-direction: column;
            text-align: center;
        }

        .detail-grid {
            grid-template-columns: 1fr;
        }

        .penilaian-summary {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 480px) {
        .penilaian-summary {
            grid-template-columns: 1fr;
        }

        .detail-avatar {
            width: 60px;
            height: 60px;
            font-size: 1.5rem;
        }

        .detail-info h4 {
            font-size: 1.2rem;
        }
    }


    .empty-state {
        padding: 40px 20px;
        text-align: center;
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

    @keyframes slideInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Responsive Design */
    @media (max-width: 1200px) {
        .filter-group {
            grid-template-columns: 1fr;
            gap: 15px;
        }

        .filter-actions {
            justify-content: flex-start;
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
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .stat-card {
            padding: 20px;
            flex-direction: column;
            text-align: center;
        }

        .stat-icon {
            margin-bottom: 10px;
        }

        .table-responsive {
            font-size: 0.85rem;
        }

        .table th,
        .table td {
            padding: 10px 8px;
        }

        .action-buttons {
            flex-direction: column;
            gap: 3px;
        }

        .modal-content {
            width: 95%;
            margin: 10px;
        }

        .modal-header,
        .modal-body,
        .modal-footer {
            padding: 20px;
        }
    }

    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }

        .header-actions {
            width: 100%;
            justify-content: flex-start;
        }
    }
</style>

<script>
    // Global variables
    let currentKriteriaId = null;
    let sisaBobotGlobal = <?= $sisa_bobot ?>;

    // Modal functions
    function openAddModal() {
        document.getElementById('modalTitle').textContent = 'Tambah Kriteria';
        document.getElementById('formAction').value = 'add';
        document.getElementById('kriteriaForm').reset();
        document.getElementById('kriteriaId').value = '';

        // Update sisa bobot
        updateSisaBobot();

        // Reset slider
        const slider = document.getElementById('bobotSlider');
        slider.max = Math.max(sisaBobotGlobal, 0.001);
        slider.value = Math.min(0.1, sisaBobotGlobal);
        document.getElementById('bobot').value = slider.value;

        document.getElementById('kriteriaModal').classList.add('show');
    }

    function closeModal() {
        document.getElementById('kriteriaModal').classList.remove('show');
    }

    function closeDetailModal() {
        document.getElementById('detailModal').classList.remove('show');
    }

    // Edit kriteria
    function editKriteria(id) {
        fetch(`ajax/get-kriteria.php?id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const kriteria = data.data;
                    document.getElementById('modalTitle').textContent = 'Edit Kriteria';
                    document.getElementById('formAction').value = 'edit';
                    document.getElementById('kriteriaId').value = kriteria.id;
                    document.getElementById('nama_kriteria').value = kriteria.nama_kriteria;
                    document.getElementById('bobot').value = kriteria.bobot;
                    document.getElementById('jenis').value = kriteria.jenis;
                    document.getElementById('keterangan').value = kriteria.keterangan || '';

                    // Update slider untuk edit
                    const currentBobot = parseFloat(kriteria.bobot);
                    const availableBobot = sisaBobotGlobal + currentBobot;
                    const slider = document.getElementById('bobotSlider');
                    slider.max = Math.max(availableBobot, 0.001);
                    slider.value = currentBobot;

                    updateSisaBobotForEdit(currentBobot);

                    document.getElementById('kriteriaModal').classList.add('show');
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Terjadi kesalahan saat mengambil data', 'error');
            });
    }

    // Delete kriteria
    function deleteKriteria(id, nama, penilaianCount) {
        if (penilaianCount > 0) {
            showToast(`Tidak dapat menghapus kriteria "${nama}" karena sudah digunakan dalam ${penilaianCount} penilaian`, 'error');
            return;
        }

        if (confirm(`Apakah Anda yakin ingin menghapus kriteria "${nama}"?`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="${id}">
        `;
            document.body.appendChild(form);
            form.submit();
        }
    }

    // View detail
    function viewDetail(id) {
        document.getElementById('detailContent').innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Memuat...</div>';
        document.getElementById('detailModal').classList.add('show');

        fetch(`ajax/get-kriteria-detail.php?id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const kriteria = data.data;
                    const penilaian = data.penilaian || [];

                    let detailHtml = `
                    <div class="detail-section">
                        <div class="detail-header">
                            <div class="detail-avatar">
                                <i class="fas fa-${kriteria.jenis === 'benefit' ? 'arrow-up' : 'arrow-down'}"></i>
                            </div>
                            <div class="detail-info">
                                <h4>${kriteria.nama_kriteria}</h4>
                                <p><i class="fas fa-code"></i> ${kriteria.kode_kriteria}</p>
                                <p><i class="fas fa-tag"></i> ${kriteria.jenis === 'benefit' ? 'Benefit' : 'Cost'}</p>
                            </div>
                        </div>
                        
                        <div class="detail-grid">
                            <div class="detail-item">
                                <label>Bobot</label>
                                <span>${kriteria.bobot} (${(kriteria.bobot * 100).toFixed(1)}%)</span>
                            </div>
                            <div class="detail-item">
                                <label>Jenis Kriteria</label>
                                <span class="badge badge-${kriteria.jenis === 'benefit' ? 'success' : 'danger'}">
                                    <i class="fas fa-arrow-${kriteria.jenis === 'benefit' ? 'up' : 'down'}"></i>
                                    ${kriteria.jenis === 'benefit' ? 'Benefit' : 'Cost'}
                                </span>
                            </div>
                            <div class="detail-item">
                                <label>Penggunaan</label>
                                <span>${penilaian.length} penilaian</span>
                            </div>
                            <div class="detail-item">
                                <label>Rata-rata Nilai</label>
                                <span>${penilaian.length > 0 ? (penilaian.reduce((sum, p) => sum + parseFloat(p.nilai), 0) / penilaian.length).toFixed(2) : '-'}</span>
                            </div>
                            ${kriteria.keterangan ? `
                                <div class="detail-item full-width">
                                    <label>Keterangan</label>
                                    <p>${kriteria.keterangan}</p>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                `;

                    if (penilaian.length > 0) {
                        detailHtml += `
                        <div class="detail-section">
                            <h5><i class="fas fa-chart-bar"></i> Statistik Penilaian</h5>
                            <div class="penilaian-summary">
                                <div class="summary-item">
                                    <label>Total Penilaian</label>
                                    <div class="score-avg">${penilaian.length}</div>
                                </div>
                                <div class="summary-item">
                                    <label>Nilai Tertinggi</label>
                                    <div class="score-avg">${Math.max(...penilaian.map(p => parseFloat(p.nilai)))}</div>
                                </div>
                                <div class="summary-item">
                                    <label>Nilai Terendah</label>
                                    <div class="score-avg">${Math.min(...penilaian.map(p => parseFloat(p.nilai)))}</div>
                                </div>
                                <div class="summary-item">
                                    <label>Rata-rata</label>
                                    <div class="score-avg">${(penilaian.reduce((sum, p) => sum + parseFloat(p.nilai), 0) / penilaian.length).toFixed(2)}</div>
                                </div>
                            </div>
                        </div>
                    `;
                    }

                    document.getElementById('detailContent').innerHTML = detailHtml;
                } else {
                    document.getElementById('detailContent').innerHTML = `<div class="text-center text-danger">${data.message}</div>`;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('detailContent').innerHTML = '<div class="text-center text-danger">Terjadi kesalahan saat memuat data</div>';
            });
    }

    // Reset bobot
    function resetBobot() {
        if (confirm('Apakah Anda yakin ingin mereset semua bobot kriteria menjadi sama rata?\n\nTindakan ini akan mengubah bobot semua kriteria.')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = '<input type="hidden" name="action" value="reset_bobot">';
            document.body.appendChild(form);
            form.submit();
        }
    }

    // Export data
    function exportData() {
        const params = new URLSearchParams(window.location.search);
        window.open(`ajax/export-kriteria.php?${params.toString()}`, '_blank');
    }

    // Update sisa bobot
    function updateSisaBobot() {
        document.getElementById('sisaBobot').textContent = sisaBobotGlobal.toFixed(3);
    }

    function updateSisaBobotForEdit(currentBobot) {
        const availableBobot = sisaBobotGlobal + currentBobot;
        document.getElementById('sisaBobot').textContent = availableBobot.toFixed(3);
    }

    // Bobot slider sync
    document.addEventListener('DOMContentLoaded', function() {
        const bobotInput = document.getElementById('bobot');
        const bobotSlider = document.getElementById('bobotSlider');

        // Sync slider with input
        bobotInput.addEventListener('input', function() {
            const value = parseFloat(this.value) || 0;
            bobotSlider.value = Math.min(value, parseFloat(bobotSlider.max));
        });

        // Sync input with slider
        bobotSlider.addEventListener('input', function() {
            bobotInput.value = this.value;
        });

        // Form validation
        document.getElementById('kriteriaForm').addEventListener('submit', function(e) {
            const bobot = parseFloat(document.getElementById('bobot').value);
            const action = document.getElementById('formAction').value;

            if (action === 'add' && bobot > sisaBobotGlobal) {
                e.preventDefault();
                showToast(`Bobot tidak boleh melebihi sisa bobot yang tersedia (${sisaBobotGlobal.toFixed(3)})`, 'error');
                return;
            }

            if (action === 'edit') {
                const currentBobot = parseFloat(document.getElementById('kriteriaId').dataset.currentBobot || 0);
                const availableBobot = sisaBobotGlobal + currentBobot;
                if (bobot > availableBobot) {
                    e.preventDefault();
                    showToast(`Bobot tidak boleh melebihi sisa bobot yang tersedia (${availableBobot.toFixed(3)})`, 'error');
                    return;
                }
            }

            // Show loading
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
            submitBtn.disabled = true;

            // Reset after delay if form doesn't submit
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 3000);
        });

        // Close modal on outside click
        document.getElementById('kriteriaModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        document.getElementById('detailModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDetailModal();
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
                closeDetailModal();
            }

            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                if (sisaBobotGlobal > 0) {
                    openAddModal();
                }
            }
        });
    });

    // Toast notification function
    function showToast(message, type = 'info') {
        // Remove existing toasts
        const existingToasts = document.querySelectorAll('.toast');
        existingToasts.forEach(toast => toast.remove());

        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
        <div class="toast-content">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
            <span>${message}</span>
        </div>
        <button class="toast-close" onclick="this.parentElement.remove()">&times;</button>
    `;

        document.body.appendChild(toast);

        // Auto remove after 5 seconds
        setTimeout(() => {
            if (toast.parentElement) {
                toast.remove();
            }
        }, 5000);
    }

    // Add toast styles
    const toastStyles = `
.toast {
    position: fixed;
    top: 20px;
    right: 20px;
    background: white;
    border-radius: 10px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    padding: 15px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    z-index: 1001;
    min-width: 300px;
    animation: slideInRight 0.3s ease;
    border-left: 4px solid;
}

.toast-success { border-left-color: #28a745; }
.toast-error { border-left-color: #dc3545; }
.toast-info { border-left-color: #17a2b8; }
.toast-warning { border-left-color: #ffc107; }

.toast-content {
    display: flex;
    align-items: center;
    gap: 10px;
    flex: 1;
}

.toast-success .toast-content i { color: #28a745; }
.toast-error .toast-content i { color: #dc3545; }
.toast-info .toast-content i { color: #17a2b8; }
.toast-warning .toast-content i { color: #ffc107; }

.toast-close {
    background: none;
    border: none;
    font-size: 1.2rem;
    cursor: pointer;
    color: #999;
    padding: 0;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.toast-close:hover {
    color: #333;
}

@keyframes slideInRight {
    from {
        opacity: 0;
        transform: translateX(100%);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}
`;

    // Add styles to head
    const styleSheet = document.createElement('style');
    styleSheet.textContent = toastStyles;
    document.head.appendChild(styleSheet);
</script>

<?php if ($success): ?>
    <script>
        showToast('<?= addslashes($success) ?>', 'success');
    </script>
<?php endif; ?>

<?php if ($error): ?>
    <script>
        showToast('<?= addslashes($error) ?>', 'error');
    </script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>