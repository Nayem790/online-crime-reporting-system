<?php
/**
 * login.php
 *
 * Single login form for all three roles. The role is read from the
 * matched USERS row (not chosen by the user), and login_user()/
 * redirect_to_dashboard() send each user to the correct dashboard.
 */

require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect_to_dashboard();
}

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission. Please refresh the page and try again.';
    }

    $email = trim($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $errors[] = 'Please enter both your email and password.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare('SELECT user_id, full_name, email, password, role FROM users WHERE email = :email');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            login_user($user);
            set_flash_message('success', 'Welcome back, ' . $user['full_name'] . '!');
            redirect_to_dashboard();
        } else {
            // Deliberately generic message: do not reveal whether the
            // email exists or the password was wrong.
            $errors[] = 'Invalid email or password.';
        }
    }
}

$pageTitle = 'Login';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-sm border-0 my-3">
                <div class="card-body p-4 p-md-5">
                    <h1 class="h3 mb-1 text-center">Sign In</h1>
                    <p class="text-muted text-center mb-4">Citizen, Police Officer, or Administrator</p>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0 ps-3">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo sanitize_input($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="<?php echo BASE_URL; ?>login.php" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" required
                                   value="<?php echo sanitize_input($email); ?>" autofocus>
                        </div>

                        <div class="mb-2">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>

                        <div class="text-end mb-3">
                            <a href="<?php echo BASE_URL; ?>forgot_password.php" class="small">Forgot password?</a>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Login</button>
                    </form>

                    <p class="text-center text-muted mt-4 mb-0">
                        New citizen?
                        <a href="<?php echo BASE_URL; ?>register.php">Create an account</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>