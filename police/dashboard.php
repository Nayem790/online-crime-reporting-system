<?php
/**
 * police/dashboard.php
 *
 * Landing page for a logged-in Police Officer. Shows system-wide
 * report status counts (any officer can act on any report -- there is
 * no per-officer report assignment in the approved design), plus the
 * officer's own investigation workload. Uses only existing tables.
 */

require_once __DIR__ . '/../includes/auth.php';
require_role('police');

$officerId = $_SESSION['user_id'];

// ---- System-wide report counts by status ----
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

// ---- This officer's total investigation records ----
$stmt = $pdo->prepare('SELECT COUNT(*) FROM investigations WHERE officer_id = :officer_id');
$stmt->execute([':officer_id' => $officerId]);
$myInvestigationCount = (int) $stmt->fetchColumn();

// ---- Reports awaiting verification (oldest first, for triage) ----
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
    'SELECT inv.report_id, inv.status, inv.updated_at, cr.title
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

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Welcome, <?php echo sanitize_input($_SESSION['full_name']); ?></h1>
            <p class="text-muted mb-0">Overview of crime reports and your investigations.</p>
        </div>
        <a href="<?php echo BASE_URL; ?>police/reports.php" class="btn btn-primary">
            <i class="bi bi-list-check me-1"></i> View All Reports
        </a>
    </div>

    <!-- ================= STAT CARDS ================= -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Awaiting Verification</div>
                    <div class="fs-2 fw-bold text-warning"><?php echo $statusCounts['Pending']; ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Under Investigation</div>
                    <div class="fs-2 fw-bold text-primary"><?php echo $statusCounts['Investigating']; ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">My Investigation Records</div>
                    <div class="fs-2 fw-bold"><?php echo $myInvestigationCount; ?></div>
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
        <!-- ================= REPORTS AWAITING VERIFICATION ================= -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">Reports Awaiting Verification</span>
                    <a href="<?php echo BASE_URL; ?>police/reports.php" class="small">View all</a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($pendingReports)): ?>
                        <div class="p-4 text-center text-muted">
                            <i class="bi bi-check2-circle fs-2 d-block mb-2"></i>
                            No reports are currently awaiting verification.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Title</th>
                                        <th>Category</th>
                                        <th>Submitted</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pendingReports as $report): ?>
                                        <tr>
                                            <td><?php echo sanitize_input($report['title']); ?></td>
                                            <td><?php echo sanitize_input($report['category_name']); ?></td>
                                            <td class="text-muted small"><?php echo format_date($report['submitted_at'], 'M d, Y'); ?></td>
                                            <td class="text-end">
                                                <a href="<?php echo BASE_URL; ?>police/report_details.php?id=<?php echo (int) $report['report_id']; ?>"
                                                   class="btn btn-sm btn-outline-primary">Review</a>
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
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">My Recent Investigation Updates</div>
                <div class="card-body p-0">
                    <?php if (empty($myRecentUpdates)): ?>
                        <div class="p-4 text-center text-muted">
                            <i class="bi bi-clipboard fs-2 d-block mb-2"></i>
                            You haven't logged any investigation updates yet.
                        </div>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($myRecentUpdates as $update): ?>
                                <li class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <a href="<?php echo BASE_URL; ?>police/report_details.php?id=<?php echo (int) $update['report_id']; ?>" class="text-decoration-none">
                                            <?php echo sanitize_input($update['title']); ?>
                                        </a>
                                        <span class="badge <?php echo get_status_badge_class($update['status']); ?>">
                                            <?php echo sanitize_input($update['status']); ?>
                                        </span>
                                    </div>
                                    <div class="text-muted small"><?php echo format_date($update['updated_at'], 'M d, Y h:i A'); ?></div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>