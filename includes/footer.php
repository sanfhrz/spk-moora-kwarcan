</div> <!-- End main-wrapper -->

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script src="../assets/js/admin.js"></script>
    <script src="../assets/js/dashboard.js"></script>
    
    <!-- Additional JS for specific pages -->
    <?php if (isset($additional_js)): ?>
        <?php foreach ($additional_js as $js): ?>
            <script src="<?= $js ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>

    <script>
        // Global JavaScript Functions
        
        // Show loading spinner
        function showLoading() {
            document.getElementById('loadingSpinner').classList.add('show');
        }

        // Hide loading spinner
        function hideLoading() {
            document.getElementById('loadingSpinner').classList.remove('show');
        }

        // Show toast notification
        function showToast(message, type = 'info', duration = 5000) {
            const toastContainer = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            
            const toastId = 'toast_' + Date.now();
            toast.id = toastId;
            
            toast.innerHTML = `
                <div class="toast-header">
                    <span class="toast-title">${getToastTitle(type)}</span>
                    <button class="toast-close" onclick="closeToast('${toastId}')">&times;</button>
                </div>
                <div class="toast-body">${message}</div>
            `;
            
            toastContainer.appendChild(toast);
            
            // Show toast
            setTimeout(() => {
                toast.classList.add('show');
            }, 100);
            
            // Auto hide
            setTimeout(() => {
                closeToast(toastId);
            }, duration);
        }

        // Get toast title based on type
        function getToastTitle(type) {
            const titles = {
                'success': 'Berhasil',
                'error': 'Error',
                'warning': 'Peringatan',
                'info': 'Informasi'
            };
            return titles[type] || 'Notifikasi';
        }

        // Close toast
        function closeToast(toastId) {
            const toast = document.getElementById(toastId);
            if (toast) {
                toast.classList.remove('show');
                setTimeout(() => {
                    toast.remove();
                }, 300);
            }
        }

        // Sidebar toggle functionality
        function initSidebar() {
            const sidebar = document.getElementById('sidebar');
            const sidebarToggle = document.getElementById('sidebarToggle');
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            
            // Desktop sidebar toggle
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('collapsed');
                });
            }
            
            // Mobile sidebar toggle
            if (mobileMenuToggle) {
                mobileMenuToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('show');
                });
            }
            
            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(e) {
                if (window.innerWidth <= 768) {
                    if (!sidebar.contains(e.target) && !mobileMenuToggle?.contains(e.target)) {
                        sidebar.classList.remove('show');
                    }
                }
            });
        }

        // Form validation helper
        function validateForm(formId) {
            const form = document.getElementById(formId);
            const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
            let isValid = true;
            
            inputs.forEach(input => {
                if (!input.value.trim()) {
                    input.classList.add('error');
                    isValid = false;
                } else {
                    input.classList.remove('error');
                }
            });
            
            return isValid;
        }

        // Format number with thousand separator
        function formatNumber(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        }

        // Format currency
        function formatCurrency(num) {
            return 'Rp ' + formatNumber(num);
        }

        // Confirm delete action
        function confirmDelete(message = 'Yakin ingin menghapus data ini?') {
            return confirm(message);
        }

        // AJAX helper function
        function ajaxRequest(url, data, method = 'POST') {
            return new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                xhr.open(method, url, true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4) {
                        if (xhr.status === 200) {
                            try {
                                const response = JSON.parse(xhr.responseText);
                                resolve(response);
                            } catch (e) {
                                resolve(xhr.responseText);
                            }
                        } else {
                            reject(xhr.statusText);
                        }
                    }
                };
                
                if (method === 'POST' && data) {
                    const formData = new URLSearchParams(data).toString();
                    xhr.send(formData);
                } else {
                    xhr.send();
                }
            });
        }

        // Auto-refresh data function
        function autoRefreshData(callback, interval = 300000) { // 5 minutes default
            setInterval(callback, interval);
        }

        // Initialize everything when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            initSidebar();
            
            // Add loading states to forms
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function() {
                    showLoading();
                });
            });
            
            // Add loading states to links
            const links = document.querySelectorAll('a[href]:not([href^="#"]):not([href^="javascript:"]):not([onclick])');
            links.forEach(link => {
                link.addEventListener('click', function(e) {
                    if (!this.target || this.target === '_self') {
                        showLoading();
                    }
                });
            });
            
            // Hide loading on page load
            window.addEventListener('load', function() {
                hideLoading();
            });
            
            // Handle back button
            window.addEventListener('pageshow', function(event) {
                if (event.persisted) {
                    hideLoading();
                }
            });
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl + / for help
            if (e.ctrlKey && e.key === '/') {
                e.preventDefault();
                showToast('Keyboard Shortcuts:<br>Ctrl+1: Dashboard<br>Ctrl+2: Data Kwarcan<br>Ctrl+3: Kriteria<br>Ctrl+4: Penilaian<br>Ctrl+5: Hitung MOORA', 'info', 8000);
            }
            
            // Quick navigation shortcuts
            if (e.ctrlKey) {
                switch(e.key) {
                    case '1':
                        e.preventDefault();
                        window.location.href = 'dashboard.php';
                        break;
                    case '2':
                        e.preventDefault();
                        window.location.href = 'kwarcan.php';
                        break;
                    case '3':
                        e.preventDefault();
                        window.location.href = 'kriteria.php';
                        break;
                    case '4':
                        e.preventDefault();
                        window.location.href = 'penilaian.php';
                        break;
                    case '5':
                        e.preventDefault();
                        window.location.href = 'hitung-moora.php';
                        break;
                }
            }
        });

        // Print function
        function printPage() {
            window.print();
        }

        // Export to CSV function
        function exportToCSV(data, filename) {
            const csv = convertToCSV(data);
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            a.click();
            window.URL.revokeObjectURL(url);
        }

        // Convert array to CSV
        function convertToCSV(data) {
            if (!data || !data.length) return '';
            
            const headers = Object.keys(data[0]);
            const csvContent = [
                headers.join(','),
                ...data.map(row => headers.map(header => `"${row[header] || ''}"`).join(','))
            ].join('\n');
            
            return csvContent;
        }

        // Check for updates (optional)
        function checkForUpdates() {
            // Implementation for checking system updates
            console.log('Checking for updates...');
        }

        // Performance monitoring
        function logPerformance() {
            if (window.performance) {
                const loadTime = window.performance.timing.loadEventEnd - window.performance.timing.navigationStart;
                console.log('Page load time:', loadTime + 'ms');
            }
        }

        // Call performance logging
        window.addEventListener('load', logPerformance);
    </script>

    <!-- Page-specific inline scripts -->
    <?php if (isset($inline_js)): ?>
        <script>
            <?= $inline_js ?>
        </script>
    <?php endif; ?>

</body>
</html>