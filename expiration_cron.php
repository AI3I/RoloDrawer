#!/usr/bin/env php
<?php
/**
 * RoloDrawer Expiration Reminder Cron Job
 *
 * This script should be run daily to track files needing expiration reminders.
 * It logs reminders to the expiration_reminders table which can be used to
 * trigger dashboard alerts or email notifications.
 *
 * Cron setup (run daily at 6 AM):
 * 0 6 * * * /usr/bin/php /path/to/rolodrawer/expiration_cron.php >> /path/to/rolodrawer/storage/logs/expiration_cron.log 2>&1
 *
 * For Plesk:
 * Schedule: Daily at 06:00
 * Command: php /var/www/vhosts/yourdomain.com/httpdocs/rolodrawer/expiration_cron.php
 */

require_once __DIR__ . '/src/Database/Database.php';

use RoloDrawer\Database\Database;

// Get database instance
$db = Database::getInstance();

echo "===========================================\n";
echo "RoloDrawer Expiration Reminder Cron Job\n";
echo "Started: " . date('Y-m-d H:i:s') . "\n";
echo "===========================================\n\n";

try {
    // Get all active files with expiration dates
    $filesWithExpiration = $db->fetchAll("
        SELECT id, display_number, name, expiration_date, owner_id
        FROM files
        WHERE expiration_date IS NOT NULL
        AND is_archived = 0
        AND is_destroyed = 0
        ORDER BY expiration_date
    ");

    echo "Found " . count($filesWithExpiration) . " files with expiration dates\n\n";

    $remindersCreated = 0;
    $today = date('Y-m-d');

    foreach ($filesWithExpiration as $file) {
        $expirationDate = $file['expiration_date'];
        $daysUntilExpiration = floor((strtotime($expirationDate) - strtotime($today)) / 86400);

        // Determine which reminder types apply
        $reminderTypes = [];

        // Before expiration reminders
        if ($daysUntilExpiration == 90) {
            $reminderTypes[] = '90_days_before';
        }
        if ($daysUntilExpiration == 60) {
            $reminderTypes[] = '60_days_before';
        }
        if ($daysUntilExpiration == 30) {
            $reminderTypes[] = '30_days_before';
        }

        // After expiration reminders
        if ($daysUntilExpiration == -30) {
            $reminderTypes[] = '30_days_after';
        }
        if ($daysUntilExpiration == -60) {
            $reminderTypes[] = '60_days_after';
        }
        if ($daysUntilExpiration == -90) {
            $reminderTypes[] = '90_days_after';
        }

        // Create reminder records for each type
        foreach ($reminderTypes as $reminderType) {
            // Check if reminder already exists
            $existing = $db->fetchOne("
                SELECT id FROM expiration_reminders
                WHERE file_id = ? AND reminder_type = ?
            ", [$file['id'], $reminderType]);

            if (!$existing) {
                // Create new reminder
                $db->query("
                    INSERT INTO expiration_reminders (file_id, reminder_type, recipient_user_id, sent_at)
                    VALUES (?, ?, ?, CURRENT_TIMESTAMP)
                ", [$file['id'], $reminderType, $file['owner_id']]);

                $remindersCreated++;
                echo "✓ Created $reminderType reminder for file #" . $file['display_number'] .
                     " (" . $file['name'] . ") - " . abs($daysUntilExpiration) . " days " .
                     ($daysUntilExpiration > 0 ? "until" : "since") . " expiration\n";
            }
        }
    }

    echo "\n===========================================\n";
    echo "Summary:\n";
    echo "- Files checked: " . count($filesWithExpiration) . "\n";
    echo "- Reminders created: $remindersCreated\n";
    echo "Completed: " . date('Y-m-d H:i:s') . "\n";
    echo "===========================================\n";

} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
