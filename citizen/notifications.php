<?php
/**
 * citizen/notifications.php
 *
 * Shows all notifications belonging to the logged-in citizen.
 * Notifications that are unread at the time of this page load are
 * shown highlighted, then marked as read (simple, automatic --
 * no extra "mark as read" buttons needed).
 */

require_once __DIR__ . '/../includes/auth.php';
require_role('citizen');

$userId = $_SESSION['user_id'];

/**
 * Professional case reference, e.g. CR-2026-00014
 */
if (!function_exists('format_case_id')) {
    function format_case_id($reportId, $submittedAt) {
        return sprintf('CR-%s-%05d', date('Y', strtotime($submittedAt)), (int) $reportId);
    }
}

// ---- Fetch all notifications for this citizen, newest first ----
$stmt = $pdo->prepare(
    'SELECT n.notification_id, n.report_id, n.message, n.is_read, n.created_at, 
            cr.title AS report_title, cr.submitted_at
     FROM notifications n
     JOIN crime_reports cr ON n.report_id = cr.report_id
     WHERE n.user_id = :uid
     ORDER BY n.created_at DESC'
);
$stmt->execute([':uid' => $userId]);
$notifications = $stmt->fetchAll();

// ---- Mark any currently-unread notifications as read ----
$unreadIds = array_column(array_filter($notifications, fn($n) => (int) $n['is_read'] === 0), 'notification_id');
if (!empty($unreadIds)) {
    $placeholders = implode(',', array_fill(0, count($unreadIds), '?'));
    $updateStmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id IN ($placeholders)");
    $updateStmt->execute($unreadIds);
}

$pageTitle = 'Notifications';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <h1 class="h3 mb-4">Notifications</h1>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <?php if (empty($notifications)): ?>
                        <div class="p-5 text-center text-muted">
                            <i class="bi bi-bell-slash fs-1 d-block mb-2"></i>
                            You don't have any notifications yet.
                        </div>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($notifications as $notification): ?>
                                <?php $wasUnread = (int) $notification['is_read'] === 0; ?>
                                <li class="list-group-item d-flex justify-content-between align-items-start <?php echo $wasUnread ? 'bg-light' : ''; ?>">
                                    <div class="me-3">
                                        <?php if ($wasUnread): ?>
                                            <span class="badge bg-primary me-2">New</span>
                                        <?php endif; ?>
                                        <span class="<?php echo $wasUnread ? 'fw-semibold' : ''; ?>">
                                            <?php echo sanitize_input($notification['message']); ?>
                                        </span>
                                        <div class="small text-muted mt-1">
                                            <?php echo format_date($notification['created_at'], 'M d, Y h:i A'); ?>
                                            &middot;
                                            <a href="<?php echo BASE_URL; ?>citizen/report_details.php?id=<?php echo (int) $notification['report_id']; ?>">
                                                View Case <?php echo sanitize_input(format_case_id($notification['report_id'], $notification['submitted_at'])); ?>
                                            </a>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>