<?php
/**
 * police/report_details.php
 *
 * Read-only detail view of a single crime report for police officers.
 * Supports Print / Export PDF with professional layout spacing.
 */

require_once __DIR__ . '/../includes/auth.php';
require_role('police');

$reportId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($reportId <= 0) {
    set_flash_message('danger', 'Invalid report reference.');
    redirect('police/reports.php');
}

/**
 * Professional case reference, e.g. CR-2026-00014
 */
if (!function_exists('format_case_id')) {
    function format_case_id($reportId, $submittedAt) {
        return sprintf('CR-%s-%05d', date('Y', strtotime($submittedAt)), (int) $reportId);
    }
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

$pageTitle = 'Case ' . format_case_id($report['report_id'], $report['submitted_at']);
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Print Specific Styling & Professional Text Alignment -->
<style>
@media print {
    body { background-color: #ffffff !important; color: #000000 !important; }
    .navbar, footer, .btn, a[href], .no-print { display: none !important; }
    .card { border: 1px solid #dee2e6 !important; box-shadow: none !important; margin-bottom: 1rem !important; }
    .container { max-width: 100% !important; width: 100% !important; padding: 0 !important; margin: 0 !important; }
}
.remarks-box {
    word-break: break-word;
    overflow-wrap: break-word;
    white-space: normal !important;
    text-align: left !important;
}
</style>

<div class="container py-4">
    <!-- Top Header: Title & Status / Action Zone -->
    <div class="d-flex flex-wrap justify-content-between align-items-start mb-4 gap-2">
        <div>
            <a href="<?php echo BASE_URL; ?>police/reports.php" class="text-decoration-none small text-muted d-inline-block mb-2 no-print">
                <i class="bi bi-arrow-left me-1"></i> Back to Crime Reports
            </a>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-dark">
                    <?php echo sanitize_input(format_case_id($report['report_id'], $report['submitted_at'])); ?>
                </span>
                <span class="text-muted small">System ID #<?php echo (int) $report['report_id']; ?></span>
            </div>
            <h1 class="h3 mb-0 fw-bold text-dark"><?php echo sanitize_input($report['title']); ?></h1>
        </div>

        <div class="text-end">
            <span class="badge fs-6 <?php echo get_status_badge_class($report['status']); ?> d-block mb-2 px-3 py-2 shadow-sm">
                <?php echo sanitize_input($report['status']); ?>
            </span>

            <?php if ($report['status'] === 'Pending'): ?>
                <a href="<?php echo BASE_URL; ?>police/verify_report.php?id=<?php echo $reportId; ?>" class="btn btn-primary btn-sm px-3 no-print">
                    <i class="bi bi-check2-square me-1"></i> Verify / Reject Report
                </a>
            <?php elseif (in_array($report['status'], ['Verified', 'Investigating', 'Resolved'], true)): ?>
                <a href="<?php echo BASE_URL; ?>police/investigation.php?id=<?php echo $reportId; ?>" class="btn btn-primary btn-sm px-3 no-print">
                    <i class="bi bi-clipboard-plus me-1"></i> Update Status / Close Case
                </a>
            <?php else: ?>
                <span class="text-muted small d-block no-print"><i class="bi bi-lock me-1"></i>This case is concluded &amp; closed.</span>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">

            <!-- ================= CRIME INFORMATION ================= -->
            <div class="card border-0 shadow-sm mb-4 rounded-4">
                <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center">
                    <span class="fw-semibold text-dark"><i class="bi bi-info-circle text-primary me-2"></i>Crime Information Details</span>
                    <!-- Professional Placement of Print / PDF Download Button -->
                    <button onclick="window.print()" class="btn btn-outline-dark btn-sm px-3 rounded-3 shadow-sm no-print">
                        <i class="bi bi-printer me-1"></i> Print / Export PDF
                    </button>
                </div>
                <div class="card-body p-4">
                    <dl class="row mb-0 g-3">
                        <dt class="col-sm-4 text-muted">Category</dt>
                        <dd class="col-sm-8 fw-medium text-dark"><?php echo sanitize_input($report['category_name']); ?></dd>

                        <dt class="col-sm-4 text-muted">Incident Location</dt>
                        <dd class="col-sm-8 text-dark"><?php echo sanitize_input($report['incident_location']); ?></dd>

                        <dt class="col-sm-4 text-muted">Incident Date</dt>
                        <dd class="col-sm-8 text-dark"><?php echo format_date($report['incident_date'], 'M d, Y'); ?></dd>

                        <dt class="col-sm-4 text-muted">Submitted On</dt>
                        <dd class="col-sm-8 text-dark"><?php echo format_date($report['submitted_at'], 'M d, Y h:i A'); ?></dd>

                        <dt class="col-sm-4 text-muted">Description</dt>
                        <dd class="col-sm-8 mb-0 text-dark remarks-box"><?php echo sanitize_input($report['description']); ?></dd>
                    </dl>
                </div>
            </div>

            <!-- ================= EVIDENCE ================= -->
            <div class="card border-0 shadow-sm mb-4 rounded-4">
                <div class="card-header bg-white py-3 px-4 fw-semibold">
                    <i class="bi bi-folder2-open text-primary me-2"></i>Attached Evidence Files
                </div>
                <div class="card-body p-4">
                    <?php if (empty($evidenceFiles)): ?>
                        <p class="text-muted mb-0">No evidence files were uploaded with this incident report.</p>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($evidenceFiles as $evidence): ?>
                                <?php $isImage = in_array(strtolower($evidence['file_type']), ['jpg', 'jpeg', 'png'], true); ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-3">
                                    <span>
                                        <i class="bi <?php echo $isImage ? 'bi-file-image text-success' : 'bi-file-earmark-pdf text-danger'; ?> me-2"></i>
                                        <?php echo sanitize_input($evidence['file_name']); ?>
                                        <span class="text-muted small ms-2">(Uploaded: <?php echo format_date($evidence['uploaded_at'], 'M d, Y'); ?>)</span>
                                    </span>
                                    <a href="<?php echo BASE_URL . sanitize_input($evidence['file_path']); ?>"
                                       target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary no-print">
                                        <i class="bi bi-eye me-1"></i>View File
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ================= INVESTIGATION UPDATES ================= -->
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3 px-4 fw-semibold">
                    <i class="bi bi-clock-history text-primary me-2"></i>Investigation History &amp; Logs
                </div>
                <div class="card-body p-4">
                    <?php if (empty($investigations)): ?>
                        <p class="text-muted mb-0">No investigation progress updates have been recorded yet.</p>
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
                                    <p class="mb-1 text-dark remarks-box"><?php echo sanitize_input($update['remarks'] ?? ''); ?></p>
                                    <p class="text-muted small mb-0"><i class="bi bi-person-badge me-1"></i>Officer: <?php echo sanitize_input($update['officer_name']); ?></p>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <div class="col-lg-4">

            <!-- ================= CITIZEN INFORMATION ================= -->
            <div class="card border-0 shadow-sm mb-4 rounded-4">
                <div class="card-header bg-white py-3 px-4 fw-semibold">
                    <i class="bi bi-person text-primary me-2"></i>Reported By (Citizen)
                </div>
                <div class="card-body p-4">
                    <dl class="mb-0 g-3">
                        <dt class="small text-muted mb-1">Full Name</dt>
                        <dd class="fw-medium text-dark mb-3"><?php echo sanitize_input($report['citizen_name']); ?></dd>

                        <dt class="small text-muted mb-1">Email Address</dt>
                        <dd class="text-dark mb-3"><?php echo sanitize_input($report['citizen_email']); ?></dd>

                        <dt class="small text-muted mb-1">Phone Number</dt>
                        <dd class="mb-0 text-dark"><?php echo sanitize_input($report['citizen_phone'] ?? 'Not provided'); ?></dd>
                    </dl>
                </div>
            </div>

            <!-- ================= VERIFICATION INFORMATION ================= -->
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3 px-4 fw-semibold">
                    <i class="bi bi-shield-check text-primary me-2"></i>Verification Status
                </div>
                <div class="card-body p-4">
                    <?php if ($report['status'] === 'Pending'): ?>
                        <div class="text-muted">
                            <i class="bi bi-hourglass-split text-warning me-1"></i>
                            This report is awaiting police verification and triage.
                        </div>
                    <?php else: ?>
                        <dl class="mb-0">
                            <dt class="small text-muted mb-1">Verified By Officer</dt>
                            <dd class="fw-medium text-dark mb-3"><?php echo sanitize_input($report['verifier_name'] ?? 'N/A'); ?></dd>

                            <dt class="small text-muted mb-1">Verified On</dt>
                            <dd class="mb-3 text-dark"><?php echo !empty($report['verified_at']) ? format_date($report['verified_at'], 'M d, Y h:i A') : 'N/A'; ?></dd>

                            <dt class="small text-muted mb-1">Verification Remarks</dt>
                            <dd class="mb-0 text-dark remarks-box p-3 bg-light rounded-3 border">
                                <?php echo $report['verification_remarks']
                                    ? sanitize_input($report['verification_remarks'])
                                    : 'No specific remarks provided.'; ?>
                            </dd>
                        </dl>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>