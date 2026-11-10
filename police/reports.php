<?php
/**
 * police/reports.php
 *
 * Lists all crime reports in the system. Supports search by keyword,
 * Case ID/number, filtering by status, category, and exact date/timeframe with Executive Portal styling.
 */

require_once __DIR__ . '/../includes/auth.php';
require_role('police');

$validStatuses = ['Pending', 'Verified', 'Rejected', 'Investigating', 'Resolved', 'Closed'];

$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$categoryFilter = trim($_GET['category_id'] ?? '');
$dateRange = trim($_GET['date_range'] ?? '');
$exactDate = trim($_GET['exact_date'] ?? '');

/**
 * Professional case reference, e.g. CR-2026-00014
 */
if (!function_exists('format_case_id')) {
    function format_case_id($reportId, $submittedAt) {
        return sprintf('CR-%s-%05d', date('Y', strtotime($submittedAt)), (int) $reportId);
    }
}

$categories = $pdo->query('SELECT category_id, category_name FROM crime_categories ORDER BY category_name')->fetchAll();

// ---- Build the filtered query safely with Case ID, Keyword, and Date filters ----
$conditions = [];
$params = [];

if ($search !== '') {
    $searchId = 0;
    if (preg_match('/CR-\d{4}-(\d+)/i', $search, $matches)) {
        $searchId = (int) $matches[1];
    } else {
        $searchId = (int) preg_replace('/[^0-9]/', '', $search);
    }

    if ($searchId > 0) {
        $conditions[] = '(cr.report_id = :searchId OR cr.title LIKE :search1 OR cr.description LIKE :search2 OR cr.incident_location LIKE :search3)';
        $params[':searchId'] = $searchId;
    } else {
        $conditions[] = '(cr.title LIKE :search1 OR cr.description LIKE :search2 OR cr.incident_location LIKE :search3)';
    }
    
    $params[':search1'] = '%' . $search . '%';
    $params[':search2'] = '%' . $search . '%';
    $params[':search3'] = '%' . $search . '%';
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

$sql = 'SELECT cr.report_id, cr.title, cr.status, cr.incident_date, cr.submitted_at,
               cc.category_name, u.full_name AS citizen_name
        FROM crime_reports cr
        JOIN crime_categories cc ON cr.category_id = cc.category_id
        JOIN users u ON cr.user_id = u.user_id';

if (!empty($conditions)) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
}

$sql .= ' ORDER BY cr.submitted_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reports = $stmt->fetchAll();

$hasActiveFilters = ($search !== '' || $statusFilter !== '' || $categoryFilter !== '' || $dateRange !== '');

$pageTitle = 'Manage Crime Reports';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h1 class="h3 mb-1">Crime Reports Management</h1>
            <p class="text-muted mb-0">Search by Case ID or keyword, filter by status, category, and exact date.</p>
        </div>
        <span class="badge bg-dark fs-6 px-3 py-2">
            Total Results: <?php echo count($reports); ?>
        </span>
    </div>

    <!-- ================= SEARCH / FILTER CARD ================= -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <form method="GET" action="<?php echo BASE_URL; ?>police/reports.php" class="row g-3 align-items-end">
                <!-- Keyword / Case ID Search -->
                <div class="col-md-4">
                    <label for="search" class="form-label">Search Keyword or Case ID</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control border-start-0 ps-0" id="search" name="search"
                               placeholder="e.g. CR-2026-00004, title..."
                               value="<?php echo sanitize_input($search); ?>">
                    </div>
                </div>

                <!-- Status Filter -->
                <div class="col-md-2">
                    <label for="status" class="form-label">Filter by Status</label>
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
                    <label for="category_id" class="form-label">Filter by Category</label>
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
                    <label for="date_range" class="form-label">Timeframe / Date</label>
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

                <!-- Submit Button -->
                <div class="col-12 d-flex justify-content-end gap-2 mt-2">
                    <?php if ($hasActiveFilters): ?>
                        <a href="<?php echo BASE_URL; ?>police/reports.php" class="btn btn-outline-secondary px-3">
                            <i class="bi bi-x-circle me-1"></i> Clear Filters
                        </a>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-filter me-1"></i> Apply Filters
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ================= RESULTS TABLE ================= -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 text-dark"><i class="bi bi-table me-2 text-primary"></i>Crime Report Files</h5>
            <span class="text-muted small"><?php echo count($reports); ?> record(s) listed</span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($reports)): ?>
                <div class="p-5 text-center text-muted">
                    <div class="stat-icon mx-auto mb-3" style="width:72px;height:72px;font-size:2rem;">
                        <i class="bi bi-search"></i>
                    </div>
                    <h2 class="h5 mb-1">No Matching Reports</h2>
                    <p class="text-muted small mb-3">No incident reports match your current search criteria or filters.</p>
                    <a href="<?php echo BASE_URL; ?>police/reports.php" class="btn btn-sm btn-outline-primary">
                        Reset Filters
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Case ID</th>
                                <th>Incident Title</th>
                                <th>Citizen (Reporter)</th>
                                <th>Category</th>
                                <th>Incident Date</th>
                                <th>Status</th>
                                <th>Submitted On</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reports as $report): ?>
                                <tr>
                                    <td>
                                        <span class="fw-semibold text-dark">
                                            <?php echo sanitize_input(format_case_id($report['report_id'], $report['submitted_at'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="fw-medium text-dark">
                                            <?php echo sanitize_input(mb_strimwidth($report['title'], 0, 38, '...', 'UTF-8')); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="text-dark"><i class="bi bi-person me-1 text-muted"></i><?php echo sanitize_input($report['citizen_name']); ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            <?php echo sanitize_input($report['category_name']); ?>
                                        </span>
                                    </td>
                                    <td class="text-muted"><?php echo format_date($report['incident_date'], 'M d, Y'); ?></td>
                                    <td>
                                        <span class="badge <?php echo get_status_badge_class($report['status']); ?>">
                                            <?php echo sanitize_input($report['status']); ?>
                                        </span>
                                    </td>
                                    <td class="text-muted small"><?php echo format_date($report['submitted_at'], 'M d, Y'); ?></td>
                                    <td class="text-end">
                                        <a href="<?php echo BASE_URL; ?>police/report_details.php?id=<?php echo (int) $report['report_id']; ?>"
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye me-1"></i>View
                                        </a>
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