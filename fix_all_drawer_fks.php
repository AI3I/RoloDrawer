<?php
/**
 * RoloDrawer - Remove ALL Drawer Foreign Keys
 * Fixes both files and file_movements tables
 */

define('ROLODRAWER_LOADED', true);
header('Content-Type: text/plain; charset=utf-8');

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║   RoloDrawer - Remove ALL Drawer Foreign Keys                 ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

try {
    $dbPath = __DIR__ . '/storage/database/rolodrawer.sqlite';

    if (!file_exists($dbPath)) {
        die("Error: Database file not found at: $dbPath\n");
    }

    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Connected to database successfully.\n\n";

    // Disable foreign key checks
    $pdo->exec("PRAGMA foreign_keys = OFF");

    echo "=== FIXING FILES TABLE ===\n";

    echo "1. Creating new files table... ";
    $pdo->exec("DROP TABLE IF EXISTS files_new");
    $pdo->exec("
        CREATE TABLE files_new (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            uuid TEXT UNIQUE NOT NULL,
            display_number TEXT UNIQUE NOT NULL,
            name TEXT NOT NULL,
            description TEXT,
            sensitivity TEXT DEFAULT 'internal',
            owner_id INTEGER NOT NULL,
            current_cabinet_id INTEGER,
            vertical_position TEXT DEFAULT 'Not Specified',
            horizontal_position TEXT DEFAULT 'Not Specified',
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
            FOREIGN KEY (current_cabinet_id) REFERENCES cabinets(id) ON DELETE SET NULL,
            FOREIGN KEY (entity_id) REFERENCES entities(id) ON DELETE SET NULL,
            FOREIGN KEY (checked_out_by) REFERENCES users(id),
            FOREIGN KEY (archived_by) REFERENCES users(id),
            FOREIGN KEY (destroyed_by) REFERENCES users(id)
        )
    ");
    echo "✓\n";

    echo "2. Copying files data... ";
    $pdo->exec("
        INSERT INTO files_new
        (id, uuid, display_number, name, description, sensitivity, owner_id,
         current_cabinet_id, vertical_position, horizontal_position, entity_id,
         is_checked_out, checked_out_by, checked_out_at, expected_return_date,
         is_archived, archived_at, archived_by, archived_reason,
         is_destroyed, destroyed_at, destroyed_by, destruction_method,
         destruction_witness, destruction_reason, created_at, updated_at)
        SELECT
         id, uuid, display_number, name, description, sensitivity, owner_id,
         current_cabinet_id, vertical_position, horizontal_position, entity_id,
         is_checked_out, checked_out_by, checked_out_at, expected_return_date,
         is_archived, archived_at, archived_by, archived_reason,
         is_destroyed, destroyed_at, destroyed_by, destruction_method,
         destruction_witness, destruction_reason, created_at, updated_at
        FROM files
    ");
    $filesCount = $pdo->query("SELECT COUNT(*) FROM files_new")->fetchColumn();
    echo "✓ ($filesCount rows)\n";

    echo "3. Replacing files table... ";
    $pdo->exec("DROP TABLE files");
    $pdo->exec("ALTER TABLE files_new RENAME TO files");
    echo "✓\n";

    echo "4. Recreating files indexes... ";
    $pdo->exec("CREATE INDEX idx_files_uuid ON files(uuid)");
    $pdo->exec("CREATE INDEX idx_files_display_number ON files(display_number)");
    $pdo->exec("CREATE INDEX idx_files_current_cabinet ON files(current_cabinet_id)");
    $pdo->exec("CREATE INDEX idx_files_entity ON files(entity_id)");
    $pdo->exec("CREATE INDEX idx_files_checked_out ON files(is_checked_out)");
    $pdo->exec("CREATE INDEX idx_files_archived ON files(is_archived)");
    $pdo->exec("CREATE INDEX idx_files_destroyed ON files(is_destroyed)");
    echo "✓\n\n";

    echo "=== FIXING FILE_MOVEMENTS TABLE ===\n";

    echo "5. Creating new file_movements table... ";
    $pdo->exec("DROP TABLE IF EXISTS file_movements_new");
    $pdo->exec("
        CREATE TABLE file_movements_new (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            file_id INTEGER NOT NULL,
            from_cabinet_id INTEGER,
            to_cabinet_id INTEGER,
            moved_by INTEGER NOT NULL,
            notes TEXT,
            moved_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE,
            FOREIGN KEY (from_cabinet_id) REFERENCES cabinets(id),
            FOREIGN KEY (to_cabinet_id) REFERENCES cabinets(id),
            FOREIGN KEY (moved_by) REFERENCES users(id)
        )
    ");
    echo "✓\n";

    echo "6. Copying file_movements data... ";
    $pdo->exec("
        INSERT INTO file_movements_new
        (id, file_id, from_cabinet_id, to_cabinet_id, moved_by, notes, moved_at)
        SELECT
         id, file_id, from_cabinet_id, to_cabinet_id, moved_by, notes, moved_at
        FROM file_movements
    ");
    $movementsCount = $pdo->query("SELECT COUNT(*) FROM file_movements_new")->fetchColumn();
    echo "✓ ($movementsCount rows)\n";

    echo "7. Replacing file_movements table... ";
    $pdo->exec("DROP TABLE file_movements");
    $pdo->exec("ALTER TABLE file_movements_new RENAME TO file_movements");
    echo "✓\n";

    echo "8. Recreating file_movements indexes... ";
    $pdo->exec("CREATE INDEX idx_file_movements_file ON file_movements(file_id)");
    echo "✓\n\n";

    echo "9. Re-enabling foreign key checks... ";
    $pdo->exec("PRAGMA foreign_keys = ON");
    echo "✓\n";

    echo "\n╔════════════════════════════════════════════════════════════════╗\n";
    echo "║                    Fix Complete!                               ║\n";
    echo "╚════════════════════════════════════════════════════════════════╝\n\n";

    echo "Summary:\n";
    echo "- Fixed files table: $filesCount records preserved\n";
    echo "- Fixed file_movements table: $movementsCount records preserved\n";
    echo "- All drawer foreign keys removed\n";
    echo "- All indexes recreated\n\n";

    echo "You can now delete this script and test the move functionality!\n";

} catch (Exception $e) {
    echo "\n✗ Fix failed: " . $e->getMessage() . "\n";
    echo "\nAttempting rollback...\n";
    try {
        $pdo->exec("DROP TABLE IF EXISTS files_new");
        $pdo->exec("DROP TABLE IF EXISTS file_movements_new");
        $pdo->exec("PRAGMA foreign_keys = ON");
        echo "Rollback complete.\n";
    } catch (Exception $e2) {
        echo "Rollback failed: " . $e2->getMessage() . "\n";
    }
    exit(1);
}
