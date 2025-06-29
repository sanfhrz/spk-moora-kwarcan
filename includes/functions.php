<?php
// Helper functions untuk aplikasi SPK

// Sanitize input
function clean_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Format angka
function format_number($number, $decimals = 2) {
    return number_format($number, $decimals, ',', '.');
}

// Generate alert
function show_alert($message, $type = 'info') {
    $class = '';
    $icon = '';
    switch($type) {
        case 'success': 
            $class = 'alert-success'; 
            $icon = 'fas fa-check-circle';
            break;
        case 'error': 
        case 'danger': 
            $class = 'alert-danger'; 
            $icon = 'fas fa-exclamation-triangle';
            break;
        case 'warning': 
            $class = 'alert-warning'; 
            $icon = 'fas fa-exclamation-circle';
            break;
        default: 
            $class = 'alert-info'; 
            $icon = 'fas fa-info-circle';
            break;
    }
    
    return "<div class='alert {$class} alert-dismissible fade show' role='alert'>
                <i class='{$icon} me-2'></i>{$message}
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
            </div>";
}

// Redirect function
function redirect($url) {
    header("Location: " . $url);
    exit();
}

// Check if user is logged in
function is_logged_in() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

// Get current page name
function get_current_page() {
    return basename($_SERVER['PHP_SELF']);
}

// Get current directory
function get_current_dir() {
    return basename(dirname($_SERVER['PHP_SELF']));
}

// Generate breadcrumb
function generate_breadcrumb($items) {
    $html = '<nav aria-label="breadcrumb">
                <ol class="breadcrumb">';
    
    foreach ($items as $key => $item) {
        if ($key === array_key_last($items)) {
            $html .= '<li class="breadcrumb-item active" aria-current="page">' . $item . '</li>';
        } else {
            if (is_array($item)) {
                $html .= '<li class="breadcrumb-item"><a href="' . $item['url'] . '">' . $item['text'] . '</a></li>';
            } else {
                $html .= '<li class="breadcrumb-item">' . $item . '</li>';
            }
        }
    }
    
    $html .= '</ol></nav>';
    return $html;
}

// Pagination helper
function generate_pagination($current_page, $total_pages, $base_url) {
    if ($total_pages <= 1) return '';
    
    $html = '<nav aria-label="Page navigation">
                <ul class="pagination justify-content-center">';
    
    // Previous button
    if ($current_page > 1) {
        $html .= '<li class="page-item">
                    <a class="page-link" href="' . $base_url . '?page=' . ($current_page - 1) . '">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                  </li>';
    }
    
    // Page numbers
    $start = max(1, $current_page - 2);
    $end = min($total_pages, $current_page + 2);
    
    for ($i = $start; $i <= $end; $i++) {
        $active = ($i == $current_page) ? 'active' : '';
        $html .= '<li class="page-item ' . $active . '">
                    <a class="page-link" href="' . $base_url . '?page=' . $i . '">' . $i . '</a>
                  </li>';
    }
    
    // Next button
    if ($current_page < $total_pages) {
        $html .= '<li class="page-item">
                    <a class="page-link" href="' . $base_url . '?page=' . ($current_page + 1) . '">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                  </li>';
    }
    
    $html .= '</ul></nav>';
    return $html;
}

// MOORA Calculation Functions
function normalize_matrix($matrix) {
    $normalized = [];
    $criteria_count = count($matrix[0]);
    
    // Calculate sum of squares for each criterion
    $sum_squares = array_fill(0, $criteria_count, 0);
    
    foreach ($matrix as $row) {
        for ($j = 0; $j < $criteria_count; $j++) {
            $sum_squares[$j] += pow($row[$j], 2);
        }
    }
    
    // Calculate square root of sum of squares
    for ($j = 0; $j < $criteria_count; $j++) {
        $sum_squares[$j] = sqrt($sum_squares[$j]);
    }
    
    // Normalize matrix
    foreach ($matrix as $i => $row) {
        for ($j = 0; $j < $criteria_count; $j++) {
            $normalized[$i][$j] = $row[$j] / $sum_squares[$j];
        }
    }
    
    return $normalized;
}

function calculate_weighted_matrix($normalized_matrix, $weights) {
    $weighted = [];
    
    foreach ($normalized_matrix as $i => $row) {
        foreach ($row as $j => $value) {
            $weighted[$i][$j] = $value * $weights[$j];
        }
    }
    
    return $weighted;
}

function calculate_optimization_values($weighted_matrix, $criteria_types) {
    $optimization_values = [];
    
    foreach ($weighted_matrix as $i => $row) {
        $benefit_sum = 0;
        $cost_sum = 0;
        
        foreach ($row as $j => $value) {
            if ($criteria_types[$j] == 'benefit') {
                $benefit_sum += $value;
            } else {
                $cost_sum += $value;
            }
        }
        
        $optimization_values[$i] = $benefit_sum - $cost_sum;
    }
    
    return $optimization_values;
}

// Validation functions
function validate_required($value, $field_name) {
    if (empty(trim($value))) {
        return $field_name . ' harus diisi!';
    }
    return '';
}

function validate_numeric($value, $field_name, $min = null, $max = null) {
    if (!is_numeric($value)) {
        return $field_name . ' harus berupa angka!';
    }
    
    if ($min !== null && $value < $min) {
        return $field_name . ' minimal ' . $min . '!';
    }
    
    if ($max !== null && $value > $max) {
        return $field_name . ' maksimal ' . $max . '!';
    }
    
    return '';
}

function validate_email($email) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Format email tidak valid!';
    }
    return '';
}

// File upload helper
function upload_file($file, $upload_dir, $allowed_types = ['jpg', 'jpeg', 'png', 'pdf']) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Error uploading file'];
    }
    
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_extension, $allowed_types)) {
        return ['success' => false, 'message' => 'File type not allowed'];
    }
    
    $new_filename = uniqid() . '.' . $file_extension;
    $upload_path = $upload_dir . '/' . $new_filename;
    
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        return ['success' => true, 'filename' => $new_filename];
    } else {
        return ['success' => false, 'message' => 'Failed to move uploaded file'];
    }
}

// Start session if not started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
