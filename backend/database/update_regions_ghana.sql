-- Replace the placeholder "Region A" / "Region B" demo regions with Ghana's
-- real 16 regions (plus National). Safe to run on an existing database:
--   - Renames the two placeholder regions in place (id preserved) so any
--     hospitals/users already pointing at them keep working, no FK issues.
--   - Uses INSERT IGNORE (keyed on the unique `code` column) for the rest,
--     so running this script twice is harmless.
-- Run with: mysql -u root healthplatform < update_regions_ghana.sql
-- (replace `healthplatform` with your actual database name if different)

UPDATE regions SET name = 'Ashanti Region', code = 'GH-ASH', description = 'Ashanti Region, Ghana'
  WHERE name = 'Region A';

UPDATE regions SET name = 'Greater Accra Region', code = 'GH-GAR', description = 'Greater Accra Region, Ghana'
  WHERE name = 'Region B';

INSERT IGNORE INTO regions (id, name, code, description) VALUES
    (UUID(), 'Ahafo Region',         'GH-AHA', 'Ahafo Region, Ghana'),
    (UUID(), 'Ashanti Region',       'GH-ASH', 'Ashanti Region, Ghana'),
    (UUID(), 'Bono Region',          'GH-BON', 'Bono Region, Ghana'),
    (UUID(), 'Bono East Region',     'GH-BOE', 'Bono East Region, Ghana'),
    (UUID(), 'Central Region',       'GH-CEN', 'Central Region, Ghana'),
    (UUID(), 'Eastern Region',       'GH-EAS', 'Eastern Region, Ghana'),
    (UUID(), 'Greater Accra Region', 'GH-GAR', 'Greater Accra Region, Ghana'),
    (UUID(), 'North East Region',    'GH-NEA', 'North East Region, Ghana'),
    (UUID(), 'Northern Region',      'GH-NOR', 'Northern Region, Ghana'),
    (UUID(), 'Oti Region',           'GH-OTI', 'Oti Region, Ghana'),
    (UUID(), 'Savannah Region',      'GH-SAV', 'Savannah Region, Ghana'),
    (UUID(), 'Upper East Region',    'GH-UEA', 'Upper East Region, Ghana'),
    (UUID(), 'Upper West Region',    'GH-UWE', 'Upper West Region, Ghana'),
    (UUID(), 'Volta Region',         'GH-VOL', 'Volta Region, Ghana'),
    (UUID(), 'Western Region',       'GH-WES', 'Western Region, Ghana'),
    (UUID(), 'Western North Region', 'GH-WNO', 'Western North Region, Ghana');
