<?php
/**
 * includes/header.php
 *
 * Shared page header. Include this at the top of every page, after any
 * require_login()/require_role() checks have already run (so redirects
 * happen before any HTML is sent).
 *
 * Optional: set $pageTitle before including this file to customize
 * the browser tab title, e.g. $pageTitle = 'My Reports';
 */

require_once __DIR__ . '/auth.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? sanitize_input($pageTitle) . ' | CRMS' : 'Online Crime Reporting and Case Management System'; ?></title>

    <!-- Bootstrap 5 -->
   <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>  
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css?v=<?php echo time(); ?>">   
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Project stylesheet -->
    <link href="<?php echo BASE_URL; ?>assets/css/style.css" rel="stylesheet">
     <!-- SweetAlert2 CSS -->
   <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
   <!-- SweetAlert2 JS -->
   <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>    
</head>
<body class="d-flex flex-column min-vh-100">

<?php include __DIR__ . '/navbar.php'; ?>

<main class="app-main flex-grow-1 py-4">
    <?php $flash = get_flash_message(); ?>
    <?php if ($flash): ?>
        <div class="container mt-1 mb-3">
            <div class="alert alert-<?php echo sanitize_input($flash['type']); ?> alert-dismissible fade show shadow-sm" role="alert">
                <?php echo sanitize_input($flash['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
    <?php endif; ?>