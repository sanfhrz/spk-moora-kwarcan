    <script src="../assets/js/dashboard.js"></script>
    <script>
        // Global functions
        function showLoading() {
            Swal.fire({
                title: 'Memproses...',
                html: 'Mohon tunggu sebentar',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
        }

        function hideLoading() {
            Swal.close();
        }

        function showSuccess(message) {
            Swal.fire({
                icon: 'success',
                title: 'Berhasil!',
                text: message,
                background: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
                color: '#fff'
            });
        }

        function showError(message) {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: message,
                background: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
                color: '#fff'
            });
        }

        function confirmDelete(callback) {
            Swal.fire({
                title: 'Konfirmasi Hapus',
                text: 'Apakah Anda yakin ingin menghapus data ini?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal',
                background: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
                color: '#fff'
            }).then((result) => {
                if (result.isConfirmed) {
                    callback();
                }
            });
        }

        // Auto refresh badges
        setInterval(function() {
            const badges = document.querySelectorAll('.menu-badge');
            badges.forEach(badge => {
                badge.style.animation = 'pulse 0.5s ease';
                setTimeout(() => {
                    badge.style.animation = '';
                }, 500);
            });
        }, 60000);
    </script>
</body>
</html>