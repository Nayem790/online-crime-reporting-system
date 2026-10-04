<?php
/**
 * police/investigation.php
 *
 * Lets a police officer add a new investigation update for a report
 * that has already been Verified. Each submission inserts a new row
 * into investigations (the report can have many update records), and
 * keeps crime_reports.status in sync with the latest investigation
 * status so citizens see a consistent, current status.
 */

require_once __DIR__ . '/../includes/auth.php';
require_role('police');

$officerId = $_SESSION['user_id'];
$reportId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($reportId <= 0) {
    set_flash_message('danger', 'Invalid report reference.');
    redirect('police/reports.php');
}

// ---- Fetch the report for context ----
$stmt = $pdo->prepare(
    'SELECT cr.report_id, cr.user_id, cr.title, cr.status, cc.category_name, citizen.full_name AS citizen_name
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

$actionableStatuses = ['Verified', 'Investigating'];

if (!in_array($report['status'], $actionableStatuses, true)) {
    $message = $report['status'] === 'Resolved'
        ? 'This case is already resolved and cannot be updated further.'
        : 'This report must be verified before investigation updates can be added.';
    set_flash_message('warning', $message);
    redirect('police/report_details.php?id=' . $reportId);
}

// ---- Existing investigation history, for context ----
$stmt = $pdo->prepare(
    'SELECT inv.*, u.full_name AS officer_name
     FROM investigations inv
     JOIN users u ON inv.officer_id = u.user_id
     WHERE inv.report_id = :report_id
     ORDER BY inv.updated_at ASC'
);
$stmt->execute([':report_id' => $reportId]);
$investigations = $stmt->fetchAll();

$errors = [];
$status = '';
$remarks = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission. Please refresh the page and try again.';
    }

    $status = $_POST['status'] ?? '';
    $remarks = trim($_POST['remarks'] ?? '');

    if (!in_array($status, $actionableStatuses, true)) {
        $errors[] = 'Please select a valid investigation status.';
    }

    if (mb_strlen($remarks) < 5) {
        $errors[] = 'Please provide investigation remarks (at least 5 characters).';
    }

    if (empty($errors)) {
        // Guard against the report having been resolved/changed by another
        // action between page load and submission.
        $syncStmt = $pdo->prepare(
            'UPDATE crime_reports SET status = :status
             WHERE report_id = :report_id AND status IN ("Verified", "Investigating")'
        );
        $syncStmt->execute([
            ':status'    => $status,
            ':report_id' => $reportId,
        ]);

        if ($syncStmt->rowCount() === 0) {
            set_flash_message('warning', 'This report is no longer available for investigation updates.');
            redirect('police/report_details.php?id=' . $reportId);
        }

        $insertStmt = $pdo->prepare(
            'INSERT INTO investigations (report_id, officer_id, status, remarks)
             VALUES (:report_id, :officer_id, :status, :remarks)'
        );
        $insertStmt->execute([
            ':report_id'  => $reportId,
            ':officer_id' => $officerId,
            ':status'     => $status,
            ':remarks'    => $remarks,
        ]);

        create_notification(
            $pdo,
            $report['user_id'],
            $reportId,
            'The investigation status of your report "' . $report['title'] . '" has been updated to "' . $status . '".'
        );

        set_flash_message('success', 'Investigation update saved successfully.');
        redirect('police/report_details.php?id=' . $reportId);
    }
}

$pageTitle = 'Investigation - Report #' . $reportId;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <a href="<?php echo BASE_URL; ?>police/report_details.php?id=<?php echo $reportId; ?>"
               class="text-decoration-none small text-muted d-inline-block mb-2">
                <i class="bi bi-arrow-left"></i> Back to Report Details
            </a>
            <h1 class="h3 mb-1">Investigation Update</h1>
            <p class="text-muted mb-4">
                <?php echo sanitize_input($report['title']); ?>
                &middot; Reported by <?php echo sanitize_input($report['citizen_name']); ?>
                &middot; <?php echo sanitize_input($report['category_name']); ?>
            </p>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0 ps-3">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo sanitize_input($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- ================= EXISTING INVESTIGATION HISTORY ================= -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-semibold">Investigation History</div>
                <div class="card-body">
                    <?php if (empty($investigations)): ?>
                        <p class="text-muted mb-0">No investigation updates have been recorded yet. This will be the first.</p>
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

            <!-- ================= NEW UPDATE FORM ================= -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold">Add New Update</div>
                <div class="card-body">
                    <form method="POST" action="<?php echo BASE_URL; ?>police/investigation.php?id=<?php echo $reportId; ?>" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

                        <div class="mb-3">
                            <label for="status" class="form-label">Investigation Status</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="">-- Select status --</option>
                                <option value="Investigating" <?php echo $status === 'Investigating' ? 'selected' : ''; ?>>
                                    Investigating (case still in progress)
                                </option>
                                <option value="Resolved" <?php echo $status === 'Resolved' ? 'selected' : ''; ?>>
                                    Resolved (case closed)
                                </option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="remarks" class="form-label">Remarks</label>
                            <textarea class="form-control" id="remarks" name="remarks" rows="4" required
                                      placeholder="Describe the current findings or progress"><?php echo sanitize_input($remarks); ?></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-clipboard-plus me-1"></i> Save Update
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