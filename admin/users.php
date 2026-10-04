<?php
/**
 * admin/users.php
 *
 * Administrator management of Citizen accounts (FR-16): view, add,
 * edit, and remove. Uses only the existing users table, always
 * scoped to role = 'citizen' so this page can never touch police or
 * admin accounts.
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
        redirect('admin/users.php');
    }

    $action = $_POST['action'] ?? '';

    // -------- Delete --------
    if ($action === 'delete') {
        $targetId = (int) ($_POST['user_id'] ?? 0);
        try {
            $stmt = $pdo->prepare('DELETE FROM users WHERE user_id = :id AND role = "citizen"');
            $stmt->execute([':id' => $targetId]);
            set_flash_message(
                $stmt->rowCount() > 0 ? 'success' : 'warning',
                $stmt->rowCount() > 0 ? 'Citizen account deleted.' : 'Account not found.'
            );
        } catch (PDOException $e) {
            set_flash_message('danger', 'This account could not be deleted because related records exist.');
        }
        redirect('admin/users.php');
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
                 VALUES (:full_name, :email, :password, :phone, :address, "citizen")'
            );
            $stmt->execute([
                ':full_name' => $full_name,
                ':email'     => $email,
                ':password'  => $hashedPassword,
                ':phone'     => $phone !== '' ? $phone : null,
                ':address'   => $address !== '' ? $address : null,
            ]);
            set_flash_message('success', 'Citizen account created successfully.');
            redirect('admin/users.php');
        }

        if (empty($errors) && $action === 'update') {
            $stmt = $pdo->prepare(
                'UPDATE users SET full_name = :full_name, email = :email, phone = :phone, address = :address
                 WHERE user_id = :id AND role = "citizen"'
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
                $stmt->rowCount() > 0 ? 'Citizen account updated successfully.' : 'No changes were made.'
            );
            redirect('admin/users.php');
        }
    }
}

// ============================================================
// If editing via link, preload the form with that citizen's data
// ============================================================
if (isset($_GET['edit']) && empty($errors)) {
    $editId = (int) $_GET['edit'];
    $stmt = $pdo->prepare('SELECT * FROM users WHERE user_id = :id AND role = "citizen"');
    $stmt->execute([':id' => $editId]);
    $editUser = $stmt->fetch();

    if (!$editUser) {
        set_flash_message('danger', 'Citizen not found.');
        redirect('admin/users.php');
    }

    $formAction = 'update';
    $formUserId = $editUser['user_id'];
    $full_name = $editUser['full_name'];
    $email = $editUser['email'];
    $phone = $editUser['phone'];
    $address = $editUser['address'];
}

// ============================================================
// List all citizens, with their report count for context
// ============================================================
$stmt = $pdo->query(
    'SELECT u.user_id, u.full_name, u.email, u.phone, u.created_at,
            (SELECT COUNT(*) FROM crime_reports cr WHERE cr.user_id = u.user_id) AS report_count
     FROM users u
     WHERE u.role = "citizen"
     ORDER BY u.created_at DESC'
);
$citizens = $stmt->fetchAll();

$pageTitle = 'Manage Citizens';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <h1 class="h3 mb-4">Manage Citizens</h1>

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
                    <?php echo $formAction === 'update' ? 'Edit Citizen' : 'Add New Citizen'; ?>
                </div>
                <div class="card-body">
                    <form method="POST" action="<?php echo BASE_URL; ?>admin/users.php" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="action" value="<?php echo $formAction; ?>">
                        <?php if ($formAction === 'update'): ?>
                            <input type="hidden" name="user_id" value="<?php echo (int) $formUserId; ?>">
                        <?php endif; ?>

                        <div class="mb-3">
                            <label for="full_name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" required
                                   value="<?php echo sanitize_input($full_name); ?>">
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" required
                                   value="<?php echo sanitize_input($email); ?>">
                        </div>

                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="text" class="form-control" id="phone" name="phone"
                                   value="<?php echo sanitize_input($phone ?? ''); ?>">
                        </div>

                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="2"><?php echo sanitize_input($address ?? ''); ?></textarea>
                        </div>

                        <?php if ($formAction === 'create'): ?>
                            <div class="mb-3">
                                <label for="password" class="form-label">Initial Password</label>
                                <input type="password" class="form-control" id="password" name="password" required minlength="8">
                                <div class="form-text">At least 8 characters. The citizen can change it later.</div>
                            </div>
                        <?php endif; ?>

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i> <?php echo $formAction === 'update' ? 'Save Changes' : 'Create Account'; ?>
                        </button>
                        <?php if ($formAction === 'update'): ?>
                            <a href="<?php echo BASE_URL; ?>admin/users.php" class="btn btn-outline-secondary">Cancel</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <!-- ================= CITIZEN LIST ================= -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">All Citizens</span>
                    <span class="text-muted small"><?php echo count($citizens); ?> account(s)</span>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($citizens)): ?>
                        <div class="p-5 text-center text-muted">No citizen accounts yet.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Reports</th>
                                        <th>Joined</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($citizens as $citizen): ?>
                                        <tr>
                                            <td><?php echo sanitize_input($citizen['full_name']); ?></td>
                                            <td><?php echo sanitize_input($citizen['email']); ?></td>
                                            <td><?php echo sanitize_input($citizen['phone'] ?? "\u{2014}"); ?></td>
                                            <td><?php echo (int) $citizen['report_count']; ?></td>
                                            <td class="text-muted small"><?php echo format_date($citizen['created_at'], 'M d, Y'); ?></td>
                                            <td class="text-end">
                                                <a href="<?php echo BASE_URL; ?>admin/users.php?edit=<?php echo (int) $citizen['user_id']; ?>"
                                                   class="btn btn-sm btn-outline-secondary">Edit</a>
                                                <form method="POST" action="<?php echo BASE_URL; ?>admin/users.php" class="d-inline"
                                                      onsubmit="return confirm('Delete this citizen account? This will also remove all of their reports, evidence, and notifications. This cannot be undone.');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="user_id" value="<?php echo (int) $citizen['user_id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                                </form>
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