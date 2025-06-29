<div class="mobile-navbar" id="mobileNavbar">
    <div class="mobile-nav-header">
        <div class="mobile-brand">
            <i class="fas fa-chart-line"></i>
            <span>SPK MOORA</span>
        </div>
        <button class="mobile-menu-toggle" id="mobileMenuToggle">
            <i class="fas fa-bars"></i>
        </button>
    </div>

    <div class="mobile-user-info">
        <div class="mobile-user-avatar">
            <i class="fas fa-user-circle"></i>
        </div>
        <div class="mobile-user-details">
            <span class="mobile-user-name"><?= htmlspecialchars($_SESSION['admin_nama']) ?></span>
            <span class="mobile-user-role">Administrator</span>
        </div>
    </div>
</div>

<style>
    .mobile-navbar {
        display: none;
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(20px);
        border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        padding: 15px 20px;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 999;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .mobile-nav-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }

    .mobile-brand {
        display: flex;
        align-items: center;
        gap: 10px;
        color: var(--primary-color);
        font-weight: 700;
        font-size: 1.1rem;
    }

    .mobile-brand i {
        font-size: 1.3rem;
    }

    .mobile-menu-toggle {
        width: 40px;
        height: 40px;
        border: none;
        background: rgba(102, 126, 234, 0.1);
        color: var(--primary-color);
        border-radius: 8px;
        cursor: pointer;
        transition: var(--transition);
    }

    .mobile-menu-toggle:hover {
        background: var(--primary-color);
        color: white;
    }

    .mobile-user-info {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .mobile-user-avatar {
        width: 35px;
        height: 35px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.1rem;
    }

    .mobile-user-details {
        display: flex;
        flex-direction: column;
    }

    .mobile-user-name {
        color: var(--dark-color);
        font-weight: 600;
        font-size: 0.9rem;
    }

    .mobile-user-role {
        color: var(--gray-600);
        font-size: 0.75rem;
    }

    @media (max-width: 768px) {
        .mobile-navbar {
            display: block;
        }
    }
</style>