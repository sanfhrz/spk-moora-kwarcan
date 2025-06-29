// Dashboard JavaScript Functions
document.addEventListener('DOMContentLoaded', function() {
    initializeDashboard();
});

// Initialize dashboard
function initializeDashboard() {
    // Add loading states
    addLoadingStates();
    
    // Initialize tooltips
    initializeTooltips();
    
    // Auto refresh data every 5 minutes
    setInterval(refreshStats, 300000);
    
    // Add keyboard shortcuts
    addKeyboardShortcuts();
    
    // Initialize animations
    initializeAnimations();
}

// Add loading states to buttons
function addLoadingStates() {
    const buttons = document.querySelectorAll('.btn');
    buttons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (this.classList.contains('btn-loading')) return;
            
            const originalText = this.innerHTML;
            this.classList.add('btn-loading');
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
            
            setTimeout(() => {
                this.classList.remove('btn-loading');
                this.innerHTML = originalText;
            }, 2000);
        });
    });
}

// Initialize tooltips
function initializeTooltips() {
    const tooltipElements = document.querySelectorAll('[data-tooltip]');
    tooltipElements.forEach(element => {
        element.addEventListener('mouseenter', showTooltip);
        element.addEventListener('mouseleave', hideTooltip);
    });
}

// Show tooltip
function showTooltip(e) {
    const tooltip = document.createElement('div');
    tooltip.className = 'tooltip';
    tooltip.textContent = e.target.getAttribute('data-tooltip');
    document.body.appendChild(tooltip);
    
    const rect = e.target.getBoundingClientRect();
    tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
    tooltip.style.top = rect.top - tooltip.offsetHeight - 10 + 'px';
    
    setTimeout(() => tooltip.classList.add('show'), 10);
}

// Hide tooltip
function hideTooltip() {
    const tooltip = document.querySelector('.tooltip');
    if (tooltip) {
        tooltip.remove();
    }
}

// Refresh statistics
async function refreshStats() {
    try {
        const response = await fetch('ajax/get-stats.php');
        const data = await response.json();
        
        if (data.success) {
            updateStatsDisplay(data.stats);
        }
    } catch (error) {
        console.error('Error refreshing stats:', error);
    }
}

// Update stats display
function updateStatsDisplay(stats) {
    const statCards = document.querySelectorAll('.stat-card');
    
    if (statCards[0]) {
        statCards[0].querySelector('h3').textContent = formatNumber(stats.totalKwarcan);
    }
    if (statCards[1]) {
        statCards[1].querySelector('h3').textContent = formatNumber(stats.totalKriteria);
    }
    if (statCards[2]) {
        statCards[2].querySelector('h3').textContent = formatNumber(stats.totalPenilaian);
    }
    if (statCards[3]) {
        const lastCalc = stats.lastCalculation ? formatDate(stats.lastCalculation) : 'Belum Ada';
        statCards[3].querySelector('h3').textContent = lastCalc;
    }
}

// Format number with thousands separator
function formatNumber(num) {
    return new Intl.NumberFormat('id-ID').format(num);
}

// Format date
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('id-ID', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
}

// Add keyboard shortcuts
function addKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        // Ctrl + H = Hitung MOORA
        if (e.ctrlKey && e.key === 'h') {
            e.preventDefault();
            hitungMOORA();
        }
        
        // Ctrl + R = Refresh
        if (e.ctrlKey && e.key === 'r') {
            e.preventDefault();
            location.reload();
        }
        
        // Escape = Close modal
        if (e.key === 'Escape') {
            closeAllModals();
        }
    });
}

// Close all modals
function closeAllModals() {
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        modal.style.display = 'none';
    });
}

// Initialize animations
function initializeAnimations() {
    // Animate stat cards on load
    const statCards = document.querySelectorAll('.stat-card');
    statCards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
        card.classList.add('fade-in');
    });
    
    // Animate dashboard cards
    const dashboardCards = document.querySelectorAll('.dashboard-card');
    dashboardCards.forEach((card, index) => {
        card.style.animationDelay = `${(index + statCards.length) * 0.1}s`;
        card.classList.add('fade-in');
    });
}

// Show alert notification
function showAlert(type, message, duration = 5000) {
    // Remove existing alerts
    const existingAlerts = document.querySelectorAll('.alert-notification');
    existingAlerts.forEach(alert => alert.remove());
    
    // Create new alert
    const alert = document.createElement('div');
        alert.className = `alert alert-${type} alert-notification`;
    alert.innerHTML = `
        <i class="fas fa-${getAlertIcon(type)}"></i>
        <span>${message}</span>
        <button class="alert-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    // Add styles for notification
    alert.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        min-width: 300px;
        max-width: 500px;
        animation: slideInRight 0.3s ease;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    `;
    
    document.body.appendChild(alert);
    
    // Auto remove after duration
    if (duration > 0) {
        setTimeout(() => {
            if (alert.parentElement) {
                alert.style.animation = 'slideOutRight 0.3s ease';
                setTimeout(() => alert.remove(), 300);
            }
        }, duration);
    }
}

// Get alert icon based on type
function getAlertIcon(type) {
    const icons = {
        'success': 'check-circle',
        'error': 'exclamation-circle',
        'warning': 'exclamation-triangle',
        'info': 'info-circle'
    };
    return icons[type] || 'info-circle';
}

// Smooth scroll to element
function smoothScrollTo(elementId) {
    const element = document.getElementById(elementId);
    if (element) {
        element.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }
}

// Copy text to clipboard
async function copyToClipboard(text) {
    try {
        await navigator.clipboard.writeText(text);
        showAlert('success', 'Teks berhasil disalin ke clipboard');
    } catch (err) {
        // Fallback for older browsers
        const textArea = document.createElement('textarea');
        textArea.value = text;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        showAlert('success', 'Teks berhasil disalin ke clipboard');
    }
}

// Export data to CSV
function exportToCSV(data, filename) {
    const csv = convertToCSV(data);
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
    
    showAlert('success', `Data berhasil diekspor ke ${filename}`);
}

// Convert array to CSV
function convertToCSV(data) {
    if (!data.length) return '';
    
    const headers = Object.keys(data[0]);
    const csvHeaders = headers.join(',');
    
    const csvRows = data.map(row => {
        return headers.map(header => {
            const value = row[header];
            return typeof value === 'string' && value.includes(',') 
                ? `"${value}"` 
                : value;
        }).join(',');
    });
    
    return [csvHeaders, ...csvRows].join('\n');
}

// Print element
function printElement(elementId) {
    const element = document.getElementById(elementId);
    if (!element) return;
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Print - ${document.title}</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; font-weight: bold; }
                .no-print { display: none; }
                @media print {
                    body { margin: 0; }
                    .page-break { page-break-before: always; }
                }
            </style>
        </head>
        <body>
            <h1>Sistem Pendukung Keputusan MOORA</h1>
            <p>Dicetak pada: ${new Date().toLocaleString('id-ID')}</p>
            <hr>
            ${element.innerHTML}
        </body>
        </html>
    `);
    
    printWindow.document.close();
    printWindow.focus();
    
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 250);
}

// Validate form data
function validateForm(formData, rules) {
    const errors = [];
    
    for (const field in rules) {
        const value = formData.get(field);
        const rule = rules[field];
        
        // Required validation
        if (rule.required && (!value || value.trim() === '')) {
            errors.push(`${rule.label} wajib diisi`);
            continue;
        }
        
        if (!value) continue;
        
        // Min length validation
        if (rule.minLength && value.length < rule.minLength) {
            errors.push(`${rule.label} minimal ${rule.minLength} karakter`);
        }
        
        // Max length validation
        if (rule.maxLength && value.length > rule.maxLength) {
            errors.push(`${rule.label} maksimal ${rule.maxLength} karakter`);
        }
        
        // Email validation
        if (rule.email && !isValidEmail(value)) {
            errors.push(`${rule.label} harus berupa email yang valid`);
        }
        
        // Number validation
        if (rule.number && isNaN(value)) {
            errors.push(`${rule.label} harus berupa angka`);
        }
        
        // Min value validation
        if (rule.min && parseFloat(value) < rule.min) {
            errors.push(`${rule.label} minimal ${rule.min}`);
        }
        
        // Max value validation
        if (rule.max && parseFloat(value) > rule.max) {
            errors.push(`${rule.label} maksimal ${rule.max}`);
        }
    }
    
    return errors;
}

// Validate email format
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// Debounce function
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Throttle function
function throttle(func, limit) {
    let inThrottle;
    return function() {
        const args = arguments;
        const context = this;
        if (!inThrottle) {
            func.apply(context, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    }
}

// Format currency (Indonesian Rupiah)
function formatCurrency(amount) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    }).format(amount);
}

// Format percentage
function formatPercentage(value, decimals = 2) {
    return `${(value * 100).toFixed(decimals)}%`;
}

// Get relative time
function getRelativeTime(date) {
    const now = new Date();
    const diffInSeconds = Math.floor((now - new Date(date)) / 1000);
    
    const intervals = {
        tahun: 31536000,
        bulan: 2592000,
        minggu: 604800,
        hari: 86400,
        jam: 3600,
        menit: 60
    };
    
    for (const [unit, seconds] of Object.entries(intervals)) {
        const interval = Math.floor(diffInSeconds / seconds);
        if (interval >= 1) {
            return `${interval} ${unit} yang lalu`;
        }
    }
    
    return 'Baru saja';
}

// Local storage helpers
const storage = {
    set: (key, value) => {
        try {
            localStorage.setItem(key, JSON.stringify(value));
        } catch (e) {
            console.error('Error saving to localStorage:', e);
        }
    },
    
    get: (key, defaultValue = null) => {
        try {
            const item = localStorage.getItem(key);
            return item ? JSON.parse(item) : defaultValue;
        } catch (e) {
            console.error('Error reading from localStorage:', e);
            return defaultValue;
        }
    },
    
    remove: (key) => {
        try {
            localStorage.removeItem(key);
        } catch (e) {
            console.error('Error removing from localStorage:', e);
        }
    },
    
    clear: () => {
        try {
            localStorage.clear();
        } catch (e) {
            console.error('Error clearing localStorage:', e);
        }
    }
};

// Session storage helpers
const sessionStorage = {
    set: (key, value) => {
        try {
            window.sessionStorage.setItem(key, JSON.stringify(value));
        } catch (e) {
            console.error('Error saving to sessionStorage:', e);
        }
    },
    
    get: (key, defaultValue = null) => {
        try {
            const item = window.sessionStorage.getItem(key);
            return item ? JSON.parse(item) : defaultValue;
        } catch (e) {
            console.error('Error reading from sessionStorage:', e);
            return defaultValue;
        }
    },
    
    remove: (key) => {
        try {
            window.sessionStorage.removeItem(key);
        } catch (e) {
            console.error('Error removing from sessionStorage:', e);
        }
    }
};

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
    
    .alert-notification {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 1rem;
        border-radius: 8px;
        font-weight: 500;
    }
    
    .alert-close {
        background: none;
        border: none;
        color: inherit;
        cursor: pointer;
        padding: 0.25rem;
        margin-left: auto;
        border-radius: 4px;
        opacity: 0.7;
        transition: opacity 0.2s;
    }
    
    .alert-close:hover {
        opacity: 1;
        background: rgba(0,0,0,0.1);
    }
    
    .tooltip {
        position: absolute;
        background: #333;
        color: white;
        padding: 0.5rem;
        border-radius: 4px;
        font-size: 0.8rem;
        z-index: 1000;
        opacity: 0;
        transition: opacity 0.2s;
        pointer-events: none;
        white-space: nowrap;
    }
    
    .tooltip.show {
        opacity: 1;
    }
    
    .tooltip::after {
        content: '';
        position: absolute;
        top: 100%;
        left: 50%;
        margin-left: -5px;
        border-width: 5px;
        border-style: solid;
        border-color: #333 transparent transparent transparent;
    }
    
    .btn-loading {
        opacity: 0.7;
        cursor: not-allowed;
        pointer-events: none;
    }
`;
document.head.appendChild(style);

// Export functions for global use
window.dashboardUtils = {
    showAlert,
    smoothScrollTo,
    copyToClipboard,
    exportToCSV,
    printElement,
    validateForm,
    debounce,
    throttle,
    formatCurrency,
    formatPercentage,
    getRelativeTime,
    storage,
    sessionStorage
};
