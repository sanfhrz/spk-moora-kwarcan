<?php
    session_start();
    if (!isset($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit();
    }

    require_once '../config/database.php';

    $success = '';
    $error = '';

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        try {
            if ($_POST['action'] == 'save_single') {
                // Save single kwarcan assessment
                $kwarcan_id = $_POST['kwarcan_id'];
                $penilaian = $_POST['penilaian'];

                if (empty($kwarcan_id)) {
                    throw new Exception('Pilih kwarcan terlebih dahulu');
                }

                // Validate all criteria filled
                $stmt = $pdo->query("SELECT id FROM kriteria ORDER BY id");
                $kriteria_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

                foreach ($kriteria_ids as $kriteria_id) {
                    if (!isset($penilaian[$kriteria_id]) || $penilaian[$kriteria_id] === '') {
                        throw new Exception('Semua kriteria harus diisi');
                    }

                    $nilai = floatval($penilaian[$kriteria_id]);
                    if ($nilai < 0 || $nilai > 50) {
                        throw new Exception('Nilai harus antara 0-50');
                    }
                }

                $pdo->beginTransaction();

                // Delete existing assessments for this kwarcan
                $stmt = $pdo->prepare("DELETE FROM penilaian WHERE kwarcan_id = ?");
                $stmt->execute([$kwarcan_id]);

                // Insert new assessments
                $stmt = $pdo->prepare("INSERT INTO penilaian (kwarcan_id, kriteria_id, nilai) VALUES (?, ?, ?)");
                foreach ($penilaian as $kriteria_id => $nilai) {
                    $stmt->execute([$kwarcan_id, $kriteria_id, floatval($nilai)]);
                }

                $pdo->commit();
                $success = 'Penilaian berhasil disimpan';
            } elseif ($_POST['action'] == 'save_batch') {
                // Save batch assessments
                $batch_data = $_POST['batch_data'];

                if (empty($batch_data)) {
                    throw new Exception('Tidak ada data untuk disimpan');
                }

                $pdo->beginTransaction();

                foreach ($batch_data as $kwarcan_id => $penilaian) {
                    // Skip if no data for this kwarcan
                    if (empty(array_filter($penilaian))) continue;

                    // Validate all criteria filled for this kwarcan
                    $stmt = $pdo->query("SELECT id FROM kriteria ORDER BY id");
                    $kriteria_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

                    $has_incomplete = false;
                    foreach ($kriteria_ids as $kriteria_id) {
                        if (!isset($penilaian[$kriteria_id]) || $penilaian[$kriteria_id] === '') {
                            $has_incomplete = true;
                            break;
                        }
                    }

                    if ($has_incomplete) continue; // Skip incomplete assessments

                    // Delete existing assessments
                    $stmt = $pdo->prepare("DELETE FROM penilaian WHERE kwarcan_id = ?");
                    $stmt->execute([$kwarcan_id]);

                    // Insert new assessments
                    $stmt = $pdo->prepare("INSERT INTO penilaian (kwarcan_id, kriteria_id, nilai) VALUES (?, ?, ?)");
                    foreach ($penilaian as $kriteria_id => $nilai) {
                        if ($nilai !== '') {
                            $stmt->execute([$kwarcan_id, $kriteria_id, floatval($nilai)]);
                        }
                    }
                }

                $pdo->commit();
                $success = 'Penilaian batch berhasil disimpan';
            } elseif ($_POST['action'] == 'delete') {
                // Delete assessment
                $kwarcan_id = $_POST['kwarcan_id'];

                $stmt = $pdo->prepare("DELETE FROM penilaian WHERE kwarcan_id = ?");
                $stmt->execute([$kwarcan_id]);

                $success = 'Penilaian berhasil dihapus';
            } elseif ($_POST['action'] == 'import_excel') {
                // Handle Excel import (will implement later)
                $success = 'Fitur import Excel akan segera tersedia';
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollback();
            }
            $error = $e->getMessage();
        }
    }

    // Get statistics
    try {
        // Total kwarcan
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM kwarcan WHERE status = 'aktif'");
        $total_kwarcan = $stmt->fetch()['total'];

        // Total assessed
        $stmt = $pdo->query("SELECT COUNT(DISTINCT kwarcan_id) as total FROM penilaian");
        $total_assessed = $stmt->fetch()['total'];

        // Total criteria
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM kriteria");
        $total_kriteria = $stmt->fetch()['total'];

        // Progress percentage
        $progress = $total_kwarcan > 0 ? round(($total_assessed / $total_kwarcan) * 100, 1) : 0;

        // Get kwarcan list
        $stmt = $pdo->query("
            SELECT k.*, 
                (SELECT COUNT(*) FROM penilaian p WHERE p.kwarcan_id = k.id) as penilaian_count,
                (SELECT AVG(p.nilai) FROM penilaian p WHERE p.kwarcan_id = k.id) as rata_nilai
            FROM kwarcan k 
            WHERE k.status = 'aktif'
            ORDER BY k.kode_kwarcan ASC
        ");
        $kwarcan_list = $stmt->fetchAll();

        // Get criteria list
        $stmt = $pdo->query("SELECT * FROM kriteria ORDER BY kode_kriteria ASC");
        $kriteria_list = $stmt->fetchAll();

        // Get assessment matrix for display
        $stmt = $pdo->query("
            SELECT k.id as kwarcan_id, k.kode_kwarcan, k.nama_kwarcan, k.daerah,
                kr.id as kriteria_id, kr.kode_kriteria, kr.nama_kriteria,
                p.nilai
            FROM kwarcan k
            CROSS JOIN kriteria kr
            LEFT JOIN penilaian p ON k.id = p.kwarcan_id AND kr.id = p.kriteria_id
            WHERE k.status = 'aktif'
            ORDER BY k.kode_kwarcan ASC, kr.kode_kriteria ASC
        ");
        $matrix_data = $stmt->fetchAll();

        // Organize matrix data
        $assessment_matrix = [];
        foreach ($matrix_data as $row) {
            $assessment_matrix[$row['kwarcan_id']][$row['kriteria_id']] = $row['nilai'];
            if (!isset($assessment_matrix[$row['kwarcan_id']]['info'])) {
                $assessment_matrix[$row['kwarcan_id']]['info'] = [
                    'kode_kwarcan' => $row['kode_kwarcan'],
                    'nama_kwarcan' => $row['nama_kwarcan'],
                    'daerah' => $row['daerah']
                ];
            }
        }
    } catch (Exception $e) {
        $total_kwarcan = $total_assessed = $total_kriteria = 0;
        $progress = 0;
        $kwarcan_list = [];
        $kriteria_list = [];
        $assessment_matrix = [];
    }

    $page_title = "Penilaian - SPK MOORA Kwarcan";
?>

<?php include '../includes/header.php'; ?>

<div class="dashboard-container">
    <?php include '../includes/sidebar.php'; ?>

    <div class="content">
        <!-- Header -->
        <div class="content-header">
            <div class="header-title">
                <h1><i class="fas fa-star"></i> Penilaian Kwarcan</h1>
                <p>Input dan kelola penilaian untuk setiap kriteria pada masing-masing kwarcan</p>
            </div>
            <div class="header-actions">
                <button class="btn btn-success" onclick="openBatchModal()">
                    <i class="fas fa-table"></i> Input Batch
                </button>
                <button class="btn btn-info" onclick="openImportModal()">
                    <i class="fas fa-file-excel"></i> Import Excel
                </button>
                <button class="btn btn-primary" onclick="openSingleModal()">
                    <i class="fas fa-plus"></i> Input Penilaian
                </button>
            </div>
        </div>

        <!-- Statistics -->
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
                        <span>Aktif</span>
                    </div>
                </div>
            </div>

            <div class="stat-card success">
                <div class="stat-icon">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $total_assessed ?></h3>
                    <p>Sudah Dinilai</p>
                    <div class="stat-trend">
                        <i class="fas fa-percentage"></i>
                        <span><?= $progress ?>%</span>
                    </div>
                </div>
            </div>

            <div class="stat-card warning">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $total_kwarcan - $total_assessed ?></h3>
                    <p>Belum Dinilai</p>
                    <div class="stat-trend">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>Pending</span>
                    </div>
                </div>
            </div>

            <div class="stat-card info">
                <div class="stat-icon">
                    <i class="fas fa-list-check"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $total_kriteria ?></h3>
                    <p>Kriteria</p>
                    <div class="stat-trend">
                        <i class="fas fa-check"></i>
                        <span>Siap</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Assessment Matrix -->
        <div class="matrix-card">
            <div class="matrix-header">
                <h3><i class="fas fa-table"></i> Matrix Penilaian</h3>
                <div class="matrix-actions">
                    <button class="btn btn-secondary btn-sm" onclick="exportMatrix()">
                        <i class="fas fa-download"></i> Export
                    </button>
                    <button class="btn btn-info btn-sm" onclick="refreshMatrix()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
            </div>

            <div class="matrix-content">
                <?php if (!empty($assessment_matrix) && !empty($kriteria_list)): ?>
                    <div class="table-responsive">
                        <table class="matrix-table">
                            <thead>
                                <tr>
                                    <th rowspan="2" class="sticky-col">Kode</th>
                                    <th rowspan="2" class="sticky-col-2">Nama Kwarcan</th>
                                    <th rowspan="2" class="sticky-col-3">Daerah</th>
                                    <th colspan="<?= count($kriteria_list) ?>" class="criteria-header">Kriteria Penilaian</th>
                                    <th rowspan="2" class="action-col">Aksi</th>
                                </tr>
                                <tr>
                                    <?php foreach ($kriteria_list as $kriteria): ?>
                                        <th class="criteria-col" title="<?= htmlspecialchars($kriteria['nama_kriteria']) ?>">
                                            <?= htmlspecialchars($kriteria['kode_kriteria']) ?>
                                            <small>(<?= $kriteria['bobot'] ?>)</small>
                                        </th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($assessment_matrix as $kwarcan_id => $data): ?>
                                    <?php if (isset($data['info'])): ?>
                                        <tr class="matrix-row" data-kwarcan-id="<?= $kwarcan_id ?>">
                                            <td class="sticky-col kode-col">
                                                <?= htmlspecialchars($data['info']['kode_kwarcan']) ?>
                                            </td>
                                            <td class="sticky-col-2 nama-col">
                                                <strong><?= htmlspecialchars($data['info']['nama_kwarcan']) ?></strong>
                                            </td>
                                            <td class="sticky-col-3 daerah-col">
                                                <?= htmlspecialchars($data['info']['daerah']) ?>
                                            </td>
                                            <?php
                                            $has_complete_assessment = true;
                                            $total_nilai = 0;
                                            $count_nilai = 0;
                                            ?>
                                            <?php foreach ($kriteria_list as $kriteria): ?>
                                                <?php
                                                $nilai = $data[$kriteria['id']] ?? null;
                                                if ($nilai !== null) {
                                                    $total_nilai += $nilai;
                                                    $count_nilai++;
                                                } else {
                                                    $has_complete_assessment = false;
                                                }
                                                ?>
                                                <td class="nilai-col <?= $nilai !== null ? 'has-value' : 'no-value' ?>">
                                                    <?= $nilai !== null ? number_format($nilai, 1) : '-' ?>
                                                </td>
                                            <?php endforeach; ?>
                                            <td class="action-col">
                                                <div class="action-buttons">
                                                    <button class="btn btn-sm btn-warning" onclick="editAssessment(<?= $kwarcan_id ?>)" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <?php if ($has_complete_assessment): ?>
                                                        <button class="btn btn-sm btn-danger" onclick="deleteAssessment(<?= $kwarcan_id ?>, '<?= htmlspecialchars($data['info']['nama_kwarcan']) ?>')" title="Hapus">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                        <span class="assessment-status complete" title="Penilaian Lengkap">
                                                            <i class="fas fa-check-circle"></i>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="assessment-status incomplete" title="Penilaian Belum Lengkap">
                                                            <i class="fas fa-exclamation-circle"></i>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-table"></i>
                        <h4>Belum Ada Data Penilaian</h4>
                        <p>Mulai input penilaian untuk melihat matrix</p>
                        <button class="btn btn-primary" onclick="openSingleModal()">
                            <i class="fas fa-plus"></i> Input Penilaian Pertama
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Assessment Cards -->
        <div class="quick-assessment">
            <h3><i class="fas fa-bolt"></i> Penilaian Cepat</h3>
            <div class="assessment-grid">
                <?php foreach ($kwarcan_list as $kwarcan): ?>
                    <div class="assessment-card <?= $kwarcan['penilaian_count'] > 0 ? 'assessed' : 'not-assessed' ?>">
                        <div class="card-header">
                            <div class="kwarcan-info">
                                <h4><?= htmlspecialchars($kwarcan['kode_kwarcan']) ?></h4>
                                <p><?= htmlspecialchars($kwarcan['nama_kwarcan']) ?></p>
                                <small><?= htmlspecialchars($kwarcan['daerah']) ?></small>
                            </div>
                            <div class="assessment-status-badge">
                                <?php if ($kwarcan['penilaian_count'] >= $total_kriteria): ?>
                                    <span class="badge badge-success">
                                        <i class="fas fa-check"></i> Lengkap
                                    </span>
                                <?php elseif ($kwarcan['penilaian_count'] > 0): ?>
                                    <span class="badge badge-warning">
                                        <i class="fas fa-clock"></i> Parsial
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-danger">
                                        <i class="fas fa-times"></i> Belum
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="card-content">
                            <?php if ($kwarcan['penilaian_count'] > 0): ?>
                                <div class="assessment-summary">
                                    <div class="summary-item">
                                        <label>Kriteria Dinilai</label>
                                        <span><?= $kwarcan['penilaian_count'] ?>/<?= $total_kriteria ?></span>
                                    </div>
                                    <div class="summary-item">
                                        <label>Rata-rata Nilai</label>
                                        <span><?= $kwarcan['rata_nilai'] ? number_format($kwarcan['rata_nilai'], 1) : '-' ?></span>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="no-assessment">
                                    <i class="fas fa-star-o"></i>
                                    <p>Belum ada penilaian</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="card-actions">
                            <button class="btn btn-primary btn-sm" onclick="editAssessment(<?= $kwarcan['id'] ?>)">
                                <i class="fas fa-<?= $kwarcan['penilaian_count'] > 0 ? 'edit' : 'plus' ?>"></i>
                                <?= $kwarcan['penilaian_count'] > 0 ? 'Edit' : 'Input' ?>
                            </button>
                            <?php if ($kwarcan['penilaian_count'] > 0): ?>
                                <button class="btn btn-danger btn-sm" onclick="deleteAssessment(<?= $kwarcan['id'] ?>, '<?= htmlspecialchars($kwarcan['nama_kwarcan']) ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Single Assessment Modal -->
<div id="singleModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-star"></i> <span id="modalTitle">Input Penilaian</span></h3>
            <button class="modal-close" onclick="closeSingleModal()">&times;</button>
        </div>

        <form id="singleForm" method="POST">
            <input type="hidden" name="action" value="save_single">
            <input type="hidden" id="editKwarcanId" name="kwarcan_id" value="">

            <div class="modal-body">
                <div class="form-group">
                    <label for="kwarcanSelect">Pilih Kwarcan</label>
                    <select id="kwarcanSelect" name="kwarcan_id" class="form-control" required>
                        <option value="">-- Pilih Kwarcan --</option>
                        <?php foreach ($kwarcan_list as $kwarcan): ?>
                            <option value="<?= $kwarcan['id'] ?>" data-nama="<?= htmlspecialchars($kwarcan['nama_kwarcan']) ?>" data-daerah="<?= htmlspecialchars($kwarcan['daerah']) ?>">
                                <?= htmlspecialchars($kwarcan['kode_kwarcan']) ?> - <?= htmlspecialchars($kwarcan['nama_kwarcan']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="selectedKwarcanInfo" class="kwarcan-info-card" style="display: none;">
                    <div class="info-header">
                        <i class="fas fa-user"></i>
                        <div>
                            <h4 id="selectedNama"></h4>
                            <p id="selectedDaerah"></p>
                        </div>
                    </div>
                </div>

                <div class="criteria-section">
                    <h4><i class="fas fa-list-check"></i> Penilaian Kriteria</h4>
                    <p class="section-desc">Berikan nilai 0-50 untuk setiap kriteria</p>

                    <div class="criteria-grid">
                        <?php foreach ($kriteria_list as $kriteria): ?>
                            <div class="criteria-item">
                                <div class="criteria-header">
                                    <label for="nilai_<?= $kriteria['id'] ?>">
                                        <?= htmlspecialchars($kriteria['kode_kriteria']) ?> - <?= htmlspecialchars($kriteria['nama_kriteria']) ?>
                                    </label>
                                    <span class="criteria-weight">Bobot: <?= $kriteria['bobot'] ?></span>
                                </div>

                                <div class="input-group">
                                    <input type="number"
                                        id="nilai_<?= $kriteria['id'] ?>"
                                        name="penilaian[<?= $kriteria['id'] ?>]"
                                        class="form-control nilai-input"
                                        min="0"
                                        max="50"
                                        step="0.1"
                                        placeholder="0-50"
                                        required>
                                    <div class="input-slider">
                                        <input type="range"
                                            id="slider_<?= $kriteria['id'] ?>"
                                            class="nilai-slider"
                                            min="0"
                                            max="50"
                                            step="0.1"
                                            value="0">
                                    </div>
                                </div>

                                <?php if ($kriteria['keterangan']): ?>
                                    <small class="criteria-desc"><?= htmlspecialchars($kriteria['keterangan']) ?></small>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeSingleModal()">
                    <i class="fas fa-times"></i> Batal
                </button>
                <button type="button" class="btn btn-warning" onclick="resetForm()">
                    <i class="fas fa-undo"></i> Reset
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan Penilaian
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Batch Assessment Modal -->
<div id="batchModal" class="modal modal-large">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-table"></i> Input Penilaian Batch</h3>
            <button class="modal-close" onclick="closeBatchModal()">&times;</button>
        </div>

        <form id="batchForm" method="POST">
            <input type="hidden" name="action" value="save_batch">

            <div class="modal-body">
                <div class="batch-controls">
                    <div class="control-group">
                        <button type="button" class="btn btn-info btn-sm" onclick="fillRandomValues()">
                            <i class="fas fa-dice"></i> Isi Random
                        </button>
                        <button type="button" class="btn btn-warning btn-sm" onclick="clearAllValues()">
                            <i class="fas fa-eraser"></i> Kosongkan Semua
                        </button>
                        <button type="button" class="btn btn-success btn-sm" onclick="copyFromTemplate()">
                            <i class="fas fa-copy"></i> Copy Template
                        </button>
                    </div>

                    <div class="progress-info">
                        <span>Progress: <strong id="batchProgress">0/<?= count($kwarcan_list) ?></strong></span>
                    </div>
                </div>

                <div class="batch-table-container">
                    <table class="batch-table">
                        <thead>
                            <tr>
                                <th class="sticky-col">Kode</th>
                                <th class="sticky-col-2">Nama Kwarcan</th>
                                <?php foreach ($kriteria_list as $kriteria): ?>
                                    <th class="criteria-col" title="<?= htmlspecialchars($kriteria['nama_kriteria']) ?>">
                                        <?= htmlspecialchars($kriteria['kode_kriteria']) ?>
                                        <small>(<?= $kriteria['bobot'] ?>)</small>
                                    </th>
                                <?php endforeach; ?>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($kwarcan_list as $kwarcan): ?>
                                <tr class="batch-row" data-kwarcan-id="<?= $kwarcan['id'] ?>">
                                    <td class="sticky-col">
                                        <?= htmlspecialchars($kwarcan['kode_kwarcan']) ?>
                                    </td>
                                    <td class="sticky-col-2">
                                        <strong><?= htmlspecialchars($kwarcan['nama_kwarcan']) ?></strong>
                                        <small><?= htmlspecialchars($kwarcan['daerah']) ?></small>
                                    </td>
                                    <?php foreach ($kriteria_list as $kriteria): ?>
                                        <td>
                                            <input type="number"
                                                name="batch_data[<?= $kwarcan['id'] ?>][<?= $kriteria['id'] ?>]"
                                                class="batch-input"
                                                min="0"
                                                max="50"
                                                step="0.1"
                                                placeholder="0-50"
                                                data-kwarcan="<?= $kwarcan['id'] ?>"
                                                data-kriteria="<?= $kriteria['id'] ?>">
                                        </td>
                                    <?php endforeach; ?>
                                    <td class="status-col">
                                        <span class="batch-status incomplete">
                                            <i class="fas fa-clock"></i> Belum
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeBatchModal()">
                    <i class="fas fa-times"></i> Batal
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan Semua
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Import Excel Modal -->
<div id="importModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-file-excel"></i> Import dari Excel</h3>
            <button class="modal-close" onclick="closeImportModal()">&times;</button>
        </div>

        <div class="modal-body">
            <div class="import-section">
                <div class="upload-area" id="uploadArea">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <h4>Upload File Excel</h4>
                    <p>Drag & drop file Excel atau klik untuk browse</p>
                    <small>Format yang didukung: .xlsx, .xls (Max: 5MB)</small>
                    <input type="file" id="excelFile" accept=".xlsx,.xls" style="display: none;">
                </div>

                <div class="template-section">
                    <h4><i class="fas fa-download"></i> Download Template</h4>
                    <p>Download template Excel untuk format yang benar</p>
                    <button class="btn btn-success" onclick="downloadTemplate()">
                        <i class="fas fa-file-excel"></i> Download Template
                    </button>
                </div>

                <div class="import-preview" id="importPreview" style="display: none;">
                    <h4><i class="fas fa-eye"></i> Preview Data</h4>
                    <div id="previewContent"></div>
                </div>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeImportModal()">
                <i class="fas fa-times"></i> Batal
            </button>
            <button type="button" class="btn btn-primary" id="importBtn" onclick="processImport()" style="display: none;">
                <i class="fas fa-upload"></i> Import Data
            </button>
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

    .header-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
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
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
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
        margin-bottom: 10px;
    }

    .stat-trend {
        display: flex;
        align-items: center;
        gap: 8px;
        color: #28a745;
        font-size: 0.9rem;
        font-weight: 600;
    }

    /* Matrix Styles */
    .matrix-card {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(20px);
        border-radius: 20px;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        margin-bottom: 40px;
        overflow: hidden;
    }

    .matrix-header {
        padding: 25px 30px;
        border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }

    .matrix-header h3 {
        color: #333;
        font-size: 1.3rem;
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 0;
    }

    .matrix-actions {
        display: flex;
        gap: 10px;
    }

    .matrix-content {
        padding: 0;
    }

    .table-responsive {
        overflow-x: auto;
        max-height: 600px;
        overflow-y: auto;
    }

    .matrix-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.9rem;
    }

    .matrix-table th,
    .matrix-table td {
        padding: 12px 8px;
        text-align: center;
        border: 1px solid rgba(0, 0, 0, 0.1);
        white-space: nowrap;
    }

    .matrix-table th {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        font-weight: 600;
        position: sticky;
        top: 0;
        z-index: 10;
    }

    .sticky-col,
    .sticky-col-2,
    .sticky-col-3 {
        position: sticky;
        left: 0;
        background: white;
        z-index: 5;
        box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
    }

    .sticky-col-2 {
        left: 80px;
    }

    .sticky-col-3 {
        left: 200px;
    }

    .matrix-table th.sticky-col,
    .matrix-table th.sticky-col-2,
    .matrix-table th.sticky-col-3 {
        background: linear-gradient(135deg, #667eea, #764ba2);
        z-index: 11;
    }

    .criteria-header {
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.8), rgba(118, 75, 162, 0.8));
    }

    .criteria-col {
        min-width: 80px;
        background: rgba(102, 126, 234, 0.05);
    }

    .criteria-col small {
        display: block;
        font-size: 0.75rem;
        opacity: 0.8;
    }

    .kode-col {
        font-weight: 700;
        color: #667eea;
        min-width: 80px;
    }

    .nama-col {
        text-align: left;
        min-width: 200px;
        max-width: 200px;
    }

    .nama-col strong {
        display: block;
        color: #333;
        margin-bottom: 2px;
    }

    .daerah-col {
        text-align: left;
        min-width: 150px;
        color: #666;
        font-size: 0.85rem;
    }

    .nilai-col {
        font-weight: 600;
        min-width: 60px;
    }

    .nilai-col.has-value {
        background: rgba(40, 167, 69, 0.1);
        color: #28a745;
    }

    .nilai-col.no-value {
        background: rgba(220, 53, 69, 0.1);
        color: #dc3545;
    }

    .action-col {
        min-width: 120px;
        background: rgba(0, 0, 0, 0.02);
    }

    .action-buttons {
        display: flex;
        gap: 5px;
        align-items: center;
        justify-content: center;
    }

    .assessment-status {
        width: 20px;
        height: 20px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8rem;
    }

    .assessment-status.complete {
        background: #28a745;
        color: white;
    }

    .assessment-status.incomplete {
        background: #ffc107;
        color: white;
    }

    /* Quick Assessment Grid */
    .quick-assessment {
        margin-bottom: 40px;
    }

    .quick-assessment h3 {
        color: #333;
        font-size: 1.3rem;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .assessment-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
    }

    .assessment-card {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(20px);
        border-radius: 15px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
        overflow: hidden;
        border-left: 4px solid #ddd;
    }

    .assessment-card.assessed {
        border-left-color: #28a745;
    }

    .assessment-card.not-assessed {
        border-left-color: #dc3545;
    }

    .assessment-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
    }

    .assessment-card .card-header {
        padding: 20px;
        border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
    }

    .kwarcan-info h4 {
        color: #333;
        font-size: 1.1rem;
        margin-bottom: 5px;
    }

    .kwarcan-info p {
        color: #666;
        font-size: 0.9rem;
        margin-bottom: 3px;
        font-weight: 600;
    }

    .kwarcan-info small {
        color: #999;
        font-size: 0.8rem;
    }

    .assessment-status-badge .badge {
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .badge-success {
        background: rgba(40, 167, 69, 0.1);
        color: #28a745;
        border: 1px solid rgba(40, 167, 69, 0.2);
    }

    .badge-warning {
        background: rgba(255, 193, 7, 0.1);
        color: #ffc107;
        border: 1px solid rgba(255, 193, 7, 0.2);
    }

    .badge-danger {
        background: rgba(220, 53, 69, 0.1);
        color: #dc3545;
        border: 1px solid rgba(220, 53, 69, 0.2);
    }

    .assessment-card .card-content {
        padding: 20px;
    }

    .assessment-summary {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }

    .summary-item {
        text-align: center;
    }

    .summary-item label {
        display: block;
        color: #666;
        font-size: 0.8rem;
        margin-bottom: 5px;
        font-weight: 600;
    }

    .summary-item span {
        color: #333;
        font-size: 1.1rem;
        font-weight: 700;
    }

    .no-assessment {
        text-align: center;
        color: #999;
        padding: 20px 0;
    }

    .no-assessment i {
        font-size: 2rem;
        margin-bottom: 10px;
        display: block;
    }

    .card-actions {
        padding: 15px 20px;
        background: rgba(0, 0, 0, 0.02);
        display: flex;
        gap: 10px;
        justify-content: center;
    }

    /* Modal Styles */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(5px);
    }

    .modal.show {
        display: flex;
        align-items: center;
        justify-content: center;
        animation: fadeIn 0.3s ease;
    }

    .modal-large .modal-content {
        max-width: 95%;
        width: 1200px;
        max-height: 90vh;
    }

    .modal-content {
        background: white;
        border-radius: 20px;
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
        max-width: 600px;
        width: 90%;
        max-height: 80vh;
        overflow: hidden;
        animation: slideInUp 0.3s ease;
    }

    .modal-header {
        padding: 25px 30px;
        border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
    }

    .modal-header h3 {
        margin: 0;
        font-size: 1.3rem;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .modal-close {
        background: none;
        border: none;
        font-size: 1.5rem;
        color: white;
        cursor: pointer;
        padding: 5px;
        border-radius: 5px;
        transition: background 0.3s ease;
    }

    .modal-close:hover {
        background: rgba(255, 255, 255, 0.2);
    }

    .modal-body {
        padding: 30px;
        overflow-y: auto;
        max-height: calc(80vh - 140px);
    }

    .modal-footer {
        padding: 20px 30px;
        border-top: 1px solid rgba(0, 0, 0, 0.1);
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        background: rgba(0, 0, 0, 0.02);
    }

    /* Form Styles */
    .form-group {
        margin-bottom: 25px;
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
        border: 2px solid rgba(0, 0, 0, 0.1);
        border-radius: 10px;
        font-size: 1rem;
        transition: all 0.3s ease;
        background: white;
    }

    .form-control:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .kwarcan-info-card {
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
        border-radius: 15px;
        padding: 20px;
        margin-bottom: 25px;
        border: 2px solid rgba(102, 126, 234, 0.2);
    }

    .info-header {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .info-header i {
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

    .info-header h4 {
        color: #333;
        font-size: 1.2rem;
        margin-bottom: 5px;
    }

    .info-header p {
        color: #666;
        font-size: 0.9rem;
        margin: 0;
    }

    .criteria-section {
        margin-top: 30px;
    }

    .criteria-section h4 {
        color: #333;
        font-size: 1.2rem;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .section-desc {
        color: #666;
        font-size: 0.9rem;
        margin-bottom: 25px;
    }

    .criteria-grid {
        display: grid;
        gap: 25px;
    }

    .criteria-item {
        background: rgba(0, 0, 0, 0.02);
        border-radius: 15px;
        padding: 20px;
        border: 2px solid transparent;
        transition: all 0.3s ease;
    }

    .criteria-item:hover {
        border-color: rgba(102, 126, 234, 0.2);
        background: rgba(102, 126, 234, 0.05);
    }

    .criteria-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 15px;
        flex-wrap: wrap;
        gap: 10px;
    }

    .criteria-header label {
        color: #333;
        font-weight: 600;
        font-size: 1rem;
        margin: 0;
        flex: 1;
    }

    .criteria-weight {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
    }

    .input-group {
        display: grid;
        grid-template-columns: 1fr 2fr;
        gap: 15px;
        align-items: center;
    }

    .nilai-input {
        font-size: 1.1rem;
        font-weight: 600;
        text-align: center;
    }

    .input-slider {
        position: relative;
    }

    .nilai-slider {
        width: 100%;
        height: 8px;
        border-radius: 4px;
        background: rgba(0, 0, 0, 0.1);
        outline: none;
        cursor: pointer;
        -webkit-appearance: none;
    }

    .nilai-slider::-webkit-slider-thumb {
        -webkit-appearance: none;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea, #764ba2);
        cursor: pointer;
        box-shadow: 0 2px 10px rgba(102, 126, 234, 0.3);
    }

    .nilai-slider::-moz-range-thumb {
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea, #764ba2);
        cursor: pointer;
        border: none;
        box-shadow: 0 2px 10px rgba(102, 126, 234, 0.3);
    }

    .criteria-desc {
        color: #666;
        font-size: 0.8rem;
        font-style: italic;
        margin-top: 8px;
        display: block;
    }

    /* Batch Table Styles */
    .batch-controls {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        flex-wrap: wrap;
        gap: 15px;
    }

    .control-group {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .progress-info {
        color: #666;
        font-size: 0.9rem;
    }

    .batch-table-container {
        overflow: auto;
        max-height: 500px;
        border: 1px solid rgba(0, 0, 0, 0.1);
        border-radius: 10px;
    }

    .batch-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.9rem;
    }

    .batch-table th,
    .batch-table td {
        padding: 10px 8px;
        text-align: center;
        border: 1px solid rgba(0, 0, 0, 0.1);
        white-space: nowrap;
    }

    .batch-table th {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        font-weight: 600;
        position: sticky;
        top: 0;
        z-index: 10;
    }

    .batch-table .sticky-col,
    .batch-table .sticky-col-2 {
        position: sticky;
        left: 0;
        background: white;
        z-index: 5;
        box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
    }

    .batch-table .sticky-col-2 {
        left: 80px;
    }

    .batch-table th.sticky-col,
    .batch-table th.sticky-col-2 {
        background: linear-gradient(135deg, #667eea, #764ba2);
        z-index: 11;
    }

    .batch-input {
        width: 70px;
        padding: 6px 8px;
        border: 1px solid rgba(0, 0, 0, 0.2);
        border-radius: 5px;
        text-align: center;
        font-size: 0.9rem;
        transition: all 0.3s ease;
    }

    .batch-input:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.2);
    }

    .batch-status {
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .batch-status.complete {
        background: rgba(40, 167, 69, 0.1);
        color: #28a745;
    }

    .batch-status.incomplete {
        background: rgba(255, 193, 7, 0.1);
        color: #ffc107;
    }

    /* Import Styles */
    .import-section {
        display: grid;
        gap: 30px;
    }

    .upload-area {
        border: 3px dashed rgba(102, 126, 234, 0.3);
        border-radius: 15px;
        padding: 40px 20px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
        background: rgba(102, 126, 234, 0.05);
    }

    .upload-area:hover {
        border-color: #667eea;
        background: rgba(102, 126, 234, 0.1);
    }

    .upload-area.dragover {
        border-color: #667eea;
        background: rgba(102, 126, 234, 0.15);
        transform: scale(1.02);
    }

    .upload-area i {
        font-size: 3rem;
        color: #667eea;
        margin-bottom: 15px;
        display: block;
    }

    .upload-area h4 {
        color: #333;
        font-size: 1.2rem;
        margin-bottom: 10px;
    }

    .upload-area p {
        color: #666;
        font-size: 1rem;
        margin-bottom: 5px;
    }

    .upload-area small {
        color: #999;
        font-size: 0.8rem;
    }

    .template-section {
        background: rgba(40, 167, 69, 0.05);
        border-radius: 15px;
        padding: 25px;
        text-align: center;
        border: 2px solid rgba(40, 167, 69, 0.2);
    }

    .template-section h4 {
        color: #333;
        font-size: 1.1rem;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }

    .template-section p {
        color: #666;
        font-size: 0.9rem;
        margin-bottom: 20px;
    }

    .import-preview {
        background: rgba(0, 0, 0, 0.02);
        border-radius: 15px;
        padding: 25px;
        border: 2px solid rgba(0, 0, 0, 0.1);
    }

    .import-preview h4 {
        color: #333;
        font-size: 1.1rem;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    /* Button Styles */
    .btn {
        padding: 10px 20px;
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
        text-align: center;
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

    .btn-secondary {
        background: linear-gradient(135deg, #6c757d, #5a6268);
        color: white;
        box-shadow: 0 4px 15px rgba(108, 117, 125, 0.3);
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

    .btn:active {
        transform: translateY(0);
    }

    .btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #999;
    }

    .empty-state i {
        font-size: 4rem;
        color: #ddd;
        margin-bottom: 20px;
        display: block;
    }

    .empty-state h4 {
        color: #666;
        font-size: 1.3rem;
        margin-bottom: 10px;
    }

    .empty-state p {
        color: #999;
        font-size: 1rem;
        margin-bottom: 25px;
    }

    /* Animations */
    @keyframes fadeIn {
        from {
            opacity: 0;
        }

        to {
            opacity: 1;
        }
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
        .assessment-grid {
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
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

        .header-actions {
            width: 100%;
            justify-content: flex-start;
        }

        .stats-grid {
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .assessment-grid {
            grid-template-columns: 1fr;
        }

        .modal-content {
            width: 95%;
            margin: 20px;
        }

        .modal-body {
            padding: 20px;
        }

        .input-group {
            grid-template-columns: 1fr;
            gap: 10px;
        }

        .batch-controls {
            flex-direction: column;
            align-items: flex-start;
        }

        .table-responsive {
            font-size: 0.8rem;
        }

        .matrix-table th,
        .matrix-table td {
            padding: 8px 4px;
        }
    }

    @media (max-width: 480px) {
        .header-title h1 {
            font-size: 1.5rem;
        }

        .stat-content h3 {
            font-size: 2rem;
        }

        .btn {
            padding: 8px 12px;
            font-size: 0.8rem;
        }

        .criteria-header {
            flex-direction: column;
            align-items: flex-start;
        }
    }

    /* Loading States */
    .loading {
        opacity: 0.7;
        pointer-events: none;
    }

    .loading::after {
        content: '';
        position: absolute;
        width: 20px;
        height: 20px;
        border: 2px solid transparent;
        border-top: 2px solid currentColor;
        border-radius: 50%;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% {
            transform: translateY(-50%) rotate(0deg);
        }

        100% {
            transform: translateY(-50%) rotate(360deg);
        }
    }

    /* Success/Error Messages */
    .alert {
        padding: 15px 20px;
        border-radius: 10px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 600;
    }

    .alert-success {
        background: rgba(40, 167, 69, 0.1);
        color: #28a745;
        border: 1px solid rgba(40, 167, 69, 0.2);
    }

    .alert-danger {
        background: rgba(220, 53, 69, 0.1);
        color: #dc3545;
        border: 1px solid rgba(220, 53, 69, 0.2);
    }
</style>

<script>
    // Global variables
    let currentEditingKwarcan = null;
    const kriteriaCount = <?= count($kriteria_list) ?>;
    const kwarcanCount = <?= count($kwarcan_list) ?>;

    // Show success/error messages
    <?php if ($success): ?>
        showNotification('<?= addslashes($success) ?>', 'success');
    <?php endif; ?>

    <?php if ($error): ?>
        showNotification('<?= addslashes($error) ?>', 'error');
    <?php endif; ?>

    // Notification function
    function showNotification(message, type) {
        const notification = document.createElement('div');
        notification.className = `alert alert-${type === 'success' ? 'success' : 'danger'}`;
        notification.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i>
            ${message}
        `;

        document.querySelector('.content').insertBefore(notification, document.querySelector('.content').firstChild);

        setTimeout(() => {
            notification.style.opacity = '0';
            notification.style.transform = 'translateY(-10px)';
            setTimeout(() => notification.remove(), 300);
        }, 5000);
    }

    // Single Assessment Modal Functions
    function openSingleModal(kwarcanId = null) {
        const modal = document.getElementById('singleModal');
        const form = document.getElementById('singleForm');
        const title = document.getElementById('modalTitle');
        const select = document.getElementById('kwarcanSelect');
        const editId = document.getElementById('editKwarcanId');

        if (kwarcanId) {
            // Edit mode
            title.textContent = 'Edit Penilaian';
            select.value = kwarcanId;
            select.disabled = true;
            editId.value = kwarcanId;
            currentEditingKwarcan = kwarcanId;

            // Load existing values
            loadExistingAssessment(kwarcanId);
            updateKwarcanInfo();
        } else {
            // Add mode
            title.textContent = 'Input Penilaian';
            form.reset();
            select.disabled = false;
            editId.value = '';
            currentEditingKwarcan = null;
            document.getElementById('selectedKwarcanInfo').style.display = 'none';
        }

        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeSingleModal() {
        const modal = document.getElementById('singleModal');
        modal.classList.remove('show');
        document.body.style.overflow = 'auto';
        currentEditingKwarcan = null;
    }

    function editAssessment(kwarcanId) {
        openSingleModal(kwarcanId);
    }

    function deleteAssessment(kwarcanId, namaKwarcan) {
        if (confirm(`Apakah Anda yakin ingin menghapus penilaian untuk ${namaKwarcan}?`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="kwarcan_id" value="${kwarcanId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }

    // Load existing assessment data
    function loadExistingAssessment(kwarcanId) {
        fetch(`ajax/get-assessment.php?kwarcan_id=${kwarcanId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    data.assessment.forEach(item => {
                        const input = document.getElementById(`nilai_${item.kriteria_id}`);
                        const slider = document.getElementById(`slider_${item.kriteria_id}`);
                        if (input && slider) {
                            input.value = item.nilai;
                            slider.value = item.nilai;
                        }
                    });
                }
            })
            .catch(error => console.error('Error loading assessment:', error));
    }

    // Update kwarcan info display
    function updateKwarcanInfo() {
        const select = document.getElementById('kwarcanSelect');
        const infoCard = document.getElementById('selectedKwarcanInfo');
        const namaSpan = document.getElementById('selectedNama');
        const daerahSpan = document.getElementById('selectedDaerah');

        if (select.value) {
            const option = select.selectedOptions[0];
            namaSpan.textContent = option.dataset.nama;
            daerahSpan.textContent = option.dataset.daerah;
            infoCard.style.display = 'block';
        } else {
            infoCard.style.display = 'none';
        }
    }

    // Sync input and slider values
    function syncInputSlider(inputId, sliderId) {
        const input = document.getElementById(inputId);
        const slider = document.getElementById(sliderId);

        input.addEventListener('input', function() {
            slider.value = this.value;
        });

        slider.addEventListener('input', function() {
            input.value = this.value;
        });
    }

    // Reset form
    function resetForm() {
        if (confirm('Apakah Anda yakin ingin mereset semua nilai?')) {
            const form = document.getElementById('singleForm');
            const inputs = form.querySelectorAll('.nilai-input');
            const sliders = form.querySelectorAll('.nilai-slider');

            inputs.forEach(input => input.value = '');
            sliders.forEach(slider => slider.value = 0);
        }
    }

    // Batch Assessment Modal Functions
    function openBatchModal() {
        const modal = document.getElementById('batchModal');
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
        updateBatchProgress();
    }

    function closeBatchModal() {
        const modal = document.getElementById('batchModal');
        modal.classList.remove('show');
        document.body.style.overflow = 'auto';
    }

    function updateBatchProgress() {
        const rows = document.querySelectorAll('.batch-row');
        let completedCount = 0;

        rows.forEach(row => {
            const inputs = row.querySelectorAll('.batch-input');
            const statusSpan = row.querySelector('.batch-status');
            let filledCount = 0;

            inputs.forEach(input => {
                if (input.value && input.value.trim() !== '') {
                    filledCount++;
                }
            });

            if (filledCount === inputs.length) {
                statusSpan.className = 'batch-status complete';
                statusSpan.innerHTML = '<i class="fas fa-check"></i> Lengkap';
                completedCount++;
            } else if (filledCount > 0) {
                statusSpan.className = 'batch-status incomplete';
                statusSpan.innerHTML = '<i class="fas fa-clock"></i> Parsial';
            } else {
                statusSpan.className = 'batch-status incomplete';
                statusSpan.innerHTML = '<i class="fas fa-clock"></i> Belum';
            }
        });

        document.getElementById('batchProgress').textContent = `${completedCount}/${kwarcanCount}`;
    }

    function fillRandomValues() {
        if (confirm('Apakah Anda yakin ingin mengisi semua nilai dengan angka random?')) {
            const inputs = document.querySelectorAll('.batch-input');
            inputs.forEach(input => {
                input.value = (Math.random() * 50).toFixed(1);
            });
            updateBatchProgress();
        }
    }

    function clearAllValues() {
        if (confirm('Apakah Anda yakin ingin mengosongkan semua nilai?')) {
            const inputs = document.querySelectorAll('.batch-input');
            inputs.forEach(input => {
                input.value = '';
            });
            updateBatchProgress();
        }
    }

    function copyFromTemplate() {
        // This would copy values from a predefined template
        showNotification('Fitur copy template akan segera tersedia', 'info');
    }

    // Import Modal Functions
    function openImportModal() {
        const modal = document.getElementById('importModal');
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeImportModal() {
        const modal = document.getElementById('importModal');
        modal.classList.remove('show');
        document.body.style.overflow = 'auto';
    }

    function downloadTemplate() {
        // Generate and download Excel template
        window.location.href = 'ajax/download-template.php';
    }

    function processImport() {
        const fileInput = document.getElementById('excelFile');
        if (!fileInput.files[0]) {
            showNotification('Pilih file Excel terlebih dahulu', 'error');
            return;
        }

        const formData = new FormData();
        formData.append('excel_file', fileInput.files[0]);
        formData.append('action', 'import_excel');

        fetch('ajax/import-excel.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Data berhasil diimport', 'success');
                    closeImportModal();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotification(data.message || 'Gagal import data', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Terjadi kesalahan saat import', 'error');
            });
    }

    // Matrix functions
    function exportMatrix() {
        window.location.href = 'ajax/export-matrix.php';
    }

    function refreshMatrix() {
        location.reload();
    }

    // Initialize everything when DOM is loaded
    document.addEventListener('DOMContentLoaded', function() {
        // Setup kwarcan select change handler
        const kwarcanSelect = document.getElementById('kwarcanSelect');
        if (kwarcanSelect) {
            kwarcanSelect.addEventListener('change', updateKwarcanInfo);
        }

        // Setup input-slider synchronization
        <?php foreach ($kriteria_list as $kriteria): ?>
            syncInputSlider('nilai_<?= $kriteria['id'] ?>', 'slider_<?= $kriteria['id'] ?>');
        <?php endforeach; ?>

        // Setup batch input change handlers
        const batchInputs = document.querySelectorAll('.batch-input');
        batchInputs.forEach(input => {
            input.addEventListener('input', updateBatchProgress);
        });

        // Setup drag and drop for file upload
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('excelFile');

        if (uploadArea && fileInput) {
            uploadArea.addEventListener('click', () => fileInput.click());

            uploadArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                uploadArea.classList.add('dragover');
            });

            uploadArea.addEventListener('dragleave', () => {
                uploadArea.classList.remove('dragover');
            });

            uploadArea.addEventListener('drop', (e) => {
                e.preventDefault();
                uploadArea.classList.remove('dragover');

                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    fileInput.files = files;
                    handleFileSelect(files[0]);
                }
            });

            fileInput.addEventListener('change', (e) => {
                if (e.target.files.length > 0) {
                    handleFileSelect(e.target.files[0]);
                }
            });
        }

        // Setup form submissions
        const singleForm = document.getElementById('singleForm');
        if (singleForm) {
            singleForm.addEventListener('submit', function(e) {
                e.preventDefault();

                // Validate form
                const kwarcanId = document.getElementById('kwarcanSelect').value ||
                    document.getElementById('editKwarcanId').value;

                if (!kwarcanId) {
                    showNotification('Pilih Kwarcan terlebih dahulu', 'error');
                    return;
                }

                // Check if all criteria are filled
                const inputs = this.querySelectorAll('.nilai-input');
                let allFilled = true;

                inputs.forEach(input => {
                    if (!input.value || input.value.trim() === '') {
                        allFilled = false;
                    }
                });

                if (!allFilled) {
                    if (!confirm('Beberapa kriteria belum diisi. Lanjutkan menyimpan?')) {
                        return;
                    }
                }

                // Submit form
                const submitBtn = this.querySelector('button[type="submit"]');
                submitBtn.classList.add('loading');
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';

                this.submit();
            });
        }

        const batchForm = document.getElementById('batchForm');
        if (batchForm) {
            batchForm.addEventListener('submit', function(e) {
                e.preventDefault();

                // Count filled assessments
                const rows = document.querySelectorAll('.batch-row');
                let filledCount = 0;

                rows.forEach(row => {
                    const inputs = row.querySelectorAll('.batch-input');
                    let hasValue = false;

                    inputs.forEach(input => {
                        if (input.value && input.value.trim() !== '') {
                            hasValue = true;
                        }
                    });

                    if (hasValue) filledCount++;
                });

                if (filledCount === 0) {
                    showNotification('Tidak ada data yang diisi', 'error');
                    return;
                }

                if (!confirm(`Simpan penilaian untuk ${filledCount} Kwarcan?`)) {
                    return;
                }

                // Submit form
                const submitBtn = this.querySelector('button[type="submit"]');
                submitBtn.classList.add('loading');
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';

                this.submit();
            });
        }

        // Setup keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey) {
                switch (e.key) {
                    case 'n':
                        e.preventDefault();
                        openSingleModal();
                        break;
                    case 'b':
                        e.preventDefault();
                        openBatchModal();
                        break;
                    case 'i':
                        e.preventDefault();
                        openImportModal();
                        break;
                }
            }

            // ESC to close modals
            if (e.key === 'Escape') {
                const modals = document.querySelectorAll('.modal.show');
                modals.forEach(modal => {
                    modal.classList.remove('show');
                });
                document.body.style.overflow = 'auto';
            }
        });

        // Auto-save draft functionality
        let autoSaveTimer;
        const draftKey = 'penilaian_draft';

        function saveDraft() {
            const form = document.getElementById('singleForm');
            if (!form || !currentEditingKwarcan) return;

            const formData = new FormData(form);
            const draft = {};

            for (let [key, value] of formData.entries()) {
                if (key.startsWith('penilaian[') && value) {
                    draft[key] = value;
                }
            }

            if (Object.keys(draft).length > 0) {
                localStorage.setItem(`${draftKey}_${currentEditingKwarcan}`, JSON.stringify(draft));
            }
        }

        function loadDraft(kwarcanId) {
            const draft = localStorage.getItem(`${draftKey}_${kwarcanId}`);
            if (draft) {
                try {
                    const data = JSON.parse(draft);
                    Object.entries(data).forEach(([key, value]) => {
                        const input = document.querySelector(`[name="${key}"]`);
                        if (input) {
                            input.value = value;
                            // Update corresponding slider
                            const kriteriaId = key.match(/\[(\d+)\]/)[1];
                            const slider = document.getElementById(`slider_${kriteriaId}`);
                            if (slider) slider.value = value;
                        }
                    });

                    showNotification('Draft dimuat', 'success');
                } catch (e) {
                    console.error('Error loading draft:', e);
                }
            }
        }

        function clearDraft(kwarcanId) {
            localStorage.removeItem(`${draftKey}_${kwarcanId}`);
        }

        // Setup auto-save for single form
        const singleFormInputs = document.querySelectorAll('#singleForm .nilai-input');
        singleFormInputs.forEach(input => {
            input.addEventListener('input', function() {
                clearTimeout(autoSaveTimer);
                autoSaveTimer = setTimeout(saveDraft, 2000); // Save after 2 seconds of inactivity
            });
        });

        // Load draft when editing
        if (currentEditingKwarcan) {
            setTimeout(() => loadDraft(currentEditingKwarcan), 500);
        }

        // Clear draft on successful save
        window.addEventListener('beforeunload', function() {
            if (currentEditingKwarcan) {
                saveDraft();
            }
        });

        // Initialize tooltips
        initializeTooltips();

        // Initialize animations
        animateCards();
    });

    // Handle file selection for import
    function handleFileSelect(file) {
        const uploadArea = document.getElementById('uploadArea');
        const preview = document.getElementById('importPreview');
        const importBtn = document.getElementById('importBtn');

        if (!file) return;

        // Validate file type
        const allowedTypes = ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel'
        ];

        if (!allowedTypes.includes(file.type)) {
            showNotification('Format file tidak didukung. Gunakan file .xlsx atau .xls', 'error');
            return;
        }

        // Validate file size (5MB max)
        if (file.size > 5 * 1024 * 1024) {
            showNotification('Ukuran file terlalu besar. Maksimal 5MB', 'error');
            return;
        }

        // Update upload area
        uploadArea.innerHTML = `
            <i class="fas fa-file-excel" style="color: #28a745;"></i>
            <h4 style="color: #28a745;">File Dipilih</h4>
            <p><strong>${file.name}</strong></p>
            <small>Ukuran: ${(file.size / 1024 / 1024).toFixed(2)} MB</small>
        `;

        // Show preview and import button
        preview.style.display = 'block';
        importBtn.style.display = 'inline-flex';

        // Preview file content (simplified)
        preview.innerHTML = `
            <div class="preview-info">
                <p><strong>File:</strong> ${file.name}</p>
                <p><strong>Ukuran:</strong> ${(file.size / 1024 / 1024).toFixed(2)} MB</p>
                <p><strong>Tipe:</strong> ${file.type}</p>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    Klik "Import Data" untuk memproses file Excel
                </div>
            </div>
        `;
    }

    // Initialize tooltips
    function initializeTooltips() {
        const elements = document.querySelectorAll('[title]');
        elements.forEach(element => {
            element.addEventListener('mouseenter', function(e) {
                const tooltip = document.createElement('div');
                tooltip.className = 'tooltip';
                tooltip.textContent = this.title;
                tooltip.style.cssText = `
                    position: absolute;
                    background: rgba(0, 0, 0, 0.8);
                    color: white;
                    padding: 8px 12px;
                    border-radius: 6px;
                    font-size: 0.8rem;
                    z-index: 1000;
                    pointer-events: none;
                    white-space: nowrap;
                `;

                document.body.appendChild(tooltip);

                const rect = this.getBoundingClientRect();
                tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
                tooltip.style.top = rect.top - tooltip.offsetHeight - 8 + 'px';

                this.addEventListener('mouseleave', function() {
                    tooltip.remove();
                }, {
                    once: true
                });
            });
        });
    }

    // Animate cards on load
    function animateCards() {
        const cards = document.querySelectorAll('.assessment-card, .stat-card');
        cards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';

            setTimeout(() => {
                card.style.transition = 'all 0.5s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 100);
        });
    }

    // Utility functions
    function formatNumber(num) {
        return parseFloat(num).toFixed(1);
    }

    function validateInput(input) {
        const value = parseFloat(input.value);
        const min = parseFloat(input.min);
        const max = parseFloat(input.max);

        if (isNaN(value)) {
            input.value = '';
            return false;
        }

        if (value < min) {
            input.value = min;
            return false;
        }

        if (value > max) {
            input.value = max;
            return false;
        }

        return true;
    }

    // Setup input validation
    document.addEventListener('input', function(e) {
        if (e.target.classList.contains('nilai-input') || e.target.classList.contains('batch-input')) {
            validateInput(e.target);
        }
    });

    // Print matrix function
    function printMatrix() {
        const printWindow = window.open('', '_blank');
        const matrixTable = document.querySelector('.matrix-table').outerHTML;

        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Matrix Penilaian Kwarcan</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 20px; }
                    table { width: 100%; border-collapse: collapse; font-size: 12px; }
                    th, td { border: 1px solid #ddd; padding: 8px; text-align: center; }
                    th { background: #f5f5f5; font-weight: bold; }
                    .header { text-align: center; margin-bottom: 20px; }
                    @media print { body { margin: 0; } }
                </style>
            </head>
            <body>
                <div class="header">
                    <h2>Matrix Penilaian Kwarcan</h2>
                    <p>Tanggal: ${new Date().toLocaleDateString('id-ID')}</p>
                </div>
                ${matrixTable}
            </body>
            </html>
        `);

        printWindow.document.close();
        printWindow.print();
    }

    // Add print button functionality
    document.addEventListener('click', function(e) {
        if (e.target.closest('.btn-print')) {
            e.preventDefault();
            printMatrix();
        }
    });

    // Performance optimization: Lazy load assessment cards
    function lazyLoadCards() {
        const cards = document.querySelectorAll('.assessment-card');
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('loaded');
                    observer.unobserve(entry.target);
                }
            });
        });

        cards.forEach(card => observer.observe(card));
    }

    // Initialize lazy loading
    if ('IntersectionObserver' in window) {
        lazyLoadCards();
    }
</script>

<?php include '../includes/footer.php'; ?>