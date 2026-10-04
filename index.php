<?php
/**
 * index.php
 *
 * Public homepage. Accessible to everyone (no login required).
 * Emergency contact info is pulled from the existing HELP_RESOURCES
 * table (resource_type = 'emergency_contact') -- no new table needed.
 */

require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Home';
require_once __DIR__ . '/includes/header.php';

$loggedIn = is_logged_in();
$dashboardUrl = $loggedIn ? BASE_URL . $_SESSION['role'] . '/dashboard.php' : null;

// Pull a couple of emergency contacts from the existing HELP_RESOURCES table.
$stmt = $pdo->prepare(
    "SELECT title, contact_info FROM help_resources
     WHERE resource_type = 'emergency_contact'
     ORDER BY resource_id LIMIT 2"
);
$stmt->execute();
$emergencyContacts = $stmt->fetchAll();
?>

<!-- ================= HERO ================= -->
<section class="bg-light border-bottom">
    <div class="container py-5">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <h1 class="display-6 fw-bold mb-3">
                    <i class="bi bi-shield-lock-fill text-primary me-2"></i>
                    Online Crime Reporting &amp; Case Management System
                </h1>
                <p class="lead text-muted">
                    Report incidents from anywhere, attach supporting evidence, and track the
                    progress of your case online &mdash; without needing to visit a police station
                    in person.
                </p>
                <p class="text-muted">
                    Police officers verify and investigate reports through a dedicated dashboard,
                    and administrators keep user accounts and crime categories organized &mdash;
                    all in one centralized, role-based platform.
                </p>

                <?php if ($loggedIn): ?>
                    <a href="<?php echo $dashboardUrl; ?>" class="btn btn-primary btn-lg mt-2">
                        <i class="bi bi-speedometer2 me-1"></i> Go to My Dashboard
                    </a>
                <?php else: ?>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <a href="<?php echo BASE_URL; ?>register.php" class="btn btn-primary btn-lg">
                            <i class="bi bi-person-plus-fill me-1"></i> Register as Citizen
                        </a>
                        <a href="<?php echo BASE_URL; ?>login.php" class="btn btn-outline-secondary btn-lg">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Login
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <div class="col-lg-5">
                <?php if (!empty($emergencyContacts)): ?>
                    <div class="card border-danger shadow-sm">
                        <div class="card-header bg-danger text-white fw-semibold">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i> In an Emergency
                        </div>
                        <div class="card-body">
                            <p class="small text-muted mb-3">
                                This system is for reporting and tracking non-emergency cases.
                                If you are in immediate danger or witnessing a crime in progress,
                                contact emergency services right away.
                            </p>
                            <ul class="list-unstyled mb-0">
                                <?php foreach ($emergencyContacts as $contact): ?>
                                    <li class="d-flex justify-content-between border-top pt-2 mt-2">
                                        <span><?php echo sanitize_input($contact['title']); ?></span>
                                        <strong class="text-danger"><?php echo sanitize_input($contact['contact_info']); ?></strong>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- ================= HOW IT WORKS ================= -->
<section class="container py-5">
    <h2 class="h3 text-center mb-5">How It Works</h2>
    <div class="row g-4 text-center">
        <div class="col-md-4">
            <div class="p-4 h-100">
                <div class="display-6 text-primary mb-2"><i class="bi bi-pencil-square"></i></div>
                <h3 class="h5">1. Submit a Report</h3>
                <p class="text-muted small mb-0">
                    A citizen registers, selects a crime category, describes the incident,
                    and optionally uploads photo or PDF evidence.
                </p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="p-4 h-100">
                <div class="display-6 text-primary mb-2"><i class="bi bi-clipboard-check"></i></div>
                <h3 class="h5">2. Officer Verification</h3>
                <p class="text-muted small mb-0">
                    A police officer reviews the report and either verifies it for
                    investigation or rejects it with a remark.
                </p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="p-4 h-100">
                <div class="display-6 text-primary mb-2"><i class="bi bi-check2-circle"></i></div>
                <h3 class="h5">3. Investigation &amp; Resolution</h3>
                <p class="text-muted small mb-0">
                    The officer updates the investigation status and remarks as the case
                    progresses, while the citizen tracks it in real time.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- ================= ROLES ================= -->
<section class="bg-light border-top border-bottom py-5">
    <div class="container">
        <h2 class="h3 text-center mb-5">Built for Every Role</h2>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body">
                        <i class="bi bi-person-fill text-primary fs-3"></i>
                        <h3 class="h5 mt-2">Citizen</h3>
                        <ul class="small text-muted ps-3 mb-0">
                            <li>Register &amp; manage profile</li>
                            <li>Submit crime reports with evidence</li>
                            <li>Track report status</li>
                            <li>View report history</li>
                            <li>Access Help &amp; Resources</li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body">
                        <i class="bi bi-shield-fill-check text-primary fs-3"></i>
                        <h3 class="h5 mt-2">Police Officer</h3>
                        <ul class="small text-muted ps-3 mb-0">
                            <li>View &amp; search crime reports</li>
                            <li>Verify or reject reports</li>
                            <li>Update investigation status</li>
                            <li>Add investigation remarks</li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body">
                        <i class="bi bi-gear-fill text-primary fs-3"></i>
                        <h3 class="h5 mt-2">Administrator</h3>
                        <ul class="small text-muted ps-3 mb-0">
                            <li>Manage citizen accounts</li>
                            <li>Manage police officer accounts</li>
                            <li>Manage crime categories</li>
                            <li>View all reports &amp; dashboard statistics</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ================= CTA ================= -->
<?php if (!$loggedIn): ?>
<section class="container py-5 text-center">
    <h2 class="h4 mb-3">Ready to report an incident?</h2>
    <p class="text-muted mb-4">It only takes a few minutes to create an account and submit your first report.</p>
    <a href="<?php echo BASE_URL; ?>register.php" class="btn btn-primary btn-lg px-4">
        <i class="bi bi-person-plus-fill me-1"></i> Get Started
    </a>
</section>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>