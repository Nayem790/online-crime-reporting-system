<?php
/**
 * admin/reports.php
 *
 * Administrator, read-only, system-wide view of all crime reports
 * (FR-19), with search and filter (broadened to also match citizen
 * name/email, since admin oversight often starts from a person, not
 * just a report). Since the approved admin file set has no separate
 * report-details page, each row can expand in place (Bootstrap
 * collapse) to show full description, evidence, verification info,
 * and investigation history -- no new file needed.
 */

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$validStatuses = ['Pending', 'Verified', 'Rejected', 'Investigating', 'Resolved'];

$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$categoryFilter = trim($_GET['category_id'] ?? '');

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

$hasActiveFilters = ($search !== '' || $statusFilter !== '' || $categoryFilter !== '');

// ---- Prepared statements reused inside the loop for expandable details ----
$evidenceStmt = $pdo->prepare('SELECT * FROM evidence WHERE report_id = :report_id ORDER BY uploaded_at');
$investigationStmt = $pdo->prepare(
    'SELECT inv.*, u.full_name AS officer_name
     FROM investigations inv
     JOIN users u ON inv.officer_id = u.user_id
     WHERE inv.report_id = :report_id
     ORDER BY inv.updated_at ASC'
);

$pageTitle = 'All Crime Reports';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <h1 class="h3 mb-4">All Crime Reports</h1>

    <!-- ================= SEARCH / FILTER ================= -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="<?php echo BASE_URL; ?>admin/reports.php" class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search"
                           placeholder="Title, description, location, or citizen"
                           value="<?php echo sanitize_input($search); ?>">
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All Statuses</option>
                        <?php foreach ($validStatuses as $status): ?>
                            <option value="<?php echo $status; ?>" <?php echo $statusFilter === $status ? 'selected' : ''; ?>>
                                <?php echo $status; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="category_id" class="form-label">Category</label>
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
                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </form>
            <?php if ($hasActiveFilters): ?>
                <a href="<?php echo BASE_URL; ?>admin/reports.php" class="small d-inline-block mt-2">
                    <i class="bi bi-x-circle"></i> Clear filters
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================= RESULTS ================= -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span class="fw-semibold">Results</span>
            <span class="text-muted small"><?php echo count($reports); ?> report(s) found</span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($reports)): ?>
                <div class="p-5 text-center text-muted">
                    <i class="bi bi-search fs-1 d-block mb-2"></i>
                    No reports match your search/filter criteria.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Title</th>
                                <th>Citizen</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th>Submitted</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reports as $report): ?>
                                <?php $detailId = 'detail' . (int) $report['report_id']; ?>
                                <tr>
                                    <td class="text-muted">#<?php echo (int) $report['report_id']; ?></td>
                                    <td><?php echo sanitize_input($report['title']); ?></td>
                                    <td><?php echo sanitize_input($report['citizen_name']); ?></td>
                                    <td><?php echo sanitize_input($report['category_name']); ?></td>
                                    <td>
                                        <span class="badge <?php echo get_status_badge_class($report['status']); ?>">
                                            <?php echo sanitize_input($report['status']); ?>
                                        </span>
                                    </td>
                                    <td class="text-muted small"><?php echo format_date($report['submitted_at'], 'M d, Y'); ?></td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse"
                                                data-bs-target="#<?php echo $detailId; ?>">
                                            View
                                        </button>
                                    </td>
                                </tr>
                                <tr class="collapse" id="<?php echo $detailId; ?>">
                                    <td colspan="7" class="bg-light">
                                        <div class="row g-4 py-3">

                                            <!-- Crime information -->
                                            <div class="col-md-6">
                                                <h3 class="h6">Crime Information</h3>
                                                <dl class="row small mb-0">
                                                    <dt class="col-sm-4">Location</dt>
                                                    <dd class="col-sm-8"><?php echo sanitize_input($report['incident_location']); ?></dd>

                                                    <dt class="col-sm-4">Incident Date</dt>
                                                    <dd class="col-sm-8"><?php echo format_date($report['incident_date'], 'M d, Y'); ?></dd>

                                                    <dt class="col-sm-4">Citizen Email</dt>
                                                    <dd class="col-sm-8"><?php echo sanitize_input($report['citizen_email']); ?></dd>

                                                    <dt class="col-sm-4">Description</dt>
                                                    <dd class="col-sm-8 mb-0" style="white-space: pre-wrap;"><?php echo sanitize_input($report['description']); ?></dd>
                                                </dl>

                                                <h3 class="h6 mt-3">Verification</h3>
                                                <?php if ($report['status'] === 'Pending'): ?>
                                                    <p class="small text-muted mb-0">Not yet verified.</p>
                                                <?php else: ?>
                                                    <dl class="row small mb-0">
                                                        <dt class="col-sm-4">Verified By</dt>
                                                        <dd class="col-sm-8"><?php echo sanitize_input($report['verifier_name'] ?? 'N/A'); ?></dd>

                                                        <dt class="col-sm-4">Verified On</dt>
                                                        <dd class="col-sm-8"><?php echo format_date($report['verified_at']); ?></dd>

                                                        <dt class="col-sm-4">Remarks</dt>
                                                        <dd class="col-sm-8 mb-0">
                                                            <?php echo $report['verification_remarks']
                                                                ? nl2br(sanitize_input($report['verification_remarks']))
                                                                : 'No remarks provided.'; ?>
                                                        </dd>
                                                    </dl>
                                                <?php endif; ?>
                                            </div>

                                            <!-- Evidence + investigation history -->
                                            <div class="col-md-6">
                                                <h3 class="h6">Evidence (<?php echo (int) $report['evidence_count']; ?>)</h3>
                                                <?php if ((int) $report['evidence_count'] === 0): ?>
                                                    <p class="small text-muted">No evidence files uploaded.</p>
                                                <?php else: ?>
                                                    <?php
                                                        $evidenceStmt->execute([':report_id' => $report['report_id']]);
                                                        $evidenceFiles = $evidenceStmt->fetchAll();
                                                    ?>
                                                    <ul class="list-unstyled small mb-0">
                                                        <?php foreach ($evidenceFiles as $evidence): ?>
                                                            <li class="mb-1">
                                                                <i class="bi bi-paperclip me-1"></i>
                                                                <a href="<?php echo BASE_URL . sanitize_input($evidence['file_path']); ?>" target="_blank" rel="noopener">
                                                                    <?php echo sanitize_input($evidence['file_name']); ?>
                                                                </a>
                                                            </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                <?php endif; ?>

                                                <h3 class="h6 mt-3">Investigation Updates (<?php echo (int) $report['investigation_count']; ?>)</h3>
                                                <?php if ((int) $report['investigation_count'] === 0): ?>
                                                    <p class="small text-muted mb-0">No investigation updates recorded.</p>
                                                <?php else: ?>
                                                    <?php
                                                        $investigationStmt->execute([':report_id' => $report['report_id']]);
                                                        $updates = $investigationStmt->fetchAll();
                                                    ?>
                                                    <ul class="list-unstyled small mb-0">
                                                        <?php foreach ($updates as $update): ?>
                                                            <li class="mb-2">
                                                                <span class="badge <?php echo get_status_badge_class($update['status']); ?>">
                                                                    <?php echo sanitize_input($update['status']); ?>
                                                                </span>
                                                                <span class="text-muted"><?php echo format_date($update['updated_at'], 'M d, Y h:i A'); ?></span>
                                                                <br>
                                                                <?php echo nl2br(sanitize_input($update['remarks'] ?? '')); ?>
                                                                <span class="text-muted">&mdash; <?php echo sanitize_input($update['officer_name']); ?></span>
                                                            </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                <?php endif; ?>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>