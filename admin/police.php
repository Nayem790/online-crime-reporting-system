<?php
/**
 * admin/police.php
 *
 * Administrator management of Police Officer accounts (FR-17): view,
 * add, edit, and remove. Uses only the existing users table, always
 * scoped to role = 'police'. Police accounts can only be created here
 * -- there is no public self-registration for this role.
 *
 * Note: investigations.officer_id uses ON DELETE RESTRICT in the
 * schema, so deleting an officer who has existing investigation
 * records will fail at the database level. That failure is caught
 * and shown as a friendly message rather than a fatal error.
 */

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$errors = [];

// ---- Values used to redraw the Add/Edit form ----
$formAction = 'create';
$formUserId = 0;
$full_name = '';
$email = '';
$phone = '';
$address = '';

// ============================================================
// Handle POST actions: create / update / delete
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('danger', 'Invalid form submission. Please try again.');
        redirect('admin/police.php');
    }

    $action = $_POST['action'] ?? '';

    // -------- Delete --------
    if ($action === 'delete') {
        $targetId = (int) ($_POST['user_id'] ?? 0);
        try {
            $stmt = $pdo->prepare('DELETE FROM users WHERE user_id = :id AND role = "police"');
            $stmt->execute([':id' => $targetId]);
            set_flash_message(
                $stmt->rowCount() > 0 ? 'success' : 'warning',
                $stmt->rowCount() > 0 ? 'Police officer account deleted successfully.' : 'Account not found.'
            );
        } catch (PDOException $e) {
            set_flash_message(
                'danger',
                'This officer cannot be deleted because they have existing investigation records linked to their account.'
            );
        }
        redirect('admin/police.php');
    }

    // -------- Create / Update --------
    if ($action === 'create' || $action === 'update') {
        $formAction = $action;
        $formUserId = $action === 'update' ? (int) ($_POST['user_id'] ?? 0) : 0;

        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $password = (string) ($_POST['password'] ?? '');

        if (mb_strlen($full_name) < 3) {
            $errors[] = 'Full name must be at least 3 characters long.';
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        if ($phone !== '' && !preg_match('/^[0-9+\-\s()]{6,20}$/', $phone)) {
            $errors[] = 'Please enter a valid phone number.';
        }
        if ($action === 'create' && strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        }
        if ($action === 'update' && $formUserId <= 0) {
            $errors[] = 'Invalid account reference.';
        }

        if (empty($errors)) {
            $sql = 'SELECT user_id FROM users WHERE email = :email';
            $params = [':email' => $email];
            if ($action === 'update') {
                $sql .= ' AND user_id != :uid';
                $params[':uid'] = $formUserId;
            }
            $checkStmt = $pdo->prepare($sql);
            $checkStmt->execute($params);
            if ($checkStmt->fetch()) {
                $errors[] = 'That email address is already used by another account.';
            }
        }

        if (empty($errors) && $action === 'create') {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare(
                'INSERT INTO users (full_name, email, password, phone, address, role)
                 VALUES (:full_name, :email, :password, :phone, :address, "police")'
            );
            $stmt->execute([
                ':full_name' => $full_name,
                ':email'     => $email,
                ':password'  => $hashedPassword,
                ':phone'     => $phone !== '' ? $phone : null,
                ':address'   => $address !== '' ? $address : null,
            ]);
            set_flash_message('success', 'Police officer account created successfully.');
            redirect('admin/police.php');
        }

        if (empty($errors) && $action === 'update') {
            $stmt = $pdo->prepare(
                'UPDATE users SET full_name = :full_name, email = :email, phone = :phone, address = :address
                 WHERE user_id = :id AND role = "police"'
            );
            $stmt->execute([
                ':full_name' => $full_name,
                ':email'     => $email,
                ':phone'     => $phone !== '' ? $phone : null,
                ':address'   => $address !== '' ? $address : null,
                ':id'        => $formUserId,
            ]);
            set_flash_message(
                $stmt->rowCount() > 0 ? 'success' : 'warning',
                $stmt->rowCount() > 0 ? 'Police officer account updated successfully.' : 'No changes were made.'
            );
            redirect('admin/police.php');
        }
    }
}

// ============================================================
// If editing via link, preload the form with that officer's data
// ============================================================
if (isset($_GET['edit']) && empty($errors)) {
    $editId = (int) $_GET['edit'];
    $stmt = $pdo->prepare('SELECT * FROM users WHERE user_id = :id AND role = "police"');
    $stmt->execute([':id' => $editId]);
    $editUser = $stmt->fetch();

    if (!$editUser) {
        set_flash_message('danger', 'Police officer not found.');
        redirect('admin/police.php');
    }

    $formAction = 'update';
    $formUserId = $editUser['user_id'];
    $full_name = $editUser['full_name'];
    $email = $editUser['email'];
    $phone = $editUser['phone'];
    $address = $editUser['address'];
}

// ============================================================
// List all police officers, with their investigation count for context
// ============================================================
$stmt = $pdo->query(
    'SELECT u.user_id, u.full_name, u.email, u.phone, u.created_at,
            (SELECT COUNT(*) FROM investigations inv WHERE inv.officer_id = u.user_id) AS investigation_count
     FROM users u
     WHERE u.role = "police"
     ORDER BY u.created_at DESC'
);
$officers = $stmt->fetchAll();

$pageTitle = 'Manage Police Officers';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
    
    <!-- ================= PAGE HEADER ================= -->
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-1">Manage Police Officers</h1>
            <p class="text-muted mb-0">Register, update credentials, and oversee field investigation personnel.</p>
        </div>
        <div>
            <span class="badge bg-primary px-3 py-2 fs-6">
                <i class="bi bi-shield-fill-check me-1"></i> Total Officers: <?php echo count($officers); ?>
            </span>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger shadow-sm">
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
                <div class="card-header bg-white fw-semibold py-3">
                    <i class="bi <?php echo $formAction === 'update' ? 'bi-pencil-square text-warning' : 'bi-person-plus text-primary'; ?> me-2"></i>
                    <?php echo $formAction === 'update' ? 'Edit Police Officer' : 'Add New Police Officer'; ?>
                </div>
                <div class="card-body">
                    <form method="POST" action="<?php echo BASE_URL; ?>admin/police.php" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="action" value="<?php echo $formAction; ?>">
                        <?php if ($formAction === 'update'): ?>
                            <input type="hidden" name="user_id" value="<?php echo (int) $formUserId; ?>">
                        <?php endif; ?>

                        <div class="mb-3">
                            <label for="full_name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" required
                                   value="<?php echo sanitize_input($full_name); ?>" placeholder="Officer's full name">
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" required
                                   value="<?php echo sanitize_input($email); ?>" placeholder="officer@crms.gov">
                        </div>

                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="text" class="form-control" id="phone" name="phone"
                                   value="<?php echo sanitize_input($phone ?? ''); ?>" placeholder="+880...">
                        </div>

                        <div class="mb-3">
                            <label for="address" class="form-label">Station / Address</label>
                            <textarea class="form-control" id="address" name="address" rows="2" placeholder="Office location or address"><?php echo sanitize_input($address ?? ''); ?></textarea>
                        </div>

                        <?php if ($formAction === 'create'): ?>
                            <div class="mb-3">
                                <label for="password" class="form-label">Initial Password</label>
                                <input type="password" class="form-control" id="password" name="password" required minlength="8" placeholder="••••••••">
                                <div class="form-text">At least 8 characters required.</div>
                            </div>
                        <?php endif; ?>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save me-1"></i> <?php echo $formAction === 'update' ? 'Save Changes' : 'Create Account'; ?>
                            </button>
                            <?php if ($formAction === 'update'): ?>
                                <a href="<?php echo BASE_URL; ?>admin/police.php" class="btn btn-outline-secondary">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ================= OFFICER LIST ================= -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                    <span class="fw-semibold"><i class="bi bi-shield-shaded me-2 text-primary"></i> Registered Police Personnel</span>
                    <span class="text-muted small">Active Deployments</span>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($officers)): ?>
                        <div class="p-5 text-center text-muted">
                            <i class="bi bi-shield-slash fs-1 d-block mb-2"></i>
                            No police officer accounts created yet.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Officer Name</th>
                                        <th>Contact Info</th>
                                        <th>Investigations</th>
                                        <th>Joined Date</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($officers as $officer): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 36px; height: 36px; font-size: 0.9rem;">
                                                        <?php echo strtoupper(mb_substr($officer['full_name'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <div class="fw-semibold"><?php echo sanitize_input($officer['full_name']); ?></div>
                                                        <div class="text-muted small"><?php echo sanitize_input($officer['email']); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div><?php echo sanitize_input($officer['phone'] ?? "\u{2014}"); ?></div>
                                                <div class="text-muted small text-truncate" style="max-width: 150px;">
                                                    <?php echo sanitize_input($officer['address'] ?? 'No station address'); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1">
                                                    <?php echo (int) $officer['investigation_count']; ?> Cases
                                                </span>
                                            </td>
                                            <td class="text-muted small"><?php echo format_date($officer['created_at'], 'M d, Y'); ?></td>
                                            <td class="text-end">
                                                <div class="d-flex justify-content-end gap-1">
                                                    <a href="<?php echo BASE_URL; ?>admin/police.php?edit=<?php echo (int) $officer['user_id']; ?>"
                                                       class="btn btn-sm btn-outline-secondary" title="Edit Officer">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <form method="POST" action="<?php echo BASE_URL; ?>admin/police.php" class="d-inline"
                                                          onsubmit="return confirm('Delete this police officer account? This cannot be undone.');">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="user_id" value="<?php echo (int) $officer['user_id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Officer">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
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
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>