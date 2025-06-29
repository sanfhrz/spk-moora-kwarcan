<?php
// Get current page for active menu
$current_page = basename($_SERVER['PHP_SELF'], '.php');

// Get statistics for badges
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM kwarcan WHERE status = 'aktif'");
    $total_kwarcan = $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM kriteria");
    $total_kriteria = $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT COUNT(DISTINCT kwarcan_id) as total FROM penilaian");
    $total_penilaian = $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM hasil_moora");
    $total_hasil = $stmt->fetch()['total'];
} catch (Exception $e) {
    $total_kwarcan = $total_kriteria = $total_penilaian = $total_hasil = 0;
}
?>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-brand">
            <div class="brand-icon">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="brand-text">
                <h3>SPK MOORA</h3>
                <span>Kwarcan System</span>
            </div>
        </div>
        <button class="sidebar-toggle" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </button>
    </div>

    <div class="sidebar-content">
        <div class="sidebar-user">
            <div class="user-avatar">
                <i class="fas fa-user-circle"></i>
            </div>
            <div class="user-info">
                <h4><?= htmlspecialchars($_SESSION['admin_nama']) ?></h4>
                <span>Administrator</span>
            </div>
            <div class="user-status">
                <span class="status-dot online"></span>
            </div>
        </div>

        <nav class="sidebar-nav">
            <ul class="nav-list">
                <li class="nav-item">
                    <a href="dashboard.php" class="nav-link <?= $current_page == 'dashboard' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </li>

                <li class="nav-divider">
                    <span>Data Master</span>
                </li>

                <li class="nav-item">
                    <a href="kwarcan.php" class="nav-link <?= $current_page == 'kwarcan' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-users"></i>
                        <span class="nav-text">Data Kwarcan</span>
                        <?php if ($total_kwarcan > 0): ?>
                            <span class="nav-badge"><?= $total_kwarcan ?></span>
                        <?php endif; ?>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="kriteria.php" class="nav-link <?= $current_page == 'kriteria' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-list-check"></i>
                        <span class="nav-text">Kriteria</span>
                        <?php if ($total_kriteria > 0): ?>
                            <span class="nav-badge"><?= $total_kriteria ?></span>
                        <?php endif; ?>
                    </a>
                </li>

                <li class="nav-divider">
                    <span>Penilaian</span>
                </li>

                <li class="nav-item">
                    <a href="penilaian.php" class="nav-link <?= $current_page == 'penilaian' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-star"></i>
                        <span class="nav-text">Input Penilaian</span>
                        <?php if ($total_penilaian > 0): ?>
                            <span class="nav-badge success"><?= $total_penilaian ?></span>
                        <?php endif; ?>
                    </a>
                </li>

                <li class="nav-divider">
                    <span>Perhitungan</span>
                </li>

                <li class="nav-item">
                    <a href="hitung-moora.php" class="nav-link <?= $current_page == 'hitung-moora' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-calculator"></i>
                        <span class="nav-text">Hitung MOORA</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="hasil-moora.php" class="nav-link <?= $current_page == 'hasil-moora' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-trophy"></i>
                        <span class="nav-text">Hasil & Ranking</span>
                        <?php if ($total_hasil > 0): ?>
                            <span class="nav-badge warning"><?= $total_hasil ?></span>
                        <?php endif; ?>
                    </a>
                </li>

                <li class="nav-divider">
                    <span>Laporan</span>
                </li>

                <li class="nav-item">
                    <a href="laporan.php" class="nav-link <?= $current_page == 'laporan' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-file-alt"></i>
                        <span class="nav-text">Cetak Laporan</span>
                    </a>
                </li>

                <li class="nav-divider">
                    <span>Sistem</span>
                </li>

                <li class="nav-item">
                    <a href="pengaturan.php" class="nav-link <?= $current_page == 'pengaturan' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-cog"></i>
                        <span class="nav-text">Pengaturan</span>
                    </a>
                </li>
            </ul>
        </nav>
    </div>

    <div class="sidebar-footer">
        <div class="footer-stats">
            <div class="stat-item">
                <span class="stat-label">Progress</span>
                <div class="stat-progress">
                    <div class="progress-bar" style="width: <?= $total_kwarcan > 0 ? round(($total_penilaian / $total_kwarcan) * 100) : 0 ?>%"></div>
                </div>
                <span class="stat-value"><?= $total_kwarcan > 0 ? round(($total_penilaian / $total_kwarcan) * 100) : 0 ?>%</span>
            </div>
        </div>

        <div class="footer-actions">
            <a href="logout.php" class="logout-btn" onclick="return confirm('Yakin ingin logout?')">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>
</div>

<style>
    .sidebar {
        width: 280px;
        height: 100vh;
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(20px);
        border-right: 1px solid rgba(0, 0, 0, 0.1);
        position: fixed;
        left: 0;
        top: 0;
        z-index: 1000;
        display: flex;
        flex-direction: column;
        transition: var(--transition);
        box-shadow: 5px 0 20px rgba(0, 0, 0, 0.1);
    }

    .sidebar-header {
        padding: 25px 20px;
        border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .sidebar-brand {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .brand-icon {
        width: 45px;
        height: 45px;
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.2rem;
    }

    .brand-text h3 {
        color: var(--dark-color);
        font-size: 1.3rem;
        font-weight: 700;
        margin-bottom: 2px;
    }

    .brand-text span {
        color: var(--gray-600);
        font-size: 0.8rem;
        font-weight: 500;
    }

    .sidebar-toggle {
        width: 35px;
        height: 35px;
        border: none;
        background: rgba(102, 126, 234, 0.1);
        color: var(--primary-color);
        border-radius: 8px;
        cursor: pointer;
        transition: var(--transition);
        display: none;
    }

    .sidebar-toggle:hover {
        background: var(--primary-color);
        color: white;
    }

    .sidebar-content {
        flex: 1;
        overflow-y: auto;
        padding: 20px 0;
    }

    .sidebar-user {
        padding: 0 20px 20px;
        display: flex;
        align-items: center;
        gap: 12px;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        margin-bottom: 20px;
    }

    .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.2rem;
    }

    .user-info {
        flex: 1;
    }

    .user-info h4 {
        color: var(--dark-color);
        font-size: 0.9rem;
        font-weight: 600;
        margin-bottom: 2px;
    }

    .user-info span {
        color: var(--gray-600);
        font-size: 0.75rem;
    }

    .user-status {
        position: relative;
    }

    .status-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: var(--success-color);
        display: block;
    }

    .status-dot.online {
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0% {
            box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.7);
        }

        70% {
            box-shadow: 0 0 0 10px rgba(40, 167, 69, 0);
        }

        100% {
            box-shadow: 0 0 0 0 rgba(40, 167, 69, 0);
        }
    }

    .sidebar-nav {
        padding: 0 10px;
    }

    .nav-list {
        list-style: none;
    }

    .nav-item {
        margin-bottom: 2px;
    }

    .nav-link {
        display: flex;
        align-items: center;
        padding: 12px 15px;
        color: var(--gray-700);
        text-decoration: none;
        border-radius: 12px;
        transition: var(--transition);
        position: relative;
        gap: 12px;
    }

    .nav-link:hover {
        background: rgba(102, 126, 234, 0.1);
        color: var(--primary-color);
        transform: translateX(5px);
    }

    .nav-link.active {
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        color: white;
        box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
    }

    .nav-icon {
        width: 20px;
        text-align: center;
        font-size: 1rem;
    }

    .nav-text {
        flex: 1;
        font-weight: 500;
        font-size: 0.9rem;
    }

    .nav-badge {
        background: var(--primary-color);
        color: white;
        font-size: 0.7rem;
        padding: 2px 6px;
        border-radius: 10px;
        font-weight: 600;
        min-width: 18px;
        text-align: center;
    }

    .nav-badge.success {
        background: var(--success-color);
    }

    .nav-badge.warning {
        background: var(--warning-color);
    }

    .nav-badge.danger {
        background: var(--danger-color);
    }

    .nav-divider {
        padding: 15px 15px 8px;
        margin-top: 10px;
    }

    .nav-divider span {
        color: var(--gray-500);
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .sidebar-footer {
        padding: 20px;
        border-top: 1px solid rgba(0, 0, 0, 0.1);
    }

    .footer-stats {
        margin-bottom: 15px;
    }

    .stat-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 8px;
    }

    .stat-label {
        font-size: 0.8rem;
        color: var(--gray-600);
        font-weight: 500;
    }

    .stat-progress {
        flex: 1;
        height: 4px;
        background: rgba(0, 0, 0, 0.1);
        border-radius: 2px;
        margin: 0 10px;
        overflow: hidden;
    }

    .progress-bar {
        height: 100%;
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        border-radius: 2px;
        transition: width 0.3s ease;
    }

    .stat-value {
        font-size: 0.8rem;
        color: var(--primary-color);
        font-weight: 600;
    }

    .logout-btn {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 15px;
        background: rgba(220, 53, 69, 0.1);
        color: var(--danger-color);
        text-decoration: none;
        border-radius: 10px;
        transition: var(--transition);
        font-size: 0.9rem;
        font-weight: 500;
    }

    .logout-btn:hover {
        background: var(--danger-color);
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(220, 53, 69, 0.3);
    }

    /* Mobile Responsive */
    @media (max-width: 768px) {
        .sidebar {
            transform: translateX(-100%);
        }

        .sidebar.show {
            transform: translateX(0);
        }

        .sidebar-toggle {
            display: block;
        }
    }

    /* Scrollbar Styling */
    .sidebar-content::-webkit-scrollbar {
        width: 4px;
    }

    .sidebar-content::-webkit-scrollbar-track {
        background: transparent;
    }

    .sidebar-content::-webkit-scrollbar-thumb {
        background: rgba(102, 126, 234, 0.3);
        border-radius: 2px;
    }

    .sidebar-content::-webkit-scrollbar-thumb:hover {
        background: rgba(102, 126, 234, 0.5);
    }
</style>