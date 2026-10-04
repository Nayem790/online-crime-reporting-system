<?php
/**
 * citizen/profile.php
 *
 * Lets the logged-in citizen view and update their own profile
 * (full_name, email, phone, address) and change their password.
 * Uses the existing users table only.
 */

require_once __DIR__ . '/../includes/auth.php';
require_role('citizen');

$userId = $_SESSION['user_id'];
$errors = [];
$passwordErrors = [];

// ---- Load current profile data ----
$stmt = $pdo->prepare('SELECT full_name, email, phone, address FROM users WHERE user_id = :uid');
$stmt->execute([':uid' => $userId]);
$profile = $stmt->fetch();

$full_name = $profile['full_name'];
$email = $profile['email'];
$phone = $profile['phone'];
$address = $profile['address'];

// ---- Handle form submissions ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('danger', 'Invalid form submission. Please try again.');
        redirect('citizen/profile.php');
    }

    $formType = $_POST['form_type'] ?? '';

    // -------- Update profile info --------
    if ($formType === 'update_profile') {
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');

        if (mb_strlen($full_name) < 3) {
            $errors[] = 'Full name must be at least 3 characters long.';
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        if ($phone !== '' && !preg_match('/^[0-9+\-\s()]{6,20}$/', $phone)) {
            $errors[] = 'Please enter a valid phone number.';
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare('SELECT user_id FROM users WHERE email = :email AND user_id != :uid');
            $stmt->execute([':email' => $email, ':uid' => $userId]);
            if ($stmt->fetch()) {
                $errors[] = 'That email address is already used by another account.';
            }
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare(
                'UPDATE users SET full_name = :full_name, email = :email, phone = :phone, address = :address
                 WHERE user_id = :uid'
            );
            $stmt->execute([
                ':full_name' => $full_name,
                ':email'     => $email,
                ':phone'     => $phone !== '' ? $phone : null,
                ':address'   => $address !== '' ? $address : null,
                ':uid'       => $userId,
            ]);

            // Keep the session in sync with the updated values.
            $_SESSION['full_name'] = $full_name;
            $_SESSION['email'] = $email;

            set_flash_message('success', 'Your profile has been updated.');
            redirect('citizen/profile.php');
        }
    }

    // -------- Change password --------
    if ($formType === 'change_password') {
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        $stmt = $pdo->prepare('SELECT password FROM users WHERE user_id = :uid');
        $stmt->execute([':uid' => $userId]);
        $storedHash = $stmt->fetchColumn();

        if (!password_verify($currentPassword, $storedHash)) {
            $passwordErrors[] = 'Your current password is incorrect.';
        }
        if (strlen($newPassword) < 8) {
            $passwordErrors[] = 'New password must be at least 8 characters long.';
        }
        if ($newPassword !== $confirmPassword) {
            $passwordErrors[] = 'New passwords do not match.';
        }

        if (empty($passwordErrors)) {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE users SET password = :password WHERE user_id = :uid');
            $stmt->execute([':password' => $newHash, ':uid' => $userId]);

            set_flash_message('success', 'Your password has been changed successfully.');
            redirect('citizen/profile.php');
        }
    }
}

$pageTitle = 'My Profile';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <h1 class="h3 mb-4">My Profile</h1>

    <div class="row g-4">
        <!-- ================= PROFILE INFO ================= -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">Profile Information</div>
                <div class="card-body">

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0 ps-3">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo sanitize_input($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="<?php echo BASE_URL; ?>citizen/profile.php" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="form_type" value="update_profile">

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
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="text" class="form-control" id="phone" name="phone"
                                   value="<?php echo sanitize_input($phone ?? ''); ?>">
                        </div>

                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3"><?php echo sanitize_input($address ?? ''); ?></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i> Save Changes
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- ================= CHANGE PASSWORD ================= -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">Change Password</div>
                <div class="card-body">

                    <?php if (!empty($passwordErrors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0 ps-3">
                                <?php foreach ($passwordErrors as $error): ?>
                                    <li><?php echo sanitize_input($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="<?php echo BASE_URL; ?>citizen/profile.php" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="form_type" value="change_password">

                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>

                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8">
                            <div class="form-text">At least 8 characters.</div>
                        </div>

                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8">
                        </div>

                        <button type="submit" class="btn btn-outline-primary">
                            <i class="bi bi-key me-1"></i> Change Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>