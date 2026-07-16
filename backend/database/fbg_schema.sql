-- FBG Assessment Sheet Schema Extension
-- Tracks per-patient ART clinical progress (viral load, CD4, TB, disclosure) over time
-- Run AFTER schema.sql and seed.sql

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS fbg_assessments (
    id                      CHAR(36)     NOT NULL,
    hospital_id             CHAR(36)     NOT NULL,
    reporting_month         TINYINT UNSIGNED NOT NULL,
    reporting_year          SMALLINT UNSIGNED NOT NULL,

    -- Patient identification
    art_number              VARCHAR(80)  NOT NULL,
    gender                  CHAR(1)      COMMENT 'M or F',
    age                     TINYINT UNSIGNED,

    -- Viral Load Monitoring (status: suppressed / unsuppressed)
    baseline_viral_load     VARCHAR(50),
    baseline_status         VARCHAR(20),
    vl6_viral_load          VARCHAR(50),
    vl6_status              VARCHAR(20),
    vl12_viral_load         VARCHAR(50),
    vl12_status             VARCHAR(20),
    vl18_viral_load         VARCHAR(50),
    vl18_status             VARCHAR(20),
    vl24_viral_load         VARCHAR(50),
    vl24_status             VARCHAR(20),

    -- CD4 / AHD
    screened_for_ahd        TINYINT(1)   NOT NULL DEFAULT 0,
    cd4_above_200           TINYINT(1)   NOT NULL DEFAULT 0,
    cd4_below_200           TINYINT(1)   NOT NULL DEFAULT 0,

    -- Tuberculosis / TPT
    screened_for_tb         TINYINT(1)   NOT NULL DEFAULT 0,
    negative_for_tb         TINYINT(1)   NOT NULL DEFAULT 0,
    positive_for_tb         TINYINT(1)   NOT NULL DEFAULT 0,
    eligible_tpt_previous   TINYINT(1)   NOT NULL DEFAULT 0,
    receiving_tpt           TINYINT(1)   NOT NULL DEFAULT 0,

    -- Disclosure & Index Testing
    disclosure_done         TINYINT(1)   NOT NULL DEFAULT 0,
    index_testing_done      TINYINT(1)   NOT NULL DEFAULT 0,
    number_tested           INT          NOT NULL DEFAULT 0,

    -- Authentication
    prepared_by_name         VARCHAR(150),
    prepared_by_designation  VARCHAR(150),
    prepared_by_signature    VARCHAR(150),

    is_active                TINYINT(1)  NOT NULL DEFAULT 1,
    created_by                CHAR(36),
    created_at                DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_fbg_patient_period (hospital_id, art_number, reporting_year, reporting_month),
    KEY idx_fbg_hospital (hospital_id),
    KEY idx_fbg_art      (art_number),
    KEY idx_fbg_period   (reporting_year, reporting_month),
    CONSTRAINT fk_fbg_hospital   FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE RESTRICT,
    CONSTRAINT fk_fbg_created_by FOREIGN KEY (created_by)  REFERENCES users(id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
