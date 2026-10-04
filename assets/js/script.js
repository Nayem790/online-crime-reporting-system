/**
 * assets/js/script.js
 *
 * Frontend-only enhancements for the Online Crime Reporting and Case
 * Management System. Everything here is a convenience layer on top of
 * server-side logic that already exists in the PHP files -- the
 * server remains the authority on every check performed here
 * (required fields, password match, evidence file type/size/count).
 * Nothing here can be trusted on its own and nothing here replaces
 * a server-side check.
 *
 * No external libraries beyond the Bootstrap bundle already loaded
 * by includes/footer.php.
 */

document.addEventListener('DOMContentLoaded', function () {
    wireFormSubmitHandlers();
    wirePasswordConfirmation();
    wireEvidenceLiveFeedback();
    autoDismissAlerts();
});

/* ============================================================
   1. Unified submit handling for every form marked novalidate
   ------------------------------------------------------------
   Forms in this project use the HTML "novalidate" attribute so the
   browser's default validation bubbles are suppressed in favor of
   Bootstrap's ".was-validated" styling. This single handler, in
   order, per form:
     a) Runs the browser's own constraint validation (required,
        type="email", minlength, pattern, custom validity, etc.)
        and reveals Bootstrap's valid/invalid styling.
     b) If this is the evidence upload form (submit_report.php,
        identified by its unique #evidence file input), re-checks
        the selected files client-side.
     c) If this is the verify/reject form (police/verify_report.php,
        identified by its unique input[name="decision"]), asks for
        confirmation before an irreversible decision is submitted.
     d) If this is the investigation update form
        (police/investigation.php, identified by being a POST form
        with a select[name="status"]), asks for confirmation only
        when the officer is about to close the case ("Resolved").
   Forms not matching any of the above (login, register, profile,
   admin add/edit forms) simply get step (a) and submit normally.
   ============================================================ */
function wireFormSubmitHandlers() {
    var forms = document.querySelectorAll('form[novalidate]');

    forms.forEach(function (form) {
        form.addEventListener('submit', function (event) {

            // (a) Native/Bootstrap validation first
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
                form.classList.add('was-validated');
                return;
            }
            form.classList.add('was-validated');

            // (b) Evidence file checks -- only present on submit_report.php
            var evidenceInput = form.querySelector('#evidence');
            if (evidenceInput && !validateEvidenceFiles(evidenceInput)) {
                event.preventDefault();
                return;
            }

            // (c) Verify / reject confirmation -- only present on verify_report.php
            var decisionInput = form.querySelector('input[name="decision"]:checked');
            if (decisionInput) {
                var actionWord = decisionInput.value === 'Rejected' ? 'reject' : 'verify';
                var confirmed = window.confirm(
                    'Are you sure you want to ' + actionWord + ' this report? This action cannot be undone.'
                );
                if (!confirmed) {
                    event.preventDefault();
                    return;
                }
            }

            // (d) Investigation "Resolved" confirmation -- only present on investigation.php
            var statusSelect = form.querySelector('select[name="status"]');
            if (statusSelect && form.method.toLowerCase() === 'post' && statusSelect.value === 'Resolved') {
                var confirmedResolve = window.confirm(
                    'Mark this case as Resolved? This will close the investigation.'
                );
                if (!confirmedResolve) {
                    event.preventDefault();
                    return;
                }
            }
        });
    });
}

/* ============================================================
   2. Evidence file validation (client-side pre-check only)
   ------------------------------------------------------------
   Mirrors the *rules* already enforced in includes/functions.php's
   validate_file_upload() -- allowed extensions, max size, max file
   count -- purely so the citizen finds out immediately instead of
   after a full page submit. The server still independently checks
   extension, real MIME type (via finfo), and size on every request;
   this client check cannot be trusted and does not replace it.
   ============================================================ */
var EVIDENCE_MAX_FILES = 5;
var EVIDENCE_MAX_SIZE_BYTES = 5 * 1024 * 1024; // 5 MB
var EVIDENCE_ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'pdf'];

function validateEvidenceFiles(input) {
    clearFieldError(input);
    var files = input.files;

    if (!files || files.length === 0) {
        return true; // evidence is optional
    }

    if (files.length > EVIDENCE_MAX_FILES) {
        showFieldError(input, 'You can upload up to ' + EVIDENCE_MAX_FILES + ' evidence files per report.');
        return false;
    }

    for (var i = 0; i < files.length; i++) {
        var file = files[i];
        var parts = file.name.split('.');
        var extension = parts.length > 1 ? parts.pop().toLowerCase() : '';

        if (EVIDENCE_ALLOWED_EXTENSIONS.indexOf(extension) === -1) {
            showFieldError(input, '"' + file.name + '" is not an allowed file type. Use JPG, JPEG, PNG, or PDF.');
            return false;
        }

        if (file.size > EVIDENCE_MAX_SIZE_BYTES) {
            showFieldError(input, '"' + file.name + '" is larger than the 5 MB limit.');
            return false;
        }
    }

    return true;
}

function wireEvidenceLiveFeedback() {
    var evidenceInput = document.getElementById('evidence');
    if (!evidenceInput) {
        return;
    }
    evidenceInput.addEventListener('change', function () {
        validateEvidenceFiles(evidenceInput);
    });
}

/* ============================================================
   3. Live "passwords match" feedback
   ------------------------------------------------------------
   Applies wherever a #confirm_password field exists alongside a
   #password or #new_password field (register.php, and both forms
   on citizen/profile.php). Uses the native setCustomValidity() API
   so a mismatch also correctly fails the Bootstrap validation step
   above -- the server still re-checks the match independently.
   ============================================================ */
function wirePasswordConfirmation() {
    var confirmField = document.getElementById('confirm_password');
    if (!confirmField) {
        return;
    }

    var passwordField = document.getElementById('password') || document.getElementById('new_password');
    if (!passwordField) {
        return;
    }

    function checkMatch() {
        if (confirmField.value === '') {
            confirmField.setCustomValidity('');
            clearFieldError(confirmField);
            return;
        }
        if (confirmField.value !== passwordField.value) {
            confirmField.setCustomValidity('Passwords do not match.');
            showFieldError(confirmField, 'Passwords do not match.');
        } else {
            confirmField.setCustomValidity('');
            clearFieldError(confirmField);
        }
    }

    passwordField.addEventListener('input', checkMatch);
    confirmField.addEventListener('input', checkMatch);
}

/* ============================================================
   4. Small shared helpers for inline field error messages
   ============================================================ */
function showFieldError(input, message) {
    clearFieldError(input);
    input.classList.add('is-invalid');

    var errorEl = document.createElement('div');
    errorEl.className = 'text-danger small mt-1';
    errorEl.setAttribute('data-js-error', 'true');
    errorEl.textContent = message;
    input.insertAdjacentElement('afterend', errorEl);
}

function clearFieldError(input) {
    input.classList.remove('is-invalid');
    var next = input.nextElementSibling;
    if (next && next.hasAttribute('data-js-error')) {
        next.remove();
    }
}

/* ============================================================
   5. Auto-dismiss flash messages after a short delay
   ------------------------------------------------------------
   Uses Bootstrap's own Alert component (already loaded in
   includes/footer.php) -- no custom animation code is added here,
   just triggering the existing dismiss behavior on a timer. The
   manual close button continues to work as before at any time.
   ============================================================ */
function autoDismissAlerts() {
    if (typeof bootstrap === 'undefined' || !bootstrap.Alert) {
        return;
    }

    var alerts = document.querySelectorAll('.alert.alert-dismissible');
    alerts.forEach(function (alertEl) {
        setTimeout(function () {
            var instance = bootstrap.Alert.getOrCreateInstance(alertEl);
            instance.close();
        }, 6000);
    });
}