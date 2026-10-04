<?php
/**
 * citizen/report_details.php
 *
 * Shows full details of one crime report: crime info, category,
 * evidence, verification info, and investigation updates.
 *
 * Ownership is enforced directly in the SQL WHERE clause (user_id
 * must match the logged-in citizen), so a citizen can never view --
 * or even detect the existence of -- another citizen's report.
 */

require_once __DIR__ . '/../includes/auth.php';
require_role('citizen');

$userId = $_SESSION['user_id'];
$reportId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($reportId <= 0) {
    set_flash_message('danger', 'Invalid report reference.');
    redirect('citizen/report_history.php');
}

// ---- Fetch the report, but ONLY if it belongs to this citizen ----
$stmt = $pdo->prepare(
    'SELECT cr.*, cc.category_name, v.full_name AS verifier_name
     FROM crime_reports cr
     JOIN crime_categories cc ON cr.category_id = cc.category_id
     LEFT JOIN users v ON cr.verified_by = v.user_id
     WHERE cr.report_id = :report_id AND cr.user_id = :uid'
);
$stmt->execute([':report_id' => $reportId, ':uid' => $userId]);
$report = $stmt->fetch();

if (!$report) {
    set_flash_message('danger', 'That report was not found or does not belong to your account.');
    redirect('citizen/report_history.php');
}

// ---- Evidence files for this report ----
$stmt = $pdo->prepare('SELECT * FROM evidence WHERE report_id = :report_id ORDER BY uploaded_at');
$stmt->execute([':report_id' => $reportId]);
$evidenceFiles = $stmt->fetchAll();

// ---- Investigation updates for this report (chronological) ----
$stmt = $pdo->prepare(
    'SELECT inv.*, u.full_name AS officer_name
     FROM investigations inv
     JOIN users u ON inv.officer_id = u.user_id
     WHERE inv.report_id = :report_id
     ORDER BY inv.updated_at ASC'
);
$stmt->execute([':report_id' => $reportId]);
$investigations = $stmt->fetchAll();

$pageTitle = 'Report #' . $reportId;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="d-flex flex-wrap justify-content-between align-items-start mb-4 gap-2">
        <div>
            <a href="<?php echo BASE_URL; ?>citizen/report_history.php" class="text-decoration-none small text-muted d-inline-block mb-2">
                <i class="bi bi-arrow-left"></i> Back to Report History
            </a>
            <h1 class="h3 mb-1"><?php echo sanitize_input($report['title']); ?></h1>
            <span class="text-muted">Report #<?php echo (int) $report['report_id']; ?></span>
        </div>
        <span class="badge fs-6 <?php echo get_status_badge_class($report['status']); ?>">
            <?php echo sanitize_input($report['status']); ?>
        </span>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">

            <!-- ================= CRIME INFORMATION ================= -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-semibold">Crime Information</div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Category</dt>
                        <dd class="col-sm-8"><?php echo sanitize_input($report['category_name']); ?></dd>

                        <dt class="col-sm-4">Incident Location</dt>
                        <dd class="col-sm-8"><?php echo sanitize_input($report['incident_location']); ?></dd>

                        <dt class="col-sm-4">Incident Date</dt>
                        <dd class="col-sm-8"><?php echo format_date($report['incident_date'], 'M d, Y'); ?></dd>

                        <dt class="col-sm-4">Submitted On</dt>
                        <dd class="col-sm-8"><?php echo format_date($report['submitted_at'], 'M d, Y h:i A'); ?></dd>

                        <dt class="col-sm-4">Description</dt>
                        <dd class="col-sm-8" style="white-space: pre-wrap;"><?php echo sanitize_input($report['description']); ?></dd>
                    </dl>
                </div>
            </div>

            <!-- ================= EVIDENCE ================= -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-semibold">Evidence</div>
                <div class="card-body">
                    <?php if (empty($evidenceFiles)): ?>
                        <p class="text-muted mb-0">No evidence files were uploaded with this report.</p>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($evidenceFiles as $evidence): ?>
                                <?php
                                    $isImage = in_array(strtolower($evidence['file_type']), ['jpg', 'jpeg', 'png'], true);
                                ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <span>
                                        <i class="bi <?php echo $isImage ? 'bi-file-image' : 'bi-file-earmark-pdf'; ?> me-2"></i>
                                        <?php echo sanitize_input($evidence['file_name']); ?>
                                        <span class="text-muted small ms-2"><?php echo format_date($evidence['uploaded_at'], 'M d, Y'); ?></span>
                                    </span>
                                    <a href="<?php echo BASE_URL . sanitize_input($evidence['file_path']); ?>"
                                       target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">
                                        View
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ================= INVESTIGATION UPDATES ================= -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold">Investigation Updates</div>
                <div class="card-body">
                    <?php if (empty($investigations)): ?>
                        <p class="text-muted mb-0">
                            <?php echo $report['status'] === 'Pending'
                                ? 'Your report is awaiting review. Investigation updates will appear here once it is verified.'
                                : 'No investigation updates have been recorded yet.'; ?>
                        </p>
                    <?php else: ?>
                        <ul class="list-unstyled mb-0">
                            <?php foreach ($investigations as $index => $update): ?>
                                <li class="<?php echo $index > 0 ? 'border-top pt-3 mt-3' : ''; ?>">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="badge <?php echo get_status_badge_class($update['status']); ?>">
                                            <?php echo sanitize_input($update['status']); ?>
                                        </span>
                                        <span class="text-muted small"><?php echo format_date($update['updated_at'], 'M d, Y h:i A'); ?></span>
                                    </div>
                                    <p class="mb-1"><?php echo nl2br(sanitize_input($update['remarks'] ?? '')); ?></p>
                                    <p class="text-muted small mb-0">Officer: <?php echo sanitize_input($update['officer_name']); ?></p>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <div class="col-lg-4">
            <!-- ================= VERIFICATION INFORMATION ================= -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold">Verification Information</div>
                <div class="card-body">
                    <?php if ($report['status'] === 'Pending'): ?>
                        <p class="text-muted mb-0">
                            <i class="bi bi-hourglass-split me-1"></i>
                            This report is awaiting review by a police officer.
                        </p>
                    <?php else: ?>
                        <dl class="mb-0">
                            <dt class="small text-muted">Verified By</dt>
                            <dd><?php echo sanitize_input($report['verifier_name'] ?? 'N/A'); ?></dd>

                            <dt class="small text-muted">Verified On</dt>
                            <dd><?php echo format_date($report['verified_at']); ?></dd>

                            <dt class="small text-muted">Remarks</dt>
                            <dd class="mb-0" style="white-space: pre-wrap;">
                                <?php echo $report['verification_remarks']
                                    ? nl2br(sanitize_input($report['verification_remarks']))
                                    : 'No remarks provided.'; ?>
                            </dd>
                        </dl>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>