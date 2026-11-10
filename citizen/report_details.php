<?php
/**
 * citizen/report_details.php
 *
 * Shows full details of one crime report: crime info, category,
 * evidence, verification info, investigation updates, and the 
 * Executive Visual Case Tracking Stepper with Print/PDF Export.
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

<!-- Print Styling & Clean Professional Text Alignment Fix -->
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
    <!-- Top Header Zone -->
    <div class="d-flex flex-wrap justify-content-between align-items-start mb-4 gap-2">
        <div>
            <a href="<?php echo BASE_URL; ?>citizen/report_history.php" class="text-decoration-none small text-muted d-inline-block mb-2 no-print">
                <i class="bi bi-arrow-left"></i> Back to Report History
            </a>
            <h1 class="h3 mb-1 fw-bold text-dark"><?php echo sanitize_input($report['title']); ?></h1>
            <span class="text-muted">Report #<?php echo (int) $report['report_id']; ?> (CR-<?php echo date('Y', strtotime($report['submitted_at'])); ?>-<?php echo str_pad($report['report_id'], 5, '0', STR_PAD_LEFT); ?>)</span>
        </div>
        <div>
            <span class="badge fs-6 px-3 py-2 shadow-sm <?php echo get_status_badge_class($report['status']); ?>">
                <?php echo sanitize_input($report['status']); ?>
            </span>
        </div>
    </div>

    <!-- ================= VISUAL CASE TRACKING STEPPER ================= -->
    <?php
    $currentStatus = $report['status'];
    
    $steps = [
        ['label' => 'Submitted', 'icon' => 'bi-file-earmark-text', 'state' => 'completed'],
        ['label' => 'Verified', 'icon' => 'bi-shield-check', 'state' => 'pending'],
        ['label' => 'Under Investigation', 'icon' => 'bi-search', 'state' => 'pending'],
        ['label' => 'Resolved', 'icon' => 'bi-check2-circle', 'state' => 'pending'],
        ['label' => 'Closed', 'icon' => 'bi-archive', 'state' => 'pending']
    ];

    if ($currentStatus === 'Rejected') {
        $steps = [
            ['label' => 'Submitted', 'icon' => 'bi-file-earmark-text', 'state' => 'completed'],
            ['label' => 'Rejected', 'icon' => 'bi-x-circle', 'state' => 'rejected']
        ];
    } else {
        foreach ($steps as $index => $step) {
            $label = $step['label'];
            if ($label === 'Submitted') {
                $steps[$index]['state'] = 'completed';
                $steps[$index]['meta'] = format_date($report['submitted_at'], 'M d, Y');
            } elseif ($label === 'Verified') {
                if (in_array($currentStatus, ['Verified', 'Investigating', 'Resolved', 'Closed'])) {
                    $steps[$index]['state'] = 'completed';
                    if (!empty($report['verified_at'])) {
                        $steps[$index]['meta'] = format_date($report['verified_at'], 'M d, Y');
                    }
                } elseif ($currentStatus === 'Pending') {
                    $steps[$index]['state'] = 'active';
                }
            } elseif ($label === 'Under Investigation') {
                if (in_array($currentStatus, ['Investigating', 'Resolved', 'Closed'])) {
                    $steps[$index]['state'] = 'completed';
                } elseif ($currentStatus === 'Verified') {
                    $steps[$index]['state'] = 'active';
                }
            } elseif ($label === 'Resolved') {
                if (in_array($currentStatus, ['Resolved', 'Closed'])) {
                    $steps[$index]['state'] = 'completed';
                } elseif ($currentStatus === 'Investigating') {
                    $steps[$index]['state'] = 'active';
                }
            } elseif ($label === 'Closed') {
                if ($currentStatus === 'Closed') {
                    $steps[$index]['state'] = 'completed';
                } elseif ($currentStatus === 'Resolved') {
                    $steps[$index]['state'] = 'active';
                }
            }
        }
    }
    ?>

    <div class="tracking-container mb-4">
        <ul class="tracking-steps">
            <?php foreach ($steps as $s): ?>
                <li class="tracking-step <?php echo $s['state']; ?>">
                    <span class="step-circle">
                        <i class="bi <?php echo $s['icon']; ?>"></i>
                    </span>
                    <span class="step-label"><?php echo $s['label']; ?></span>
                    <?php if (!empty($s['meta'])): ?>
                        <span class="step-meta"><?php echo $s['meta']; ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <!-- ================= END TRACKING STEPPER ================= -->

    <div class="row g-4">
        <div class="col-lg-8">

            <!-- ================= CRIME INFORMATION ================= -->
            <div class="card border-0 shadow-sm mb-4 rounded-4">
                <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center">
                    <span class="fw-semibold text-dark"><i class="bi bi-info-circle text-primary me-2"></i>Crime Information Summary</span>
                    <!-- Print / PDF Download Button placed professionally inside card header -->
                    <button onclick="window.print()" class="btn btn-outline-dark btn-sm px-3 rounded-3 shadow-sm no-print">
                        <i class="bi bi-printer me-1"></i> Print / Export PDF
                    </button>
                </div>
                <div class="card-body p-4">
                    <dl class="row mb-0 g-3">
                        <dt class="col-sm-4 text-muted">Category</dt>
                        <dd class="col-sm-8 fw-semibold text-dark"><?php echo sanitize_input($report['category_name']); ?></dd>

                        <dt class="col-sm-4 text-muted">Incident Location</dt>
                        <dd class="col-sm-8 text-dark"><?php echo sanitize_input($report['incident_location']); ?></dd>

                        <dt class="col-sm-4 text-muted">Incident Date</dt>
                        <dd class="col-sm-8 text-dark"><?php echo format_date($report['incident_date'], 'M d, Y'); ?></dd>

                        <dt class="col-sm-4 text-muted">Submitted On</dt>
                        <dd class="col-sm-8 text-dark"><?php echo format_date($report['submitted_at'], 'M d, Y h:i A'); ?></dd>

                        <dt class="col-sm-4 text-muted">Description</dt>
                        <dd class="col-sm-8 text-dark remarks-box"><?php echo sanitize_input($report['description']); ?></dd>
                    </dl>
                </div>
            </div>

            <!-- ================= EVIDENCE ================= -->
            <div class="card border-0 shadow-sm mb-4 rounded-4">
                <div class="card-header bg-white py-3 px-4 fw-semibold">Attached Evidence Files</div>
                <div class="card-body p-4">
                    <?php if (empty($evidenceFiles)): ?>
                        <p class="text-muted mb-0">No evidence files were uploaded with this report.</p>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($evidenceFiles as $evidence): ?>
                                <?php
                                    $isImage = in_array(strtolower($evidence['file_type']), ['jpg', 'jpeg', 'png'], true);
                                ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-3">
                                    <span>
                                        <i class="bi <?php echo $isImage ? 'bi-file-image' : 'bi-file-earmark-pdf'; ?> me-2"></i>
                                        <?php echo sanitize_input($evidence['file_name']); ?>
                                        <span class="text-muted small ms-2"><?php echo format_date($evidence['uploaded_at'], 'M d, Y'); ?></span>
                                    </span>
                                    <a href="<?php echo BASE_URL . sanitize_input($evidence['file_path']); ?>"
                                       target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary no-print">
                                        View
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ================= INVESTIGATION UPDATES ================= -->
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3 px-4 fw-semibold">Investigation Progress Logs</div>
                <div class="card-body p-4">
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
                                    <p class="mb-1 remarks-box"><?php echo sanitize_input($update['remarks'] ?? ''); ?></p>
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
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3 px-4 fw-semibold">Verification Details</div>
                <div class="card-body p-4">
                    <?php if ($report['status'] === 'Pending'): ?>
                        <p class="text-muted mb-0">
                            <i class="bi bi-hourglass-split me-1"></i>
                            This report is currently awaiting triage and review by a police officer.
                        </p>
                    <?php else: ?>
                        <dl class="mb-0">
                            <dt class="small text-muted mb-1">Verified By Officer</dt>
                            <dd class="mb-3 fw-semibold text-dark"><?php echo sanitize_input($report['verifier_name'] ?? 'N/A'); ?></dd>

                            <dt class="small text-muted mb-1">Verification Date</dt>
                            <dd class="mb-3 text-dark"><?php echo !empty($report['verified_at']) ? format_date($report['verified_at'], 'M d, Y h:i A') : 'N/A'; ?></dd>

                            <dt class="small text-muted mb-1">Official Verification Remarks</dt>
                            <dd class="mb-0 remarks-box p-3 bg-light rounded-3 border text-dark">
                                <?php echo $report['verification_remarks']
                                    ? sanitize_input($report['verification_remarks'])
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