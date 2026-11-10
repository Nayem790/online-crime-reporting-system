
CREATE DATABASE IF NOT EXISTS crime_reporting_system
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE crime_reporting_system;

-- ------------------------------------------------------------
-- 1. USERS
-- Single table for Citizen / Police / Administrator (role column)
-- reset_token / reset_token_expiry support FR-03 (Forgot Password)
-- without requiring email delivery (out of scope per proposal).
-- ------------------------------------------------------------
CREATE TABLE users (
    user_id             INT AUTO_INCREMENT PRIMARY KEY,
    full_name           VARCHAR(100)     NOT NULL,
    email               VARCHAR(100)     NOT NULL UNIQUE,
    password            VARCHAR(255)     NOT NULL,
    phone               VARCHAR(20)      NULL,
    address             TEXT             NULL,
    role                ENUM('citizen','police','admin') NOT NULL DEFAULT 'citizen',
    reset_token         VARCHAR(64)      NULL,
    reset_token_expiry  DATETIME         NULL,
    created_at          DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 2. CRIME_CATEGORIES
-- ------------------------------------------------------------
CREATE TABLE crime_categories (
    category_id     INT AUTO_INCREMENT PRIMARY KEY,
    category_name   VARCHAR(100)   NOT NULL UNIQUE,
    description     TEXT           NULL,
    created_at      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 3. CRIME_REPORTS  (central entity)
-- status kept as VARCHAR(50) to match the approved ERD's declared
-- type; the allowed set (Pending, Verified, Rejected, Investigating,
-- Resolved) is enforced at the application layer.
-- ------------------------------------------------------------
CREATE TABLE crime_reports (
    report_id             INT AUTO_INCREMENT PRIMARY KEY,
    user_id                INT           NOT NULL,
    category_id            INT           NOT NULL,
    title                   VARCHAR(150)  NOT NULL,
    description             TEXT          NOT NULL,
    incident_location       VARCHAR(255)  NOT NULL,
    incident_date           DATE          NOT NULL,
    status                  VARCHAR(50)   NOT NULL DEFAULT 'Pending',
    verified_by             INT           NULL,
    verified_at             DATETIME      NULL,
    verification_remarks    TEXT          NULL,
    submitted_at            DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_report_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT fk_report_category
        FOREIGN KEY (category_id) REFERENCES crime_categories(category_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,

    CONSTRAINT fk_report_verifier
        FOREIGN KEY (verified_by) REFERENCES users(user_id)
        ON DELETE SET NULL ON UPDATE CASCADE,

    INDEX idx_report_user (user_id),
    INDEX idx_report_category (category_id),
    INDEX idx_report_status (status)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 4. EVIDENCE  (1 report : N evidence files)
-- ------------------------------------------------------------
CREATE TABLE evidence (
    evidence_id     INT AUTO_INCREMENT PRIMARY KEY,
    report_id       INT           NOT NULL,
    file_name       VARCHAR(255)  NOT NULL,
    file_path       VARCHAR(500)  NOT NULL,
    file_type       VARCHAR(50)   NULL,
    uploaded_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_evidence_report
        FOREIGN KEY (report_id) REFERENCES crime_reports(report_id)
        ON DELETE CASCADE ON UPDATE CASCADE,

    INDEX idx_evidence_report (report_id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 5. INVESTIGATIONS  (1 report : N updates, 1 officer : N investigations)
-- ------------------------------------------------------------
CREATE TABLE investigations (
    investigation_id   INT AUTO_INCREMENT PRIMARY KEY,
    report_id          INT           NOT NULL,
    officer_id         INT           NOT NULL,
    status             VARCHAR(50)   NOT NULL,
    remarks            TEXT          NULL,
    updated_at         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                                      ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_investigation_report
        FOREIGN KEY (report_id) REFERENCES crime_reports(report_id)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT fk_investigation_officer
        FOREIGN KEY (officer_id) REFERENCES users(user_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,

    INDEX idx_investigation_report (report_id),
    INDEX idx_investigation_officer (officer_id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 6. NOTIFICATIONS  (1 user : N notifications, 1 report : N notifications)
-- ------------------------------------------------------------
CREATE TABLE notifications (
    notification_id     INT AUTO_INCREMENT PRIMARY KEY,
    user_id              INT           NOT NULL,
    report_id            INT           NOT NULL,
    message              TEXT          NOT NULL,
    is_read              BOOLEAN       NOT NULL DEFAULT FALSE,
    created_at           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_notification_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT fk_notification_report
        FOREIGN KEY (report_id) REFERENCES crime_reports(report_id)
        ON DELETE CASCADE ON UPDATE CASCADE,

    INDEX idx_notification_user (user_id),
    INDEX idx_notification_read (user_id, is_read)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 7. HELP_RESOURCES  (independent entity, no FK relationships)
-- resource_type distinguishes: emergency_contact / safety_guideline / faq
-- ------------------------------------------------------------
CREATE TABLE help_resources (
    resource_id     INT AUTO_INCREMENT PRIMARY KEY,
    title           VARCHAR(150)  NOT NULL,
    resource_type   VARCHAR(50)   NOT NULL,
    content         TEXT          NULL,
    contact_info    VARCHAR(255)  NULL,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- SEED DATA (minimal, so the system is demoable immediately)
-- ============================================================

-- Default administrator account. Password is: Admin@123
-- (bcrypt hash, verifiable with PHP's password_verify())
INSERT INTO users (full_name, email, password, role) VALUES
('System Administrator', 'admin@crms.local',
 '$2b$10$JdTqqO0M0rvdpvXqvZS8SOo6/TZJH06FHARHRFqXeyPBcDNzyo4Vq', 'admin');

-- Starter crime categories
INSERT INTO crime_categories (category_name, description) VALUES
('Theft', 'Unlawful taking of another person''s property'),
('Assault', 'Physical attack or threat of attack on a person'),
('Burglary', 'Illegal entry into a property with intent to commit a crime'),
('Vandalism', 'Deliberate destruction of or damage to property'),
('Fraud', 'Deception intended to result in financial or personal gain'),
('Cybercrime', 'Crimes committed using computers or the internet'),
('Missing Person', 'Report of a person who cannot be located'),
('Other', 'Any incident not covered by the categories above');

-- Starter Help & Resources entries
INSERT INTO help_resources (title, resource_type, content, contact_info) VALUES
('Police Emergency Hotline', 'emergency_contact',
 'For life-threatening emergencies or crimes in progress, call the national police emergency line immediately.',
 '999'),
('Ambulance / Medical Emergency', 'emergency_contact',
 'For medical emergencies related to a crime or accident, contact emergency medical services.',
 '999'),
('How to Stay Safe After Reporting a Crime', 'safety_guideline',
 'Keep your report reference number, avoid confronting suspects directly, and preserve any evidence until an officer reviews your case.',
 NULL),
('Protecting Yourself from Fraud', 'safety_guideline',
 'Never share your bank OTP or password with anyone. Verify the identity of callers before sharing personal information.',
 NULL),
('How long does report verification take?', 'faq',
 'A police officer typically reviews and verifies new reports within a few working days. You can track the status from your dashboard.',
 NULL),
('Can I submit a report anonymously?', 'faq',
 'The current version of the system requires a registered account so officers can follow up and update you on your case.',
 NULL);

 
USE crime_reporting_system;

CREATE TABLE IF NOT EXISTS case_status_history (
    history_id INT AUTO_INCREMENT PRIMARY KEY,

    report_id INT NOT NULL,

    previous_status VARCHAR(50) DEFAULT NULL,

    new_status VARCHAR(50) NOT NULL,

    remarks TEXT DEFAULT NULL,

    changed_by INT DEFAULT NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_history_report
        FOREIGN KEY (report_id)
        REFERENCES crime_reports(report_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_history_user
        FOREIGN KEY (changed_by)
        REFERENCES users(user_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    INDEX idx_history_report (report_id),
    INDEX idx_history_created (created_at)
) ENGINE=InnoDB;


-- ============================================================
-- NOTE ON DEFAULT ADMIN LOGIN
-- Email:    admin@crms.local
-- Password: Admin@123
-- Change this password after first login in a real deployment.
-- ============================================================