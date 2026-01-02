<?php
/**
 * RoloDrawer - Fix Foreign Key Constraints After Drawer Removal
 * This script removes the drawer foreign key from the files table
 */

define('ROLODRAWER_LOADED', true);
header('Content-Type: text/plain; charset=utf-8');

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║   RoloDrawer - Fix Drawer Foreign Key Constraint              ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

try {
    $dbPath = __DIR__ . '/storage/database/rolodrawer.sqlite';

    if (!file_exists($dbPath)) {
        die("Error: Database file not found at: $dbPath\n");
    }

    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Connected to database successfully.\n\n";

    // Check if fix is needed
    $fks = $pdo->query("PRAGMA foreign_key_list(files)")->fetchAll(PDO::FETCH_ASSOC);
    $hasDrawerFK = false;
    foreach ($fks as $fk) {
        if ($fk['table'] === 'drawers') {
            $hasDrawerFK = true;
            break;
        }
    }

    if (!$hasDrawerFK) {
        echo "✓ No drawer foreign key found. Database is already fixed.\n";
        exit(0);
    }

    echo "Found drawer foreign key constraint. Starting fix...\n\n";

    // Disable foreign key checks temporarily
    $pdo->exec("PRAGMA foreign_keys = OFF");

    echo "1. Creating new files table without drawer FK... ";

    // Create new table structure without drawer FK
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
    echo "✓ Done\n";

    echo "2. Copying data from old table to new table... ";
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
    $rowCount = $pdo->query("SELECT COUNT(*) FROM files_new")->fetchColumn();
    echo "✓ Done ($rowCount rows copied)\n";

    echo "3. Dropping old files table... ";
    $pdo->exec("DROP TABLE files");
    echo "✓ Done\n";

    echo "4. Renaming new table to 'files'... ";
    $pdo->exec("ALTER TABLE files_new RENAME TO files");
    echo "✓ Done\n";

    echo "5. Recreating indexes... ";
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_files_uuid ON files(uuid)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_files_display_number ON files(display_number)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_files_current_cabinet ON files(current_cabinet_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_files_entity ON files(entity_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_files_checked_out ON files(is_checked_out)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_files_archived ON files(is_archived)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_files_destroyed ON files(is_destroyed)");
    echo "✓ Done\n";

    echo "6. Re-enabling foreign key checks... ";
    $pdo->exec("PRAGMA foreign_keys = ON");
    echo "✓ Done\n";

    // Verify the fix
    echo "\n7. Verifying fix... ";
    $fks = $pdo->query("PRAGMA foreign_key_list(files)")->fetchAll(PDO::FETCH_ASSOC);
    $hasDrawerFK = false;
    foreach ($fks as $fk) {
        if ($fk['table'] === 'drawers') {
            $hasDrawerFK = true;
            break;
        }
    }

    if ($hasDrawerFK) {
        throw new Exception("Foreign key constraint still exists after migration!");
    }
    echo "✓ Done\n";

    echo "\n╔════════════════════════════════════════════════════════════════╗\n";
    echo "║                    Fix Complete!                               ║\n";
    echo "╚════════════════════════════════════════════════════════════════╝\n\n";

    echo "Summary:\n";
    echo "- Removed drawer foreign key constraint from files table\n";
    echo "- Preserved all $rowCount file records\n";
    echo "- All indexes recreated\n";
    echo "- Foreign key checks re-enabled\n\n";

    echo "You can now delete this script (fix_drawer_fk.php)\n";

} catch (Exception $e) {
    echo "\n✗ Fix failed: " . $e->getMessage() . "\n";
    echo "\nRolling back changes...\n";
    try {
        $pdo->exec("DROP TABLE IF EXISTS files_new");
        $pdo->exec("PRAGMA foreign_keys = ON");
        echo "Rollback complete.\n";
    } catch (Exception $e2) {
        echo "Rollback failed: " . $e2->getMessage() . "\n";
    }
    exit(1);
}
