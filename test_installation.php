<?php
/**
 * RoloDrawer Installation Test Script
 * Version: 1.0.4
 *
 * This script tests your RoloDrawer installation to ensure everything is configured correctly.
 * Run this from your browser: http://yourdomain.com/rolodrawer/test_installation.php
 */

header('Content-Type: text/plain; charset=utf-8');

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║         RoloDrawer Installation Test - Version 1.0.4          ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$errors = 0;
$warnings = 0;
$passed = 0;

function test_result($test_name, $status, $message = '') {
    global $errors, $warnings, $passed;

    $width = 50;
    $dots = str_repeat('.', $width - strlen($test_name));

    if ($status === 'PASS') {
        echo "$test_name $dots ✓ PASS\n";
        $passed++;
    } elseif ($status === 'WARN') {
        echo "$test_name $dots ⚠ WARN\n";
        if ($message) echo "  └─ $message\n";
        $warnings++;
    } else {
        echo "$test_name $dots ✗ FAIL\n";
        if ($message) echo "  └─ $message\n";
        $errors++;
    }
}

// Test 1: PHP Version
echo "=== PHP Environment ===\n\n";
$php_version = phpversion();
test_result('PHP Version (' . $php_version . ')',
    version_compare($php_version, '7.4.0', '>=') ? 'PASS' : 'FAIL',
    version_compare($php_version, '7.4.0', '<') ? 'PHP 7.4+ required' : '');

// Test 2: Required Extensions
$required_extensions = ['pdo', 'pdo_sqlite', 'mbstring', 'json'];
foreach ($required_extensions as $ext) {
    test_result("Extension: $ext",
        extension_loaded($ext) ? 'PASS' : 'FAIL',
        !extension_loaded($ext) ? "Extension $ext is missing" : '');
}

// Test 3: File System
echo "\n=== File System ===\n\n";

$required_files = [
    'index.php' => 'Main application file',
    'src/Database/Database.php' => 'Database class',
    'storage/database/schema.sql' => 'Database schema'
];

foreach ($required_files as $file => $desc) {
    test_result($desc,
        file_exists(__DIR__ . '/' . $file) ? 'PASS' : 'FAIL',
        !file_exists(__DIR__ . '/' . $file) ? "Missing: $file" : '');
}

// Test 4: Directory Permissions
$required_dirs = [
    'storage/' => 0777,
    'storage/database/' => 0777,
    'storage/uploads/' => 0777,
    'storage/logs/' => 0777,
    'storage/backups/' => 0777
];

foreach ($required_dirs as $dir => $required_perms) {
    $full_path = __DIR__ . '/' . $dir;

    if (!file_exists($full_path)) {
        test_result("Directory: $dir", 'FAIL', "Directory does not exist");
        continue;
    }

    $perms = fileperms($full_path) & 0777;
    $is_writable = is_writable($full_path);

    if ($is_writable) {
        test_result("Writable: $dir", 'PASS');
    } else {
        test_result("Writable: $dir", 'FAIL',
            sprintf("Not writable (current: %o, need: %o)", $perms, $required_perms));
    }
}

// Test 5: Database
echo "\n=== Database ===\n\n";

try {
    require_once __DIR__ . '/src/Database/Database.php';
    use RoloDrawer\Database\Database;

    $db = Database::getInstance();
    test_result('Database Connection', 'PASS');

    // Check if tables exist
    $tables = ['users', 'files', 'locations', 'cabinets', 'drawers', 'tags', 'entities'];
    $existing_tables = [];

    foreach ($tables as $table) {
        try {
            $result = $db->fetchOne("SELECT COUNT(*) as count FROM $table");
            $existing_tables[] = $table;
            test_result("Table: $table", 'PASS', "({$result['count']} records)");
        } catch (Exception $e) {
            test_result("Table: $table", 'FAIL', "Table does not exist or is corrupted");
        }
    }

    // Check for admin user
    try {
        $admin = $db->fetchOne("SELECT * FROM users WHERE id = 1 AND role = 'admin'");
        if ($admin) {
            test_result('Admin User Exists', 'PASS', "Username: " . $admin['email']);
        } else {
            test_result('Admin User Exists', 'FAIL', 'No admin user found');
        }
    } catch (Exception $e) {
        test_result('Admin User Exists', 'FAIL', $e->getMessage());
    }

} catch (Exception $e) {
    test_result('Database Connection', 'FAIL', $e->getMessage());
}

// Test 6: Configuration
echo "\n=== Configuration ===\n\n";

$config_file = __DIR__ . '/config.php';
if (file_exists($config_file)) {
    test_result('Config File', 'PASS', 'Custom configuration loaded');
} else {
    test_result('Config File', 'WARN', 'Using default configuration');
}

// Test 7: Web Server
echo "\n=== Web Server ===\n\n";

$server_software = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
test_result('Server Software', 'PASS', $server_software);

$document_root = $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown';
test_result('Document Root', 'PASS', $document_root);

// Test 8: URLs and Paths
echo "\n=== URLs ===\n\n";

$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") .
            "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
test_result('Base URL', 'PASS', $base_url);

// Test 9: Security
echo "\n=== Security ===\n\n";

if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    test_result('HTTPS Enabled', 'PASS');
} else {
    test_result('HTTPS Enabled', 'WARN', 'Consider enabling HTTPS for production');
}

// Summary
echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║                        Test Summary                            ║\n";
echo "╠════════════════════════════════════════════════════════════════╣\n";
printf("║  ✓ Passed:  %-4d                                              ║\n", $passed);
printf("║  ⚠ Warnings: %-4d                                              ║\n", $warnings);
printf("║  ✗ Errors:  %-4d                                              ║\n", $errors);
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

if ($errors > 0) {
    echo "Status: FAILED - Please fix the errors above before using RoloDrawer.\n";
    echo "See INSTALLATION.md for troubleshooting help.\n";
} elseif ($warnings > 0) {
    echo "Status: PASSED with warnings - RoloDrawer should work, but review warnings.\n";
} else {
    echo "Status: ALL TESTS PASSED! ✓\n";
    echo "Your RoloDrawer installation is ready to use!\n";
    echo "\nNext steps:\n";
    echo "1. Delete this test file (test_installation.php)\n";
    echo "2. Access RoloDrawer: $base_url/\n";
    echo "3. Login with: admin@rolodrawer.local / RoloDrawer2026!\n";
    echo "4. Change your password immediately!\n";
}

echo "\n";
