-- FBGDSD Meeting Register — Form Seed
-- Inserts the FBGDSD register as a form in the existing forms system.
-- Run AFTER schema.sql and seed.sql.
-- Each submission = one patient's record for one meeting session.

SET NAMES utf8mb4;

-- ─── Form ─────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO forms
    (id, name, code, description, category, status, version, published_at, created_by)
VALUES (
    'fbgd0001-0000-0000-0000-000000000001',
    'FBGDSD Meeting Register',
    'fbgdsd_register',
    'Facility-Based Group Differentiated Service Delivery meeting register. Each submission captures one patient attendance record for a group meeting session, including clinical tracking data.',
    'FBGDSD',
    'published',
    1,
    NOW(),
    (SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.name = 'super_admin' ORDER BY u.created_at LIMIT 1)
);

-- ─── Sections ─────────────────────────────────────────────────────────────────
INSERT IGNORE INTO form_sections (id, form_id, title, description, order_index) VALUES
('fbgs0001-0000-0000-0000-000000000001', 'fbgd0001-0000-0000-0000-000000000001',
 'Meeting Information',
 'Details of the group meeting session',
 0),
('fbgs0001-0000-0000-0000-000000000002', 'fbgd0001-0000-0000-0000-000000000001',
 'Patient Details',
 'Demographic and registration information for the group member',
 1),
('fbgs0001-0000-0000-0000-000000000003', 'fbgd0001-0000-0000-0000-000000000001',
 'Baseline Data',
 'Current clinical baseline at time of enrolment',
 2),
('fbgs0001-0000-0000-0000-000000000004', 'fbgd0001-0000-0000-0000-000000000001',
 'Disclosure',
 'HIV status disclosure tracking',
 3),
('fbgs0001-0000-0000-0000-000000000005', 'fbgd0001-0000-0000-0000-000000000001',
 'Adherence Support / EAC',
 'Enhanced adherence counselling tracking',
 4),
('fbgs0001-0000-0000-0000-000000000006', 'fbgd0001-0000-0000-0000-000000000001',
 'Viral Load',
 'Viral load testing results',
 5),
('fbgs0001-0000-0000-0000-000000000007', 'fbgd0001-0000-0000-0000-000000000001',
 'AHD Screening',
 'Advanced HIV Disease screening',
 6),
('fbgs0001-0000-0000-0000-000000000008', 'fbgd0001-0000-0000-0000-000000000001',
 'TB Screening',
 'Tuberculosis screening',
 7),
('fbgs0001-0000-0000-0000-000000000009', 'fbgd0001-0000-0000-0000-000000000001',
 'TPT',
 'TB Preventive Therapy',
 8),
('fbgs0001-0000-0000-0000-000000000010', 'fbgd0001-0000-0000-0000-000000000001',
 'Index Client Testing',
 'Index client HIV testing services',
 9);

-- ─── Section 1: Meeting Information ──────────────────────────────────────────
INSERT IGNORE INTO form_fields
    (id, section_id, form_id, name, label, field_type, is_required, options, help_text, order_index)
VALUES
(
    'fbgf0001-0000-0000-0000-000000000001',
    'fbgs0001-0000-0000-0000-000000000001',
    'fbgd0001-0000-0000-0000-000000000001',
    'meeting_date', 'Date of FBGDSD Meeting', 'date', 1,
    NULL, 'Date the group meeting was held', 0
),
(
    'fbgf0001-0000-0000-0000-000000000002',
    'fbgs0001-0000-0000-0000-000000000001',
    'fbgd0001-0000-0000-0000-000000000001',
    'lead_nurse', 'Name of FBGDSD Lead (Nurse)', 'text', 1,
    NULL, 'Full name of the nurse facilitating the group', 1
),
(
    'fbgf0001-0000-0000-0000-000000000003',
    'fbgs0001-0000-0000-0000-000000000001',
    'fbgd0001-0000-0000-0000-000000000001',
    'topic_covered', 'Topic Covered', 'select', 1,
    '["Disclosure Support","Psychosocial Support","Adherence Counselling & Support","HIV Health Education & Treatment Literacy","ART Defaulter Counselling","Post Violence Counselling and Support","Nutritional Counselling","Sexual Reproductive Health & Rights Counselling","Family Planning Methods Counselling","U=U Concept"]',
    'Topic discussed during this group meeting session', 2
),
(
    'fbgf0001-0000-0000-0000-000000000004',
    'fbgs0001-0000-0000-0000-000000000001',
    'fbgd0001-0000-0000-0000-000000000001',
    'district', 'District', 'text', 1,
    NULL, NULL, 3
),
(
    'fbgf0001-0000-0000-0000-000000000005',
    'fbgs0001-0000-0000-0000-000000000001',
    'fbgd0001-0000-0000-0000-000000000001',
    'health_facility', 'Health Facility', 'text', 1,
    NULL, NULL, 4
),
(
    'fbgf0001-0000-0000-0000-000000000006',
    'fbgs0001-0000-0000-0000-000000000001',
    'fbgd0001-0000-0000-0000-000000000001',
    'region', 'Region', 'text', 1,
    NULL, NULL, 5
);

-- ─── Section 2: Patient Details ───────────────────────────────────────────────
INSERT IGNORE INTO form_fields
    (id, section_id, form_id, name, label, field_type, is_required, options, help_text, order_index)
VALUES
(
    'fbgf0001-0000-0000-0000-000000000007',
    'fbgs0001-0000-0000-0000-000000000002',
    'fbgd0001-0000-0000-0000-000000000001',
    'first_name', 'First Name', 'text', 1,
    NULL, NULL, 0
),
(
    'fbgf0001-0000-0000-0000-000000000008',
    'fbgs0001-0000-0000-0000-000000000002',
    'fbgd0001-0000-0000-0000-000000000001',
    'surname', 'Surname', 'text', 1,
    NULL, NULL, 1
),
(
    'fbgf0001-0000-0000-0000-000000000009',
    'fbgs0001-0000-0000-0000-000000000002',
    'fbgd0001-0000-0000-0000-000000000001',
    'age', 'Age', 'number', 1,
    NULL, NULL, 2
),
(
    'fbgf0001-0000-0000-0000-000000000010',
    'fbgs0001-0000-0000-0000-000000000002',
    'fbgd0001-0000-0000-0000-000000000001',
    'sex', 'Sex', 'select', 1,
    '["Male","Female"]',
    NULL, 3
),
(
    'fbgf0001-0000-0000-0000-000000000011',
    'fbgs0001-0000-0000-0000-000000000002',
    'fbgd0001-0000-0000-0000-000000000001',
    'educational_level', 'Educational Level', 'select', 0,
    '["None","Primary","Secondary","Tertiary","Vocational"]',
    NULL, 4
),
(
    'fbgf0001-0000-0000-0000-000000000012',
    'fbgs0001-0000-0000-0000-000000000002',
    'fbgd0001-0000-0000-0000-000000000001',
    'art_reg_number', 'ART Registration Number', 'text', 1,
    NULL, 'Unique ART registration number for this patient', 5
),
(
    'fbgf0001-0000-0000-0000-000000000013',
    'fbgs0001-0000-0000-0000-000000000002',
    'fbgd0001-0000-0000-0000-000000000001',
    'drug_regimen', 'Drug Regimen', 'select', 1,
    '["First Line","Second Line"]',
    NULL, 6
);

-- ─── Section 3: Baseline Data ─────────────────────────────────────────────────
INSERT IGNORE INTO form_fields
    (id, section_id, form_id, name, label, field_type, is_required, options, help_text, order_index)
VALUES
(
    'fbgf0001-0000-0000-0000-000000000014',
    'fbgs0001-0000-0000-0000-000000000003',
    'fbgd0001-0000-0000-0000-000000000001',
    'level_of_care', 'Current Level of Care', 'select', 1,
    '["Established","Non-Established"]',
    NULL, 0
),
(
    'fbgf0001-0000-0000-0000-000000000015',
    'fbgs0001-0000-0000-0000-000000000003',
    'fbgd0001-0000-0000-0000-000000000001',
    'vl_baseline_status', 'Viral Load Status (Baseline)', 'select', 0,
    '["Viral Suppressed","Viral Non-Suppressed"]',
    'Viral suppression status at baseline', 1
),
(
    'fbgf0001-0000-0000-0000-000000000016',
    'fbgs0001-0000-0000-0000-000000000003',
    'fbgd0001-0000-0000-0000-000000000001',
    'cd4_baseline', 'CD4 Count (Baseline)', 'select', 0,
    '["CD4 ≥ 200","CD4 < 200"]',
    'CD4 count category at baseline', 2
);

-- ─── Section 4: Disclosure ────────────────────────────────────────────────────
INSERT IGNORE INTO form_fields
    (id, section_id, form_id, name, label, field_type, is_required, options, help_text, order_index)
VALUES
(
    'fbgf0001-0000-0000-0000-000000000017',
    'fbgs0001-0000-0000-0000-000000000004',
    'fbgd0001-0000-0000-0000-000000000001',
    'disclosure_done', 'Disclosure Done', 'boolean', 0,
    NULL, 'Has the patient disclosed their HIV status?', 0
),
(
    'fbgf0001-0000-0000-0000-000000000018',
    'fbgs0001-0000-0000-0000-000000000004',
    'fbgd0001-0000-0000-0000-000000000001',
    'disclosure_date', 'Date of Disclosure', 'date', 0,
    NULL, 'Date patient disclosed their HIV status', 1
);

-- ─── Section 5: Adherence Support / EAC ──────────────────────────────────────
INSERT IGNORE INTO form_fields
    (id, section_id, form_id, name, label, field_type, is_required, options, help_text, order_index)
VALUES
(
    'fbgf0001-0000-0000-0000-000000000019',
    'fbgs0001-0000-0000-0000-000000000005',
    'fbgd0001-0000-0000-0000-000000000001',
    'eac_eligible', 'EAC Eligible', 'boolean', 0,
    NULL, 'Is the patient eligible for Enhanced Adherence Counselling?', 0
),
(
    'fbgf0001-0000-0000-0000-000000000020',
    'fbgs0001-0000-0000-0000-000000000005',
    'fbgd0001-0000-0000-0000-000000000001',
    'eac_date_initiated', 'Date EAC Initiated', 'date', 0,
    NULL, NULL, 1
);

-- ─── Section 6: Viral Load ────────────────────────────────────────────────────
INSERT IGNORE INTO form_fields
    (id, section_id, form_id, name, label, field_type, is_required, options, help_text, order_index)
VALUES
(
    'fbgf0001-0000-0000-0000-000000000021',
    'fbgs0001-0000-0000-0000-000000000006',
    'fbgd0001-0000-0000-0000-000000000001',
    'vl_date_taken', 'VL Sample Date', 'date', 0,
    NULL, 'Date viral load sample was taken', 0
),
(
    'fbgf0001-0000-0000-0000-000000000022',
    'fbgs0001-0000-0000-0000-000000000006',
    'fbgd0001-0000-0000-0000-000000000001',
    'vl_result', 'VL Result (copies/mL)', 'text', 0,
    NULL, 'Viral load result in copies per mL, e.g. <20 or 450', 1
);

-- ─── Section 7: AHD Screening ────────────────────────────────────────────────
INSERT IGNORE INTO form_fields
    (id, section_id, form_id, name, label, field_type, is_required, options, help_text, order_index)
VALUES
(
    'fbgf0001-0000-0000-0000-000000000023',
    'fbgs0001-0000-0000-0000-000000000007',
    'fbgd0001-0000-0000-0000-000000000001',
    'ahd_date_done', 'AHD Screening Date', 'date', 0,
    NULL, 'Date Advanced HIV Disease screening was performed', 0
),
(
    'fbgf0001-0000-0000-0000-000000000024',
    'fbgs0001-0000-0000-0000-000000000007',
    'fbgd0001-0000-0000-0000-000000000001',
    'ahd_result', 'AHD Screening Result', 'select', 0,
    '["Negative","Positive","Inconclusive"]',
    NULL, 1
);

-- ─── Section 8: TB Screening ──────────────────────────────────────────────────
INSERT IGNORE INTO form_fields
    (id, section_id, form_id, name, label, field_type, is_required, options, help_text, order_index)
VALUES
(
    'fbgf0001-0000-0000-0000-000000000025',
    'fbgs0001-0000-0000-0000-000000000008',
    'fbgd0001-0000-0000-0000-000000000001',
    'tb_date_done', 'TB Screening Date', 'date', 0,
    NULL, NULL, 0
),
(
    'fbgf0001-0000-0000-0000-000000000026',
    'fbgs0001-0000-0000-0000-000000000008',
    'fbgd0001-0000-0000-0000-000000000001',
    'tb_result', 'TB Screening Result', 'select', 0,
    '["Negative","Positive","Inconclusive"]',
    NULL, 1
);

-- ─── Section 9: TPT ───────────────────────────────────────────────────────────
INSERT IGNORE INTO form_fields
    (id, section_id, form_id, name, label, field_type, is_required, options, help_text, order_index)
VALUES
(
    'fbgf0001-0000-0000-0000-000000000027',
    'fbgs0001-0000-0000-0000-000000000009',
    'fbgd0001-0000-0000-0000-000000000001',
    'tpt_eligible', 'TPT Eligible', 'boolean', 0,
    NULL, 'Is the patient eligible for TB Preventive Therapy?', 0
),
(
    'fbgf0001-0000-0000-0000-000000000028',
    'fbgs0001-0000-0000-0000-000000000009',
    'fbgd0001-0000-0000-0000-000000000001',
    'tpt_provided', 'TPT Provided', 'boolean', 0,
    NULL, 'Has TB Preventive Therapy been provided to the patient?', 1
);

-- ─── Section 10: Index Client Testing ────────────────────────────────────────
INSERT IGNORE INTO form_fields
    (id, section_id, form_id, name, label, field_type, is_required, options, help_text, order_index)
VALUES
(
    'fbgf0001-0000-0000-0000-000000000029',
    'fbgs0001-0000-0000-0000-000000000010',
    'fbgd0001-0000-0000-0000-000000000001',
    'index_testing_done', 'Index Client Testing Done', 'boolean', 0,
    NULL, 'Has index client testing been offered and completed?', 0
),
(
    'fbgf0001-0000-0000-0000-000000000030',
    'fbgs0001-0000-0000-0000-000000000010',
    'fbgd0001-0000-0000-0000-000000000001',
    'index_testing_contacts', 'Number of Contacts Tested', 'number', 0,
    NULL, 'Total number of contacts tested through index testing', 1
),
(
    'fbgf0001-0000-0000-0000-000000000031',
    'fbgs0001-0000-0000-0000-000000000010',
    'fbgd0001-0000-0000-0000-000000000001',
    'signature', 'Signature / Thumb Print', 'text', 0,
    NULL, 'Group member signature or thumb print reference', 2
);
