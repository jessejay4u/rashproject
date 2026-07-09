-- Health Platform - MySQL Schema
-- Compatible with MySQL 5.7+ and MariaDB 10.3+
-- Run this file first, then seed.sql

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- Regions
-- ============================================================
CREATE TABLE IF NOT EXISTS regions (
    id          CHAR(36)     NOT NULL,
    name        VARCHAR(150) NOT NULL,
    code        VARCHAR(50)  NOT NULL,
    description TEXT,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_regions_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Hospitals
-- ============================================================
CREATE TABLE IF NOT EXISTS hospitals (
    id          CHAR(36)     NOT NULL,
    name        VARCHAR(200) NOT NULL,
    code        VARCHAR(50)  NOT NULL,
    region_id   CHAR(36),
    type        VARCHAR(50),
    address     TEXT,
    phone       VARCHAR(50),
    email       VARCHAR(150),
    latitude    DECIMAL(10,7),
    longitude   DECIMAL(10,7),
    bed_count   INT          DEFAULT 0,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_hospitals_code (code),
    KEY idx_hospitals_region (region_id),
    CONSTRAINT fk_hospitals_region FOREIGN KEY (region_id) REFERENCES regions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Roles
-- ============================================================
CREATE TABLE IF NOT EXISTS roles (
    id          CHAR(36)     NOT NULL,
    name        VARCHAR(50)  NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_roles_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Permissions
-- ============================================================
CREATE TABLE IF NOT EXISTS permissions (
    id           CHAR(36)     NOT NULL,
    name         VARCHAR(100) NOT NULL,
    display_name VARCHAR(150) NOT NULL,
    module       VARCHAR(50),
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_permissions_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Role Permissions
-- ============================================================
CREATE TABLE IF NOT EXISTS role_permissions (
    role_id       CHAR(36) NOT NULL,
    permission_id CHAR(36) NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_rp_role       FOREIGN KEY (role_id)       REFERENCES roles(id)       ON DELETE CASCADE,
    CONSTRAINT fk_rp_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Users
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id             CHAR(36)     NOT NULL,
    name           VARCHAR(150) NOT NULL,
    email          VARCHAR(150) NOT NULL,
    password_hash  VARCHAR(255) NOT NULL,
    role           VARCHAR(50)  NOT NULL DEFAULT 'viewer',
    hospital_id    CHAR(36),
    region_id      CHAR(36),
    phone          VARCHAR(50),
    is_active      TINYINT(1)   NOT NULL DEFAULT 1,
    last_login_at  DATETIME,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_hospital (hospital_id),
    KEY idx_users_region (region_id),
    CONSTRAINT fk_users_hospital FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE SET NULL,
    CONSTRAINT fk_users_region   FOREIGN KEY (region_id)   REFERENCES regions(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Forms
-- ============================================================
CREATE TABLE IF NOT EXISTS forms (
    id            CHAR(36)     NOT NULL,
    name          VARCHAR(200) NOT NULL,
    code          VARCHAR(100) NOT NULL,
    description   TEXT,
    category      VARCHAR(100),
    status        VARCHAR(20)  NOT NULL DEFAULT 'draft',
    version       INT          NOT NULL DEFAULT 1,
    is_recurring  TINYINT(1)   NOT NULL DEFAULT 0,
    published_at  DATETIME,
    created_by    CHAR(36),
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_forms_code (code),
    KEY idx_forms_status (status),
    CONSTRAINT fk_forms_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Form Sections
-- ============================================================
CREATE TABLE IF NOT EXISTS form_sections (
    id           CHAR(36)     NOT NULL,
    form_id      CHAR(36)     NOT NULL,
    title        VARCHAR(200) NOT NULL,
    description  TEXT,
    sort_order   INT          NOT NULL DEFAULT 0,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_form_sections_form (form_id),
    CONSTRAINT fk_fs_form FOREIGN KEY (form_id) REFERENCES forms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Form Fields
-- ============================================================
CREATE TABLE IF NOT EXISTS form_fields (
    id           CHAR(36)     NOT NULL,
    section_id   CHAR(36)     NOT NULL,
    form_id      CHAR(36)     NOT NULL,
    name         VARCHAR(100) NOT NULL,
    label        VARCHAR(200) NOT NULL,
    field_type   VARCHAR(50)  NOT NULL DEFAULT 'text',
    is_required  TINYINT(1)   NOT NULL DEFAULT 0,
    options      JSON,
    validation   JSON,
    help_text    TEXT,
    sort_order   INT          NOT NULL DEFAULT 0,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_form_fields_section (section_id),
    KEY idx_form_fields_form (form_id),
    CONSTRAINT fk_ff_section FOREIGN KEY (section_id) REFERENCES form_sections(id) ON DELETE CASCADE,
    CONSTRAINT fk_ff_form    FOREIGN KEY (form_id)    REFERENCES forms(id)          ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Submissions
-- ============================================================
CREATE TABLE IF NOT EXISTS submissions (
    id              CHAR(36)    NOT NULL,
    form_id         CHAR(36)    NOT NULL,
    hospital_id     CHAR(36)    NOT NULL,
    submitted_by    CHAR(36)    NOT NULL,
    status          VARCHAR(20) NOT NULL DEFAULT 'draft',
    period_start    DATE,
    period_end      DATE,
    submitted_at    DATETIME,
    reviewed_by     CHAR(36),
    reviewed_at     DATETIME,
    review_notes    TEXT,
    created_at      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_submissions_form     (form_id),
    KEY idx_submissions_hospital (hospital_id),
    KEY idx_submissions_user     (submitted_by),
    KEY idx_submissions_status   (status),
    CONSTRAINT fk_sub_form       FOREIGN KEY (form_id)      REFERENCES forms(id)      ON DELETE RESTRICT,
    CONSTRAINT fk_sub_hospital   FOREIGN KEY (hospital_id)  REFERENCES hospitals(id)  ON DELETE RESTRICT,
    CONSTRAINT fk_sub_user       FOREIGN KEY (submitted_by) REFERENCES users(id)      ON DELETE RESTRICT,
    CONSTRAINT fk_sub_reviewer   FOREIGN KEY (reviewed_by)  REFERENCES users(id)      ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Submission Values
-- ============================================================
CREATE TABLE IF NOT EXISTS submission_values (
    id            CHAR(36)  NOT NULL,
    submission_id CHAR(36)  NOT NULL,
    field_id      CHAR(36)  NOT NULL,
    value_text    TEXT,
    value_number  DECIMAL(20,6),
    value_boolean TINYINT(1),
    value_date    DATE,
    created_at    DATETIME  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME  NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_sv_submission (submission_id),
    KEY idx_sv_field      (field_id),
    CONSTRAINT fk_sv_submission FOREIGN KEY (submission_id) REFERENCES submissions(id)  ON DELETE CASCADE,
    CONSTRAINT fk_sv_field      FOREIGN KEY (field_id)      REFERENCES form_fields(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Audit Log
-- ============================================================
CREATE TABLE IF NOT EXISTS audit_logs (
    id          CHAR(36)     NOT NULL,
    user_id     CHAR(36),
    action      VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100),
    entity_id   CHAR(36),
    old_values  JSON,
    new_values  JSON,
    ip_address  VARCHAR(45),
    user_agent  VARCHAR(255),
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_user   (user_id),
    KEY idx_audit_entity (entity_type, entity_id),
    KEY idx_audit_time   (created_at),
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
