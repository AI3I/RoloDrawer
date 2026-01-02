<?php
define('ROLODRAWER_LOADED', true);
header('Content-Type: text/plain; charset=utf-8');

try {
    $dbPath = __DIR__ . '/storage/database/rolodrawer.sqlite';
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== TABLES ===\n";
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        echo "- $table\n";
    }

    echo "\n=== FILES TABLE COLUMNS ===\n";
    $fileInfo = $pdo->query("PRAGMA table_info(files)")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($fileInfo as $col) {
        echo "- {$col['name']} ({$col['type']})\n";
    }

    echo "\n=== FILES TABLE FOREIGN KEYS ===\n";
    $fks = $pdo->query("PRAGMA foreign_key_list(files)")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($fks)) {
        echo "No foreign keys\n";
    } else {
        foreach ($fks as $fk) {
            echo "- {$fk['from']} -> {$fk['table']}.{$fk['to']}\n";
        }
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
