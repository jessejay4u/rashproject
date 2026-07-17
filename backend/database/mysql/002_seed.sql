-- ============================================================
-- MySQL Seed: Roles, Permissions, Regions, Default Super Admin
-- ============================================================

SET FOREIGN_KEY_CHECKS=0;

-- Roles
INSERT INTO roles (id, name, display_name, description, is_system) VALUES
    ('00000000-0000-0000-0000-000000000001', 'super_admin',    'Super Administrator',    'Full system access',                    1),
    ('00000000-0000-0000-0000-000000000002', 'regional_admin', 'Regional Administrator', 'Manages hospitals within a region',     1),
    ('00000000-0000-0000-0000-000000000003', 'hospital_admin', 'Hospital Administrator', 'Manages a single hospital',             1),
    ('00000000-0000-0000-0000-000000000004', 'data_entry',     'Data Entry User',        'Submits data for a hospital',           1),
    ('00000000-0000-0000-0000-000000000005', 'viewer',         'Viewer',                 'Read-only access to reports/dashboards', 1);

-- Permissions (UUIDs assigned explicitly for MySQL compatibility)
INSERT INTO permissions (id, name, display_name, module) VALUES
    ('a0000000-0000-0000-0001-000000000001', 'users.view',           'View Users',                'users'),
    ('a0000000-0000-0000-0001-000000000002', 'users.create',         'Create Users',              'users'),
    ('a0000000-0000-0000-0001-000000000003', 'users.edit',           'Edit Users',                'users'),
    ('a0000000-0000-0000-0001-000000000004', 'users.delete',         'Delete Users',              'users'),
    ('a0000000-0000-0000-0002-000000000001', 'hospitals.view',       'View Hospitals',            'hospitals'),
    ('a0000000-0000-0000-0002-000000000002', 'hospitals.create',     'Create Hospitals',          'hospitals'),
    ('a0000000-0000-0000-0002-000000000003', 'hospitals.edit',       'Edit Hospitals',            'hospitals'),
    ('a0000000-0000-0000-0002-000000000004', 'hospitals.delete',     'Delete Hospitals',          'hospitals'),
    ('a0000000-0000-0000-0003-000000000001', 'forms.view',           'View Forms',                'forms'),
    ('a0000000-0000-0000-0003-000000000002', 'forms.create',         'Create Forms',              'forms'),
    ('a0000000-0000-0000-0003-000000000003', 'forms.edit',           'Edit Forms',                'forms'),
    ('a0000000-0000-0000-0003-000000000004', 'forms.delete',         'Delete Forms',              'forms'),
    ('a0000000-0000-0000-0003-000000000005', 'forms.publish',        'Publish Forms',             'forms'),
    ('a0000000-0000-0000-0004-000000000001', 'submissions.view',     'View Submissions',          'submissions'),
    ('a0000000-0000-0000-0004-000000000002', 'submissions.create',   'Create Submissions',        'submissions'),
    ('a0000000-0000-0000-0004-000000000003', 'submissions.edit',     'Edit Own Submissions',      'submissions'),
    ('a0000000-0000-0000-0004-000000000004', 'submissions.edit_any', 'Edit Any Submission',       'submissions'),
    ('a0000000-0000-0000-0004-000000000005', 'submissions.delete',   'Delete Submissions',        'submissions'),
    ('a0000000-0000-0000-0004-000000000006', 'submissions.review',   'Review Submissions',        'submissions'),
    ('a0000000-0000-0000-0005-000000000001', 'reports.view',         'View Reports',              'reports'),
    ('a0000000-0000-0000-0005-000000000002', 'reports.generate',     'Generate Reports',          'reports'),
    ('a0000000-0000-0000-0005-000000000003', 'reports.export',       'Export Reports',            'reports'),
    ('a0000000-0000-0000-0006-000000000001', 'dashboard.view',       'View Dashboard',            'dashboard'),
    ('a0000000-0000-0000-0006-000000000002', 'dashboard.executive',  'View Executive Dashboard',  'dashboard'),
    ('a0000000-0000-0000-0007-000000000001', 'indicators.view',      'View Indicators',           'indicators'),
    ('a0000000-0000-0000-0007-000000000002', 'indicators.manage',    'Manage Indicators',         'indicators'),
    ('a0000000-0000-0000-0008-000000000001', 'audit.view',           'View Audit Logs',           'audit'),
    ('a0000000-0000-0000-0009-000000000001', 'settings.manage',      'Manage System Settings',    'settings'),
    ('a0000000-0000-0000-0010-000000000001', 'regions.view',         'View Regions',              'regions'),
    ('a0000000-0000-0000-0010-000000000002', 'regions.manage',       'Manage Regions',            'regions'),
    ('a0000000-0000-0000-0011-000000000001', 'notifications.manage', 'Manage Notifications',      'notifications');

-- Assign ALL permissions to super_admin
INSERT INTO role_permissions (role_id, permission_id)
    SELECT '00000000-0000-0000-0000-000000000001', id FROM permissions;

-- Regional admin permissions
INSERT INTO role_permissions (role_id, permission_id)
    SELECT '00000000-0000-0000-0000-000000000002', id FROM permissions
    WHERE name IN (
        'users.view', 'hospitals.view', 'forms.view',
        'submissions.view', 'submissions.review', 'submissions.edit_any',
        'reports.view', 'reports.generate', 'reports.export',
        'dashboard.view', 'dashboard.executive',
        'indicators.view', 'regions.view'
    );

-- Hospital admin permissions
INSERT INTO role_permissions (role_id, permission_id)
    SELECT '00000000-0000-0000-0000-000000000003', id FROM permissions
    WHERE name IN (
        'users.view', 'hospitals.view', 'forms.view',
        'submissions.view', 'submissions.create', 'submissions.edit', 'submissions.edit_any',
        'reports.view', 'reports.generate',
        'dashboard.view'
    );

-- Data entry permissions
INSERT INTO role_permissions (role_id, permission_id)
    SELECT '00000000-0000-0000-0000-000000000004', id FROM permissions
    WHERE name IN (
        'forms.view', 'submissions.view', 'submissions.create', 'submissions.edit',
        'dashboard.view'
    );

-- Viewer permissions
INSERT INTO role_permissions (role_id, permission_id)
    SELECT '00000000-0000-0000-0000-000000000005', id FROM permissions
    WHERE name IN (
        'reports.view', 'dashboard.view', 'dashboard.executive',
        'indicators.view', 'hospitals.view', 'regions.view'
    );

-- Default regions (Ghana's 16 regions, plus a National scope)
INSERT INTO regions (id, name, code, description) VALUES
    ('10000000-0000-0000-0000-000000000001', 'National',              'NATIONAL', 'National level'),
    ('10000000-0000-0000-0000-000000000002', 'Ashanti Region',        'GH-ASH',   'Ashanti Region, Ghana'),
    ('10000000-0000-0000-0000-000000000003', 'Greater Accra Region',  'GH-GAR',   'Greater Accra Region, Ghana'),
    ('10000000-0000-0000-0000-000000000004', 'Ahafo Region',          'GH-AHA',   'Ahafo Region, Ghana'),
    ('10000000-0000-0000-0000-000000000005', 'Bono Region',           'GH-BON',   'Bono Region, Ghana'),
    ('10000000-0000-0000-0000-000000000006', 'Bono East Region',      'GH-BOE',   'Bono East Region, Ghana'),
    ('10000000-0000-0000-0000-000000000007', 'Central Region',        'GH-CEN',   'Central Region, Ghana'),
    ('10000000-0000-0000-0000-000000000008', 'Eastern Region',        'GH-EAS',   'Eastern Region, Ghana'),
    ('10000000-0000-0000-0000-000000000009', 'North East Region',     'GH-NEA',   'North East Region, Ghana'),
    ('10000000-0000-0000-0000-000000000010', 'Northern Region',       'GH-NOR',   'Northern Region, Ghana'),
    ('10000000-0000-0000-0000-000000000011', 'Oti Region',            'GH-OTI',   'Oti Region, Ghana'),
    ('10000000-0000-0000-0000-000000000012', 'Savannah Region',       'GH-SAV',   'Savannah Region, Ghana'),
    ('10000000-0000-0000-0000-000000000013', 'Upper East Region',     'GH-UEA',   'Upper East Region, Ghana'),
    ('10000000-0000-0000-0000-000000000014', 'Upper West Region',     'GH-UWE',   'Upper West Region, Ghana'),
    ('10000000-0000-0000-0000-000000000015', 'Volta Region',          'GH-VOL',   'Volta Region, Ghana'),
    ('10000000-0000-0000-0000-000000000016', 'Western Region',        'GH-WES',   'Western Region, Ghana'),
    ('10000000-0000-0000-0000-000000000017', 'Western North Region',  'GH-WNO',   'Western North Region, Ghana');

-- Default super admin (password: Admin@12345!)
INSERT INTO users (id, role_id, name, email, password_hash, is_active, mfa_enabled, email_verified_at) VALUES
    (
        '20000000-0000-0000-0000-000000000001',
        '00000000-0000-0000-0000-000000000001',
        'System Administrator',
        'admin@healthplatform.org',
        '$argon2id$v=19$m=65536,t=4,p=1$Nmo4UGg1cFhFQjlGdG5Rbw$Y+NS6wRIEIRMnydK7glUotQ6BhYfg5f9yY/J6K7sPs8',
        1,
        0,
        NOW()
    );

SET FOREIGN_KEY_CHECKS=1;
