<?php
/**
 * citizen/submit_report.php
 *
 * Lets a logged-in citizen submit a new crime report, optionally with
 * multiple evidence files (JPG/JPEG/PNG/PDF). Uses only the existing
 * crime_reports, crime_categories, and evidence tables.
 */

require_once __DIR__ . '/../includes/auth.php';
require_role('citizen');

$userId = $_SESSION['user_id'];

// Evidence upload rules
const MAX_EVIDENCE_FILES = 5;
const MAX_EVIDENCE_SIZE_BYTES = 5 * 1024 * 1024; // 5 MB per file
const ALLOWED_EVIDENCE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'pdf'];
const EVIDENCE_UPLOAD_DIR = __DIR__ . '/../uploads/evidence/';
const EVIDENCE_WEB_PATH = 'uploads/evidence/';

// ---- Load categories for the dropdown ----
$categories = $pdo->query('SELECT category_id, category_name FROM crime_categories ORDER BY category_name')->fetchAll();

$errors = [];
$title = '';
$description = '';
$incident_location = '';
$incident_date = '';
$category_id = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission. Please refresh the page and try again.';
    }

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $incident_location = trim($_POST['incident_location'] ?? '');
    $incident_date = trim($_POST['incident_date'] ?? '');
    $category_id = trim($_POST['category_id'] ?? '');

    // ---- Validate category ----
    $validCategoryIds = array_column($categories, 'category_id');
    if ($category_id === '' || !in_array((int) $category_id, $validCategoryIds, true)) {
        $errors[] = 'Please select a valid crime category.';
    }

    // ---- Validate text fields ----
    if (mb_strlen($title) < 5 || mb_strlen($title) > 150) {
        $errors[] = 'Title must be between 5 and 150 characters.';
    }
    if (mb_strlen($description) < 20) {
        $errors[] = 'Description must be at least 20 characters so officers have enough detail.';
    }
    if ($incident_location === '' || mb_strlen($incident_location) > 255) {
        $errors[] = 'Please enter a valid incident location.';
    }

    // ---- Validate date ----
    $dateObj = DateTime::createFromFormat('Y-m-d', $incident_date);
    if (!$dateObj || $dateObj->format('Y-m-d') !== $incident_date) {
        $errors[] = 'Please enter a valid incident date.';
    } elseif ($dateObj > new DateTime('today')) {
        $errors[] = 'Incident date cannot be in the future.';
    }

    // ---- Validate evidence files (optional, but if present must be valid) ----
    $uploadedFiles = [];
    $evidenceErrors = [];

    if (isset($_FILES['evidence']) && is_array($_FILES['evidence']['name'])) {
        $fileCount = count(array_filter($_FILES['evidence']['name'], fn($n) => $n !== ''));

        if ($fileCount > MAX_EVIDENCE_FILES) {
            $errors[] = 'You can upload up to ' . MAX_EVIDENCE_FILES . ' evidence files per report.';
        } else {
            for ($i = 0; $i < count($_FILES['evidence']['name']); $i++) {
                if ($_FILES['evidence']['error'][$i] === UPLOAD_ERR_NO_FILE) {
                    continue; // empty slot, evidence is optional
                }

                $singleFile = [
                    'name'     => $_FILES['evidence']['name'][$i],
                    'type'     => $_FILES['evidence']['type'][$i],
                    'tmp_name' => $_FILES['evidence']['tmp_name'][$i],
                    'error'    => $_FILES['evidence']['error'][$i],
                    'size'     => $_FILES['evidence']['size'][$i],
                ];

                $result = validate_file_upload($singleFile, ALLOWED_EVIDENCE_EXTENSIONS, MAX_EVIDENCE_SIZE_BYTES);

                if (!$result['success']) {
                    $evidenceErrors[] = basename($singleFile['name']) . ': ' . $result['message'];
                    continue;
                }

                $uploadedFiles[] = $singleFile;
            }
        }
    }

    // ---- If the main report fields are valid, save the report ----
    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'INSERT INTO crime_reports (user_id, category_id, title, description, incident_location, incident_date)
             VALUES (:user_id, :category_id, :title, :description, :incident_location, :incident_date)'
        );
        $stmt->execute([
            ':user_id'           => $userId,
            ':category_id'       => (int) $category_id,
            ':title'             => $title,
            ':description'       => $description,
            ':incident_location' => $incident_location,
            ':incident_date'     => $incident_date,
        ]);

        $reportId = (int) $pdo->lastInsertId();

        // ---- Store each valid evidence file ----
        if (!is_dir(EVIDENCE_UPLOAD_DIR)) {
            mkdir(EVIDENCE_UPLOAD_DIR, 0755, true);
        }

        foreach ($uploadedFiles as $file) {
            // Never trust the original filename: only its extension is reused.
            $safeFilename = generate_unique_filename($file['name']);
            $destination = EVIDENCE_UPLOAD_DIR . $safeFilename;

            if (move_uploaded_file($file['tmp_name'], $destination)) {
                $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $originalNameForDisplay = mb_substr(basename($file['name']), 0, 255);

                $evidenceStmt = $pdo->prepare(
                    'INSERT INTO evidence (report_id, file_name, file_path, file_type)
                     VALUES (:report_id, :file_name, :file_path, :file_type)'
                );
                $evidenceStmt->execute([
                    ':report_id' => $reportId,
                    ':file_name' => $originalNameForDisplay,
                    ':file_path' => EVIDENCE_WEB_PATH . $safeFilename,
                    ':file_type' => $extension,
                ]);
            } else {
                $evidenceErrors[] = basename($file['name']) . ': could not be saved on the server.';
            }
        }

        // ---- Notify the citizen that the report was received ----
        create_notification(
            $pdo,
            $userId,
            $reportId,
            'Your report "' . $title . '" has been submitted and is pending review.'
        );

        if (!empty($evidenceErrors)) {
            set_flash_message(
                'warning',
                'Your report was submitted successfully, but some evidence files could not be uploaded: '
                . implode('; ', $evidenceErrors)
            );
        } else {
            set_flash_message('success', 'Your crime report has been submitted successfully.');
        }

        redirect('citizen/report_details.php?id=' . $reportId);
    }

    // Main fields had errors: surface evidence errors alongside them too.
    $errors = array_merge($errors, $evidenceErrors);
}

$pageTitle = 'Submit Crime Report';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <h1 class="h3 mb-4">Submit a Crime Report</h1>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0 ps-3">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo sanitize_input($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form method="POST" action="<?php echo BASE_URL; ?>citizen/submit_report.php" enctype="multipart/form-data" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

                        <div class="mb-3">
                            <label for="category_id" class="form-label">Crime Category</label>
                            <select class="form-select" id="category_id" name="category_id" required>
                                <option value="">-- Select a category --</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo (int) $category['category_id']; ?>"
                                        <?php echo ((string) $category['category_id'] === (string) $category_id) ? 'selected' : ''; ?>>
                                        <?php echo sanitize_input($category['category_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="title" class="form-label">Title</label>
                            <input type="text" class="form-control" id="title" name="title" required
                                   maxlength="150" placeholder="Brief summary of the incident"
                                   value="<?php echo sanitize_input($title); ?>">
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="5" required
                                      placeholder="Describe what happened in as much detail as possible"><?php echo sanitize_input($description); ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-7 mb-3">
                                <label for="incident_location" class="form-label">Incident Location</label>
                                <input type="text" class="form-control" id="incident_location" name="incident_location" required
                                       maxlength="255" placeholder="e.g. Street, area, or landmark"
                                       value="<?php echo sanitize_input($incident_location); ?>">
                            </div>
                            <div class="col-md-5 mb-3">
                                <label for="incident_date" class="form-label">Incident Date</label>
                                <input type="date" class="form-control" id="incident_date" name="incident_date" required
                                       max="<?php echo date('Y-m-d'); ?>"
                                       value="<?php echo sanitize_input($incident_date); ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="evidence" class="form-label">
                                Evidence <span class="text-muted">(optional, up to <?php echo MAX_EVIDENCE_FILES; ?> files)</span>
                            </label>
                            <input type="file" class="form-control" id="evidence" name="evidence[]"
                                   accept=".jpg,.jpeg,.png,.pdf" multiple>
                            <div class="form-text">Accepted formats: JPG, JPEG, PNG, PDF. Max 5 MB per file.</div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send-fill me-1"></i> Submit Report
                        </button>
                        <a href="<?php echo BASE_URL; ?>citizen/dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>s