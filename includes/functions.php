<?php
/**
 * includes/functions.php
 *
 * Reusable helper functions used across the whole project.
 * Starts the session once and opens the shared PDO connection
 * (via config/database.php) so no other file needs to duplicate it.
 */

require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Base path of the app relative to the web root.
// Change this ONLY if your project folder inside htdocs has a different name.
if (!defined('BASE_URL')) {
    define('BASE_URL', '/online_crime_reporting/');
}

/**
 * Clean a piece of user input for safe display/storage.
 */
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Redirect to another page (absolute, based on BASE_URL) and stop execution.
 */
function redirect($path) {
    header('Location: ' . BASE_URL . ltrim($path, '/'));
    exit;
}

/**
 * Store a one-time flash message in the session.
 * $type: 'success' | 'danger' | 'warning' | 'info'
 */
function set_flash_message($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Retrieve and clear the current flash message, if any.
 */
function get_flash_message() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Generate (or reuse) a CSRF token for the current session.
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify a submitted CSRF token against the session token.
 */
function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string) $token);
}

/**
 * Format a MySQL datetime/date string for display.
 */
function format_date($datetime, $format = 'M d, Y h:i A') {
    if (empty($datetime)) {
        return "\u{2014}";
    }
    return date($format, strtotime($datetime));
}

/**
 * Bootstrap badge class for a given crime report / investigation status.
 */
function get_status_badge_class($status) {
    switch (strtolower((string) $status)) {
        case 'pending':       return 'bg-warning text-dark';
        case 'verified':      return 'bg-info text-dark';
        case 'rejected':      return 'bg-danger';
        case 'investigating': return 'bg-primary';
        case 'resolved':      return 'bg-success';
        default:              return 'bg-secondary';
    }
}

/**
 * Validate an uploaded evidence file (extension + real MIME type + size).
 * Returns ['success' => bool, 'message' => string].
 */
function validate_file_upload(array $file, array $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'], $maxSizeBytes = 5242880) {
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'message' => 'Invalid file upload.'];
    }

    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            return ['success' => false, 'message' => 'No file was uploaded.'];
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return ['success' => false, 'message' => 'File exceeds the maximum allowed size.'];
        default:
            return ['success' => false, 'message' => 'File upload failed. Please try again.'];
    }

    if ($file['size'] > $maxSizeBytes) {
        return ['success' => false, 'message' => 'File size must not exceed ' . round($maxSizeBytes / 1048576, 1) . ' MB.'];
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions, true)) {
        return ['success' => false, 'message' => 'Only ' . strtoupper(implode(', ', $allowedExtensions)) . ' files are allowed.'];
    }

    $allowedMimeByExtension = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'pdf'  => 'application/pdf',
    ];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $actualMime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!isset($allowedMimeByExtension[$extension]) || $actualMime !== $allowedMimeByExtension[$extension]) {
        return ['success' => false, 'message' => 'The file content does not match its extension.'];
    }

    return ['success' => true, 'message' => 'ok'];
}

/**
 * Build a safe, unique filename for a stored upload, preserving the extension.
 */
function generate_unique_filename($originalName) {
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    return bin2hex(random_bytes(16)) . '_' . time() . '.' . $extension;
}

/**
 * Insert a notification row for a user related to a specific report.
 */
function create_notification(PDO $pdo, $user_id, $report_id, $message) {
    $stmt = $pdo->prepare(
        'INSERT INTO notifications (user_id, report_id, message) VALUES (:user_id, :report_id, :message)'
    );
    return $stmt->execute([
        ':user_id'   => $user_id,
        ':report_id' => $report_id,
        ':message'   => $message,
    ]);
}


/**
 * Add a new case status change to the history table.
 */
function create_case_status_history(
    PDO $pdo,
    $report_id,
    $previous_status,
    $new_status,
    $remarks = null,
    $changed_by = null
) {
    $sql = "INSERT INTO case_status_history
            (report_id, previous_status, new_status, remarks, changed_by)
            VALUES
            (:report_id, :previous_status, :new_status, :remarks, :changed_by)";

    $stmt = $pdo->prepare($sql);

    return $stmt->execute([
        ':report_id'       => $report_id,
        ':previous_status' => $previous_status,
        ':new_status'      => $new_status,
        ':remarks'         => $remarks,
        ':changed_by'      => $changed_by
    ]);
}


/**
 * Get the complete status history of a case.
 */
function get_case_status_history(PDO $pdo, $report_id) {
    $sql = "SELECT
                h.history_id,
                h.previous_status,
                h.new_status,
                h.remarks,
                h.created_at,
                u.full_name AS changed_by_name,
                u.role AS changed_by_role
            FROM case_status_history h
            LEFT JOIN users u ON h.changed_by = u.user_id
            WHERE h.report_id = :report_id
            ORDER BY h.created_at ASC, h.history_id ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':report_id' => $report_id]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
