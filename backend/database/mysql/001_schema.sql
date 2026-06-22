-- ============================================================
-- MySQL-compatible schema (for shared hosting / XAMPP / cPanel)
-- ============================================================

SET FOREIGN_KEY_CHECKS=0;
SET NAMES utf8mb4;

CREATE TABLE regions (
    id          CHAR(36)     NOT NULL,
    name        VARCHAR(150) NOT NULL,
    code        VARCHAR(20)  NOT NULL,
    description TEXT,
    parent_id   CHAR(36)     DEFAULT NULL,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_regions_code (code),
    KEY idx_regions_parent (parent_id),
    CONSTRAINT fk_regions_parent FOREIGN KEY (parent_id) REFERENCES regions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE hospitals (
    id              CHAR(36)       NOT NULL,
    region_id       CHAR(36)       NOT NULL,
    name            VARCHAR(200)   NOT NULL,
    code            VARCHAR(30)    NOT NULL,
    type            VARCHAR(50)    NOT NULL DEFAULT 'general',
    level           SMALLINT       NOT NULL DEFAULT 1,
    address         TEXT,
    phone           VARCHAR(30),
    email           VARCHAR(150),
    latitude        DECIMAL(10,7),
    longitude       DECIMAL(10,7),
    capacity_beds   INT,
    is_active       TINYINT(1)     NOT NULL DEFAULT 1,
    metadata        JSON,
    created_at      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_hospitals_code (code),
    KEY idx_hospitals_region (region_id),
    KEY idx_hospitals_active (is_active),
    CONSTRAINT fk_hospitals_region FOREIGN KEY (region_id) REFERENCES regions(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE roles (
    id           CHAR(36)     NOT NULL,
    name         VARCHAR(50)  NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    description  TEXT,
    is_system    TINYINT(1)   NOT NULL DEFAULT 0,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_roles_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permissions (
    id           CHAR(36)     NOT NULL,
    name         VARCHAR(100) NOT NULL,
    display_name VARCHAR(150) NOT NULL,
    module       VARCHAR(50)  NOT NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_permissions_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role_permissions (
    role_id       CHAR(36) NOT NULL,
    permission_id CHAR(36) NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_rp_role FOREIGN KEY (role_id)       REFERENCES roles(id)       ON DELETE CASCADE,
    CONSTRAINT fk_rp_perm FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE users (
    id                    CHAR(36)     NOT NULL,
    hospital_id           CHAR(36)     DEFAULT NULL,
    region_id             CHAR(36)     DEFAULT NULL,
    role_id               CHAR(36)     NOT NULL,
    name                  VARCHAR(150) NOT NULL,
    email                 VARCHAR(200) NOT NULL,
    password_hash         VARCHAR(255) NOT NULL,
    phone                 VARCHAR(30),
    avatar_url            VARCHAR(500),
    is_active             TINYINT(1)   NOT NULL DEFAULT 1,
    mfa_enabled           TINYINT(1)   NOT NULL DEFAULT 0,
    mfa_secret            VARCHAR(100),
    mfa_backup_codes      JSON,
    last_login_at         DATETIME,
    last_login_ip         VARCHAR(45),
    password_changed_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    must_change_password  TINYINT(1)   NOT NULL DEFAULT 0,
    failed_login_attempts SMALLINT     NOT NULL DEFAULT 0,
    locked_until          DATETIME,
    email_verified_at     DATETIME,
    preferences           JSON,
    created_at            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_hospital (hospital_id),
    KEY idx_users_region   (region_id),
    KEY idx_users_role     (role_id),
    KEY idx_users_active   (is_active),
    CONSTRAINT fk_users_hospital FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE SET NULL,
    CONSTRAINT fk_users_region   FOREIGN KEY (region_id)   REFERENCES regions(id)   ON DELETE SET NULL,
    CONSTRAINT fk_users_role     FOREIGN KEY (role_id)     REFERENCES roles(id)     ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE refresh_tokens (
    id          CHAR(36)     NOT NULL,
    user_id     CHAR(36)     NOT NULL,
    token_hash  VARCHAR(255) NOT NULL,
    device_info JSON,
    ip_address  VARCHAR(45),
    expires_at  DATETIME     NOT NULL,
    revoked_at  DATETIME,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rt_hash (token_hash),
    KEY idx_rt_user    (user_id),
    KEY idx_rt_expires (expires_at),
    CONSTRAINT fk_rt_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE password_resets (
    id         CHAR(36)     NOT NULL,
    email      VARCHAR(200) NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME     NOT NULL,
    used_at    DATETIME,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pr_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE forms (
    id              CHAR(36)     NOT NULL,
    created_by      CHAR(36)     NOT NULL,
    name            VARCHAR(200) NOT NULL,
    code            VARCHAR(50)  NOT NULL,
    description     TEXT,
    category        VARCHAR(100),
    version         SMALLINT     NOT NULL DEFAULT 1,
    status          VARCHAR(20)  NOT NULL DEFAULT 'draft',
    is_recurring    TINYINT(1)   NOT NULL DEFAULT 0,
    recurrence_type VARCHAR(20),
    settings        JSON,
    published_at    DATETIME,
    archived_at     DATETIME,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_forms_code (code),
    KEY idx_forms_status   (status),
    KEY idx_forms_category (category),
    CONSTRAINT fk_forms_user FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE form_sections (
    id            CHAR(36)     NOT NULL,
    form_id       CHAR(36)     NOT NULL,
    title         VARCHAR(200) NOT NULL,
    description   TEXT,
    order_index   SMALLINT     NOT NULL DEFAULT 0,
    is_repeatable TINYINT(1)   NOT NULL DEFAULT 0,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_fs_form (form_id),
    CONSTRAINT fk_fs_form FOREIGN KEY (form_id) REFERENCES forms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE form_fields (
    id            CHAR(36)     NOT NULL,
    section_id    CHAR(36)     NOT NULL,
    form_id       CHAR(36)     NOT NULL,
    name          VARCHAR(100) NOT NULL,
    label         VARCHAR(300) NOT NULL,
    field_type    VARCHAR(30)  NOT NULL,
    is_required   TINYINT(1)   NOT NULL DEFAULT 0,
    order_index   SMALLINT     NOT NULL DEFAULT 0,
    placeholder   VARCHAR(300),
    help_text     TEXT,
    default_value TEXT,
    options       JSON,
    validation    JSON,
    conditions    JSON,
    metadata      JSON,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ff_section (section_id),
    KEY idx_ff_form    (form_id),
    CONSTRAINT fk_ff_section FOREIGN KEY (section_id) REFERENCES form_sections(id) ON DELETE CASCADE,
    CONSTRAINT fk_ff_form    FOREIGN KEY (form_id)    REFERENCES forms(id)          ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE submissions (
    id               CHAR(36)    NOT NULL,
    form_id          CHAR(36)    NOT NULL,
    form_version     SMALLINT    NOT NULL,
    hospital_id      CHAR(36)    NOT NULL,
    submitted_by     CHAR(36)    NOT NULL,
    status           VARCHAR(20) NOT NULL DEFAULT 'draft',
    period_start     DATE,
    period_end       DATE,
    submitted_at     DATETIME,
    reviewed_by      CHAR(36),
    reviewed_at      DATETIME,
    review_notes     TEXT,
    latitude         DECIMAL(10,7),
    longitude        DECIMAL(10,7),
    device_id        VARCHAR(100),
    device_info      JSON,
    sync_status      VARCHAR(20) NOT NULL DEFAULT 'synced',
    local_id         VARCHAR(100),
    duration_seconds INT,
    metadata         JSON,
    created_at       DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_sub_form         (form_id),
    KEY idx_sub_hospital     (hospital_id),
    KEY idx_sub_user         (submitted_by),
    KEY idx_sub_status       (status),
    KEY idx_sub_period       (period_start, period_end),
    KEY idx_sub_submitted_at (submitted_at),
    KEY idx_sub_local_id     (local_id),
    CONSTRAINT fk_sub_form     FOREIGN KEY (form_id)      REFERENCES forms(id)      ON DELETE RESTRICT,
    CONSTRAINT fk_sub_hospital FOREIGN KEY (hospital_id)  REFERENCES hospitals(id)  ON DELETE RESTRICT,
    CONSTRAINT fk_sub_user     FOREIGN KEY (submitted_by) REFERENCES users(id)      ON DELETE RESTRICT,
    CONSTRAINT fk_sub_reviewer FOREIGN KEY (reviewed_by)  REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE submission_values (
    id            CHAR(36)       NOT NULL,
    submission_id CHAR(36)       NOT NULL,
    field_id      CHAR(36)       NOT NULL,
    field_name    VARCHAR(100)   NOT NULL,
    value_text    TEXT,
    value_number  DECIMAL(20,6),
    value_date    DATE,
    value_boolean TINYINT(1),
    value_json    JSON,
    created_at    DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_sv_submission (submission_id),
    KEY idx_sv_field      (field_id),
    KEY idx_sv_name       (field_name),
    CONSTRAINT fk_sv_sub   FOREIGN KEY (submission_id) REFERENCES submissions(id)  ON DELETE CASCADE,
    CONSTRAINT fk_sv_field FOREIGN KEY (field_id)      REFERENCES form_fields(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE submission_attachments (
    id            CHAR(36)     NOT NULL,
    submission_id CHAR(36)     NOT NULL,
    field_id      CHAR(36),
    file_name     VARCHAR(300) NOT NULL,
    file_type     VARCHAR(100) NOT NULL,
    file_size     INT          NOT NULL,
    storage_path  VARCHAR(500) NOT NULL,
    thumbnail_path VARCHAR(500),
    checksum      VARCHAR(64),
    is_public     TINYINT(1)   NOT NULL DEFAULT 0,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_sa_submission (submission_id),
    CONSTRAINT fk_sa_sub   FOREIGN KEY (submission_id) REFERENCES submissions(id)  ON DELETE CASCADE,
    CONSTRAINT fk_sa_field FOREIGN KEY (field_id)      REFERENCES form_fields(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE audit_logs (
    id          BIGINT       NOT NULL AUTO_INCREMENT,
    user_id     CHAR(36),
    user_email  VARCHAR(200),
    action      VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100),
    entity_id   VARCHAR(100),
    old_values  JSON,
    new_values  JSON,
    ip_address  VARCHAR(45),
    user_agent  TEXT,
    metadata    JSON,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_user    (user_id),
    KEY idx_audit_action  (action),
    KEY idx_audit_entity  (entity_type, entity_id),
    KEY idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE notifications (
    id         CHAR(36)     NOT NULL,
    user_id    CHAR(36)     NOT NULL,
    type       VARCHAR(50)  NOT NULL,
    title      VARCHAR(300) NOT NULL,
    message    TEXT         NOT NULL,
    data       JSON,
    is_read    TINYINT(1)   NOT NULL DEFAULT 0,
    read_at    DATETIME,
    sent_email TINYINT(1)   NOT NULL DEFAULT 0,
    sent_push  TINYINT(1)   NOT NULL DEFAULT 0,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notif_user (user_id),
    KEY idx_notif_read (user_id, is_read),
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE indicators (
    id              CHAR(36)       NOT NULL,
    created_by      CHAR(36)       NOT NULL,
    name            VARCHAR(200)   NOT NULL,
    code            VARCHAR(50)    NOT NULL,
    description     TEXT,
    category        VARCHAR(100),
    unit            VARCHAR(50),
    formula         TEXT,
    target_value    DECIMAL(20,6),
    target_period   VARCHAR(20),
    data_source     VARCHAR(100),
    source_form_id  CHAR(36),
    source_field_id CHAR(36),
    aggregation     VARCHAR(20)    DEFAULT 'sum',
    is_active       TINYINT(1)     NOT NULL DEFAULT 1,
    created_at      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_indicators_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE indicator_values (
    id           CHAR(36)     NOT NULL,
    indicator_id CHAR(36)     NOT NULL,
    hospital_id  CHAR(36),
    region_id    CHAR(36),
    period_start DATE         NOT NULL,
    period_end   DATE         NOT NULL,
    value        DECIMAL(20,6) NOT NULL,
    computed_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_iv_indicator (indicator_id),
    KEY idx_iv_hospital  (hospital_id),
    KEY idx_iv_period    (period_start, period_end)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE reports (
    id           CHAR(36)    NOT NULL,
    created_by   CHAR(36)    NOT NULL,
    name         VARCHAR(200) NOT NULL,
    type         VARCHAR(50) NOT NULL,
    parameters   JSON,
    status       VARCHAR(20) NOT NULL DEFAULT 'pending',
    file_path    VARCHAR(500),
    file_format  VARCHAR(10),
    file_size    INT,
    error_message TEXT,
    expires_at   DATETIME,
    generated_at DATETIME,
    created_at   DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_reports_user   (created_by),
    KEY idx_reports_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE job_batches (
    id           CHAR(36)    NOT NULL,
    name         VARCHAR(200) NOT NULL,
    total_jobs   INT         NOT NULL DEFAULT 0,
    pending_jobs INT         NOT NULL DEFAULT 0,
    failed_jobs  INT         NOT NULL DEFAULT 0,
    options      JSON,
    created_at   DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    cancelled_at DATETIME,
    finished_at  DATETIME,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS=1;
