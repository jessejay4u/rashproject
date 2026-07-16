-- FBG Assessment Sheet — Form Seed
-- Registers the FBG Assessment as a browsable entry in the generic Forms page.
-- Run AFTER schema.sql/mysql-001_schema.sql, seed.sql, and fbg_schema.sql.
-- NOTE: this registration is for discoverability/documentation only — actual
-- FBG data is captured and stored via fbg-assessment.html / api/fbg.php
-- (the fbg_assessments table), not via the generic submissions engine, so
-- the dashboard aggregation can stay fast. The "Open Form" action on this
-- form's detail page links straight to fbg-assessment.html.

SET NAMES utf8mb4;

-- ─── Form ─────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO forms
    (id, name, code, description, category, status, version, published_at, created_by)
VALUES (
    'fbga0001-0000-0000-0000-000000000001',
    'FBG Assessment Sheet',
    'fbg_assessment',
    'Facility-Based Group Assessment Sheet — tracks clinical progress of HIV patients on ART over time: viral load monitoring at baseline/6/12/18/24 months, CD4/AHD screening, TB screening and TPT, and disclosure & index testing.',
    'FBG Assessment',
    'published',
    1,
    NOW(),
    (SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.name = 'super_admin' ORDER BY u.created_at LIMIT 1)
);

-- ─── Sections ─────────────────────────────────────────────────────────────────
INSERT IGNORE INTO form_sections (id, form_id, title, description, order_index) VALUES
('fbgas001-0000-0000-0000-000000000001', 'fbga0001-0000-0000-0000-000000000001',
 'Facility Information', 'Header information for this assessment period', 0),
('fbgas001-0000-0000-0000-000000000002', 'fbga0001-0000-0000-0000-000000000001',
 'Patient Information', 'Patient identification', 1),
('fbgas001-0000-0000-0000-000000000003', 'fbga0001-0000-0000-0000-000000000001',
 'Viral Load Assessment', 'Viral load and suppression status at each follow-up period', 2),
('fbgas001-0000-0000-0000-000000000004', 'fbga0001-0000-0000-0000-000000000001',
 'CD4 Count', 'Advanced HIV Disease screening and CD4 count', 3),
('fbgas001-0000-0000-0000-000000000005', 'fbga0001-0000-0000-0000-000000000001',
 'Tuberculosis (TB)', 'TB screening and TB Preventive Therapy', 4),
('fbgas001-0000-0000-0000-000000000006', 'fbga0001-0000-0000-0000-000000000001',
 'Disclosure & Index Testing', 'HIV status disclosure and index client testing', 5),
('fbgas001-0000-0000-0000-000000000007', 'fbga0001-0000-0000-0000-000000000001',
 'Authentication', 'Officer completing the assessment', 6);

-- ─── Section 1: Facility Information ─────────────────────────────────────────
INSERT IGNORE INTO form_fields
    (id, section_id, form_id, name, label, field_type, is_required, options, help_text, order_index)
VALUES
('fbgaf001-0000-0000-0000-000000000001', 'fbgas001-0000-0000-0000-000000000001', 'fbga0001-0000-0000-0000-000000000001',
 'facility_name', 'Name of Facility', 'text', 1, NULL, NULL, 0),
('fbgaf001-0000-0000-0000-000000000002', 'fbgas001-0000-0000-0000-000000000001', 'fbga0001-0000-0000-0000-000000000001',
 'reporting_month', 'Reporting Month', 'select', 1,
 '["January","February","March","April","May","June","July","August","September","October","November","December"]',
 NULL, 1),
('fbgaf001-0000-0000-0000-000000000003', 'fbgas001-0000-0000-0000-000000000001', 'fbga0001-0000-0000-0000-000000000001',
 'reporting_year', 'Reporting Year', 'number', 1, NULL, NULL, 2);

-- ─── Section 2: Patient Information ──────────────────────────────────────────
INSERT IGNORE INTO form_fields
    (id, section_id, form_id, name, label, field_type, is_required, options, help_text, order_index)
VALUES
('fbgaf001-0000-0000-0000-000000000004', 'fbgas001-0000-0000-0000-000000000002', 'fbga0001-0000-0000-0000-000000000001',
 'art_number', 'ART Number', 'text', 1, NULL, 'Unique patient ART identifier', 0),
('fbgaf001-0000-0000-0000-000000000005', 'fbgas001-0000-0000-0000-000000000002', 'fbga0001-0000-0000-0000-000000000001',
 'gender', 'Gender', 'select', 0, '["Male","Female"]', NULL, 1),
('fbgaf001-0000-0000-0000-000000000006', 'fbgas001-0000-0000-0000-000000000002', 'fbga0001-0000-0000-0000-000000000001',
 'age', 'Age', 'number', 0, NULL, NULL, 2);

-- ─── Section 3: Viral Load Assessment ────────────────────────────────────────
INSERT IGNORE INTO form_fields
    (id, section_id, form_id, name, label, field_type, is_required, options, help_text, order_index)
VALUES
('fbgaf001-0000-0000-0000-000000000007', 'fbgas001-0000-0000-0000-000000000003', 'fbga0001-0000-0000-0000-000000000001',
 'baseline_viral_load', 'Baseline Viral Load (cp/mL)', 'text', 0, NULL, 'Viral load before treatment or at enrolment', 0),
('fbgaf001-0000-0000-0000-000000000008', 'fbgas001-0000-0000-0000-000000000003', 'fbga0001-0000-0000-0000-000000000001',
 'baseline_status', 'Baseline Status', 'select', 0, '["Suppressed","Unsuppressed"]', NULL, 1),
('fbgaf001-0000-0000-0000-000000000009', 'fbgas001-0000-0000-0000-000000000003', 'fbga0001-0000-0000-0000-000000000001',
 'six_months_viral_load', '6 Month Viral Load', 'text', 0, NULL, NULL, 2),
('fbgaf001-0000-0000-0000-000000000010', 'fbgas001-0000-0000-0000-000000000003', 'fbga0001-0000-0000-0000-000000000001',
 'six_months_status', '6 Month Status', 'select', 0, '["Suppressed","Unsuppressed"]', NULL, 3),
('fbgaf001-0000-0000-0000-000000000011', 'fbgas001-0000-0000-0000-000000000003', 'fbga0001-0000-0000-0000-000000000001',
 'twelve_months_viral_load', '12 Month Viral Load', 'text', 0, NULL, NULL, 4),
('fbgaf001-0000-0000-0000-000000000012', 'fbgas001-0000-0000-0000-000000000003', 'fbga0001-0000-0000-0000-000000000001',
 'twelve_months_status', '12 Month Status', 'select', 0, '["Suppressed","Unsuppressed"]', NULL, 5),
('fbgaf001-0000-0000-0000-000000000013', 'fbgas001-0000-0000-0000-000000000003', 'fbga0001-0000-0000-0000-000000000001',
 'eighteen_months_viral_load', '18 Month Viral Load', 'text', 0, NULL, NULL, 6),
('fbgaf001-0000-0000-0000-000000000014', 'fbgas001-0000-0000-0000-000000000003', 'fbga0001-0000-0000-0000-000000000001',
 'eighteen_months_status', '18 Month Status', 'select', 0, '["Suppressed","Unsuppressed"]', NULL, 7),
('fbgaf001-0000-0000-0000-000000000015', 'fbgas001-0000-0000-0000-000000000003', 'fbga0001-0000-0000-0000-000000000001',
 'twenty_four_months_viral_load', '24 Month Viral Load', 'text', 0, NULL, NULL, 8),
('fbgaf001-0000-0000-0000-000000000016', 'fbgas001-0000-0000-0000-000000000003', 'fbga0001-0000-0000-0000-000000000001',
 'twenty_four_months_status', '24 Month Status', 'select', 0, '["Suppressed","Unsuppressed"]', NULL, 9);

-- ─── Section 4: CD4 Count ─────────────────────────────────────────────────────
INSERT IGNORE INTO form_fields
    (id, section_id, form_id, name, label, field_type, is_required, options, help_text, order_index)
VALUES
('fbgaf001-0000-0000-0000-000000000017', 'fbgas001-0000-0000-0000-000000000004', 'fbga0001-0000-0000-0000-000000000001',
 'screened_for_ahd', 'Screened for AHD', 'boolean', 0, NULL, 'Screened for Advanced HIV Disease', 0),
('fbgaf001-0000-0000-0000-000000000018', 'fbgas001-0000-0000-0000-000000000004', 'fbga0001-0000-0000-0000-000000000001',
 'cd4_above_200', 'CD4 Above 200', 'boolean', 0, NULL, NULL, 1),
('fbgaf001-0000-0000-0000-000000000019', 'fbgas001-0000-0000-0000-000000000004', 'fbga0001-0000-0000-0000-000000000001',
 'cd4_below_200', 'CD4 Below 200', 'boolean', 0, NULL, NULL, 2);

-- ─── Section 5: Tuberculosis (TB) ─────────────────────────────────────────────
INSERT IGNORE INTO form_fields
    (id, section_id, form_id, name, label, field_type, is_required, options, help_text, order_index)
VALUES
('fbgaf001-0000-0000-0000-000000000020', 'fbgas001-0000-0000-0000-000000000005', 'fbga0001-0000-0000-0000-000000000001',
 'screened_for_tb', 'Screened for TB', 'boolean', 0, NULL, NULL, 0),
('fbgaf001-0000-0000-0000-000000000021', 'fbgas001-0000-0000-0000-000000000005', 'fbga0001-0000-0000-0000-000000000001',
 'negative_for_tb', 'Negative for TB', 'boolean', 0, NULL, NULL, 1),
('fbgaf001-0000-0000-0000-000000000022', 'fbgas001-0000-0000-0000-000000000005', 'fbga0001-0000-0000-0000-000000000001',
 'positive_for_tb', 'Positive for TB', 'boolean', 0, NULL, NULL, 2),
('fbgaf001-0000-0000-0000-000000000023', 'fbgas001-0000-0000-0000-000000000005', 'fbga0001-0000-0000-0000-000000000001',
 'eligible_for_tpt_previous_assessment', 'Eligible for TPT at Previous Assessment', 'boolean', 0, NULL, NULL, 3),
('fbgaf001-0000-0000-0000-000000000024', 'fbgas001-0000-0000-0000-000000000005', 'fbga0001-0000-0000-0000-000000000001',
 'receiving_tpt', 'Receiving TPT', 'boolean', 0, NULL, 'Tuberculosis Preventive Therapy', 4);

-- ─── Section 6: Disclosure & Index Testing ───────────────────────────────────
INSERT IGNORE INTO form_fields
    (id, section_id, form_id, name, label, field_type, is_required, options, help_text, order_index)
VALUES
('fbgaf001-0000-0000-0000-000000000025', 'fbgas001-0000-0000-0000-000000000006', 'fbga0001-0000-0000-0000-000000000001',
 'disclosure_done', 'Disclosure Done', 'boolean', 0, NULL, NULL, 0),
('fbgaf001-0000-0000-0000-000000000026', 'fbgas001-0000-0000-0000-000000000006', 'fbga0001-0000-0000-0000-000000000001',
 'index_testing_done', 'Index Testing Done', 'boolean', 0, NULL, NULL, 1),
('fbgaf001-0000-0000-0000-000000000027', 'fbgas001-0000-0000-0000-000000000006', 'fbga0001-0000-0000-0000-000000000001',
 'number_of_people_tested', 'Number of People Tested', 'number', 0, NULL, NULL, 2);

-- ─── Section 7: Authentication ────────────────────────────────────────────────
INSERT IGNORE INTO form_fields
    (id, section_id, form_id, name, label, field_type, is_required, options, help_text, order_index)
VALUES
('fbgaf001-0000-0000-0000-000000000028', 'fbgas001-0000-0000-0000-000000000007', 'fbga0001-0000-0000-0000-000000000001',
 'prepared_by_name', 'Name of Officer', 'text', 0, NULL, NULL, 0),
('fbgaf001-0000-0000-0000-000000000029', 'fbgas001-0000-0000-0000-000000000007', 'fbga0001-0000-0000-0000-000000000001',
 'prepared_by_designation', 'Designation', 'text', 0, NULL, NULL, 1),
('fbgaf001-0000-0000-0000-000000000030', 'fbgas001-0000-0000-0000-000000000007', 'fbga0001-0000-0000-0000-000000000001',
 'prepared_by_signature', 'Signature', 'text', 0, NULL, NULL, 2);
