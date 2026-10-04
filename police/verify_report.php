<?php
/**
 * police/verify_report.php
 *
 * Lets a police officer verify or reject a Pending crime report.
 * Updates crime_reports.status/verified_by/verified_at/verification_remarks
 * and creates a notification for the citizen who submitted it.
 * Only reports currently in "Pending" status can be acted on here.
 */

require_once __DIR__ . '/../includes/auth.php';
require_role('police');

$officerId = $_SESSION['user_id'];
$reportId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($reportId <= 0) {
    set_flash_message('danger', 'Invalid report reference.');
    redirect('police/reports.php');
}

// ---- Fetch the report with citizen/category info for context ----
$stmt = $pdo->prepare(
    'SELECT cr.report_id, cr.user_id, cr.title, cr.description, cr.incident_location, cr.incident_date, cr.status,
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
        // WHERE status = 'Pending' guards against a race condition where the
        // report was already verified/rejected by another action in the meantime.
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

$pageTitle = 'Verify Report #' . $reportId;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <a href="<?php echo BASE_URL; ?>police/report_details.php?id=<?php echo $reportId; ?>"
               class="text-decoration-none small text-muted d-inline-block mb-2">
                <i class="bi bi-arrow-left"></i> Back to Report Details
            </a>
            <h1 class="h3 mb-4">Verify / Reject Report</h1>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0 ps-3">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo sanitize_input($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- ================= REPORT SUMMARY ================= -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-semibold">Report Summary</div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-3">Title</dt>
                        <dd class="col-sm-9"><?php echo sanitize_input($report['title']); ?></dd>

                        <dt class="col-sm-3">Citizen</dt>
                        <dd class="col-sm-9"><?php echo sanitize_input($report['citizen_name']); ?></dd>

                        <dt class="col-sm-3">Category</dt>
                        <dd class="col-sm-9"><?php echo sanitize_input($report['category_name']); ?></dd>

                        <dt class="col-sm-3">Location</dt>
                        <dd class="col-sm-9"><?php echo sanitize_input($report['incident_location']); ?></dd>

                        <dt class="col-sm-3">Incident Date</dt>
                        <dd class="col-sm-9"><?php echo format_date($report['incident_date'], 'M d, Y'); ?></dd>

                        <dt class="col-sm-3">Description</dt>
                        <dd class="col-sm-9 mb-0" style="white-space: pre-wrap;"><?php echo sanitize_input($report['description']); ?></dd>
                    </dl>
                </div>
            </div>

            <!-- ================= DECISION FORM ================= -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold">Your Decision</div>
                <div class="card-body">
                    <form method="POST" action="<?php echo BASE_URL; ?>police/verify_report.php?id=<?php echo $reportId; ?>" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="decision" id="decisionVerify"
                                       value="Verified" <?php echo $decision === 'Verified' ? 'checked' : ''; ?> required>
                                <label class="form-check-label text-success fw-semibold" for="decisionVerify">
                                    <i class="bi bi-check-circle-fill me-1"></i> Verify Report
                                </label>
                                <div class="form-text ms-4">The report is legitimate and will proceed to investigation.</div>
                            </div>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="radio" name="decision" id="decisionReject"
                                       value="Rejected" <?php echo $decision === 'Rejected' ? 'checked' : ''; ?> required>
                                <label class="form-check-label text-danger fw-semibold" for="decisionReject">
                                    <i class="bi bi-x-circle-fill me-1"></i> Reject Report
                                </label>
                                <div class="form-text ms-4">The report does not meet the criteria for investigation.</div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="remarks" class="form-label">Verification Remarks</label>
                            <textarea class="form-control" id="remarks" name="remarks" rows="4" required
                                      placeholder="Explain your decision"><?php echo sanitize_input($remarks); ?></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check2-square me-1"></i> Submit Decision
                        </button>
                        <a href="<?php echo BASE_URL; ?>police/report_details.php?id=<?php echo $reportId; ?>" class="btn btn-outline-secondary">
                            Cancel
                        </a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>