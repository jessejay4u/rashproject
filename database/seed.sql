-- Health Platform — Seed Data
-- Run AFTER schema.sql
-- Default admin: admin@healthplatform.org / Admin@12345!

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─── Roles ────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO roles (id, name, display_name, description) VALUES
('r0000001-0000-0000-0000-000000000001', 'super_admin',    'Super Admin',    'Full system access'),
('r0000001-0000-0000-0000-000000000002', 'regional_admin', 'Regional Admin', 'Manage a region and its hospitals'),
('r0000001-0000-0000-0000-000000000003', 'hospital_admin', 'Hospital Admin', 'Manage a single hospital'),
('r0000001-0000-0000-0000-000000000004', 'data_entry',     'Data Entry',     'Submit data for their hospital'),
('r0000001-0000-0000-0000-000000000005', 'viewer',         'Viewer',         'Read-only access');

-- ─── Permissions ──────────────────────────────────────────────────────────────
INSERT IGNORE INTO permissions (id, name, display_name, module) VALUES
('p0000001-0000-0000-0000-000000000001', 'view_dashboard',       'View Dashboard',         'dashboard'),
('p0000001-0000-0000-0000-000000000002', 'view_submissions',     'View Submissions',       'submissions'),
('p0000001-0000-0000-0000-000000000003', 'create_submissions',   'Create Submissions',     'submissions'),
('p0000001-0000-0000-0000-000000000004', 'edit_submissions',     'Edit Submissions',       'submissions'),
('p0000001-0000-0000-0000-000000000005', 'delete_submissions',   'Delete Submissions',     'submissions'),
('p0000001-0000-0000-0000-000000000006', 'review_submissions',   'Review Submissions',     'submissions'),
('p0000001-0000-0000-0000-000000000007', 'view_forms',           'View Forms',             'forms'),
('p0000001-0000-0000-0000-000000000008', 'create_forms',         'Create Forms',           'forms'),
('p0000001-0000-0000-0000-000000000009', 'edit_forms',           'Edit Forms',             'forms'),
('p0000001-0000-0000-0000-000000000010', 'publish_forms',        'Publish/Archive Forms',  'forms'),
('p0000001-0000-0000-0000-000000000011', 'view_hospitals',       'View Hospitals',         'hospitals'),
('p0000001-0000-0000-0000-000000000012', 'create_hospitals',     'Create Hospitals',       'hospitals'),
('p0000001-0000-0000-0000-000000000013', 'edit_hospitals',       'Edit Hospitals',         'hospitals'),
('p0000001-0000-0000-0000-000000000014', 'view_users',           'View Users',             'users'),
('p0000001-0000-0000-0000-000000000015', 'create_users',         'Create Users',           'users'),
('p0000001-0000-0000-0000-000000000016', 'edit_users',           'Edit Users',             'users'),
('p0000001-0000-0000-0000-000000000017', 'deactivate_users',     'Deactivate Users',       'users'),
('p0000001-0000-0000-0000-000000000018', 'view_reports',         'View Reports',           'reports'),
('p0000001-0000-0000-0000-000000000019', 'export_reports',       'Export Reports',         'reports'),
('p0000001-0000-0000-0000-000000000020', 'view_audit_logs',      'View Audit Logs',        'audit');

-- ─── Role → Permission mapping ────────────────────────────────────────────────
-- super_admin: all permissions
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 'r0000001-0000-0000-0000-000000000001', id FROM permissions;

-- regional_admin: everything except user deactivate and audit
INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES
('r0000001-0000-0000-0000-000000000002', 'p0000001-0000-0000-0000-000000000001'),
('r0000001-0000-0000-0000-000000000002', 'p0000001-0000-0000-0000-000000000002'),
('r0000001-0000-0000-0000-000000000002', 'p0000001-0000-0000-0000-000000000003'),
('r0000001-0000-0000-0000-000000000002', 'p0000001-0000-0000-0000-000000000004'),
('r0000001-0000-0000-0000-000000000002', 'p0000001-0000-0000-0000-000000000006'),
('r0000001-0000-0000-0000-000000000002', 'p0000001-0000-0000-0000-000000000007'),
('r0000001-0000-0000-0000-000000000002', 'p0000001-0000-0000-0000-000000000010'),
('r0000001-0000-0000-0000-000000000002', 'p0000001-0000-0000-0000-000000000011'),
('r0000001-0000-0000-0000-000000000002', 'p0000001-0000-0000-0000-000000000012'),
('r0000001-0000-0000-0000-000000000002', 'p0000001-0000-0000-0000-000000000013'),
('r0000001-0000-0000-0000-000000000002', 'p0000001-0000-0000-0000-000000000014'),
('r0000001-0000-0000-0000-000000000002', 'p0000001-0000-0000-0000-000000000015'),
('r0000001-0000-0000-0000-000000000002', 'p0000001-0000-0000-0000-000000000016'),
('r0000001-0000-0000-0000-000000000002', 'p0000001-0000-0000-0000-000000000018'),
('r0000001-0000-0000-0000-000000000002', 'p0000001-0000-0000-0000-000000000019');

-- hospital_admin
INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES
('r0000001-0000-0000-0000-000000000003', 'p0000001-0000-0000-0000-000000000001'),
('r0000001-0000-0000-0000-000000000003', 'p0000001-0000-0000-0000-000000000002'),
('r0000001-0000-0000-0000-000000000003', 'p0000001-0000-0000-0000-000000000003'),
('r0000001-0000-0000-0000-000000000003', 'p0000001-0000-0000-0000-000000000004'),
('r0000001-0000-0000-0000-000000000003', 'p0000001-0000-0000-0000-000000000006'),
('r0000001-0000-0000-0000-000000000003', 'p0000001-0000-0000-0000-000000000007'),
('r0000001-0000-0000-0000-000000000003', 'p0000001-0000-0000-0000-000000000011'),
('r0000001-0000-0000-0000-000000000003', 'p0000001-0000-0000-0000-000000000018');

-- data_entry
INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES
('r0000001-0000-0000-0000-000000000004', 'p0000001-0000-0000-0000-000000000001'),
('r0000001-0000-0000-0000-000000000004', 'p0000001-0000-0000-0000-000000000002'),
('r0000001-0000-0000-0000-000000000004', 'p0000001-0000-0000-0000-000000000003'),
('r0000001-0000-0000-0000-000000000004', 'p0000001-0000-0000-0000-000000000004'),
('r0000001-0000-0000-0000-000000000004', 'p0000001-0000-0000-0000-000000000007');

-- viewer
INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES
('r0000001-0000-0000-0000-000000000005', 'p0000001-0000-0000-0000-000000000001'),
('r0000001-0000-0000-0000-000000000005', 'p0000001-0000-0000-0000-000000000002'),
('r0000001-0000-0000-0000-000000000005', 'p0000001-0000-0000-0000-000000000007');

-- ─── Regions ──────────────────────────────────────────────────────────────────
INSERT IGNORE INTO regions (id, name, code) VALUES
('reg00001-0000-0000-0000-000000000001', 'Northern Region',  'NORTH'),
('reg00001-0000-0000-0000-000000000002', 'Southern Region',  'SOUTH'),
('reg00001-0000-0000-0000-000000000003', 'Eastern Region',   'EAST'),
('reg00001-0000-0000-0000-000000000004', 'Western Region',   'WEST'),
('reg00001-0000-0000-0000-000000000005', 'Central Region',   'CENTRAL');

-- ─── Hospitals ────────────────────────────────────────────────────────────────
INSERT IGNORE INTO hospitals (id, name, code, region_id, type, bed_count, phone, email, is_active) VALUES
('hosp0001-0000-0000-0000-000000000001', 'Central General Hospital',    'CGH001', 'reg00001-0000-0000-0000-000000000005', 'general',     350, '+1-555-0100', 'cgh@healthplatform.org', 1),
('hosp0001-0000-0000-0000-000000000002', 'Northern Medical Centre',     'NMC001', 'reg00001-0000-0000-0000-000000000001', 'specialized', 200, '+1-555-0200', 'nmc@healthplatform.org', 1),
('hosp0001-0000-0000-0000-000000000003', 'Southern District Hospital',  'SDH001', 'reg00001-0000-0000-0000-000000000002', 'general',     150, '+1-555-0300', 'sdh@healthplatform.org', 1),
('hosp0001-0000-0000-0000-000000000004', 'Eastern Health Clinic',       'EHC001', 'reg00001-0000-0000-0000-000000000003', 'clinic',       50, '+1-555-0400', 'ehc@healthplatform.org', 1),
('hosp0001-0000-0000-0000-000000000005', 'Western Regional Hospital',   'WRH001', 'reg00001-0000-0000-0000-000000000004', 'general',     280, '+1-555-0500', 'wrh@healthplatform.org', 1);

-- ─── Admin User ───────────────────────────────────────────────────────────────
-- Password: Admin@12345!  (argon2id hash)
INSERT IGNORE INTO users (id, name, email, password_hash, role, is_active) VALUES
('user0001-0000-0000-0000-000000000001',
 'System Administrator',
 'admin@healthplatform.org',
 '$argon2id$v=19$m=65536,t=4,p=1$c29tZXNhbHRoZXJl$8X3gQzAqhAv2sIKiYjH9gI+4Kz5oFzXyJJwpMmIVdVk',
 'super_admin',
 1);

-- ─── Sample Forms ─────────────────────────────────────────────────────────────
INSERT IGNORE INTO forms (id, name, code, description, category, status, version, published_at, created_by) VALUES
('form0001-0000-0000-0000-000000000001',
 'Monthly Hospital Report',
 'monthly_report',
 'Monthly data collection for all registered hospitals covering patient statistics, bed occupancy, and services.',
 'Monthly',
 'published',
 1,
 NOW(),
 'user0001-0000-0000-0000-000000000001'),
('form0001-0000-0000-0000-000000000002',
 'Quarterly Disease Surveillance',
 'quarterly_disease',
 'Quarterly report on disease incidence and outbreak tracking.',
 'Quarterly',
 'published',
 1,
 NOW(),
 'user0001-0000-0000-0000-000000000001'),
('form0001-0000-0000-0000-000000000003',
 'Annual Facility Assessment',
 'annual_assessment',
 'Annual assessment of facility infrastructure and staff capacity.',
 'Annual',
 'draft',
 1,
 NULL,
 'user0001-0000-0000-0000-000000000001');

-- ─── Form Sections & Fields (Monthly Report) ──────────────────────────────────
INSERT IGNORE INTO form_sections (id, form_id, title, sort_order) VALUES
('sec00001-0000-0000-0000-000000000001', 'form0001-0000-0000-0000-000000000001', 'Patient Statistics',  0),
('sec00001-0000-0000-0000-000000000002', 'form0001-0000-0000-0000-000000000001', 'Bed Occupancy',       1),
('sec00001-0000-0000-0000-000000000003', 'form0001-0000-0000-0000-000000000001', 'Services Rendered',   2);

INSERT IGNORE INTO form_fields (id, section_id, form_id, name, label, field_type, is_required, sort_order) VALUES
('fld00001-0000-0000-0000-000000000001', 'sec00001-0000-0000-0000-000000000001', 'form0001-0000-0000-0000-000000000001', 'total_admissions',    'Total Admissions',          'number',  1, 0),
('fld00001-0000-0000-0000-000000000002', 'sec00001-0000-0000-0000-000000000001', 'form0001-0000-0000-0000-000000000001', 'total_discharges',    'Total Discharges',          'number',  1, 1),
('fld00001-0000-0000-0000-000000000003', 'sec00001-0000-0000-0000-000000000001', 'form0001-0000-0000-0000-000000000001', 'total_deaths',        'Total Deaths',              'number',  1, 2),
('fld00001-0000-0000-0000-000000000004', 'sec00001-0000-0000-0000-000000000001', 'form0001-0000-0000-0000-000000000001', 'outpatient_visits',   'Outpatient Visits',         'number',  0, 3),
('fld00001-0000-0000-0000-000000000005', 'sec00001-0000-0000-0000-000000000002', 'form0001-0000-0000-0000-000000000001', 'total_beds',          'Total Available Beds',      'number',  1, 0),
('fld00001-0000-0000-0000-000000000006', 'sec00001-0000-0000-0000-000000000002', 'form0001-0000-0000-0000-000000000001', 'occupied_beds',       'Occupied Beds',             'number',  1, 1),
('fld00001-0000-0000-0000-000000000007', 'sec00001-0000-0000-0000-000000000003', 'form0001-0000-0000-0000-000000000001', 'surgeries_performed', 'Surgeries Performed',       'number',  0, 0),
('fld00001-0000-0000-0000-000000000008', 'sec00001-0000-0000-0000-000000000003', 'form0001-0000-0000-0000-000000000001', 'deliveries',          'Deliveries (Live Births)',  'number',  0, 1),
('fld00001-0000-0000-0000-000000000009', 'sec00001-0000-0000-0000-000000000003', 'form0001-0000-0000-0000-000000000001', 'notes',               'Additional Notes',          'textarea', 0, 2);

-- ─── Form Sections & Fields (Quarterly Disease) ───────────────────────────────
INSERT IGNORE INTO form_sections (id, form_id, title, sort_order) VALUES
('sec00001-0000-0000-0000-000000000004', 'form0001-0000-0000-0000-000000000002', 'Disease Incidence',   0),
('sec00001-0000-0000-0000-000000000005', 'form0001-0000-0000-0000-000000000002', 'Outbreak Tracking',   1);

INSERT IGNORE INTO form_fields (id, section_id, form_id, name, label, field_type, is_required, sort_order) VALUES
('fld00001-0000-0000-0000-000000000010', 'sec00001-0000-0000-0000-000000000004', 'form0001-0000-0000-0000-000000000002', 'malaria_cases',     'Malaria Cases',           'number',  1, 0),
('fld00001-0000-0000-0000-000000000011', 'sec00001-0000-0000-0000-000000000004', 'form0001-0000-0000-0000-000000000002', 'tb_cases',          'Tuberculosis Cases',      'number',  1, 1),
('fld00001-0000-0000-0000-000000000012', 'sec00001-0000-0000-0000-000000000004', 'form0001-0000-0000-0000-000000000002', 'hiv_new_cases',     'New HIV Cases',           'number',  1, 2),
('fld00001-0000-0000-0000-000000000013', 'sec00001-0000-0000-0000-000000000005', 'form0001-0000-0000-0000-000000000002', 'outbreak_reported', 'Outbreak Reported?',      'boolean', 0, 0),
('fld00001-0000-0000-0000-000000000014', 'sec00001-0000-0000-0000-000000000005', 'form0001-0000-0000-0000-000000000002', 'outbreak_details',  'Outbreak Details',        'textarea', 0, 1);

SET FOREIGN_KEY_CHECKS = 1;

-- ─── Note on admin password ───────────────────────────────────────────────────
-- The stored hash above may not match your PHP version's argon2id output.
-- To regenerate a fresh hash, run this PHP snippet:
--   php -r "echo password_hash('Admin@12345!', PASSWORD_ARGON2ID, ['memory_cost'=>65536,'time_cost'=>4,'threads'=>1]);"
-- Then UPDATE users SET password_hash = '<output>' WHERE email = 'admin@healthplatform.org';
