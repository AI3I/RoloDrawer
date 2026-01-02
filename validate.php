<?php
/**
 * RoloDrawer Database Validation Script
 * Checks database integrity and identifies common issues
 */

header('Content-Type: text/plain; charset=utf-8');

echo "═══════════════════════════════════════════════════════════════\n";
echo "    RoloDrawer Database Validation - Version 1.0.1\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

require_once __DIR__ . '/src/Database/Database.php';
use RoloDrawer\Database\Database;

$db = Database::getInstance();
$issues = [];
$warnings = [];

echo "Running validation checks...\n\n";

// Check 1: Orphaned files
echo "[1/10] Checking for orphaned files (no drawer assigned)...\n";
$orphaned = $db->fetchAll("SELECT id, display_number, name FROM files WHERE current_drawer_id IS NULL AND is_destroyed = 0 AND is_archived = 0");
if (count($orphaned) > 0) {
    $warnings[] = count($orphaned) . " files have no drawer assigned";
    foreach ($orphaned as $file) {
        echo "  ⚠ File #{$file['display_number']}: {$file['name']}\n";
    }
} else {
    echo "  ✓ No orphaned files\n";
}

// Check 2: Invalid drawer references
echo "\n[2/10] Checking for invalid drawer references...\n";
$invalid_drawers = $db->fetchAll("SELECT f.id, f.display_number, f.current_drawer_id FROM files f LEFT JOIN drawers d ON f.current_drawer_id = d.id WHERE f.current_drawer_id IS NOT NULL AND d.id IS NULL");
if (count($invalid_drawers) > 0) {
    $issues[] = count($invalid_drawers) . " files reference non-existent drawers";
    foreach ($invalid_drawers as $file) {
        echo "  ✗ File #{$file['display_number']} references drawer ID {$file['current_drawer_id']} (missing)\n";
    }
} else {
    echo "  ✓ All drawer references valid\n";
}

// Check 3: Checkout status consistency
echo "\n[3/10] Checking checkout status consistency...\n";
$checkout_issues = $db->fetchAll("SELECT id, display_number FROM files WHERE is_checked_out = 1 AND (checked_out_by IS NULL OR checked_out_at IS NULL)");
if (count($checkout_issues) > 0) {
    $issues[] = count($checkout_issues) . " files have inconsistent checkout status";
    foreach ($checkout_issues as $file) {
        echo "  ✗ File #{$file['display_number']}: marked as checked out but missing data\n";
    }
} else {
    echo "  ✓ Checkout status consistent\n";
}

// Check 4: Duplicate display numbers
echo "\n[4/10] Checking for duplicate display numbers...\n";
$duplicates = $db->fetchAll("SELECT display_number, COUNT(*) as count FROM files GROUP BY display_number HAVING count > 1");
if (count($duplicates) > 0) {
    $issues[] = count($duplicates) . " duplicate display numbers found";
    foreach ($duplicates as $dup) {
        echo "  ✗ Display number '{$dup['display_number']}' used {$dup['count']} times\n";
    }
} else {
    echo "  ✓ No duplicate display numbers\n";
}

// Check 5: Empty drawers in cabinets
echo "\n[5/10] Checking cabinet structure...\n";
$empty_cabinets = $db->fetchAll("SELECT c.id, c.label FROM cabinets c LEFT JOIN drawers d ON c.id = d.cabinet_id WHERE d.id IS NULL");
if (count($empty_cabinets) > 0) {
    $warnings[] = count($empty_cabinets) . " cabinets have no drawers";
    foreach ($empty_cabinets as $cab) {
        echo "  ⚠ Cabinet '{$cab['label']}' has no drawers\n";
    }
} else {
    echo "  ✓ All cabinets have drawers\n";
}

// Check 6: User account status
echo "\n[6/10] Checking user accounts...\n";
$inactive_with_files = $db->fetchAll("SELECT u.id, u.name, COUNT(f.id) as file_count FROM users u INNER JOIN files f ON u.id = f.owner_id WHERE u.status != 'active' GROUP BY u.id");
if (count($inactive_with_files) > 0) {
    $warnings[] = count($inactive_with_files) . " inactive users still own files";
    foreach ($inactive_with_files as $user) {
        echo "  ⚠ User '{$user['name']}' (inactive) owns {$user['file_count']} files\n";
    }
} else {
    echo "  ✓ No inactive users with files\n";
}

// Check 7: Archived files
echo "\n[7/10] Checking archived files...\n";
$bad_archives = $db->fetchAll("SELECT id, display_number FROM files WHERE is_archived = 1 AND (archived_at IS NULL OR archived_by IS NULL)");
if (count($bad_archives) > 0) {
    $issues[] = count($bad_archives) . " archived files missing metadata";
    foreach ($bad_archives as $file) {
        echo "  ✗ File #{$file['display_number']}: archived but missing metadata\n";
    }
} else {
    echo "  ✓ All archived files have complete metadata\n";
}

// Check 8: Destroyed files
echo "\n[8/10] Checking destroyed files...\n";
$bad_destroyed = $db->fetchAll("SELECT id, display_number FROM files WHERE is_destroyed = 1 AND (destroyed_at IS NULL OR destroyed_by IS NULL)");
if (count($bad_destroyed) > 0) {
    $issues[] = count($bad_destroyed) . " destroyed files missing metadata";
    foreach ($bad_destroyed as $file) {
        echo "  ✗ File #{$file['display_number']}: destroyed but missing metadata\n";
    }
} else {
    echo "  ✓ All destroyed files have complete metadata\n";
}

// Check 9: Tag assignments
echo "\n[9/10] Checking tag assignments...\n";
$orphaned_tags = $db->fetchAll("SELECT ft.tag_id, t.name FROM file_tags ft LEFT JOIN tags t ON ft.tag_id = t.id WHERE t.id IS NULL");
if (count($orphaned_tags) > 0) {
    $issues[] = count($orphaned_tags) . " file-tag assignments reference missing tags";
    echo "  ✗ Found orphaned tag assignments\n";
} else {
    echo "  ✓ All tag assignments valid\n";
}

// Check 10: Database statistics
echo "\n[10/10] Gathering statistics...\n";
$stats = [
    'files' => $db->fetchOne("SELECT COUNT(*) as count FROM files WHERE is_destroyed = 0")['count'],
    'active_files' => $db->fetchOne("SELECT COUNT(*) as count FROM files WHERE is_destroyed = 0 AND is_archived = 0")['count'],
    'checked_out' => $db->fetchOne("SELECT COUNT(*) as count FROM files WHERE is_checked_out = 1")['count'],
    'archived' => $db->fetchOne("SELECT COUNT(*) as count FROM files WHERE is_archived = 1")['count'],
    'destroyed' => $db->fetchOne("SELECT COUNT(*) as count FROM files WHERE is_destroyed = 1")['count'],
    'locations' => $db->fetchOne("SELECT COUNT(*) as count FROM locations")['count'],
    'cabinets' => $db->fetchOne("SELECT COUNT(*) as count FROM cabinets")['count'],
    'drawers' => $db->fetchOne("SELECT COUNT(*) as count FROM drawers")['count'],
    'users' => $db->fetchOne("SELECT COUNT(*) as count FROM users")['count'],
];

echo "  • Total files: {$stats['files']}\n";
echo "  • Active files: {$stats['active_files']}\n";
echo "  • Checked out: {$stats['checked_out']}\n";
echo "  • Archived: {$stats['archived']}\n";
echo "  • Destroyed: {$stats['destroyed']}\n";
echo "  • Locations: {$stats['locations']}\n";
echo "  • Cabinets: {$stats['cabinets']}\n";
echo "  • Drawers: {$stats['drawers']}\n";
echo "  • Users: {$stats['users']}\n";

// Summary
echo "\n═══════════════════════════════════════════════════════════════\n";
echo "                      Validation Summary\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "Critical Issues: " . count($issues) . "\n";
echo "Warnings: " . count($warnings) . "\n\n";

if (count($issues) > 0) {
    echo "⚠ ISSUES FOUND:\n";
    foreach ($issues as $issue) {
        echo "  • $issue\n";
    }
    echo "\nRun repair.php to fix these issues.\n";
}

if (count($warnings) > 0) {
    echo "\n⚠ WARNINGS:\n";
    foreach ($warnings as $warning) {
        echo "  • $warning\n";
    }
}

if (count($issues) == 0 && count($warnings) == 0) {
    echo "✓ DATABASE IS HEALTHY\n";
    echo "No issues or warnings found.\n";
}

echo "\n";
