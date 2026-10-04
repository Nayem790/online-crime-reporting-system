<?php
/**
 * citizen/dashboard.php
 *
 * Citizen landing page after login. Shows a summary of the citizen's
 * own crime reports, built entirely from the existing crime_reports /
 * crime_categories / notifications tables -- no new tables.
 */

require_once __DIR__ . '/../includes/auth.php';
require_role('citizen');

$userId = $_SESSION['user_id'];

// ---- Total report count ----
$stmt = $pdo->prepare('SELECT COUNT(*) FROM crime_reports WHERE user_id = :uid');
$stmt->execute([':uid' => $userId]);
$totalReports = (int) $stmt->fetchColumn();

// ---- Report count per status ----
$statusCounts = [
    'Pending'       => 0,
    'Verified'      => 0,
    'Rejected'      => 0,
    'Investigating' => 0,
    'Resolved'      => 0,
];
$stmt = $pdo->prepare('SELECT status, COUNT(*) AS cnt FROM crime_reports WHERE user_id = :uid GROUP BY status');
$stmt->execute([':uid' => $userId]);
foreach ($stmt->fetchAll() as $row) {
    if (array_key_exists($row['status'], $statusCounts)) {
        $statusCounts[$row['status']] = (int) $row['cnt'];
    }
}

// ---- Unread notifications ----
$stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0');
$stmt->execute([':uid' => $userId]);
$unreadNotifications = (int) $stmt->fetchColumn();

// ---- 5 most recent reports ----
$stmt = $pdo->prepare(
    'SELECT cr.report_id, cr.title, cr.status, cr.submitted_at, cc.category_name
     FROM crime_reports cr
     JOIN crime_categories cc ON cr.category_id = cc.category_id
     WHERE cr.user_id = :uid
     ORDER BY cr.submitted_at DESC
     LIMIT 5'
);
$stmt->execute([':uid' => $userId]);
$recentReports = $stmt->fetchAll();

$pageTitle = 'Citizen Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Welcome, <?php echo sanitize_input($_SESSION['full_name']); ?></h1>
            <p class="text-muted mb-0">Here is a summary of your crime reports.</p>
        </div>
        <a href="<?php echo BASE_URL; ?>citizen/submit_report.php" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i> Submit New Report
        </a>
    </div>

    <!-- ================= STAT CARDS ================= -->
    <div class="row g-3 mb-4">
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
                    <div class="text-muted small text-uppercase">Pending</div>
                    <div class="fs-2 fw-bold text-warning"><?php echo $statusCounts['Pending']; ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Investigating</div>
                    <div class="fs-2 fw-bold text-primary"><?php echo $statusCounts['Investigating']; ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Resolved</div>
                    <div class="fs-2 fw-bold text-success"><?php echo $statusCounts['Resolved']; ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- ================= RECENT REPORTS ================= -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">Recent Reports</span>
                    <a href="<?php echo BASE_URL; ?>citizen/report_history.php" class="small">View all</a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($recentReports)): ?>
                        <div class="p-4 text-center text-muted">
                            <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                            You haven't submitted any reports yet.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Title</th>
                                        <th>Category</th>
                                        <th>Status</th>
                                        <th>Submitted</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentReports as $report): ?>
                                        <tr>
                                            <td><?php echo sanitize_input($report['title']); ?></td>
                                            <td><?php echo sanitize_input($report['category_name']); ?></td>
                                            <td>
                                                <span class="badge <?php echo get_status_badge_class($report['status']); ?>">
                                                    <?php echo sanitize_input($report['status']); ?>
                                                </span>
                                            </td>
                                            <td class="text-muted small"><?php echo format_date($report['submitted_at'], 'M d, Y'); ?></td>
                                            <td class="text-end">
                                                <a href="<?php echo BASE_URL; ?>citizen/report_details.php?id=<?php echo (int) $report['report_id']; ?>"
                                                   class="btn btn-sm btn-outline-primary">View</a>
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

        <!-- ================= QUICK LINKS ================= -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h2 class="h6 mb-3">Quick Links</h2>
                    <div class="d-grid gap-2">
                        <a href="<?php echo BASE_URL; ?>citizen/submit_report.php" class="btn btn-outline-primary btn-sm text-start">
                            <i class="bi bi-plus-circle me-1"></i> Submit New Report
                        </a>
                        <a href="<?php echo BASE_URL; ?>citizen/report_history.php" class="btn btn-outline-secondary btn-sm text-start">
                            <i class="bi bi-clock-history me-1"></i> Report History
                        </a>
                        <a href="<?php echo BASE_URL; ?>citizen/notifications.php" class="btn btn-outline-secondary btn-sm text-start">
                            <i class="bi bi-bell me-1"></i> Notifications
                            <?php if ($unreadNotifications > 0): ?>
                                <span class="badge bg-danger ms-1"><?php echo $unreadNotifications; ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="<?php echo BASE_URL; ?>citizen/help.php" class="btn btn-outline-secondary btn-sm text-start">
                            <i class="bi bi-life-preserver me-1"></i> Help &amp; Resources
                        </a>
                        <a href="<?php echo BASE_URL; ?>citizen/profile.php" class="btn btn-outline-secondary btn-sm text-start">
                            <i class="bi bi-person-gear me-1"></i> My Profile
                        </a>
                    </div>
                </div>
            </div>

            <?php if ($statusCounts['Rejected'] > 0): ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h2 class="h6 mb-2 text-danger"><i class="bi bi-x-circle me-1"></i>Rejected Reports</h2>
                        <p class="small text-muted mb-0">
                            You have <?php echo $statusCounts['Rejected']; ?> rejected report(s).
                            Check the verification remarks in your report history for details.
                        </p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>