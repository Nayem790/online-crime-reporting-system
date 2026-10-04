<?php
/**
 * police/reports.php
 *
 * Lists all crime reports in the system (any officer may view/act on
 * any report -- there is no per-officer assignment in the approved
 * design). Supports search by keyword plus filtering by status and
 * category (FR-15).
 */

require_once __DIR__ . '/../includes/auth.php';
require_role('police');

$validStatuses = ['Pending', 'Verified', 'Rejected', 'Investigating', 'Resolved'];

$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$categoryFilter = trim($_GET['category_id'] ?? '');

$categories = $pdo->query('SELECT category_id, category_name FROM crime_categories ORDER BY category_name')->fetchAll();

// ---- Build the filtered query safely ----
$conditions = [];
$params = [];

if ($search !== '') {
    $conditions[] = '(cr.title LIKE :search OR cr.description LIKE :search OR cr.incident_location LIKE :search)';
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

$hasActiveFilters = ($search !== '' || $statusFilter !== '' || $categoryFilter !== '');

$pageTitle = 'Crime Reports';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <h1 class="h3 mb-4">Crime Reports</h1>

    <!-- ================= SEARCH / FILTER ================= -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="<?php echo BASE_URL; ?>police/reports.php" class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search"
                           placeholder="Title, description, or location"
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
                <a href="<?php echo BASE_URL; ?>police/reports.php" class="small d-inline-block mt-2">
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
                                <th>Incident Date</th>
                                <th>Status</th>
                                <th>Submitted</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reports as $report): ?>
                                <tr>
                                    <td class="text-muted">#<?php echo (int) $report['report_id']; ?></td>
                                    <td><?php echo sanitize_input($report['title']); ?></td>
                                    <td><?php echo sanitize_input($report['citizen_name']); ?></td>
                                    <td><?php echo sanitize_input($report['category_name']); ?></td>
                                    <td><?php echo format_date($report['incident_date'], 'M d, Y'); ?></td>
                                    <td>
                                        <span class="badge <?php echo get_status_badge_class($report['status']); ?>">
                                            <?php echo sanitize_input($report['status']); ?>
                                        </span>
                                    </td>
                                    <td class="text-muted small"><?php echo format_date($report['submitted_at'], 'M d, Y'); ?></td>
                                    <td class="text-end">
                                        <a href="<?php echo BASE_URL; ?>police/report_details.php?id=<?php echo (int) $report['report_id']; ?>"
                                           class="btn btn-sm btn-outline-primary">View</a>
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