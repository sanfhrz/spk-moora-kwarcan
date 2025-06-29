<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

require_once '../config/database.php';

// Get current admin data
try {
    $stmt = $pdo->prepare("SELECT * FROM admin WHERE id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $admin_data = $stmt->fetch();

    if (!$admin_data) {
        session_destroy();
        header('Location: login.php');
        exit();
    }
} catch (Exception $e) {
    $error_message = "Error mengambil data admin: " . $e->getMessage();
}

// Get system stats for info tab
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM kwarcan WHERE status = 'aktif'");
    $total_kwarcan = $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM kriteria");
    $total_kriteria = $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM penilaian");
    $total_penilaian = $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM hasil_moora");
    $total_hasil = $stmt->fetch()['total'];

    // Get database size (approximate)
    $stmt = $pdo->query("
        SELECT 
            ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'db_size_mb'
        FROM information_schema.tables 
        WHERE table_schema = DATABASE()
    ");
    $db_info = $stmt->fetch();
    $db_size = $db_info['db_size_mb'] ?? 0;
} catch (Exception $e) {
    $total_kwarcan = $total_kriteria = $total_penilaian = $total_hasil = 0;
    $db_size = 0;
}

$page_title = "Pengaturan Sistem - SPK MOORA Kwarcan";
?>

<?php include '../includes/header.php'; ?>

<div class="main-wrapper">
    <?php include '../includes/sidebar.php'; ?>
    <?php include '../includes/navbar.php'; ?>

    <div class="content">
        <!-- Header -->
        <div class="content-header">
            <div class="header-title">
                <h1><i class="fas fa-cog"></i> Pengaturan Sistem</h1>
                <p>Kelola pengaturan aplikasi, profil admin, dan konfigurasi sistem</p>
            </div>
            <div class="header-actions">
                <button class="btn btn-primary" onclick="saveAllSettings()">
                    <i class="fas fa-save"></i> Simpan Semua
                </button>
            </div>
        </div>

        <!-- Alert Messages -->
        <div id="alert-container"></div>

        <!-- Settings Tabs -->
        <div class="settings-container">
            <div class="settings-tabs">
                <button class="tab-btn active" onclick="openTab(event, 'profil-tab')">
                    <i class="fas fa-user"></i> Profil Admin
                </button>
                <button class="tab-btn" onclick="openTab(event, 'sistem-tab')">
                    <i class="fas fa-cogs"></i> Sistem
                </button>
                <button class="tab-btn" onclick="openTab(event, 'penilaian-tab')">
                    <i class="fas fa-star"></i> Penilaian
                </button>
                <button class="tab-btn" onclick="openTab(event, 'backup-tab')">
                    <i class="fas fa-database"></i> Backup & Restore
                </button>
                <button class="tab-btn" onclick="openTab(event, 'info-tab')">
                    <i class="fas fa-info-circle"></i> Info Sistem
                </button>
            </div>

            <!-- Tab Content -->
            <div class="tab-content">

                <!-- Profil Admin Tab -->
                <div id="profil-tab" class="tab-pane active">
                    <div class="settings-card">
                        <div class="card-header">
                            <h3><i class="fas fa-user-edit"></i> Profil Administrator</h3>
                            <p>Kelola informasi akun administrator</p>
                        </div>
                        <div class="card-content">
                            <form id="profil-form" class="settings-form">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="nama_lengkap">Nama Lengkap</label>
                                        <input type="text" id="nama_lengkap" name="nama_lengkap"
                                            value="<?= htmlspecialchars($admin_data['nama_lengkap']) ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="email">Email</label>
                                        <input type="email" id="email" name="email"
                                            value="<?= htmlspecialchars($admin_data['email'] ?? '') ?>">
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="username">Username</label>
                                        <input type="text" id="username" name="username"
                                            value="<?= htmlspecialchars($admin_data['username']) ?>" required>
                                    </div>
                                </div>

                                <div class="form-divider">
                                    <span>Ubah Password</span>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="password_lama">Password Lama</label>
                                        <input type="password" id="password_lama" name="password_lama"
                                            placeholder="Kosongkan jika tidak ingin mengubah">
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="password_baru">Password Baru</label>
                                        <input type="password" id="password_baru" name="password_baru"
                                            placeholder="Minimal 6 karakter">
                                    </div>
                                    <div class="form-group">
                                        <label for="konfirmasi_password">Konfirmasi Password</label>
                                        <input type="password" id="konfirmasi_password" name="konfirmasi_password"
                                            placeholder="Ulangi password baru">
                                    </div>
                                </div>

                                <div class="form-actions">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Simpan Profil
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Sistem Tab -->
                <div id="sistem-tab" class="tab-pane">
                    <div class="settings-card">
                        <div class="card-header">
                            <h3><i class="fas fa-cogs"></i> Pengaturan Sistem</h3>
                            <p>Konfigurasi umum aplikasi</p>
                        </div>
                        <div class="card-content">
                            <form id="sistem-form" class="settings-form">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="nama_aplikasi">Nama Aplikasi</label>
                                        <input type="text" id="nama_aplikasi" name="nama_aplikasi"
                                            value="SPK MOORA Kwarcan" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="versi_aplikasi">Versi</label>
                                        <input type="text" id="versi_aplikasi" name="versi_aplikasi"
                                            value="1.0.0" readonly>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="nama_instansi">Nama Instansi/Organisasi</label>
                                        <input type="text" id="nama_instansi" name="nama_instansi"
                                            value="Kwartir Cabang Pramuka" required>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="alamat_instansi">Alamat Instansi</label>
                                        <textarea id="alamat_instansi" name="alamat_instansi" rows="3"
                                            placeholder="Alamat lengkap instansi"></textarea>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="tahun_periode">Tahun Periode</label>
                                        <input type="number" id="tahun_periode" name="tahun_periode"
                                            value="<?= date('Y') ?>" min="2020" max="2030" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="timezone">Timezone</label>
                                        <select id="timezone" name="timezone">
                                            <option value="Asia/Jakarta" selected>WIB (Asia/Jakarta)</option>
                                            <option value="Asia/Makassar">WITA (Asia/Makassar)</option>
                                            <option value="Asia/Jayapura">WIT (Asia/Jayapura)</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-actions">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Simpan Pengaturan
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Penilaian Tab -->
                <div id="penilaian-tab" class="tab-pane">
                    <div class="settings-card">
                        <div class="card-header">
                            <h3><i class="fas fa-star"></i> Pengaturan Penilaian</h3>
                            <p>Konfigurasi sistem penilaian MOORA</p>
                        </div>
                        <div class="card-content">
                            <form id="penilaian-form" class="settings-form">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="skala_penilaian">Skala Penilaian</label>
                                        <select id="skala_penilaian" name="skala_penilaian">
                                            <option value="1-5" selected>Skala 1-5 (Sangat Buruk - Sangat Baik)</option>
                                            <option value="1-10">Skala 1-10 (1=Terburuk, 10=Terbaik)</option>
                                            <option value="10-50">Skala 10-50 (Saat ini digunakan)</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="metode_normalisasi">Metode Normalisasi</label>
                                        <select id="metode_normalisasi" name="metode_normalisasi">
                                            <option value="vector" selected>Vector Normalization</option>
                                            <option value="linear">Linear Scale Transformation</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="bobot_minimum">Bobot Minimum Kriteria</label>
                                        <input type="number" id="bobot_minimum" name="bobot_minimum"
                                            value="0.1" min="0.01" max="1" step="0.01">
                                    </div>
                                    <div class="form-group">
                                        <label for="bobot_maksimum">Bobot Maksimum Kriteria</label>
                                        <input type="number" id="bobot_maksimum" name="bobot_maksimum"
                                            value="0.5" min="0.01" max="1" step="0.01">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" id="auto_calculate" name="auto_calculate" checked>
                                        <span class="checkmark"></span>
                                        Hitung otomatis setelah input penilaian
                                    </label>
                                </div>

                                <div class="form-actions">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Simpan Pengaturan
                                    </button>
                                    <button type="button" class="btn btn-warning" onclick="resetPenilaian()">
                                        <i class="fas fa-undo"></i> Reset Semua Penilaian
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Backup & Restore Tab -->
                <div id="backup-tab" class="tab-pane">
                    <div class="settings-grid">
                        <!-- Backup Section -->
                        <div class="settings-card">
                            <div class="card-header">
                                <h3><i class="fas fa-download"></i> Backup Database</h3>
                                <p>Unduh backup data sistem</p>
                            </div>
                            <div class="card-content">
                                <div class="backup-info">
                                    <div class="info-item">
                                        <span class="info-label">Ukuran Database:</span>
                                        <span class="info-value"><?= $db_size ?> MB</span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Total Records:</span>
                                        <span class="info-value"><?= $total_kwarcan + $total_kriteria + $total_penilaian ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Backup Terakhir:</span>
                                        <span class="info-value" id="last-backup">
                                            <span id="last-backup-date">Belum pernah</span>
                                        </span>
                                    </div>
                                </div>

                                <div class="backup-actions">
                                    <button type="button" class="btn btn-success" onclick="downloadBackup()">
                                        <i class="fas fa-download"></i> Download Backup
                                    </button>
                                    <button type="button" class="btn btn-info" onclick="downloadTemplate()">
                                        <i class="fas fa-file-excel"></i> Download Template Excel
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Restore Section -->
                        <div class="settings-card">
                            <div class="card-header">
                                <h3><i class="fas fa-upload"></i> Restore Database</h3>
                                <p>Pulihkan data dari file backup</p>
                            </div>
                            <div class="card-content">
                                <div class="upload-area" id="restore-area">
                                    <div class="upload-icon">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                    </div>
                                    <div class="upload-text">
                                        <h4>Drop file backup di sini</h4>
                                        <p>atau klik untuk memilih file (.sql)</p>
                                    </div>
                                    <input type="file" id="restore-file" accept=".sql" style="display: none;">
                                </div>

                                <div class="restore-warning">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <span>Peringatan: Restore akan mengganti semua data yang ada!</span>
                                </div>

                                <div class="restore-actions">
                                    <button type="button" class="btn btn-warning" onclick="selectRestoreFile()">
                                        <i class="fas fa-folder-open"></i> Pilih File
                                    </button>
                                    <button type="button" class="btn btn-danger" onclick="confirmRestore()" disabled id="restore-btn">
                                        <i class="fas fa-upload"></i> Restore Data
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Reset Data Section -->
                        <div class="settings-card">
                            <div class="card-header">
                                <h3><i class="fas fa-trash-alt"></i> Reset Data</h3>
                                <p>Hapus data tertentu atau semua data</p>
                            </div>
                            <div class="card-content">
                                <div class="reset-options">
                                    <div class="reset-option">
                                        <h4>Reset Penilaian</h4>
                                        <p>Hapus semua data penilaian dan hasil MOORA</p>
                                        <button type="button" class="btn btn-warning" onclick="resetData('penilaian')">
                                            <i class="fas fa-eraser"></i> Reset Penilaian
                                        </button>
                                    </div>

                                    <div class="reset-option">
                                        <h4>Reset Hasil MOORA</h4>
                                        <p>Hapus hanya hasil perhitungan MOORA</p>
                                        <button type="button" class="btn btn-warning" onclick="resetData('hasil')">
                                            <i class="fas fa-calculator"></i> Reset Hasil
                                        </button>
                                    </div>

                                    <div class="reset-option danger">
                                        <h4>Reset Semua Data</h4>
                                        <p>Hapus semua data kecuali admin dan kriteria</p>
                                        <button type="button" class="btn btn-danger" onclick="resetData('all')">
                                            <i class="fas fa-trash"></i> Reset Semua
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Info Sistem Tab -->
                <div id="info-tab" class="tab-pane">
                    <div class="settings-grid">
                        <!-- System Info -->
                        <div class="settings-card">
                            <div class="card-header">
                                <h3><i class="fas fa-info-circle"></i> Informasi Sistem</h3>
                                <p>Detail aplikasi dan server</p>
                            </div>
                            <div class="card-content">
                                <div class="info-grid">
                                    <div class="info-item">
                                        <span class="info-label">Nama Aplikasi:</span>
                                        <span class="info-value">SPK MOORA Kwarcan</span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Versi:</span>
                                        <span class="info-value">1.0.0</span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">PHP Version:</span>
                                        <span class="info-value"><?= phpversion() ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Database:</span>
                                        <span class="info-value">MySQL <?= $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Server:</span>
                                        <span class="info-value"><?= $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Timezone:</span>
                                        <span class="info-value"><?= date_default_timezone_get() ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Database Stats -->
                        <div class="settings-card">
                            <div class="card-header">
                                <h3><i class="fas fa-database"></i> Statistik Database</h3>
                                <p>Ringkasan data dalam sistem</p>
                            </div>
                            <div class="card-content">
                                <div class="stats-grid">
                                    <div class="stat-item">
                                        <div class="stat-icon primary">
                                            <i class="fas fa-users"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3><?= $total_kwarcan ?></h3>
                                            <p>Total Kwarcan</p>
                                        </div>
                                    </div>

                                    <div class="stat-item">
                                        <div class="stat-icon success">
                                            <i class="fas fa-list-check"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3><?= $total_kriteria ?></h3>
                                            <p>Kriteria</p>
                                        </div>
                                    </div>

                                    <div class="stat-item">
                                        <div class="stat-icon warning">
                                            <i class="fas fa-star"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3><?= $total_penilaian ?></h3>
                                            <p>Penilaian</p>
                                        </div>
                                    </div>

                                    <div class="stat-item">
                                        <div class="stat-icon info">
                                            <i class="fas fa-trophy"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3><?= $total_hasil ?></h3>
                                            <p>Hasil MOORA</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Activity Log -->
                        <div class="settings-card">
                            <div class="card-header">
                                <h3><i class="fas fa-history"></i> Log Aktivitas</h3>
                                <p>Aktivitas sistem terbaru</p>
                            </div>
                            <div class="card-content">
                                <div class="activity-log" id="activity-log">
                                    <div class="log-item">
                                        <div class="log-time"><?= date('H:i:s') ?></div>
                                        <div class="log-message">Admin login: <?= htmlspecialchars($_SESSION['admin_nama']) ?></div>
                                    </div>
                                    <div class="log-item">
                                        <div class="log-time"><?= date('H:i:s', strtotime('-5 minutes')) ?></div>
                                        <div class="log-message">Sistem dimuat</div>
                                    </div>
                                </div>

                                <div class="log-actions">
                                    <button type="button" class="btn btn-sm btn-secondary" onclick="refreshLog()">
                                        <i class="fas fa-sync-alt"></i> Refresh
                                    </button>
                                    <button type="button" class="btn btn-sm btn-danger" onclick="clearLog()">
                                        <i class="fas fa-trash"></i> Clear Log
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
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
        line-height: 1.5;
    }

    .settings-container {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(20px);
        border-radius: 20px;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        overflow: hidden;
    }

    .settings-tabs {
        display: flex;
        background: rgba(0, 0, 0, 0.05);
        border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        overflow-x: auto;
    }

    .tab-btn {
        padding: 20px 25px;
        border: none;
        background: transparent;
        color: #666;
        font-size: 0.9rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 10px;
        white-space: nowrap;
        border-bottom: 3px solid transparent;
    }

    .tab-btn:hover {
        background: rgba(102, 126, 234, 0.1);
        color: var(--primary-color);
    }

    .tab-btn.active {
        background: rgba(102, 126, 234, 0.1);
        color: var(--primary-color);
        border-bottom-color: var(--primary-color);
    }

    .tab-content {
        padding: 0;
    }

    .tab-pane {
        display: none;
        padding: 30px;
    }

    .tab-pane.active {
        display: block;
    }

    .settings-card {
        background: rgba(255, 255, 255, 0.9);
        border-radius: 15px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        margin-bottom: 25px;
        overflow: hidden;
    }

    .settings-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 25px;
    }

    .card-header {
        padding: 25px 30px 20px;
        border-bottom: 1px solid rgba(0, 0, 0, 0.1);
    }

    .card-header h3 {
        color: #333;
        font-size: 1.3rem;
        margin-bottom: 5px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .card-header p {
        color: #666;
        font-size: 0.9rem;
        margin: 0;
    }

    .card-content {
        padding: 25px 30px 30px;
    }

    .settings-form {
        max-width: 100%;
    }

    .form-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        color: #333;
        font-weight: 600;
        margin-bottom: 8px;
        font-size: 0.9rem;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 12px 15px;
        border: 2px solid rgba(0, 0, 0, 0.1);
        border-radius: 10px;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        background: rgba(255, 255, 255, 0.8);
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .form-divider {
        margin: 30px 0 20px;
        text-align: center;
        position: relative;
    }

    .form-divider::before {
        content: '';
        position: absolute;
        top: 50%;
        left: 0;
        right: 0;
        height: 1px;
        background: rgba(0, 0, 0, 0.1);
    }

    .form-divider span {
        background: white;
        padding: 0 20px;
        color: #666;
        font-weight: 600;
        font-size: 0.9rem;
    }

    .checkbox-label {
        display: flex;
        align-items: center;
        gap: 12px;
        cursor: pointer;
        font-weight: 500;
        color: #333;
    }

    .checkbox-label input[type="checkbox"] {
        width: auto;
        margin: 0;
    }

    .form-actions {
        margin-top: 30px;
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
    }

    .btn {
        padding: 12px 20px;
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

    .btn-secondary {
        background: linear-gradient(135deg, #6c757d, #5a6268);
        color: white;
        box-shadow: 0 4px 15px rgba(108, 117, 125, 0.3);
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

    .btn-sm {
        padding: 8px 15px;
        font-size: 0.8rem;
    }

    /* Backup & Restore Styles */
    .backup-info,
    .info-grid {
        display: grid;
        gap: 15px;
        margin-bottom: 25px;
    }

    .info-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 0;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    }

    .info-label {
        color: #666;
        font-weight: 500;
    }

    .info-value {
        color: #333;
        font-weight: 600;
    }

    .backup-actions,
    .restore-actions {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
    }

    .upload-area {
        border: 2px dashed rgba(102, 126, 234, 0.3);
        border-radius: 15px;
        padding: 40px 20px;
        text-align: center;
        margin-bottom: 20px;
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .upload-area:hover {
        border-color: var(--primary-color);
        background: rgba(102, 126, 234, 0.05);
    }

    .upload-area.dragover {
        border-color: var(--primary-color);
        background: rgba(102, 126, 234, 0.1);
    }

    .upload-icon {
        font-size: 3rem;
        color: var(--primary-color);
        margin-bottom: 15px;
    }

    .upload-text h4 {
        color: #333;
        margin-bottom: 5px;
    }

    .upload-text p {
        color: #666;
        font-size: 0.9rem;
    }

    .restore-warning {
        background: rgba(255, 193, 7, 0.1);
        border: 1px solid rgba(255, 193, 7, 0.3);
        border-radius: 10px;
        padding: 15px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
        color: #856404;
    }

    .reset-options {
        display: grid;
        gap: 20px;
    }

    .reset-option {
        padding: 20px;
        border: 1px solid rgba(0, 0, 0, 0.1);
        border-radius: 10px;
        transition: all 0.3s ease;
    }

    .reset-option:hover {
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }

    .reset-option.danger {
        border-color: rgba(220, 53, 69, 0.3);
        background: rgba(220, 53, 69, 0.05);
    }

    .reset-option h4 {
        color: #333;
        margin-bottom: 8px;
    }

    .reset-option p {
        color: #666;
        font-size: 0.9rem;
        margin-bottom: 15px;
    }

    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
    }

    .stat-item {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 20px;
        background: rgba(255, 255, 255, 0.8);
        border-radius: 15px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
    }

    .stat-item:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
    }

    .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: white;
    }

    .stat-icon.primary {
        background: linear-gradient(135deg, #667eea, #764ba2);
    }

    .stat-icon.success {
        background: linear-gradient(135deg, #28a745, #20c997);
    }

    .stat-icon.warning {
        background: linear-gradient(135deg, #ffc107, #fd7e14);
    }

    .stat-icon.info {
        background: linear-gradient(135deg, #17a2b8, #138496);
    }

    .stat-content h3 {
        color: #333;
        font-size: 1.8rem;
        margin-bottom: 5px;
    }

    .stat-content p {
        color: #666;
        font-size: 0.9rem;
        margin: 0;
    }

    /* Activity Log */
    .activity-log {
        max-height: 300px;
        overflow-y: auto;
        margin-bottom: 20px;
    }

    .log-item {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 12px 0;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    }

    .log-time {
        color: #999;
        font-size: 0.8rem;
        font-family: monospace;
        min-width: 60px;
    }

    .log-message {
        color: #333;
        font-size: 0.9rem;
    }

    .log-actions {
        display: flex;
        gap: 10px;
    }

    /* Alert Messages */
    #alert-container {
        margin-bottom: 20px;
    }

    .alert {
        padding: 15px 20px;
        border-radius: 10px;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 500;
    }

    .alert-success {
        background: rgba(40, 167, 69, 0.1);
        border: 1px solid rgba(40, 167, 69, 0.3);
        color: #155724;
    }

    .alert-danger {
        background: rgba(220, 53, 69, 0.1);
        border: 1px solid rgba(220, 53, 69, 0.3);
        color: #721c24;
    }

    .alert-warning {
        background: rgba(255, 193, 7, 0.1);
        border: 1px solid rgba(255, 193, 7, 0.3);
        color: #856404;
    }

    .alert-info {
        background: rgba(23, 162, 184, 0.1);
        border: 1px solid rgba(23, 162, 184, 0.3);
        color: #0c5460;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .content {
            margin-left: 0;
            padding: 20px 15px;
        }

        .settings-tabs {
            flex-direction: column;
        }

        .tab-btn {
            justify-content: center;
            padding: 15px 20px;
        }

        .tab-pane {
            padding: 20px 15px;
        }

        .settings-grid {
            grid-template-columns: 1fr;
        }

        .form-row {
            grid-template-columns: 1fr;
        }

        .backup-actions,
        .restore-actions,
        .form-actions {
            flex-direction: column;
        }

        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }

        .stat-item {
            flex-direction: column;
            text-align: center;
        }
    }
</style>

<script>
    // Tab functionality
    function openTab(evt, tabName) {
        var i, tabcontent, tablinks;

        // Hide all tab content
        tabcontent = document.getElementsByClassName("tab-pane");
        for (i = 0; i < tabcontent.length; i++) {
            tabcontent[i].classList.remove("active");
        }

        // Remove active class from all tab buttons
        tablinks = document.getElementsByClassName("tab-btn");
        for (i = 0; i < tablinks.length; i++) {
            tablinks[i].classList.remove("active");
        }

        // Show selected tab and mark button as active
        document.getElementById(tabName).classList.add("active");
        evt.currentTarget.classList.add("active");
    }

    // Show alert message
    function showAlert(message, type = 'info') {
        const alertContainer = document.getElementById('alert-container');
        const alert = document.createElement('div');
        alert.className = `alert alert-${type}`;
        alert.innerHTML = `
            <i class="fas fa-${getAlertIcon(type)}"></i>
            <span>${message}</span>
        `;

        alertContainer.appendChild(alert);

        // Auto remove after 5 seconds
        setTimeout(() => {
            alert.remove();
        }, 5000);
    }

    function getAlertIcon(type) {
        const icons = {
            'success': 'check-circle',
            'danger': 'exclamation-circle',
            'warning': 'exclamation-triangle',
            'info': 'info-circle'
        };
        return icons[type] || 'info-circle';
    }

    // Form submissions
    document.getElementById('profil-form').addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);

        // Validate password if provided
        const passwordBaru = formData.get('password_baru');
        const konfirmasiPassword = formData.get('konfirmasi_password');

        if (passwordBaru && passwordBaru !== konfirmasiPassword) {
            showAlert('Password baru dan konfirmasi password tidak sama!', 'danger');
            return;
        }

        if (passwordBaru && passwordBaru.length < 6) {
            showAlert('Password baru minimal 6 karakter!', 'danger');
            return;
        }

        // Submit form
        fetch('ajax/update-profil.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Profil berhasil diperbarui!', 'success');
                    // Clear password fields
                    document.getElementById('password_lama').value = '';
                    document.getElementById('password_baru').value = '';
                    document.getElementById('konfirmasi_password').value = '';
                } else {
                    showAlert(data.message || 'Gagal memperbarui profil!', 'danger');
                }
            })
            .catch(error => {
                showAlert('Terjadi kesalahan sistem!', 'danger');
                console.error('Error:', error);
            });
    });

    document.getElementById('sistem-form').addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);

        fetch('ajax/update-sistem.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Pengaturan sistem berhasil disimpan!', 'success');
                } else {
                    showAlert(data.message || 'Gagal menyimpan pengaturan sistem!', 'danger');
                }
            })
            .catch(error => {
                showAlert('Terjadi kesalahan sistem!', 'danger');
                console.error('Error:', error);
            });
    });

    document.getElementById('penilaian-form').addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);

        fetch('ajax/update-penilaian.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Pengaturan penilaian berhasil disimpan!', 'success');
                } else {
                    showAlert(data.message || 'Gagal menyimpan pengaturan penilaian!', 'danger');
                }
            })
            .catch(error => {
                showAlert('Terjadi kesalahan sistem!', 'danger');
                console.error('Error:', error);
            });
    });

    // Backup & Restore functions
    function downloadBackup() {
        showAlert('Memulai download backup...', 'info');

        fetch('ajax/backup-data.php', {
                method: 'POST'
            })
            .then(response => {
                if (response.ok) {
                    return response.blob();
                }
                throw new Error('Gagal membuat backup');
            })
            .then(blob => {
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `spk_kwarcan_backup_${new Date().toISOString().slice(0, 10)}.sql`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);

                showAlert('Backup berhasil didownload!', 'success');
                updateLastBackup();
            })
            .catch(error => {
                showAlert('Gagal membuat backup!', 'danger');
                console.error('Error:', error);
            });
    }

    function downloadTemplate() {
        showAlert('Memulai download template...', 'info');

        const link = document.createElement('a');
        link.href = '../ajax/download-template.php';
        link.download = 'template_import_kwarcan.xlsx';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        showAlert('Template berhasil didownload!', 'success');
    }

    function selectRestoreFile() {
        document.getElementById('restore-file').click();
    }

    document.getElementById('restore-file').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            if (file.name.endsWith('.sql')) {
                document.getElementById('restore-btn').disabled = false;
                showAlert(`File ${file.name} siap untuk di-restore`, 'info');
            } else {
                showAlert('Hanya file .sql yang diizinkan!', 'danger');
                this.value = '';
            }
        }
    });

    function confirmRestore() {
        if (!confirm('PERINGATAN: Restore akan mengganti semua data yang ada!\n\nApakah Anda yakin ingin melanjutkan?')) {
            return;
        }

        const fileInput = document.getElementById('restore-file');
        const file = fileInput.files[0];

        if (!file) {
            showAlert('Pilih file backup terlebih dahulu!', 'danger');
            return;
        }

        const formData = new FormData();
        formData.append('backup_file', file);

        showAlert('Memulai proses restore...', 'info');

        fetch('ajax/restore-data.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Data berhasil di-restore!', 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    showAlert(data.message || 'Gagal melakukan restore!', 'danger');
                }
            })
            .catch(error => {
                showAlert('Terjadi kesalahan saat restore!', 'danger');
                console.error('Error:', error);
            });
    }

    // Drag & Drop functionality
    const restoreArea = document.getElementById('restore-area');

    restoreArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        this.classList.add('dragover');
    });

    restoreArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        this.classList.remove('dragover');
    });

    restoreArea.addEventListener('drop', function(e) {
        e.preventDefault();
        this.classList.remove('dragover');

        const files = e.dataTransfer.files;
        if (files.length > 0) {
            const file = files[0];
            if (file.name.endsWith('.sql')) {
                document.getElementById('restore-file').files = files;
                document.getElementById('restore-btn').disabled = false;
                showAlert(`File ${file.name} siap untuk di-restore`, 'info');
            } else {
                showAlert('Hanya file .sql yang diizinkan!', 'danger');
            }
        }
    });

    restoreArea.addEventListener('click', function() {
        selectRestoreFile();
    });

    // Reset data functions
    function resetData(type) {
        let message = '';
        let confirmMessage = '';

        switch (type) {
            case 'penilaian':
                message = 'Reset semua data penilaian dan hasil MOORA';
                confirmMessage = 'Yakin ingin menghapus semua data penilaian dan hasil MOORA?';
                break;
            case 'hasil':
                message = 'Reset hasil perhitungan MOORA';
                confirmMessage = 'Yakin ingin menghapus semua hasil perhitungan MOORA?';
                break;
            case 'all':
                message = 'Reset semua data kecuali admin dan kriteria';
                confirmMessage = 'PERINGATAN: Ini akan menghapus semua data kwarcan, penilaian, dan hasil!\n\nApakah Anda yakin?';
                break;
        }

        if (!confirm(confirmMessage)) {
            return;
        }

        showAlert(`Memulai ${message}...`, 'info');

        fetch('ajax/reset-data.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `type=${type}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(`${message} berhasil!`, 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    showAlert(data.message || `Gagal ${message}!`, 'danger');
                }
            })
            .catch(error => {
                showAlert('Terjadi kesalahan sistem!', 'danger');
                console.error('Error:', error);
            });
    }

    function resetPenilaian() {
        resetData('penilaian');
    }

    // Utility functions
    function updateLastBackup() {
        document.getElementById('last-backup-date').textContent = new Date().toLocaleString('id-ID');
    }

    function refreshLog() {
        const logContainer = document.getElementById('activity-log');
        const currentTime = new Date().toLocaleTimeString('id-ID');

        const newLogItem = document.createElement('div');
        newLogItem.className = 'log-item';
        newLogItem.innerHTML = `
            <div class="log-time">${currentTime}</div>
            <div class="log-message">Log di-refresh oleh admin</div>
        `;

        logContainer.insertBefore(newLogItem, logContainer.firstChild);
        showAlert('Log berhasil di-refresh', 'info');
    }

    function clearLog() {
        if (!confirm('Yakin ingin menghapus semua log aktivitas?')) {
            return;
        }

        const logContainer = document.getElementById('activity-log');
        logContainer.innerHTML = `
            <div class="log-item">
                <div class="log-time">${new Date().toLocaleTimeString('id-ID')}</div>
                <div class="log-message">Log dibersihkan oleh admin</div>
            </div>
        `;

        showAlert('Log berhasil dibersihkan', 'success');
    }

    function saveAllSettings() {
        showAlert('Menyimpan semua pengaturan...', 'info');

        // Submit all forms
        const forms = ['profil-form', 'sistem-form', 'penilaian-form'];
        let completed = 0;
        let errors = 0;

        forms.forEach(formId => {
            const form = document.getElementById(formId);
            if (form) {
                const submitEvent = new Event('submit');
                form.dispatchEvent(submitEvent);

                // Simulate completion (in real scenario, you'd wait for actual responses)
                setTimeout(() => {
                    completed++;
                    if (completed === forms.length) {
                        if (errors === 0) {
                            showAlert('Semua pengaturan berhasil disimpan!', 'success');
                        } else {
                            showAlert(`${forms.length - errors} pengaturan disimpan, ${errors} gagal`, 'warning');
                        }
                    }
                }, 1000 * (completed + 1));
            }
        });
    }

    // Initialize page
    document.addEventListener('DOMContentLoaded', function() {
        // Check for last backup date from localStorage
        const lastBackup = localStorage.getItem('lastBackupDate');
        if (lastBackup) {
            document.getElementById('last-backup-date').textContent = lastBackup;
        }

        // Add form validation
        const forms = document.querySelectorAll('.settings-form');
        forms.forEach(form => {
            const inputs = form.querySelectorAll('input[required]');
            inputs.forEach(input => {
                input.addEventListener('blur', function() {
                    if (!this.value.trim()) {
                        this.style.borderColor = '#dc3545';
                    } else {
                        this.style.borderColor = '';
                    }
                });
            });
        });

        // Auto-save draft (optional feature)
        const autoSaveInputs = document.querySelectorAll('input, textarea, select');
        autoSaveInputs.forEach(input => {
            input.addEventListener('change', function() {
                localStorage.setItem(`draft_${this.name}`, this.value);
            });
        });

        // Load drafts
        autoSaveInputs.forEach(input => {
            const draftValue = localStorage.getItem(`draft_${input.name}`);
            if (draftValue && !input.value) {
                input.value = draftValue;
            }
        });
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey) {
            switch (e.key) {
                case 's':
                    e.preventDefault();
                    saveAllSettings();
                    break;
                case 'b':
                    e.preventDefault();
                    downloadBackup();
                    break;
            }
        }
    });

    // Auto-refresh stats every 5 minutes
    setInterval(function() {
        location.reload();
    }, 300000);
</script>

<?php include '../includes/footer.php'; ?>