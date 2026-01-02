-- RoloDrawer Database Schema
-- SQLite database initialization
-- Version 1.0.0

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    name TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'user', -- admin, user, viewer
    status TEXT NOT NULL DEFAULT 'active', -- active, inactive, locked
    failed_login_attempts INTEGER DEFAULT 0,
    last_failed_login TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

-- User sessions table
CREATE TABLE IF NOT EXISTS user_sessions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    session_id TEXT NOT NULL,
    ip_address TEXT,
    user_agent TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    last_activity TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Locations table
CREATE TABLE IF NOT EXISTS locations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    building TEXT,
    floor TEXT,
    room TEXT,
    notes TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

-- Entities table (companies, organizations, departments)
CREATE TABLE IF NOT EXISTS entities (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    description TEXT,
    contact_info TEXT,
    notes TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

-- Cabinets table
CREATE TABLE IF NOT EXISTS cabinets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    label TEXT NOT NULL,
    location_id INTEGER,
    entity_id INTEGER,
    notes TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL,
    FOREIGN KEY (entity_id) REFERENCES entities(id) ON DELETE SET NULL
);

-- Drawers table
CREATE TABLE IF NOT EXISTS drawers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    cabinet_id INTEGER NOT NULL,
    label TEXT NOT NULL,
    position INTEGER DEFAULT 0,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cabinet_id) REFERENCES cabinets(id) ON DELETE CASCADE
);

-- Tags table
CREATE TABLE IF NOT EXISTS tags (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT UNIQUE NOT NULL,
    color TEXT DEFAULT '#3B82F6',
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

-- Files table
CREATE TABLE IF NOT EXISTS files (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    uuid TEXT UNIQUE NOT NULL,
    display_number TEXT UNIQUE NOT NULL,
    name TEXT NOT NULL,
    description TEXT,
    sensitivity TEXT DEFAULT 'internal', -- public, internal, confidential, restricted
    owner_id INTEGER NOT NULL,
    current_drawer_id INTEGER,
    entity_id INTEGER,
    is_checked_out INTEGER DEFAULT 0,
    checked_out_by INTEGER,
    checked_out_at TEXT,
    expected_return_date TEXT,
    is_archived INTEGER DEFAULT 0,
    archived_at TEXT,
    archived_by INTEGER,
    archived_reason TEXT,
    is_destroyed INTEGER DEFAULT 0,
    destroyed_at TEXT,
    destroyed_by INTEGER,
    destruction_method TEXT,
    destruction_witness TEXT,
    destruction_reason TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id),
    FOREIGN KEY (current_drawer_id) REFERENCES drawers(id) ON DELETE SET NULL,
    FOREIGN KEY (entity_id) REFERENCES entities(id) ON DELETE SET NULL,
    FOREIGN KEY (checked_out_by) REFERENCES users(id),
    FOREIGN KEY (archived_by) REFERENCES users(id),
    FOREIGN KEY (destroyed_by) REFERENCES users(id)
);

-- File tags junction table
CREATE TABLE IF NOT EXISTS file_tags (
    file_id INTEGER NOT NULL,
    tag_id INTEGER NOT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (file_id, tag_id),
    FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
);

-- File checkouts history
CREATE TABLE IF NOT EXISTS file_checkouts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    file_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    checked_out_at TEXT NOT NULL,
    expected_return_date TEXT,
    returned_at TEXT,
    notes TEXT,
    FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- File movements audit trail
CREATE TABLE IF NOT EXISTS file_movements (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    file_id INTEGER NOT NULL,
    from_drawer_id INTEGER,
    to_drawer_id INTEGER,
    moved_by INTEGER NOT NULL,
    notes TEXT,
    moved_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE,
    FOREIGN KEY (from_drawer_id) REFERENCES drawers(id),
    FOREIGN KEY (to_drawer_id) REFERENCES drawers(id),
    FOREIGN KEY (moved_by) REFERENCES users(id)
);

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_files_uuid ON files(uuid);
CREATE INDEX IF NOT EXISTS idx_files_display_number ON files(display_number);
CREATE INDEX IF NOT EXISTS idx_files_current_drawer ON files(current_drawer_id);
CREATE INDEX IF NOT EXISTS idx_files_entity ON files(entity_id);
CREATE INDEX IF NOT EXISTS idx_files_checked_out ON files(is_checked_out);
CREATE INDEX IF NOT EXISTS idx_files_archived ON files(is_archived);
CREATE INDEX IF NOT EXISTS idx_files_destroyed ON files(is_destroyed);
CREATE INDEX IF NOT EXISTS idx_file_checkouts_file ON file_checkouts(file_id);
CREATE INDEX IF NOT EXISTS idx_file_checkouts_user ON file_checkouts(user_id);
CREATE INDEX IF NOT EXISTS idx_file_movements_file ON file_movements(file_id);
CREATE INDEX IF NOT EXISTS idx_user_sessions_user ON user_sessions(user_id);
CREATE INDEX IF NOT EXISTS idx_user_sessions_session ON user_sessions(session_id);

-- Insert default admin user
-- Username: admin
-- Password: RoloDrawer2026!
INSERT INTO users (id, email, password, name, role, status) VALUES
(1, 'admin', '$2y$10$vX8mJxGqYQFKmZKzqhHKJeK5xN.6YqWF8OQ0mLgxZ5NJY7RxMZhPC', 'System Administrator', 'admin', 'active');

-- Insert some default tags (optional - can be commented out for completely empty database)
INSERT INTO tags (id, name, color) VALUES
(1, 'Contracts', '#3B82F6'),
(2, 'HR', '#8B5CF6'),
(3, 'Financial', '#10B981'),
(4, 'Legal', '#EF4444');
