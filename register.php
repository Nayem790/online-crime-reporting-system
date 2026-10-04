<?php
/**
 * register.php
 *
 * Self-registration is for Citizens only, per the approved scope.
 * Police and Administrator accounts are created by an Administrator
 * (Admin module), never through public self-registration.
 */

require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect_to_dashboard();
}

$errors = [];
$full_name = '';
$email = '';
$phone = '';
$address = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission. Please refresh the page and try again.';
    }

    // Raw (trimmed only) values are what get validated/stored.
    // HTML-escaping is applied only when echoing values back into the page.
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $confirm_password = (string) ($_POST['confirm_password'] ?? '');

    if (mb_strlen($full_name) < 3) {
        $errors[] = 'Full name must be at least 3 characters long.';
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if ($phone !== '' && !preg_match('/^[0-9+\-\s()]{6,20}$/', $phone)) {
        $errors[] = 'Please enter a valid phone number.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }

    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare('SELECT user_id FROM users WHERE email = :email');
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) {
            $errors[] = 'An account with this email already exists. Please log in instead.';
        }
    }

    if (empty($errors)) {
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

        set_flash_message('success', 'Registration successful! You can now log in.');
        redirect('login.php');
    }
}

$pageTitle = 'Register';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-6">
            <div class="card shadow-sm border-0 my-3">
                <div class="card-body p-4 p-md-5">
                    <h1 class="h3 mb-1 text-center">Create a Citizen Account</h1>
                    <p class="text-muted text-center mb-4">Report incidents and track their progress online.</p>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0 ps-3">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo sanitize_input($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="<?php echo BASE_URL; ?>register.php" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

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
                            <label for="phone" class="form-label">Phone Number <span class="text-muted">(optional)</span></label>
                            <input type="text" class="form-control" id="phone" name="phone"
                                   value="<?php echo sanitize_input($phone); ?>">
                        </div>

                        <div class="mb-3">
                            <label for="address" class="form-label">Address <span class="text-muted">(optional)</span></label>
                            <textarea class="form-control" id="address" name="address" rows="2"><?php echo sanitize_input($address); ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required minlength="8">
                                <div class="form-text">At least 8 characters.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 mt-2">Register</button>
                    </form>

                    <p class="text-center text-muted mt-4 mb-0">
                        Already have an account?
                        <a href="<?php echo BASE_URL; ?>login.php">Log in</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>