<?php
/**
 * RoloDrawer Database Repair Script
 * Fixes common database issues and inconsistencies
 */

header('Content-Type: text/plain; charset=utf-8');

echo "═══════════════════════════════════════════════════════════════\n";
echo "    RoloDrawer Database Repair - Version 1.0.1\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

require_once __DIR__ . '/src/Database/Database.php';
use RoloDrawer\Database\Database;

$db = Database::getInstance();
$fixed = 0;

echo "Starting repair operations...\n\n";

// Repair 1: Fix checkout status inconsistencies
echo "[1/6] Fixing checkout status inconsistencies...\n";
try {
    $result = $db->execute("UPDATE files SET is_checked_out = 0, checked_out_by = NULL, checked_out_at = NULL, expected_return_date = NULL WHERE is_checked_out = 1 AND (checked_out_by IS NULL OR checked_out_at IS NULL)");
    if ($result > 0) {
        echo "  ✓ Fixed $result files with inconsistent checkout status\n";
        $fixed += $result;
    } else {
        echo "  • No issues found\n";
    }
} catch (Exception $e) {
    echo "  ✗ Error: " . $e->getMessage() . "\n";
}

// Repair 2: Fix archived files missing metadata
echo "\n[2/6] Fixing archived files metadata...\n";
try {
    $result = $db->execute("UPDATE files SET archived_at = CURRENT_TIMESTAMP, archived_by = 1 WHERE is_archived = 1 AND archived_at IS NULL");
    if ($result > 0) {
        echo "  ✓ Fixed $result archived files\n";
        $fixed += $result;
    } else {
        echo "  • No issues found\n";
    }
} catch (Exception $e) {
    echo "  ✗ Error: " . $e->getMessage() . "\n";
}

// Repair 3: Fix destroyed files missing metadata
echo "\n[3/6] Fixing destroyed files metadata...\n";
try {
    $result = $db->execute("UPDATE files SET destroyed_at = CURRENT_TIMESTAMP, destroyed_by = 1 WHERE is_destroyed = 1 AND destroyed_at IS NULL");
    if ($result > 0) {
        echo "  ✓ Fixed $result destroyed files\n";
        $fixed += $result;
    } else {
        echo "  • No issues found\n";
    }
} catch (Exception $e) {
    echo "  ✗ Error: " . $e->getMessage() . "\n";
}

// Repair 4: Remove orphaned tag assignments
echo "\n[4/6] Removing orphaned tag assignments...\n";
try {
    $result = $db->execute("DELETE FROM file_tags WHERE tag_id NOT IN (SELECT id FROM tags)");
    if ($result > 0) {
        echo "  ✓ Removed $result orphaned tag assignments\n";
        $fixed += $result;
    } else {
        echo "  • No issues found\n";
    }
} catch (Exception $e) {
    echo "  ✗ Error: " . $e->getMessage() . "\n";
}

// Repair 5: Update schema if needed
echo "\n[5/6] Checking database schema...\n";
try {
    // Check if floor column exists in locations table
    $columns = $db->fetchAll("PRAGMA table_info(locations)");
    $hasFloor = false;
    foreach ($columns as $col) {
        if ($col['name'] === 'floor') {
            $hasFloor = true;
            break;
        }
    }

    if (!$hasFloor) {
        $db->execute("ALTER TABLE locations ADD COLUMN floor TEXT");
        echo "  ✓ Added 'floor' column to locations table\n";
        $fixed++;
    } else {
        echo "  • Schema is up to date\n";
    }
} catch (Exception $e) {
    echo "  ✗ Error: " . $e->getMessage() . "\n";
}

// Repair 6: Optimize database
echo "\n[6/6] Optimizing database...\n";
try {
    $db->execute("VACUUM");
    $db->execute("ANALYZE");
    echo "  ✓ Database optimized\n";
} catch (Exception $e) {
    echo "  ⚠ Warning: " . $e->getMessage() . "\n";
}

// Summary
echo "\n═══════════════════════════════════════════════════════════════\n";
echo "                      Repair Summary\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "Total fixes applied: $fixed\n\n";

if ($fixed > 0) {
    echo "✓ REPAIR COMPLETE\n";
    echo "Your database has been repaired.\n";
    echo "\nRecommendation: Run validate.php to verify all issues are resolved.\n";
} else {
    echo "✓ NO REPAIRS NEEDED\n";
    echo "Your database is in good condition.\n";
}

echo "\n";
