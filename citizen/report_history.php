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
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">My Report History</h1>
        <a href="<?php echo BASE_URL; ?>citizen/submit_report.php" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i> Submit New Report
        </a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <?php if (empty($reports)): ?>
                <div class="p-5 text-center text-muted">
                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                    You haven't submitted any reports yet.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Title</th>
                                <th>Category</th>
                                <th>Incident Date</th>
                                <th>Status</th>
                                <th>Submitted</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reports as $report): ?>
                                <tr>
                                    <td class="text-muted">#<?php echo (int) $report['report_id']; ?></td>
                                    <td><?php echo sanitize_input($report['title']); ?></td>
                                    <td><?php echo sanitize_input($report['category_name']); ?></td>
                                    <td><?php echo format_date($report['incident_date'], 'M d, Y'); ?></td>
                                    <td>
                                        <span class="badge <?php echo get_status_badge_class($report['status']); ?>">
                                            <?php echo sanitize_input($report['status']); ?>
                                        </span>
                                    </td>
                                    <td class="text-muted small"><?php echo format_date($report['submitted_at'], 'M d, Y h:i A'); ?></td>
                                    <td class="text-end">
                                        <a href="<?php echo BASE_URL; ?>citizen/report_details.php?id=<?php echo (int) $report['report_id']; ?>"
                                           class="btn btn-sm btn-outline-primary">View Details</a>
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