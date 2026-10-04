<?php
/**
 * admin/categories.php
 *
 * Administrator management of crime categories (FR-18): view, add,
 * edit, and remove. Uses only the existing crime_categories table.
 *
 * Note: crime_reports.category_id uses ON DELETE RESTRICT in the
 * schema, so deleting a category that already has reports linked to
 * it will fail at the database level. That failure is caught and
 * shown as a friendly message -- categories in use are never
 * silently deleted or left in a broken state.
 */

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$errors = [];

// ---- Values used to redraw the Add/Edit form ----
$formAction = 'create';
$formCategoryId = 0;
$category_name = '';
$description = '';

// ============================================================
// Handle POST actions: create / update / delete
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('danger', 'Invalid form submission. Please try again.');
        redirect('admin/categories.php');
    }

    $action = $_POST['action'] ?? '';

    // -------- Delete --------
    if ($action === 'delete') {
        $targetId = (int) ($_POST['category_id'] ?? 0);
        try {
            $stmt = $pdo->prepare('DELETE FROM crime_categories WHERE category_id = :id');
            $stmt->execute([':id' => $targetId]);
            set_flash_message(
                $stmt->rowCount() > 0 ? 'success' : 'warning',
                $stmt->rowCount() > 0 ? 'Crime category deleted.' : 'Category not found.'
            );
        } catch (PDOException $e) {
            set_flash_message(
                'danger',
                'This category cannot be deleted because one or more crime reports are already assigned to it.'
            );
        }
        redirect('admin/categories.php');
    }

    // -------- Create / Update --------
    if ($action === 'create' || $action === 'update') {
        $formAction = $action;
        $formCategoryId = $action === 'update' ? (int) ($_POST['category_id'] ?? 0) : 0;

        $category_name = trim($_POST['category_name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (mb_strlen($category_name) < 3 || mb_strlen($category_name) > 100) {
            $errors[] = 'Category name must be between 3 and 100 characters.';
        }
        if ($action === 'update' && $formCategoryId <= 0) {
            $errors[] = 'Invalid category reference.';
        }

        if (empty($errors)) {
            $sql = 'SELECT category_id FROM crime_categories WHERE category_name = :name';
            $params = [':name' => $category_name];
            if ($action === 'update') {
                $sql .= ' AND category_id != :id';
                $params[':id'] = $formCategoryId;
            }
            $checkStmt = $pdo->prepare($sql);
            $checkStmt->execute($params);
            if ($checkStmt->fetch()) {
                $errors[] = 'A category with this name already exists.';
            }
        }

        if (empty($errors) && $action === 'create') {
            $stmt = $pdo->prepare(
                'INSERT INTO crime_categories (category_name, description) VALUES (:name, :description)'
            );
            $stmt->execute([
                ':name'        => $category_name,
                ':description' => $description !== '' ? $description : null,
            ]);
            set_flash_message('success', 'Crime category created successfully.');
            redirect('admin/categories.php');
        }

        if (empty($errors) && $action === 'update') {
            $stmt = $pdo->prepare(
                'UPDATE crime_categories SET category_name = :name, description = :description WHERE category_id = :id'
            );
            $stmt->execute([
                ':name'        => $category_name,
                ':description' => $description !== '' ? $description : null,
                ':id'          => $formCategoryId,
            ]);
            set_flash_message(
                $stmt->rowCount() > 0 ? 'success' : 'warning',
                $stmt->rowCount() > 0 ? 'Crime category updated successfully.' : 'No changes were made.'
            );
            redirect('admin/categories.php');
        }
    }
}

// ============================================================
// If editing via link, preload the form with that category's data
// ============================================================
if (isset($_GET['edit']) && empty($errors)) {
    $editId = (int) $_GET['edit'];
    $stmt = $pdo->prepare('SELECT * FROM crime_categories WHERE category_id = :id');
    $stmt->execute([':id' => $editId]);
    $editCategory = $stmt->fetch();

    if (!$editCategory) {
        set_flash_message('danger', 'Category not found.');
        redirect('admin/categories.php');
    }

    $formAction = 'update';
    $formCategoryId = $editCategory['category_id'];
    $category_name = $editCategory['category_name'];
    $description = $editCategory['description'];
}

// ============================================================
// List all categories, with their report count for context
// ============================================================
$stmt = $pdo->query(
    'SELECT cc.category_id, cc.category_name, cc.description, cc.created_at,
            (SELECT COUNT(*) FROM crime_reports cr WHERE cr.category_id = cc.category_id) AS report_count
     FROM crime_categories cc
     ORDER BY cc.category_name ASC'
);
$categories = $stmt->fetchAll();

$pageTitle = 'Manage Crime Categories';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <h1 class="h3 mb-4">Manage Crime Categories</h1>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0 ps-3">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo sanitize_input($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- ================= ADD / EDIT FORM ================= -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold">
                    <?php echo $formAction === 'update' ? 'Edit Category' : 'Add New Category'; ?>
                </div>
                <div class="card-body">
                    <form method="POST" action="<?php echo BASE_URL; ?>admin/categories.php" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="action" value="<?php echo $formAction; ?>">
                        <?php if ($formAction === 'update'): ?>
                            <input type="hidden" name="category_id" value="<?php echo (int) $formCategoryId; ?>">
                        <?php endif; ?>

                        <div class="mb-3">
                            <label for="category_name" class="form-label">Category Name</label>
                            <input type="text" class="form-control" id="category_name" name="category_name" required
                                   maxlength="100" value="<?php echo sanitize_input($category_name); ?>">
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?php echo sanitize_input($description ?? ''); ?></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i> <?php echo $formAction === 'update' ? 'Save Changes' : 'Create Category'; ?>
                        </button>
                        <?php if ($formAction === 'update'): ?>
                            <a href="<?php echo BASE_URL; ?>admin/categories.php" class="btn btn-outline-secondary">Cancel</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <!-- ================= CATEGORY LIST ================= -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">All Categories</span>
                    <span class="text-muted small"><?php echo count($categories); ?> categor<?php echo count($categories) === 1 ? 'y' : 'ies'; ?></span>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($categories)): ?>
                        <div class="p-5 text-center text-muted">No crime categories have been created yet.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Name</th>
                                        <th>Description</th>
                                        <th>Reports</th>
                                        <th>Created</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($categories as $category): ?>
                                        <?php $inUse = (int) $category['report_count'] > 0; ?>
                                        <tr>
                                            <td><?php echo sanitize_input($category['category_name']); ?></td>
                                            <td class="text-muted small"><?php echo sanitize_input($category['description'] ?? "\u{2014}"); ?></td>
                                            <td>
                                                <span class="badge <?php echo $inUse ? 'bg-secondary' : 'bg-light text-dark'; ?>">
                                                    <?php echo (int) $category['report_count']; ?>
                                                </span>
                                            </td>
                                            <td class="text-muted small"><?php echo format_date($category['created_at'], 'M d, Y'); ?></td>
                                            <td class="text-end">
                                                <a href="<?php echo BASE_URL; ?>admin/categories.php?edit=<?php echo (int) $category['category_id']; ?>"
                                                   class="btn btn-sm btn-outline-secondary">Edit</a>
                                                <?php if ($inUse): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" disabled
                                                            title="This category has <?php echo (int) $category['report_count']; ?> report(s) assigned to it and cannot be deleted.">
                                                        Delete
                                                    </button>
                                                <?php else: ?>
                                                    <form method="POST" action="<?php echo BASE_URL; ?>admin/categories.php" class="d-inline"
                                                          onsubmit="return confirm('Delete this crime category? This cannot be undone.');">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="category_id" value="<?php echo (int) $category['category_id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                                    </form>
                                                <?php endif; ?>
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
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>