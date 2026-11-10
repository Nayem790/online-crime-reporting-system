<?php
/**
 * citizen/report_history.php
 *
 * Shows every crime report submitted by the logged-in citizen.
 * Scoped strictly to the citizen's own user_id.
 */

require_once __DIR__ . '/../includes/auth.php';
require_role('citizen');

$userId = $_SESSION['user_id'];

/**
 * Professional case reference, e.g. CR-2026-00014
 * (year of submission + zero-padded report_id).
 */
if (!function_exists('format_case_id')) {
    function format_case_id($reportId, $submittedAt) {
        return sprintf('CR-%s-%05d', date('Y', strtotime($submittedAt)), (int) $reportId);
    }
}

$stmt = $pdo->prepare(
    'SELECT cr.report_id, cr.title, cr.status, cr.incident_date, cr.submitted_at, cc.category_name
     FROM crime_reports cr
     JOIN crime_categories cc ON cr.category_id = cc.category_id
     WHERE cr.user_id = :uid
     ORDER BY cr.submitted_at DESC'
);
$stmt->execute([':uid' => $userId]);
$reports = $stmt->fetchAll();

$pageTitle = 'Report History';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h1 class="h3 mb-1">My Report History</h1>
            <p class="text-muted mb-0">View, track, and manage all your submitted crime incident reports.</p>
        </div>
        <a href="<?php echo BASE_URL; ?>citizen/submit_report.php" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i> Submit New Report
        </a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <?php if (empty($reports)): ?>
                <div class="p-5 text-center text-muted">
                    <div class="stat-icon mx-auto mb-3" style="width:72px;height:72px;font-size:2rem;">
                        <i class="bi bi-inbox"></i>
                    </div>
                    <h2 class="h5 mb-2">No Reports Found</h2>
                    <p class="text-muted mx-auto mb-4" style="max-width: 400px;">
                        You haven't submitted any crime reports yet. Once you file a report, it will be listed here.
                    </p>
                    <a href="<?php echo BASE_URL; ?>citizen/submit_report.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-1"></i> File Your First Report
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Case ID</th>
                                <th>Title / Description</th>
                                <th>Category</th>
                                <th>Incident Date</th>
                                <th>Status</th>
                                <th>Submitted On</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reports as $report): ?>
                                <tr>
                                    <td>
                                        <span class="fw-semibold text-dark">
                                            <?php echo sanitize_input(format_case_id($report['report_id'], $report['submitted_at'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="fw-medium text-dark">
                                            <?php echo sanitize_input(mb_strimwidth($report['title'], 0, 45, '...', 'UTF-8')); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            <?php echo sanitize_input($report['category_name']); ?>
                                        </span>
                                    </td>
                                    <td class="text-muted"><?php echo format_date($report['incident_date'], 'M d, Y'); ?></td>
                                    <td>
                                        <span class="badge <?php echo get_status_badge_class($report['status']); ?>">
                                            <?php echo sanitize_input($report['status']); ?>
                                        </span>
                                    </td>
                                    <td class="text-muted small"><?php echo format_date($report['submitted_at'], 'M d, Y h:i A'); ?></td>
                                    <td class="text-end">
                                        <a href="<?php echo BASE_URL; ?>citizen/report_details.php?id=<?php echo (int) $report['report_id']; ?>"
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye me-1"></i> View Details
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>