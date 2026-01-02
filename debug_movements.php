<?php
define('ROLODRAWER_LOADED', true);
header('Content-Type: text/plain; charset=utf-8');

try {
    $dbPath = __DIR__ . '/storage/database/rolodrawer.sqlite';
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== FILE_MOVEMENTS TABLE COLUMNS ===\n";
    $info = $pdo->query("PRAGMA table_info(file_movements)")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($info as $col) {
        echo "- {$col['name']} ({$col['type']})\n";
    }

    echo "\n=== FILE_MOVEMENTS TABLE FOREIGN KEYS ===\n";
    $fks = $pdo->query("PRAGMA foreign_key_list(file_movements)")->fetchAll(PDO::FETCH_ASSOC);
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
