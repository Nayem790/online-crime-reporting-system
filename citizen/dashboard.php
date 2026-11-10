<?php
/**
 * citizen/dashboard.php
 *
 * Citizen landing page after login. Shows summary statistics and the most
 * recent reports for the logged-in citizen only. Built from the existing
 * crime_reports / crime_categories tables -- no schema changes.
 *
 * Schema notes: the owner column is crime_reports.user_id and the creation
 * timestamp is crime_reports.submitted_at.
 */

require_once __DIR__ . '/../includes/auth.php';
require_role('citizen');

$userId = (int) $_SESSION['user_id'];
$recentLimit = 10;   // rows shown in the "Recent Crime Reports" table

/**
 * Professional case reference, e.g. CR-2026-00014
 * (year of submission + zero-padded report_id).
 */
if (!function_exists('format_case_id')) {
    function format_case_id($reportId, $submittedAt) {
        return sprintf('CR-%s-%05d', date('Y', strtotime($submittedAt)), (int) $reportId);
    }
}

// ---- Summary statistics (one query, this citizen's reports only) ----
$stmt = $pdo->prepare(
    "SELECT COUNT(*)                                       AS total,
            COALESCE(SUM(status = 'Pending'), 0)            AS pending,
            COALESCE(SUM(status = 'Verified'), 0)           AS verified,
            COALESCE(SUM(status = 'Investigating'), 0)      AS investigating,
            COALESCE(SUM(status IN ('Resolved', 'Closed')), 0) AS resolved,
            COALESCE(SUM(status = 'Rejected'), 0)           AS rejected
     FROM crime_reports
     WHERE user_id = :uid"
);
$stmt->execute([':uid' => $userId]);
$stats = $stmt->fetch();

$totalReports       = (int) $stats['total'];
$pendingCount       = (int) $stats['pending'];
$investigatingCount = (int) $stats['verified'] + (int) $stats['investigating'];  // verified cases are queued for investigation
$resolvedCount      = (int) $stats['resolved'];
$rejectedCount      = (int) $stats['rejected'];

// ---- Most recent reports, newest first ----
$stmt = $pdo->prepare(
    'SELECT cr.report_id, cr.title, cr.status, cr.submitted_at, cc.category_name
     FROM crime_reports cr
     JOIN crime_categories cc ON cr.category_id = cc.category_id
     WHERE cr.user_id = :uid
     ORDER BY cr.submitted_at DESC, cr.report_id DESC
     LIMIT ' . $recentLimit
);
$stmt->execute([':uid' => $userId]);
$recentReports = $stmt->fetchAll();

// ---- Greeting ----
$nameParts = explode(' ', trim((string) $_SESSION['full_name']));
$firstName = !empty($nameParts[0]) ? $nameParts[0] : 'Citizen';

$pageTitle = 'Citizen Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">

    <!-- ================= WELCOME BANNER ================= -->
    <div class="card bg-dark text-white border-0 rounded-4 shadow-sm p-4 p-md-5 mb-4">
        <div class="card-body p-0 d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <h1 class="h3 fw-bold mb-1 text-white">Welcome back, <?php echo sanitize_input($firstName); ?></h1>
                <p class="text-white-50 mb-0">Track your reports, see investigation progress, and file new incidents securely.</p>
            </div>
            <div>
                <a href="<?php echo BASE_URL; ?>citizen/submit_report.php" class="btn btn-light btn-lg px-4 text-dark fw-semibold shadow-sm">
                    <i class="bi bi-plus-circle me-2 text-primary"></i>File New Report
                </a>
            </div>
        </div>
    </div>

    <!-- ================= METRIC CARDS ================= -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card">
                <span class="stat-icon is-dark"><i class="bi bi-folder2-open"></i></span>
                <div class="stat-body">
                    <div class="stat-label">Total Reports</div>
                    <div class="stat-value"><?php echo $totalReports; ?></div>
                    <?php if ($rejectedCount > 0): ?>
                        <span class="stat-indicator is-down"><?php echo $rejectedCount; ?> rejected</span>
                    <?php else: ?>
                        <span class="stat-indicator">All time</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="stat-card">
                <span class="stat-icon is-warning"><i class="bi bi-hourglass-split"></i></span>
                <div class="stat-body">
                    <div class="stat-label">Pending Review</div>
                    <div class="stat-value"><?php echo $pendingCount; ?></div>
                    <?php if ($pendingCount > 0): ?>
                        <span class="stat-indicator is-warn">Awaiting an officer</span>
                    <?php else: ?>
                        <span class="stat-indicator">Nothing waiting</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="stat-card">
                <span class="stat-icon"><i class="bi bi-search"></i></span>
                <div class="stat-body">
                    <div class="stat-label">Under Investigation</div>
                    <div class="stat-value"><?php echo $investigatingCount; ?></div>
                    <span class="stat-indicator">Verified &amp; active cases</span>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="stat-card">
                <span class="stat-icon is-success"><i class="bi bi-check2-circle"></i></span>
                <div class="stat-body">
                    <div class="stat-label">Resolved</div>
                    <div class="stat-value"><?php echo $resolvedCount; ?></div>
                    <?php if ($resolvedCount > 0): ?>
                        <span class="stat-indicator is-up">Cases closed out</span>
                    <?php else: ?>
                        <span class="stat-indicator">None yet</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ================= RECENT REPORTS / EMPTY STATE ================= -->
    <?php if (empty($recentReports)): ?>

        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5 px-3">
                <div class="stat-icon mx-auto mb-3" style="width:72px;height:72px;font-size:2rem;">
                    <i class="bi bi-folder-plus"></i>
                </div>
                <h2 class="h5 mb-2">You haven't filed any reports yet</h2>
                <p class="text-muted mx-auto mb-4" style="max-width: 460px;">
                    When you report an incident, it will appear here so you can follow its
                    verification and investigation progress.
                </p>
                <a href="<?php echo BASE_URL; ?>citizen/submit_report.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-1"></i> File Your First Report
                </a>
            </div>
        </div>

    <?php else: ?>

        <div class="card border-0 shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center bg-white py-3">
                <span class="fw-semibold">Recent Crime Reports</span>
                <a href="<?php echo BASE_URL; ?>citizen/report_history.php" class="small text-decoration-none">
                    View all<?php echo $totalReports > $recentLimit ? ' ' . $totalReports . ' reports' : ''; ?>
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Case ID</th>
                                <th>Crime Type</th>
                                <th>Date Submitted</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentReports as $report): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold">
                                            <?php echo sanitize_input(format_case_id($report['report_id'], $report['submitted_at'])); ?>
                                        </div>
                                        <div class="text-muted small">
                                            <?php echo sanitize_input(mb_strimwidth($report['title'], 0, 55, '...', 'UTF-8')); ?>
                                        </div>
                                    </td>
                                    <td><?php echo sanitize_input($report['category_name']); ?></td>
                                    <td class="text-muted"><?php echo format_date($report['submitted_at'], 'M d, Y'); ?></td>
                                    <td>
                                        <span class="badge <?php echo get_status_badge_class($report['status']); ?>">
                                            <?php echo sanitize_input($report['status']); ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?php echo BASE_URL; ?>citizen/report_details.php?id=<?php echo (int) $report['report_id']; ?>"
                                           class="btn btn-sm btn-outline-primary">
                                            View Details
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>