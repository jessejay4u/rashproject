-- ============================================================
-- Digital Health Information Management Platform
-- Initial Database Schema
-- ============================================================

-- Enable UUID extension
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "postgis";
CREATE EXTENSION IF NOT EXISTS "pg_trgm";

-- ============================================================
-- REGIONS
-- ============================================================
CREATE TABLE regions (
    id          UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    name        VARCHAR(150) NOT NULL,
    code        VARCHAR(20) UNIQUE NOT NULL,
    description TEXT,
    parent_id   UUID REFERENCES regions(id) ON DELETE SET NULL,
    is_active   BOOLEAN NOT NULL DEFAULT TRUE,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_regions_parent ON regions(parent_id);
CREATE INDEX idx_regions_code ON regions(code);

-- ============================================================
-- HOSPITALS
-- ============================================================
CREATE TABLE hospitals (
    id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    region_id       UUID NOT NULL REFERENCES regions(id) ON DELETE RESTRICT,
    name            VARCHAR(200) NOT NULL,
    code            VARCHAR(30) UNIQUE NOT NULL,
    type            VARCHAR(50) NOT NULL DEFAULT 'general',  -- general, specialized, clinic, health_post
    level           SMALLINT NOT NULL DEFAULT 1,             -- 1-5 facility level
    address         TEXT,
    phone           VARCHAR(30),
    email           VARCHAR(150),
    latitude        DECIMAL(10, 7),
    longitude       DECIMAL(10, 7),
    location        GEOGRAPHY(POINT, 4326),
    capacity_beds   INTEGER,
    is_active       BOOLEAN NOT NULL DEFAULT TRUE,
    metadata        JSONB DEFAULT '{}',
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_hospitals_region ON hospitals(region_id);
CREATE INDEX idx_hospitals_code ON hospitals(code);
CREATE INDEX idx_hospitals_active ON hospitals(is_active);
CREATE INDEX idx_hospitals_location ON hospitals USING GIST(location);

-- ============================================================
-- ROLES & PERMISSIONS (RBAC)
-- ============================================================
CREATE TABLE roles (
    id          UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    name        VARCHAR(50) UNIQUE NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    description TEXT,
    is_system   BOOLEAN NOT NULL DEFAULT FALSE,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE permissions (
    id          UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    name        VARCHAR(100) UNIQUE NOT NULL,  -- e.g. "submissions.create"
    display_name VARCHAR(150) NOT NULL,
    module      VARCHAR(50) NOT NULL,          -- e.g. "submissions"
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE role_permissions (
    role_id       UUID NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
    permission_id UUID NOT NULL REFERENCES permissions(id) ON DELETE CASCADE,
    PRIMARY KEY (role_id, permission_id)
);

-- ============================================================
-- USERS
-- ============================================================
CREATE TABLE users (
    id                    UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    hospital_id           UUID REFERENCES hospitals(id) ON DELETE SET NULL,
    region_id             UUID REFERENCES regions(id) ON DELETE SET NULL,
    role_id               UUID NOT NULL REFERENCES roles(id) ON DELETE RESTRICT,
    name                  VARCHAR(150) NOT NULL,
    email                 VARCHAR(200) UNIQUE NOT NULL,
    password_hash         VARCHAR(255) NOT NULL,  -- argon2id
    phone                 VARCHAR(30),
    avatar_url            VARCHAR(500),
    is_active             BOOLEAN NOT NULL DEFAULT TRUE,
    mfa_enabled           BOOLEAN NOT NULL DEFAULT FALSE,
    mfa_secret            VARCHAR(100),          -- TOTP secret (encrypted)
    mfa_backup_codes      TEXT[],                -- encrypted backup codes
    last_login_at         TIMESTAMPTZ,
    last_login_ip         INET,
    password_changed_at   TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    must_change_password  BOOLEAN NOT NULL DEFAULT FALSE,
    failed_login_attempts SMALLINT NOT NULL DEFAULT 0,
    locked_until          TIMESTAMPTZ,
    email_verified_at     TIMESTAMPTZ,
    preferences           JSONB DEFAULT '{}',
    created_at            TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at            TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_hospital ON users(hospital_id);
CREATE INDEX idx_users_region ON users(region_id);
CREATE INDEX idx_users_role ON users(role_id);
CREATE INDEX idx_users_active ON users(is_active);

-- ============================================================
-- AUTH TOKENS
-- ============================================================
CREATE TABLE refresh_tokens (
    id          UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id     UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token_hash  VARCHAR(255) NOT NULL UNIQUE,  -- SHA-256 of actual token
    device_info JSONB DEFAULT '{}',
    ip_address  INET,
    expires_at  TIMESTAMPTZ NOT NULL,
    revoked_at  TIMESTAMPTZ,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_refresh_tokens_user ON refresh_tokens(user_id);
CREATE INDEX idx_refresh_tokens_expires ON refresh_tokens(expires_at);

CREATE TABLE password_resets (
    id          UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    email       VARCHAR(200) NOT NULL,
    token_hash  VARCHAR(255) NOT NULL,
    expires_at  TIMESTAMPTZ NOT NULL,
    used_at     TIMESTAMPTZ,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_password_resets_email ON password_resets(email);

-- ============================================================
-- FORMS (Dynamic Form Builder)
-- ============================================================
CREATE TABLE forms (
    id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    created_by      UUID NOT NULL REFERENCES users(id),
    name            VARCHAR(200) NOT NULL,
    code            VARCHAR(50) UNIQUE NOT NULL,
    description     TEXT,
    category        VARCHAR(100),   -- e.g. immunization, maternal, outreach
    version         SMALLINT NOT NULL DEFAULT 1,
    status          VARCHAR(20) NOT NULL DEFAULT 'draft',  -- draft, published, archived
    is_recurring    BOOLEAN NOT NULL DEFAULT FALSE,
    recurrence_type VARCHAR(20),    -- daily, weekly, monthly
    settings        JSONB DEFAULT '{}',  -- form-level config (offline_capable, gps_required, etc.)
    published_at    TIMESTAMPTZ,
    archived_at     TIMESTAMPTZ,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_forms_status ON forms(status);
CREATE INDEX idx_forms_category ON forms(category);
CREATE INDEX idx_forms_code ON forms(code);

CREATE TABLE form_sections (
    id          UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    form_id     UUID NOT NULL REFERENCES forms(id) ON DELETE CASCADE,
    title       VARCHAR(200) NOT NULL,
    description TEXT,
    order_index SMALLINT NOT NULL DEFAULT 0,
    is_repeatable BOOLEAN NOT NULL DEFAULT FALSE,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_form_sections_form ON form_sections(form_id);

CREATE TABLE form_fields (
    id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    section_id      UUID NOT NULL REFERENCES form_sections(id) ON DELETE CASCADE,
    form_id         UUID NOT NULL REFERENCES forms(id) ON DELETE CASCADE,
    name            VARCHAR(100) NOT NULL,  -- machine name
    label           VARCHAR(300) NOT NULL,
    field_type      VARCHAR(30) NOT NULL,  -- text, number, date, select, multiselect, radio, checkbox, file, gps, photo, textarea, calculated
    is_required     BOOLEAN NOT NULL DEFAULT FALSE,
    order_index     SMALLINT NOT NULL DEFAULT 0,
    placeholder     VARCHAR(300),
    help_text       TEXT,
    default_value   TEXT,
    options         JSONB DEFAULT '[]',     -- for select/radio: [{value, label}]
    validation      JSONB DEFAULT '{}',     -- {min, max, minLength, maxLength, pattern, custom}
    conditions      JSONB DEFAULT '[]',     -- [{field, operator, value, action}] conditional logic
    metadata        JSONB DEFAULT '{}',
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_form_fields_section ON form_fields(section_id);
CREATE INDEX idx_form_fields_form ON form_fields(form_id);

-- ============================================================
-- SUBMISSIONS
-- ============================================================
CREATE TABLE submissions (
    id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    form_id         UUID NOT NULL REFERENCES forms(id) ON DELETE RESTRICT,
    form_version    SMALLINT NOT NULL,
    hospital_id     UUID NOT NULL REFERENCES hospitals(id) ON DELETE RESTRICT,
    submitted_by    UUID NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    status          VARCHAR(20) NOT NULL DEFAULT 'draft',  -- draft, submitted, reviewed, approved, rejected
    period_start    DATE,
    period_end      DATE,
    submitted_at    TIMESTAMPTZ,
    reviewed_by     UUID REFERENCES users(id),
    reviewed_at     TIMESTAMPTZ,
    review_notes    TEXT,
    latitude        DECIMAL(10, 7),
    longitude       DECIMAL(10, 7),
    location        GEOGRAPHY(POINT, 4326),
    device_id       VARCHAR(100),
    device_info     JSONB DEFAULT '{}',
    sync_status     VARCHAR(20) NOT NULL DEFAULT 'synced',  -- synced, pending, conflict
    local_id        VARCHAR(100),   -- client-side UUID for offline sync
    duration_seconds INTEGER,       -- time to fill form
    metadata        JSONB DEFAULT '{}',
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_submissions_form ON submissions(form_id);
CREATE INDEX idx_submissions_hospital ON submissions(hospital_id);
CREATE INDEX idx_submissions_user ON submissions(submitted_by);
CREATE INDEX idx_submissions_status ON submissions(status);
CREATE INDEX idx_submissions_period ON submissions(period_start, period_end);
CREATE INDEX idx_submissions_submitted_at ON submissions(submitted_at);
CREATE INDEX idx_submissions_location ON submissions USING GIST(location);
CREATE INDEX idx_submissions_local_id ON submissions(local_id);

CREATE TABLE submission_values (
    id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    submission_id   UUID NOT NULL REFERENCES submissions(id) ON DELETE CASCADE,
    field_id        UUID NOT NULL REFERENCES form_fields(id) ON DELETE RESTRICT,
    field_name      VARCHAR(100) NOT NULL,  -- denormalized for query performance
    value_text      TEXT,
    value_number    DECIMAL(20, 6),
    value_date      DATE,
    value_boolean   BOOLEAN,
    value_json      JSONB,                  -- for multi-select, gps objects
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_submission_values_submission ON submission_values(submission_id);
CREATE INDEX idx_submission_values_field ON submission_values(field_id);
CREATE INDEX idx_submission_values_name ON submission_values(field_name);

CREATE TABLE submission_attachments (
    id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    submission_id   UUID NOT NULL REFERENCES submissions(id) ON DELETE CASCADE,
    field_id        UUID REFERENCES form_fields(id) ON DELETE SET NULL,
    file_name       VARCHAR(300) NOT NULL,
    file_type       VARCHAR(100) NOT NULL,
    file_size       INTEGER NOT NULL,
    storage_path    VARCHAR(500) NOT NULL,
    thumbnail_path  VARCHAR(500),
    checksum        VARCHAR(64),   -- SHA-256
    is_public       BOOLEAN NOT NULL DEFAULT FALSE,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_submission_attachments_submission ON submission_attachments(submission_id);

-- ============================================================
-- AUDIT LOG
-- ============================================================
CREATE TABLE audit_logs (
    id          BIGSERIAL PRIMARY KEY,
    user_id     UUID REFERENCES users(id) ON DELETE SET NULL,
    user_email  VARCHAR(200),    -- denormalized in case user deleted
    action      VARCHAR(100) NOT NULL,  -- e.g. "submission.create"
    entity_type VARCHAR(100),
    entity_id   VARCHAR(100),
    old_values  JSONB,
    new_values  JSONB,
    ip_address  INET,
    user_agent  TEXT,
    metadata    JSONB DEFAULT '{}',
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_audit_logs_user ON audit_logs(user_id);
CREATE INDEX idx_audit_logs_action ON audit_logs(action);
CREATE INDEX idx_audit_logs_entity ON audit_logs(entity_type, entity_id);
CREATE INDEX idx_audit_logs_created ON audit_logs(created_at);

-- ============================================================
-- NOTIFICATIONS
-- ============================================================
CREATE TABLE notifications (
    id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id         UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    type            VARCHAR(50) NOT NULL,  -- submission_due, reminder, system, alert
    title           VARCHAR(300) NOT NULL,
    message         TEXT NOT NULL,
    data            JSONB DEFAULT '{}',
    is_read         BOOLEAN NOT NULL DEFAULT FALSE,
    read_at         TIMESTAMPTZ,
    sent_email      BOOLEAN NOT NULL DEFAULT FALSE,
    sent_push       BOOLEAN NOT NULL DEFAULT FALSE,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_notifications_user ON notifications(user_id);
CREATE INDEX idx_notifications_read ON notifications(user_id, is_read);
CREATE INDEX idx_notifications_created ON notifications(created_at);

-- ============================================================
-- INDICATORS & KPIs
-- ============================================================
CREATE TABLE indicators (
    id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    created_by      UUID NOT NULL REFERENCES users(id),
    name            VARCHAR(200) NOT NULL,
    code            VARCHAR(50) UNIQUE NOT NULL,
    description     TEXT,
    category        VARCHAR(100),
    unit            VARCHAR(50),
    formula         TEXT,               -- SQL/expression for computed indicators
    target_value    DECIMAL(20, 6),
    target_period   VARCHAR(20),        -- monthly, quarterly, annual
    data_source     VARCHAR(100),       -- form field or computed
    source_form_id  UUID REFERENCES forms(id),
    source_field_id UUID REFERENCES form_fields(id),
    aggregation     VARCHAR(20) DEFAULT 'sum',  -- sum, avg, count, min, max
    is_active       BOOLEAN NOT NULL DEFAULT TRUE,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE indicator_values (
    id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    indicator_id    UUID NOT NULL REFERENCES indicators(id) ON DELETE CASCADE,
    hospital_id     UUID REFERENCES hospitals(id) ON DELETE CASCADE,
    region_id       UUID REFERENCES regions(id) ON DELETE CASCADE,
    period_start    DATE NOT NULL,
    period_end      DATE NOT NULL,
    value           DECIMAL(20, 6) NOT NULL,
    computed_at     TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_indicator_values_indicator ON indicator_values(indicator_id);
CREATE INDEX idx_indicator_values_hospital ON indicator_values(hospital_id);
CREATE INDEX idx_indicator_values_period ON indicator_values(period_start, period_end);

-- ============================================================
-- REPORTS
-- ============================================================
CREATE TABLE reports (
    id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    created_by      UUID NOT NULL REFERENCES users(id),
    name            VARCHAR(200) NOT NULL,
    type            VARCHAR(50) NOT NULL,  -- summary, detailed, comparison, trend
    parameters      JSONB DEFAULT '{}',    -- filters used to generate
    status          VARCHAR(20) NOT NULL DEFAULT 'pending',  -- pending, generating, ready, failed
    file_path       VARCHAR(500),
    file_format     VARCHAR(10),  -- pdf, xlsx, csv
    file_size       INTEGER,
    error_message   TEXT,
    expires_at      TIMESTAMPTZ,
    generated_at    TIMESTAMPTZ,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_reports_user ON reports(created_by);
CREATE INDEX idx_reports_status ON reports(status);

-- ============================================================
-- JOBS / QUEUE LOG
-- ============================================================
CREATE TABLE job_batches (
    id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    name            VARCHAR(200) NOT NULL,
    total_jobs      INTEGER NOT NULL DEFAULT 0,
    pending_jobs    INTEGER NOT NULL DEFAULT 0,
    failed_jobs     INTEGER NOT NULL DEFAULT 0,
    options         JSONB DEFAULT '{}',
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    cancelled_at    TIMESTAMPTZ,
    finished_at     TIMESTAMPTZ
);

-- ============================================================
-- AUTO-UPDATE TIMESTAMPS TRIGGER
-- ============================================================
CREATE OR REPLACE FUNCTION trigger_set_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER set_updated_at BEFORE UPDATE ON regions
    FOR EACH ROW EXECUTE FUNCTION trigger_set_updated_at();
CREATE TRIGGER set_updated_at BEFORE UPDATE ON hospitals
    FOR EACH ROW EXECUTE FUNCTION trigger_set_updated_at();
CREATE TRIGGER set_updated_at BEFORE UPDATE ON users
    FOR EACH ROW EXECUTE FUNCTION trigger_set_updated_at();
CREATE TRIGGER set_updated_at BEFORE UPDATE ON forms
    FOR EACH ROW EXECUTE FUNCTION trigger_set_updated_at();
CREATE TRIGGER set_updated_at BEFORE UPDATE ON form_fields
    FOR EACH ROW EXECUTE FUNCTION trigger_set_updated_at();
CREATE TRIGGER set_updated_at BEFORE UPDATE ON submissions
    FOR EACH ROW EXECUTE FUNCTION trigger_set_updated_at();
CREATE TRIGGER set_updated_at BEFORE UPDATE ON indicators
    FOR EACH ROW EXECUTE FUNCTION trigger_set_updated_at();
