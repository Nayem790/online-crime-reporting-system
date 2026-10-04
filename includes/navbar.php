<?php
/**
 * includes/navbar.php
 *
 * Role-aware navigation bar. Included automatically by header.php.
 * Assumes auth.php / functions.php have already been loaded, so
 * $pdo, BASE_URL, sanitize_input(), and the session are available.
 */

$navRole = $_SESSION['role'] ?? null;

// Unread notification count for a logged-in citizen (small, targeted query).
$unreadNotifications = 0;
if ($navRole === 'citizen' && isset($pdo)) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0');
    $stmt->execute([':uid' => $_SESSION['user_id']]);
    $unreadNotifications = (int) $stmt->fetchColumn();
}

$homeLink = BASE_URL . 'index.php';
if ($navRole) {
    $homeLink = BASE_URL . $navRole . '/dashboard.php';
}
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="<?php echo $homeLink; ?>">
            <i class="bi bi-shield-lock-fill"></i>
            <span>CRMS</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav ms-auto align-items-lg-center">

                <?php if ($navRole === 'citizen'): ?>
                    <li class="nav-item"><a class="nav-link" href="<?php echo BASE_URL; ?>citizen/dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo BASE_URL; ?>citizen/submit_report.php">Submit Report</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo BASE_URL; ?>citizen/report_history.php">My Reports</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo BASE_URL; ?>citizen/help.php">Help &amp; Resources</a></li>
                    <li class="nav-item">
                        <a class="nav-link position-relative" href="<?php echo BASE_URL; ?>citizen/notifications.php">
                            <i class="bi bi-bell-fill"></i>
                            <?php if ($unreadNotifications > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:.6rem;">
                                    <?php echo $unreadNotifications; ?>
                                    <span class="visually-hidden">unread notifications</span>
                                </span>
                            <?php endif; ?>
                        </a>
                    </li>

                <?php elseif ($navRole === 'police'): ?>
                    <li class="nav-item"><a class="nav-link" href="<?php echo BASE_URL; ?>police/dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo BASE_URL; ?>police/reports.php">Crime Reports</a></li>

                <?php elseif ($navRole === 'admin'): ?>
                    <li class="nav-item"><a class="nav-link" href="<?php echo BASE_URL; ?>admin/dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo BASE_URL; ?>admin/users.php">Users</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo BASE_URL; ?>admin/police.php">Police Officers</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo BASE_URL; ?>admin/categories.php">Categories</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo BASE_URL; ?>admin/reports.php">All Reports</a></li>
                <?php endif; ?>

                <?php if ($navRole): ?>
                    <li class="nav-item dropdown ms-lg-3">
                        <a class="nav-link dropdown-toggle d-flex align-items-center gap-1" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i>
                            <?php echo sanitize_input($_SESSION['full_name']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><span class="dropdown-item-text small text-muted text-capitalize"><?php echo sanitize_input($navRole); ?> account</span></li>
                            <?php if ($navRole === 'citizen'): ?>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>citizen/profile.php"><i class="bi bi-person-gear me-1"></i>My Profile</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="<?php echo BASE_URL; ?>logout.php"><i class="bi bi-box-arrow-right me-1"></i>Logout</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="<?php echo BASE_URL; ?>login.php">Login</a></li>
                    <li class="nav-item ms-lg-2">
                        <a class="btn btn-sm btn-outline-light" href="<?php echo BASE_URL; ?>register.php">Register</a>
                    </li>
                <?php endif; ?>

            </ul>
        </div>
    </div>
</nav>