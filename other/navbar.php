<nav class="navbar navbar-expand-lg navbar-dark navbar-custom fixed-top">
    <div class="container-fluid">
        <!-- Brand -->
        <a class="navbar-brand d-flex align-items-center" href="<?php echo BASE_URL; ?>admin/dashboard.php">
            <i class="fas fa-shield-alt me-2"></i>
            <span class="d-none d-md-inline">SPK MOORA</span>
            <span class="d-md-none">SPK</span>
        </a>
        
        <!-- Mobile Toggle -->
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <!-- Navbar Content -->
        <div class="collapse navbar-collapse" id="navbarNav">
            <!-- Left Side - Breadcrumb (optional) -->
            <div class="navbar-nav me-auto">
                <span class="navbar-text d-none d-lg-block">
                    <i class="fas fa-map-marker-alt me-1"></i>
                    Kabupaten Asahan
                </span>
            </div>
            
            <!-- Right Side - User Menu -->
            <div class="navbar-nav ms-auto">
                <!-- Notifications (future feature) -->
                <div class="nav-item dropdown d-none d-md-block">
                    <a class="nav-link position-relative" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-bell"></i>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6em;">
                            3
                        </span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><h6 class="dropdown-header">Notifikasi</h6></li>
                        <li><a class="dropdown-item" href="#">
                            <i class="fas fa-info-circle text-info me-2"></i>
                            Sistem berhasil diinisialisasi
                        </a></li>
                        <li><a class="dropdown-item" href="#">
                            <i class="fas fa-user-check text-success me-2"></i>
                            Admin berhasil login
                        </a></li>
                        <li><a class="dropdown-item" href="#">
                            <i class="fas fa-database text-primary me-2"></i>
                            Database terhubung
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-center" href="#">Lihat Semua</a></li>
                    </ul>
                </div>
                
                <!-- User Profile -->
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                        <div class="avatar me-2">
                            <i class="fas fa-user-circle fa-lg"></i>
                        </div>
                        <span class="d-none d-md-inline"><?php echo $_SESSION['admin_name']; ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><h6 class="dropdown-header">
                            <i class="fas fa-user me-2"></i>
                            <?php echo $_SESSION['admin_name']; ?>
                        </h6></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#">
                            <i class="fas fa-user-cog me-2"></i>
                            Profile Settings
                        </a></li>
                        <li><a class="dropdown-item" href="#">
                            <i class="fas fa-cog me-2"></i>
                            System Settings
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="<?php echo BASE_URL; ?>admin/logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>
                            Logout
                        </a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>

<!-- Mobile Sidebar Toggle -->
<button class="btn btn-primary d-lg-none position-fixed" 
        style="top: 70px; left: 10px; z-index: 1050;" 
        type="button" 
        onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</button>

<script>
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    sidebar.classList.toggle('show');
}

// Close sidebar when clicking outside on mobile
document.addEventListener('click', function(event) {
    const sidebar = document.querySelector('.sidebar');
    const toggleBtn = document.querySelector('[onclick="toggleSidebar()"]');
    
    if (window.innerWidth <= 991.98) {
        if (!sidebar.contains(event.target) && !toggleBtn.contains(event.target)) {
            sidebar.classList.remove('show');
        }
    }
});
</script>
