<?php
/**
 * police/report_details.php
 *
 * Read-only detail view of a single crime report for police officers.
 * Any officer may view any report (no per-officer assignment exists
 * in the approved design). Actions (verify/reject, add investigation
 * update) live on their own dedicated pages -- this page only links
 * to them, keeping this page focused on display.
 */

require_once __DIR__ . '/../includes/auth.php';
require_role('police');

$reportId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($reportId <= 0) {
    set_flash_message('danger', 'Invalid report reference.');
    redirect('police/reports.php');
}

// ---- Fetch the report with citizen and verifier info ----
$stmt = $pdo->prepare(
    'SELECT cr.*, cc.category_name,
            citizen.full_name AS citizen_name, citizen.email AS citizen_email, citizen.phone AS citizen_phone,
            verifier.full_name AS verifier_name
     FROM crime_reports cr
     JOIN crime_categories cc ON cr.category_id = cc.category_id
     JOIN users citizen ON cr.user_id = citizen.user_id
     LEFT JOIN users verifier ON cr.verified_by = verifier.user_id
     WHERE cr.report_id = :report_id'
);
$stmt->execute([':report_id' => $reportId]);
$report = $stmt->fetch();

if (!$report) {
    set_flash_message('danger', 'That report was not found.');
    redirect('police/reports.php');
}

// ---- Evidence files ----
$stmt = $pdo->prepare('SELECT * FROM evidence WHERE report_id = :report_id ORDER BY uploaded_at');
$stmt->execute([':report_id' => $reportId]);
$evidenceFiles = $stmt->fetchAll();

// ---- Investigation updates, chronological ----
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
            <a href="<?php echo BASE_URL; ?>police/reports.php" class="text-decoration-none small text-muted d-inline-block mb-2">
                <i class="bi bi-arrow-left"></i> Back to Crime Reports
            </a>
            <h1 class="h3 mb-1"><?php echo sanitize_input($report['title']); ?></h1>
            <span class="text-muted">Report #<?php echo (int) $report['report_id']; ?></span>
        </div>

        <div class="text-end">
            <span class="badge fs-6 <?php echo get_status_badge_class($report['status']); ?> d-block mb-2">
                <?php echo sanitize_input($report['status']); ?>
            </span>

            <?php if ($report['status'] === 'Pending'): ?>
                <a href="<?php echo BASE_URL; ?>police/verify_report.php?id=<?php echo $reportId; ?>" class="btn btn-primary btn-sm">
                    <i class="bi bi-check2-square me-1"></i> Verify / Reject Report
                </a>
            <?php elseif (in_array($report['status'], ['Verified', 'Investigating'], true)): ?>
                <a href="<?php echo BASE_URL; ?>police/investigation.php?id=<?php echo $reportId; ?>" class="btn btn-primary btn-sm">
                    <i class="bi bi-clipboard-plus me-1"></i> Add Investigation Update
                </a>
            <?php else: ?>
                <span class="text-muted small d-block">This case is closed.</span>
            <?php endif; ?>
        </div>
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
                                <?php $isImage = in_array(strtolower($evidence['file_type']), ['jpg', 'jpeg', 'png'], true); ?>
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
                        <p class="text-muted mb-0">No investigation updates have been recorded yet.</p>
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

            <!-- ================= CITIZEN INFORMATION ================= -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-semibold">Reported By</div>
                <div class="card-body">
                    <dl class="mb-0">
                        <dt class="small text-muted">Name</dt>
                        <dd><?php echo sanitize_input($report['citizen_name']); ?></dd>

                        <dt class="small text-muted">Email</dt>
                        <dd><?php echo sanitize_input($report['citizen_email']); ?></dd>

                        <dt class="small text-muted mb-0">Phone</dt>
                        <dd class="mb-0"><?php echo sanitize_input($report['citizen_phone'] ?? 'Not provided'); ?></dd>
                    </dl>
                </div>
            </div>

            <!-- ================= VERIFICATION INFORMATION ================= -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold">Verification Information</div>
                <div class="card-body">
                    <?php if ($report['status'] === 'Pending'): ?>
                        <p class="text-muted mb-0">
                            <i class="bi bi-hourglass-split me-1"></i>
                            This report has not been verified yet.
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