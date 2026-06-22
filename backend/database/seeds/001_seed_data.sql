-- ============================================================
-- Seed: Roles, Permissions, and Default Super Admin
-- ============================================================

-- Roles
INSERT INTO roles (id, name, display_name, description, is_system) VALUES
    ('00000000-0000-0000-0000-000000000001', 'super_admin',       'Super Administrator', 'Full system access',                   TRUE),
    ('00000000-0000-0000-0000-000000000002', 'regional_admin',    'Regional Administrator', 'Manages hospitals within a region', TRUE),
    ('00000000-0000-0000-0000-000000000003', 'hospital_admin',    'Hospital Administrator', 'Manages a single hospital',         TRUE),
    ('00000000-0000-0000-0000-000000000004', 'data_entry',        'Data Entry User', 'Submits data for a hospital',            TRUE),
    ('00000000-0000-0000-0000-000000000005', 'viewer',            'Viewer', 'Read-only access to reports and dashboards',      TRUE);

-- Permissions
INSERT INTO permissions (name, display_name, module) VALUES
    -- Users
    ('users.view',           'View Users',            'users'),
    ('users.create',         'Create Users',          'users'),
    ('users.edit',           'Edit Users',            'users'),
    ('users.delete',         'Delete Users',          'users'),
    -- Hospitals
    ('hospitals.view',       'View Hospitals',        'hospitals'),
    ('hospitals.create',     'Create Hospitals',      'hospitals'),
    ('hospitals.edit',       'Edit Hospitals',        'hospitals'),
    ('hospitals.delete',     'Delete Hospitals',      'hospitals'),
    -- Forms
    ('forms.view',           'View Forms',            'forms'),
    ('forms.create',         'Create Forms',          'forms'),
    ('forms.edit',           'Edit Forms',            'forms'),
    ('forms.delete',         'Delete Forms',          'forms'),
    ('forms.publish',        'Publish Forms',         'forms'),
    -- Submissions
    ('submissions.view',     'View Submissions',      'submissions'),
    ('submissions.create',   'Create Submissions',    'submissions'),
    ('submissions.edit',     'Edit Own Submissions',  'submissions'),
    ('submissions.edit_any', 'Edit Any Submission',   'submissions'),
    ('submissions.delete',   'Delete Submissions',    'submissions'),
    ('submissions.review',   'Review Submissions',    'submissions'),
    -- Reports
    ('reports.view',         'View Reports',          'reports'),
    ('reports.generate',     'Generate Reports',      'reports'),
    ('reports.export',       'Export Reports',        'reports'),
    -- Dashboard
    ('dashboard.view',       'View Dashboard',        'dashboard'),
    ('dashboard.executive',  'View Executive Dashboard', 'dashboard'),
    -- Indicators
    ('indicators.view',      'View Indicators',       'indicators'),
    ('indicators.manage',    'Manage Indicators',     'indicators'),
    -- Audit
    ('audit.view',           'View Audit Logs',       'audit'),
    -- Settings
    ('settings.manage',      'Manage System Settings','settings'),
    -- Regions
    ('regions.view',         'View Regions',          'regions'),
    ('regions.manage',       'Manage Regions',        'regions'),
    -- Notifications
    ('notifications.manage', 'Manage Notifications',  'notifications');

-- Assign all permissions to super_admin
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

-- Default region
INSERT INTO regions (id, name, code, description) VALUES
    ('10000000-0000-0000-0000-000000000001', 'National', 'NATIONAL', 'National level'),
    ('10000000-0000-0000-0000-000000000002', 'Region A', 'REG-A', 'Region A'),
    ('10000000-0000-0000-0000-000000000003', 'Region B', 'REG-B', 'Region B');

-- Default super admin user (password: Admin@12345! — CHANGE IN PRODUCTION)
-- argon2id hash of "Admin@12345!"
INSERT INTO users (id, role_id, name, email, password_hash, is_active, mfa_enabled, email_verified_at) VALUES
    (
        '20000000-0000-0000-0000-000000000001',
        '00000000-0000-0000-0000-000000000001',
        'System Administrator',
        'admin@healthplatform.org',
        '$argon2id$v=19$m=65536,t=4,p=1$PLACEHOLDER_CHANGE_ON_FIRST_LOGIN',
        TRUE,
        FALSE,
        NOW()
    );
