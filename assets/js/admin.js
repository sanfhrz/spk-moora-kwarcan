// Admin Dashboard JavaScript

$(document).ready(function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initialize popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
    
    // Sidebar toggle for mobile
    $('.sidebar-toggle').click(function() {
        $('#sidebar').toggleClass('show');
    });
    
    // Close sidebar when clicking outside on mobile
    $(document).click(function(event) {
        if (!$(event.target).closest('#sidebar, .sidebar-toggle').length) {
            $('#sidebar').removeClass('show');
        }
    });
    
    // Confirm delete actions
    $('.btn-delete').click(function(e) {
        e.preventDefault();
        var href = $(this).attr('href');
        var itemName = $(this).data('name') || 'item ini';
        
        if (confirm('Apakah Anda yakin ingin menghapus ' + itemName + '?')) {
            window.location.href = href;
        }
    });
    
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        $('.alert').fadeOut('slow');
    }, 5000);
    
    // Form validation
    $('form').submit(function() {
        var isValid = true;
        
        // Check required fields
        $(this).find('input[required], select[required], textarea[required]').each(function() {
            if ($(this).val() === '') {
                $(this).addClass('is-invalid');
                isValid = false;
            } else {
                $(this).removeClass('is-invalid');
            }
        });
        
        if (!isValid) {
            alert('Mohon lengkapi semua field yang wajib diisi!');
            return false;
        }
        
        // Show loading spinner
        $(this).find('button[type="submit"]').html('<span class="loading-spinner me-2"></span>Memproses...');
    });
    
    // Number input formatting
    $('.number-input').on('input', function() {
        var value = $(this).val();
        // Only allow numbers and decimal point
        value = value.replace(/[^0-9.]/g, '');
        $(this).val(value);
    });
    
    // DataTable initialization (if exists)
    if ($.fn.DataTable) {
        $('.data-table').DataTable({
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Indonesian.json"
            },
            "pageLength": 10,
            "responsive": true,
            "order": [[0, "asc"]]
        });
    }
});

// Utility Functions
function showLoading(element) {
    $(element).html('<span class="loading-spinner me-2"></span>Loading...');
    $(element).prop('disabled', true);
}

function hideLoading(element, originalText) {
    $(element).html(originalText);
    $(element).prop('disabled', false);
}

function showAlert(message, type = 'info') {
    var alertClass = 'alert-' + type;
    var alertHtml = `
        <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    $('.main-content .container-fluid').prepend(alertHtml);
    
    // Auto hide after 5 seconds
    setTimeout(function() {
        $('.alert').fadeOut('slow');
    }, 5000);
}

// MOORA Calculation Functions (lanjutan)
function calculateMOORA() {
    showLoading('#btn-calculate');
    
    $.ajax({
        url: 'ajax/calculate_moora.php',
        type: 'POST',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('Perhitungan MOORA berhasil!', 'success');
                setTimeout(function() {
                    window.location.reload();
                }, 2000);
            } else {
                showAlert('Error: ' + response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Terjadi kesalahan sistem!', 'danger');
        },
        complete: function() {
            hideLoading('#btn-calculate', '<i class="fas fa-calculator me-2"></i>Hitung MOORA');
        }
    });
}

// Export Functions
function exportData(type, url) {
    var btn = '#btn-export-' + type;
    showLoading(btn);
    
    window.open(url, '_blank');
    
    setTimeout(function() {
        hideLoading(btn, '<i class="fas fa-file-' + type + ' me-2"></i>Export ' + type.toUpperCase());
    }, 2000);
}

// Chart Functions (for future use)
function initializeCharts() {
    // Will be implemented when we add charts
    console.log('Charts initialized');
}
