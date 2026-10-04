<?php
/**
 * admin/dashboard.php
 *
 * Landing page for a logged-in Administrator. Shows system-wide
 * statistics built entirely from existing tables (users,
 * crime_reports, crime_categories) -- no new tables.
 */

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

// ---- User counts by role ----
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'citizen'");
$totalCitizens = (int) $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'police'");
$totalPolice = (int) $stmt->fetchColumn();

// ---- Report totals ----
$stmt = $pdo->query('SELECT COUNT(*) FROM crime_reports');
$totalReports = (int) $stmt->fetchColumn();

$stmt = $pdo->query('SELECT COUNT(*) FROM crime_categories');
$totalCategories = (int) $stmt->fetchColumn();

// ---- Report counts by status ----
$statusCounts = [
    'Pending'       => 0,
    'Verified'      => 0,
    'Rejected'      => 0,
    'Investigating' => 0,
    'Resolved'      => 0,
];
$stmt = $pdo->query('SELECT status, COUNT(*) AS cnt FROM crime_reports GROUP BY status');
foreach ($stmt->fetchAll() as $row) {
    if (array_key_exists($row['status'], $statusCounts)) {
        $statusCounts[$row['status']] = (int) $row['cnt'];
    }
}

// ---- Reports per category ----
$stmt = $pdo->query(
    'SELECT cc.category_name, COUNT(cr.report_id) AS report_count
     FROM crime_categories cc
     LEFT JOIN crime_reports cr ON cc.category_id = cr.category_id
     GROUP BY cc.category_id, cc.category_name
     ORDER BY report_count DESC, cc.category_name ASC'
);
$categoryStats = $stmt->fetchAll();
$maxCategoryCount = max(array_column($categoryStats, 'report_count') ?: [0]);

$pageTitle = 'Admin Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">

    <div class="mb-4">
        <h1 class="h3 mb-1">Welcome, <?php echo sanitize_input($_SESSION['full_name']); ?></h1>
        <p class="text-muted mb-0">System overview and statistics.</p>
    </div>

    <!-- ================= TOP-LEVEL STAT CARDS ================= -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Total Citizens</div>
                    <div class="fs-2 fw-bold"><?php echo $totalCitizens; ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Police Officers</div>
                    <div class="fs-2 fw-bold"><?php echo $totalPolice; ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Total Reports</div>
                    <div class="fs-2 fw-bold"><?php echo $totalReports; ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Crime Categories</div>
                    <div class="fs-2 fw-bold"><?php echo $totalCategories; ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ================= REPORT STATUS BREAKDOWN ================= -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-semibold">Report Status Breakdown</div>
        <div class="card-body">
            <div class="row g-3 text-center">
                <div class="col-6 col-md">
                    <div class="fs-4 fw-bold text-warning"><?php echo $statusCounts['Pending']; ?></div>
                    <div class="text-muted small">Pending</div>
                </div>
                <div class="col-6 col-md">
                    <div class="fs-4 fw-bold text-info"><?php echo $statusCounts['Verified']; ?></div>
                    <div class="text-muted small">Verified</div>
                </div>
                <div class="col-6 col-md">
                    <div class="fs-4 fw-bold text-primary"><?php echo $statusCounts['Investigating']; ?></div>
                    <div class="text-muted small">Investigating</div>
                </div>
                <div class="col-6 col-md">
                    <div class="fs-4 fw-bold text-success"><?php echo $statusCounts['Resolved']; ?></div>
                    <div class="text-muted small">Resolved</div>
                </div>
                <div class="col-6 col-md">
                    <div class="fs-4 fw-bold text-danger"><?php echo $statusCounts['Rejected']; ?></div>
                    <div class="text-muted small">Rejected</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- ================= CATEGORY STATISTICS ================= -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">Reports by Category</div>
                <div class="card-body">
                    <?php if (empty($categoryStats)): ?>
                        <p class="text-muted mb-0">No crime categories have been created yet.</p>
                    <?php else: ?>
                        <?php foreach ($categoryStats as $category): ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between small mb-1">
                                    <span><?php echo sanitize_input($category['category_name']); ?></span>
                                    <span class="text-muted"><?php echo (int) $category['report_count']; ?></span>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <?php
                                        $percent = $maxCategoryCount > 0
                                            ? round(($category['report_count'] / $maxCategoryCount) * 100)
                                            : 0;
                                    ?>
                                    <div class="progress-bar bg-primary" role="progressbar"
                                         style="width: <?php echo $percent; ?>%;"
                                         aria-valuenow="<?php echo $percent; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ================= QUICK LINKS ================= -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold">Quick Links</div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="<?php echo BASE_URL; ?>admin/reports.php" class="btn btn-outline-primary btn-sm text-start">
                            <i class="bi bi-file-earmark-text me-1"></i> View All Reports
                        </a>
                        <a href="<?php echo BASE_URL; ?>admin/users.php" class="btn btn-outline-secondary btn-sm text-start">
                            <i class="bi bi-people me-1"></i> Manage Citizens
                        </a>
                        <a href="<?php echo BASE_URL; ?>admin/police.php" class="btn btn-outline-secondary btn-sm text-start">
                            <i class="bi bi-shield-fill-check me-1"></i> Manage Police Officers
                        </a>
                        <a href="<?php echo BASE_URL; ?>admin/categories.php" class="btn btn-outline-secondary btn-sm text-start">
                            <i class="bi bi-tags me-1"></i> Manage Crime Categories
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>