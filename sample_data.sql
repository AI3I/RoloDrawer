-- RoloDrawer Sample Database
-- This creates a realistic sample dataset for demonstration and testing

-- Insert sample locations
INSERT OR IGNORE INTO locations (id, name, building, floor, room, notes) VALUES
(3, 'Corporate Office', 'Building A', '2nd Floor', 'Records Room', 'Main corporate filing location'),
(4, 'Warehouse Storage', 'Building B', 'Ground Floor', 'Secure Storage Area C', 'Long-term archive storage'),
(5, 'Legal Department', 'Building A', '3rd Floor', 'Room 305', 'Legal and compliance documents');

-- Insert sample cabinets
INSERT OR IGNORE INTO cabinets (id, label, location_id, notes) VALUES
(3, 'CAB-A01', 3, 'Active files - Current year'),
(4, 'CAB-A02', 3, 'Personnel files'),
(5, 'CAB-W01', 4, 'Archive cabinet - 2020-2023'),
(6, 'CAB-L01', 5, 'Legal contracts and agreements');

-- Insert sample drawers
INSERT OR IGNORE INTO drawers (id, cabinet_id, label, position) VALUES
(6, 3, '1', 1),
(7, 3, '2', 2),
(8, 3, '3', 3),
(9, 4, 'A-F', 1),
(10, 4, 'G-M', 2),
(11, 4, 'N-Z', 3),
(12, 5, 'Top', 1),
(13, 5, 'Middle', 2),
(14, 5, 'Bottom', 3),
(15, 6, '1', 1);

-- Insert sample entities
INSERT OR IGNORE INTO entities (id, name, description, contact_info) VALUES
(4, 'GlobalTech Solutions', 'IT services vendor', 'procurement@globaltech.example.com'),
(5, 'Acme Manufacturing', 'Primary supplier for parts', 'orders@acme.example.com'),
(6, 'Smith & Associates Legal', 'Corporate legal counsel', 'contact@smithlaw.example.com'),
(7, 'Internal - HR', 'Human Resources department', NULL),
(8, 'Internal - Finance', 'Finance and Accounting department', NULL);

-- Insert sample tags
INSERT OR IGNORE INTO tags (id, name, color) VALUES
(5, 'Urgent', '#EF4444'),
(6, 'Confidential', '#DC2626'),
(7, 'Tax Documents', '#F59E0B'),
(8, 'Insurance', '#3B82F6'),
(9, 'Employee Records', '#8B5CF6'),
(10, '2024', '#10B981'),
(11, '2025', '#059669'),
(12, 'Under Review', '#F97316');

-- Insert sample users
INSERT OR IGNORE INTO users (id, email, password, name, role, status) VALUES
(2, 'john.doe', '$2y$10$vX8mJxGqYQFKmZKzqhHKJeK5xN.6YqWF8OQ0mLgxZ5NJY7RxMZhPC', 'John Doe', 'user', 'active'),
(3, 'jane.smith', '$2y$10$vX8mJxGqYQFKmZKzqhHKJeK5xN.6YqWF8OQ0mLgxZ5NJY7RxMZhPC', 'Jane Smith', 'admin', 'active'),
(4, 'bob.jones', '$2y$10$vX8mJxGqYQFKmZKzqhHKJeK5xN.6YqWF8OQ0mLgxZ5NJY7RxMZhPC', 'Bob Jones', 'user', 'active'),
(5, 'viewer.demo', '$2y$10$vX8mJxGqYQFKmZKzqhHKJeK5xN.6YqWF8OQ0mLgxZ5NJY7RxMZhPC', 'Demo Viewer', 'viewer', 'active');

-- Note: All sample users have password: RoloDrawer2026!

-- Insert sample files
INSERT OR IGNORE INTO files (uuid, display_number, name, description, sensitivity, owner_id, current_drawer_id, entity_id, is_checked_out, is_archived) VALUES
('a1b2c3d4-e5f6-4789-a012-3456789abcde', '2024-001', 'Q4 Financial Report', '2024 Fourth Quarter financial statements and analysis', 'confidential', 1, 6, 8, 0, 0),
('b2c3d4e5-f6a7-4890-b123-456789abcdef', '2024-002', 'Employee Handbook v3.2', 'Updated employee handbook with new policies', 'internal', 2, 9, 7, 0, 0),
('c3d4e5f6-a7b8-4901-c234-56789abcdef0', '2024-003', 'GlobalTech Master Agreement', 'Service agreement for IT infrastructure support', 'confidential', 1, 15, 4, 0, 0),
('d4e5f6a7-b8c9-4012-d345-6789abcdef01', '2024-004', 'Building Lease Contract', 'Lease agreement for Building A', 'confidential', 3, 15, NULL, 1, 0),
('e5f6a7b8-c9d0-4123-e456-789abcdef012', '2023-127', 'Annual Safety Inspection', '2023 workplace safety inspection report', 'internal', 2, 12, NULL, 0, 1),
('f6a7b8c9-d0e1-4234-f567-89abcdef0123', '2024-005', 'Insurance Policy - Liability', 'General liability insurance policy document', 'confidential', 1, 7, NULL, 0, 0),
('a7b8c9d0-e1f2-4345-a678-9abcdef01234', '2024-006', 'Acme Parts Catalog 2024', 'Product catalog from Acme Manufacturing', 'public', 4, 6, 5, 0, 0),
('b8c9d0e1-f2a3-4456-b789-abcdef012345', '2024-007', 'Board Meeting Minutes - Jan', 'January 2024 board meeting minutes', 'restricted', 1, 8, NULL, 0, 0),
('c9d0e1f2-a3b4-4567-c890-bcdef0123456', '2024-008', 'Marketing Plan Q1', 'Q1 2024 marketing strategy and budget', 'internal', 3, 7, NULL, 0, 0),
('d0e1f2a3-b4c5-4678-d901-cdef01234567', '2024-009', 'Tax Return - 2023', 'Corporate tax return for fiscal year 2023', 'confidential', 1, 8, 8, 1, 0);

-- Insert file tags
INSERT OR IGNORE INTO file_tags (file_id, tag_id) VALUES
(1, 7), (1, 10),  -- Q4 Financial Report: Tax Documents, 2024
(2, 9), (2, 10),  -- Employee Handbook: Employee Records, 2024
(3, 1), (3, 6), (3, 10),  -- GlobalTech Agreement: Contracts, Confidential, 2024
(4, 1), (4, 6), (4, 10),  -- Building Lease: Contracts, Confidential, 2024
(5, 10),  -- Safety Inspection: 2024
(6, 8), (6, 6), (6, 10),  -- Insurance: Insurance, Confidential, 2024
(7, 10),  -- Acme Catalog: 2024
(8, 6), (8, 10),  -- Board Minutes: Confidential, 2024
(9, 10),  -- Marketing Plan: 2024
(10, 7), (10, 6), (10, 5);  -- Tax Return: Tax Documents, Confidential, Urgent

-- Insert sample checkouts
INSERT OR IGNORE INTO file_checkouts (file_id, user_id, checked_out_at, expected_return_date, returned_at, notes) VALUES
(4, 3, datetime('now', '-5 days'), date('now', '+2 days'), NULL, 'Reviewing lease terms for renewal'),
(10, 2, datetime('now', '-3 days'), date('now', '+4 days'), NULL, 'Preparing tax filing documentation');

-- Update files to reflect current checkouts
UPDATE files SET is_checked_out = 1, checked_out_by = 3, checked_out_at = datetime('now', '-5 days'), expected_return_date = date('now', '+2 days') WHERE id = 4;
UPDATE files SET is_checked_out = 1, checked_out_by = 2, checked_out_at = datetime('now', '-3 days'), expected_return_date = date('now', '+4 days') WHERE id = 10;

-- Insert sample file movements
INSERT OR IGNORE INTO file_movements (file_id, from_drawer_id, to_drawer_id, moved_by, notes, moved_at) VALUES
(1, NULL, 6, 1, 'Initial placement', datetime('now', '-30 days')),
(3, NULL, 15, 1, 'Filed after signing', datetime('now', '-15 days')),
(4, 7, 15, 3, 'Moved to legal cabinet for review', datetime('now', '-10 days')),
(5, 6, 12, 2, 'Archived - inspection complete', datetime('now', '-60 days'));

-- Set archived status for sample archived file
UPDATE files SET archived_at = datetime('now', '-60 days'), archived_by = 2, archived_reason = '2023 documents archived per retention policy' WHERE id = 5;
