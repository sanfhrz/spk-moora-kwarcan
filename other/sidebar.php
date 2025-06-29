<div class="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <i class="fas fa-chart-line"></i>
            <span>SPK MOORA</span>
        </div>
        <button class="sidebar-toggle" onclick="toggleSidebar()">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <div class="sidebar-menu">
        <div class="menu-section">
            <h4>MAIN MENU</h4>
            <ul>
                <li class="<?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>">
                    <a href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="menu-section">
            <h4>DATA MASTER</h4>
            <ul>
                <li class="<?= basename($_SERVER['PHP_SELF']) == 'kwarcan.php' ? 'active' : '' ?>">
                    <a href="kwarcan.php">
                        <i class="fas fa-users"></i>
                        <span>Data Kwarcan</span>
                        <div class="menu-badge">
                            <?php
                            try {
                                $stmt = $pdo->query("SELECT COUNT(*) as total FROM kwarcan WHERE status = 'aktif'");
                                echo $stmt->fetch()['total'];
                            } catch (Exception $e) {
                                echo '0';
                            }
                            ?>
                        </div>
                    </a>
                </li>
                <li class="<?= basename($_SERVER['PHP_SELF']) == 'kriteria.php' ? 'active' : '' ?>">
                    <a href="kriteria.php">
                        <i class="fas fa-list-check"></i>
                        <span>Data Kriteria</span>
                        <div class="menu-badge">
                            <?php
                            try {
                                $stmt = $pdo->query("SELECT COUNT(*) as total FROM kriteria");
                                echo $stmt->fetch()['total'];
                            } catch (Exception $e) {
                                echo '0';
                            }
                            ?>
                        </div>
                    </a>
                </li>
            </ul>
        </div>

        <div class="menu-section">
            <h4>PENILAIAN</h4>
            <ul>
                <li class="<?= basename($_SERVER['PHP_SELF']) == 'penilaian.php' ? 'active' : '' ?>">
                    <a href="penilaian.php">
                        <i class="fas fa-star"></i>
                        <span>Input Penilaian</span>
                        <div class="menu-badge">
                            <?php
                            try {
                                $stmt = $pdo->query("SELECT COUNT(DISTINCT kwarcan_id) as total FROM penilaian");
                                echo $stmt->fetch()['total'];
                            } catch (Exception $e) {
                                echo '0';
                            }
                            ?>
                        </div>
                    </a>
                </li>
                <li class="<?= basename($_SERVER['PHP_SELF']) == 'matrix.php' ? 'active' : '' ?>">
                    <a href="matrix.php">
                        <i class="fas fa-table"></i>
                        <span>Matrix Keputusan</span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="menu-section">
            <h4>PERHITUNGAN MOORA</h4>
            <ul>
                <li class="<?= basename($_SERVER['PHP_SELF']) == 'hitung-moora.php' ? 'active' : '' ?>">
                    <a href="hitung-moora.php">
                        <i class="fas fa-calculator"></i>
                        <span>Hitung MOORA</span>
                    </a>
                </li>
                <li class="<?= basename($_SERVER['PHP_SELF']) == 'hasil-moora.php' ? 'active' : '' ?>">
                    <a href="hasil-moora.php">
                        <i class="fas fa-trophy"></i>
                        <span>Hasil & Ranking</span>
                        <div class="menu-badge">
                            <?php
                            try {
                                $stmt = $pdo->query("SELECT COUNT(*) as total FROM hasil_moora");
                                echo $stmt->fetch()['total'];
                            } catch (Exception $e) {
                                echo '0';
                            }
                            ?>
                        </div>
                    </a>
                </li>
            </ul>
        </div>

        <div class="menu-section">
            <h4>LAPORAN</h4>
            <ul>
                <li class="<?= basename($_SERVER['PHP_SELF']) == 'laporan.php' ? 'active' : '' ?>">
                    <a href="laporan.php">
                        <i class="fas fa-file-alt"></i>
                        <span>Laporan</span>
                    </a>
                </li>
                <li class="<?= basename($_SERVER['PHP_SELF']) == 'cetak-detail.php' ? 'active' : '' ?>">
                    <a href="cetak-detail.php">
                        <i class="fas fa-print"></i>
                        <span>Cetak Detail</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar">
                <i class="fas fa-user-circle"></i>
            </div>
            <div class="user-details">
                <h4><?= $_SESSION['admin_nama'] ?? 'Administrator' ?></h4>
                <p>@<?= $_SESSION['admin_username'] ?? 'admin' ?></p>
            </div>
        </div>

        <div class="sidebar-actions">
            <button class="btn-icon" onclick="showProfile()" title="Profile">
                <i class="fas fa-cog"></i>
            </button>
            <button class="btn-icon" onclick="confirmLogout()" title="Logout">
                <i class="fas fa-sign-out-alt"></i>
            </button>
        </div>
    </div>
</div>

<!-- Mobile menu toggle -->
<button class="mobile-menu-toggle" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</button>

<!-- Overlay for mobile -->
<div class="sidebar-overlay" onclick="toggleSidebar()"></div>

<script>
    function toggleSidebar() {
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.querySelector('.sidebar-overlay');

        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
        document.body.classList.toggle('sidebar-open');
    }

    function confirmLogout() {
        if (confirm('Apakah Anda yakin ingin keluar?')) {
            window.location.href = 'logout.php';
        }
    }

    function showProfile() {
        alert('Profile: <?= $_SESSION['admin_nama'] ?? 'Administrator' ?>\nUsername: <?= $_SESSION['admin_username'] ?? 'admin' ?>');
    }

    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(event) {
        const sidebar = document.querySelector('.sidebar');
        const toggleBtn = document.querySelector('.mobile-menu-toggle');

        if (window.innerWidth <= 768 &&
            !sidebar.contains(event.target) &&
            !toggleBtn.contains(event.target) &&
            sidebar.classList.contains('active')) {
            toggleSidebar();
        }
    });
</script>