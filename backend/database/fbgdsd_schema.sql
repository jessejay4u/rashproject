-- FBGDSD (Facility-Based Group Differentiated Service Delivery) Schema Extension
-- Run AFTER schema.sql and seed.sql

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─── FBGDSD Groups ───────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS fbgdsd_groups (
    id          CHAR(36)     NOT NULL,
    hospital_id CHAR(36)     NOT NULL,
    group_name  VARCHAR(150) NOT NULL,
    lead_nurse  VARCHAR(150),
    district    VARCHAR(100),
    region      VARCHAR(100),
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    created_by  CHAR(36),
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_fgr_hospital (hospital_id),
    CONSTRAINT fk_fgr_hospital   FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE RESTRICT,
    CONSTRAINT fk_fgr_created_by FOREIGN KEY (created_by)  REFERENCES users(id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Group Members (enrolled patients) ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS fbgdsd_members (
    id                 CHAR(36)    NOT NULL,
    group_id           CHAR(36)    NOT NULL,
    hospital_id        CHAR(36)    NOT NULL,
    first_name         VARCHAR(100),
    surname            VARCHAR(100),
    age                TINYINT UNSIGNED,
    sex                CHAR(1)     COMMENT 'M or F',
    educational_level  VARCHAR(100),
    art_reg_number     VARCHAR(80) NOT NULL,
    drug_regimen       VARCHAR(20) COMMENT 'first_line or second_line',
    -- Baseline
    level_of_care      VARCHAR(30) COMMENT 'established or non_established',
    vl_suppressed      TINYINT(1),
    cd4_above_200      TINYINT(1),
    -- Disclosure
    disclosure_done    TINYINT(1)  DEFAULT 0,
    disclosure_date    DATE,
    -- Adherence / EAC
    eac_eligible       TINYINT(1)  DEFAULT 0,
    eac_date_initiated DATE,
    -- Viral Load
    vl_date_taken      DATE,
    vl_result          VARCHAR(50),
    -- AHD Screening
    ahd_date_done      DATE,
    ahd_result         VARCHAR(50),
    -- TB Screening
    tb_date_done       DATE,
    tb_result          VARCHAR(50),
    -- TPT
    tpt_eligible       TINYINT(1)  DEFAULT 0,
    tpt_provided       TINYINT(1)  DEFAULT 0,
    -- Index client testing
    index_testing_done TINYINT(1)  DEFAULT 0,
    is_active          TINYINT(1)  NOT NULL DEFAULT 1,
    enrolled_at        DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_fm_art (group_id, art_reg_number),
    KEY idx_fm_group    (group_id),
    KEY idx_fm_hospital (hospital_id),
    CONSTRAINT fk_fm_group    FOREIGN KEY (group_id)    REFERENCES fbgdsd_groups(id) ON DELETE RESTRICT,
    CONSTRAINT fk_fm_hospital FOREIGN KEY (hospital_id) REFERENCES hospitals(id)     ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Meetings ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS fbgdsd_meetings (
    id           CHAR(36)     NOT NULL,
    group_id     CHAR(36)     NOT NULL,
    hospital_id  CHAR(36)     NOT NULL,
    meeting_date DATE         NOT NULL,
    topic        VARCHAR(255),
    notes        TEXT,
    created_by   CHAR(36),
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_fmtg_group    (group_id),
    KEY idx_fmtg_hospital (hospital_id),
    KEY idx_fmtg_date     (meeting_date),
    CONSTRAINT fk_fmtg_group    FOREIGN KEY (group_id)    REFERENCES fbgdsd_groups(id) ON DELETE RESTRICT,
    CONSTRAINT fk_fmtg_hospital FOREIGN KEY (hospital_id) REFERENCES hospitals(id)     ON DELETE RESTRICT,
    CONSTRAINT fk_fmtg_user     FOREIGN KEY (created_by)  REFERENCES users(id)         ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Meeting Attendance ───────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS fbgdsd_attendance (
    id         CHAR(36)    NOT NULL,
    meeting_id CHAR(36)    NOT NULL,
    member_id  CHAR(36)    NOT NULL,
    attended   TINYINT(1)  NOT NULL DEFAULT 1,
    created_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_fa_meeting_member (meeting_id, member_id),
    KEY idx_fa_meeting (meeting_id),
    KEY idx_fa_member  (member_id),
    CONSTRAINT fk_fa_meeting FOREIGN KEY (meeting_id) REFERENCES fbgdsd_meetings(id) ON DELETE CASCADE,
    CONSTRAINT fk_fa_member  FOREIGN KEY (member_id)  REFERENCES fbgdsd_members(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Annual Targets ───────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS fbgdsd_targets (
    id                    CHAR(36) NOT NULL,
    hospital_id           CHAR(36),
    year                  YEAR     NOT NULL,
    total_groups          INT      DEFAULT 0,
    beneficiaries_per_group INT    DEFAULT 10,
    meetings_per_group    INT      DEFAULT 12,
    created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ft_hospital_year (hospital_id, year),
    CONSTRAINT fk_ft_hospital FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
