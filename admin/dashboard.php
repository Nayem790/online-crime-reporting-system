<?php
/**
 * admin/dashboard.php
 *
 * Landing page for a logged-in Administrator. Shows system-wide
 * statistics, status breakdown with interactive charts, category analytics,
 * and recent system-wide activities.
 */

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

/**
 * Professional case reference format, e.g. CR-2026-00014
 */
if (!function_exists('format_case_id')) {
    function format_case_id($reportId, $submittedAt) {
        return sprintf('CR-%s-%05d', date('Y', strtotime($submittedAt)), (int) $reportId);
    }
}

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
    'Closed'        => 0
];
$stmt = $pdo->query('SELECT status, COUNT(*) AS cnt FROM crime_reports GROUP BY status');
foreach ($stmt->fetchAll() as $row) {
    if (array_key_exists($row['status'], $statusCounts)) {
        $statusCounts[$row['status']] = (int) $row['cnt'];
    }
}
$totalResolvedOrClosed = $statusCounts['Resolved'] + $statusCounts['Closed'];

// ---- Reports per category ----
$stmt = $pdo->query(
    'SELECT cc.category_name, COUNT(cr.report_id) AS report_count
     FROM crime_categories cc
     LEFT JOIN crime_reports cr ON cc.category_id = cr.category_id
     GROUP BY cc.category_id, cc.category_name
     ORDER BY report_count DESC, cc.category_name ASC'
);
$categoryStats = $stmt->fetchAll();

// ---- Recent System-wide Reports (Latest 5) ----
$stmt = $pdo->query(
    'SELECT cr.report_id, cr.title, cr.status, cr.submitted_at, cc.category_name, u.full_name AS citizen_name
     FROM crime_reports cr
     JOIN crime_categories cc ON cr.category_id = cc.category_id
     JOIN users u ON cr.user_id = u.user_id
     ORDER BY cr.submitted_at DESC, cr.report_id DESC
     LIMIT 5'
);
$recentSystemReports = $stmt->fetchAll();

$pageTitle = 'Admin Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">

    <!-- ================= WELCOME BANNER ================= -->
    <div class="card bg-dark text-white border-0 rounded-4 shadow-sm p-4 mb-4">
        <div class="card-body p-0 d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <h1 class="h3 fw-bold mb-1 text-white">Admin Executive Dashboard</h1>
                <p class="text-white-50 mb-0">System-wide monitoring, user governance, and crime analytics overview.</p>
            </div>
            <div>
                <span class="badge bg-primary px-3 py-2 fs-6">
                    <i class="bi bi-shield-shaded me-1"></i> Secure Admin Control
                </span>
            </div>
        </div>
    </div>

    <!-- ================= TOP-LEVEL STAT CARDS ================= -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="stat-card h-100">
                <span class="stat-icon is-dark"><i class="bi bi-people"></i></span>
                <div class="stat-body">
                    <div class="stat-label">Total Citizens</div>
                    <div class="stat-value"><?php echo $totalCitizens; ?></div>
                    <span class="stat-indicator">Registered accounts</span>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="stat-card h-100">
                <span class="stat-icon is-success"><i class="bi bi-shield-fill-check"></i></span>
                <div class="stat-body">
                    <div class="stat-label">Police Officers</div>
                    <div class="stat-value"><?php echo $totalPolice; ?></div>
                    <span class="stat-indicator">Active field personnel</span>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="stat-card h-100">
                <span class="stat-icon is-warning"><i class="bi bi-folder2-open"></i></span>
                <div class="stat-body">
                    <div class="stat-label">Total Reports</div>
                    <div class="stat-value"><?php echo $totalReports; ?></div>
                    <span class="stat-indicator">System-wide filed cases</span>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="stat-card h-100">
                <span class="stat-icon"><i class="bi bi-tags"></i></span>
                <div class="stat-body">
                    <div class="stat-label">Crime Categories</div>
                    <div class="stat-value"><?php echo $totalCategories; ?></div>
                    <span class="stat-indicator">Active classifications</span>
                </div>
            </div>
        </div>
    </div>

    <!-- ================= VISUAL ANALYTICS SECTION (GRAPHS) ================= -->
    <div class="row g-4 mb-4">
        <!-- Report Status Breakdown Doughnut Chart -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm h-100 rounded-4">
                <div class="card-header bg-white fw-semibold py-3 d-flex align-items-center justify-content-between">
                    <span><i class="bi bi-pie-chart me-2 text-primary"></i> Status Proportions</span>
                    <span class="badge bg-light text-dark border">Total: <?php echo $totalReports; ?></span>
                </div>
                <div class="card-body d-flex flex-column justify-content-center align-items-center">
                    <div style="max-width: 260px; width: 100%;">
                        <canvas id="statusDoughnutChart"></canvas>
                    </div>
                    <div class="row g-2 w-100 mt-4 text-center small text-secondary">
                        <div class="col-4"><span class="badge bg-warning text-dark">&nbsp;</span> Pending: <?php echo $statusCounts['Pending']; ?></div>
                        <div class="col-4"><span class="badge bg-info text-dark">&nbsp;</span> Verified: <?php echo $statusCounts['Verified']; ?></div>
                        <div class="col-4"><span class="badge bg-primary">&nbsp;</span> Investigating: <?php echo $statusCounts['Investigating']; ?></div>
                        <div class="col-6"><span class="badge bg-success">&nbsp;</span> Resolved/Closed: <?php echo $totalResolvedOrClosed; ?></div>
                        <div class="col-6"><span class="badge bg-danger">&nbsp;</span> Rejected: <?php echo $statusCounts['Rejected']; ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reports Distribution by Category Bar Chart -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm h-100 rounded-4">
                <div class="card-header bg-white fw-semibold py-3">
                    <i class="bi bi-bar-chart-fill me-2 text-primary"></i> Reports Distribution by Category
                </div>
                <div class="card-body d-flex flex-column justify-content-center">
                    <?php if (empty($categoryStats)): ?>
                        <p class="text-muted text-center mb-0">No crime categories have been created yet.</p>
                    <?php else: ?>
                        <div style="height: 260px; width: 100%;">
                            <canvas id="categoryBarChart"></canvas>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ================= QUICK MANAGEMENT LINKS & RECENT REPORTS ================= -->
    <div class="row g-4">
        <!-- ================= QUICK MANAGEMENT LINKS ================= -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100 rounded-4">
                <div class="card-header bg-white fw-semibold py-3">
                    <i class="bi bi-lightning-charge me-2 text-primary"></i> Administrative Quick Actions
                </div>
                <div class="card-body d-flex flex-column justify-content-between">
                    <div class="d-grid gap-3">
                        <a href="<?php echo BASE_URL; ?>admin/reports.php" class="btn btn-outline-primary py-2.5 text-start d-flex align-items-center justify-content-between">
                            <span><i class="bi bi-file-earmark-text me-2"></i> View System-Wide Reports</span>
                            <i class="bi bi-chevron-right small"></i>
                        </a>
                        <a href="<?php echo BASE_URL; ?>admin/users.php" class="btn btn-outline-secondary py-2.5 text-start d-flex align-items-center justify-content-between">
                            <span><i class="bi bi-people me-2"></i> Manage Citizen Accounts</span>
                            <i class="bi bi-chevron-right small"></i>
                        </a>
                        <a href="<?php echo BASE_URL; ?>admin/police.php" class="btn btn-outline-secondary py-2.5 text-start d-flex align-items-center justify-content-between">
                            <span><i class="bi bi-shield-fill-check me-2"></i> Manage Police Officers</span>
                            <i class="bi bi-chevron-right small"></i>
                        </a>
                        <a href="<?php echo BASE_URL; ?>admin/categories.php" class="btn btn-outline-secondary py-2.5 text-start d-flex align-items-center justify-content-between">
                            <span><i class="bi bi-tags me-2"></i> Manage Crime Categories</span>
                            <i class="bi bi-chevron-right small"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= LATEST SYSTEM-WIDE REPORTS TABLE ================= -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm h-100 rounded-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                    <span class="fw-semibold"><i class="bi bi-clock-history me-2 text-primary"></i> Recent System-Wide Reports</span>
                    <a href="<?php echo BASE_URL; ?>admin/reports.php" class="small text-decoration-none fw-medium">View All Reports &rarr;</a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($recentSystemReports)): ?>
                        <div class="p-4 text-center text-muted">No crime reports filed in the system yet.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Case ID</th>
                                        <th>Citizen</th>
                                        <th>Crime Type</th>
                                        <th>Submitted Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentSystemReports as $rep): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold">
                                                    <?php echo sanitize_input(format_case_id($rep['report_id'], $rep['submitted_at'])); ?>
                                                </div>
                                                <div class="text-muted small">
                                                    <?php echo sanitize_input(mb_strimwidth($rep['title'], 0, 30, '...', 'UTF-8')); ?>
                                                </div>
                                            </td>
                                            <td><?php echo sanitize_input($rep['citizen_name']); ?></td>
                                            <td><?php echo sanitize_input($rep['category_name']); ?></td>
                                            <td class="text-muted"><?php echo format_date($rep['submitted_at'], 'M d, Y'); ?></td>
                                            <td>
                                                <span class="badge <?php echo get_status_badge_class($rep['status']); ?>">
                                                    <?php echo sanitize_input($rep['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Include Chart.js CDN for interactive visual graphs -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    // 1. Status Breakdown Doughnut Chart
    const statusCtx = document.getElementById('statusDoughnutChart').getContext('2d');
    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: ['Pending', 'Verified', 'Investigating', 'Resolved/Closed', 'Rejected'],
            datasets: [{
                data: [
                    <?php echo $statusCounts['Pending']; ?>,
                    <?php echo $statusCounts['Verified']; ?>,
                    <?php echo $statusCounts['Investigating']; ?>,
                    <?php echo $totalResolvedOrClosed; ?>,
                    <?php echo $statusCounts['Rejected']; ?>
                ],
                backgroundColor: ['#ffc107', '#0dcaf0', '#0d6efd', '#198754', '#dc3545'],
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { display: false }
            },
            cutout: '65%'
        }
    });

    // 2. Category Distribution Bar Chart
    <?php if (!empty($categoryStats)): ?>
    const catLabels = <?php echo json_encode(array_column($categoryStats, 'category_name')); ?>;
    const catData = <?php echo json_encode(array_column($categoryStats, 'report_count')); ?>;

    const catCtx = document.getElementById('categoryBarChart').getContext('2d');
    new Chart(catCtx, {
        type: 'bar',
        data: {
            labels: catLabels,
            datasets: [{
                label: 'Cases Filed',
                data: catData,
                backgroundColor: '#0d6efd',
                borderRadius: 6,
                barThickness: 'flex',
                maxBarThickness: 40
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0 }
                },
                x: {
                    grid: { display: false }
                }
            }
        }
    });
    <?php endif; ?>
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>