<?php
/**
 * admin/reports.php
 *
 * Administrator, read-only, system-wide view of all crime reports
 * (FR-19), with advanced search, status, category, and exact date / timeframe filters.
 */

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

/**
 * Professional case reference format, e.g. CR-2026-00014
 */
if (!function_exists('format_case_id')) {
    function format_case_id($reportId, $submittedAt) {
        return sprintf('CR-%s-%05d', date('Y', strtotime($submittedAt)), (int) $reportId);
    }
}

$validStatuses = ['Pending', 'Verified', 'Rejected', 'Investigating', 'Resolved', 'Closed'];

// Retrieve filter parameters safely
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$categoryFilter = trim($_GET['category_id'] ?? '');
$dateRange = trim($_GET['date_range'] ?? '');
$exactDate = trim($_GET['exact_date'] ?? '');

$categories = $pdo->query('SELECT category_id, category_name FROM crime_categories ORDER BY category_name')->fetchAll();

// ---- Build the filtered query safely ----
$conditions = [];
$params = [];

if ($search !== '') {
    $conditions[] = '(cr.title LIKE :search OR cr.description LIKE :search OR cr.incident_location LIKE :search
                        OR citizen.full_name LIKE :search OR citizen.email LIKE :search)';
    $params[':search'] = '%' . $search . '%';
}

if ($statusFilter !== '' && in_array($statusFilter, $validStatuses, true)) {
    $conditions[] = 'cr.status = :status';
    $params[':status'] = $statusFilter;
}

if ($categoryFilter !== '' && ctype_digit($categoryFilter)) {
    $conditions[] = 'cr.category_id = :category_id';
    $params[':category_id'] = (int) $categoryFilter;
}

// ---- Date Filtering Logic ----
if ($dateRange === 'last_7_days') {
    $conditions[] = 'cr.submitted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
} elseif ($dateRange === 'last_30_days') {
    $conditions[] = 'cr.submitted_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
} elseif ($dateRange === 'this_month') {
    $conditions[] = 'MONTH(cr.submitted_at) = MONTH(CURRENT_DATE()) AND YEAR(cr.submitted_at) = YEAR(CURRENT_DATE())';
} elseif ($dateRange === 'exact' && $exactDate !== '') {
    $conditions[] = 'DATE(cr.submitted_at) = :exact_date';
    $params[':exact_date'] = $exactDate;
}

$sql = 'SELECT cr.report_id, cr.title, cr.description, cr.status, cr.incident_date, cr.incident_location,
               cr.submitted_at, cr.verified_at, cr.verification_remarks,
               cc.category_name,
               citizen.full_name AS citizen_name, citizen.email AS citizen_email,
               verifier.full_name AS verifier_name,
               (SELECT COUNT(*) FROM evidence e WHERE e.report_id = cr.report_id) AS evidence_count,
               (SELECT COUNT(*) FROM investigations i WHERE i.report_id = cr.report_id) AS investigation_count
        FROM crime_reports cr
        JOIN crime_categories cc ON cr.category_id = cc.category_id
        JOIN users citizen ON cr.user_id = citizen.user_id
        LEFT JOIN users verifier ON cr.verified_by = verifier.user_id';

if (!empty($conditions)) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
}

$sql .= ' ORDER BY cr.submitted_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reports = $stmt->fetchAll();

$hasActiveFilters = ($search !== '' || $statusFilter !== '' || $categoryFilter !== '' || $dateRange !== '');

// ---- Prepared statements reused inside the loop for expandable details ----
$evidenceStmt = $pdo->prepare('SELECT * FROM evidence WHERE report_id = :report_id ORDER BY uploaded_at');
$investigationStmt = $pdo->prepare(
    'SELECT inv.*, u.full_name AS officer_name
     FROM investigations inv
     JOIN users u ON inv.officer_id = u.user_id
     WHERE inv.report_id = :report_id
     ORDER BY inv.updated_at ASC'
);

$pageTitle = 'System-Wide Crime Reports';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-3">

    <!-- ================= PAGE HEADER ================= -->
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-1 fw-bold text-dark">System-Wide Crime Reports</h1>
            <p class="text-muted mb-0">Advanced filtering, exact date lookup, and comprehensive case inspection.</p>
        </div>
        <div>
            <span class="badge bg-primary px-3 py-2 fs-6">
                <i class="bi bi-file-earmark-text me-1"></i> Total Matches: <?php echo count($reports); ?>
            </span>
        </div>
    </div>

    <!-- ================= ADVANCED SEARCH & DATE FILTER CARD ================= -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-white py-3 fw-semibold">
            <i class="bi bi-funnel me-2 text-primary"></i> Search &amp; Exact Date Filtering
        </div>
        <div class="card-body">
            <form method="GET" action="<?php echo BASE_URL; ?>admin/reports.php" class="row g-3 align-items-end">
                <!-- Search Keyword -->
                <div class="col-md-4">
                    <label for="search" class="form-label small fw-semibold">Search Keyword</label>
                    <input type="text" class="form-control" id="search" name="search"
                           placeholder="Title, location, or citizen name/email"
                           value="<?php echo sanitize_input($search); ?>">
                </div>

                <!-- Status Filter -->
                <div class="col-md-2">
                    <label for="status" class="form-label small fw-semibold">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All Statuses</option>
                        <?php foreach ($validStatuses as $status): ?>
                            <option value="<?php echo $status; ?>" <?php echo $statusFilter === $status ? 'selected' : ''; ?>>
                                <?php echo $status; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Category Filter -->
                <div class="col-md-3">
                    <label for="category_id" class="form-label small fw-semibold">Category</label>
                    <select class="form-select" id="category_id" name="category_id">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo (int) $category['category_id']; ?>"
                                <?php echo ((string) $categoryFilter === (string) $category['category_id']) ? 'selected' : ''; ?>>
                                <?php echo sanitize_input($category['category_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Date Filter Options -->
                <div class="col-md-3">
                    <label for="date_range" class="form-label small fw-semibold">Timeframe / Date</label>
                    <select class="form-select" id="date_range" name="date_range" onchange="toggleExactDate(this.value)">
                        <option value="">All Time</option>
                        <option value="last_7_days" <?php echo $dateRange === 'last_7_days' ? 'selected' : ''; ?>>Last 7 Days (Last Week)</option>
                        <option value="last_30_days" <?php echo $dateRange === 'last_30_days' ? 'selected' : ''; ?>>Last 30 Days (Last Month)</option>
                        <option value="this_month" <?php echo $dateRange === 'this_month' ? 'selected' : ''; ?>>This Month</option>
                        <option value="exact" <?php echo $dateRange === 'exact' ? 'selected' : ''; ?>>Specific Exact Date</option>
                    </select>
                </div>

                <!-- Exact Date Input (Toggled via JS) -->
                <div class="col-12 exact-date-container <?php echo $dateRange === 'exact' ? '' : 'd-none'; ?>">
                    <div class="row g-3 p-3 bg-light rounded-3 border">
                        <div class="col-md-4">
                            <label for="exact_date" class="form-label small fw-semibold">Select Specific Date</label>
                            <input type="date" class="form-control" id="exact_date" name="exact_date" value="<?php echo sanitize_input($exactDate); ?>">
                        </div>
                        <div class="col-md-8 d-flex align-items-center">
                            <span class="text-muted small">Displays all crime reports filed on this exact calendar date.</span>
                        </div>
                    </div>
                </div>

                <!-- Submit & Clear Buttons -->
                <div class="col-12 d-flex justify-content-end gap-2 mt-2">
                    <?php if ($hasActiveFilters): ?>
                        <a href="<?php echo BASE_URL; ?>admin/reports.php" class="btn btn-outline-secondary px-3">
                            <i class="bi bi-x-circle me-1"></i> Reset Filters
                        </a>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-search me-1"></i> Apply Filters
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ================= RESULTS TABLE ================= -->
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
            <span class="fw-semibold"><i class="bi bi-folder-check me-2 text-primary"></i> Filtered Case Reports Directory</span>
            <span class="text-muted small"><?php echo count($reports); ?> record(s) displayed</span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($reports)): ?>
                <div class="p-5 text-center text-muted">
                    <i class="bi bi-search fs-1 d-block mb-2 text-secondary"></i>
                    No crime reports match your selected criteria.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Case ID</th>
                                <th>Report Title</th>
                                <th>Citizen Name</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th>Submitted Date</th>
                                <th class="text-end">Actions</th>
                          </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reports as $report): ?>
                                <?php 
                                    $detailId = 'detail' . (int) $report['report_id']; 
                                    $caseIdFormatted = format_case_id($report['report_id'], $report['submitted_at']);
                                ?>
                                <tr>
                                    <td>
                                        <span class="fw-bold text-primary"><?php echo sanitize_input($caseIdFormatted); ?></span>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?php echo sanitize_input($report['title']); ?></div>
                                    </td>
                                    <td><?php echo sanitize_input($report['citizen_name']); ?></td>
                                    <td>
                                        <span class="badge bg-light text-dark border"><?php echo sanitize_input($report['category_name']); ?></span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo get_status_badge_class($report['status']); ?>">
                                            <?php echo sanitize_input($report['status']); ?>
                                        </span>
                                    </td>
                                    <td class="text-muted small"><?php echo format_date($report['submitted_at'], 'M d, Y h:i A'); ?></td>
                                    <td class="text-muted small"><?php echo format_date($report['submitted_at'], 'M d, Y h:i A'); ?></td>
                                    <td class="text-end">
                                        <a href="<?php echo BASE_URL; ?>admin/report_details.php?id=<?php echo (int) $report['report_id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye me-1"></i> View Details
                                        </a>
                                    </td>
                                </tr>
                                </tr>
                                <tr class="collapse" id="<?php echo $detailId; ?>">
                                    <td colspan="7" class="bg-light p-4">
                                        <div class="row g-4">
                                            <!-- Crime info & Verification -->
                                            <div class="col-md-6">
                                                <div class="card border-0 shadow-sm p-3 h-100">
                                                    <h5 class="h6 text-primary fw-bold mb-3"><i class="bi bi-info-circle me-1"></i> Crime &amp; Verification Details</h5>
                                                    <dl class="row small mb-3">
                                                        <dt class="col-sm-4 text-muted">Location:</dt>
                                                        <dd class="col-sm-8 fw-medium"><?php echo sanitize_input($report['incident_location']); ?></dd>

                                                        <dt class="col-sm-4 text-muted">Incident Date:</dt>
                                                        <dd class="col-sm-8"><?php echo format_date($report['incident_date'], 'M d, Y'); ?></dd>

                                                        <dt class="col-sm-4 text-muted">Citizen Email:</dt>
                                                        <dd class="col-sm-8"><?php echo sanitize_input($report['citizen_email']); ?></dd>

                                                        <dt class="col-sm-4 text-muted">Description:</dt>
                                                        <dd class="col-sm-8 mb-0 text-secondary" style="white-space: pre-wrap;"><?php echo sanitize_input($report['description']); ?></dd>
                                                    </dl>

                                                    <hr class="text-muted opacity-25">

                                                    <h6 class="text-dark fw-bold small text-uppercase mb-2">Triage Verification</h6>
                                                    <?php if ($report['status'] === 'Pending'): ?>
                                                        <p class="small text-warning fw-medium mb-0"><i class="bi bi-clock-history me-1"></i> Awaiting verification from police authority.</p>
                                                    <?php else: ?>
                                                        <dl class="row small mb-0">
                                                            <dt class="col-sm-4 text-muted">Verified By:</dt>
                                                            <dd class="col-sm-8"><?php echo sanitize_input($report['verifier_name'] ?? 'N/A'); ?></dd>

                                                            <dt class="col-sm-4 text-muted">Verified On:</dt>
                                                            <dd class="col-sm-8"><?php echo format_date($report['verified_at'], 'M d, Y h:i A'); ?></dd>

                                                            <dt class="col-sm-4 text-muted">Remarks:</dt>
                                                            <dd class="col-sm-8 mb-0 text-secondary">
                                                                <?php echo $report['verification_remarks']
                                                                    ? nl2br(sanitize_input($report['verification_remarks']))
                                                                    : 'No remarks provided.'; ?>
                                                            </dd>
                                                        </dl>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <!-- Evidence + investigation history -->
                                            <div class="col-md-6">
                                                <div class="card border-0 shadow-sm p-3 h-100">
                                                    <h5 class="h6 text-primary fw-bold mb-3"><i class="bi bi-folder2-open me-1"></i> Evidence &amp; Investigations</h5>
                                                    
                                                    <!-- Evidence Section -->
                                                    <div class="mb-3">
                                                        <h6 class="text-dark fw-bold small text-uppercase mb-2">Attached Evidence (<?php echo (int) $report['evidence_count']; ?>)</h6>
                                                        <?php if ((int) $report['evidence_count'] === 0): ?>
                                                            <p class="small text-muted mb-0">No evidence files uploaded for this case.</p>
                                                        <?php else: ?>
                                                            <?php
                                                                $evidenceStmt->execute([':report_id' => $report['report_id']]);
                                                                $evidenceFiles = $evidenceStmt->fetchAll();
                                                            ?>
                                                            <ul class="list-unstyled small mb-0">
                                                                <?php foreach ($evidenceFiles as $evidence): ?>
                                                                    <li class="mb-1">
                                                                        <a href="<?php echo BASE_URL . sanitize_input($evidence['file_path']); ?>" target="_blank" rel="noopener" class="text-decoration-none">
                                                                            <i class="bi bi-paperclip text-primary me-1"></i> <?php echo sanitize_input($evidence['file_name']); ?>
                                                                        </a>
                                                                    </li>
                                                                <?php endforeach; ?>
                                                            </ul>
                                                        <?php endif; ?>
                                                    </div>

                                                    <hr class="text-muted opacity-25">

                                                    <!-- Investigation Updates Section -->
                                                    <div>
                                                        <h6 class="text-dark fw-bold small text-uppercase mb-2">Investigation History (<?php echo (int) $report['investigation_count']; ?>)</h6>
                                                        <?php if ((int) $report['investigation_count'] === 0): ?>
                                                            <p class="small text-muted mb-0">No field investigation updates recorded yet.</p>
                                                        <?php else: ?>
                                                            <?php
                                                                $investigationStmt->execute([':report_id' => $report['report_id']]);
                                                                $updates = $investigationStmt->fetchAll();
                                                            ?>
                                                            <ul class="list-unstyled small mb-0">
                                                                <?php foreach ($updates as $update): ?>
                                                                    <li class="mb-2 p-2 bg-white rounded border border-light shadow-sm">
                                                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                                                            <span class="badge <?php echo get_status_badge_class($update['status']); ?>">
                                                                                <?php echo sanitize_input($update['status']); ?>
                                                                            </span>
                                                                            <span class="text-muted" style="font-size: 0.75rem;"><?php echo format_date($update['updated_at'], 'M d, Y h:i A'); ?></span>
                                                                        </div>
                                                                        <p class="mb-1 text-secondary"><?php echo nl2br(sanitize_input($update['remarks'] ?? '')); ?></p>
                                                                        <div class="text-end text-muted fst-italic" style="font-size: 0.8rem;">
                                                                            &mdash; Officer <?php echo sanitize_input($update['officer_name']); ?>
                                                                        </div>
                                                                    </li>
                                                                <?php endforeach; ?>
                                                            </ul>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
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

<!-- JavaScript to toggle Exact Date Input -->
<script>
function toggleExactDate(val) {
    const exactContainer = document.querySelector('.exact-date-container');
    if (val === 'exact') {
        exactContainer.classList.remove('d-none');
    } else {
        exactContainer.classList.add('d-none');
        document.getElementById('exact_date').value = '';
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>