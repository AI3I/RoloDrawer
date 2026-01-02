<?php
/**
 * RoloDrawer Database Migration - Add Position Fields
 * Version: 1.0.4
 *
 * This script adds vertical_position and horizontal_position fields to the files table
 * Run this once to upgrade existing installations
 */

define('ROLODRAWER_LOADED', true);
header('Content-Type: text/plain; charset=utf-8');

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║     RoloDrawer Migration - Add Position Fields (v1.0.4)       ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

try {
    $dbPath = __DIR__ . '/storage/database/rolodrawer.sqlite';

    if (!file_exists($dbPath)) {
        die("Error: Database file not found at: $dbPath\n");
    }

    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Connected to database successfully.\n\n";

    // Check if columns already exist
    $tableInfo = $pdo->query("PRAGMA table_info(files)")->fetchAll(PDO::FETCH_ASSOC);
    $columns = array_column($tableInfo, 'name');

    $needsVertical = !in_array('vertical_position', $columns);
    $needsHorizontal = !in_array('horizontal_position', $columns);

    if (!$needsVertical && !$needsHorizontal) {
        echo "✓ Position fields already exist. No migration needed.\n";
        exit(0);
    }

    echo "Starting migration...\n\n";

    // Add vertical_position column
    if ($needsVertical) {
        echo "Adding vertical_position column... ";
        $pdo->exec("ALTER TABLE files ADD COLUMN vertical_position TEXT");
        echo "✓ Done\n";
    } else {
        echo "✓ vertical_position column already exists\n";
    }

    // Add horizontal_position column
    if ($needsHorizontal) {
        echo "Adding horizontal_position column... ";
        $pdo->exec("ALTER TABLE files ADD COLUMN horizontal_position TEXT");
        echo "✓ Done\n";
    } else {
        echo "✓ horizontal_position column already exists\n";
    }

    echo "\n";

    // Set default values for existing files with drawer assignments
    echo "Setting default positions for existing files... ";
    $updated = $pdo->exec("
        UPDATE files
        SET vertical_position = 'Top',
            horizontal_position = 'Front'
        WHERE current_drawer_id IS NOT NULL
          AND (vertical_position IS NULL OR horizontal_position IS NULL)
    ");
    echo "✓ Done ($updated files updated)\n";

    echo "\n╔════════════════════════════════════════════════════════════════╗\n";
    echo "║                    Migration Complete!                         ║\n";
    echo "╚════════════════════════════════════════════════════════════════╝\n\n";

    echo "Summary:\n";
    echo "- vertical_position field: " . ($needsVertical ? "Added" : "Already existed") . "\n";
    echo "- horizontal_position field: " . ($needsHorizontal ? "Added" : "Already existed") . "\n";
    echo "- Existing files updated: $updated\n\n";

    echo "You can now delete this migration script (migrate_positions.php)\n";

} catch (Exception $e) {
    echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
    echo "\nPlease contact support or check the error log.\n";
    exit(1);
}
