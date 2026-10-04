<?php
/**
 * citizen/help.php
 *
 * Displays Help & Resources content: emergency contacts, safety
 * guidelines, and FAQ. All content comes from the existing
 * help_resources table (resource_type distinguishes the three kinds).
 */

require_once __DIR__ . '/../includes/auth.php';
require_role('citizen');

$stmt = $pdo->prepare('SELECT title, content, contact_info FROM help_resources WHERE resource_type = :type ORDER BY resource_id');

$stmt->execute([':type' => 'emergency_contact']);
$emergencyContacts = $stmt->fetchAll();

$stmt->execute([':type' => 'safety_guideline']);
$safetyGuidelines = $stmt->fetchAll();

$stmt->execute([':type' => 'faq']);
$faqs = $stmt->fetchAll();

$pageTitle = 'Help & Resources';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <h1 class="h3 mb-4">Help &amp; Resources</h1>

    <div class="row g-4">
        <div class="col-lg-5">

            <!-- ================= EMERGENCY CONTACTS ================= -->
            <div class="card border-0 border-danger shadow-sm mb-4">
                <div class="card-header bg-danger text-white fw-semibold">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i> Emergency Contacts
                </div>
                <div class="card-body">
                    <?php if (empty($emergencyContacts)): ?>
                        <p class="text-muted mb-0">No emergency contacts are available at this time.</p>
                    <?php else: ?>
                        <ul class="list-unstyled mb-0">
                            <?php foreach ($emergencyContacts as $index => $contact): ?>
                                <li class="<?php echo $index > 0 ? 'border-top pt-3 mt-3' : ''; ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <strong><?php echo sanitize_input($contact['title']); ?></strong>
                                        <?php if (!empty($contact['contact_info'])): ?>
                                            <span class="badge bg-danger fs-6"><?php echo sanitize_input($contact['contact_info']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($contact['content'])): ?>
                                        <p class="text-muted small mb-0 mt-1"><?php echo sanitize_input($contact['content']); ?></p>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ================= SAFETY GUIDELINES ================= -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-shield-check me-1"></i> Safety Guidelines
                </div>
                <div class="card-body">
                    <?php if (empty($safetyGuidelines)): ?>
                        <p class="text-muted mb-0">No safety guidelines are available at this time.</p>
                    <?php else: ?>
                        <?php foreach ($safetyGuidelines as $index => $guideline): ?>
                            <div class="<?php echo $index > 0 ? 'border-top pt-3 mt-3' : ''; ?>">
                                <h2 class="h6 mb-1"><?php echo sanitize_input($guideline['title']); ?></h2>
                                <p class="text-muted small mb-0"><?php echo sanitize_input($guideline['content']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- ================= FAQ ================= -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-question-circle me-1"></i> Frequently Asked Questions
                </div>
                <div class="card-body">
                    <?php if (empty($faqs)): ?>
                        <p class="text-muted mb-0">No FAQs are available at this time.</p>
                    <?php else: ?>
                        <div class="accordion" id="faqAccordion">
                            <?php foreach ($faqs as $index => $faq): ?>
                                <?php $collapseId = 'faq' . $index; ?>
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button <?php echo $index > 0 ? 'collapsed' : ''; ?>" type="button"
                                                data-bs-toggle="collapse" data-bs-target="#<?php echo $collapseId; ?>">
                                            <?php echo sanitize_input($faq['title']); ?>
                                        </button>
                                    </h2>
                                    <div id="<?php echo $collapseId; ?>"
                                         class="accordion-collapse collapse <?php echo $index === 0 ? 'show' : ''; ?>"
                                         data-bs-parent="#faqAccordion">
                                        <div class="accordion-body text-muted">
                                            <?php echo sanitize_input($faq['content']); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>