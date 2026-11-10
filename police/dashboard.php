<?php
/**
 * police/dashboard.php
 *
 * Landing page for a logged-in Police Officer with premium compact visual analytics.
 */

require_once __DIR__ . '/../includes/auth.php';
require_role('police');

$officerId = (int) $_SESSION['user_id'];

if (!function_exists('format_case_id')) {
    function format_case_id($reportId, $submittedAt) {
        return sprintf('CR-%s-%05d', date('Y', strtotime($submittedAt)), (int) $reportId);
    }
}

// ---- System-wide report counts by status ----
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
$totalSystemReports = array_sum($statusCounts);
$totalSystemReports = $totalSystemReports > 0 ? $totalSystemReports : 1; // Prevent division by zero

// ---- Percentages for Sleek Custom Progress Bars ----
$pctPending = round(($statusCounts['Pending'] / $totalSystemReports) * 100);
$pctVerified = round(($statusCounts['Verified'] / $totalSystemReports) * 100);
$pctInvestigating = round(($statusCounts['Investigating'] / $totalSystemReports) * 100);
$pctResolved = round(($totalResolvedOrClosed / $totalSystemReports) * 100);
$pctRejected = round(($statusCounts['Rejected'] / $totalSystemReports) * 100);

// ---- This officer's total investigation records ----
$stmt = $pdo->prepare('SELECT COUNT(*) FROM investigations WHERE officer_id = :officer_id');
$stmt->execute([':officer_id' => $officerId]);
$myInvestigationCount = (int) $stmt->fetchColumn();

// ---- Reports awaiting verification ----
$stmt = $pdo->prepare(
    'SELECT cr.report_id, cr.title, cc.category_name, cr.submitted_at
     FROM crime_reports cr
     JOIN crime_categories cc ON cr.category_id = cc.category_id
     WHERE cr.status = "Pending"
     ORDER BY cr.submitted_at ASC
     LIMIT 5'
);
$stmt->execute();
$pendingReports = $stmt->fetchAll();

// ---- This officer's most recent investigation updates ----
$stmt = $pdo->prepare(
    'SELECT inv.report_id, inv.status, inv.updated_at, cr.title, cr.submitted_at
     FROM investigations inv
     JOIN crime_reports cr ON inv.report_id = cr.report_id
     WHERE inv.officer_id = :officer_id
     ORDER BY inv.updated_at DESC
     LIMIT 5'
);
$stmt->execute([':officer_id' => $officerId]);
$myRecentUpdates = $stmt->fetchAll();

$pageTitle = 'Police Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">

    <!-- ================= WELCOME BANNER ================= -->
    <div class="card bg-primary text-white border-0 shadow-sm mb-4 rounded-4">
        <div class="card-body p-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <h1 class="h3 fw-bold mb-1 text-white">Officer Portal &mdash; Welcome, <?php echo sanitize_input($_SESSION['full_name'] ?? 'Officer'); ?></h1>
                <p class="text-white-50 mb-0">Manage citizen reports, verify triage submissions, and record case investigation progress.</p>
            </div>
            <div>
                <a href="<?php echo BASE_URL; ?>police/reports.php" class="btn btn-light px-4 py-2 fw-semibold text-primary shadow-sm">
                    <i class="bi bi-shield-lock-fill me-1"></i> View All Case Files
                </a>
            </div>
        </div>
    </div>

    <!-- ================= EXECUTIVE STAT CARDS ================= -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card h-100">
                <span class="stat-icon is-warning"><i class="bi bi-hourglass-split"></i></span>
                <div class="stat-body">
                    <div class="stat-label">Awaiting Triage</div>
                    <div class="stat-value"><?php echo $statusCounts['Pending']; ?></div>
                    <span class="stat-indicator is-warn">Requires verification</span>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="stat-card h-100">
                <span class="stat-icon is-info"><i class="bi bi-search"></i></span>
                <div class="stat-body">
                    <div class="stat-label">Under Investigation</div>
                    <div class="stat-value"><?php echo $statusCounts['Investigating']; ?></div>
                    <span class="stat-indicator">Active cases</span>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="stat-card h-100">
                <span class="stat-icon is-dark"><i class="bi bi-journal-check"></i></span>
                <div class="stat-body">
                    <div class="stat-label">My Investigation Log</div>
                    <div class="stat-value"><?php echo $myInvestigationCount; ?></div>
                    <span class="stat-indicator">Personal updates</span>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="stat-card h-100">
                <span class="stat-icon is-success"><i class="bi bi-check2-all"></i></span>
                <div class="stat-body">
                    <div class="stat-label">Resolved System-Wide</div>
                    <div class="stat-value"><?php echo $statusCounts['Resolved']; ?></div>
                    <span class="stat-indicator is-up">Completed cases</span>
                </div>
            </div>
        </div>
    </div>

    <!-- ================= ULTRA-PREMIUM COMPACT ANALYTICS CARD ================= -->
    <div class="card border-0 shadow-sm mb-4 rounded-4 overflow-hidden">
        <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center border-bottom">
            <h6 class="mb-0 text-dark fw-semibold">
                <i class="bi bi-pie-chart-fill text-primary me-2"></i> Case Lifecycle Distribution &amp; Proportions
            </h6>
            <span class="badge bg-dark px-3 py-1.5 rounded-pill">Total System Cases: <?php echo array_sum($statusCounts); ?></span>
        </div>
        <div class="card-body p-4">
            <div class="row align-items-center g-4">
                <!-- Left: Minimalist Centered Donut Chart -->
                <div class="col-lg-4 text-center border-end-lg">
                    <div class="position-relative d-inline-block" style="width: 140px; height: 140px;">
                        <canvas id="compactDoughnut"></canvas>
                        <div class="position-absolute top-50 start-50 translate-middle text-center" style="pointer-events: none;">
                            <span class="fs-4 fw-bold text-dark d-block lh-1"><?php echo array_sum($statusCounts); ?></span>
                            <span class="text-muted" style="font-size: 0.7rem; text-transform: uppercase; font-weight: 600;">Cases</span>
                        </div>
                    </div>
                </div>

                <!-- Right: Sleek Proportional Breakdown Bars ( incluyendo Rejected ) -->
                <div class="col-lg-8">
                    <div class="d-flex flex-column gap-2">
                        <!-- Pending -->
                        <div>
                            <div class="d-flex justify-content-between small fw-medium mb-1">
                                <span class="text-secondary"><i class="bi bi-circle-fill text-warning me-1" style="font-size: 0.5rem;"></i> Pending Review</span>
                                <span class="fw-bold text-dark"><?php echo $statusCounts['Pending']; ?> <span class="text-muted fw-normal">(<?php echo $pctPending; ?>%)</span></span>
                            </div>
                            <div class="progress" style="height: 5px;"><div class="progress-bar bg-warning rounded-pill" style="width: <?php echo $pctPending; ?>%;"></div></div>
                        </div>
                        <!-- Verified -->
                        <div>
                            <div class="d-flex justify-content-between small fw-medium mb-1">
                                <span class="text-secondary"><i class="bi bi-circle-fill text-info me-1" style="font-size: 0.5rem;"></i> Verified</span>
                                <span class="fw-bold text-dark"><?php echo $statusCounts['Verified']; ?> <span class="text-muted fw-normal">(<?php echo $pctVerified; ?>%)</span></span>
                            </div>
                            <div class="progress" style="height: 5px;"><div class="progress-bar bg-info rounded-pill" style="width: <?php echo $pctVerified; ?>%;"></div></div>
                        </div>
                        <!-- Investigating -->
                        <div>
                            <div class="d-flex justify-content-between small fw-medium mb-1">
                                <span class="text-secondary"><i class="bi bi-circle-fill text-primary me-1" style="font-size: 0.5rem;"></i> Investigating</span>
                                <span class="fw-bold text-dark"><?php echo $statusCounts['Investigating']; ?> <span class="text-muted fw-normal">(<?php echo $pctInvestigating; ?>%)</span></span>
                            </div>
                            <div class="progress" style="height: 5px;"><div class="progress-bar bg-primary rounded-pill" style="width: <?php echo $pctInvestigating; ?>%;"></div></div>
                        </div>
                        <!-- Resolved / Closed -->
                        <div>
                            <div class="d-flex justify-content-between small fw-medium mb-1">
                                <span class="text-secondary"><i class="bi bi-circle-fill text-success me-1" style="font-size: 0.5rem;"></i> Resolved / Closed</span>
                                <span class="fw-bold text-dark"><?php echo $totalResolvedOrClosed; ?> <span class="text-muted fw-normal">(<?php echo $pctResolved; ?>%)</span></span>
                            </div>
                            <div class="progress" style="height: 5px;"><div class="progress-bar bg-success rounded-pill" style="width: <?php echo $pctResolved; ?>%;"></div></div>
                        </div>
                        <!-- Rejected (Added) -->
                        <div>
                            <div class="d-flex justify-content-between small fw-medium mb-1">
                                <span class="text-secondary"><i class="bi bi-circle-fill text-danger me-1" style="font-size: 0.5rem;"></i> Rejected</span>
                                <span class="fw-bold text-dark"><?php echo $statusCounts['Rejected']; ?> <span class="text-muted fw-normal">(<?php echo $pctRejected; ?>%)</span></span>
                            </div>
                            <div class="progress" style="height: 5px;"><div class="progress-bar bg-danger rounded-pill" style="width: <?php echo $pctRejected; ?>%;"></div></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- ================= REPORTS AWAITING VERIFICATION ================= -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm h-100 rounded-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 text-dark fs-6 fw-semibold">
                        <i class="bi bi-shield-exclamation text-warning me-2"></i>Pending Verification Queue
                    </h5>
                    <a href="<?php echo BASE_URL; ?>police/reports.php?status=Pending" class="small text-decoration-none">
                        View all pending
                    </a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($pendingReports)): ?>
                        <div class="p-5 text-center text-muted">
                            <div class="stat-icon mx-auto mb-3" style="width:64px;height:64px;font-size:1.8rem;">
                                <i class="bi bi-check2-circle text-success"></i>
                            </div>
                            <h2 class="h6 mb-1">Queue Clear</h2>
                            <p class="small text-muted mb-0">No incident reports are currently awaiting police triage.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Case ID</th>
                                        <th>Crime Type</th>
                                        <th>Submitted</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pendingReports as $report): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold text-dark">
                                                    <?php echo sanitize_input(format_case_id($report['report_id'], $report['submitted_at'])); ?>
                                                </div>
                                                <div class="text-muted small">
                                                    <?php echo sanitize_input(mb_strimwidth($report['title'], 0, 35, '...', 'UTF-8')); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark border">
                                                    <?php echo sanitize_input($report['category_name']); ?>
                                                </span>
                                            </td>
                                            <td class="text-muted small"><?php echo format_date($report['submitted_at'], 'M d, Y'); ?></td>
                                            <td class="text-end">
                                                <a href="<?php echo BASE_URL; ?>police/report_details.php?id=<?php echo (int) $report['report_id']; ?>"
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-clipboard-check me-1"></i>Review
                                                </a>
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

        <!-- ================= MY RECENT INVESTIGATION UPDATES ================= -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm h-100 rounded-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 text-dark fs-6 fw-semibold">
                        <i class="bi bi-clock-history me-2 text-primary"></i>My Recent Logs
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($myRecentUpdates)): ?>
                        <div class="p-5 text-center text-muted">
                            <div class="stat-icon mx-auto mb-3" style="width:64px;height:64px;font-size:1.8rem;">
                                <i class="bi bi-journal-text"></i>
                            </div>
                            <h2 class="h6 mb-1">No Activity Logged</h2>
                            <p class="small text-muted mb-0">You haven't recorded updates on any case files yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($myRecentUpdates as $update): ?>
                                <div class="list-group-item p-3">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <span class="badge bg-light text-dark border mb-1">
                                                <?php echo sanitize_input(format_case_id($update['report_id'], $update['submitted_at'])); ?>
                                            </span>
                                            <h6 class="mb-0 fs-6">
                                                <a href="<?php echo BASE_URL; ?>police/report_details.php?id=<?php echo (int) $update['report_id']; ?>" 
                                                   class="text-decoration-none text-dark fw-semibold">
                                                    <?php echo sanitize_input(mb_strimwidth($update['title'], 0, 32, '...', 'UTF-8')); ?>
                                                </a>
                                            </h6>
                                        </div>
                                        <span class="badge <?php echo get_status_badge_class($update['status']); ?>">
                                            <?php echo sanitize_input($update['status']); ?>
                                        </span>
                                    </div>
                                    <div class="text-muted small">
                                        <i class="bi bi-calendar3 me-1"></i><?php echo format_date($update['updated_at'], 'M d, Y h:i A'); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Ultra-Clean Minimalist Chart.js Script -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const ctx = document.getElementById('compactDoughnut').getContext('2d');
    new Chart(ctx, {
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
                borderWidth: 0,
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return ' ' + context.label + ': ' + context.raw + ' cases';
                        }
                    }
                }
            },
            cutout: '78%'
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>