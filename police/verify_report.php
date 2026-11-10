<?php
/**
 * police/verify_report.php
 *
 * Lets a police officer verify or reject a Pending crime report.
 * Updates crime_reports status/verified_by/verified_at/verification_remarks
 * and creates a notification for the citizen in Executive Portal design.
 */

require_once __DIR__ . '/../includes/auth.php';
require_role('police');

$officerId = (int) $_SESSION['user_id'];
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

// ---- Fetch the report with citizen/category info for context ----
$stmt = $pdo->prepare(
    'SELECT cr.report_id, cr.user_id, cr.title, cr.description, cr.incident_location, cr.incident_date, cr.status, cr.submitted_at,
            cc.category_name, citizen.full_name AS citizen_name
     FROM crime_reports cr
     JOIN crime_categories cc ON cr.category_id = cc.category_id
     JOIN users citizen ON cr.user_id = citizen.user_id
     WHERE cr.report_id = :report_id'
);
$stmt->execute([':report_id' => $reportId]);
$report = $stmt->fetch();

if (!$report) {
    set_flash_message('danger', 'That report was not found.');
    redirect('police/reports.php');
}

if ($report['status'] !== 'Pending') {
    set_flash_message('warning', 'This report has already been ' . strtolower($report['status']) . ' and cannot be verified again.');
    redirect('police/report_details.php?id=' . $reportId);
}

$errors = [];
$remarks = '';
$decision = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission. Please refresh the page and try again.';
    }

    $decision = $_POST['decision'] ?? '';
    $remarks = trim($_POST['remarks'] ?? '');

    if (!in_array($decision, ['Verified', 'Rejected'], true)) {
        $errors[] = 'Please choose whether to verify or reject this report.';
    }

    if (mb_strlen($remarks) < 5) {
        $errors[] = 'Please provide remarks explaining your decision (at least 5 characters).';
    }

    if (empty($errors)) {
        $updateStmt = $pdo->prepare(
            'UPDATE crime_reports
             SET status = :status, verified_by = :verified_by, verified_at = NOW(), verification_remarks = :remarks
             WHERE report_id = :report_id AND status = "Pending"'
        );
        $updateStmt->execute([
            ':status'      => $decision,
            ':verified_by' => $officerId,
            ':remarks'     => $remarks,
            ':report_id'   => $reportId,
        ]);

        if ($updateStmt->rowCount() === 0) {
            set_flash_message('warning', 'This report was already processed. No changes were made.');
            redirect('police/report_details.php?id=' . $reportId);
        }

        $notificationMessage = $decision === 'Verified'
            ? 'Your report "' . $report['title'] . '" has been verified and will now be investigated.'
            : 'Your report "' . $report['title'] . '" has been rejected. See the report details for the reason.';

        create_notification($pdo, $report['user_id'], $reportId, $notificationMessage);

        set_flash_message('success', 'The report has been ' . strtolower($decision) . ' successfully.');
        redirect('police/report_details.php?id=' . $reportId);
    }
}

$pageTitle = 'Triage Verification - ' . format_case_id($report['report_id'], $report['submitted_at']);
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <a href="<?php echo BASE_URL; ?>police/report_details.php?id=<?php echo $reportId; ?>"
               class="text-decoration-none small text-muted d-inline-block mb-3">
                <i class="bi bi-arrow-left me-1"></i> Back to Report Details
            </a>

            <div class="mb-4">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="badge bg-dark">
                        <?php echo sanitize_input(format_case_id($report['report_id'], $report['submitted_at'])); ?>
                    </span>
                    <span class="badge bg-warning text-dark">Pending Triage</span>
                </div>
                <h1 class="h3 mb-1">Verify or Reject Incident Report</h1>
                <p class="text-muted mb-0">Review the details below and record your official triage decision.</p>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger shadow-sm mb-4">
                    <ul class="mb-0 ps-3">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo sanitize_input($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- ================= REPORT SUMMARY ================= -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 fw-semibold">
                    <i class="bi bi-file-earmark-text text-primary me-2"></i>Incident Summary
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-3 text-muted">Title</dt>
                        <dd class="col-sm-9 fw-medium text-dark"><?php echo sanitize_input($report['title']); ?></dd>

                        <dt class="col-sm-3 text-muted">Reporter</dt>
                        <dd class="col-sm-9"><?php echo sanitize_input($report['citizen_name']); ?></dd>

                        <dt class="col-sm-3 text-muted">Category</dt>
                        <dd class="col-sm-9">
                            <span class="badge bg-light text-dark border"><?php echo sanitize_input($report['category_name']); ?></span>
                        </dd>

                        <dt class="col-sm-3 text-muted">Location</dt>
                        <dd class="col-sm-9"><?php echo sanitize_input($report['incident_location']); ?></dd>

                        <dt class="col-sm-3 text-muted">Incident Date</dt>
                        <dd class="col-sm-9"><?php echo format_date($report['incident_date'], 'M d, Y'); ?></dd>

                        <dt class="col-sm-3 text-muted">Description</dt>
                        <dd class="col-sm-9 mb-0 text-dark" style="white-space: pre-wrap;"><?php echo sanitize_input($report['description']); ?></dd>
                    </dl>
                </div>
            </div>

            <!-- ================= DECISION FORM ================= -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3 fw-semibold">
                    <i class="bi bi-shield-lock text-primary me-2"></i>Official Police Triage Decision
                </div>
                <div class="card-body">
                    <form method="POST" action="<?php echo BASE_URL; ?>police/verify_report.php?id=<?php echo $reportId; ?>" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

                        <div class="mb-4">
                            <label class="form-label fw-medium text-dark mb-3">Select Triage Action</label>
                            
                            <div class="card border p-3 mb-3 bg-light-subtle">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="decision" id="decisionVerify"
                                           value="Verified" <?php echo $decision === 'Verified' ? 'checked' : ''; ?> required>
                                    <label class="form-check-label text-success fw-semibold" for="decisionVerify">
                                        <i class="bi bi-check-circle-fill me-1"></i> Verify Report
                                    </label>
                                    <div class="form-text ms-4 text-muted">The incident report is assessed as legitimate and will proceed to active investigation.</div>
                                </div>
                            </div>

                            <div class="card border p-3 bg-light-subtle">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="decision" id="decisionReject"
                                           value="Rejected" <?php echo $decision === 'Rejected' ? 'checked' : ''; ?> required>
                                    <label class="form-check-label text-danger fw-semibold" for="decisionReject">
                                        <i class="bi bi-x-circle-fill me-1"></i> Reject Report
                                    </label>
                                    <div class="form-text ms-4 text-muted">The report lacks credibility or does not meet criminal criteria for official investigation.</div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="remarks" class="form-label fw-medium text-dark">Verification / Rejection Remarks</label>
                            <textarea class="form-control" id="remarks" name="remarks" rows="4" required
                                      placeholder="Provide formal explanation or justification for this decision (minimum 5 characters)"><?php echo sanitize_input($remarks); ?></textarea>
                            <div class="form-text">These remarks will be securely recorded and visible to the reporting citizen.</div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="bi bi-check2-square me-1"></i> Submit Triage Decision
                            </button>
                            <a href="<?php echo BASE_URL; ?>police/report_details.php?id=<?php echo $reportId; ?>" class="btn btn-outline-secondary">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>