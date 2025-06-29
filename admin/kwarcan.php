<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

require_once '../config/database.php';

$page_title = "Data Kwarcan - SPK MOORA";
$success = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                try {
                    // Generate kode kwarcan otomatis
                    $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(kode_kwarcan, 2) AS UNSIGNED)) as max_num FROM kwarcan WHERE kode_kwarcan LIKE 'A%'");
                    $result = $stmt->fetch();
                    $next_num = ($result['max_num'] ?? 0) + 1;
                    $kode_kwarcan = 'A' . $next_num;
                    
                    $stmt = $pdo->prepare("INSERT INTO kwarcan (kode_kwarcan, nama_kwarcan, daerah, kontak, keterangan, status) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $kode_kwarcan,
                        $_POST['nama_kwarcan'],
                        $_POST['daerah'],
                        $_POST['kontak'],
                        $_POST['keterangan'],
                        $_POST['status']
                    ]);
                    
                    $success = "Data kwarcan berhasil ditambahkan dengan kode: $kode_kwarcan";
                } catch (Exception $e) {
                    $error = "Gagal menambahkan data: " . $e->getMessage();
                }
                break;
                
            case 'edit':
                try {
                    $stmt = $pdo->prepare("UPDATE kwarcan SET nama_kwarcan = ?, daerah = ?, kontak = ?, keterangan = ?, status = ? WHERE id = ?");
                    $stmt->execute([
                        $_POST['nama_kwarcan'],
                        $_POST['daerah'],
                        $_POST['kontak'],
                        $_POST['keterangan'],
                        $_POST['status'],
                        $_POST['id']
                    ]);
                    
                    $success = "Data kwarcan berhasil diperbarui";
                } catch (Exception $e) {
                    $error = "Gagal memperbarui data: " . $e->getMessage();
                }
                break;
                
            case 'delete':
                try {
                    // Check if kwarcan has penilaian
                    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM penilaian WHERE kwarcan_id = ?");
                    $stmt->execute([$_POST['id']]);
                    $penilaian_count = $stmt->fetch()['count'];
                    
                    if ($penilaian_count > 0) {
                        $error = "Tidak dapat menghapus kwarcan yang sudah memiliki data penilaian";
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM kwarcan WHERE id = ?");
                        $stmt->execute([$_POST['id']]);
                        $success = "Data kwarcan berhasil dihapus";
                    }
                } catch (Exception $e) {
                    $error = "Gagal menghapus data: " . $e->getMessage();
                }
                break;
        }
    }
}

// Get filter parameters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// Build query
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(nama_kwarcan LIKE ? OR daerah LIKE ? OR kode_kwarcan LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($status_filter)) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total count
$count_query = "SELECT COUNT(*) as total FROM kwarcan $where_clause";
$stmt = $pdo->prepare($count_query);
$stmt->execute($params);
$total_records = $stmt->fetch()['total'];
$total_pages = ceil($total_records / $limit);

// Get kwarcan data
$query = "SELECT k.*, 
          (SELECT COUNT(*) FROM penilaian p WHERE p.kwarcan_id = k.id) as penilaian_count,
          (SELECT AVG(p.nilai) FROM penilaian p WHERE p.kwarcan_id = k.id) as rata_nilai
          FROM kwarcan k 
          $where_clause 
          ORDER BY k.kode_kwarcan ASC 
          LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$kwarcan_list = $stmt->fetchAll();

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'aktif' THEN 1 ELSE 0 END) as aktif,
    SUM(CASE WHEN status = 'nonaktif' THEN 1 ELSE 0 END) as nonaktif,
    (SELECT COUNT(DISTINCT kwarcan_id) FROM penilaian) as sudah_dinilai
    FROM kwarcan";
$stmt = $pdo->query($stats_query);
$stats = $stmt->fetch();
?>

<?php include '../includes/header.php'; ?>

<div class="main-wrapper">
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="content">
        <!-- Content Header -->
        <div class="content-header">
            <div class="header-title">
                <h1><i class="fas fa-users"></i> Data Kwarcan</h1>
                <p>Kelola data calon Ketua Kwarcab Kabupaten Asahan</p>
            </div>
            <div class="header-actions">
                <button class="btn btn-primary" onclick="openAddModal()">
                    <i class="fas fa-plus"></i> Tambah Kwarcan
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $stats['total'] ?></h3>
                    <p>Total Kwarcan</p>
                </div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $stats['aktif'] ?></h3>
                    <p>Status Aktif</p>
                </div>
            </div>
            
            <div class="stat-card warning">
                <div class="stat-icon">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $stats['sudah_dinilai'] ?></h3>
                    <p>Sudah Dinilai</p>
                </div>
            </div>
            
            <div class="stat-card info">
                <div class="stat-icon">
                    <i class="fas fa-percentage"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $stats['total'] > 0 ? round(($stats['sudah_dinilai'] / $stats['total']) * 100, 1) : 0 ?>%</h3>
                    <p>Progress Penilaian</p>
                </div>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="filter-section">
            <div class="filter-card">
                <form method="GET" class="filter-form">
                    <div class="filter-group">
                        <div class="form-group">
                            <label>Pencarian</label>
                            <div class="input-group">
                                <input type="text" name="search" class="form-control" 
                                       placeholder="Cari nama, daerah, atau kode..." 
                                       value="<?= htmlspecialchars($search) ?>">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status" class="form-control">
                                <option value="">Semua Status</option>
                                <option value="aktif" <?= $status_filter == 'aktif' ? 'selected' : '' ?>>Aktif</option>
                                <option value="nonaktif" <?= $status_filter == 'nonaktif' ? 'selected' : '' ?>>Non-aktif</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <div class="filter-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter"></i> Filter
                                </button>
                                <a href="kwarcan.php" class="btn btn-secondary">
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
                <h3>Daftar Kwarcan</h3>
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
                            <th>Nama Kwarcan</th>
                            <th>Daerah</th>
                            <th>Kontak</th>
                            <th>Status</th>
                            <th>Penilaian</th>
                            <th>Rata-rata</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($kwarcan_list)): ?>
                            <tr>
                                <td colspan="9" class="text-center">
                                    <div class="empty-state">
                                        <i class="fas fa-users"></i>
                                        <h4>Tidak ada data</h4>
                                        <p>Belum ada data kwarcan yang tersedia</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($kwarcan_list as $index => $kwarcan): ?>
                                <tr>
                                    <td><?= $offset + $index + 1 ?></td>
                                    <td>
                                        <span class="badge badge-primary"><?= htmlspecialchars($kwarcan['kode_kwarcan']) ?></span>
                                    </td>
                                    <td>
                                        <div class="user-info">
                                            <strong><?= htmlspecialchars($kwarcan['nama_kwarcan']) ?></strong>
                                            <?php if ($kwarcan['keterangan']): ?>
                                                <small class="text-muted d-block"><?= htmlspecialchars($kwarcan['keterangan']) ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($kwarcan['daerah']) ?></td>
                                    <td><?= htmlspecialchars($kwarcan['kontak'] ?: '-') ?></td>
                                    <td>
                                        <span class="badge badge-<?= $kwarcan['status'] == 'aktif' ? 'success' : 'secondary' ?>">
                                            <?= ucfirst($kwarcan['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($kwarcan['penilaian_count'] > 0): ?>
                                            <span class="badge badge-success">
                                                <i class="fas fa-check"></i> <?= $kwarcan['penilaian_count'] ?> kriteria
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">
                                                <i class="fas fa-clock"></i> Belum dinilai
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($kwarcan['rata_nilai']): ?>
                                            <strong class="text-primary"><?= number_format($kwarcan['rata_nilai'], 2) ?></strong>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-sm btn-info" onclick="viewDetail(<?= $kwarcan['id'] ?>)" title="Detail">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-warning" onclick="editKwarcan(<?= $kwarcan['id'] ?>)" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if ($kwarcan['penilaian_count'] == 0): ?>
                                                <button class="btn btn-sm btn-danger" onclick="deleteKwarcan(<?= $kwarcan['id'] ?>)" title="Hapus">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                            <a href="penilaian.php?kwarcan_id=<?= $kwarcan['id'] ?>" class="btn btn-sm btn-success" title="Penilaian">
                                                                                                <i class="fas fa-star"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination-wrapper">
                    <div class="pagination-info">
                        Menampilkan <?= $offset + 1 ?> - <?= min($offset + $limit, $total_records) ?> dari <?= $total_records ?> data
                    </div>
                    <nav class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>" class="page-link">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>
                        
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++):
                        ?>
                            <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>" 
                               class="page-link <?= $i == $page ? 'active' : '' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>" class="page-link">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal" id="kwarcanModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Tambah Kwarcan</h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form id="kwarcanForm" method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="kwarcanId">
                
                <div class="form-group">
                    <label for="nama_kwarcan">Nama Kwarcan <span class="required">*</span></label>
                    <input type="text" id="nama_kwarcan" name="nama_kwarcan" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="daerah">Daerah <span class="required">*</span></label>
                    <input type="text" id="daerah" name="daerah" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="kontak">Kontak</label>
                    <input type="text" id="kontak" name="kontak" class="form-control" placeholder="No. HP, Email, dll">
                </div>
                
                <div class="form-group">
                    <label for="keterangan">Keterangan</label>
                    <textarea id="keterangan" name="keterangan" class="form-control" rows="3" placeholder="Keterangan tambahan..."></textarea>
                </div>
                
                <div class="form-group">
                    <label for="status">Status <span class="required">*</span></label>
                    <select id="status" name="status" class="form-control" required>
                        <option value="aktif">Aktif</option>
                        <option value="nonaktif">Non-aktif</option>
                    </select>
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
            <h3>Detail Kwarcan</h3>
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
.content {
    margin-left: 280px;
    padding: 30px;
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

.stat-card.primary .stat-icon { background: linear-gradient(135deg, #667eea, #764ba2); }
.stat-card.success .stat-icon { background: linear-gradient(135deg, #28a745, #20c997); }
.stat-card.warning .stat-icon { background: linear-gradient(135deg, #ffc107, #fd7e14); }
.stat-card.info .stat-icon { background: linear-gradient(135deg, #17a2b8, #6f42c1); }

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
    padding: 25px 30px;
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
    background: rgba(102, 126, 234, 0.02);
}

.badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge-primary { background: rgba(102, 126, 234, 0.1); color: #667eea; }
.badge-success { background: rgba(40, 167, 69, 0.1); color: #28a745; }
.badge-warning { background: rgba(255, 193, 7, 0.1); color: #ffc107; }
.badge-secondary { background: rgba(108, 117, 125, 0.1); color: #6c757d; }

.user-info strong {
    color: #333;
    font-size: 0.95rem;
}

.user-info small {
    color: #666;
    font-size: 0.8rem;
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
}

.btn-sm {
    padding: 6px 10px;
    font-size: 0.8rem;
}

.btn-primary { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
.btn-success { background: linear-gradient(135deg, #28a745, #20c997); color: white; }
.btn-warning { background: linear-gradient(135deg, #ffc107, #fd7e14); color: white; }
.btn-info { background: linear-gradient(135deg, #17a2b8, #6f42c1); color: white; }
.btn-danger { background: linear-gradient(135deg, #dc3545, #c82333); color: white; }
.btn-secondary { background: linear-gradient(135deg, #6c757d, #5a6268); color: white; }

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
}

.pagination-wrapper {
    padding: 20px 30px;
    border-top: 1px solid rgba(0, 0, 0, 0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.pagination-info {
    color: #666;
    font-size: 0.9rem;
}

.pagination {
    display: flex;
    gap: 5px;
}

.page-link {
    padding: 8px 12px;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    color: #667eea;
    text-decoration: none;
    transition: all 0.3s ease;
}

.page-link:hover,
.page-link.active {
    background: #667eea;
    color: white;
    border-color: #667eea;
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
    z-index: 1050;
    animation: fadeIn 0.3s ease;
}

.modal.show {
    display: flex;
    align-items: center;
        justify-content: center;
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

.required {
    color: #dc3545;
}

.text-center {
    text-align: center;
}

.text-muted {
    color: #6c757d !important;
}

.text-primary {
    color: #667eea !important;
}

.d-block {
    display: block !important;
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
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
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
    
    .pagination-wrapper {
        flex-direction: column;
        gap: 15px;
        text-align: center;
    }
}
</style>

<script>
// Global variables
let currentKwarcanId = null;

// Modal functions
function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Tambah Kwarcan';
    document.getElementById('formAction').value = 'add';
    document.getElementById('kwarcanForm').reset();
    document.getElementById('kwarcanId').value = '';
    document.getElementById('kwarcanModal').classList.add('show');
}

function closeModal() {
    document.getElementById('kwarcanModal').classList.remove('show');
}

function closeDetailModal() {
    document.getElementById('detailModal').classList.remove('show');
}

// Edit kwarcan
function editKwarcan(id) {
    fetch(`ajax/get-kwarcan.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const kwarcan = data.data;
                document.getElementById('modalTitle').textContent = 'Edit Kwarcan';
                document.getElementById('formAction').value = 'edit';
                document.getElementById('kwarcanId').value = kwarcan.id;
                document.getElementById('nama_kwarcan').value = kwarcan.nama_kwarcan;
                document.getElementById('daerah').value = kwarcan.daerah;
                document.getElementById('kontak').value = kwarcan.kontak || '';
                document.getElementById('keterangan').value = kwarcan.keterangan || '';
                document.getElementById('status').value = kwarcan.status;
                document.getElementById('kwarcanModal').classList.add('show');
            } else {
                showToast(data.message || 'Gagal mengambil data kwarcan', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Terjadi kesalahan saat mengambil data', 'error');
        });
}

// Delete kwarcan
function deleteKwarcan(id) {
    if (confirm('Yakin ingin menghapus data kwarcan ini?\n\nData yang sudah dihapus tidak dapat dikembalikan.')) {
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
    
    fetch(`ajax/get-kwarcan-detail.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const kwarcan = data.data;
                const penilaian = data.penilaian || [];
                
                let detailHtml = `
                    <div class="detail-section">
                        <div class="detail-header">
                            <div class="detail-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="detail-info">
                                <h4>${kwarcan.nama_kwarcan}</h4>
                                <p><i class="fas fa-map-marker-alt"></i> ${kwarcan.daerah}</p>
                                <span class="badge badge-${kwarcan.status === 'aktif' ? 'success' : 'secondary'}">
                                    ${kwarcan.status.charAt(0).toUpperCase() + kwarcan.status.slice(1)}
                                </span>
                            </div>
                        </div>
                        
                        <div class="detail-grid">
                            <div class="detail-item">
                                <label>Kode Kwarcan</label>
                                <span class="badge badge-primary">${kwarcan.kode_kwarcan}</span>
                            </div>
                            <div class="detail-item">
                                <label>Kontak</label>
                                <span>${kwarcan.kontak || '-'}</span>
                            </div>
                            <div class="detail-item">
                                <label>Tanggal Dibuat</label>
                                <span>${new Date(kwarcan.created_at).toLocaleDateString('id-ID')}</span>
                            </div>
                            <div class="detail-item">
                                <label>Terakhir Update</label>
                                <span>${new Date(kwarcan.updated_at).toLocaleDateString('id-ID')}</span>
                            </div>
                        </div>
                        
                        ${kwarcan.keterangan ? `
                            <div class="detail-item full-width">
                                <label>Keterangan</label>
                                <p>${kwarcan.keterangan}</p>
                            </div>
                        ` : ''}
                    </div>
                `;
                
                if (penilaian.length > 0) {
                    detailHtml += `
                        <div class="detail-section">
                            <h5><i class="fas fa-star"></i> Data Penilaian</h5>
                            <div class="penilaian-grid">
                    `;
                    
                    penilaian.forEach(p => {
                        detailHtml += `
                            <div class="penilaian-item">
                                <div class="penilaian-header">
                                    <span class="kriteria-code">${p.kode_kriteria}</span>
                                    <span class="penilaian-score">${p.nilai}</span>
                                </div>
                                <div class="kriteria-name">${p.nama_kriteria}</div>
                                <div class="kriteria-weight">Bobot: ${p.bobot}</div>
                            </div>
                        `;
                    });
                    
                    const avgScore = penilaian.reduce((sum, p) => sum + parseFloat(p.nilai), 0) / penilaian.length;
                    
                    detailHtml += `
                            </div>
                            <div class="penilaian-summary">
                                <div class="summary-item">
                                    <label>Total Kriteria Dinilai</label>
                                    <span class="badge badge-success">${penilaian.length}</span>
                                </div>
                                <div class="summary-item">
                                    <label>Rata-rata Nilai</label>
                                    <span class="score-avg">${avgScore.toFixed(2)}</span>
                                </div>
                            </div>
                        </div>
                    `;
                } else {
                    detailHtml += `
                        <div class="detail-section">
                            <div class="empty-state">
                                <i class="fas fa-star"></i>
                                <h4>Belum Ada Penilaian</h4>
                                <p>Kwarcan ini belum memiliki data penilaian</p>
                                <a href="penilaian.php?kwarcan_id=${kwarcan.id}" class="btn btn-primary btn-sm">
                                    <i class="fas fa-plus"></i> Tambah Penilaian
                                </a>
                            </div>
                        </div>
                    `;
                }
                
                document.getElementById('detailContent').innerHTML = detailHtml;
            } else {
                document.getElementById('detailContent').innerHTML = `
                    <div class="text-center text-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        <p>${data.message || 'Gagal memuat detail kwarcan'}</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('detailContent').innerHTML = `
                <div class="text-center text-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>Terjadi kesalahan saat memuat data</p>
                </div>
            `;
        });
}

// Export data
function exportData() {
    const searchParams = new URLSearchParams(window.location.search);
    const exportUrl = `ajax/export-kwarcan.php?${searchParams.toString()}`;
    window.open(exportUrl, '_blank');
}

// Form validation
document.getElementById('kwarcanForm').addEventListener('submit', function(e) {
    const namaKwarcan = document.getElementById('nama_kwarcan').value.trim();
    const daerah = document.getElementById('daerah').value.trim();
    
    if (!namaKwarcan || !daerah) {
        e.preventDefault();
        showToast('Nama kwarcan dan daerah harus diisi', 'error');
        return;
    }
    
    // Show loading
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
    submitBtn.disabled = true;
    
    // Re-enable button after 3 seconds (fallback)
    setTimeout(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }, 3000);
});

// Close modal when clicking outside
document.getElementById('kwarcanModal').addEventListener('click', function(e) {
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
        openAddModal();
    }
});

// Auto-refresh data every 5 minutes
setInterval(function() {
    // Update statistics
    fetch('ajax/get-kwarcan-stats.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update stat cards if needed
                console.log('Stats updated:', data.stats);
            }
        })
        .catch(error => console.error('Error updating stats:', error));
}, 300000);

// Show success/error messages
<?php if ($success): ?>
    showToast('<?= addslashes($success) ?>', 'success');
<?php endif; ?>

<?php if ($error): ?>
    showToast('<?= addslashes($error) ?>', 'error');
<?php endif; ?>

// Initialize tooltips and other components
document.addEventListener('DOMContentLoaded', function() {
    // Add loading states to action buttons
    document.querySelectorAll('.action-buttons .btn').forEach(btn => {
        btn.addEventListener('click', function() {
            if (!this.onclick && this.href) {
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            }
        });
    });
    
    // Auto-focus first input in modal
    document.getElementById('kwarcanModal').addEventListener('transitionend', function() {
        if (this.classList.contains('show')) {
            document.getElementById('nama_kwarcan').focus();
        }
    });
});
</script>

<!-- Additional CSS for detail modal -->
<style>
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
}

.detail-info h4 {
    color: #333;
    font-size: 1.5rem;
    margin-bottom: 8px;
}

.detail-info p {
    color: #666;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.detail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.detail-item {
    background: rgba(102, 126, 234, 0.05);
    padding: 15px;
    border-radius: 10px;
}

.detail-item.full-width {
    grid-column: 1 / -1;
}

.detail-item label {
    display: block;
    color: #666;
    font-size: 0.85rem;
    font-weight: 600;
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.detail-item span,
.detail-item p {
    color: #333;
    font-weight: 500;
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

.penilaian-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.penilaian-item {
    background: white;
    border: 2px solid rgba(102, 126, 234, 0.1);
    border-radius: 12px;
    padding: 20px;
    transition: all 0.3s ease;
}

.penilaian-item:hover {
    border-color: rgba(102, 126, 234, 0.3);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.1);
}

.penilaian-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.kriteria-code {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 600;
}

.penilaian-score {
    font-size: 1.5rem;
    font-weight: 700;
    color: #28a745;
}

.kriteria-name {
    color: #333;
    font-weight: 600;
    margin-bottom: 5px;
}

.kriteria-weight {
    color: #666;
    font-size: 0.85rem;
}

.penilaian-summary {
    display: flex;
    justify-content: space-around;
    background: rgba(40, 167, 69, 0.05);
    padding: 20px;
    border-radius: 12px;
    border: 2px solid rgba(40, 167, 69, 0.1);
}

.summary-item {
    text-align: center;
}

.summary-item label {
    display: block;
    color: #666;
    font-size: 0.85rem;
    margin-bottom: 8px;
    font-weight: 600;
}

.score-avg {
    font-size: 1.8rem;
    font-weight: 700;
    color: #28a745;
}

@media (max-width: 768px) {
    .detail-header {
        flex-direction: column;
        text-align: center;
    }
    
    .detail-grid {
        grid-template-columns: 1fr;
    }
    
    .penilaian-grid {
        grid-template-columns: 1fr;
    }
    
    .penilaian-summary {
        flex-direction: column;
        gap: 15px;
    }
}
</style>

<?php include '../includes/footer.php'; ?>
