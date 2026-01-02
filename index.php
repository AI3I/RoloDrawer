<?php
// RoloDrawer - Complete with Tags, Search, Entities, User Management & QR Code System
// QR Code Features:
// - File QR codes for quick lookup (displays on file detail page)
// - Print individual file labels (Avery 5160 compatible)
// - Bulk label printing for multiple files
// - QR lookup page for scanning codes
// - Drawer QR codes for location tracking
// - Mobile-friendly lookup interface

// Configure custom session directory
$sessionPath = __DIR__ . '/storage/sessions';
if (!is_dir($sessionPath)) {
    mkdir($sessionPath, 0700, true);
}
session_save_path($sessionPath);

// Configure session cookie parameters for better compatibility
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', 1);

session_start();

// Helper function to generate UUID v4
function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

// Helper function to generate random password
function generateRandomPassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%';
    return substr(str_shuffle(str_repeat($chars, ceil($length/strlen($chars)))), 0, $length);
}

// Helper function to check if a file is overdue
function isOverdue($expectedReturnDate) {
    if (!$expectedReturnDate) return false;
    return strtotime($expectedReturnDate) < strtotime('today');
}

// Helper function to calculate days overdue
function daysOverdue($expectedReturnDate) {
    if (!$expectedReturnDate) return 0;
    $days = floor((strtotime('today') - strtotime($expectedReturnDate)) / 86400);
    return max(0, $days);
}

// Helper function to generate QR Code URL
function generateQRCodeURL($data, $size = 200) {
    return "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data=" . urlencode($data);
}

// Helper function to get base URL for QR codes
function getBaseURL() {
    // Automatically detect the current base URL
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $baseDir = dirname($scriptName);

    // Normalize the base directory (remove trailing slash if it's just root)
    $baseDir = ($baseDir === '/' || $baseDir === '\\') ? '' : $baseDir;

    return $protocol . '://' . $host . $baseDir . '/';
}

// Simple autoloader
spl_autoload_register(function ($class) {
    $prefix = 'RoloDrawer\\';
    $base_dir = __DIR__ . '/src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) require $file;
});

use RoloDrawer\Database\Database;

$db = Database::getInstance();
// Initialize database if not already initialized
if (!$db->isInitialized()) {
    $db->initializeDatabase();
}

$page = $_GET['page'] ?? 'dashboard';
$action = $_GET['action'] ?? 'index';
$id = $_GET['id'] ?? null;

// Handle logout
if ($page === 'logout') {
    // Delete session from database
    if (isset($_SESSION['session_db_id'])) {
        $db->query("DELETE FROM user_sessions WHERE session_id = ?", [$_SESSION['session_db_id']]);
    }
    session_destroy();
    header('Location: ?page=login');
    exit;
}

// Handle CSV exports (must be before any HTML output)
$format = $_GET['format'] ?? '';
if ($format === 'csv' && $page === 'reports') {
    $report = $_GET['report'] ?? '';

    function exportCSV($data, $filename, $headers) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $output = fopen('php://output', 'w');
        fputcsv($output, $headers);
        foreach ($data as $row) { fputcsv($output, $row); }
        fclose($output);
        exit;
    }

    if ($report === 'by_location') {
        $files = $db->fetchAll("SELECT f.*, d.label as drawer_label, c.label as cabinet_label, l.name as location_name, e.name as entity_name, u.name as owner_name FROM files f LEFT JOIN drawers d ON f.current_drawer_id = d.id LEFT JOIN cabinets c ON d.cabinet_id = c.id LEFT JOIN locations l ON c.location_id = l.id LEFT JOIN entities e ON f.entity_id = e.id LEFT JOIN users u ON f.owner_id = u.id WHERE f.is_archived = 0 AND f.is_destroyed = 0 ORDER BY l.name, c.label, d.label");
        $data = array_map(fn($f) => [$f['location_name'] ?: 'No Location', $f['cabinet_label'] ?: 'No Cabinet', $f['drawer_label'] ?: 'No Drawer', $f['display_number'], $f['name'], $f['owner_name'], $f['sensitivity']], $files);
        exportCSV($data, 'files_by_location_'.date('Y-m-d').'.csv', ['Location','Cabinet','Drawer','File #','Name','Owner','Sensitivity']);
    } elseif ($report === 'by_entity') {
        $files = $db->fetchAll("SELECT f.*, e.name as entity_name, u.name as owner_name FROM files f LEFT JOIN entities e ON f.entity_id = e.id LEFT JOIN users u ON f.owner_id = u.id WHERE f.is_destroyed = 0 ORDER BY e.name, f.display_number");
        $data = array_map(fn($f) => [$f['entity_name'] ?: 'No Entity', $f['display_number'], $f['name'], $f['owner_name'], $f['sensitivity'], $f['is_archived'] ? 'Yes' : 'No'], $files);
        exportCSV($data, 'files_by_entity_'.date('Y-m-d').'.csv', ['Entity','File #','Name','Owner','Sensitivity','Archived']);
    } elseif ($report === 'by_tag') {
        $tags = $db->fetchAll("SELECT t.*, COUNT(ft.file_id) as file_count FROM tags t LEFT JOIN file_tags ft ON t.id = ft.tag_id GROUP BY t.id ORDER BY file_count DESC, t.name");
        $data = array_map(fn($t) => [$t['name'], $t['file_count'], $t['color']], $tags);
        exportCSV($data, 'files_by_tag_'.date('Y-m-d').'.csv', ['Tag','File Count','Color']);
    } elseif ($report === 'checkouts') {
        $currentCheckouts = $db->fetchAll("SELECT f.*, u.name as checked_out_to FROM files f LEFT JOIN users u ON f.checked_out_by = u.id WHERE f.is_checked_out = 1 ORDER BY f.expected_return_date");
        $data = array_map(fn($f) => [$f['display_number'], $f['name'], $f['checked_out_to'], $f['checked_out_at'], $f['expected_return_date'], daysOverdue($f['expected_return_date'])], $currentCheckouts);
        exportCSV($data, 'checkout_status_'.date('Y-m-d').'.csv', ['File #','Name','Checked Out To','Date','Expected Return','Days Overdue']);
    } elseif ($report === 'overdue') {
        $overdueFiles = $db->fetchAll("SELECT f.*, u.name as checked_out_to, u.email as user_email FROM files f LEFT JOIN users u ON f.checked_out_by = u.id WHERE f.is_checked_out = 1 AND f.expected_return_date < DATE('now') ORDER BY f.expected_return_date");
        $data = array_map(fn($f) => [$f['display_number'], $f['name'], $f['checked_out_to'], $f['user_email'], $f['expected_return_date'], daysOverdue($f['expected_return_date'])], $overdueFiles);
        exportCSV($data, 'overdue_files_'.date('Y-m-d').'.csv', ['File #','Name','Checked Out To','Email','Expected Return','Days Overdue']);
    }
}

// Check if logged in
if (!isset($_SESSION['user_id']) && $page !== 'login') {
    header('Location: ?page=login');
    exit;
}

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // USER MANAGEMENT HANDLERS

    // CREATE USER
    if ($page === 'users' && $action === 'create') {
        // Check admin permission
        if ($_SESSION['user_role'] !== 'admin') {
            $message = "Unauthorized: Only admins can create users";
            $messageType = "error";
        } else {
            $email = $_POST['email'] ?? '';
            $name = $_POST['name'] ?? '';
            $password = $_POST['password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            $role = $_POST['role'] ?? 'user';
            $status = $_POST['status'] ?? 'active';
            $generatePassword = isset($_POST['generate_password']);

            // Validation
            if (empty($email) || empty($name)) {
                $message = "Email and name are required";
                $messageType = "error";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $message = "Invalid email format";
                $messageType = "error";
            } else {
                // Check if email already exists
                $existingUser = $db->fetchOne("SELECT id FROM users WHERE email = ?", [$email]);
                if ($existingUser) {
                    $message = "Email already exists";
                    $messageType = "error";
                } else {
                    // Generate password if requested
                    if ($generatePassword) {
                        $password = generateRandomPassword(12);
                        $confirmPassword = $password;
                    }

                    // Validate password
                    if (strlen($password) < 8) {
                        $message = "Password must be at least 8 characters";
                        $messageType = "error";
                    } elseif ($password !== $confirmPassword) {
                        $message = "Passwords do not match";
                        $messageType = "error";
                    } else {
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        $db->query("INSERT INTO users (email, password, name, role, status) VALUES (?, ?, ?, ?, ?)",
                                    [$email, $hashedPassword, $name, $role, $status]);

                        $message = "User created successfully" . ($generatePassword ? " with password: $password" : "");
                        $messageType = "success";
                        header("Location: ?page=users&message=" . urlencode($message));
                        exit;
                    }
                }
            }
        }
    }

    // EDIT USER
    if ($page === 'users' && $action === 'edit' && $id) {
        // Check admin permission
        if ($_SESSION['user_role'] !== 'admin') {
            $message = "Unauthorized: Only admins can edit users";
            $messageType = "error";
        } else {
            $email = $_POST['email'] ?? '';
            $name = $_POST['name'] ?? '';
            $role = $_POST['role'] ?? 'user';
            $status = $_POST['status'] ?? 'active';

            // Validation
            if (empty($email) || empty($name)) {
                $message = "Email and name are required";
                $messageType = "error";
            } else {
                // Check if email already exists for another user
                $existingUser = $db->fetchOne("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $id]);
                if ($existingUser) {
                    $message = "Email already exists for another user";
                    $messageType = "error";
                } else {
                    $db->query("UPDATE users SET email = ?, name = ?, role = ?, status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?",
                                [$email, $name, $role, $status, $id]);

                    $message = "User updated successfully";
                    header("Location: ?page=users&action=view&id=$id&message=" . urlencode($message));
                    exit;
                }
            }
        }
    }

    // RESET PASSWORD (Admin resetting another user's password)
    if ($page === 'users' && $action === 'reset_password' && $id) {
        // Check admin permission
        if ($_SESSION['user_role'] !== 'admin') {
            $message = "Unauthorized: Only admins can reset passwords";
            $messageType = "error";
        } else {
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (strlen($newPassword) < 8) {
                $message = "Password must be at least 8 characters";
                $messageType = "error";
            } elseif ($newPassword !== $confirmPassword) {
                $message = "Passwords do not match";
                $messageType = "error";
            } else {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $db->query("UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?",
                            [$hashedPassword, $id]);

                $message = "Password reset successfully";
                header("Location: ?page=users&action=view&id=$id&message=" . urlencode($message));
                exit;
            }
        }
    }

    // CHANGE OWN PASSWORD
    if ($page === 'users' && $action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // Get current user
        $user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);

        if (!password_verify($currentPassword, $user['password'])) {
            $message = "Current password is incorrect";
            $messageType = "error";
        } elseif (strlen($newPassword) < 8) {
            $message = "New password must be at least 8 characters";
            $messageType = "error";
        } elseif ($newPassword !== $confirmPassword) {
            $message = "New passwords do not match";
            $messageType = "error";
        } else {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $db->query("UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?",
                        [$hashedPassword, $_SESSION['user_id']]);

            $message = "Password changed successfully";
            $messageType = "success";
            header("Location: ?page=users&action=change_password&message=" . urlencode($message));
            exit;
        }
    }

    // FORCE LOGOUT
    if ($page === 'users' && $action === 'force_logout' && $id) {
        // Check admin permission
        if ($_SESSION['user_role'] !== 'admin') {
            $message = "Unauthorized: Only admins can force logout";
            $messageType = "error";
        } else {
            // Get session_id if provided
            $sessionId = $_POST['session_id'] ?? null;

            if ($sessionId) {
                // Delete specific session
                $db->query("DELETE FROM user_sessions WHERE session_id = ?", [$sessionId]);
                $message = "Session terminated successfully";
            } else {
                // Delete all sessions for the user
                $db->query("DELETE FROM user_sessions WHERE user_id = ?", [$id]);
                $message = "All sessions terminated successfully";
            }

            $messageType = "success";
            header("Location: ?page=users&action=sessions&message=" . urlencode($message));
            exit;
        }
    }

    // TOGGLE STATUS
    if ($page === 'users' && $action === 'toggle_status' && $id) {
        // Check admin permission
        if ($_SESSION['user_role'] !== 'admin') {
            $message = "Unauthorized: Only admins can change user status";
            $messageType = "error";
        } else {
            $newStatus = $_POST['status'] ?? 'active';
            $lockedUntil = null;

            // If locking, set locked_until to 24 hours from now
            if ($newStatus === 'locked') {
                $lockedUntil = date('Y-m-d H:i:s', strtotime('+24 hours'));
                $db->query("UPDATE users SET status = ?, locked_until = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?",
                            [$newStatus, $lockedUntil, $id]);
            } else {
                // Clear locked_until when unlocking
                $db->query("UPDATE users SET status = ?, locked_until = NULL, failed_login_attempts = 0, updated_at = CURRENT_TIMESTAMP WHERE id = ?",
                            [$newStatus, $id]);
            }

            $message = "User status updated to " . $newStatus;
            header("Location: ?page=users&message=" . urlencode($message));
            exit;
        }
    }

    // FILE CREATE with tags and entity
    if ($page === 'files' && $action === 'create') {
        $uuid = generateUUID();
        $displayNumber = $_POST['display_number'] ?? '';
        $name = $_POST['name'] ?? '';
        $description = $_POST['description'] ?? '';
        $sensitivity = $_POST['sensitivity'] ?? 'internal';
        $ownerId = $_SESSION['user_id'];
        $drawerId = !empty($_POST['drawer_id']) ? $_POST['drawer_id'] : null;
        $verticalPosition = $_POST['vertical_position'] ?? 'Not Specified';
        $horizontalPosition = $_POST['horizontal_position'] ?? 'Not Specified';
        $entityId = !empty($_POST['entity_id']) ? $_POST['entity_id'] : null;

        $db->query("INSERT INTO files (uuid, display_number, name, description, sensitivity, owner_id, current_drawer_id, vertical_position, horizontal_position, entity_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [$uuid, $displayNumber, $name, $description, $sensitivity, $ownerId, $drawerId, $verticalPosition, $horizontalPosition, $entityId]);

        $fileId = $db->lastInsertId();

        // Handle tags
        if (!empty($_POST['tags'])) {
            foreach ($_POST['tags'] as $tagId) {
                $db->query("INSERT INTO file_tags (file_id, tag_id) VALUES (?, ?)", [$fileId, $tagId]);
            }
        }

        $message = "File created successfully!";
        $messageType = "success";
        header("Location: ?page=files&action=view&id=$fileId&message=" . urlencode($message));
        exit;
    }

    // FILE EDIT with tags and entity
    if ($page === 'files' && $action === 'edit' && $id) {
        $name = $_POST['name'] ?? '';
        $description = $_POST['description'] ?? '';
        $sensitivity = $_POST['sensitivity'] ?? 'internal';
        $drawerId = !empty($_POST['drawer_id']) ? $_POST['drawer_id'] : null;
        $verticalPosition = $_POST['vertical_position'] ?? 'Not Specified';
        $horizontalPosition = $_POST['horizontal_position'] ?? 'Not Specified';
        $entityId = !empty($_POST['entity_id']) ? $_POST['entity_id'] : null;

        // MOVEMENT TRACKING: Fetch current drawer_id before update
        $currentFile = $db->fetchOne("SELECT current_drawer_id FROM files WHERE id = ?", [$id]);
        $oldDrawerId = $currentFile['current_drawer_id'];

        $db->query("UPDATE files SET name = ?, description = ?, sensitivity = ?, current_drawer_id = ?, vertical_position = ?, horizontal_position = ?, entity_id = ?, updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?",
                    [$name, $description, $sensitivity, $drawerId, $verticalPosition, $horizontalPosition, $entityId, $id]);

        // MOVEMENT TRACKING: Log movement if drawer changed
        if ($oldDrawerId != $drawerId) {
            $db->query("INSERT INTO file_movements (file_id, from_drawer_id, to_drawer_id, moved_by, notes, moved_at)
                       VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)",
                       [$id, $oldDrawerId, $drawerId, $_SESSION['user_id'], 'Moved via edit form']);
        }

        // Update tags - remove old ones and add new ones
        $db->query("DELETE FROM file_tags WHERE file_id = ?", [$id]);
        if (!empty($_POST['tags'])) {
            foreach ($_POST['tags'] as $tagId) {
                $db->query("INSERT INTO file_tags (file_id, tag_id) VALUES (?, ?)", [$id, $tagId]);
            }
        }

        $message = "File updated successfully!";
        header("Location: ?page=files&action=view&id=$id&message=" . urlencode($message));
        exit;
    }

    // TAG CREATE
    if ($page === 'tags' && $action === 'create') {
        $name = $_POST['name'] ?? '';
        $color = $_POST['color'] ?? '#3B82F6';

        $db->query("INSERT INTO tags (name, color) VALUES (?, ?)", [$name, $color]);

        header("Location: ?page=tags&message=Tag created successfully");
        exit;
    }

    // ENTITY CREATE
    if ($page === 'entities' && $action === 'create') {
        $name = $_POST['name'] ?? '';
        $description = $_POST['description'] ?? '';
        $contactInfo = $_POST['contact_info'] ?? '';
        $notes = $_POST['notes'] ?? '';

        $db->query("INSERT INTO entities (name, description, contact_info, notes) VALUES (?, ?, ?, ?)",
                    [$name, $description, $contactInfo, $notes]);

        header("Location: ?page=entities&message=Entity created successfully");
        exit;
    }

    // ENTITY EDIT
    if ($page === 'entities' && $action === 'edit' && $id) {
        $name = $_POST['name'] ?? '';
        $description = $_POST['description'] ?? '';
        $contactInfo = $_POST['contact_info'] ?? '';
        $notes = $_POST['notes'] ?? '';

        $db->query("UPDATE entities SET name = ?, description = ?, contact_info = ?, notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?",
                    [$name, $description, $contactInfo, $notes, $id]);

        header("Location: ?page=entities&message=Entity updated successfully");
        exit;
    }

    // LOCATION OPERATIONS
    if ($page === 'locations' && $action === 'create') {
        $name = $_POST['name'] ?? '';
        $building = $_POST['building'] ?? '';
        $floor = $_POST['floor'] ?? '';
        $room = $_POST['room'] ?? '';
        $notes = $_POST['notes'] ?? '';

        $db->query("INSERT INTO locations (name, building, floor, room, notes) VALUES (?, ?, ?, ?, ?)",
                    [$name, $building, $floor, $room, $notes]);

        header("Location: ?page=locations&message=Location created");
        exit;
    }

    if ($page === 'locations' && $action === 'edit' && $id) {
        $name = $_POST['name'] ?? '';
        $building = $_POST['building'] ?? '';
        $floor = $_POST['floor'] ?? '';
        $room = $_POST['room'] ?? '';
        $notes = $_POST['notes'] ?? '';

        $db->query("UPDATE locations SET name = ?, building = ?, floor = ?, room = ?, notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?",
                    [$name, $building, $floor, $room, $notes, $id]);

        header("Location: ?page=locations&message=Location updated");
        exit;
    }

    // CABINET OPERATIONS
    if ($page === 'cabinets' && $action === 'create') {
        $label = $_POST['label'] ?? '';
        $locationId = $_POST['location_id'] ?? null;
        $entityId = !empty($_POST['entity_id']) ? $_POST['entity_id'] : null;
        $notes = $_POST['notes'] ?? '';

        $db->query("INSERT INTO cabinets (label, location_id, entity_id, notes) VALUES (?, ?, ?, ?)",
                    [$label, $locationId, $entityId, $notes]);

        header("Location: ?page=locations&message=Cabinet created");
        exit;
    }

    if ($page === 'cabinets' && $action === 'edit' && $id) {
        $label = $_POST['label'] ?? '';
        $locationId = $_POST['location_id'] ?? null;
        $entityId = !empty($_POST['entity_id']) ? $_POST['entity_id'] : null;
        $notes = $_POST['notes'] ?? '';

        $db->query("UPDATE cabinets SET label = ?, location_id = ?, entity_id = ?, notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?",
                    [$label, $locationId, $entityId, $notes, $id]);

        header("Location: ?page=locations&message=Cabinet updated");
        exit;
    }

    // DRAWER OPERATIONS
    if ($page === 'drawers' && $action === 'create') {
        $label = $_POST['label'] ?? '';
        $cabinetId = $_POST['cabinet_id'] ?? '';
        $position = $_POST['position'] ?? 0;

        $db->query("INSERT INTO drawers (cabinet_id, label, position) VALUES (?, ?, ?)",
                    [$cabinetId, $label, $position]);

        header("Location: ?page=locations&message=Drawer created");
        exit;
    }

    if ($page === 'drawers' && $action === 'edit' && $id) {
        $label = $_POST['label'] ?? '';
        $cabinetId = $_POST['cabinet_id'] ?? '';
        $position = $_POST['position'] ?? 0;

        $db->query("UPDATE drawers SET cabinet_id = ?, label = ?, position = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?",
                    [$cabinetId, $label, $position, $id]);

        header("Location: ?page=locations&message=Drawer updated");
        exit;
    }

    // CHECKOUT HANDLER
    if ($page === 'files' && $action === 'checkout' && $id) {
        // Get the file to check if it's already checked out
        $file = $db->fetchOne("SELECT * FROM files WHERE id = ?", [$id]);

        if (!$file) {
            $message = "File not found";
            $messageType = "error";
        } elseif ($file['is_checked_out']) {
            $message = "File is already checked out";
            $messageType = "error";
        } else {
            // Determine which user to check out to
            if ($_SESSION['user_role'] === 'admin' && !empty($_POST['user_id'])) {
                $userId = $_POST['user_id'];
            } else {
                $userId = $_SESSION['user_id'];
            }

            $expectedReturnDate = $_POST['expected_return_date'] ?? null;
            $notes = $_POST['notes'] ?? '';

            // Validation
            if (!$expectedReturnDate) {
                $message = "Expected return date is required";
                $messageType = "error";
            } else {
                // Insert into file_checkouts table
                $db->query("INSERT INTO file_checkouts (file_id, user_id, checked_out_at, expected_return_date, notes)
                           VALUES (?, ?, CURRENT_TIMESTAMP, ?, ?)",
                           [$id, $userId, $expectedReturnDate, $notes]);

                // Update files table
                $db->query("UPDATE files SET is_checked_out = 1, checked_out_by = ?, checked_out_at = CURRENT_TIMESTAMP, expected_return_date = ?
                           WHERE id = ?",
                           [$userId, $expectedReturnDate, $id]);

                $message = "File checked out successfully";
                $messageType = "success";
                header("Location: ?page=files&action=view&id=$id&message=" . urlencode($message));
                exit;
            }
        }
    }

    // CHECKIN HANDLER
    if ($page === 'files' && $action === 'checkin' && $id) {
        // Get the file to check if it's checked out
        $file = $db->fetchOne("SELECT * FROM files WHERE id = ?", [$id]);

        if (!$file) {
            $message = "File not found";
            $messageType = "error";
        } elseif (!$file['is_checked_out']) {
            $message = "File is not checked out";
            $messageType = "error";
        } else {
            // Check permissions - only admin or the person who checked it out can check it back in
            if ($_SESSION['user_role'] !== 'admin' && $file['checked_out_by'] != $_SESSION['user_id']) {
                $message = "Unauthorized: Only admins or the person who checked out the file can check it back in";
                $messageType = "error";
            } else {
                $returnNotes = $_POST['return_notes'] ?? '';

                // Update file_checkouts table
                if ($returnNotes) {
                    $db->query("UPDATE file_checkouts
                               SET returned_at = CURRENT_TIMESTAMP,
                                   notes = CASE WHEN notes IS NULL OR notes = '' THEN ? ELSE notes || '\n\nReturn: ' || ? END
                               WHERE file_id = ? AND returned_at IS NULL",
                               [$returnNotes, $returnNotes, $id]);
                } else {
                    $db->query("UPDATE file_checkouts SET returned_at = CURRENT_TIMESTAMP
                               WHERE file_id = ? AND returned_at IS NULL",
                               [$id]);
                }

                // Update files table
                $db->query("UPDATE files SET is_checked_out = 0, checked_out_by = NULL, checked_out_at = NULL, expected_return_date = NULL
                           WHERE id = ?",
                           [$id]);

                $message = "File checked in successfully";
                $messageType = "success";
                header("Location: ?page=files&action=view&id=$id&message=" . urlencode($message));
                exit;
            }
        }
    }

    // MANUAL FILE MOVE HANDLER
    if ($page === 'files' && $action === 'move' && $id) {
        // Get the file to check status
        $file = $db->fetchOne("SELECT * FROM files WHERE id = ?", [$id]);

        if (!$file) {
            $message = "File not found";
            $messageType = "error";
        } else {
            // Movement validation
            $newDrawerId = !empty($_POST['new_drawer_id']) ? $_POST['new_drawer_id'] : null;
            $moveNotes = $_POST['move_notes'] ?? '';
            $errors = [];

            // Cannot move a file that's checked out (unless admin)
            if ($file['is_checked_out'] && $_SESSION['user_role'] !== 'admin') {
                $errors[] = "Cannot move a checked out file (admin override required)";
            }

            // Cannot move destroyed files
            if ($file['is_destroyed']) {
                $errors[] = "Cannot move destroyed files";
            }

            // Warn if moving archived files
            if ($file['is_archived'] && empty($_POST['confirm_archived'])) {
                $errors[] = "This file is archived. Check the confirmation box to proceed with the move.";
            }

            if (empty($errors)) {
                $oldDrawerId = $file['current_drawer_id'];

                // Update file location
                $db->query("UPDATE files SET current_drawer_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?",
                          [$newDrawerId, $id]);

                // Log movement
                $db->query("INSERT INTO file_movements (file_id, from_drawer_id, to_drawer_id, moved_by, notes, moved_at)
                           VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)",
                           [$id, $oldDrawerId, $newDrawerId, $_SESSION['user_id'], $moveNotes ?: 'Manual move']);

                $message = "File moved successfully!";
                $messageType = "success";
                header("Location: ?page=files&action=view&id=$id&message=" . urlencode($message));
                exit;
            } else {
                $message = implode(', ', $errors);
                $messageType = "error";
            }
        }
    }

    // BULK MOVE HANDLER
    if ($page === 'files' && $action === 'bulk_move') {
        $fileIds = $_POST['file_ids'] ?? [];
        $newDrawerId = !empty($_POST['bulk_drawer_id']) ? $_POST['bulk_drawer_id'] : null;
        $bulkNotes = $_POST['bulk_notes'] ?? 'Bulk move operation';

        if (empty($fileIds)) {
            $message = "No files selected for bulk move";
            $messageType = "error";
        } else {
            $movedCount = 0;
            $errorCount = 0;
            $errors = [];

            foreach ($fileIds as $fileId) {
                $file = $db->fetchOne("SELECT * FROM files WHERE id = ?", [$fileId]);

                if (!$file) {
                    $errors[] = "File ID $fileId not found";
                    $errorCount++;
                    continue;
                }

                // Movement validation
                $canMove = true;
                $reason = '';

                // Cannot move a file that's checked out (unless admin)
                if ($file['is_checked_out'] && $_SESSION['user_role'] !== 'admin') {
                    $canMove = false;
                    $reason = "checked out";
                }

                // Cannot move destroyed files
                if ($file['is_destroyed']) {
                    $canMove = false;
                    $reason = "destroyed";
                }

                if ($canMove) {
                    $oldDrawerId = $file['current_drawer_id'];

                    // Update file location
                    $db->query("UPDATE files SET current_drawer_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?",
                              [$newDrawerId, $fileId]);

                    // Log movement
                    $db->query("INSERT INTO file_movements (file_id, from_drawer_id, to_drawer_id, moved_by, notes, moved_at)
                               VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)",
                               [$fileId, $oldDrawerId, $newDrawerId, $_SESSION['user_id'], $bulkNotes]);

                    $movedCount++;
                } else {
                    $errors[] = "File #{$file['display_number']} ($reason)";
                    $errorCount++;
                }
            }

            if ($movedCount > 0 && $errorCount === 0) {
                $message = "$movedCount file(s) moved successfully!";
                $messageType = "success";
            } elseif ($movedCount > 0 && $errorCount > 0) {
                $message = "$movedCount file(s) moved, $errorCount failed: " . implode(', ', $errors);
                $messageType = "success";
            } else {
                $message = "Bulk move failed: " . implode(', ', $errors);
                $messageType = "error";
            }

            header("Location: ?page=files&message=" . urlencode($message));
            exit;
        }
    }

    // ARCHIVE HANDLER
    if ($page === 'files' && $action === 'archive' && $id) {
        // Check admin permission
        if ($_SESSION['user_role'] !== 'admin') {
            $message = "Unauthorized: Only admins can archive files";
            $messageType = "error";
        } else {
            $archivedReason = $_POST['archived_reason'] ?? '';

            if (empty($archivedReason)) {
                $message = "Archive reason is required";
                $messageType = "error";
            } else {
                $db->query("UPDATE files SET is_archived = 1, archived_at = CURRENT_TIMESTAMP, archived_reason = ?, archived_by = ? WHERE id = ?",
                            [$archivedReason, $_SESSION['user_id'], $id]);

                $message = "File archived successfully";
                $messageType = "success";
                header("Location: ?page=files&action=view&id=$id&message=" . urlencode($message));
                exit;
            }
        }
    }

    // RESTORE HANDLER
    if ($page === 'files' && $action === 'restore' && $id) {
        // Check admin permission
        if ($_SESSION['user_role'] !== 'admin') {
            $message = "Unauthorized: Only admins can restore files";
            $messageType = "error";
        } else {
            $db->query("UPDATE files SET is_archived = 0, archived_at = NULL, archived_reason = NULL, archived_by = NULL WHERE id = ?",
                        [$id]);

            $message = "File restored from archive";
            $messageType = "success";
            header("Location: ?page=files&action=view&id=$id&message=" . urlencode($message));
            exit;
        }
    }

    // MARK FOR DESTRUCTION HANDLER
    if ($page === 'files' && $action === 'mark_destruction' && $id) {
        // Check admin permission
        if ($_SESSION['user_role'] !== 'admin') {
            $message = "Unauthorized: Only admins can mark files as destroyed";
            $messageType = "error";
        } else {
            $destructionMethod = $_POST['destruction_method'] ?? '';
            $notes = $_POST['notes'] ?? '';
            $confirmed = isset($_POST['confirm_destruction']);

            // Validation
            if (empty($destructionMethod)) {
                $message = "Destruction method is required";
                $messageType = "error";
            } elseif (!$confirmed) {
                $message = "You must confirm that the file has been properly destroyed";
                $messageType = "error";
            } else {
                // Check if file is archived (business rule: must archive before destroy)
                $file = $db->fetchOne("SELECT is_archived, is_destroyed FROM files WHERE id = ?", [$id]);

                if (!$file) {
                    $message = "File not found";
                    $messageType = "error";
                } elseif ($file['is_destroyed']) {
                    $message = "File is already marked as destroyed";
                    $messageType = "error";
                } elseif (!$file['is_archived']) {
                    $message = "File must be archived before it can be destroyed";
                    $messageType = "error";
                } else {
                    // Combine method and notes
                    $fullMethod = $destructionMethod;
                    if (!empty($notes)) {
                        $fullMethod .= ' - ' . $notes;
                    }

                    $db->query("UPDATE files SET is_destroyed = 1, destroyed_at = CURRENT_TIMESTAMP, destroyed_by = ?, destruction_method = ? WHERE id = ?",
                                [$_SESSION['user_id'], $fullMethod, $id]);

                    $message = "File marked as destroyed";
                    $messageType = "success";
                    header("Location: ?page=files&action=view&id=$id&message=" . urlencode($message));
                    exit;
                }
            }
        }
    }

    // RESTORE FROM DESTRUCTION HANDLER
    if ($page === 'files' && $action === 'restore_destruction' && $id) {
        // Check admin permission
        if ($_SESSION['user_role'] !== 'admin') {
            $message = "Unauthorized: Only admins can restore destroyed files";
            $messageType = "error";
        } else {
            $confirmed = isset($_POST['confirm_restore']);

            if (!$confirmed) {
                $message = "You must confirm restoration from destroyed state";
                $messageType = "error";
            } else {
                $db->query("UPDATE files SET is_destroyed = 0, destroyed_at = NULL, destroyed_by = NULL, destruction_method = NULL WHERE id = ?",
                            [$id]);

                $message = "File restored from destroyed state (correction applied)";
                $messageType = "success";
                header("Location: ?page=files&action=view&id=$id&message=" . urlencode($message));
                exit;
            }
        }
    }
}

// Handle GET message parameter
if (isset($_GET['message'])) {
    $message = $_GET['message'];
    $messageType = $_GET['messageType'] ?? 'success';
}

// Load all tags for use in forms
$allTags = $db->fetchAll("SELECT * FROM tags ORDER BY name");

// Load all entities for use in forms
$allEntities = $db->fetchAll("SELECT * FROM entities ORDER BY name");

// Handle label printing - must exit before main layout
if ($page === 'labels' && $action === 'print' && !empty($_GET['file_ids'])) {
    $fileIds = array_map('intval', $_GET['file_ids']);
    $placeholders = str_repeat('?,', count($fileIds) - 1) . '?';
    $selectedFiles = $db->fetchAll("SELECT * FROM files WHERE id IN ($placeholders) ORDER BY display_number", $fileIds);

    if (!empty($selectedFiles)):
?>
<!DOCTYPE html>
<html>
<head>
    <title>Print Labels</title>
    <style>
        @page {
            size: letter;
            margin: 0.5in;
        }

        body {
            margin: 0;
            padding: 20px;
            font-family: Arial, sans-serif;
        }

        .label-sheet {
            width: 8.5in;
            margin: 0 auto;
        }

        .label {
            width: 2.625in;
            height: 1in;
            padding: 0.05in;
            border: 1px dashed #ccc;
            display: inline-block;
            margin: 0;
            page-break-inside: avoid;
            box-sizing: border-box;
            vertical-align: top;
        }

        .label-content {
            display: flex;
            align-items: center;
            height: 100%;
            gap: 5px;
        }

        .label-qr {
            flex-shrink: 0;
            width: 80px;
            height: 80px;
        }

        .label-qr img {
            width: 100%;
            height: 100%;
        }

        .label-info {
            flex: 1;
            min-width: 0;
        }

        .label-number {
            font-weight: bold;
            font-size: 10px;
            margin-bottom: 2px;
        }

        .label-name {
            font-size: 9px;
            line-height: 1.2;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        .label-sensitivity {
            display: inline-block;
            padding: 1px 4px;
            font-size: 7px;
            border-radius: 2px;
            margin-top: 2px;
        }

        .sensitivity-public { background: #10B981; color: white; }
        .sensitivity-internal { background: #3B82F6; color: white; }
        .sensitivity-confidential { background: #F59E0B; color: white; }
        .sensitivity-restricted { background: #EF4444; color: white; }

        .no-print {
            margin-bottom: 20px;
        }

        @media print {
            body {
                padding: 0;
            }
            .no-print {
                display: none;
            }
            .label {
                border: none;
            }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <h2>Label Sheet Preview</h2>
        <p>Labels: <?= count($selectedFiles) ?> files | Format: Avery 5160</p>
        <button onclick="window.print()" style="padding: 10px 20px; background: #3b82f6; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; margin-right: 10px;">
            Print Labels
        </button>
        <button onclick="window.close()" style="padding: 10px 20px; background: #6b7280; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px;">
            Close
        </button>
    </div>

    <div class="label-sheet">
        <?php foreach ($selectedFiles as $file):
            $lookupURL = getBaseURL() . '?page=lookup&uuid=' . urlencode($file['uuid']);
            $qrCodeURL = generateQRCodeURL($lookupURL, 100);
        ?>
        <div class="label">
            <div class="label-content">
                <div class="label-qr">
                    <img src="<?= $qrCodeURL ?>" alt="QR">
                </div>
                <div class="label-info">
                    <div class="label-number">FILE #<?= htmlspecialchars($file['display_number']) ?></div>
                    <div class="label-name"><?= htmlspecialchars($file['name']) ?></div>
                    <span class="label-sensitivity sensitivity-<?= $file['sensitivity'] ?>">
                        <?= strtoupper($file['sensitivity']) ?>
                    </span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</body>
</html>
<?php
    exit;
    endif;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RoloDrawer</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-100">

<?php if ($page === 'login'): ?>
    <!-- Login Page -->
    <div class="min-h-screen flex items-center justify-center">
        <div class="bg-white p-8 rounded-lg shadow-md w-96">
            <h1 class="text-2xl font-bold mb-6 text-center flex items-center justify-center gap-2">
                <span class="text-5xl">🗂️</span>
                <span>RoloDrawer</span>
            </h1>
            <?php
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $email = $_POST['email'] ?? '';
                $password = $_POST['password'] ?? '';

                // DEBUG: Log attempt
                error_log("Login attempt for: $email");

                $user = $db->fetchOne("SELECT * FROM users WHERE email = ?", [$email]);

                if ($user) {
                    // Check account status
                    if ($user['status'] !== 'active') {
                        echo '<div class="bg-red-100 text-red-700 p-3 rounded mb-4">Account is ' . htmlspecialchars($user['status']) . '</div>';
                    } elseif ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                        $lockedUntil = date('Y-m-d H:i:s', strtotime($user['locked_until']));
                        echo '<div class="bg-red-100 text-red-700 p-3 rounded mb-4">Account is locked until ' . htmlspecialchars($lockedUntil) . '</div>';
                    } elseif (password_verify($password, $user['password'])) {
                        // DEBUG: Password verified
                        error_log("Password verified for user ID: " . $user['id']);
                        // Auto-unlock if locked_until has passed
                        if ($user['locked_until'] && strtotime($user['locked_until']) <= time()) {
                            $db->query("UPDATE users SET status = 'active', locked_until = NULL, failed_login_attempts = 0 WHERE id = ?", [$user['id']]);
                        }

                        // Reset failed login attempts
                        $db->query("UPDATE users SET failed_login_attempts = 0, last_login = CURRENT_TIMESTAMP WHERE id = ?", [$user['id']]);

                        // Create session in database
                        $sessionId = session_id();
                        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

                        $db->query("INSERT INTO user_sessions (session_id, user_id, ip_address, user_agent, last_activity) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)",
                                    [$sessionId, $user['id'], $ipAddress, $userAgent]);

                        // Set session variables
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_name'] = $user['name'];
                        $_SESSION['user_role'] = $user['role'];
                        $_SESSION['session_db_id'] = $sessionId;

                        // DEBUG: Show what we're setting
                        error_log("Session set - user_id: {$_SESSION['user_id']}, session_id: " . session_id());

                        // Force session write before redirect
                        session_write_close();

                        header('Location: ?page=dashboard');
                        exit;
                    } else {
                        // Increment failed login attempts
                        $failedAttempts = $user['failed_login_attempts'] + 1;

                        if ($failedAttempts >= 5) {
                            // Lock account for 24 hours
                            $lockedUntil = date('Y-m-d H:i:s', strtotime('+24 hours'));
                            $db->query("UPDATE users SET failed_login_attempts = ?, status = 'locked', locked_until = ? WHERE id = ?",
                                        [$failedAttempts, $lockedUntil, $user['id']]);
                            echo '<div class="bg-red-100 text-red-700 p-3 rounded mb-4">Account locked due to too many failed login attempts</div>';
                        } else {
                            $db->query("UPDATE users SET failed_login_attempts = ? WHERE id = ?", [$failedAttempts, $user['id']]);
                            $remaining = 5 - $failedAttempts;
                            echo '<div class="bg-red-100 text-red-700 p-3 rounded mb-4">Invalid credentials (' . $remaining . ' attempts remaining)</div>';
                        }
                    }
                } else {
                    echo '<div class="bg-red-100 text-red-700 p-3 rounded mb-4">Invalid credentials</div>';
                }
            }
            ?>
            <form method="POST">
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2">Email</label>
                    <input type="email" name="email" required class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500" value="admin@rolodrawer.local">
                </div>
                <div class="mb-6">
                    <label class="block text-gray-700 mb-2">Password</label>
                    <input type="password" name="password" required class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700">Login</button>
            </form>
        </div>
    </div>

<?php else: ?>
    <!-- Main App -->
    <div class="flex h-screen">
        <!-- Sidebar -->
        <div class="w-64 bg-gray-800 text-white flex-shrink-0">
            <div class="p-4">
                <h1 class="text-2xl font-bold flex items-center gap-2">
                    <span class="text-3xl">🗂️</span>
                    <span>RoloDrawer</span>
                </h1>
                <p class="text-sm text-gray-400"><?= htmlspecialchars($_SESSION['user_name']) ?></p>
                <p class="text-xs text-gray-500"><?= ucfirst($_SESSION['user_role']) ?></p>
            </div>
            <nav class="mt-4">
                <a href="?page=dashboard" class="block px-4 py-2 <?= $page === 'dashboard' ? 'bg-gray-700' : 'hover:bg-gray-700' ?>">
                    <span class="inline-block w-5">📊</span> Dashboard
                </a>
                <a href="?page=files" class="block px-4 py-2 <?= $page === 'files' ? 'bg-gray-700' : 'hover:bg-gray-700' ?>">
                    <span class="inline-block w-5">📄</span> Files
                </a>
                <a href="?page=search" class="block px-4 py-2 pl-8 <?= $page === 'search' ? 'bg-gray-700' : 'hover:bg-gray-700' ?>">
                    <span class="inline-block w-5">🔍</span> Search
                </a>
                <a href="?page=lookup" class="block px-4 py-2 pl-8 <?= $page === 'lookup' ? 'bg-gray-700' : 'hover:bg-gray-700' ?>">
                    <span class="inline-block w-5">🔍</span> QR Lookup
                </a>
                <a href="?page=labels" class="block px-4 py-2 pl-8 <?= $page === 'labels' ? 'bg-gray-700' : 'hover:bg-gray-700' ?>">
                    <span class="inline-block w-5">🏷️</span> Print Labels
                </a>
                <?php
                // Get user checkout count for badge
                $myCheckoutCount = $db->fetchOne("SELECT COUNT(*) as count FROM files WHERE checked_out_by = ? AND is_checked_out = 1", [$_SESSION['user_id']])['count'];
                ?>
                <a href="?page=my-checkouts" class="block px-4 py-2 <?= $page === 'my-checkouts' ? 'bg-gray-700' : 'hover:bg-gray-700' ?> flex justify-between items-center">
                    <span><span class="inline-block w-5">📤</span> My Checkouts</span>
                    <?php if ($myCheckoutCount > 0): ?>
                        <span class="bg-orange-500 text-white text-xs px-2 py-1 rounded-full"><?= $myCheckoutCount ?></span>
                    <?php endif; ?>
                </a>
                <a href="?page=archived" class="block px-4 py-2 <?= $page === 'archived' ? 'bg-gray-700' : 'hover:bg-gray-700' ?>">
                    <span class="inline-block w-5">📦</span> Archive
                </a>
                <hr class="my-4 border-gray-700">
                <a href="?page=entities" class="block px-4 py-2 <?= $page === 'entities' ? 'bg-gray-700' : 'hover:bg-gray-700' ?>">
                    <span class="inline-block w-5">🏢</span> Entities
                </a>
                <a href="?page=locations" class="block px-4 py-2 <?= $page === 'locations' ? 'bg-gray-700' : 'hover:bg-gray-700' ?>">
                    <span class="inline-block w-5">📍</span> Locations
                </a>
                <a href="?page=tags" class="block px-4 py-2 <?= $page === 'tags' ? 'bg-gray-700' : 'hover:bg-gray-700' ?>">
                    <span class="inline-block w-5">🏷️</span> Tags
                </a>
                <?php if ($_SESSION['user_role'] === 'admin'): ?>
                <hr class="my-4 border-gray-700">
                <a href="?page=checkouts" class="block px-4 py-2 <?= $page === 'checkouts' ? 'bg-gray-700' : 'hover:bg-gray-700' ?>">
                    <span class="inline-block w-5">📋</span> All Checkouts
                </a>
                <a href="?page=movements" class="block px-4 py-2 <?= $page === 'movements' ? 'bg-gray-700' : 'hover:bg-gray-700' ?>">
                    <span class="inline-block w-5">🔄</span> Movements
                </a>
                <a href="?page=destroyed" class="block px-4 py-2 <?= $page === 'destroyed' ? 'bg-gray-700' : 'hover:bg-gray-700' ?>">
                    <span class="inline-block w-5">🗑️</span> Destroyed Files
                </a>
                <a href="?page=reports" class="block px-4 py-2 <?= $page === 'reports' ? 'bg-gray-700' : 'hover:bg-gray-700' ?>">
                    <span class="inline-block w-5">📈</span> Reports
                </a>
                <?php endif; ?>
                <hr class="my-4 border-gray-700">
                <?php if ($_SESSION['user_role'] === 'admin'): ?>
                <a href="?page=users" class="block px-4 py-2 <?= $page === 'users' ? 'bg-gray-700' : 'hover:bg-gray-700' ?>">
                    <span class="inline-block w-5">👥</span> Users
                </a>
                <?php endif; ?>
                <a href="?page=users&action=change_password" class="block px-4 py-2 hover:bg-gray-700">
                    <span class="inline-block w-5">🔐</span> Change Password
                </a>
                <a href="?page=logout" class="block px-4 py-2 hover:bg-gray-700">
                    <span class="inline-block w-5">🚪</span> Logout
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="flex-1 overflow-auto">
            <div class="p-8">
                <?php if ($message): ?>
                    <div class="mb-4 p-4 rounded <?= $messageType === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <?php if ($page === 'dashboard'): ?>
                    <!-- Dashboard -->
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-3xl font-bold">Dashboard</h2>
                        <div class="text-sm text-gray-600">
                            Welcome back, <span class="font-semibold"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
                        </div>
                    </div>

                    <?php
                    $stats = [
                        'files' => $db->fetchOne("SELECT COUNT(*) as count FROM files WHERE is_archived = 0 AND is_destroyed = 0")['count'],
                        'checked_out' => $db->fetchOne("SELECT COUNT(*) as count FROM files WHERE is_checked_out = 1")['count'],
                        'overdue' => $db->fetchOne("SELECT COUNT(*) as count FROM files WHERE is_checked_out = 1 AND expected_return_date < DATE('now')")['count'],
                        'archived' => $db->fetchOne("SELECT COUNT(*) as count FROM files WHERE is_archived = 1 AND is_destroyed = 0")['count'],
                        'destroyed' => $db->fetchOne("SELECT COUNT(*) as count FROM files WHERE is_destroyed = 1")['count'],
                        'cabinets' => $db->fetchOne("SELECT COUNT(*) as count FROM cabinets")['count'],
                        'locations' => $db->fetchOne("SELECT COUNT(*) as count FROM locations")['count'],
                        'drawers' => $db->fetchOne("SELECT COUNT(*) as count FROM drawers")['count'],
                        'tags' => $db->fetchOne("SELECT COUNT(*) as count FROM tags")['count'],
                        'entities' => $db->fetchOne("SELECT COUNT(*) as count FROM entities")['count'],
                    ];

                    $recentCheckouts = $db->fetchAll("SELECT f.*, u.name as checked_out_to FROM files f LEFT JOIN users u ON f.checked_out_by = u.id WHERE f.is_checked_out = 1 ORDER BY f.checked_out_at DESC LIMIT 5");
                    $topTags = $db->fetchAll("SELECT t.name, t.color, COUNT(ft.file_id) as usage FROM tags t LEFT JOIN file_tags ft ON t.id = ft.tag_id GROUP BY t.id ORDER BY usage DESC LIMIT 5");
                    ?>

                    <!-- Main Stats Grid -->
                    <div class="grid grid-cols-3 gap-6 mb-8">
                        <!-- Files Card -->
                        <div class="bg-gradient-to-br from-blue-500 to-blue-600 p-6 rounded-lg shadow-lg text-white hover:shadow-xl transition-shadow">
                            <div class="flex items-center justify-between mb-2">
                                <div class="text-4xl">📄</div>
                                <div class="text-right">
                                    <div class="text-4xl font-bold"><?= $stats['files'] ?></div>
                                    <div class="text-blue-100 text-sm">Active Files</div>
                                </div>
                            </div>
                            <a href="?page=files" class="text-sm text-blue-100 hover:text-white underline">View all files →</a>
                        </div>

                        <!-- Checked Out Card -->
                        <div class="bg-gradient-to-br from-orange-500 to-orange-600 p-6 rounded-lg shadow-lg text-white hover:shadow-xl transition-shadow">
                            <div class="flex items-center justify-between mb-2">
                                <div class="text-4xl">📤</div>
                                <div class="text-right">
                                    <div class="text-4xl font-bold"><?= $stats['checked_out'] ?></div>
                                    <div class="text-orange-100 text-sm">Checked Out</div>
                                </div>
                            </div>
                            <a href="?page=reports&report=checkouts" class="text-sm text-orange-100 hover:text-white underline">View checkouts →</a>
                        </div>

                        <!-- Overdue Card -->
                        <div class="bg-gradient-to-br from-<?= $stats['overdue'] > 0 ? 'red' : 'green' ?>-500 to-<?= $stats['overdue'] > 0 ? 'red' : 'green' ?>-600 p-6 rounded-lg shadow-lg text-white hover:shadow-xl transition-shadow <?= $stats['overdue'] > 0 ? 'ring-4 ring-red-300 animate-pulse' : '' ?>">
                            <div class="flex items-center justify-between mb-2">
                                <div class="text-4xl"><?= $stats['overdue'] > 0 ? '⚠️' : '✅' ?></div>
                                <div class="text-right">
                                    <div class="text-4xl font-bold"><?= $stats['overdue'] ?></div>
                                    <div class="text-<?= $stats['overdue'] > 0 ? 'red' : 'green' ?>-100 text-sm">Overdue Files</div>
                                </div>
                            </div>
                            <?php if ($stats['overdue'] > 0): ?>
                                <a href="?page=reports&report=overdue" class="text-sm text-red-100 hover:text-white underline">View overdue files →</a>
                            <?php else: ?>
                                <div class="text-sm text-green-100">All files on time!</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Secondary Stats Grid -->
                    <div class="grid grid-cols-4 gap-4 mb-8">
                        <div class="bg-white p-5 rounded-lg shadow hover:shadow-md transition-shadow border-l-4 border-purple-500">
                            <div class="flex items-center gap-3">
                                <div class="text-3xl">📍</div>
                                <div>
                                    <div class="text-2xl font-bold text-purple-600"><?= $stats['locations'] ?></div>
                                    <div class="text-sm text-gray-600">Locations</div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white p-5 rounded-lg shadow hover:shadow-md transition-shadow border-l-4 border-green-500">
                            <div class="flex items-center gap-3">
                                <div class="text-3xl">🗄️</div>
                                <div>
                                    <div class="text-2xl font-bold text-green-600"><?= $stats['cabinets'] ?></div>
                                    <div class="text-sm text-gray-600">Cabinets</div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white p-5 rounded-lg shadow hover:shadow-md transition-shadow border-l-4 border-indigo-500">
                            <div class="flex items-center gap-3">
                                <div class="text-3xl">📦</div>
                                <div>
                                    <div class="text-2xl font-bold text-indigo-600"><?= $stats['drawers'] ?></div>
                                    <div class="text-sm text-gray-600">Drawers</div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white p-5 rounded-lg shadow hover:shadow-md transition-shadow border-l-4 border-pink-500">
                            <div class="flex items-center gap-3">
                                <div class="text-3xl">🏷️</div>
                                <div>
                                    <div class="text-2xl font-bold text-pink-600"><?= $stats['tags'] ?></div>
                                    <div class="text-sm text-gray-600">Tags</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="bg-white rounded-lg shadow p-6 mb-8">
                        <h3 class="text-xl font-bold mb-4 flex items-center gap-2">
                            <span class="text-2xl">⚡</span> Quick Actions
                        </h3>
                        <div class="grid grid-cols-4 gap-4">
                            <a href="?page=files&action=create" class="flex items-center gap-3 p-4 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors group">
                                <div class="text-3xl">➕</div>
                                <div>
                                    <div class="font-semibold text-blue-700 group-hover:text-blue-800">Add New File</div>
                                    <div class="text-xs text-gray-600">Create file record</div>
                                </div>
                            </a>
                            <a href="?page=lookup" class="flex items-center gap-3 p-4 bg-green-50 hover:bg-green-100 rounded-lg transition-colors group">
                                <div class="text-3xl">🔍</div>
                                <div>
                                    <div class="font-semibold text-green-700 group-hover:text-green-800">Find a File</div>
                                    <div class="text-xs text-gray-600">Search or scan QR</div>
                                </div>
                            </a>
                            <a href="?page=locations" class="flex items-center gap-3 p-4 bg-purple-50 hover:bg-purple-100 rounded-lg transition-colors group">
                                <div class="text-3xl">🗺️</div>
                                <div>
                                    <div class="font-semibold text-purple-700 group-hover:text-purple-800">Browse Locations</div>
                                    <div class="text-xs text-gray-600">View hierarchy</div>
                                </div>
                            </a>
                            <a href="?page=reports" class="flex items-center gap-3 p-4 bg-orange-50 hover:bg-orange-100 rounded-lg transition-colors group">
                                <div class="text-3xl">📊</div>
                                <div>
                                    <div class="font-semibold text-orange-700 group-hover:text-orange-800">View Reports</div>
                                    <div class="text-xs text-gray-600">Analytics & exports</div>
                                </div>
                            </a>
                        </div>
                    </div>

                    <!-- Two Column Layout for Recent Activity -->
                    <div class="grid grid-cols-2 gap-6 mb-8">
                        <!-- Recent Checkouts -->
                        <?php if (!empty($recentCheckouts)): ?>
                            <div class="bg-white rounded-lg shadow p-6">
                                <div class="flex justify-between items-center mb-4">
                                    <h3 class="text-xl font-bold flex items-center gap-2">
                                        <span class="text-2xl">📤</span> Recent Checkouts
                                    </h3>
                                    <a href="?page=reports&report=checkouts" class="text-blue-600 hover:underline text-sm">View All</a>
                                </div>
                                <div class="space-y-2">
                                    <?php foreach ($recentCheckouts as $c): ?>
                                        <div class="flex items-start gap-3 p-3 hover:bg-gray-50 rounded border-l-4 <?= isOverdue($c['expected_return_date']) ? 'border-red-500 bg-red-50' : 'border-blue-500' ?>">
                                            <div class="flex-1">
                                                <div class="font-medium text-sm">
                                                    <span class="font-mono text-gray-600">#<?= htmlspecialchars($c['display_number']) ?></span>
                                                    <span class="ml-2"><?= htmlspecialchars($c['name']) ?></span>
                                                </div>
                                                <div class="text-xs text-gray-600 mt-1">
                                                    Checked out to: <span class="font-medium"><?= htmlspecialchars($c['checked_out_to']) ?></span>
                                                </div>
                                                <?php if ($c['expected_return_date']): ?>
                                                    <div class="text-xs mt-1 <?= isOverdue($c['expected_return_date']) ? 'text-red-600 font-semibold' : 'text-gray-500' ?>">
                                                        Due: <?= date('M j, Y', strtotime($c['expected_return_date'])) ?>
                                                        <?php if (isOverdue($c['expected_return_date'])): ?>
                                                            <span class="ml-1">(<?= daysOverdue($c['expected_return_date']) ?> days overdue)</span>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Top Tags -->
                        <?php if (!empty($topTags)): ?>
                            <div class="bg-white rounded-lg shadow p-6">
                                <div class="flex justify-between items-center mb-4">
                                    <h3 class="text-xl font-bold flex items-center gap-2">
                                        <span class="text-2xl">🏷️</span> Most Used Tags
                                    </h3>
                                    <a href="?page=tags" class="text-blue-600 hover:underline text-sm">View All</a>
                                </div>
                                <div class="space-y-3">
                                    <?php $maxUsage = max(array_column($topTags, 'usage')); ?>
                                    <?php foreach ($topTags as $tag): ?>
                                        <?php $percentage = $maxUsage > 0 ? ($tag['usage'] / $maxUsage * 100) : 0; ?>
                                        <div>
                                            <div class="flex justify-between items-center mb-1">
                                                <div class="flex items-center gap-2">
                                                    <span class="inline-block w-4 h-4 rounded" style="background-color: <?= htmlspecialchars($tag['color']) ?>"></span>
                                                    <span class="font-medium text-sm"><?= htmlspecialchars($tag['name']) ?></span>
                                                </div>
                                                <span class="text-sm text-gray-600"><?= $tag['usage'] ?> files</span>
                                            </div>
                                            <div class="w-full bg-gray-200 rounded-full h-2">
                                                <div class="h-2 rounded-full transition-all" style="width: <?= $percentage ?>%; background-color: <?= htmlspecialchars($tag['color']) ?>"></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Recent Movements Widget -->
                    <?php
                    $recentMovements = $db->fetchAll("
                        SELECT fm.*,
                               f.display_number, f.name as file_name,
                               u.name as moved_by_name,
                               l_from.name as from_location_name, c_from.label as from_cabinet_label, d_from.label as from_drawer_label,
                               l_to.name as to_location_name, c_to.label as to_cabinet_label, d_to.label as to_drawer_label
                        FROM file_movements fm
                        LEFT JOIN files f ON fm.file_id = f.id
                        LEFT JOIN users u ON fm.moved_by = u.id
                        LEFT JOIN drawers d_from ON fm.from_drawer_id = d_from.id
                        LEFT JOIN cabinets c_from ON d_from.cabinet_id = c_from.id
                        LEFT JOIN locations l_from ON c_from.location_id = l_from.id
                        LEFT JOIN drawers d_to ON fm.to_drawer_id = d_to.id
                        LEFT JOIN cabinets c_to ON d_to.cabinet_id = c_to.id
                        LEFT JOIN locations l_to ON c_to.location_id = l_to.id
                        ORDER BY fm.moved_at DESC
                        LIMIT 5
                    ");

                    if (!empty($recentMovements)):
                    ?>
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-xl font-bold flex items-center gap-2">
                                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                                    </svg>
                                    Recent File Movements
                                </h3>
                                <a href="?page=movements" class="text-blue-600 hover:underline text-sm">View All</a>
                            </div>
                            <div class="space-y-3">
                                <?php foreach ($recentMovements as $m): ?>
                                    <div class="border-l-4 border-blue-500 pl-4 py-2 hover:bg-gray-50">
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <div class="font-medium">
                                                    <span class="font-mono text-sm text-gray-600">#<?= htmlspecialchars($m['display_number']) ?></span>
                                                    <span class="ml-2"><?= htmlspecialchars($m['file_name']) ?></span>
                                                </div>
                                                <div class="text-sm text-gray-600 mt-1">
                                                    <span class="font-medium">From:</span>
                                                    <?php if ($m['from_location_name']): ?>
                                                        <?= htmlspecialchars($m['from_location_name'] . ' > ' . $m['from_cabinet_label'] . ' > ' . $m['from_drawer_label']) ?>
                                                    <?php else: ?>
                                                        <span class="italic">Unassigned</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-sm text-gray-600">
                                                    <span class="font-medium">To:</span>
                                                    <?php if ($m['to_location_name']): ?>
                                                        <?= htmlspecialchars($m['to_location_name'] . ' > ' . $m['to_cabinet_label'] . ' > ' . $m['to_drawer_label']) ?>
                                                    <?php else: ?>
                                                        <span class="italic">Unassigned</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="text-right text-xs text-gray-500">
                                                <div><?= date('M j, Y', strtotime($m['moved_at'])) ?></div>
                                                <div><?= date('g:i A', strtotime($m['moved_at'])) ?></div>
                                                <div class="mt-1 font-medium"><?= htmlspecialchars($m['moved_by_name']) ?></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                <?php elseif ($page === 'users'): ?>
                    <!-- Users Management Page (Admin Only) -->
                    <?php if ($_SESSION['user_role'] !== 'admin'): ?>
                        <div class="bg-red-100 text-red-700 p-4 rounded">
                            Unauthorized: Only administrators can access user management
                        </div>
                    <?php else: ?>
                        <?php if ($action === 'create'): ?>
                            <!-- Create User Form -->
                            <h2 class="text-3xl font-bold mb-6">Create New User</h2>
                            <div class="bg-white rounded-lg shadow p-6 max-w-2xl">
                                <form method="POST" action="?page=users&action=create">
                                    <div class="mb-4">
                                        <label class="block text-gray-700 mb-2">Email *</label>
                                        <input type="email" name="email" required class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    </div>
                                    <div class="mb-4">
                                        <label class="block text-gray-700 mb-2">Name *</label>
                                        <input type="text" name="name" required class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    </div>
                                    <div class="mb-4">
                                        <label class="flex items-center">
                                            <input type="checkbox" name="generate_password" id="generate_password" class="mr-2" onchange="togglePasswordFields()">
                                            <span class="text-gray-700">Generate random password</span>
                                        </label>
                                    </div>
                                    <div id="password_fields">
                                        <div class="mb-4">
                                            <label class="block text-gray-700 mb-2">Password * (min 8 characters)</label>
                                            <input type="password" name="password" id="password" class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        </div>
                                        <div class="mb-4">
                                            <label class="block text-gray-700 mb-2">Confirm Password *</label>
                                            <input type="password" name="confirm_password" id="confirm_password" class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        </div>
                                    </div>
                                    <div class="mb-4">
                                        <label class="block text-gray-700 mb-2">Role</label>
                                        <select name="role" class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                            <option value="viewer">Viewer</option>
                                            <option value="user" selected>User</option>
                                            <option value="admin">Admin</option>
                                        </select>
                                    </div>
                                    <div class="mb-6">
                                        <label class="block text-gray-700 mb-2">Status</label>
                                        <select name="status" class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                            <option value="active" selected>Active</option>
                                            <option value="inactive">Inactive</option>
                                        </select>
                                    </div>
                                    <div class="flex gap-2">
                                        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">Create User</button>
                                        <a href="?page=users" class="bg-gray-300 text-gray-700 px-6 py-2 rounded hover:bg-gray-400">Cancel</a>
                                    </div>
                                </form>
                            </div>
                            <script>
                                function togglePasswordFields() {
                                    const generate = document.getElementById('generate_password').checked;
                                    const fields = document.getElementById('password_fields');
                                    const password = document.getElementById('password');
                                    const confirm = document.getElementById('confirm_password');

                                    if (generate) {
                                        fields.style.display = 'none';
                                        password.required = false;
                                        confirm.required = false;
                                    } else {
                                        fields.style.display = 'block';
                                        password.required = true;
                                        confirm.required = true;
                                    }
                                }
                            </script>

                        <?php elseif ($action === 'edit' && $id): ?>
                            <!-- Edit User Form -->
                            <?php
                            $editUser = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$id]);
                            if (!$editUser):
                            ?>
                                <div class="bg-red-100 text-red-700 p-4 rounded">User not found</div>
                            <?php else: ?>
                                <h2 class="text-3xl font-bold mb-6">Edit User</h2>
                                <div class="bg-white rounded-lg shadow p-6 max-w-2xl">
                                    <form method="POST" action="?page=users&action=edit&id=<?= $id ?>">
                                        <div class="mb-4">
                                            <label class="block text-gray-700 mb-2">Email *</label>
                                            <input type="email" name="email" required value="<?= htmlspecialchars($editUser['email']) ?>" class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        </div>
                                        <div class="mb-4">
                                            <label class="block text-gray-700 mb-2">Name *</label>
                                            <input type="text" name="name" required value="<?= htmlspecialchars($editUser['name']) ?>" class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        </div>
                                        <div class="mb-4">
                                            <label class="block text-gray-700 mb-2">Role</label>
                                            <select name="role" class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                                <option value="viewer" <?= $editUser['role'] === 'viewer' ? 'selected' : '' ?>>Viewer</option>
                                                <option value="user" <?= $editUser['role'] === 'user' ? 'selected' : '' ?>>User</option>
                                                <option value="admin" <?= $editUser['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                            </select>
                                        </div>
                                        <div class="mb-4">
                                            <label class="block text-gray-700 mb-2">Status</label>
                                            <select name="status" class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                                <option value="active" <?= $editUser['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                                <option value="inactive" <?= $editUser['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                                <option value="locked" <?= $editUser['status'] === 'locked' ? 'selected' : '' ?>>Locked</option>
                                            </select>
                                        </div>
                                        <div class="mb-4 p-3 bg-gray-50 rounded">
                                            <div class="text-sm text-gray-600">
                                                <div>Created: <?= $editUser['created_at'] ?></div>
                                                <div>Last Login: <?= $editUser['last_login'] ?? 'Never' ?></div>
                                            </div>
                                        </div>
                                        <div class="flex gap-2">
                                            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">Update User</button>
                                            <a href="?page=users&action=view&id=<?= $id ?>" class="bg-gray-300 text-gray-700 px-6 py-2 rounded hover:bg-gray-400">Cancel</a>
                                            <a href="?page=users&action=reset_password&id=<?= $id ?>" class="bg-orange-600 text-white px-6 py-2 rounded hover:bg-orange-700">Reset Password</a>
                                        </div>
                                    </form>
                                </div>
                            <?php endif; ?>

                        <?php elseif ($action === 'view' && $id): ?>
                            <!-- User Detail View -->
                            <?php
                            $viewUser = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$id]);
                            if (!$viewUser):
                            ?>
                                <div class="bg-red-100 text-red-700 p-4 rounded">User not found</div>
                            <?php else:
                                // Get files owned by user
                                $ownedFiles = $db->fetchAll("SELECT * FROM files WHERE owner_id = ? AND is_archived = 0 AND is_destroyed = 0", [$id]);

                                // Get files checked out by user
                                $checkedOutFiles = $db->fetchAll("SELECT * FROM files WHERE checked_out_by = ? AND is_checked_out = 1", [$id]);

                                // Get active sessions
                                $activeSessions = $db->fetchAll("SELECT * FROM user_sessions WHERE user_id = ? ORDER BY last_activity DESC", [$id]);
                            ?>
                                <div class="flex justify-between items-center mb-6">
                                    <h2 class="text-3xl font-bold">User Details</h2>
                                    <div class="flex gap-2">
                                        <a href="?page=users&action=edit&id=<?= $id ?>" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Edit User</a>
                                        <a href="?page=users" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400">Back to List</a>
                                    </div>
                                </div>

                                <!-- User Info Card -->
                                <div class="bg-white rounded-lg shadow p-6 mb-6">
                                    <div class="grid grid-cols-2 gap-6">
                                        <div>
                                            <h3 class="text-xl font-bold mb-4"><?= htmlspecialchars($viewUser['name']) ?></h3>
                                            <div class="space-y-2">
                                                <div><span class="text-gray-600">Email:</span> <?= htmlspecialchars($viewUser['email']) ?></div>
                                                <div><span class="text-gray-600">Role:</span> <span class="px-2 py-1 text-xs rounded bg-blue-100 text-blue-800"><?= ucfirst($viewUser['role']) ?></span></div>
                                                <div>
                                                    <span class="text-gray-600">Status:</span>
                                                    <?php
                                                    $statusColors = [
                                                        'active' => 'bg-green-100 text-green-800',
                                                        'inactive' => 'bg-gray-100 text-gray-800',
                                                        'locked' => 'bg-red-100 text-red-800'
                                                    ];
                                                    $statusColor = $statusColors[$viewUser['status']] ?? 'bg-gray-100 text-gray-800';
                                                    ?>
                                                    <span class="px-2 py-1 text-xs rounded <?= $statusColor ?>"><?= ucfirst($viewUser['status']) ?></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div>
                                            <h4 class="font-bold mb-2">Account Info</h4>
                                            <div class="space-y-2 text-sm">
                                                <div><span class="text-gray-600">Created:</span> <?= $viewUser['created_at'] ?></div>
                                                <div><span class="text-gray-600">Last Login:</span> <?= $viewUser['last_login'] ?? 'Never' ?></div>
                                                <div><span class="text-gray-600">Failed Attempts:</span> <?= $viewUser['failed_login_attempts'] ?></div>
                                                <?php if ($viewUser['locked_until']): ?>
                                                    <div><span class="text-gray-600">Locked Until:</span> <?= $viewUser['locked_until'] ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Files Owned -->
                                <div class="bg-white rounded-lg shadow p-6 mb-6">
                                    <h3 class="text-lg font-bold mb-4">Files Owned (<?= count($ownedFiles) ?>)</h3>
                                    <?php if (empty($ownedFiles)): ?>
                                        <p class="text-gray-500">No files owned by this user</p>
                                    <?php else: ?>
                                        <div class="space-y-2">
                                            <?php foreach (array_slice($ownedFiles, 0, 5) as $file): ?>
                                                <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                                                    <div>
                                                        <span class="font-mono text-sm">#<?= htmlspecialchars($file['display_number']) ?></span>
                                                        <span class="ml-3"><?= htmlspecialchars($file['name']) ?></span>
                                                    </div>
                                                    <a href="?page=files&action=view&id=<?= $file['id'] ?>" class="text-blue-600 hover:underline">View</a>
                                                </div>
                                            <?php endforeach; ?>
                                            <?php if (count($ownedFiles) > 5): ?>
                                                <p class="text-sm text-gray-500">And <?= count($ownedFiles) - 5 ?> more...</p>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Files Checked Out -->
                                <div class="bg-white rounded-lg shadow p-6 mb-6">
                                    <h3 class="text-lg font-bold mb-4">Files Checked Out (<?= count($checkedOutFiles) ?>)</h3>
                                    <?php if (empty($checkedOutFiles)): ?>
                                        <p class="text-gray-500">No files currently checked out</p>
                                    <?php else: ?>
                                        <div class="space-y-2">
                                            <?php foreach ($checkedOutFiles as $file): ?>
                                                <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                                                    <div>
                                                        <span class="font-mono text-sm">#<?= htmlspecialchars($file['display_number']) ?></span>
                                                        <span class="ml-3"><?= htmlspecialchars($file['name']) ?></span>
                                                    </div>
                                                    <a href="?page=files&action=view&id=<?= $file['id'] ?>" class="text-blue-600 hover:underline">View</a>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Movement Activity -->
                                <?php
                                $userMovements = $db->fetchAll("
                                    SELECT fm.*,
                                           f.display_number, f.name as file_name,
                                           l_from.name as from_location_name, c_from.label as from_cabinet_label, d_from.label as from_drawer_label,
                                           l_to.name as to_location_name, c_to.label as to_cabinet_label, d_to.label as to_drawer_label
                                    FROM file_movements fm
                                    LEFT JOIN files f ON fm.file_id = f.id
                                    LEFT JOIN drawers d_from ON fm.from_drawer_id = d_from.id
                                    LEFT JOIN cabinets c_from ON d_from.cabinet_id = c_from.id
                                    LEFT JOIN locations l_from ON c_from.location_id = l_from.id
                                    LEFT JOIN drawers d_to ON fm.to_drawer_id = d_to.id
                                    LEFT JOIN cabinets c_to ON d_to.cabinet_id = c_to.id
                                    LEFT JOIN locations l_to ON c_to.location_id = l_to.id
                                    WHERE fm.moved_by = ?
                                    ORDER BY fm.moved_at DESC
                                    LIMIT 10
                                ", [$id]);

                                $totalMovements = $db->fetchOne("SELECT COUNT(*) as count FROM file_movements WHERE moved_by = ?", [$id])['count'];
                                ?>
                                <div class="bg-white rounded-lg shadow p-6 mb-6">
                                    <div class="flex justify-between items-center mb-4">
                                        <h3 class="text-lg font-bold flex items-center gap-2">
                                            <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                                            </svg>
                                            Movement Activity (<?= $totalMovements ?> total)
                                        </h3>
                                        <a href="?page=movements&user_id=<?= $id ?>" class="text-blue-600 hover:underline text-sm">View All Movements</a>
                                    </div>
                                    <?php if (empty($userMovements)): ?>
                                        <p class="text-gray-500">No file movements by this user</p>
                                    <?php else: ?>
                                        <div class="space-y-2">
                                            <?php foreach ($userMovements as $m): ?>
                                                <div class="p-3 bg-gray-50 rounded hover:bg-gray-100">
                                                    <div class="flex justify-between items-start">
                                                        <div class="flex-1">
                                                            <div class="font-medium text-sm">
                                                                <span class="font-mono text-xs text-gray-600">#<?= htmlspecialchars($m['display_number']) ?></span>
                                                                <span class="ml-2"><?= htmlspecialchars($m['file_name']) ?></span>
                                                            </div>
                                                            <div class="text-xs text-gray-600 mt-1 grid grid-cols-2 gap-2">
                                                                <div>
                                                                    <span class="font-medium">From:</span>
                                                                    <?php if ($m['from_location_name']): ?>
                                                                        <?= htmlspecialchars($m['from_location_name'] . ' > ' . $m['from_drawer_label']) ?>
                                                                    <?php else: ?>
                                                                        <span class="italic">Unassigned</span>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <div>
                                                                    <span class="font-medium">To:</span>
                                                                    <?php if ($m['to_location_name']): ?>
                                                                        <?= htmlspecialchars($m['to_location_name'] . ' > ' . $m['to_drawer_label']) ?>
                                                                    <?php else: ?>
                                                                        <span class="italic">Unassigned</span>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                            <?php if ($m['notes']): ?>
                                                                <div class="text-xs text-gray-500 mt-1 italic"><?= htmlspecialchars($m['notes']) ?></div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="text-xs text-gray-500 ml-4 text-right whitespace-nowrap">
                                                            <?= date('M j, Y', strtotime($m['moved_at'])) ?>
                                                            <br><?= date('g:i A', strtotime($m['moved_at'])) ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                            <?php if ($totalMovements > 10): ?>
                                                <p class="text-sm text-gray-500 text-center">And <?= $totalMovements - 10 ?> more...</p>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Active Sessions -->
                                <div class="bg-white rounded-lg shadow p-6">
                                    <h3 class="text-lg font-bold mb-4">Active Sessions (<?= count($activeSessions) ?>)</h3>
                                    <?php if (empty($activeSessions)): ?>
                                        <p class="text-gray-500">No active sessions</p>
                                    <?php else: ?>
                                        <div class="space-y-2">
                                            <?php foreach ($activeSessions as $session): ?>
                                                <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                                                    <div class="flex-1">
                                                        <div class="text-sm"><span class="text-gray-600">IP:</span> <?= htmlspecialchars($session['ip_address']) ?></div>
                                                        <div class="text-xs text-gray-500"><?= htmlspecialchars(substr($session['user_agent'], 0, 80)) ?></div>
                                                        <div class="text-xs text-gray-500">Last activity: <?= $session['last_activity'] ?></div>
                                                    </div>
                                                    <form method="POST" action="?page=users&action=force_logout&id=<?= $id ?>" class="ml-4">
                                                        <input type="hidden" name="session_id" value="<?= $session['session_id'] ?>">
                                                        <button type="submit" class="bg-red-600 text-white px-3 py-1 rounded text-sm hover:bg-red-700" onclick="return confirm('Force logout this session?')">Force Logout</button>
                                                    </form>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                        <?php elseif ($action === 'reset_password' && $id): ?>
                            <!-- Reset Password Form -->
                            <?php
                            $resetUser = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$id]);
                            if (!$resetUser):
                            ?>
                                <div class="bg-red-100 text-red-700 p-4 rounded">User not found</div>
                            <?php else: ?>
                                <h2 class="text-3xl font-bold mb-6">Reset Password for <?= htmlspecialchars($resetUser['name']) ?></h2>
                                <div class="bg-white rounded-lg shadow p-6 max-w-2xl">
                                    <form method="POST" action="?page=users&action=reset_password&id=<?= $id ?>">
                                        <div class="mb-4">
                                            <label class="block text-gray-700 mb-2">New Password * (min 8 characters)</label>
                                            <input type="password" name="new_password" required class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        </div>
                                        <div class="mb-4">
                                            <label class="block text-gray-700 mb-2">Confirm Password *</label>
                                            <input type="password" name="confirm_password" required class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        </div>
                                        <div class="mb-6">
                                            <label class="flex items-center text-gray-400">
                                                <input type="checkbox" disabled class="mr-2">
                                                <span>Send email notification (coming soon)</span>
                                            </label>
                                        </div>
                                        <div class="flex gap-2">
                                            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">Reset Password</button>
                                            <a href="?page=users&action=view&id=<?= $id ?>" class="bg-gray-300 text-gray-700 px-6 py-2 rounded hover:bg-gray-400">Cancel</a>
                                        </div>
                                    </form>
                                </div>
                            <?php endif; ?>

                        <?php elseif ($action === 'change_password'): ?>
                            <!-- Change Own Password Form -->
                            <h2 class="text-3xl font-bold mb-6">Change Your Password</h2>
                            <div class="bg-white rounded-lg shadow p-6 max-w-2xl">
                                <form method="POST" action="?page=users&action=change_password">
                                    <div class="mb-4">
                                        <label class="block text-gray-700 mb-2">Current Password *</label>
                                        <input type="password" name="current_password" required class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    </div>
                                    <div class="mb-4">
                                        <label class="block text-gray-700 mb-2">New Password * (min 8 characters)</label>
                                        <input type="password" name="new_password" required class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    </div>
                                    <div class="mb-6">
                                        <label class="block text-gray-700 mb-2">Confirm New Password *</label>
                                        <input type="password" name="confirm_password" required class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    </div>
                                    <div class="flex gap-2">
                                        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">Change Password</button>
                                        <a href="?page=dashboard" class="bg-gray-300 text-gray-700 px-6 py-2 rounded hover:bg-gray-400">Cancel</a>
                                    </div>
                                </form>
                            </div>

                        <?php elseif ($action === 'sessions'): ?>
                            <!-- Active Sessions Management -->
                            <h2 class="text-3xl font-bold mb-6">Active Sessions</h2>

                            <?php
                            // Auto-cleanup sessions older than 24 hours
                            $db->query("DELETE FROM user_sessions WHERE last_activity < datetime('now', '-24 hours')");

                            $activeSessions = $db->fetchAll("
                                SELECT us.*, u.name, u.email
                                FROM user_sessions us
                                JOIN users u ON us.user_id = u.id
                                ORDER BY us.last_activity DESC
                            ");
                            ?>

                            <div class="bg-white rounded-lg shadow overflow-hidden">
                                <table class="min-w-full">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP Address</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Browser</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Last Activity</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php if (empty($activeSessions)): ?>
                                            <tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">No active sessions</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($activeSessions as $session): ?>
                                                <tr>
                                                    <td class="px-6 py-4">
                                                        <div><?= htmlspecialchars($session['name']) ?></div>
                                                        <div class="text-sm text-gray-500"><?= htmlspecialchars($session['email']) ?></div>
                                                    </td>
                                                    <td class="px-6 py-4"><?= htmlspecialchars($session['ip_address']) ?></td>
                                                    <td class="px-6 py-4 text-sm"><?= htmlspecialchars(substr($session['user_agent'], 0, 50)) ?>...</td>
                                                    <td class="px-6 py-4"><?= $session['last_activity'] ?></td>
                                                    <td class="px-6 py-4">
                                                        <form method="POST" action="?page=users&action=force_logout&id=<?= $session['user_id'] ?>" class="inline">
                                                            <input type="hidden" name="session_id" value="<?= $session['session_id'] ?>">
                                                            <button type="submit" class="text-red-600 hover:underline" onclick="return confirm('Force logout this session?')">Force Logout</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                        <?php else: ?>
                            <!-- Users List (Default) -->
                            <div x-data="{ tab: 'users' }">
                                <div class="flex justify-between items-center mb-6">
                                    <h2 class="text-3xl font-bold">User Management</h2>
                                    <a href="?page=users&action=create" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">+ Create New User</a>
                                </div>

                                <!-- Tabs -->
                                <div class="mb-6 border-b border-gray-200">
                                    <nav class="-mb-px flex space-x-8">
                                        <button @click="tab = 'users'" :class="tab === 'users' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                            User List
                                        </button>
                                        <button @click="tab = 'sessions'" :class="tab === 'sessions' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                            Active Sessions
                                        </button>
                                    </nav>
                                </div>

                                <!-- User List Tab -->
                                <div x-show="tab === 'users'">
                                    <?php
                                    $users = $db->fetchAll("SELECT * FROM users ORDER BY created_at DESC");
                                    ?>
                                    <div class="bg-white rounded-lg shadow overflow-hidden">
                                        <table class="min-w-full">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Last Login</th>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-200">
                                                <?php foreach ($users as $user): ?>
                                                    <tr>
                                                        <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($user['name']) ?></td>
                                                        <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($user['email']) ?></td>
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <span class="px-2 py-1 text-xs rounded bg-blue-100 text-blue-800"><?= ucfirst($user['role']) ?></span>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <?php
                                                            $statusColors = [
                                                                'active' => 'bg-green-100 text-green-800',
                                                                'inactive' => 'bg-gray-100 text-gray-800',
                                                                'locked' => 'bg-red-100 text-red-800'
                                                            ];
                                                            $statusColor = $statusColors[$user['status']] ?? 'bg-gray-100 text-gray-800';
                                                            ?>
                                                            <span class="px-2 py-1 text-xs rounded <?= $statusColor ?>"><?= ucfirst($user['status']) ?></span>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm"><?= $user['last_login'] ?? 'Never' ?></td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                            <a href="?page=users&action=view&id=<?= $user['id'] ?>" class="text-blue-600 hover:underline mr-3">View</a>
                                                            <a href="?page=users&action=edit&id=<?= $user['id'] ?>" class="text-green-600 hover:underline mr-3">Edit</a>

                                                            <!-- Toggle Status Dropdown -->
                                                            <div class="inline-block relative" x-data="{ open: false }">
                                                                <button @click="open = !open" class="text-orange-600 hover:underline">Toggle Status</button>
                                                                <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg z-10">
                                                                    <form method="POST" action="?page=users&action=toggle_status&id=<?= $user['id'] ?>">
                                                                        <input type="hidden" name="status" value="active">
                                                                        <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Set Active</button>
                                                                    </form>
                                                                    <form method="POST" action="?page=users&action=toggle_status&id=<?= $user['id'] ?>">
                                                                        <input type="hidden" name="status" value="inactive">
                                                                        <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Set Inactive</button>
                                                                    </form>
                                                                    <form method="POST" action="?page=users&action=toggle_status&id=<?= $user['id'] ?>">
                                                                        <input type="hidden" name="status" value="locked">
                                                                        <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-red-700 hover:bg-gray-100">Lock Account</button>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- Active Sessions Tab -->
                                <div x-show="tab === 'sessions'">
                                    <?php
                                    // Auto-cleanup sessions older than 24 hours
                                    $db->query("DELETE FROM user_sessions WHERE last_activity < datetime('now', '-24 hours')");

                                    $activeSessions = $db->fetchAll("
                                        SELECT us.*, u.name, u.email
                                        FROM user_sessions us
                                        JOIN users u ON us.user_id = u.id
                                        ORDER BY us.last_activity DESC
                                    ");
                                    ?>
                                    <div class="bg-white rounded-lg shadow overflow-hidden">
                                        <table class="min-w-full">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP Address</th>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Browser</th>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Last Activity</th>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-200">
                                                <?php if (empty($activeSessions)): ?>
                                                    <tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">No active sessions</td></tr>
                                                <?php else: ?>
                                                    <?php foreach ($activeSessions as $session): ?>
                                                        <tr>
                                                            <td class="px-6 py-4">
                                                                <div><?= htmlspecialchars($session['name']) ?></div>
                                                                <div class="text-sm text-gray-500"><?= htmlspecialchars($session['email']) ?></div>
                                                            </td>
                                                            <td class="px-6 py-4"><?= htmlspecialchars($session['ip_address']) ?></td>
                                                            <td class="px-6 py-4 text-sm"><?= htmlspecialchars(substr($session['user_agent'], 0, 50)) ?>...</td>
                                                            <td class="px-6 py-4"><?= $session['last_activity'] ?></td>
                                                            <td class="px-6 py-4">
                                                                <form method="POST" action="?page=users&action=force_logout&id=<?= $session['user_id'] ?>" class="inline">
                                                                    <input type="hidden" name="session_id" value="<?= $session['session_id'] ?>">
                                                                    <button type="submit" class="text-red-600 hover:underline" onclick="return confirm('Force logout this session?')">Force Logout</button>
                                                                </form>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                <?php elseif ($page === 'search'): ?>
                    <!-- Search Page -->
                    <h2 class="text-3xl font-bold mb-6">Search Files</h2>

                    <div class="bg-white rounded-lg shadow p-6 mb-6">
                        <form method="GET" action="?page=search" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <input type="hidden" name="page" value="search">
                            <div>
                                <label class="block text-gray-700 mb-2">Search Text</label>
                                <input type="text" name="q" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
                                       placeholder="Name, number, or description..."
                                       class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-gray-700 mb-2">Filter by Tag</label>
                                <select name="tag" class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">All tags</option>
                                    <?php foreach ($allTags as $tag): ?>
                                        <option value="<?= $tag['id'] ?>" <?= ($_GET['tag'] ?? '') == $tag['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($tag['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-gray-700 mb-2">Sensitivity</label>
                                <select name="sensitivity" class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">All levels</option>
                                    <option value="public" <?= ($_GET['sensitivity'] ?? '') === 'public' ? 'selected' : '' ?>>Public</option>
                                    <option value="internal" <?= ($_GET['sensitivity'] ?? '') === 'internal' ? 'selected' : '' ?>>Internal</option>
                                    <option value="confidential" <?= ($_GET['sensitivity'] ?? '') === 'confidential' ? 'selected' : '' ?>>Confidential</option>
                                    <option value="restricted" <?= ($_GET['sensitivity'] ?? '') === 'restricted' ? 'selected' : '' ?>>Restricted</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-gray-700 mb-2">Filter by Entity</label>
                                <select name="entity" class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">All entities</option>
                                    <?php foreach ($allEntities as $entity): ?>
                                        <option value="<?= $entity['id'] ?>" <?= ($_GET['entity'] ?? '') == $entity['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($entity['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="md:col-span-4 flex items-center gap-4">
                                <label class="flex items-center">
                                    <input type="checkbox" name="include_archived" value="1" <?= isset($_GET['include_archived']) ? 'checked' : '' ?> class="mr-2">
                                    <span class="text-gray-700">Include archived files</span>
                                </label>
                                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">Search</button>
                                <a href="?page=search" class="bg-gray-300 text-gray-700 px-6 py-2 rounded hover:bg-gray-400 ml-2">Clear</a>
                            </div>
                        </form>
                    </div>

                    <?php
                    // Build search query
                    $searchQuery = $_GET['q'] ?? '';
                    $tagFilter = $_GET['tag'] ?? '';
                    $sensitivityFilter = $_GET['sensitivity'] ?? '';
                    $entityFilter = $_GET['entity'] ?? '';
                    $includeArchived = isset($_GET['include_archived']);

                    $sql = "SELECT DISTINCT f.*, u.name as owner_name, d.label as drawer_label, c.label as cabinet_label, e.name as entity_name
                            FROM files f
                            LEFT JOIN users u ON f.owner_id = u.id
                            LEFT JOIN drawers d ON f.current_drawer_id = d.id
                            LEFT JOIN cabinets c ON d.cabinet_id = c.id
                            LEFT JOIN entities e ON f.entity_id = e.id
                            LEFT JOIN file_tags ft ON f.id = ft.file_id
                            WHERE f.is_destroyed = 0";

                    // Only exclude archived files if not including them
                    if (!$includeArchived) {
                        $sql .= " AND f.is_archived = 0";
                    }

                    $params = [];

                    if ($searchQuery) {
                        $sql .= " AND (f.name LIKE ? OR f.display_number LIKE ? OR f.description LIKE ?)";
                        $searchTerm = "%$searchQuery%";
                        $params[] = $searchTerm;
                        $params[] = $searchTerm;
                        $params[] = $searchTerm;
                    }

                    if ($tagFilter) {
                        $sql .= " AND ft.tag_id = ?";
                        $params[] = $tagFilter;
                    }

                    if ($sensitivityFilter) {
                        $sql .= " AND f.sensitivity = ?";
                        $params[] = $sensitivityFilter;
                    }

                    if ($entityFilter) {
                        $sql .= " AND f.entity_id = ?";
                        $params[] = $entityFilter;
                    }

                    $sql .= " ORDER BY f.created_at DESC";

                    $searchResults = $db->fetchAll($sql, $params);
                    ?>

                    <div class="bg-white rounded-lg shadow overflow-hidden">
                        <table class="min-w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">File #</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Owner</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Entity</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Location</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($searchResults)): ?>
                                    <tr><td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                        No files found matching your search.
                                    </td></tr>
                                <?php else: ?>
                                    <?php foreach ($searchResults as $file): ?>
                                        <tr class="<?= $file['is_archived'] ? 'bg-yellow-50' : '' ?>">
                                            <td class="px-6 py-4 whitespace-nowrap font-mono">#<?= htmlspecialchars($file['display_number']) ?></td>
                                            <td class="px-6 py-4">
                                                <?= htmlspecialchars($file['name']) ?>
                                                <?php if ($file['is_archived']): ?>
                                                    <span class="ml-2 px-2 py-1 text-xs bg-gray-400 text-white rounded">Archived</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4"><?= htmlspecialchars($file['owner_name'] ?? 'N/A') ?></td>
                                            <td class="px-6 py-4"><?= htmlspecialchars($file['entity_name'] ?? 'N/A') ?></td>
                                            <td class="px-6 py-4"><?= htmlspecialchars(($file['cabinet_label'] ?? '') . ($file['drawer_label'] ? ' - ' . $file['drawer_label'] : 'Not assigned')) ?></td>
                                            <td class="px-6 py-4">
                                                <a href="?page=files&action=view&id=<?= $file['id'] ?>" class="text-blue-600 hover:underline">View</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                <?php elseif ($page === 'entities' && $action === 'edit' && $id): ?>
                    <!-- Edit Entity Form -->
                    <?php
                    $entity = $db->fetchOne("SELECT * FROM entities WHERE id = ?", [$id]);
                    if (!$entity):
                    ?>
                        <div class="bg-red-100 text-red-700 p-4 rounded">Entity not found</div>
                    <?php else: ?>
                        <h2 class="text-3xl font-bold mb-6">Edit Entity: <?= htmlspecialchars($entity['name']) ?></h2>
                        <div class="bg-white rounded-lg shadow p-6 max-w-2xl">
                            <form method="POST">
                                <div class="mb-4">
                                    <label class="block text-gray-700 mb-2">Entity Name *</label>
                                    <input type="text" name="name" required value="<?= htmlspecialchars($entity['name']) ?>"
                                           placeholder="e.g., ACME Corporation, John Doe"
                                           class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                </div>
                                <div class="mb-4">
                                    <label class="block text-gray-700 mb-2">Description</label>
                                    <textarea name="description" rows="3"
                                              placeholder="Brief description of the entity"
                                              class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500"><?= htmlspecialchars($entity['description']) ?></textarea>
                                </div>
                                <div class="mb-4">
                                    <label class="block text-gray-700 mb-2">Contact Info</label>
                                    <input type="text" name="contact_info" value="<?= htmlspecialchars($entity['contact_info']) ?>"
                                           placeholder="Email, phone, or address"
                                           class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                </div>
                                <div class="mb-4">
                                    <label class="block text-gray-700 mb-2">Notes</label>
                                    <textarea name="notes" rows="3"
                                              placeholder="Additional notes"
                                              class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500"><?= htmlspecialchars($entity['notes']) ?></textarea>
                                </div>
                                <div class="flex gap-2">
                                    <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">Update Entity</button>
                                    <a href="?page=entities" class="bg-gray-300 text-gray-700 px-6 py-2 rounded hover:bg-gray-400">Cancel</a>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>
                <?php elseif ($page === 'entities'): ?>
                    <!-- Entities Management Page -->
                    <h2 class="text-3xl font-bold mb-6">Entity Management</h2>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Create Entity Form -->
                        <div class="bg-white rounded-lg shadow p-6">
                            <h3 class="text-xl font-bold mb-4">Create New Entity</h3>
                            <form method="POST" action="?page=entities&action=create">
                                <div class="mb-4">
                                    <label class="block text-gray-700 mb-2">Entity Name *</label>
                                    <input type="text" name="name" required
                                           placeholder="e.g., ACME Corporation, John Doe"
                                           class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                </div>
                                <div class="mb-4">
                                    <label class="block text-gray-700 mb-2">Description</label>
                                    <textarea name="description" rows="2"
                                              placeholder="Brief description of the entity"
                                              class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                                </div>
                                <div class="mb-4">
                                    <label class="block text-gray-700 mb-2">Contact Info</label>
                                    <input type="text" name="contact_info"
                                           placeholder="Email, phone, or address"
                                           class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                </div>
                                <div class="mb-4">
                                    <label class="block text-gray-700 mb-2">Notes</label>
                                    <textarea name="notes" rows="2"
                                              placeholder="Additional notes"
                                              class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                                </div>
                                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">Create Entity</button>
                            </form>
                        </div>

                        <!-- Entities List -->
                        <div class="bg-white rounded-lg shadow p-6">
                            <h3 class="text-xl font-bold mb-4">Existing Entities</h3>
                            <div class="space-y-2">
                                <?php
                                $entitiesWithCounts = $db->fetchAll("
                                    SELECT e.*,
                                           COUNT(DISTINCT f.id) as file_count,
                                           COUNT(DISTINCT c.id) as cabinet_count
                                    FROM entities e
                                    LEFT JOIN files f ON e.id = f.entity_id
                                    LEFT JOIN cabinets c ON e.id = c.entity_id
                                    GROUP BY e.id
                                    ORDER BY e.name
                                ");

                                if (empty($entitiesWithCounts)):
                                ?>
                                    <p class="text-gray-500">No entities yet. Create one above!</p>
                                <?php else: ?>
                                    <?php foreach ($entitiesWithCounts as $entity): ?>
                                        <div class="p-4 bg-gray-50 rounded">
                                            <div class="flex items-center justify-between mb-2">
                                                <span class="font-medium text-lg"><?= htmlspecialchars($entity['name']) ?></span>
                                                <div class="flex gap-2">
                                                    <a href="?page=entities&action=edit&id=<?= $entity['id'] ?>" class="text-green-600 hover:underline text-sm">Edit</a>
                                                    <a href="?page=search&entity=<?= $entity['id'] ?>" class="text-blue-600 hover:underline text-sm">View Files</a>
                                                </div>
                                            </div>
                                            <?php if ($entity['description']): ?>
                                                <p class="text-sm text-gray-600 mb-1"><?= htmlspecialchars($entity['description']) ?></p>
                                            <?php endif; ?>
                                            <?php if ($entity['contact_info']): ?>
                                                <p class="text-sm text-gray-500 mb-1">Contact: <?= htmlspecialchars($entity['contact_info']) ?></p>
                                            <?php endif; ?>
                                            <div class="text-xs text-gray-500 mt-2">
                                                <?= $entity['file_count'] ?> files | <?= $entity['cabinet_count'] ?> cabinets
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>


                <?php elseif ($page === 'tags'): ?>
                    <!-- Tags Management Page -->
                    <h2 class="text-3xl font-bold mb-6">Tag Management</h2>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Create Tag Form -->
                        <div class="bg-white rounded-lg shadow p-6">
                            <h3 class="text-xl font-bold mb-4">Create New Tag</h3>
                            <form method="POST" action="?page=tags&action=create">
                                <div class="mb-4">
                                    <label class="block text-gray-700 mb-2">Tag Name *</label>
                                    <input type="text" name="name" required
                                           placeholder="e.g., ACME Vendor, HR-2024, Project X"
                                           class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                </div>
                                <div class="mb-4">
                                    <label class="block text-gray-700 mb-2">Color</label>
                                    <input type="color" name="color" value="#3B82F6"
                                           class="w-20 h-10 border rounded cursor-pointer">
                                </div>
                                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">Create Tag</button>
                            </form>
                        </div>

                        <!-- Tags List -->
                        <div class="bg-white rounded-lg shadow p-6">
                            <h3 class="text-xl font-bold mb-4">Existing Tags</h3>
                            <div class="space-y-2">
                                <?php
                                $tagsWithCounts = $db->fetchAll("
                                    SELECT t.*, COUNT(ft.file_id) as file_count
                                    FROM tags t
                                    LEFT JOIN file_tags ft ON t.id = ft.tag_id
                                    GROUP BY t.id
                                    ORDER BY t.name
                                ");

                                if (empty($tagsWithCounts)):
                                ?>
                                    <p class="text-gray-500">No tags yet. Create one above!</p>
                                <?php else: ?>
                                    <?php foreach ($tagsWithCounts as $tag): ?>
                                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
                                            <div class="flex items-center gap-3">
                                                <div class="w-4 h-4 rounded" style="background-color: <?= htmlspecialchars($tag['color']) ?>"></div>
                                                <span class="font-medium"><?= htmlspecialchars($tag['name']) ?></span>
                                                <span class="text-sm text-gray-500">(<?= $tag['file_count'] ?> files)</span>
                                            </div>
                                            <a href="?page=search&tag=<?= $tag['id'] ?>" class="text-blue-600 hover:underline text-sm">View Files</a>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                <?php elseif ($page === 'files' && $action === 'create'): ?>
                    <!-- Create File Form with Tags and Entity -->
                    <h2 class="text-3xl font-bold mb-6">Create New File</h2>
                    <div class="bg-white rounded-lg shadow p-6 max-w-2xl">
                        <form method="POST">
                            <div class="mb-4">
                                <label class="block text-gray-700 mb-2">Display Number *</label>
                                <input type="text" name="display_number" required
                                       class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       placeholder="e.g., 2024-001, DEPT-031">
                                <p class="text-sm text-gray-500 mt-1">Custom format for easy reference</p>
                            </div>
                            <div class="mb-4">
                                <label class="block text-gray-700 mb-2">File Name *</label>
                                <input type="text" name="name" required
                                       class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       placeholder="e.g., ACME Vendor Contract">
                            </div>
                            <div class="mb-4">
                                <label class="block text-gray-700 mb-2">Description</label>
                                <textarea name="description" rows="3"
                                          class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                                          placeholder="Brief description of contents"></textarea>
                            </div>
                            <div class="mb-4">
                                <label class="block text-gray-700 mb-2">Sensitivity Level</label>
                                <select name="sensitivity" class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="public">Public</option>
                                    <option value="internal" selected>Internal</option>
                                    <option value="confidential">Confidential</option>
                                    <option value="restricted">Restricted</option>
                                </select>
                            </div>
                            <div class="mb-4">
                                <label class="block text-gray-700 mb-2">Entity (optional)</label>
                                <select name="entity_id" class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">No entity</option>
                                    <?php foreach ($allEntities as $entity): ?>
                                        <option value="<?= $entity['id'] ?>">
                                            <?= htmlspecialchars($entity['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-4">
                                <label class="block text-gray-700 mb-2">Tags (optional)</label>
                                <div class="border rounded p-3 max-h-48 overflow-y-auto">
                                    <?php if (empty($allTags)): ?>
                                        <p class="text-gray-500 text-sm">No tags available. <a href="?page=tags" class="text-blue-600 hover:underline">Create one</a></p>
                                    <?php else: ?>
                                        <?php foreach ($allTags as $tag): ?>
                                            <label class="flex items-center gap-2 py-1 hover:bg-gray-50 cursor-pointer">
                                                <input type="checkbox" name="tags[]" value="<?= $tag['id'] ?>" class="rounded">
                                                <div class="w-3 h-3 rounded" style="background-color: <?= htmlspecialchars($tag['color']) ?>"></div>
                                                <span><?= htmlspecialchars($tag['name']) ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="mb-6">
                                <label class="block text-gray-700 mb-2">Assign to Drawer (optional)</label>
                                <select name="drawer_id" class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">Not assigned</option>
                                    <?php
                                    $drawers = $db->fetchAll("
                                        SELECT d.id, d.label as drawer_label, c.label as cabinet_label, l.name as location_name
                                        FROM drawers d
                                        JOIN cabinets c ON d.cabinet_id = c.id
                                        LEFT JOIN locations l ON c.location_id = l.id
                                        ORDER BY l.name, c.label, d.position
                                    ");
                                    foreach ($drawers as $drawer):
                                    ?>
                                        <option value="<?= $drawer['id'] ?>">
                                            <?= htmlspecialchars(($drawer['location_name'] ?? 'No Location') . ' > ' . $drawer['cabinet_label'] . ' > Drawer ' . $drawer['drawer_label']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="grid grid-cols-2 gap-4 mb-6">
                                <div>
                                    <label class="block text-gray-700 mb-2">Vertical Position</label>
                                    <select name="vertical_position" class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="Not Specified" selected>Not Specified</option>
                                        <option value="Top">Top</option>
                                        <option value="Upper">Upper</option>
                                        <option value="Lower">Lower</option>
                                        <option value="Bottom">Bottom</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-gray-700 mb-2">Horizontal Position</label>
                                    <select name="horizontal_position" class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="Not Specified" selected>Not Specified</option>
                                        <option value="Front">Front</option>
                                        <option value="Center">Center</option>
                                        <option value="Back">Back</option>
                                    </select>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">Create File</button>
                                <a href="?page=files" class="bg-gray-300 text-gray-700 px-6 py-2 rounded hover:bg-gray-400">Cancel</a>
                            </div>
                        </form>
                    </div>

                <?php elseif ($page === 'files' && $action === 'view' && $id): ?>
                    <!-- View File Detail with Tags, Entity, and Related Files -->
                    <?php
                    $file = $db->fetchOne("
                        SELECT f.*, u.name as owner_name, d.label as drawer_label, d.id as drawer_id,
                               c.label as cabinet_label, c.id as cabinet_id, l.name as location_name, l.id as location_id,
                               e.name as entity_name, e.description as entity_description, e.contact_info as entity_contact,
                               cu.name as checked_out_user_name, cu.id as checked_out_user_id
                        FROM files f
                        LEFT JOIN users u ON f.owner_id = u.id
                        LEFT JOIN users cu ON f.checked_out_by = cu.id
                        LEFT JOIN drawers d ON f.current_drawer_id = d.id
                        LEFT JOIN cabinets c ON d.cabinet_id = c.id
                        LEFT JOIN locations l ON c.location_id = l.id
                        LEFT JOIN entities e ON f.entity_id = e.id
                        WHERE f.id = ?
                    ", [$id]);

                    if (!$file): ?>
                        <div class="bg-red-100 text-red-700 p-4 rounded">File not found</div>
                    <?php else:
                        // Get tags for this file
                        $fileTags = $db->fetchAll("
                            SELECT t.* FROM tags t
                            JOIN file_tags ft ON t.id = ft.tag_id
                            WHERE ft.file_id = ?
                            ORDER BY t.name
                        ", [$id]);

                        // Get related files (files with shared tags)
                        $relatedFiles = [];
                        if (!empty($fileTags)) {
                            $tagIds = array_column($fileTags, 'id');
                            $placeholders = str_repeat('?,', count($tagIds) - 1) . '?';
                            $relatedFiles = $db->fetchAll("
                                SELECT DISTINCT f.id, f.display_number, f.name, COUNT(ft.tag_id) as shared_tags
                                FROM files f
                                JOIN file_tags ft ON f.id = ft.file_id
                                WHERE ft.tag_id IN ($placeholders)
                                AND f.id != ?
                                AND f.is_archived = 0 AND f.is_destroyed = 0
                                GROUP BY f.id
                                ORDER BY shared_tags DESC, f.name
                                LIMIT 10
                            ", array_merge($tagIds, [$id]));
                        }
                    ?>
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-3xl font-bold">File #<?= htmlspecialchars($file['display_number']) ?></h2>
                            <div class="flex gap-2">
                                <a href="?page=files&action=edit&id=<?= $file['id'] ?>" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Edit</a>
                                <a href="?page=files" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400">Back to List</a>
                            </div>
                        </div>

                        <?php if ($file['is_destroyed']): ?>
                            <!-- DESTROYED Banner (Critical Priority) -->
                            <div class="bg-red-600 border-4 border-red-800 rounded-lg p-8 mb-6 text-white">
                                <div class="flex items-center gap-4 mb-4">
                                    <svg class="w-16 h-16 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                    </svg>
                                    <h3 class="text-4xl font-black uppercase">THIS FILE HAS BEEN DESTROYED</h3>
                                </div>
                                <div class="mb-4 bg-red-700 p-4 rounded">
                                    <div class="text-lg mb-2"><strong>Destroyed on:</strong> <?= date('F j, Y g:i A', strtotime($file['destroyed_at'])) ?></div>
                                    <?php
                                    $destroyedBy = $db->fetchOne("SELECT name FROM users WHERE id = ?", [$file['destroyed_by']]);
                                    if ($destroyedBy):
                                    ?>
                                        <div class="text-lg mb-2"><strong>Authorized by:</strong> <?= htmlspecialchars($destroyedBy['name']) ?></div>
                                    <?php endif; ?>
                                    <div class="text-lg"><strong>Destruction method:</strong> <?= htmlspecialchars($file['destruction_method']) ?></div>
                                </div>
                                <div class="flex gap-3">
                                    <a href="?page=files&action=certificate&id=<?= $file['id'] ?>" target="_blank"
                                       class="bg-white text-red-700 px-6 py-3 rounded hover:bg-gray-100 font-bold flex items-center gap-2">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                                        </svg>
                                        Print Certificate of Destruction
                                    </a>
                                    <?php if ($_SESSION['user_role'] === 'admin'): ?>
                                        <button onclick="document.getElementById('restoreDestructionModal').classList.remove('hidden')"
                                                class="bg-yellow-500 text-white px-6 py-3 rounded hover:bg-yellow-600 font-bold flex items-center gap-2">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                            </svg>
                                            Restore (Undo Destruction)
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Restore from Destruction Modal (Admin Only) -->
                            <?php if ($_SESSION['user_role'] === 'admin'): ?>
                                <div id="restoreDestructionModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                                    <div class="bg-white rounded-lg p-8 max-w-md">
                                        <h3 class="text-2xl font-bold text-red-700 mb-4">WARNING: Restore from Destroyed State</h3>
                                        <p class="text-gray-700 mb-4">
                                            This action should ONLY be used to correct a mistake. Restoring a file from destroyed state
                                            has legal and compliance implications.
                                        </p>
                                        <p class="text-gray-700 mb-6 font-medium">
                                            Are you absolutely certain this destruction was recorded in error?
                                        </p>
                                        <form method="POST" action="?page=files&action=restore_destruction&id=<?= $file['id'] ?>">
                                            <div class="mb-4">
                                                <label class="flex items-center">
                                                    <input type="checkbox" name="confirm_restore" required class="mr-2">
                                                    <span class="text-sm">I confirm this destruction was recorded in error and must be undone</span>
                                                </label>
                                            </div>
                                            <div class="flex gap-2">
                                                <button type="submit" class="bg-red-600 text-white px-6 py-2 rounded hover:bg-red-700 font-medium">
                                                    Restore File
                                                </button>
                                                <button type="button" onclick="document.getElementById('restoreDestructionModal').classList.add('hidden')"
                                                        class="bg-gray-300 text-gray-700 px-6 py-2 rounded hover:bg-gray-400">
                                                    Cancel
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if ($file['is_archived'] && !$file['is_destroyed']): ?>
                            <!-- Archived Banner -->
                            <div class="bg-yellow-100 border-2 border-yellow-500 rounded-lg p-6 mb-6">
                                <div class="flex items-center gap-3 mb-3">
                                    <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                    </svg>
                                    <h3 class="text-2xl font-bold text-yellow-800">This file is archived</h3>
                                </div>
                                <div class="mb-3">
                                    <div class="text-sm text-gray-700 mb-1"><strong>Archived on:</strong> <?= date('F j, Y g:i A', strtotime($file['archived_at'])) ?></div>
                                    <?php
                                    $archivedBy = $db->fetchOne("SELECT name FROM users WHERE id = ?", [$file['archived_by']]);
                                    if ($archivedBy):
                                    ?>
                                        <div class="text-sm text-gray-700 mb-1"><strong>Archived by:</strong> <?= htmlspecialchars($archivedBy['name']) ?></div>
                                    <?php endif; ?>
                                    <div class="text-sm text-gray-700"><strong>Reason:</strong> <?= nl2br(htmlspecialchars($file['archived_reason'])) ?></div>
                                </div>
                                <?php if ($_SESSION['user_role'] === 'admin'): ?>
                                    <form method="POST" action="?page=files&action=restore&id=<?= $file['id'] ?>" class="inline" onsubmit="return confirm('Are you sure you want to restore this file from archive?');">
                                        <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded hover:bg-green-700 font-medium">
                                            Restore File
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div class="bg-white rounded-lg shadow p-6 mb-6">
                            <div class="grid grid-cols-2 gap-6 mb-6">
                                <div>
                                    <h3 class="font-bold text-lg mb-4"><?= htmlspecialchars($file['name']) ?></h3>
                                    <div class="space-y-2">
                                        <div><span class="text-gray-600">UUID:</span> <span class="font-mono text-sm"><?= $file['uuid'] ?></span></div>
                                        <div><span class="text-gray-600">Owner:</span> <?= htmlspecialchars($file['owner_name'] ?? 'N/A') ?></div>
                                        <div><span class="text-gray-600">Sensitivity:</span>
                                            <span class="px-2 py-1 text-xs rounded <?= $file['sensitivity'] === 'confidential' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800' ?>">
                                                <?= ucfirst($file['sensitivity']) ?>
                                            </span>
                                        </div>
                                        <div><span class="text-gray-600">Status:</span>
                                            <?php if ($file['is_checked_out']): ?>
                                                <span class="px-2 py-1 text-xs bg-orange-100 text-orange-800 rounded">Checked Out</span>
                                            <?php else: ?>
                                                <span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded">Available</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <h4 class="font-bold mb-2">Location</h4>
                                    <?php if ($file['location_name']): ?>
                                        <div class="text-sm space-y-1">
                                            <div>Location: <?= htmlspecialchars($file['location_name']) ?></div>
                                            <div>Cabinet: <?= htmlspecialchars($file['cabinet_label']) ?></div>
                                            <div>Drawer: <?= htmlspecialchars($file['drawer_label']) ?></div>
                                            <?php if (!empty($file['vertical_position']) && $file['vertical_position'] !== 'Not Specified'): ?>
                                                <div>Vertical: <span class="font-medium"><?= htmlspecialchars($file['vertical_position']) ?></span></div>
                                            <?php endif; ?>
                                            <?php if (!empty($file['horizontal_position']) && $file['horizontal_position'] !== 'Not Specified'): ?>
                                                <div>Horizontal: <span class="font-medium"><?= htmlspecialchars($file['horizontal_position']) ?></span></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-gray-500">Not assigned to a location</div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if ($file['entity_name']): ?>
                                <div class="mb-6 p-4 bg-blue-50 rounded">
                                    <h4 class="font-bold mb-2">Entity</h4>
                                    <div class="text-sm space-y-1">
                                        <div><span class="text-gray-600">Name:</span> <?= htmlspecialchars($file['entity_name']) ?></div>
                                        <?php if ($file['entity_description']): ?>
                                            <div><span class="text-gray-600">Description:</span> <?= htmlspecialchars($file['entity_description']) ?></div>
                                        <?php endif; ?>
                                        <?php if ($file['entity_contact']): ?>
                                            <div><span class="text-gray-600">Contact:</span> <?= htmlspecialchars($file['entity_contact']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($file['description']): ?>
                                <div class="mb-6">
                                    <h4 class="font-bold mb-2">Description</h4>
                                    <p class="text-gray-700"><?= nl2br(htmlspecialchars($file['description'])) ?></p>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($fileTags)): ?>
                                <div class="mb-6">
                                    <h4 class="font-bold mb-2">Tags</h4>
                                    <div class="flex flex-wrap gap-2">
                                        <?php foreach ($fileTags as $tag): ?>
                                            <a href="?page=search&tag=<?= $tag['id'] ?>"
                                               class="inline-flex items-center gap-2 px-3 py-1 rounded hover:opacity-80"
                                               style="background-color: <?= htmlspecialchars($tag['color']) ?>20; border: 1px solid <?= htmlspecialchars($tag['color']) ?>">
                                                <div class="w-2 h-2 rounded-full" style="background-color: <?= htmlspecialchars($tag['color']) ?>"></div>
                                                <span style="color: <?= htmlspecialchars($tag['color']) ?>"><?= htmlspecialchars($tag['name']) ?></span>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="text-sm text-gray-500">
                                Created: <?= $file['created_at'] ?> | Updated: <?= $file['updated_at'] ?>
                            </div>
                        </div>

                        <!-- QR CODE SECTION -->
                        <div class="bg-white rounded-lg shadow p-6 mb-6">
                            <h3 class="font-bold text-lg mb-4">QR Code & Labels</h3>
                            <div class="grid grid-cols-2 gap-6">
                                <div>
                                    <h4 class="font-semibold mb-3">File QR Code</h4>
                                    <p class="text-sm text-gray-600 mb-4">Scan this code to quickly look up this file</p>
                                    <?php
                                    $lookupURL = getBaseURL() . '?page=lookup&uuid=' . urlencode($file['uuid']);
                                    $qrCodeURL = generateQRCodeURL($lookupURL, 200);
                                    ?>
                                    <div class="mb-4 p-4 bg-gray-50 rounded text-center">
                                        <img src="<?= $qrCodeURL ?>" alt="QR Code" class="mx-auto mb-2" style="width: 200px; height: 200px;">
                                        <div class="text-xs text-gray-500 break-all"><?= htmlspecialchars($file['uuid']) ?></div>
                                    </div>
                                    <div class="flex gap-2">
                                        <a href="?page=files&action=print_label&id=<?= $file['id'] ?>" target="_blank"
                                           class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm flex items-center gap-2">
                                            <span>🖨️</span>
                                            <span>Print Label</span>
                                        </a>
                                        <button onclick="window.open('<?= $qrCodeURL ?>&size=500x500', '_blank')"
                                                class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 text-sm flex items-center gap-2">
                                            <span>🔍</span>
                                            <span>View Large</span>
                                        </button>
                                    </div>
                                </div>
                                <div>
                                    <h4 class="font-semibold mb-3">Quick Access</h4>
                                    <div class="space-y-3">
                                        <div class="p-3 bg-gray-50 rounded">
                                            <div class="text-xs text-gray-600 mb-1">Direct Lookup URL:</div>
                                            <div class="text-xs font-mono bg-white p-2 rounded border break-all">
                                                <?= htmlspecialchars($lookupURL) ?>
                                            </div>
                                        </div>
                                        <div class="p-3 bg-gray-50 rounded">
                                            <div class="text-xs text-gray-600 mb-1">File Number:</div>
                                            <div class="text-2xl font-bold text-blue-600">
                                                #<?= htmlspecialchars($file['display_number']) ?>
                                            </div>
                                        </div>
                                        <div class="text-xs text-gray-600">
                                            Tip: Use the mobile scanner or lookup page to find files quickly by scanning their QR codes.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- CHECKOUT/CHECKIN SECTION -->
                        <?php if (!$file['is_checked_out']): ?>
                            <!-- File is available - show checkout form -->
                            <div class="bg-white rounded-lg shadow p-6 mb-6">
                                <h3 class="font-bold text-lg mb-4 flex items-center gap-2">
                                    <span>Check Out File</span>
                                    <span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded">Available</span>
                                </h3>
                                <form method="POST" action="?page=files&action=checkout&id=<?= $file['id'] ?>">
                                    <?php if ($_SESSION['user_role'] === 'admin'): ?>
                                        <div class="mb-4">
                                            <label class="block text-gray-700 mb-2">Assign to User *</label>
                                            <select name="user_id" class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                                <option value="<?= $_SESSION['user_id'] ?>">Myself (<?= htmlspecialchars($_SESSION['user_name']) ?>)</option>
                                                <?php
                                                $allUsers = $db->fetchAll("SELECT id, name, email FROM users WHERE status = 'active' AND id != ? ORDER BY name", [$_SESSION['user_id']]);
                                                foreach ($allUsers as $u):
                                                ?>
                                                    <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['email']) ?>)</option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    <?php endif; ?>
                                    <div class="mb-4">
                                        <label class="block text-gray-700 mb-2">Expected Return Date *</label>
                                        <input type="date" name="expected_return_date" required min="<?= date('Y-m-d') ?>"
                                               value="<?= date('Y-m-d', strtotime('+7 days')) ?>"
                                               class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    </div>
                                    <div class="mb-4">
                                        <label class="block text-gray-700 mb-2">Notes (optional)</label>
                                        <textarea name="notes" rows="3" placeholder="Reason for checkout, special handling instructions, etc."
                                                  class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                                    </div>
                                    <button type="submit" class="bg-orange-600 text-white px-6 py-2 rounded hover:bg-orange-700 flex items-center gap-2">
                                        <span>Check Out File</span>
                                    </button>
                                </form>
                            </div>
                        <?php else: ?>
                            <!-- File is checked out - show status and checkin option -->
                            <?php
                            $overdue = isOverdue($file['expected_return_date']);
                            $canCheckin = ($_SESSION['user_role'] === 'admin' || $file['checked_out_by'] == $_SESSION['user_id']);
                            ?>
                            <div class="bg-white rounded-lg shadow p-6 mb-6 <?= $overdue ? 'border-2 border-red-500' : '' ?>">
                                <h3 class="font-bold text-lg mb-4 flex items-center gap-2">
                                    <span>Checkout Status</span>
                                    <span class="px-2 py-1 text-xs <?= $overdue ? 'bg-red-100 text-red-800' : 'bg-orange-100 text-orange-800' ?> rounded">
                                        <?= $overdue ? 'OVERDUE' : 'Checked Out' ?>
                                    </span>
                                </h3>
                                <div class="grid grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <div class="text-sm text-gray-600">Checked Out By</div>
                                        <div class="font-medium"><?= htmlspecialchars($file['checked_out_user_name']) ?></div>
                                    </div>
                                    <div>
                                        <div class="text-sm text-gray-600">Checkout Date</div>
                                        <div class="font-medium"><?= date('M j, Y', strtotime($file['checked_out_at'])) ?></div>
                                    </div>
                                    <div>
                                        <div class="text-sm text-gray-600">Expected Return</div>
                                        <div class="font-medium <?= $overdue ? 'text-red-600' : '' ?>">
                                            <?= date('M j, Y', strtotime($file['expected_return_date'])) ?>
                                        </div>
                                    </div>
                                    <?php if ($overdue): ?>
                                        <div>
                                            <div class="text-sm text-gray-600">Days Overdue</div>
                                            <div class="font-bold text-red-600"><?= daysOverdue($file['expected_return_date']) ?> days</div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php if ($canCheckin): ?>
                                    <div class="border-t pt-4">
                                        <form method="POST" action="?page=files&action=checkin&id=<?= $file['id'] ?>" onsubmit="return confirm('Are you sure you want to check in this file?');">
                                            <div class="mb-4">
                                                <label class="block text-gray-700 mb-2">Return Notes (optional)</label>
                                                <textarea name="return_notes" rows="2" placeholder="Condition, issues, etc."
                                                          class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                                            </div>
                                            <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded hover:bg-green-700 flex items-center gap-2">
                                                <span>Check In File</span>
                                            </button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <div class="p-3 bg-gray-100 rounded text-sm text-gray-600">
                                        Only the person who checked out this file or an administrator can check it back in.
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- CHECKOUT HISTORY -->
                        <?php
                        $checkoutHistory = $db->fetchAll("
                            SELECT fc.*, u.name as user_name
                            FROM file_checkouts fc
                            LEFT JOIN users u ON fc.user_id = u.id
                            WHERE fc.file_id = ?
                            ORDER BY fc.checked_out_at DESC
                            LIMIT 20
                        ", [$id]);

                        if (!empty($checkoutHistory)):
                        ?>
                            <div class="bg-white rounded-lg shadow p-6 mb-6">
                                <h3 class="font-bold text-lg mb-4">Checkout History</h3>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full text-sm">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Checked Out</th>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Expected Return</th>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Returned</th>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Duration</th>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Notes</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php foreach ($checkoutHistory as $checkout): ?>
                                                <?php
                                                $wasOverdue = $checkout['returned_at'] && strtotime($checkout['returned_at']) > strtotime($checkout['expected_return_date']);
                                                $isOverdueNow = !$checkout['returned_at'] && isOverdue($checkout['expected_return_date']);
                                                $duration = '';
                                                if ($checkout['returned_at']) {
                                                    $days = floor((strtotime($checkout['returned_at']) - strtotime($checkout['checked_out_at'])) / 86400);
                                                    $duration = $days . ' day' . ($days != 1 ? 's' : '');
                                                } else {
                                                    $days = floor((time() - strtotime($checkout['checked_out_at'])) / 86400);
                                                    $duration = $days . ' day' . ($days != 1 ? 's' : '') . ' (ongoing)';
                                                }
                                                ?>
                                                <tr class="<?= ($wasOverdue || $isOverdueNow) ? 'bg-red-50' : '' ?>">
                                                    <td class="px-4 py-2"><?= htmlspecialchars($checkout['user_name']) ?></td>
                                                    <td class="px-4 py-2"><?= date('M j, Y', strtotime($checkout['checked_out_at'])) ?></td>
                                                    <td class="px-4 py-2 <?= ($wasOverdue || $isOverdueNow) ? 'text-red-600 font-medium' : '' ?>">
                                                        <?= date('M j, Y', strtotime($checkout['expected_return_date'])) ?>
                                                        <?php if ($isOverdueNow): ?>
                                                            <span class="ml-1 px-1 py-0.5 text-xs bg-red-200 text-red-800 rounded">OVERDUE</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-4 py-2">
                                                        <?= $checkout['returned_at'] ? date('M j, Y', strtotime($checkout['returned_at'])) : '<span class="text-orange-600">Not returned</span>' ?>
                                                    </td>
                                                    <td class="px-4 py-2"><?= $duration ?></td>
                                                    <td class="px-4 py-2 text-xs text-gray-600"><?= htmlspecialchars($checkout['notes'] ?? '-') ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- MANUAL FILE MOVE SECTION -->
                        <?php if (!$file['is_destroyed']): ?>
                            <div class="bg-white rounded-lg shadow p-6 mb-6">
                                <h3 class="font-bold text-lg mb-4 flex items-center gap-2">
                                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                                    </svg>
                                    <span>Move File</span>
                                </h3>

                                <?php if ($file['location_name']): ?>
                                    <div class="mb-4 p-3 bg-blue-50 rounded">
                                        <div class="text-sm text-gray-700">
                                            <strong>Current Location:</strong> <?= htmlspecialchars($file['location_name'] . ' > ' . $file['cabinet_label'] . ' > Drawer ' . $file['drawer_label']) ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="mb-4 p-3 bg-gray-50 rounded">
                                        <div class="text-sm text-gray-600">This file is not currently assigned to a drawer</div>
                                    </div>
                                <?php endif; ?>

                                <?php if ($file['is_checked_out'] && $_SESSION['user_role'] !== 'admin'): ?>
                                    <div class="p-3 bg-yellow-50 border border-yellow-200 rounded text-sm text-yellow-800">
                                        This file is checked out. Only administrators can move checked out files.
                                    </div>
                                <?php elseif ($file['is_archived']): ?>
                                    <div class="p-3 bg-yellow-50 border border-yellow-200 rounded text-sm text-yellow-800 mb-4">
                                        Warning: This file is archived. Moving it will not change its archived status.
                                    </div>
                                    <form method="POST" action="?page=files&action=move&id=<?= $file['id'] ?>">
                                        <div class="mb-4">
                                            <label class="block text-gray-700 mb-2 font-medium">New Drawer *</label>
                                            <select name="new_drawer_id" required class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                                <option value="">Select a drawer...</option>
                                                <?php
                                                $allDrawers = $db->fetchAll("
                                                    SELECT d.id, d.label as drawer_label, c.label as cabinet_label, l.name as location_name
                                                    FROM drawers d
                                                    JOIN cabinets c ON d.cabinet_id = c.id
                                                    LEFT JOIN locations l ON c.location_id = l.id
                                                    ORDER BY l.name, c.label, d.position
                                                ");
                                                foreach ($allDrawers as $drawer):
                                                    $selected = ($file['drawer_id'] == $drawer['id']) ? 'selected disabled' : '';
                                                ?>
                                                    <option value="<?= $drawer['id'] ?>" <?= $selected ?>>
                                                        <?= htmlspecialchars(($drawer['location_name'] ?? 'No Location') . ' > ' . $drawer['cabinet_label'] . ' > Drawer ' . $drawer['drawer_label']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="mb-4">
                                            <label class="block text-gray-700 mb-2">Reason for Move (Optional)</label>
                                            <textarea name="move_notes" rows="2" placeholder="e.g., Reorganization, space optimization, etc."
                                                      class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                                        </div>
                                        <div class="mb-4">
                                            <label class="flex items-center">
                                                <input type="checkbox" name="confirm_archived" value="1" class="mr-2">
                                                <span class="text-sm">I confirm I want to move this archived file</span>
                                            </label>
                                        </div>
                                        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 font-medium">
                                            Move File
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" action="?page=files&action=move&id=<?= $file['id'] ?>">
                                        <div class="mb-4">
                                            <label class="block text-gray-700 mb-2 font-medium">New Drawer *</label>
                                            <select name="new_drawer_id" required class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                                <option value="">Select a drawer...</option>
                                                <?php
                                                $allDrawers = $db->fetchAll("
                                                    SELECT d.id, d.label as drawer_label, c.label as cabinet_label, l.name as location_name
                                                    FROM drawers d
                                                    JOIN cabinets c ON d.cabinet_id = c.id
                                                    LEFT JOIN locations l ON c.location_id = l.id
                                                    ORDER BY l.name, c.label, d.position
                                                ");
                                                foreach ($allDrawers as $drawer):
                                                    $selected = ($file['drawer_id'] == $drawer['id']) ? 'selected disabled' : '';
                                                ?>
                                                    <option value="<?= $drawer['id'] ?>" <?= $selected ?>>
                                                        <?= htmlspecialchars(($drawer['location_name'] ?? 'No Location') . ' > ' . $drawer['cabinet_label'] . ' > Drawer ' . $drawer['drawer_label']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="mb-4">
                                            <label class="block text-gray-700 mb-2">Reason for Move (Optional)</label>
                                            <textarea name="move_notes" rows="2" placeholder="e.g., Reorganization, space optimization, etc."
                                                      class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                                        </div>
                                        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 font-medium">
                                            Move File
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- MOVEMENT HISTORY -->
                        <?php
                        $movementHistory = $db->fetchAll("
                            SELECT fm.*,
                                   u.name as moved_by_name,
                                   l_from.name as from_location_name, c_from.label as from_cabinet_label, d_from.label as from_drawer_label,
                                   l_to.name as to_location_name, c_to.label as to_cabinet_label, d_to.label as to_drawer_label
                            FROM file_movements fm
                            LEFT JOIN users u ON fm.moved_by = u.id
                            LEFT JOIN drawers d_from ON fm.from_drawer_id = d_from.id
                            LEFT JOIN cabinets c_from ON d_from.cabinet_id = c_from.id
                            LEFT JOIN locations l_from ON c_from.location_id = l_from.id
                            LEFT JOIN drawers d_to ON fm.to_drawer_id = d_to.id
                            LEFT JOIN cabinets c_to ON d_to.cabinet_id = c_to.id
                            LEFT JOIN locations l_to ON c_to.location_id = l_to.id
                            WHERE fm.file_id = ?
                            ORDER BY fm.moved_at DESC
                            LIMIT 50
                        ", [$id]);

                        if (!empty($movementHistory)):
                        ?>
                            <div class="bg-white rounded-lg shadow p-6 mb-6">
                                <h3 class="font-bold text-lg mb-4 flex items-center gap-2">
                                    <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <span>Movement History</span>
                                </h3>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full text-sm">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">From</th>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">To</th>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Moved By</th>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Notes</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php foreach ($movementHistory as $movement): ?>
                                                <tr>
                                                    <td class="px-4 py-2 whitespace-nowrap"><?= date('M j, Y g:i A', strtotime($movement['moved_at'])) ?></td>
                                                    <td class="px-4 py-2">
                                                        <?php if ($movement['from_location_name']): ?>
                                                            <span class="text-xs"><?= htmlspecialchars($movement['from_location_name'] . ' > ' . $movement['from_cabinet_label'] . ' > ' . $movement['from_drawer_label']) ?></span>
                                                        <?php else: ?>
                                                            <span class="text-gray-400 italic">Unassigned</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-4 py-2">
                                                        <?php if ($movement['to_location_name']): ?>
                                                            <span class="text-xs"><?= htmlspecialchars($movement['to_location_name'] . ' > ' . $movement['to_cabinet_label'] . ' > ' . $movement['to_drawer_label']) ?></span>
                                                        <?php else: ?>
                                                            <span class="text-gray-400 italic">Unassigned</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-4 py-2"><?= htmlspecialchars($movement['moved_by_name'] ?? 'Unknown') ?></td>
                                                    <td class="px-4 py-2 text-xs text-gray-600"><?= htmlspecialchars($movement['notes'] ?? '-') ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="bg-white rounded-lg shadow p-6 mb-6">
                                <h3 class="font-bold text-lg mb-4 flex items-center gap-2">
                                    <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <span>Movement History</span>
                                </h3>
                                <p class="text-gray-500 text-sm">No movement history for this file</p>
                            </div>
                        <?php endif; ?>

                        <?php if (!$file['is_archived'] && $_SESSION['user_role'] === 'admin'): ?>
                            <!-- Archive File Section -->
                            <div class="bg-white rounded-lg shadow p-6 mb-6" x-data="{ showArchiveForm: false }">
                                <h3 class="font-bold text-lg mb-4 text-yellow-700">Archive File</h3>
                                <p class="text-sm text-gray-600 mb-4">Archiving a file removes it from active file listings but preserves all data for future reference.</p>
                                
                                <button @click="showArchiveForm = !showArchiveForm" 
                                        class="bg-yellow-500 text-white px-6 py-2 rounded hover:bg-yellow-600 font-medium"
                                        x-text="showArchiveForm ? 'Cancel' : 'Archive This File'">
                                    Archive This File
                                </button>

                                <div x-show="showArchiveForm" x-cloak class="mt-4 p-4 bg-yellow-50 rounded border border-yellow-200">
                                    <form method="POST" action="?page=files&action=archive&id=<?= $file['id'] ?>" onsubmit="return confirm('Are you sure you want to archive this file?');">
                                        <div class="mb-4">
                                            <label class="block text-gray-700 mb-2 font-medium">Archive Reason (Required) *</label>
                                            <textarea name="archived_reason" required rows="4" 
                                                      placeholder="Example: Project completed, Retention period expired, Superseded by newer version, etc."
                                                      class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-yellow-500"></textarea>
                                        </div>
                                        <button type="submit" class="bg-yellow-600 text-white px-6 py-2 rounded hover:bg-yellow-700 font-medium">
                                            Confirm Archive
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($file['is_archived'] && !$file['is_destroyed'] && $_SESSION['user_role'] === 'admin'): ?>
                            <!-- Destruction Section (Admin Only, Archived Files Only) -->
                            <div class="bg-white rounded-lg shadow p-6 mb-6 border-2 border-red-200" x-data="{ showDestructionForm: false }">
                                <h3 class="font-bold text-lg mb-4 text-red-700 flex items-center gap-2">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                    </svg>
                                    Mark File as Destroyed
                                </h3>
                                <div class="mb-4 p-4 bg-red-50 rounded border border-red-200">
                                    <p class="text-sm text-gray-700 mb-2"><strong>Workflow:</strong> Active → Archive → Destroy</p>
                                    <p class="text-sm text-red-700 font-medium">
                                        This is a PERMANENT action with legal implications. Only mark a file as destroyed after
                                        physical destruction has been completed according to your organization's policies.
                                    </p>
                                </div>

                                <button @click="showDestructionForm = !showDestructionForm"
                                        class="bg-red-600 text-white px-6 py-2 rounded hover:bg-red-700 font-bold"
                                        x-text="showDestructionForm ? 'Cancel' : 'Mark as Destroyed'">
                                    Mark as Destroyed
                                </button>

                                <div x-show="showDestructionForm" x-cloak class="mt-4 p-6 bg-red-50 rounded border-2 border-red-300">
                                    <form method="POST" action="?page=files&action=mark_destruction&id=<?= $file['id'] ?>">
                                        <div class="mb-4">
                                            <label class="block text-gray-700 mb-2 font-bold">Destruction Method *</label>
                                            <select name="destruction_method" required class="w-full px-3 py-2 border border-red-300 rounded focus:outline-none focus:ring-2 focus:ring-red-500">
                                                <option value="">-- Select Destruction Method --</option>
                                                <option value="Shredding (Cross-cut)">Shredding (Cross-cut)</option>
                                                <option value="Shredding (Strip-cut)">Shredding (Strip-cut)</option>
                                                <option value="Incineration">Incineration</option>
                                                <option value="Pulping">Pulping</option>
                                                <option value="Digital Deletion (Secure Wipe)">Digital Deletion (Secure Wipe)</option>
                                                <option value="Recycled">Recycled</option>
                                                <option value="Other">Other (specify in notes)</option>
                                            </select>
                                        </div>
                                        <div class="mb-4">
                                            <label class="block text-gray-700 mb-2 font-medium">Additional Notes (Optional)</label>
                                            <textarea name="notes" rows="3"
                                                      placeholder="Any additional details about the destruction process, certification numbers, witness information, etc."
                                                      class="w-full px-3 py-2 border border-red-300 rounded focus:outline-none focus:ring-2 focus:ring-red-500"></textarea>
                                        </div>
                                        <div class="mb-6 p-4 bg-white rounded border-2 border-red-400">
                                            <label class="flex items-start">
                                                <input type="checkbox" name="confirm_destruction" required class="mr-3 mt-1">
                                                <span class="text-sm font-bold text-red-700">
                                                    I confirm that this file has been properly destroyed according to organizational policies
                                                    and that the destruction method selected above accurately reflects the physical destruction
                                                    that has been completed.
                                                </span>
                                            </label>
                                        </div>
                                        <div class="flex gap-2">
                                            <button type="submit" class="bg-red-700 text-white px-8 py-3 rounded hover:bg-red-800 font-bold flex items-center gap-2">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                </svg>
                                                Confirm Destruction
                                            </button>
                                            <button type="button" @click="showDestructionForm = false"
                                                    class="bg-gray-300 text-gray-700 px-6 py-3 rounded hover:bg-gray-400">
                                                Cancel
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($relatedFiles)): ?>
                            <div class="bg-white rounded-lg shadow p-6">
                                <h3 class="font-bold text-lg mb-4">Related Files</h3>
                                <div class="space-y-2">
                                    <?php foreach ($relatedFiles as $related): ?>
                                        <div class="flex justify-between items-center p-3 bg-gray-50 rounded hover:bg-gray-100">
                                            <div>
                                                <span class="font-mono text-sm text-gray-600">#<?= htmlspecialchars($related['display_number']) ?></span>
                                                <span class="ml-3"><?= htmlspecialchars($related['name']) ?></span>
                                                <span class="ml-2 text-xs text-gray-500">(<?= $related['shared_tags'] ?> shared tag<?= $related['shared_tags'] > 1 ? 's' : '' ?>)</span>
                                            </div>
                                            <a href="?page=files&action=view&id=<?= $related['id'] ?>" class="text-blue-600 hover:underline text-sm">View</a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                <?php elseif ($page === 'files' && $action === 'print_label' && $id): ?>
                    <!-- Print Label for Single File (Avery 5160 Compatible) -->
                    <?php
                    $file = $db->fetchOne("SELECT * FROM files WHERE id = ?", [$id]);
                    if (!$file):
                    ?>
                        <div class="bg-red-100 text-red-700 p-4 rounded">File not found</div>
                    <?php else:
                        $lookupURL = getBaseURL() . '?page=lookup&uuid=' . urlencode($file['uuid']);
                        $qrCodeURL = generateQRCodeURL($lookupURL, 100);
                    ?>
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <title>Print Label - File #<?= htmlspecialchars($file['display_number']) ?></title>
                        <style>
                            @page {
                                size: letter;
                                margin: 0.5in;
                            }

                            body {
                                margin: 0;
                                padding: 20px;
                                font-family: Arial, sans-serif;
                            }

                            .label-sheet {
                                width: 8.5in;
                                margin: 0 auto;
                            }

                            .label {
                                width: 2.625in;
                                height: 1in;
                                padding: 0.05in;
                                border: 1px dashed #ccc;
                                display: inline-block;
                                margin: 0;
                                page-break-inside: avoid;
                                box-sizing: border-box;
                                vertical-align: top;
                            }

                            .label-content {
                                display: flex;
                                align-items: center;
                                height: 100%;
                                gap: 0.1in;
                            }

                            .label-qr {
                                flex-shrink: 0;
                            }

                            .label-qr img {
                                width: 0.75in;
                                height: 0.75in;
                                display: block;
                            }

                            .label-info {
                                flex: 1;
                                min-width: 0;
                                font-size: 9pt;
                            }

                            .label-number {
                                font-weight: bold;
                                font-size: 11pt;
                                margin-bottom: 2px;
                            }

                            .label-name {
                                font-size: 8pt;
                                overflow: hidden;
                                text-overflow: ellipsis;
                                white-space: nowrap;
                                margin-bottom: 2px;
                            }

                            .label-sensitivity {
                                display: inline-block;
                                padding: 1px 4px;
                                font-size: 7pt;
                                border-radius: 2px;
                                font-weight: bold;
                            }

                            .sensitivity-public { background: #e5e7eb; color: #374151; }
                            .sensitivity-internal { background: #dbeafe; color: #1e40af; }
                            .sensitivity-confidential { background: #fee2e2; color: #991b1b; }
                            .sensitivity-restricted { background: #fca5a5; color: #7f1d1d; }

                            .no-print {
                                margin-bottom: 20px;
                            }

                            @media print {
                                body {
                                    padding: 0;
                                }
                                .no-print {
                                    display: none;
                                }
                                .label {
                                    border: none;
                                }
                            }

                            .position-selector {
                                margin-bottom: 20px;
                                padding: 15px;
                                background: #f3f4f6;
                                border-radius: 8px;
                            }

                            .position-grid {
                                display: grid;
                                grid-template-columns: repeat(3, 1fr);
                                gap: 5px;
                                max-width: 400px;
                                margin-top: 10px;
                            }

                            .position-btn {
                                padding: 10px;
                                border: 2px solid #d1d5db;
                                background: white;
                                cursor: pointer;
                                border-radius: 4px;
                                font-size: 12px;
                            }

                            .position-btn:hover {
                                background: #e5e7eb;
                            }

                            .position-btn.active {
                                background: #3b82f6;
                                color: white;
                                border-color: #2563eb;
                            }
                        </style>
                        <script>
                            let startPosition = 1;

                            function setStartPosition(pos) {
                                startPosition = pos;
                                document.querySelectorAll('.position-btn').forEach(btn => {
                                    btn.classList.remove('active');
                                });
                                event.target.classList.add('active');
                                regenerateLabels();
                            }

                            function regenerateLabels() {
                                const sheet = document.getElementById('labelSheet');
                                sheet.innerHTML = '';

                                // Add empty labels before the start position
                                for (let i = 1; i < startPosition; i++) {
                                    const emptyLabel = document.createElement('div');
                                    emptyLabel.className = 'label';
                                    sheet.appendChild(emptyLabel);
                                }

                                // Add the actual label
                                const label = document.querySelector('.label-template').cloneNode(true);
                                label.classList.remove('label-template');
                                label.style.display = 'inline-block';
                                sheet.appendChild(label);
                            }

                            window.onload = function() {
                                regenerateLabels();
                            };
                        </script>
                    </head>
                    <body>
                        <div class="no-print">
                            <h2>Print Label - File #<?= htmlspecialchars($file['display_number']) ?></h2>
                            <p>Label format: Avery 5160 (30 labels per sheet, 2.625" × 1")</p>

                            <div class="position-selector">
                                <strong>Select starting position on sheet:</strong>
                                <div class="position-grid">
                                    <?php for ($i = 1; $i <= 30; $i++): ?>
                                        <button type="button" class="position-btn <?= $i === 1 ? 'active' : '' ?>" onclick="setStartPosition(<?= $i ?>)">
                                            Position <?= $i ?>
                                        </button>
                                    <?php endfor; ?>
                                </div>
                            </div>

                            <button onclick="window.print()" style="padding: 10px 20px; background: #3b82f6; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; margin-right: 10px;">
                                Print Label
                            </button>
                            <button onclick="window.close()" style="padding: 10px 20px; background: #6b7280; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px;">
                                Close
                            </button>
                        </div>

                        <!-- Hidden template -->
                        <div class="label label-template" style="display: none;">
                            <div class="label-content">
                                <div class="label-qr">
                                    <img src="<?= $qrCodeURL ?>" alt="QR">
                                </div>
                                <div class="label-info">
                                    <div class="label-number">FILE #<?= htmlspecialchars($file['display_number']) ?></div>
                                    <div class="label-name"><?= htmlspecialchars($file['name']) ?></div>
                                    <span class="label-sensitivity sensitivity-<?= $file['sensitivity'] ?>">
                                        <?= strtoupper($file['sensitivity']) ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="label-sheet" id="labelSheet">
                            <!-- Labels will be generated by JavaScript -->
                        </div>
                    </body>
                    </html>
                    <?php
                    exit; // Stop rendering the main layout for print view
                    endif;
                    ?>

                <?php elseif ($page === 'files' && $action === 'edit' && $id): ?>
                    <!-- Edit File Form with Tags and Entity -->
                    <?php
                    $file = $db->fetchOne("SELECT * FROM files WHERE id = ?", [$id]);
                    if (!$file):
                    ?>
                        <div class="bg-red-100 text-red-700 p-4 rounded">File not found</div>
                    <?php else:
                        // Get current tags for this file
                        $currentTags = $db->fetchAll("SELECT tag_id FROM file_tags WHERE file_id = ?", [$id]);
                        $currentTagIds = array_column($currentTags, 'tag_id');
                    ?>
                        <h2 class="text-3xl font-bold mb-6">Edit File #<?= htmlspecialchars($file['display_number']) ?></h2>
                        <div class="bg-white rounded-lg shadow p-6 max-w-2xl">
                            <form method="POST">
                                <div class="mb-4">
                                    <label class="block text-gray-700 mb-2">Display Number</label>
                                    <input type="text" value="<?= htmlspecialchars($file['display_number']) ?>" disabled class="w-full px-3 py-2 border rounded bg-gray-100">
                                    <p class="text-sm text-gray-500 mt-1">Display number cannot be changed</p>
                                </div>
                                <div class="mb-4">
                                    <label class="block text-gray-700 mb-2">File Name *</label>
                                    <input type="text" name="name" required value="<?= htmlspecialchars($file['name']) ?>"
                                           class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                </div>
                                <div class="mb-4">
                                    <label class="block text-gray-700 mb-2">Description</label>
                                    <textarea name="description" rows="3" class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500"><?= htmlspecialchars($file['description']) ?></textarea>
                                </div>
                                <div class="mb-4">
                                    <label class="block text-gray-700 mb-2">Sensitivity Level</label>
                                    <select name="sensitivity" class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="public" <?= $file['sensitivity'] === 'public' ? 'selected' : '' ?>>Public</option>
                                        <option value="internal" <?= $file['sensitivity'] === 'internal' ? 'selected' : '' ?>>Internal</option>
                                        <option value="confidential" <?= $file['sensitivity'] === 'confidential' ? 'selected' : '' ?>>Confidential</option>
                                        <option value="restricted" <?= $file['sensitivity'] === 'restricted' ? 'selected' : '' ?>>Restricted</option>
                                    </select>
                                </div>
                                <div class="mb-4">
                                    <label class="block text-gray-700 mb-2">Entity</label>
                                    <select name="entity_id" class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="">No entity</option>
                                        <?php foreach ($allEntities as $entity): ?>
                                            <option value="<?= $entity['id'] ?>" <?= $file['entity_id'] == $entity['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($entity['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-4">
                                    <label class="block text-gray-700 mb-2">Tags</label>
                                    <div class="border rounded p-3 max-h-48 overflow-y-auto">
                                        <?php if (empty($allTags)): ?>
                                            <p class="text-gray-500 text-sm">No tags available. <a href="?page=tags" class="text-blue-600 hover:underline">Create one</a></p>
                                        <?php else: ?>
                                            <?php foreach ($allTags as $tag): ?>
                                                <label class="flex items-center gap-2 py-1 hover:bg-gray-50 cursor-pointer">
                                                    <input type="checkbox" name="tags[]" value="<?= $tag['id'] ?>"
                                                           <?= in_array($tag['id'], $currentTagIds) ? 'checked' : '' ?>
                                                           class="rounded">
                                                    <div class="w-3 h-3 rounded" style="background-color: <?= htmlspecialchars($tag['color']) ?>"></div>
                                                    <span><?= htmlspecialchars($tag['name']) ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="mb-6">
                                    <label class="block text-gray-700 mb-2">Assign to Drawer</label>
                                    <select name="drawer_id" class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="">Not assigned</option>
                                        <?php
                                        $drawers = $db->fetchAll("
                                            SELECT d.id, d.label as drawer_label, c.label as cabinet_label, l.name as location_name
                                            FROM drawers d
                                            JOIN cabinets c ON d.cabinet_id = c.id
                                            LEFT JOIN locations l ON c.location_id = l.id
                                            ORDER BY l.name, c.label, d.position
                                        ");
                                        foreach ($drawers as $drawer):
                                        ?>
                                            <option value="<?= $drawer['id'] ?>" <?= $file['current_drawer_id'] == $drawer['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars(($drawer['location_name'] ?? 'No Location') . ' > ' . $drawer['cabinet_label'] . ' > Drawer ' . $drawer['drawer_label']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="grid grid-cols-2 gap-4 mb-6">
                                    <div>
                                        <label class="block text-gray-700 mb-2">Vertical Position</label>
                                        <select name="vertical_position" class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                            <option value="Not Specified" <?= ($file['vertical_position'] ?? 'Not Specified') === 'Not Specified' ? 'selected' : '' ?>>Not Specified</option>
                                            <option value="Top" <?= ($file['vertical_position'] ?? '') === 'Top' ? 'selected' : '' ?>>Top</option>
                                            <option value="Upper" <?= ($file['vertical_position'] ?? '') === 'Upper' ? 'selected' : '' ?>>Upper</option>
                                            <option value="Lower" <?= ($file['vertical_position'] ?? '') === 'Lower' ? 'selected' : '' ?>>Lower</option>
                                            <option value="Bottom" <?= ($file['vertical_position'] ?? '') === 'Bottom' ? 'selected' : '' ?>>Bottom</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 mb-2">Horizontal Position</label>
                                        <select name="horizontal_position" class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                            <option value="Not Specified" <?= ($file['horizontal_position'] ?? 'Not Specified') === 'Not Specified' ? 'selected' : '' ?>>Not Specified</option>
                                            <option value="Front" <?= ($file['horizontal_position'] ?? '') === 'Front' ? 'selected' : '' ?>>Front</option>
                                            <option value="Center" <?= ($file['horizontal_position'] ?? '') === 'Center' ? 'selected' : '' ?>>Center</option>
                                            <option value="Back" <?= ($file['horizontal_position'] ?? '') === 'Back' ? 'selected' : '' ?>>Back</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">Update File</button>
                                    <a href="?page=files&action=view&id=<?= $file['id'] ?>" class="bg-gray-300 text-gray-700 px-6 py-2 rounded hover:bg-gray-400">Cancel</a>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>

                <?php elseif ($page === 'files'): ?>
                    <!-- Files List -->
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-3xl font-bold">Files</h2>
                        <div class="flex gap-2">
                            <button id="bulkMoveBtn" onclick="showBulkMoveModal()" class="bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-700 hidden">
                                Bulk Move (<span id="selectedCount">0</span>)
                            </button>
                            <a href="?page=files&action=create" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">+ New File</a>
                        </div>
                    </div>
                    <?php
                    $files = $db->fetchAll("
                        SELECT f.*, u.name as owner_name, d.label as drawer_label, c.label as cabinet_label, e.name as entity_name,
                               cu.name as checked_out_user_name
                        FROM files f
                        LEFT JOIN users u ON f.owner_id = u.id
                        LEFT JOIN users cu ON f.checked_out_by = cu.id
                        LEFT JOIN drawers d ON f.current_drawer_id = d.id
                        LEFT JOIN cabinets c ON d.cabinet_id = c.id
                        LEFT JOIN entities e ON f.entity_id = e.id
                        WHERE f.is_archived = 0 AND f.is_destroyed = 0
                        ORDER BY f.created_at DESC
                    ");
                    ?>
                    <div class="bg-white rounded-lg shadow overflow-hidden">
                        <table class="min-w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left">
                                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll()" class="rounded">
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">File #</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Owner</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Entity</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Location</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($files)): ?>
                                    <tr><td colspan="8" class="px-6 py-4 text-center text-gray-500">
                                        No files yet. <a href="?page=files&action=create" class="text-blue-600 hover:underline">Create one</a>
                                    </td></tr>
                                <?php else: ?>
                                    <?php foreach ($files as $file): ?>
                                        <tr>
                                            <td class="px-6 py-4">
                                                <input type="checkbox" class="file-checkbox rounded" value="<?= $file['id'] ?>" onchange="updateBulkMoveButton()">
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap font-mono">#<?= htmlspecialchars($file['display_number']) ?></td>
                                            <td class="px-6 py-4"><?= htmlspecialchars($file['name']) ?></td>
                                            <td class="px-6 py-4"><?= htmlspecialchars($file['owner_name'] ?? 'N/A') ?></td>
                                            <td class="px-6 py-4"><?= htmlspecialchars($file['entity_name'] ?? 'N/A') ?></td>
                                            <td class="px-6 py-4">
                                                <?php if ($file['cabinet_label'] && $file['drawer_label']): ?>
                                                    <div class="text-sm">
                                                        <div><?= htmlspecialchars($file['cabinet_label'] . ' - ' . $file['drawer_label']) ?></div>
                                                        <?php if (!empty($file['vertical_position']) && $file['vertical_position'] !== 'Not Specified' || !empty($file['horizontal_position']) && $file['horizontal_position'] !== 'Not Specified'): ?>
                                                            <div class="text-xs text-gray-600">
                                                                <?php
                                                                $positions = [];
                                                                if (!empty($file['vertical_position']) && $file['vertical_position'] !== 'Not Specified') {
                                                                    $positions[] = $file['vertical_position'];
                                                                }
                                                                if (!empty($file['horizontal_position']) && $file['horizontal_position'] !== 'Not Specified') {
                                                                    $positions[] = $file['horizontal_position'];
                                                                }
                                                                echo htmlspecialchars(implode(' - ', $positions));
                                                                ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    Not assigned
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4">
                                                <?php if ($file['is_checked_out']): ?>
                                                    <?php $overdue = isOverdue($file['expected_return_date']); ?>
                                                    <div class="flex flex-col gap-1">
                                                        <span class="px-2 py-1 text-xs <?= $overdue ? 'bg-red-100 text-red-800' : 'bg-orange-100 text-orange-800' ?> rounded inline-block">
                                                            <?= $overdue ? 'OVERDUE' : 'Checked Out' ?>
                                                        </span>
                                                        <span class="text-xs text-gray-600">by <?= htmlspecialchars($file['checked_out_user_name']) ?></span>
                                                        <?php if ($overdue): ?>
                                                            <span class="text-xs text-red-600 font-bold"><?= daysOverdue($file['expected_return_date']) ?> days overdue</span>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded">Available</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4">
                                                <a href="?page=files&action=view&id=<?= $file['id'] ?>" class="text-blue-600 hover:underline mr-3">View</a>
                                                <a href="?page=files&action=edit&id=<?= $file['id'] ?>" class="text-green-600 hover:underline">Edit</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Bulk Move Modal -->
                    <div id="bulkMoveModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                        <div class="bg-white rounded-lg p-8 max-w-md w-full">
                            <h3 class="text-2xl font-bold mb-4">Bulk Move Files</h3>
                            <p class="text-gray-600 mb-4">Move <span id="bulkMoveCount">0</span> selected file(s) to a new drawer</p>
                            <form method="POST" action="?page=files&action=bulk_move">
                                <div class="mb-4">
                                    <label class="block text-gray-700 mb-2 font-medium">New Drawer *</label>
                                    <select name="bulk_drawer_id" required class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-purple-500">
                                        <option value="">Select a drawer...</option>
                                        <?php
                                        $allDrawersForBulk = $db->fetchAll("
                                            SELECT d.id, d.label as drawer_label, c.label as cabinet_label, l.name as location_name
                                            FROM drawers d
                                            JOIN cabinets c ON d.cabinet_id = c.id
                                            LEFT JOIN locations l ON c.location_id = l.id
                                            ORDER BY l.name, c.label, d.position
                                        ");
                                        foreach ($allDrawersForBulk as $drawer):
                                        ?>
                                            <option value="<?= $drawer['id'] ?>">
                                                <?= htmlspecialchars(($drawer['location_name'] ?? 'No Location') . ' > ' . $drawer['cabinet_label'] . ' > Drawer ' . $drawer['drawer_label']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-4">
                                    <label class="block text-gray-700 mb-2">Notes (Optional)</label>
                                    <textarea name="bulk_notes" rows="2" placeholder="Reason for bulk move..."
                                              class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-purple-500"></textarea>
                                </div>
                                <div id="bulkMoveFileIds"></div>
                                <div class="flex gap-2">
                                    <button type="submit" class="bg-purple-600 text-white px-6 py-2 rounded hover:bg-purple-700 font-medium">
                                        Move Files
                                    </button>
                                    <button type="button" onclick="hideBulkMoveModal()" class="bg-gray-300 text-gray-700 px-6 py-2 rounded hover:bg-gray-400">
                                        Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <script>
                    function updateBulkMoveButton() {
                        const checkboxes = document.querySelectorAll('.file-checkbox:checked');
                        const count = checkboxes.length;
                        document.getElementById('selectedCount').textContent = count;
                        document.getElementById('bulkMoveBtn').classList.toggle('hidden', count === 0);
                    }

                    function toggleSelectAll() {
                        const selectAll = document.getElementById('selectAll');
                        const checkboxes = document.querySelectorAll('.file-checkbox');
                        checkboxes.forEach(cb => cb.checked = selectAll.checked);
                        updateBulkMoveButton();
                    }

                    function showBulkMoveModal() {
                        const checkboxes = document.querySelectorAll('.file-checkbox:checked');
                        const count = checkboxes.length;
                        document.getElementById('bulkMoveCount').textContent = count;

                        // Add hidden inputs for file IDs
                        const container = document.getElementById('bulkMoveFileIds');
                        container.innerHTML = '';
                        checkboxes.forEach(cb => {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = 'file_ids[]';
                            input.value = cb.value;
                            container.appendChild(input);
                        });

                        document.getElementById('bulkMoveModal').classList.remove('hidden');
                    }

                    function hideBulkMoveModal() {
                        document.getElementById('bulkMoveModal').classList.add('hidden');
                    }
                    </script>

                <?php elseif ($page === 'my-checkouts'): ?>
                    <!-- My Checkouts Page -->
                    <h2 class="text-3xl font-bold mb-6">My Checkouts</h2>
                    <?php
                    $myCheckouts = $db->fetchAll("
                        SELECT f.*, u.name as owner_name
                        FROM files f
                        LEFT JOIN users u ON f.owner_id = u.id
                        WHERE f.checked_out_by = ? AND f.is_checked_out = 1
                        ORDER BY f.expected_return_date ASC, f.checked_out_at DESC
                    ", [$_SESSION['user_id']]);

                    $totalCheckouts = count($myCheckouts);
                    $overdueCount = 0;
                    foreach ($myCheckouts as $f) {
                        if (isOverdue($f['expected_return_date'])) $overdueCount++;
                    }
                    ?>

                    <!-- Stats -->
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div class="bg-white p-6 rounded-lg shadow">
                            <div class="text-3xl font-bold text-orange-600"><?= $totalCheckouts ?></div>
                            <div class="text-gray-600">Total Checked Out</div>
                        </div>
                        <div class="bg-white p-6 rounded-lg shadow <?= $overdueCount > 0 ? 'border-2 border-red-500' : '' ?>">
                            <div class="text-3xl font-bold <?= $overdueCount > 0 ? 'text-red-600' : 'text-green-600' ?>"><?= $overdueCount ?></div>
                            <div class="text-gray-600">Overdue</div>
                        </div>
                    </div>

                    <!-- Files List -->
                    <?php if (empty($myCheckouts)): ?>
                        <div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
                            You don't have any files checked out.
                        </div>
                    <?php else: ?>
                        <div class="bg-white rounded-lg shadow overflow-hidden">
                            <table class="min-w-full">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">File #</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Checked Out</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Expected Return</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Days Out</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($myCheckouts as $file): ?>
                                        <?php
                                        $overdue = isOverdue($file['expected_return_date']);
                                        $daysOut = floor((time() - strtotime($file['checked_out_at'])) / 86400);
                                        ?>
                                        <tr class="<?= $overdue ? 'bg-red-50' : '' ?>">
                                            <td class="px-6 py-4 whitespace-nowrap font-mono">#<?= htmlspecialchars($file['display_number']) ?></td>
                                            <td class="px-6 py-4"><?= htmlspecialchars($file['name']) ?></td>
                                            <td class="px-6 py-4"><?= date('M j, Y', strtotime($file['checked_out_at'])) ?></td>
                                            <td class="px-6 py-4 <?= $overdue ? 'font-bold' : '' ?>">
                                                <?= date('M j, Y', strtotime($file['expected_return_date'])) ?>
                                            </td>
                                            <td class="px-6 py-4"><?= $daysOut ?> day<?= $daysOut != 1 ? 's' : '' ?></td>
                                            <td class="px-6 py-4">
                                                <?php if ($overdue): ?>
                                                    <span class="px-2 py-1 text-xs bg-red-100 text-red-800 rounded font-bold">
                                                        OVERDUE (<?= daysOverdue($file['expected_return_date']) ?> days)
                                                    </span>
                                                <?php else: ?>
                                                    <span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded">OK</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4">
                                                <a href="?page=files&action=view&id=<?= $file['id'] ?>" class="text-blue-600 hover:underline mr-3">View</a>
                                                <form method="POST" action="?page=files&action=checkin&id=<?= $file['id'] ?>" class="inline" onsubmit="return confirm('Check in this file?');">
                                                    <button type="submit" class="text-green-600 hover:underline">Check In</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                <?php elseif ($page === 'movements'): ?>
                    <!-- File Movements Report Page -->
                    <h2 class="text-3xl font-bold mb-6">File Movement History</h2>

                    <?php
                    // Handle CSV export
                    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
                        header('Content-Type: text/csv');
                        header('Content-Disposition: attachment; filename="file_movements_' . date('Y-m-d') . '.csv"');

                        $output = fopen('php://output', 'w');
                        fputcsv($output, ['Date', 'File #', 'File Name', 'From Location', 'To Location', 'Moved By', 'Notes']);

                        // Build query based on filters
                        $where = ['1=1'];
                        $params = [];

                        if (!empty($_GET['date_range'])) {
                            if ($_GET['date_range'] === '7') {
                                $where[] = "fm.moved_at >= datetime('now', '-7 days')";
                            } elseif ($_GET['date_range'] === '30') {
                                $where[] = "fm.moved_at >= datetime('now', '-30 days')";
                            } elseif ($_GET['date_range'] === '90') {
                                $where[] = "fm.moved_at >= datetime('now', '-90 days')";
                            }
                        }

                        if (!empty($_GET['user_id'])) {
                            $where[] = "fm.moved_by = ?";
                            $params[] = $_GET['user_id'];
                        }

                        if (!empty($_GET['file_id'])) {
                            $where[] = "fm.file_id = ?";
                            $params[] = $_GET['file_id'];
                        }

                        $movements = $db->fetchAll("
                            SELECT fm.*,
                                   f.display_number, f.name as file_name,
                                   u.name as moved_by_name,
                                   l_from.name as from_location_name, c_from.label as from_cabinet_label, d_from.label as from_drawer_label,
                                   l_to.name as to_location_name, c_to.label as to_cabinet_label, d_to.label as to_drawer_label
                            FROM file_movements fm
                            LEFT JOIN files f ON fm.file_id = f.id
                            LEFT JOIN users u ON fm.moved_by = u.id
                            LEFT JOIN drawers d_from ON fm.from_drawer_id = d_from.id
                            LEFT JOIN cabinets c_from ON d_from.cabinet_id = c_from.id
                            LEFT JOIN locations l_from ON c_from.location_id = l_from.id
                            LEFT JOIN drawers d_to ON fm.to_drawer_id = d_to.id
                            LEFT JOIN cabinets c_to ON d_to.cabinet_id = c_to.id
                            LEFT JOIN locations l_to ON c_to.location_id = l_to.id
                            WHERE " . implode(' AND ', $where) . "
                            ORDER BY fm.moved_at DESC
                        ", $params);

                        foreach ($movements as $m) {
                            $fromLoc = $m['from_location_name'] ? ($m['from_location_name'] . ' > ' . $m['from_cabinet_label'] . ' > ' . $m['from_drawer_label']) : 'Unassigned';
                            $toLoc = $m['to_location_name'] ? ($m['to_location_name'] . ' > ' . $m['to_cabinet_label'] . ' > ' . $m['to_drawer_label']) : 'Unassigned';
                            fputcsv($output, [
                                date('Y-m-d H:i:s', strtotime($m['moved_at'])),
                                '#' . $m['display_number'],
                                $m['file_name'],
                                $fromLoc,
                                $toLoc,
                                $m['moved_by_name'],
                                $m['notes']
                            ]);
                        }
                        fclose($output);
                        exit;
                    }

                    // Build query based on filters
                    $where = ['1=1'];
                    $params = [];
                    $dateRange = $_GET['date_range'] ?? '30';

                    if (!empty($dateRange)) {
                        if ($dateRange === '7') {
                            $where[] = "fm.moved_at >= datetime('now', '-7 days')";
                        } elseif ($dateRange === '30') {
                            $where[] = "fm.moved_at >= datetime('now', '-30 days')";
                        } elseif ($dateRange === '90') {
                            $where[] = "fm.moved_at >= datetime('now', '-90 days')";
                        }
                    }

                    if (!empty($_GET['user_id'])) {
                        $where[] = "fm.moved_by = ?";
                        $params[] = $_GET['user_id'];
                    }

                    if (!empty($_GET['file_id'])) {
                        $where[] = "fm.file_id = ?";
                        $params[] = $_GET['file_id'];
                    }

                    if (!empty($_GET['location_id'])) {
                        $where[] = "(l_from.id = ? OR l_to.id = ?)";
                        $params[] = $_GET['location_id'];
                        $params[] = $_GET['location_id'];
                    }

                    $movements = $db->fetchAll("
                        SELECT fm.*,
                               f.display_number, f.name as file_name,
                               u.name as moved_by_name,
                               l_from.name as from_location_name, c_from.label as from_cabinet_label, d_from.label as from_drawer_label,
                               l_to.name as to_location_name, c_to.label as to_cabinet_label, d_to.label as to_drawer_label
                        FROM file_movements fm
                        LEFT JOIN files f ON fm.file_id = f.id
                        LEFT JOIN users u ON fm.moved_by = u.id
                        LEFT JOIN drawers d_from ON fm.from_drawer_id = d_from.id
                        LEFT JOIN cabinets c_from ON d_from.cabinet_id = c_from.id
                        LEFT JOIN locations l_from ON c_from.location_id = l_from.id
                        LEFT JOIN drawers d_to ON fm.to_drawer_id = d_to.id
                        LEFT JOIN cabinets c_to ON d_to.cabinet_id = c_to.id
                        LEFT JOIN locations l_to ON c_to.location_id = l_to.id
                        WHERE " . implode(' AND ', $where) . "
                        ORDER BY fm.moved_at DESC
                        LIMIT 500
                    ", $params);

                    // Calculate stats
                    $totalMovements = count($movements);
                    $userCounts = [];
                    $fileCounts = [];
                    foreach ($movements as $m) {
                        $userCounts[$m['moved_by_name']] = ($userCounts[$m['moved_by_name']] ?? 0) + 1;
                        $fileKey = '#' . $m['display_number'] . ' ' . $m['file_name'];
                        $fileCounts[$fileKey] = ($fileCounts[$fileKey] ?? 0) + 1;
                    }
                    arsort($userCounts);
                    arsort($fileCounts);
                    $mostActiveUser = !empty($userCounts) ? array_key_first($userCounts) : 'N/A';
                    $mostMovedFile = !empty($fileCounts) ? array_key_first($fileCounts) : 'N/A';
                    ?>

                    <!-- Stats Cards -->
                    <div class="grid grid-cols-3 gap-4 mb-6">
                        <div class="bg-white p-6 rounded-lg shadow">
                            <div class="text-3xl font-bold text-blue-600"><?= $totalMovements ?></div>
                            <div class="text-gray-600">Total Movements</div>
                            <div class="text-xs text-gray-500 mt-1">
                                <?php if ($dateRange === '7'): ?>Last 7 days
                                <?php elseif ($dateRange === '30'): ?>Last 30 days
                                <?php elseif ($dateRange === '90'): ?>Last 90 days
                                <?php else: ?>All time<?php endif; ?>
                            </div>
                        </div>
                        <div class="bg-white p-6 rounded-lg shadow">
                            <div class="text-lg font-bold text-purple-600"><?= htmlspecialchars($mostActiveUser) ?></div>
                            <div class="text-gray-600">Most Active User</div>
                            <?php if ($mostActiveUser !== 'N/A'): ?>
                                <div class="text-xs text-gray-500 mt-1"><?= $userCounts[$mostActiveUser] ?> movement(s)</div>
                            <?php endif; ?>
                        </div>
                        <div class="bg-white p-6 rounded-lg shadow">
                            <div class="text-sm font-bold text-orange-600"><?= htmlspecialchars($mostMovedFile) ?></div>
                            <div class="text-gray-600">Most Moved File</div>
                            <?php if ($mostMovedFile !== 'N/A'): ?>
                                <div class="text-xs text-gray-500 mt-1"><?= $fileCounts[$mostMovedFile] ?> movement(s)</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="bg-white rounded-lg shadow p-6 mb-6">
                        <h3 class="font-bold text-lg mb-4">Filters</h3>
                        <form method="GET" class="grid grid-cols-4 gap-4">
                            <input type="hidden" name="page" value="movements">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Date Range</label>
                                <select name="date_range" class="w-full px-3 py-2 border rounded">
                                    <option value="7" <?= $dateRange === '7' ? 'selected' : '' ?>>Last 7 days</option>
                                    <option value="30" <?= $dateRange === '30' ? 'selected' : '' ?>>Last 30 days</option>
                                    <option value="90" <?= $dateRange === '90' ? 'selected' : '' ?>>Last 90 days</option>
                                    <option value="all" <?= $dateRange === 'all' ? 'selected' : '' ?>>All time</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">User</label>
                                <select name="user_id" class="w-full px-3 py-2 border rounded">
                                    <option value="">All users</option>
                                    <?php
                                    $allUsers = $db->fetchAll("SELECT id, name FROM users ORDER BY name");
                                    foreach ($allUsers as $u):
                                    ?>
                                        <option value="<?= $u['id'] ?>" <?= (isset($_GET['user_id']) && $_GET['user_id'] == $u['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($u['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Location</label>
                                <select name="location_id" class="w-full px-3 py-2 border rounded">
                                    <option value="">All locations</option>
                                    <?php
                                    $allLocations = $db->fetchAll("SELECT id, name FROM locations ORDER BY name");
                                    foreach ($allLocations as $loc):
                                    ?>
                                        <option value="<?= $loc['id'] ?>" <?= (isset($_GET['location_id']) && $_GET['location_id'] == $loc['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($loc['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="flex items-end gap-2">
                                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Apply Filters</button>
                                <a href="?page=movements" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400">Reset</a>
                            </div>
                        </form>
                    </div>

                    <!-- Export Button -->
                    <div class="mb-4">
                        <a href="?page=movements&export=csv<?= !empty($_GET['date_range']) ? '&date_range=' . htmlspecialchars($_GET['date_range']) : '' ?><?= !empty($_GET['user_id']) ? '&user_id=' . htmlspecialchars($_GET['user_id']) : '' ?><?= !empty($_GET['file_id']) ? '&file_id=' . htmlspecialchars($_GET['file_id']) : '' ?><?= !empty($_GET['location_id']) ? '&location_id=' . htmlspecialchars($_GET['location_id']) : '' ?>"
                           class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 inline-flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            Export to CSV
                        </a>
                    </div>

                    <!-- Movements Table -->
                    <?php if (empty($movements)): ?>
                        <div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
                            No file movements found for the selected filters
                        </div>
                    <?php else: ?>
                        <div class="bg-white rounded-lg shadow overflow-hidden">
                            <table class="min-w-full text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">File</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">From Location</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">To Location</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Moved By</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Notes</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($movements as $m): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3 whitespace-nowrap"><?= date('M j, Y g:i A', strtotime($m['moved_at'])) ?></td>
                                            <td class="px-4 py-3">
                                                <div class="font-mono text-xs text-gray-600">#<?= htmlspecialchars($m['display_number']) ?></div>
                                                <div><?= htmlspecialchars($m['file_name']) ?></div>
                                            </td>
                                            <td class="px-4 py-3">
                                                <?php if ($m['from_location_name']): ?>
                                                    <span class="text-xs"><?= htmlspecialchars($m['from_location_name'] . ' > ' . $m['from_cabinet_label'] . ' > ' . $m['from_drawer_label']) ?></span>
                                                <?php else: ?>
                                                    <span class="text-gray-400 italic text-xs">Unassigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-3">
                                                <?php if ($m['to_location_name']): ?>
                                                    <span class="text-xs"><?= htmlspecialchars($m['to_location_name'] . ' > ' . $m['to_cabinet_label'] . ' > ' . $m['to_drawer_label']) ?></span>
                                                <?php else: ?>
                                                    <span class="text-gray-400 italic text-xs">Unassigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-3"><?= htmlspecialchars($m['moved_by_name'] ?? 'Unknown') ?></td>
                                            <td class="px-4 py-3 text-xs text-gray-600"><?= htmlspecialchars($m['notes'] ?? '-') ?></td>
                                            <td class="px-4 py-3">
                                                <a href="?page=files&action=view&id=<?= $m['file_id'] ?>" class="text-blue-600 hover:underline text-xs">View File</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if (count($movements) >= 500): ?>
                            <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded text-sm text-yellow-800">
                                Showing first 500 results. Use filters to narrow down the results.
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                <?php elseif ($page === 'checkouts'): ?>
                    <!-- All Checkouts Page (Admin Only) -->
                    <?php if ($_SESSION['user_role'] !== 'admin'): ?>
                        <div class="bg-red-100 text-red-700 p-4 rounded">
                            Unauthorized: Only administrators can access this page
                        </div>
                    <?php else: ?>
                        <h2 class="text-3xl font-bold mb-6">All Checked Out Files</h2>

                        <?php
                        $showOverdueOnly = isset($_GET['filter']) && $_GET['filter'] === 'overdue';

                        if ($showOverdueOnly) {
                            $allCheckouts = $db->fetchAll("
                                SELECT f.*, u.name as owner_name, cu.name as checked_out_user_name, cu.email as checked_out_user_email
                                FROM files f
                                LEFT JOIN users u ON f.owner_id = u.id
                                LEFT JOIN users cu ON f.checked_out_by = cu.id
                                WHERE f.is_checked_out = 1 AND f.expected_return_date < DATE('now')
                                ORDER BY f.expected_return_date ASC
                            ");
                        } else {
                            $allCheckouts = $db->fetchAll("
                                SELECT f.*, u.name as owner_name, cu.name as checked_out_user_name, cu.email as checked_out_user_email
                                FROM files f
                                LEFT JOIN users u ON f.owner_id = u.id
                                LEFT JOIN users cu ON f.checked_out_by = cu.id
                                WHERE f.is_checked_out = 1
                                ORDER BY f.expected_return_date ASC, f.checked_out_at DESC
                            ");
                        }

                        $overdueCount = 0;
                        foreach ($allCheckouts as $f) {
                            if (isOverdue($f['expected_return_date'])) $overdueCount++;
                        }
                        ?>

                        <!-- Filter Buttons -->
                        <div class="mb-6 flex gap-2">
                            <a href="?page=checkouts" class="px-4 py-2 rounded <?= !$showOverdueOnly ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700' ?>">
                                Show All (<?= count($allCheckouts) ?>)
                            </a>
                            <a href="?page=checkouts&filter=overdue" class="px-4 py-2 rounded <?= $showOverdueOnly ? 'bg-red-600 text-white' : 'bg-gray-200 text-gray-700' ?>">
                                Show Overdue Only (<?= $overdueCount ?>)
                            </a>
                        </div>

                        <!-- Files List -->
                        <?php if (empty($allCheckouts)): ?>
                            <div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
                                <?= $showOverdueOnly ? 'No overdue files.' : 'No files are currently checked out.' ?>
                            </div>
                        <?php else: ?>
                            <div class="bg-white rounded-lg shadow overflow-hidden">
                                <table class="min-w-full">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">File #</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Checked Out By</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Checked Out</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Expected Return</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($allCheckouts as $file): ?>
                                            <?php $overdue = isOverdue($file['expected_return_date']); ?>
                                            <tr class="<?= $overdue ? 'bg-red-50' : '' ?>">
                                                <td class="px-6 py-4 whitespace-nowrap font-mono">#<?= htmlspecialchars($file['display_number']) ?></td>
                                                <td class="px-6 py-4"><?= htmlspecialchars($file['name']) ?></td>
                                                <td class="px-6 py-4">
                                                    <div><?= htmlspecialchars($file['checked_out_user_name']) ?></div>
                                                    <div class="text-xs text-gray-500"><?= htmlspecialchars($file['checked_out_user_email']) ?></div>
                                                </td>
                                                <td class="px-6 py-4"><?= date('M j, Y', strtotime($file['checked_out_at'])) ?></td>
                                                <td class="px-6 py-4 <?= $overdue ? 'font-bold' : '' ?>">
                                                    <?= date('M j, Y', strtotime($file['expected_return_date'])) ?>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <?php if ($overdue): ?>
                                                        <span class="px-2 py-1 text-xs bg-red-100 text-red-800 rounded font-bold">
                                                            OVERDUE (<?= daysOverdue($file['expected_return_date']) ?> days)
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded">OK</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <a href="?page=files&action=view&id=<?= $file['id'] ?>" class="text-blue-600 hover:underline mr-3">View</a>
                                                    <form method="POST" action="?page=files&action=checkin&id=<?= $file['id'] ?>" class="inline" onsubmit="return confirm('Force check in this file?');">
                                                        <button type="submit" class="text-green-600 hover:underline">Force Check In</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                <?php elseif ($page === 'archived'): ?>
                    <!-- Archived Files Page -->
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-3xl font-bold">Archived Files</h2>
                    </div>

                    <?php
                    $archivedFiles = $db->fetchAll("
                        SELECT f.*, u.name as owner_name, d.label as drawer_label, c.label as cabinet_label, e.name as entity_name,
                               au.name as archived_by_name
                        FROM files f
                        LEFT JOIN users u ON f.owner_id = u.id
                        LEFT JOIN users au ON f.archived_by = au.id
                        LEFT JOIN drawers d ON f.current_drawer_id = d.id
                        LEFT JOIN cabinets c ON d.cabinet_id = c.id
                        LEFT JOIN entities e ON f.entity_id = e.id
                        WHERE f.is_archived = 1
                        ORDER BY f.archived_at DESC
                    ");
                    ?>

                    <?php if (empty($archivedFiles)): ?>
                        <div class="bg-white rounded-lg shadow p-8 text-center">
                            <svg class="w-16 h-16 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path>
                            </svg>
                            <p class="text-gray-600 text-lg">No archived files</p>
                            <p class="text-gray-500 text-sm mt-2">Archived files will appear here when you archive them from the file detail page.</p>
                        </div>
                    <?php else: ?>
                        <div class="bg-white rounded-lg shadow overflow-hidden">
                            <table class="min-w-full">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">File #</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Owner</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Archived Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Archived By</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reason</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($archivedFiles as $file): ?>
                                        <tr class="bg-yellow-50">
                                            <td class="px-6 py-4 whitespace-nowrap font-mono">#<?= htmlspecialchars($file['display_number']) ?></td>
                                            <td class="px-6 py-4">
                                                <?= htmlspecialchars($file['name']) ?>
                                                <span class="ml-2 px-2 py-1 text-xs bg-gray-400 text-white rounded">Archived</span>
                                            </td>
                                            <td class="px-6 py-4"><?= htmlspecialchars($file['owner_name'] ?? 'N/A') ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap"><?= date('M j, Y', strtotime($file['archived_at'])) ?></td>
                                            <td class="px-6 py-4"><?= htmlspecialchars($file['archived_by_name'] ?? 'Unknown') ?></td>
                                            <td class="px-6 py-4">
                                                <div class="max-w-xs truncate" title="<?= htmlspecialchars($file['archived_reason']) ?>">
                                                    <?= htmlspecialchars($file['archived_reason']) ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <a href="?page=files&action=view&id=<?= $file['id'] ?>" class="text-blue-600 hover:underline mr-3">View</a>
                                                <?php if ($_SESSION['user_role'] === 'admin'): ?>
                                                    <form method="POST" action="?page=files&action=restore&id=<?= $file['id'] ?>" class="inline" onsubmit="return confirm('Restore this file from archive?');">
                                                        <button type="submit" class="text-green-600 hover:underline">Restore</button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                <?php elseif ($page === 'destroyed'): ?>
                    <!-- Destroyed Files Page (Admin Only) -->
                    <?php if ($_SESSION['user_role'] !== 'admin'): ?>
                        <div class="bg-red-100 text-red-700 p-4 rounded">
                            Unauthorized: Only administrators can view destroyed files
                        </div>
                    <?php else: ?>
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-3xl font-bold text-red-700">Destroyed Files</h2>
                        </div>

                        <?php
                        $destroyedFiles = $db->fetchAll("
                            SELECT f.*, u.name as owner_name, du.name as destroyed_by_name
                            FROM files f
                            LEFT JOIN users u ON f.owner_id = u.id
                            LEFT JOIN users du ON f.destroyed_by = du.id
                            WHERE f.is_destroyed = 1
                            ORDER BY f.destroyed_at DESC
                        ");

                        // Calculate statistics by method
                        $methodStats = [];
                        foreach ($destroyedFiles as $file) {
                            $method = explode(' - ', $file['destruction_method'])[0]; // Get method without notes
                            if (!isset($methodStats[$method])) {
                                $methodStats[$method] = 0;
                            }
                            $methodStats[$method]++;
                        }
                        ?>

                        <?php if (!empty($destroyedFiles)): ?>
                            <!-- Statistics Cards -->
                            <div class="grid grid-cols-4 gap-4 mb-6">
                                <div class="bg-white p-6 rounded-lg shadow border-2 border-red-200">
                                    <div class="text-3xl font-bold text-red-700"><?= count($destroyedFiles) ?></div>
                                    <div class="text-gray-600">Total Destroyed Files</div>
                                </div>
                                <?php
                                $topMethods = array_slice($methodStats, 0, 3, true);
                                foreach ($topMethods as $method => $count):
                                ?>
                                    <div class="bg-white p-6 rounded-lg shadow">
                                        <div class="text-2xl font-bold text-gray-700"><?= $count ?></div>
                                        <div class="text-gray-600 text-sm"><?= htmlspecialchars($method) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (empty($destroyedFiles)): ?>
                            <div class="bg-white rounded-lg shadow p-8 text-center">
                                <svg class="w-16 h-16 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                                <p class="text-gray-600 text-lg">No destroyed files</p>
                                <p class="text-gray-500 text-sm mt-2">Files marked as destroyed will appear here.</p>
                            </div>
                        <?php else: ?>
                            <div class="bg-white rounded-lg shadow overflow-hidden">
                                <table class="min-w-full">
                                    <thead class="bg-red-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-red-700 uppercase">File #</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-red-700 uppercase">Name</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-red-700 uppercase">Destroyed Date</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-red-700 uppercase">Method</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-red-700 uppercase">Authorized By</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-red-700 uppercase">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($destroyedFiles as $file): ?>
                                            <tr class="bg-red-50 hover:bg-red-100">
                                                <td class="px-6 py-4 whitespace-nowrap font-mono font-bold">#<?= htmlspecialchars($file['display_number']) ?></td>
                                                <td class="px-6 py-4">
                                                    <?= htmlspecialchars($file['name']) ?>
                                                    <span class="ml-2 px-2 py-1 text-xs bg-red-600 text-white rounded font-bold">DESTROYED</span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap"><?= date('M j, Y g:i A', strtotime($file['destroyed_at'])) ?></td>
                                                <td class="px-6 py-4">
                                                    <div class="max-w-xs truncate" title="<?= htmlspecialchars($file['destruction_method']) ?>">
                                                        <?= htmlspecialchars($file['destruction_method']) ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4"><?= htmlspecialchars($file['destroyed_by_name'] ?? 'Unknown') ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <a href="?page=files&action=view&id=<?= $file['id'] ?>" class="text-blue-600 hover:underline mr-3">View</a>
                                                    <a href="?page=files&action=certificate&id=<?= $file['id'] ?>" target="_blank" class="text-red-600 hover:underline">Certificate</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Breakdown by Method -->
                            <div class="mt-6 bg-white rounded-lg shadow p-6">
                                <h3 class="font-bold text-lg mb-4">Destruction Methods Breakdown</h3>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                    <?php foreach ($methodStats as $method => $count): ?>
                                        <div class="p-4 bg-gray-50 rounded border border-gray-200">
                                            <div class="text-2xl font-bold text-gray-700"><?= $count ?></div>
                                            <div class="text-sm text-gray-600"><?= htmlspecialchars($method) ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                <?php elseif ($page === 'files' && $action === 'certificate' && $id): ?>
                    <!-- Certificate of Destruction -->
                    <?php
                    $file = $db->fetchOne("
                        SELECT f.*, u.name as owner_name, du.name as destroyed_by_name
                        FROM files f
                        LEFT JOIN users u ON f.owner_id = u.id
                        LEFT JOIN users du ON f.destroyed_by = du.id
                        WHERE f.id = ?
                    ", [$id]);

                    if (!$file || !$file['is_destroyed']):
                    ?>
                        <div class="bg-red-100 text-red-700 p-4 rounded">
                            File not found or has not been destroyed
                        </div>
                    <?php else: ?>
                        <style>
                            @media print {
                                body { margin: 0; padding: 20px; }
                                .no-print { display: none !important; }
                                .certificate-container {
                                    border: 3px solid #000;
                                    padding: 40px;
                                    max-width: 800px;
                                    margin: 0 auto;
                                }
                            }
                            @media screen {
                                .certificate-container {
                                    border: 3px solid #000;
                                    padding: 40px;
                                    max-width: 800px;
                                    margin: 0 auto;
                                    background: white;
                                }
                            }
                        </style>

                        <div class="no-print mb-4 text-center">
                            <button onclick="window.print()" class="bg-red-600 text-white px-8 py-3 rounded hover:bg-red-700 font-bold">
                                Print Certificate
                            </button>
                            <a href="?page=files&action=view&id=<?= $file['id'] ?>" class="ml-4 bg-gray-300 text-gray-700 px-8 py-3 rounded hover:bg-gray-400 inline-block">
                                Back to File
                            </a>
                        </div>

                        <div class="certificate-container">
                            <!-- Official Header -->
                            <div class="text-center mb-8 pb-6 border-b-4 border-black">
                                <h1 class="text-4xl font-black mb-2 uppercase">Certificate of Destruction</h1>
                                <p class="text-lg text-gray-600">Official Record of File Destruction</p>
                            </div>

                            <!-- Certificate Body -->
                            <div class="mb-8">
                                <p class="text-lg mb-6">
                                    This is to certify that the following file has been permanently destroyed in accordance
                                    with established organizational policies and procedures:
                                </p>

                                <!-- File Details Box -->
                                <div class="border-2 border-gray-400 p-6 mb-6 bg-gray-50">
                                    <h2 class="text-xl font-bold mb-4 border-b pb-2">File Information</h2>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <div class="text-sm text-gray-600">File Number</div>
                                            <div class="font-bold text-lg">#<?= htmlspecialchars($file['display_number']) ?></div>
                                        </div>
                                        <div>
                                            <div class="text-sm text-gray-600">File Name</div>
                                            <div class="font-bold text-lg"><?= htmlspecialchars($file['name']) ?></div>
                                        </div>
                                        <div>
                                            <div class="text-sm text-gray-600">UUID</div>
                                            <div class="font-mono text-sm"><?= htmlspecialchars($file['uuid']) ?></div>
                                        </div>
                                        <div>
                                            <div class="text-sm text-gray-600">Original Owner</div>
                                            <div class="font-medium"><?= htmlspecialchars($file['owner_name'] ?? 'N/A') ?></div>
                                        </div>
                                    </div>
                                    <?php if ($file['description']): ?>
                                        <div class="mt-4 pt-4 border-t">
                                            <div class="text-sm text-gray-600 mb-1">Description</div>
                                            <div class="text-sm"><?= htmlspecialchars($file['description']) ?></div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Destruction Details Box -->
                                <div class="border-2 border-red-600 p-6 mb-6 bg-red-50">
                                    <h2 class="text-xl font-bold mb-4 border-b border-red-600 pb-2 text-red-700">Destruction Details</h2>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <div class="text-sm text-gray-600">Destruction Date</div>
                                            <div class="font-bold"><?= date('F j, Y', strtotime($file['destroyed_at'])) ?></div>
                                            <div class="text-sm text-gray-500"><?= date('g:i A', strtotime($file['destroyed_at'])) ?></div>
                                        </div>
                                        <div>
                                            <div class="text-sm text-gray-600">Destruction Method</div>
                                            <div class="font-bold"><?= htmlspecialchars($file['destruction_method']) ?></div>
                                        </div>
                                        <div class="col-span-2">
                                            <div class="text-sm text-gray-600">Authorized By</div>
                                            <div class="font-bold text-lg"><?= htmlspecialchars($file['destroyed_by_name'] ?? 'Unknown') ?></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Compliance Statement -->
                                <div class="border-2 border-gray-300 p-6 mb-6 bg-blue-50">
                                    <h2 class="text-lg font-bold mb-3">Compliance Statement</h2>
                                    <p class="text-sm leading-relaxed">
                                        The destruction of this file was conducted in full compliance with applicable laws, regulations,
                                        and organizational policies governing the retention and disposal of records. The destruction
                                        process was executed using the method specified above, ensuring complete and irreversible
                                        elimination of the physical file and all associated materials.
                                    </p>
                                    <p class="text-sm leading-relaxed mt-2">
                                        This certificate serves as the official and permanent record of destruction for the file
                                        identified above. The information contained herein is accurate and complete to the best
                                        of our knowledge.
                                    </p>
                                </div>

                                <!-- Signature Section -->
                                <div class="mt-12 pt-6">
                                    <div class="grid grid-cols-2 gap-8">
                                        <div>
                                            <div class="border-b-2 border-black pb-1 mb-2" style="min-height: 50px;"></div>
                                            <div class="text-sm">
                                                <div class="font-bold"><?= htmlspecialchars($file['destroyed_by_name'] ?? 'Unknown') ?></div>
                                                <div class="text-gray-600">Authorized Signature</div>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="border-b-2 border-black pb-1 mb-2" style="min-height: 50px;"></div>
                                            <div class="text-sm">
                                                <div class="font-bold"><?= date('F j, Y', strtotime($file['destroyed_at'])) ?></div>
                                                <div class="text-gray-600">Date</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Footer -->
                            <div class="text-center text-xs text-gray-500 mt-12 pt-6 border-t">
                                <p>Certificate Generated: <?= date('F j, Y g:i A') ?></p>
                                <p class="mt-1">File UUID: <?= htmlspecialchars($file['uuid']) ?></p>
                            </div>
                        </div>
                    <?php endif; ?>

                <?php elseif ($page === 'locations' && $action === 'edit' && $id): ?>
                    <!-- Edit Location Form -->
                    <?php
                    $location = $db->fetchOne("SELECT * FROM locations WHERE id = ?", [$id]);
                    if (!$location):
                    ?>
                        <div class="bg-red-100 text-red-700 p-4 rounded">Location not found</div>
                    <?php else: ?>
                        <h2 class="text-3xl font-bold mb-6">Edit Location: <?= htmlspecialchars($location['name']) ?></h2>
                        <div class="bg-white rounded-lg shadow p-6 max-w-2xl">
                            <form method="POST">
                                <div class="mb-4">
                                    <label class="block text-gray-700 mb-2">Location Name *</label>
                                    <input type="text" name="name" required value="<?= htmlspecialchars($location['name']) ?>"
                                           placeholder="Location Name"
                                           class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                </div>
                                <div class="mb-4">
                                    <label class="block text-gray-700 mb-2">Building</label>
                                    <input type="text" name="building" value="<?= htmlspecialchars($location['building']) ?>"
                                           placeholder="Building"
                                           class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                </div>
                                <div class="mb-4">
                                    <label class="block text-gray-700 mb-2">Floor</label>
                                    <input type="text" name="floor" value="<?= htmlspecialchars($location['floor'] ?? '') ?>"
                                           placeholder="Floor"
                                           class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                </div>
                                <div class="mb-4">
                                    <label class="block text-gray-700 mb-2">Room</label>
                                    <input type="text" name="room" value="<?= htmlspecialchars($location['room']) ?>"
                                           placeholder="Room"
                                           class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                </div>
                                <div class="mb-4">
                                    <label class="block text-gray-700 mb-2">Notes</label>
                                    <textarea name="notes" rows="3"
                                              placeholder="Additional notes"
                                              class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500"><?= htmlspecialchars($location['notes']) ?></textarea>
                                </div>
                                <div class="flex gap-2">
                                    <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">Update Location</button>
                                    <a href="?page=locations" class="bg-gray-300 text-gray-700 px-6 py-2 rounded hover:bg-gray-400">Cancel</a>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>
                <?php elseif ($page === 'locations'): ?>
                    <!-- Locations Management -->
                    <h2 class="text-3xl font-bold mb-6">Location Management</h2>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                        <!-- Locations List -->
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-xl font-bold">Locations</h3>
                                <button onclick="document.getElementById('locationForm').classList.toggle('hidden')" class="bg-blue-600 text-white px-3 py-1 rounded text-sm">+ Add</button>
                            </div>

                            <div id="locationForm" class="hidden mb-4 p-4 bg-gray-50 rounded">
                                <form method="POST" action="?page=locations&action=create">
                                    <input type="text" name="name" placeholder="Location Name" required class="w-full px-3 py-2 border rounded mb-2">
                                    <input type="text" name="building" placeholder="Building" class="w-full px-3 py-2 border rounded mb-2">
                                    <input type="text" name="floor" placeholder="Floor" class="w-full px-3 py-2 border rounded mb-2">
                                    <input type="text" name="room" placeholder="Room" class="w-full px-3 py-2 border rounded mb-2">
                                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded text-sm w-full">Create</button>
                                </form>
                            </div>

                            <div class="space-y-2 max-h-96 overflow-y-auto">
                                <?php
                                $locations = $db->fetchAll("SELECT * FROM locations ORDER BY name");
                                foreach ($locations as $loc):
                                ?>
                                    <div class="p-3 bg-gray-50 rounded">
                                        <div class="flex items-center justify-between mb-1">
                                            <div class="font-medium"><?= htmlspecialchars($loc['name']) ?></div>
                                            <a href="?page=locations&action=edit&id=<?= $loc['id'] ?>" class="text-green-600 hover:underline text-xs">Edit</a>
                                        </div>
                                        <div class="text-sm text-gray-600">
                                            <?php
                                            $parts = array_filter([$loc['building'], $loc['floor'] ?? null, $loc['room']]);
                                            echo htmlspecialchars(implode(' - ', $parts));
                                            ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Cabinets List -->
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-xl font-bold">Cabinets</h3>
                                <button onclick="document.getElementById('cabinetForm').classList.toggle('hidden')" class="bg-green-600 text-white px-3 py-1 rounded text-sm">+ Add</button>
                            </div>

                            <div id="cabinetForm" class="hidden mb-4 p-4 bg-gray-50 rounded">
                                <form method="POST" action="?page=cabinets&action=create">
                                    <input type="text" name="label" placeholder="Cabinet Label" required class="w-full px-3 py-2 border rounded mb-2">
                                    <select name="location_id" class="w-full px-3 py-2 border rounded mb-2">
                                        <option value="">Select Location</option>
                                        <?php foreach ($locations as $loc): ?>
                                            <option value="<?= $loc['id'] ?>"><?= htmlspecialchars($loc['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <select name="entity_id" class="w-full px-3 py-2 border rounded mb-2">
                                        <option value="">Select Entity (optional)</option>
                                        <?php foreach ($allEntities as $entity): ?>
                                            <option value="<?= $entity['id'] ?>"><?= htmlspecialchars($entity['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded text-sm w-full">Create</button>
                                </form>
                            </div>

                            <div class="space-y-2 max-h-96 overflow-y-auto">
                                <?php
                                $cabinets = $db->fetchAll("
                                    SELECT c.*, l.name as location_name
                                    FROM cabinets c
                                    LEFT JOIN locations l ON c.location_id = l.id
                                    ORDER BY l.name, c.label
                                ");
                                foreach ($cabinets as $cab):
                                ?>
                                    <div class="p-3 bg-gray-50 rounded">
                                        <div class="flex items-center justify-between mb-1">
                                            <div class="font-medium"><?= htmlspecialchars($cab['label']) ?></div>
                                            <a href="?page=cabinets&action=edit&id=<?= $cab['id'] ?>" class="text-green-600 hover:underline text-xs">Edit</a>
                                        </div>
                                        <div class="text-sm text-gray-600"><?= htmlspecialchars($cab['location_name'] ?? 'No location') ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Drawers Table (Full Width) -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-xl font-bold">Drawers</h3>
                            <button onclick="document.getElementById('drawerForm').classList.toggle('hidden')" class="bg-purple-600 text-white px-4 py-2 rounded text-sm">+ Add Drawer</button>
                        </div>

                        <div id="drawerForm" class="hidden mb-4 p-4 bg-gray-50 rounded max-w-md">
                            <form method="POST" action="?page=drawers&action=create">
                                <div class="mb-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Cabinet *</label>
                                    <select name="cabinet_id" required class="w-full px-3 py-2 border rounded">
                                        <option value="">Select Cabinet</option>
                                        <?php foreach ($cabinets as $cab): ?>
                                            <option value="<?= $cab['id'] ?>"><?= htmlspecialchars($cab['label']) ?><?= $cab['location_name'] ? ' (' . htmlspecialchars($cab['location_name']) . ')' : '' ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Drawer Label *</label>
                                    <input type="text" name="label" placeholder="A, B, Top, Bottom..." required class="w-full px-3 py-2 border rounded">
                                </div>
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Position</label>
                                    <input type="number" name="position" placeholder="1" value="1" class="w-full px-3 py-2 border rounded">
                                </div>
                                <button type="submit" class="bg-purple-600 text-white px-4 py-2 rounded text-sm w-full">Create Drawer</button>
                            </form>
                        </div>

                        <?php
                        $drawers = $db->fetchAll("
                            SELECT d.*, c.label as cabinet_label, l.name as location_name
                            FROM drawers d
                            JOIN cabinets c ON d.cabinet_id = c.id
                            LEFT JOIN locations l ON c.location_id = l.id
                            ORDER BY l.name, c.label, d.position
                        ");
                        ?>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cabinet</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Drawer</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Position</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (empty($drawers)): ?>
                                        <tr>
                                            <td colspan="5" class="px-6 py-4 text-center text-gray-500 text-sm">No drawers found. Click "+ Add Drawer" to create one.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($drawers as $drawer): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($drawer['location_name'] ?? '-') ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($drawer['cabinet_label']) ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($drawer['label']) ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $drawer['position'] ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                    <a href="?page=drawers&action=edit&id=<?= $drawer['id'] ?>" class="text-blue-600 hover:text-blue-900 mr-3">Edit</a>
                                                    <a href="?page=locations&action=drawer_qr&id=<?= $drawer['id'] ?>" target="_blank" class="text-purple-600 hover:text-purple-900">QR Code</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if (count($drawers) > 0): ?>
                            <div class="mt-4 text-sm text-gray-600">
                                Total: <?= count($drawers) ?> drawer<?= count($drawers) !== 1 ? 's' : '' ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- QR Code Generator for Locations/Drawers -->
                    <?php if ($action === 'drawer_qr' && $id): ?>
                        <?php
                        $drawer = $db->fetchOne("
                            SELECT d.*, c.label as cabinet_label, l.name as location_name
                            FROM drawers d
                            JOIN cabinets c ON d.cabinet_id = c.id
                            LEFT JOIN locations l ON c.location_id = l.id
                            WHERE d.id = ?
                        ", [$id]);

                        if ($drawer):
                            $drawerURL = getBaseURL() . '?page=files&drawer_id=' . $drawer['id'];
                            $qrCodeURL = generateQRCodeURL($drawerURL, 300);
                        ?>
                        <div class="mt-6 bg-white rounded-lg shadow p-6 max-w-md mx-auto text-center">
                            <h3 class="font-bold text-xl mb-4">Drawer QR Code</h3>
                            <p class="text-gray-600 mb-4">
                                <strong>Location:</strong> <?= htmlspecialchars($drawer['location_name'] ?? 'N/A') ?><br>
                                <strong>Cabinet:</strong> <?= htmlspecialchars($drawer['cabinet_label']) ?><br>
                                <strong>Drawer:</strong> <?= htmlspecialchars($drawer['label']) ?>
                            </p>
                            <div class="mb-4 p-4 bg-gray-50 rounded">
                                <img src="<?= $qrCodeURL ?>" alt="QR Code" class="mx-auto" style="width: 300px; height: 300px;">
                            </div>
                            <p class="text-sm text-gray-600 mb-4">Scan to view all files in this drawer</p>
                            <button onclick="window.print()" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 mr-2">
                                Print QR Code
                            </button>
                            <button onclick="window.close()" class="bg-gray-600 text-white px-6 py-2 rounded hover:bg-gray-700">
                                Close
                            </button>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>


                <?php elseif ($page === 'cabinets' && $action === 'edit' && $id): ?>
                    <!-- Edit Cabinet Form -->
                    <?php
                    $cabinet = $db->fetchOne("SELECT * FROM cabinets WHERE id = ?", [$id]);
                    if (!$cabinet):
                    ?>
                        <div class="bg-red-100 text-red-700 p-4 rounded">Cabinet not found</div>
                    <?php else:
                        // Get all locations and entities for dropdowns
                        $allLocations = $db->fetchAll("SELECT * FROM locations ORDER BY name");
                        $allEntities = $db->fetchAll("SELECT * FROM entities ORDER BY name");
                    ?>
                        <h2 class="text-3xl font-bold mb-6">Edit Cabinet: <?= htmlspecialchars($cabinet['label']) ?></h2>
                        <div class="bg-white rounded-lg shadow p-6 max-w-2xl">
                            <form method="POST">
                                <div class="mb-4">
                                    <label class="block text-gray-700 mb-2">Cabinet Label *</label>
                                    <input type="text" name="label" required value="<?= htmlspecialchars($cabinet['label']) ?>"
                                           placeholder="Cabinet Label"
                                           class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                </div>
                                <div class="mb-4">
                                    <label class="block text-gray-700 mb-2">Location</label>
                                    <select name="location_id" class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="">No location</option>
                                        <?php foreach ($allLocations as $loc): ?>
                                            <option value="<?= $loc['id'] ?>" <?= $cabinet['location_id'] == $loc['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($loc['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-4">
                                    <label class="block text-gray-700 mb-2">Entity</label>
                                    <select name="entity_id" class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="">No entity</option>
                                        <?php foreach ($allEntities as $entity): ?>
                                            <option value="<?= $entity['id'] ?>" <?= $cabinet['entity_id'] == $entity['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($entity['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-4">
                                    <label class="block text-gray-700 mb-2">Notes</label>
                                    <textarea name="notes" rows="3"
                                              placeholder="Additional notes"
                                              class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500"><?= htmlspecialchars($cabinet['notes']) ?></textarea>
                                </div>
                                <div class="flex gap-2">
                                    <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">Update Cabinet</button>
                                    <a href="?page=locations" class="bg-gray-300 text-gray-700 px-6 py-2 rounded hover:bg-gray-400">Cancel</a>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>

                <?php elseif ($page === 'drawers' && $action === 'edit' && $id): ?>
                    <!-- Edit Drawer Form -->
                    <?php
                    $drawer = $db->fetchOne("SELECT * FROM drawers WHERE id = ?", [$id]);
                    if (!$drawer):
                    ?>
                        <div class="bg-red-100 text-red-700 p-4 rounded">Drawer not found</div>
                    <?php else:
                        // Get all cabinets for dropdown
                        $allCabinets = $db->fetchAll("
                            SELECT c.id, c.label, l.name as location_name
                            FROM cabinets c
                            LEFT JOIN locations l ON c.location_id = l.id
                            ORDER BY l.name, c.label
                        ");
                    ?>
                        <h2 class="text-3xl font-bold mb-6">Edit Drawer: <?= htmlspecialchars($drawer['label']) ?></h2>
                        <div class="bg-white rounded-lg shadow p-6 max-w-2xl">
                            <form method="POST">
                                <div class="mb-4">
                                    <label class="block text-gray-700 mb-2">Cabinet *</label>
                                    <select name="cabinet_id" required class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="">Select Cabinet</option>
                                        <?php foreach ($allCabinets as $cab): ?>
                                            <option value="<?= $cab['id'] ?>" <?= $drawer['cabinet_id'] == $cab['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars(($cab['location_name'] ?? 'No Location') . ' > ' . $cab['label']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="text-sm text-gray-500 mt-1">You can move this drawer to a different cabinet</p>
                                </div>
                                <div class="mb-4">
                                    <label class="block text-gray-700 mb-2">Drawer Label *</label>
                                    <input type="text" name="label" required value="<?= htmlspecialchars($drawer['label']) ?>"
                                           placeholder="Drawer Label (A, B, Top...)"
                                           class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                </div>
                                <div class="mb-4">
                                    <label class="block text-gray-700 mb-2">Position</label>
                                    <input type="number" name="position" value="<?= htmlspecialchars($drawer['position']) ?>"
                                           placeholder="Position"
                                           class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <p class="text-sm text-gray-500 mt-1">Used for sorting (e.g., 1 for top drawer, 2 for second, etc.)</p>
                                </div>
                                <div class="flex gap-2">
                                    <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">Update Drawer</button>
                                    <a href="?page=locations" class="bg-gray-300 text-gray-700 px-6 py-2 rounded hover:bg-gray-400">Cancel</a>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>

                <?php elseif ($page === 'labels'): ?>
                    <!-- Bulk Label Printing -->
                    <h2 class="text-3xl font-bold mb-6">Print Labels</h2>

                    <div class="bg-white rounded-lg shadow p-6 mb-6">
                        <h3 class="font-bold text-lg mb-4">Select Files for Label Printing</h3>
                        <p class="text-gray-600 mb-4">Choose files to print labels. Labels are formatted for Avery 5160 (30 labels per sheet).</p>

                        <form method="GET" action="?page=labels&action=print" id="labelForm">
                            <input type="hidden" name="page" value="labels">
                            <input type="hidden" name="action" value="print">

                            <?php
                            $files = $db->fetchAll("SELECT id, display_number, name, sensitivity FROM files WHERE is_archived = 0 AND is_destroyed = 0 ORDER BY display_number");
                            ?>

                            <div class="mb-4">
                                <button type="button" onclick="selectAll()" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 mr-2">
                                    Select All
                                </button>
                                <button type="button" onclick="deselectAll()" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
                                    Deselect All
                                </button>
                            </div>

                            <div class="max-h-96 overflow-y-auto border rounded p-4">
                                <div class="grid grid-cols-2 gap-3">
                                    <?php foreach ($files as $file): ?>
                                        <label class="flex items-center gap-2 p-2 hover:bg-gray-50 rounded cursor-pointer">
                                            <input type="checkbox" name="file_ids[]" value="<?= $file['id'] ?>" class="file-checkbox">
                                            <span class="font-medium">#<?= htmlspecialchars($file['display_number']) ?></span>
                                            <span class="text-sm text-gray-600 truncate"><?= htmlspecialchars($file['name']) ?></span>
                                            <span class="text-xs px-2 py-1 rounded bg-gray-100">
                                                <?= ucfirst($file['sensitivity']) ?>
                                            </span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="mt-4 flex gap-2">
                                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">
                                    Generate Label Sheet
                                </button>
                            </div>
                        </form>

                        <script>
                            function selectAll() {
                                document.querySelectorAll('.file-checkbox').forEach(cb => cb.checked = true);
                            }
                            function deselectAll() {
                                document.querySelectorAll('.file-checkbox').forEach(cb => cb.checked = false);
                            }
                        </script>
                    </div>

                <?php elseif ($page === 'lookup'): ?>
                    <!-- QR Code Lookup Page -->
                    <div class="max-w-2xl mx-auto">
                        <h2 class="text-3xl font-bold mb-6 text-center">File Lookup</h2>

                        <?php
                        $uuid = $_GET['uuid'] ?? '';
                        $fileNumber = $_GET['number'] ?? '';

                        if ($uuid || $fileNumber):
                            // Try to find the file
                            if ($uuid) {
                                $file = $db->fetchOne("SELECT * FROM files WHERE uuid = ?", [$uuid]);
                            } else {
                                $file = $db->fetchOne("SELECT * FROM files WHERE display_number = ?", [$fileNumber]);
                            }

                            if ($file):
                                // Redirect to file view
                                header("Location: ?page=files&action=view&id=" . $file['id']);
                                exit;
                            else:
                        ?>
                            <div class="bg-red-100 border-2 border-red-500 text-red-700 p-6 rounded-lg mb-6">
                                <h3 class="font-bold text-xl mb-2">File Not Found</h3>
                                <p>The file you're looking for could not be found.</p>
                                <?php if ($uuid): ?>
                                    <p class="text-sm mt-2 font-mono">UUID: <?= htmlspecialchars($uuid) ?></p>
                                <?php endif; ?>
                            </div>
                        <?php
                            endif;
                        endif;
                        ?>

                        <div class="bg-white rounded-lg shadow p-8">
                            <h3 class="font-bold text-xl mb-4 text-center">Look Up a File</h3>
                            <p class="text-gray-600 mb-6 text-center">Enter a file UUID or file number to find it</p>

                            <form method="GET" class="space-y-4">
                                <input type="hidden" name="page" value="lookup">

                                <div>
                                    <label class="block text-gray-700 mb-2 font-medium">File UUID</label>
                                    <input type="text" name="uuid"
                                           placeholder="e.g., 550e8400-e29b-41d4-a716-446655440000"
                                           class="w-full px-4 py-3 border-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono text-sm"
                                           value="<?= htmlspecialchars($uuid) ?>">
                                </div>

                                <div class="text-center text-gray-500 font-medium">- OR -</div>

                                <div>
                                    <label class="block text-gray-700 mb-2 font-medium">File Number</label>
                                    <input type="text" name="number"
                                           placeholder="e.g., 2024-001"
                                           class="w-full px-4 py-3 border-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                           value="<?= htmlspecialchars($fileNumber) ?>">
                                </div>

                                <button type="submit" class="w-full bg-blue-600 text-white py-3 rounded-lg hover:bg-blue-700 font-medium text-lg">
                                    🔍 Search
                                </button>
                            </form>

                            <div class="mt-8 pt-6 border-t">
                                <h4 class="font-bold mb-3 text-center">Quick Access</h4>
                                <div class="flex gap-3 justify-center">
                                    <a href="?page=files" class="text-blue-600 hover:underline">Browse All Files</a>
                                    <span class="text-gray-400">|</span>
                                    <a href="?page=search" class="text-blue-600 hover:underline">Advanced Search</a>
                                </div>
                            </div>
                        </div>

                        <!-- Mobile-Friendly Scanner Info -->
                        <div class="mt-6 bg-blue-50 border-2 border-blue-200 rounded-lg p-6">
                            <h4 class="font-bold mb-3">📱 Scanning QR Codes</h4>
                            <div class="text-sm text-gray-700 space-y-2">
                                <p><strong>On Mobile:</strong> Use your phone's camera app to scan QR codes on file labels. Most modern smartphones automatically detect QR codes.</p>
                                <p><strong>On Desktop:</strong> Use a QR code scanner app or manually enter the UUID from the label.</p>
                                <p class="text-xs text-gray-600 mt-3">Tip: Bookmark this page on your mobile device for quick file lookups!</p>
                            </div>
                        </div>
                    </div>

                <?php elseif ($page === 'reports'): ?>
                    <?php
                    $report = $_GET['report'] ?? 'dashboard';
                    ?>
                    <style>
                        @media print { .no-print { display: none !important; } .bg-white { background: white !important; } body { background: white; } }
                        .chart-bar { background: linear-gradient(to right, #3B82F6 0%, #3B82F6 var(--percentage), #E5E7EB var(--percentage)); height: 24px; border-radius: 4px; }
                        .stat-card { background: white; border-radius: 8px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
                        .report-table { width: 100%; border-collapse: collapse; }
                        .report-table th { background: #F3F4F6; padding: 12px; text-align: left; font-weight: 600; border-bottom: 2px solid #E5E7EB; }
                        .report-table td { padding: 10px 12px; border-bottom: 1px solid #E5E7EB; }
                        .report-table tbody tr:hover { background: #F9FAFB; }
                    </style>

                    <?php if ($report === 'dashboard'): ?>
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-3xl font-bold">Reports Dashboard</h2>
                        </div>
                        <div class="grid grid-cols-3 gap-6">
                            <div class="bg-white rounded-lg shadow p-6">
                                <h3 class="text-xl font-bold mb-4 text-blue-600">Inventory Reports</h3>
                                <div class="space-y-2">
                                    <a href="?page=reports&report=by_location" class="block p-3 bg-gray-50 hover:bg-blue-50 rounded">
                                        <div class="font-medium">Files by Location</div>
                                        <div class="text-sm text-gray-600">View files organized by location hierarchy</div>
                                    </a>
                                    <a href="?page=reports&report=by_entity" class="block p-3 bg-gray-50 hover:bg-blue-50 rounded">
                                        <div class="font-medium">Files by Entity</div>
                                        <div class="text-sm text-gray-600">Files grouped by organization/entity</div>
                                    </a>
                                    <a href="?page=reports&report=by_tag" class="block p-3 bg-gray-50 hover:bg-blue-50 rounded">
                                        <div class="font-medium">Files by Tag</div>
                                        <div class="text-sm text-gray-600">Tag usage and distribution analysis</div>
                                    </a>
                                </div>
                            </div>
                            <div class="bg-white rounded-lg shadow p-6">
                                <h3 class="text-xl font-bold mb-4 text-green-600">Activity Reports</h3>
                                <div class="space-y-2">
                                    <a href="?page=reports&report=checkouts" class="block p-3 bg-gray-50 hover:bg-green-50 rounded">
                                        <div class="font-medium">Checkout Status <?php if ($_SESSION['user_role'] !== 'admin') echo '<span class="text-xs text-gray-500">(Admin)</span>'; ?></div>
                                        <div class="text-sm text-gray-600">Current and historical checkouts</div>
                                    </a>
                                    <a href="?page=reports&report=overdue" class="block p-3 bg-gray-50 hover:bg-green-50 rounded">
                                        <div class="font-medium">Overdue Files</div>
                                        <div class="text-sm text-gray-600">Files past their return date</div>
                                    </a>
                                    <a href="?page=reports&report=user_activity" class="block p-3 bg-gray-50 hover:bg-green-50 rounded">
                                        <div class="font-medium">User Activity <?php if ($_SESSION['user_role'] !== 'admin') echo '<span class="text-xs text-gray-500">(Admin)</span>'; ?></div>
                                        <div class="text-sm text-gray-600">Per-user statistics and actions</div>
                                    </a>
                                </div>
                            </div>
                            <div class="bg-white rounded-lg shadow p-6">
                                <h3 class="text-xl font-bold mb-4 text-purple-600">Compliance & Analytics</h3>
                                <div class="space-y-2">
                                    <a href="?page=reports&report=compliance" class="block p-3 bg-gray-50 hover:bg-purple-50 rounded">
                                        <div class="font-medium">Archive & Destruction <?php if ($_SESSION['user_role'] !== 'admin') echo '<span class="text-xs text-gray-500">(Admin)</span>'; ?></div>
                                        <div class="text-sm text-gray-600">Compliance documentation</div>
                                    </a>
                                    <a href="?page=reports&report=stats" class="block p-3 bg-gray-50 hover:bg-purple-50 rounded">
                                        <div class="font-medium">System Statistics</div>
                                        <div class="text-sm text-gray-600">Overall system metrics and trends</div>
                                    </a>
                                    <a href="?page=reports&report=custom" class="block p-3 bg-gray-50 hover:bg-purple-50 rounded">
                                        <div class="font-medium">Custom Report Builder</div>
                                        <div class="text-sm text-gray-600">Build your own custom reports</div>
                                    </a>
                                </div>
                            </div>
                        </div>

                    <?php elseif ($report === 'by_location'): ?>
                        <?php
                        $files = $db->fetchAll("SELECT f.*, d.label as drawer_label, c.label as cabinet_label, l.name as location_name, e.name as entity_name, u.name as owner_name FROM files f LEFT JOIN drawers d ON f.current_drawer_id = d.id LEFT JOIN cabinets c ON d.cabinet_id = c.id LEFT JOIN locations l ON c.location_id = l.id LEFT JOIN entities e ON f.entity_id = e.id LEFT JOIN users u ON f.owner_id = u.id WHERE f.is_archived = 0 AND f.is_destroyed = 0 ORDER BY l.name, c.label, d.label");
                        $grouped = [];
                        foreach($files as $f) {
                            $loc = $f['location_name'] ?: 'No Location';
                            $cab = $f['cabinet_label'] ?: 'No Cabinet';
                            $drawer = $f['drawer_label'] ?: 'No Drawer';
                            $grouped[$loc][$cab][$drawer][] = $f;
                        }
                        ?>
                        <div class="flex justify-between items-center mb-6">
                            <div><a href="?page=reports" class="text-blue-600 hover:underline">← Back to Reports</a><h2 class="text-3xl font-bold mt-2">Files by Location</h2></div>
                            <div class="flex gap-2 no-print"><a href="?page=reports&report=by_location&format=csv" class="bg-green-600 text-white px-4 py-2 rounded">Export CSV</a><button onclick="window.print()" class="bg-blue-600 text-white px-4 py-2 rounded">Print</button></div>
                        </div>
                        <div class="grid grid-cols-3 gap-4 mb-6">
                            <div class="stat-card"><div class="text-2xl font-bold text-blue-600"><?= count($files) ?></div><div class="text-gray-600">Total Files</div></div>
                            <div class="stat-card"><div class="text-2xl font-bold text-green-600"><?= count($grouped) ?></div><div class="text-gray-600">Locations</div></div>
                            <div class="stat-card"><div class="text-2xl font-bold text-purple-600"><?= array_sum(array_map('count', array_merge(...array_values($grouped)))) ?></div><div class="text-gray-600">Cabinets</div></div>
                        </div>
                        <div class="bg-white rounded-lg shadow p-6">
                            <?php foreach($grouped as $loc => $cabs): ?>
                                <h3 class="text-xl font-bold mb-3 text-blue-600"><?= htmlspecialchars($loc) ?></h3>
                                <?php foreach($cabs as $cab => $drawers): ?>
                                    <div class="ml-4 mb-4">
                                        <h4 class="text-lg font-semibold mb-2">Cabinet: <?= htmlspecialchars($cab) ?></h4>
                                        <?php foreach($drawers as $drawer => $drawerFiles): ?>
                                            <div class="ml-4 mb-3">
                                                <h5 class="font-medium mb-2">Drawer: <?= htmlspecialchars($drawer) ?> (<?= count($drawerFiles) ?> files)</h5>
                                                <table class="report-table ml-4">
                                                    <thead><tr><th>File #</th><th>Name</th><th>Owner</th><th>Entity</th><th>Sensitivity</th></tr></thead>
                                                    <tbody>
                                                        <?php foreach($drawerFiles as $f): ?>
                                                            <tr>
                                                                <td><a href="?page=files&action=view&id=<?= $f['id'] ?>" class="text-blue-600 hover:underline"><?= htmlspecialchars($f['display_number']) ?></a></td>
                                                                <td><?= htmlspecialchars($f['name']) ?></td>
                                                                <td><?= htmlspecialchars($f['owner_name']) ?></td>
                                                                <td><?= htmlspecialchars($f['entity_name'] ?: 'N/A') ?></td>
                                                                <td><span class="px-2 py-1 text-xs rounded <?= $f['sensitivity'] === 'public' ? 'bg-green-100 text-green-800' : ($f['sensitivity'] === 'confidential' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') ?>"><?= ucfirst($f['sensitivity']) ?></span></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </div>

                    <?php elseif ($report === 'by_entity'): ?>
                        <?php
                        $files = $db->fetchAll("SELECT f.*, e.name as entity_name, u.name as owner_name FROM files f LEFT JOIN entities e ON f.entity_id = e.id LEFT JOIN users u ON f.owner_id = u.id WHERE f.is_destroyed = 0 ORDER BY e.name, f.display_number");
                        $grouped = [];
                        foreach($files as $f) {
                            $ent = $f['entity_name'] ?: 'No Entity';
                            $grouped[$ent][] = $f;
                        }
                        ?>
                        <div class="flex justify-between items-center mb-6">
                            <div><a href="?page=reports" class="text-blue-600 hover:underline">← Back to Reports</a><h2 class="text-3xl font-bold mt-2">Files by Entity</h2></div>
                            <div class="flex gap-2 no-print"><a href="?page=reports&report=by_entity&format=csv" class="bg-green-600 text-white px-4 py-2 rounded">Export CSV</a><button onclick="window.print()" class="bg-blue-600 text-white px-4 py-2 rounded">Print</button></div>
                        </div>
                        <div class="grid grid-cols-3 gap-4 mb-6">
                            <div class="stat-card"><div class="text-2xl font-bold text-blue-600"><?= count($files) ?></div><div class="text-gray-600">Total Files</div></div>
                            <div class="stat-card"><div class="text-2xl font-bold text-purple-600"><?= count($grouped) ?></div><div class="text-gray-600">Entities</div></div>
                            <div class="stat-card"><div class="text-2xl font-bold text-green-600"><?= count(array_filter($files, fn($f) => !$f['is_archived'] && !$f['is_checked_out'])) ?></div><div class="text-gray-600">Active Files</div></div>
                        </div>
                        <div class="bg-white rounded-lg shadow p-6">
                            <?php foreach($grouped as $ent => $entFiles): ?>
                                <div class="mb-6 border-b pb-4">
                                    <h4 class="text-lg font-semibold text-blue-600 mb-3"><?= htmlspecialchars($ent) ?> (<?= count($entFiles) ?> files)</h4>
                                    <table class="report-table">
                                        <thead><tr><th>File #</th><th>Name</th><th>Owner</th><th>Status</th><th>Sensitivity</th></tr></thead>
                                        <tbody>
                                            <?php foreach($entFiles as $f): ?>
                                                <?php $status = $f['is_archived'] ? 'Archived' : ($f['is_checked_out'] ? 'Checked Out' : 'Active'); ?>
                                                <tr>
                                                    <td><a href="?page=files&action=view&id=<?= $f['id'] ?>" class="text-blue-600 hover:underline"><?= htmlspecialchars($f['display_number']) ?></a></td>
                                                    <td><?= htmlspecialchars($f['name']) ?></td>
                                                    <td><?= htmlspecialchars($f['owner_name']) ?></td>
                                                    <td><span class="px-2 py-1 text-xs rounded <?= $f['is_archived'] ? 'bg-yellow-100 text-yellow-800' : ($f['is_checked_out'] ? 'bg-orange-100 text-orange-800' : 'bg-green-100 text-green-800') ?>"><?= $status ?></span></td>
                                                    <td><span class="px-2 py-1 text-xs rounded <?= $f['sensitivity'] === 'public' ? 'bg-green-100 text-green-800' : ($f['sensitivity'] === 'confidential' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') ?>"><?= ucfirst($f['sensitivity']) ?></span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endforeach; ?>
                        </div>

                    <?php elseif ($report === 'by_tag'): ?>
                        <?php
                        $tags = $db->fetchAll("SELECT t.*, COUNT(ft.file_id) as file_count FROM tags t LEFT JOIN file_tags ft ON t.id = ft.tag_id GROUP BY t.id ORDER BY file_count DESC, t.name");
                        ?>
                        <div class="flex justify-between items-center mb-6">
                            <div><a href="?page=reports" class="text-blue-600 hover:underline">← Back to Reports</a><h2 class="text-3xl font-bold mt-2">Files by Tag</h2></div>
                            <div class="flex gap-2 no-print"><a href="?page=reports&report=by_tag&format=csv" class="bg-green-600 text-white px-4 py-2 rounded">Export CSV</a><button onclick="window.print()" class="bg-blue-600 text-white px-4 py-2 rounded">Print</button></div>
                        </div>
                        <div class="grid grid-cols-3 gap-4 mb-6">
                            <div class="stat-card"><div class="text-2xl font-bold text-blue-600"><?= count($tags) ?></div><div class="text-gray-600">Total Tags</div></div>
                            <div class="stat-card"><div class="text-2xl font-bold text-green-600"><?= array_sum(array_column($tags, 'file_count')) ?></div><div class="text-gray-600">Total Assignments</div></div>
                            <div class="stat-card"><div class="text-2xl font-bold text-purple-600"><?= !empty($tags) ? number_format(array_sum(array_column($tags, 'file_count')) / count($tags), 1) : 0 ?></div><div class="text-gray-600">Avg per Tag</div></div>
                        </div>
                        <div class="bg-white rounded-lg shadow p-6 mb-6">
                            <h3 class="text-xl font-bold mb-4">Tag Usage Distribution</h3>
                            <?php $maxCount = !empty($tags) ? max(array_column($tags, 'file_count')) : 1; ?>
                            <?php foreach($tags as $tag): ?>
                                <?php $pct = $maxCount > 0 ? ($tag['file_count'] / $maxCount * 100) : 0; ?>
                                <div class="mb-3">
                                    <div class="flex justify-between mb-1">
                                        <span class="font-medium"><span class="inline-block w-4 h-4 rounded mr-2" style="background-color: <?= htmlspecialchars($tag['color']) ?>"></span><?= htmlspecialchars($tag['name']) ?></span>
                                        <span class="text-gray-600"><?= $tag['file_count'] ?> files</span>
                                    </div>
                                    <div class="chart-bar" style="--percentage: <?= $pct ?>%"></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="bg-white rounded-lg shadow p-6">
                            <h3 class="text-xl font-bold mb-4">Tag Details</h3>
                            <?php foreach($tags as $tag): ?>
                                <?php if ($tag['file_count'] > 0): $tagFiles = $db->fetchAll("SELECT f.*, u.name as owner_name FROM files f JOIN file_tags ft ON f.id = ft.file_id LEFT JOIN users u ON f.owner_id = u.id WHERE ft.tag_id = ? AND f.is_destroyed = 0 ORDER BY f.display_number", [$tag['id']]); ?>
                                    <div class="mb-6 border-b pb-4">
                                        <h4 class="text-lg font-semibold mb-3"><span class="inline-block px-3 py-1 rounded mr-2" style="background-color: <?= htmlspecialchars($tag['color']) ?>33; color: <?= htmlspecialchars($tag['color']) ?>"><?= htmlspecialchars($tag['name']) ?></span><span class="text-gray-600 text-sm">(<?= count($tagFiles) ?> files)</span></h4>
                                        <table class="report-table">
                                            <thead><tr><th>File #</th><th>Name</th><th>Owner</th><th>Status</th></tr></thead>
                                            <tbody>
                                                <?php foreach($tagFiles as $f): ?>
                                                    <tr>
                                                        <td><a href="?page=files&action=view&id=<?= $f['id'] ?>" class="text-blue-600 hover:underline"><?= htmlspecialchars($f['display_number']) ?></a></td>
                                                        <td><?= htmlspecialchars($f['name']) ?></td>
                                                        <td><?= htmlspecialchars($f['owner_name']) ?></td>
                                                        <td><span class="px-2 py-1 text-xs rounded <?= $f['is_archived'] ? 'bg-yellow-100 text-yellow-800' : ($f['is_checked_out'] ? 'bg-orange-100 text-orange-800' : 'bg-green-100 text-green-800') ?>"><?= $f['is_archived'] ? 'Archived' : ($f['is_checked_out'] ? 'Checked Out' : 'Active') ?></span></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>

                    <?php elseif ($report === 'checkouts'): ?>
                        <?php if ($_SESSION['user_role'] !== 'admin'): ?>
                            <div class="bg-red-100 text-red-700 p-4 rounded">This report is only available to administrators.</div>
                        <?php else:
                            $currentCheckouts = $db->fetchAll("SELECT f.*, u.name as checked_out_to FROM files f LEFT JOIN users u ON f.checked_out_by = u.id WHERE f.is_checked_out = 1 ORDER BY f.expected_return_date");
                            $checkoutHistory = $db->fetchAll("SELECT fc.*, f.display_number, f.name as file_name, u.name as user_name FROM file_checkouts fc JOIN files f ON fc.file_id = f.id LEFT JOIN users u ON fc.user_id = u.id WHERE fc.checked_out_at >= datetime('now', '-30 days') ORDER BY fc.checked_out_at DESC LIMIT 100");
                            $overdueCount = count(array_filter($currentCheckouts, fn($f) => isOverdue($f['expected_return_date'])));
                        ?>
                        <div class="flex justify-between items-center mb-6">
                            <div><a href="?page=reports" class="text-blue-600 hover:underline">← Back to Reports</a><h2 class="text-3xl font-bold mt-2">Checkout Status Report</h2></div>
                            <div class="flex gap-2 no-print"><a href="?page=reports&report=checkouts&format=csv" class="bg-green-600 text-white px-4 py-2 rounded">Export CSV</a><button onclick="window.print()" class="bg-blue-600 text-white px-4 py-2 rounded">Print</button></div>
                        </div>
                        <div class="grid grid-cols-3 gap-4 mb-6">
                            <div class="stat-card"><div class="text-2xl font-bold text-blue-600"><?= count($currentCheckouts) ?></div><div class="text-gray-600">Current Checkouts</div></div>
                            <div class="stat-card"><div class="text-2xl font-bold text-red-600"><?= $overdueCount ?></div><div class="text-gray-600">Overdue Files</div></div>
                            <div class="stat-card"><div class="text-2xl font-bold text-green-600"><?= count($checkoutHistory) ?></div><div class="text-gray-600">Last 30 Days</div></div>
                        </div>
                        <div class="bg-white rounded-lg shadow p-6 mb-6">
                            <h3 class="text-xl font-bold mb-4">Current Checkouts</h3>
                            <table class="report-table">
                                <thead><tr><th>File #</th><th>Name</th><th>Checked Out To</th><th>Checked Out</th><th>Expected Return</th><th>Status</th></tr></thead>
                                <tbody>
                                    <?php foreach($currentCheckouts as $f): $overdue = isOverdue($f['expected_return_date']); $daysOD = daysOverdue($f['expected_return_date']); ?>
                                        <tr class="<?= $overdue ? 'bg-red-50' : '' ?>">
                                            <td><a href="?page=files&action=view&id=<?= $f['id'] ?>" class="text-blue-600 hover:underline"><?= htmlspecialchars($f['display_number']) ?></a></td>
                                            <td><?= htmlspecialchars($f['name']) ?></td>
                                            <td><?= htmlspecialchars($f['checked_out_to']) ?></td>
                                            <td><?= date('M d, Y', strtotime($f['checked_out_at'])) ?></td>
                                            <td><?= date('M d, Y', strtotime($f['expected_return_date'])) ?></td>
                                            <td><?php if ($overdue): ?><span class="px-2 py-1 text-xs rounded bg-red-100 text-red-800 font-bold">OVERDUE (<?= $daysOD ?> days)</span><?php else: ?><span class="px-2 py-1 text-xs rounded bg-green-100 text-green-800">On Time</span><?php endif; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($currentCheckouts)): ?><tr><td colspan="6" class="text-center text-gray-600 py-4">No current checkouts</td></tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>

                    <?php elseif ($report === 'overdue'): ?>
                        <?php
                        $overdueFiles = $db->fetchAll("SELECT f.*, u.name as checked_out_to, u.email as user_email FROM files f LEFT JOIN users u ON f.checked_out_by = u.id WHERE f.is_checked_out = 1 AND f.expected_return_date < DATE('now') ORDER BY f.expected_return_date");
                        ?>
                        <div class="flex justify-between items-center mb-6">
                            <div><a href="?page=reports" class="text-blue-600 hover:underline">← Back to Reports</a><h2 class="text-3xl font-bold mt-2">Overdue Files Report</h2></div>
                            <div class="flex gap-2 no-print"><a href="?page=reports&report=overdue&format=csv" class="bg-green-600 text-white px-4 py-2 rounded">Export CSV</a><button onclick="window.print()" class="bg-blue-600 text-white px-4 py-2 rounded">Print</button></div>
                        </div>
                        <div class="grid grid-cols-3 gap-4 mb-6">
                            <div class="stat-card border-2 border-red-500"><div class="text-2xl font-bold text-red-600"><?= count($overdueFiles) ?></div><div class="text-gray-600">Overdue Files</div></div>
                            <div class="stat-card"><div class="text-2xl font-bold text-orange-600"><?= !empty($overdueFiles) ? max(array_map(fn($f) => daysOverdue($f['expected_return_date']), $overdueFiles)) : 0 ?></div><div class="text-gray-600">Max Days Overdue</div></div>
                            <div class="stat-card"><div class="text-2xl font-bold text-yellow-600"><?= !empty($overdueFiles) ? number_format(array_sum(array_map(fn($f) => daysOverdue($f['expected_return_date']), $overdueFiles)) / count($overdueFiles), 1) : 0 ?></div><div class="text-gray-600">Avg Days Overdue</div></div>
                        </div>
                        <div class="bg-white rounded-lg shadow p-6">
                            <h3 class="text-xl font-bold mb-4">Overdue Files (Sorted by Most Overdue)</h3>
                            <?php if (!empty($overdueFiles)): usort($overdueFiles, fn($a, $b) => daysOverdue($b['expected_return_date']) - daysOverdue($a['expected_return_date'])); ?>
                                <table class="report-table">
                                    <thead><tr><th>File #</th><th>Name</th><th>Checked Out To</th><th>Contact</th><th>Expected Return</th><th>Days Overdue</th><th class="no-print">Actions</th></tr></thead>
                                    <tbody>
                                        <?php foreach($overdueFiles as $f): $daysOD = daysOverdue($f['expected_return_date']); $sev = $daysOD > 30 ? 'bg-red-100' : ($daysOD > 14 ? 'bg-orange-100' : 'bg-yellow-50'); ?>
                                            <tr class="<?= $sev ?>">
                                                <td><a href="?page=files&action=view&id=<?= $f['id'] ?>" class="text-blue-600 hover:underline"><?= htmlspecialchars($f['display_number']) ?></a></td>
                                                <td><?= htmlspecialchars($f['name']) ?></td>
                                                <td><?= htmlspecialchars($f['checked_out_to']) ?></td>
                                                <td><a href="mailto:<?= htmlspecialchars($f['user_email']) ?>" class="text-blue-600 hover:underline"><?= htmlspecialchars($f['user_email']) ?></a></td>
                                                <td><?= date('M d, Y', strtotime($f['expected_return_date'])) ?></td>
                                                <td><span class="px-2 py-1 text-xs rounded bg-red-100 text-red-800 font-bold"><?= $daysOD ?> days</span></td>
                                                <td class="no-print"><button disabled class="bg-gray-300 text-gray-500 px-3 py-1 rounded text-xs cursor-not-allowed">Send Reminder</button></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <div class="text-center py-8"><div class="text-green-600 text-xl font-bold mb-2">No Overdue Files!</div><div class="text-gray-600">All checked out files are on time.</div></div>
                            <?php endif; ?>
                        </div>

                    <?php elseif ($report === 'user_activity'): ?>
                        <?php if ($_SESSION['user_role'] !== 'admin'): ?>
                            <div class="bg-red-100 text-red-700 p-4 rounded">This report is only available to administrators.</div>
                        <?php else:
                            $users = $db->fetchAll("SELECT * FROM users WHERE status != 'deleted' ORDER BY name");
                            $userStats = [];
                            foreach($users as $u) {
                                $userStats[$u['id']] = [
                                    'user' => $u,
                                    'files_owned' => $db->fetchOne("SELECT COUNT(*) as count FROM files WHERE owner_id = ? AND is_destroyed = 0", [$u['id']])['count'],
                                    'current_checkouts' => $db->fetchOne("SELECT COUNT(*) as count FROM files WHERE checked_out_by = ? AND is_checked_out = 1", [$u['id']])['count'],
                                    'total_checkouts' => $db->fetchOne("SELECT COUNT(*) as count FROM file_checkouts WHERE user_id = ?", [$u['id']])['count'],
                                    'files_moved' => $db->fetchOne("SELECT COUNT(*) as count FROM file_movements WHERE moved_by = ?", [$u['id']])['count']
                                ];
                            }
                            if ($format === 'csv') {
                                $data = array_map(fn($s) => [$s['user']['name'], $s['user']['email'], ucfirst($s['user']['role']), $s['files_owned'], $s['current_checkouts'], $s['total_checkouts'], $s['files_moved']], $userStats);
                                exportCSV($data, 'user_activity_'.date('Y-m-d').'.csv', ['User','Email','Role','Files Owned','Current Checkouts','Total Checkouts','Files Moved']);
                            }
                        ?>
                        <div class="flex justify-between items-center mb-6">
                            <div><a href="?page=reports" class="text-blue-600 hover:underline">← Back to Reports</a><h2 class="text-3xl font-bold mt-2">User Activity Report</h2></div>
                            <div class="flex gap-2 no-print"><a href="?page=reports&report=user_activity&format=csv" class="bg-green-600 text-white px-4 py-2 rounded">Export CSV</a><button onclick="window.print()" class="bg-blue-600 text-white px-4 py-2 rounded">Print</button></div>
                        </div>
                        <div class="grid grid-cols-4 gap-4 mb-6">
                            <div class="stat-card"><div class="text-2xl font-bold text-blue-600"><?= count($users) ?></div><div class="text-gray-600">Total Users</div></div>
                            <div class="stat-card"><div class="text-2xl font-bold text-green-600"><?= count(array_filter($users, fn($u) => $u['status'] === 'active')) ?></div><div class="text-gray-600">Active Users</div></div>
                            <div class="stat-card"><div class="text-2xl font-bold text-purple-600"><?= array_sum(array_column($userStats, 'files_owned')) ?></div><div class="text-gray-600">Total Files Owned</div></div>
                            <div class="stat-card"><div class="text-2xl font-bold text-orange-600"><?= array_sum(array_column($userStats, 'current_checkouts')) ?></div><div class="text-gray-600">Current Checkouts</div></div>
                        </div>
                        <div class="bg-white rounded-lg shadow p-6">
                            <h3 class="text-xl font-bold mb-4">User Activity Details</h3>
                            <table class="report-table">
                                <thead><tr><th>User</th><th>Role</th><th>Status</th><th>Files Owned</th><th>Current Checkouts</th><th>Total Checkouts</th><th>Files Moved</th></tr></thead>
                                <tbody>
                                    <?php foreach($userStats as $s): ?>
                                        <tr>
                                            <td><div class="font-medium"><?= htmlspecialchars($s['user']['name']) ?></div><div class="text-sm text-gray-600"><?= htmlspecialchars($s['user']['email']) ?></div></td>
                                            <td><span class="px-2 py-1 text-xs rounded <?= $s['user']['role'] === 'admin' ? 'bg-purple-100 text-purple-800' : 'bg-gray-100 text-gray-800' ?>"><?= ucfirst($s['user']['role']) ?></span></td>
                                            <td><span class="px-2 py-1 text-xs rounded <?= $s['user']['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>"><?= ucfirst($s['user']['status']) ?></span></td>
                                            <td><?= $s['files_owned'] ?></td>
                                            <td><?= $s['current_checkouts'] ?></td>
                                            <td><?= $s['total_checkouts'] ?></td>
                                            <td><?= $s['files_moved'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>

                    <?php elseif ($report === 'compliance'): ?>
                        <?php if ($_SESSION['user_role'] !== 'admin'): ?>
                            <div class="bg-red-100 text-red-700 p-4 rounded">This report is only available to administrators.</div>
                        <?php else:
                            $archivedFiles = $db->fetchAll("SELECT f.*, u.name as owner_name FROM files f LEFT JOIN users u ON f.owner_id = u.id WHERE f.is_archived = 1 AND f.is_destroyed = 0 ORDER BY f.archived_at DESC");
                            $destroyedFiles = $db->fetchAll("SELECT f.*, u.name as destroyed_by_name FROM files f LEFT JOIN users u ON f.destroyed_by = u.id WHERE f.is_destroyed = 1 ORDER BY f.destroyed_at DESC");
                            $pendingDestruction = $db->fetchAll("SELECT f.*, u.name as owner_name FROM files f LEFT JOIN users u ON f.owner_id = u.id WHERE f.is_archived = 1 AND f.is_destroyed = 0 AND f.archived_at <= datetime('now', '-90 days') ORDER BY f.archived_at");
                            if ($format === 'csv') {
                                $data = [];
                                foreach($archivedFiles as $f) $data[] = [$f['display_number'], $f['name'], 'Archived', $f['archived_at'], $f['archive_reason'] ?: 'N/A', $f['owner_name']];
                                foreach($destroyedFiles as $f) $data[] = [$f['display_number'], $f['name'], 'Destroyed', $f['destroyed_at'], $f['destruction_method'] ?: 'N/A', $f['destroyed_by_name']];
                                exportCSV($data, 'compliance_report_'.date('Y-m-d').'.csv', ['File #','Name','Status','Date','Method/Reason','Performed By']);
                            }
                        ?>
                        <div class="flex justify-between items-center mb-6">
                            <div><a href="?page=reports" class="text-blue-600 hover:underline">← Back to Reports</a><h2 class="text-3xl font-bold mt-2">Archive & Destruction Compliance Report</h2></div>
                            <div class="flex gap-2 no-print"><a href="?page=reports&report=compliance&format=csv" class="bg-green-600 text-white px-4 py-2 rounded">Export CSV</a><button onclick="window.print()" class="bg-blue-600 text-white px-4 py-2 rounded">Print</button></div>
                        </div>
                        <div class="grid grid-cols-3 gap-4 mb-6">
                            <div class="stat-card"><div class="text-2xl font-bold text-yellow-600"><?= count($archivedFiles) ?></div><div class="text-gray-600">Archived Files</div></div>
                            <div class="stat-card"><div class="text-2xl font-bold text-red-600"><?= count($destroyedFiles) ?></div><div class="text-gray-600">Destroyed Files</div></div>
                            <div class="stat-card border-2 border-orange-500"><div class="text-2xl font-bold text-orange-600"><?= count($pendingDestruction) ?></div><div class="text-gray-600">Pending Destruction</div></div>
                        </div>
                        <?php if (!empty($pendingDestruction)): ?>
                            <div class="bg-orange-100 border-l-4 border-orange-500 p-4 mb-6">
                                <p class="text-sm text-orange-700"><strong><?= count($pendingDestruction) ?> files</strong> have been archived for more than 90 days and are pending destruction review.</p>
                            </div>
                        <?php endif; ?>
                        <div class="bg-white rounded-lg shadow p-6 mb-6">
                            <h3 class="text-xl font-bold mb-4">Archived Files</h3>
                            <table class="report-table">
                                <thead><tr><th>File #</th><th>Name</th><th>Owner</th><th>Archived Date</th><th>Reason</th></tr></thead>
                                <tbody>
                                    <?php foreach(array_slice($archivedFiles, 0, 50) as $f): ?>
                                        <tr>
                                            <td><a href="?page=files&action=view&id=<?= $f['id'] ?>" class="text-blue-600 hover:underline"><?= htmlspecialchars($f['display_number']) ?></a></td>
                                            <td><?= htmlspecialchars($f['name']) ?></td>
                                            <td><?= htmlspecialchars($f['owner_name']) ?></td>
                                            <td><?= date('M d, Y', strtotime($f['archived_at'])) ?></td>
                                            <td><?= htmlspecialchars($f['archive_reason'] ?: 'N/A') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($archivedFiles)): ?><tr><td colspan="5" class="text-center text-gray-600 py-4">No archived files</td></tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="bg-white rounded-lg shadow p-6">
                            <h3 class="text-xl font-bold mb-4 text-red-600">Destruction Records</h3>
                            <table class="report-table">
                                <thead><tr><th>File #</th><th>Name</th><th>Destroyed Date</th><th>Method</th><th>Performed By</th><th>Certificate</th></tr></thead>
                                <tbody>
                                    <?php foreach(array_slice($destroyedFiles, 0, 50) as $f): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($f['display_number']) ?></td>
                                            <td><?= htmlspecialchars($f['name']) ?></td>
                                            <td><?= date('M d, Y', strtotime($f['destroyed_at'])) ?></td>
                                            <td><?= htmlspecialchars($f['destruction_method'] ?: 'N/A') ?></td>
                                            <td><?= htmlspecialchars($f['destroyed_by_name']) ?></td>
                                            <td><a href="?page=files&action=certificate&id=<?= $f['id'] ?>" class="text-blue-600 hover:underline text-sm">View Certificate</a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($destroyedFiles)): ?><tr><td colspan="6" class="text-center text-gray-600 py-4">No destroyed files</td></tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>

                    <?php elseif ($report === 'stats'): ?>
                        <?php
                        $stats = [
                            'total' => $db->fetchOne("SELECT COUNT(*) as count FROM files")['count'],
                            'active' => $db->fetchOne("SELECT COUNT(*) as count FROM files WHERE is_archived = 0 AND is_destroyed = 0")['count'],
                            'checked_out' => $db->fetchOne("SELECT COUNT(*) as count FROM files WHERE is_checked_out = 1")['count'],
                            'archived' => $db->fetchOne("SELECT COUNT(*) as count FROM files WHERE is_archived = 1 AND is_destroyed = 0")['count'],
                            'destroyed' => $db->fetchOne("SELECT COUNT(*) as count FROM files WHERE is_destroyed = 1")['count']
                        ];
                        $topLocations = $db->fetchAll("SELECT l.name, COUNT(f.id) as file_count FROM locations l LEFT JOIN cabinets c ON l.id = c.location_id LEFT JOIN drawers d ON c.id = d.cabinet_id LEFT JOIN files f ON d.id = f.current_drawer_id WHERE f.is_destroyed = 0 GROUP BY l.id ORDER BY file_count DESC LIMIT 5");
                        $topEntities = $db->fetchAll("SELECT e.name, COUNT(f.id) as file_count FROM entities e LEFT JOIN files f ON e.id = f.entity_id WHERE f.is_destroyed = 0 GROUP BY e.id ORDER BY file_count DESC LIMIT 5");
                        $topTags = $db->fetchAll("SELECT t.name, t.color, COUNT(ft.file_id) as usage_count FROM tags t LEFT JOIN file_tags ft ON t.id = ft.tag_id GROUP BY t.id ORDER BY usage_count DESC LIMIT 10");
                        ?>
                        <div class="flex justify-between items-center mb-6">
                            <div><a href="?page=reports" class="text-blue-600 hover:underline">← Back to Reports</a><h2 class="text-3xl font-bold mt-2">System Statistics Dashboard</h2></div>
                            <button onclick="window.print()" class="no-print bg-blue-600 text-white px-4 py-2 rounded">Print</button>
                        </div>
                        <div class="grid grid-cols-5 gap-4 mb-6">
                            <div class="stat-card"><div class="text-2xl font-bold text-gray-600"><?= $stats['total'] ?></div><div class="text-gray-600">Total Files</div></div>
                            <div class="stat-card"><div class="text-2xl font-bold text-green-600"><?= $stats['active'] ?></div><div class="text-gray-600">Active</div></div>
                            <div class="stat-card"><div class="text-2xl font-bold text-orange-600"><?= $stats['checked_out'] ?></div><div class="text-gray-600">Checked Out</div></div>
                            <div class="stat-card"><div class="text-2xl font-bold text-yellow-600"><?= $stats['archived'] ?></div><div class="text-gray-600">Archived</div></div>
                            <div class="stat-card"><div class="text-2xl font-bold text-red-600"><?= $stats['destroyed'] ?></div><div class="text-gray-600">Destroyed</div></div>
                        </div>
                        <div class="bg-white rounded-lg shadow p-6 mb-6">
                            <h3 class="text-xl font-bold mb-4">File Status Distribution</h3>
                            <?php $total = $stats['total']; foreach(['Active' => ['count' => $stats['active'], 'color' => '#10B981'], 'Checked Out' => ['count' => $stats['checked_out'], 'color' => '#F59E0B'], 'Archived' => ['count' => $stats['archived'], 'color' => '#EAB308'], 'Destroyed' => ['count' => $stats['destroyed'], 'color' => '#EF4444']] as $status => $data): $pct = $total > 0 ? ($data['count'] / $total * 100) : 0; ?>
                                <div class="mb-3">
                                    <div class="flex justify-between mb-1"><span class="font-medium"><?= $status ?></span><span class="text-gray-600"><?= $data['count'] ?> (<?= number_format($pct, 1) ?>%)</span></div>
                                    <div class="h-6 rounded" style="background: linear-gradient(to right, <?= $data['color'] ?> 0%, <?= $data['color'] ?> <?= $pct ?>%, #E5E7EB <?= $pct ?>%);"></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="grid grid-cols-2 gap-6">
                            <div class="bg-white rounded-lg shadow p-6">
                                <h3 class="text-xl font-bold mb-4">Top Locations</h3>
                                <?php $maxLoc = !empty($topLocations) ? max(array_column($topLocations, 'file_count')) : 1; foreach($topLocations as $loc): $pct = $maxLoc > 0 ? ($loc['file_count'] / $maxLoc * 100) : 0; ?>
                                    <div class="mb-3">
                                        <div class="flex justify-between mb-1"><span class="font-medium"><?= htmlspecialchars($loc['name']) ?></span><span class="text-gray-600"><?= $loc['file_count'] ?> files</span></div>
                                        <div class="chart-bar" style="--percentage: <?= $pct ?>%"></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="bg-white rounded-lg shadow p-6">
                                <h3 class="text-xl font-bold mb-4">Top Entities</h3>
                                <?php $maxEnt = !empty($topEntities) ? max(array_column($topEntities, 'file_count')) : 1; foreach($topEntities as $ent): $pct = $maxEnt > 0 ? ($ent['file_count'] / $maxEnt * 100) : 0; ?>
                                    <div class="mb-3">
                                        <div class="flex justify-between mb-1"><span class="font-medium"><?= htmlspecialchars($ent['name']) ?></span><span class="text-gray-600"><?= $ent['file_count'] ?> files</span></div>
                                        <div class="chart-bar" style="--percentage: <?= $pct ?>%"></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                    <?php elseif ($report === 'custom'): ?>
                        <?php
                        if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['entity_type']) && $_GET['entity_type'] === 'files' && !empty($_GET['fields'])) {
                            $fields = $_GET['fields'];
                            $fieldMap = ['display_number' => 'f.display_number', 'name' => 'f.name', 'owner' => 'u.name as owner_name', 'entity' => 'e.name as entity_name', 'location' => 'l.name as location_name', 'sensitivity' => 'f.sensitivity', 'status' => "CASE WHEN f.is_archived = 1 THEN 'Archived' WHEN f.is_checked_out = 1 THEN 'Checked Out' ELSE 'Active' END as status", 'created_at' => 'f.created_at'];
                            $selectFields = array_map(fn($f) => $fieldMap[$f] ?? '', $fields);
                            $headers = array_map(fn($f) => ucwords(str_replace('_', ' ', $f)), $fields);
                            $query = "SELECT " . implode(', ', $selectFields) . " FROM files f LEFT JOIN users u ON f.owner_id = u.id LEFT JOIN entities e ON f.entity_id = e.id LEFT JOIN drawers d ON f.current_drawer_id = d.id LEFT JOIN cabinets c ON d.cabinet_id = c.id LEFT JOIN locations l ON c.location_id = l.id WHERE 1=1";
                            $params = [];
                            if (!empty($_GET['filter_status'])) {
                                if ($_GET['filter_status'] === 'active') $query .= " AND f.is_archived = 0 AND f.is_destroyed = 0 AND f.is_checked_out = 0";
                                elseif ($_GET['filter_status'] === 'checked_out') $query .= " AND f.is_checked_out = 1";
                                elseif ($_GET['filter_status'] === 'archived') $query .= " AND f.is_archived = 1";
                            }
                            if (!empty($_GET['filter_sensitivity'])) { $query .= " AND f.sensitivity = ?"; $params[] = $_GET['filter_sensitivity']; }
                            if (!empty($_GET['filter_entity'])) { $query .= " AND f.entity_id = ?"; $params[] = $_GET['filter_entity']; }
                            if (!empty($_GET['filter_location'])) { $query .= " AND l.id = ?"; $params[] = $_GET['filter_location']; }
                            $sortField = $_GET['sort_by'] ?? 'display_number';
                            $sortOrder = $_GET['sort_order'] ?? 'ASC';
                            $query .= " ORDER BY " . ($fieldMap[$sortField] ?? 'f.display_number') . " " . $sortOrder;
                            $results = $db->fetchAll($query, $params);
                            if ($format === 'csv') {
                                $data = array_map(fn($r) => array_values($r), $results);
                                exportCSV($data, 'custom_report_'.date('Y-m-d').'.csv', $headers);
                            }
                        }
                        ?>
                        <div class="flex justify-between items-center mb-6">
                            <div><a href="?page=reports" class="text-blue-600 hover:underline">← Back to Reports</a><h2 class="text-3xl font-bold mt-2">Custom Report Builder</h2></div>
                        </div>
                        <?php if (!empty($results)): ?>
                            <div class="bg-white rounded-lg shadow p-6 mb-6">
                                <div class="flex justify-between items-center mb-4">
                                    <h3 class="text-xl font-bold">Report Results (<?= count($results) ?> records)</h3>
                                    <div class="flex gap-2 no-print">
                                        <a href="?<?= $_SERVER['QUERY_STRING'] ?>&format=csv" class="bg-green-600 text-white px-4 py-2 rounded text-sm">Export CSV</a>
                                        <button onclick="window.print()" class="bg-blue-600 text-white px-4 py-2 rounded text-sm">Print</button>
                                    </div>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="report-table">
                                        <thead><tr><?php foreach($headers as $h): ?><th><?= htmlspecialchars($h) ?></th><?php endforeach; ?></tr></thead>
                                        <tbody>
                                            <?php foreach($results as $r): ?>
                                                <tr><?php foreach($r as $v): ?><td><?= htmlspecialchars($v ?? 'N/A') ?></td><?php endforeach; ?></tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="bg-white rounded-lg shadow p-6 no-print">
                            <h3 class="text-xl font-bold mb-4">Build Your Report</h3>
                            <form method="GET" class="space-y-6">
                                <input type="hidden" name="page" value="reports">
                                <input type="hidden" name="report" value="custom">
                                <div><label class="block text-sm font-medium mb-2">1. Entity Type</label><select name="entity_type" required class="w-full px-3 py-2 border rounded"><option value="">Choose...</option><option value="files" <?= ($_GET['entity_type'] ?? '') === 'files' ? 'selected' : '' ?>>Files</option></select></div>
                                <div><label class="block text-sm font-medium mb-2">2. Select Fields</label><div class="grid grid-cols-3 gap-3">
                                    <label class="flex items-center"><input type="checkbox" name="fields[]" value="display_number" <?= in_array('display_number', $_GET['fields'] ?? []) ? 'checked' : '' ?> class="mr-2">File Number</label>
                                    <label class="flex items-center"><input type="checkbox" name="fields[]" value="name" <?= in_array('name', $_GET['fields'] ?? []) ? 'checked' : '' ?> class="mr-2">Name</label>
                                    <label class="flex items-center"><input type="checkbox" name="fields[]" value="owner" <?= in_array('owner', $_GET['fields'] ?? []) ? 'checked' : '' ?> class="mr-2">Owner</label>
                                    <label class="flex items-center"><input type="checkbox" name="fields[]" value="entity" <?= in_array('entity', $_GET['fields'] ?? []) ? 'checked' : '' ?> class="mr-2">Entity</label>
                                    <label class="flex items-center"><input type="checkbox" name="fields[]" value="location" <?= in_array('location', $_GET['fields'] ?? []) ? 'checked' : '' ?> class="mr-2">Location</label>
                                    <label class="flex items-center"><input type="checkbox" name="fields[]" value="sensitivity" <?= in_array('sensitivity', $_GET['fields'] ?? []) ? 'checked' : '' ?> class="mr-2">Sensitivity</label>
                                    <label class="flex items-center"><input type="checkbox" name="fields[]" value="status" <?= in_array('status', $_GET['fields'] ?? []) ? 'checked' : '' ?> class="mr-2">Status</label>
                                    <label class="flex items-center"><input type="checkbox" name="fields[]" value="created_at" <?= in_array('created_at', $_GET['fields'] ?? []) ? 'checked' : '' ?> class="mr-2">Created Date</label>
                                </div></div>
                                <div><label class="block text-sm font-medium mb-2">3. Filters</label><div class="grid grid-cols-2 gap-4">
                                    <div><label class="block text-xs text-gray-600 mb-1">Status</label><select name="filter_status" class="w-full px-3 py-2 border rounded text-sm"><option value="">All</option><option value="active" <?= ($_GET['filter_status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option><option value="checked_out" <?= ($_GET['filter_status'] ?? '') === 'checked_out' ? 'selected' : '' ?>>Checked Out</option><option value="archived" <?= ($_GET['filter_status'] ?? '') === 'archived' ? 'selected' : '' ?>>Archived</option></select></div>
                                    <div><label class="block text-xs text-gray-600 mb-1">Sensitivity</label><select name="filter_sensitivity" class="w-full px-3 py-2 border rounded text-sm"><option value="">All</option><option value="public" <?= ($_GET['filter_sensitivity'] ?? '') === 'public' ? 'selected' : '' ?>>Public</option><option value="internal" <?= ($_GET['filter_sensitivity'] ?? '') === 'internal' ? 'selected' : '' ?>>Internal</option><option value="confidential" <?= ($_GET['filter_sensitivity'] ?? '') === 'confidential' ? 'selected' : '' ?>>Confidential</option></select></div>
                                    <div><label class="block text-xs text-gray-600 mb-1">Entity</label><select name="filter_entity" class="w-full px-3 py-2 border rounded text-sm"><option value="">All</option><?php $entities = $db->fetchAll("SELECT * FROM entities ORDER BY name"); foreach($entities as $ent): ?><option value="<?= $ent['id'] ?>" <?= ($_GET['filter_entity'] ?? '') == $ent['id'] ? 'selected' : '' ?>><?= htmlspecialchars($ent['name']) ?></option><?php endforeach; ?></select></div>
                                    <div><label class="block text-xs text-gray-600 mb-1">Location</label><select name="filter_location" class="w-full px-3 py-2 border rounded text-sm"><option value="">All</option><?php $locations = $db->fetchAll("SELECT * FROM locations ORDER BY name"); foreach($locations as $loc): ?><option value="<?= $loc['id'] ?>" <?= ($_GET['filter_location'] ?? '') == $loc['id'] ? 'selected' : '' ?>><?= htmlspecialchars($loc['name']) ?></option><?php endforeach; ?></select></div>
                                </div></div>
                                <div><label class="block text-sm font-medium mb-2">4. Sort</label><div class="grid grid-cols-2 gap-4">
                                    <div><label class="block text-xs text-gray-600 mb-1">Sort By</label><select name="sort_by" class="w-full px-3 py-2 border rounded text-sm"><option value="display_number">File Number</option><option value="name">Name</option><option value="created_at">Created Date</option></select></div>
                                    <div><label class="block text-xs text-gray-600 mb-1">Order</label><select name="sort_order" class="w-full px-3 py-2 border rounded text-sm"><option value="ASC">Ascending</option><option value="DESC">Descending</option></select></div>
                                </div></div>
                                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">Generate Report</button>
                            </form>
                        </div>

                    <?php else: ?>
                        <div class="bg-white rounded-lg shadow p-6">
                            <p class="text-gray-600">Report not found. <a href="?page=reports" class="text-blue-600 hover:underline">Return to Reports Dashboard</a></p>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <h2 class="text-3xl font-bold mb-6"><?= ucfirst($page) ?></h2>
                    <div class="bg-white rounded-lg shadow p-6">
                        <p class="text-gray-600">This page is under construction.</p>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
<?php endif; ?>

</body>
</html>
