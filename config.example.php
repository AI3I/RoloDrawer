<?php
/**
 * RoloDrawer Configuration File
 *
 * Copy this file to config.php and update with your settings
 *
 * SECURITY WARNING:
 * - Keep this file secure and outside web root if possible
 * - Set file permissions to 600 (read/write for owner only)
 * - Never commit config.php to version control
 *
 * @version 1.0.0
 * @date 2026-01-15
 */

// Prevent direct access
if (!defined('ROLODRAWER_LOADED')) {
    die('Direct access not permitted');
}

// =============================================================================
// DATABASE CONFIGURATION
// =============================================================================

/**
 * Database Type
 *
 * Supported values:
 * - 'sqlite'  : SQLite (recommended for small installations, <10,000 files)
 * - 'mysql'   : MySQL 5.7+ or MariaDB 10.2+
 * - 'pgsql'   : PostgreSQL 12+
 */
define('DB_TYPE', '{{DB_TYPE}}');

/**
 * Database Host
 *
 * For MySQL/PostgreSQL only. Usually 'localhost' or '127.0.0.1'
 * For SQLite, this setting is ignored
 *
 * Examples:
 * - 'localhost'
 * - '127.0.0.1'
 * - 'db.example.com'
 * - 'localhost:3307' (custom port)
 */
define('DB_HOST', '{{DB_HOST}}');

/**
 * Database Name
 *
 * For SQLite: filename without .db extension (e.g., 'rolodrawer')
 * For MySQL/PostgreSQL: database name (must exist)
 *
 * SQLite file will be stored in: data/{DB_NAME}.db
 */
define('DB_NAME', '{{DB_NAME}}');

/**
 * Database Username
 *
 * For MySQL/PostgreSQL only
 * For SQLite, this setting is ignored
 */
define('DB_USER', '{{DB_USER}}');

/**
 * Database Password
 *
 * For MySQL/PostgreSQL only
 * For SQLite, this setting is ignored
 */
define('DB_PASS', '{{DB_PASS}}');

/**
 * Database Table Prefix
 *
 * Prefix for all database tables
 * Useful if sharing database with other applications
 *
 * Default: 'rd_'
 */
define('DB_PREFIX', '{{DB_PREFIX}}');

/**
 * Database Charset
 *
 * For MySQL/PostgreSQL only
 * Default: 'utf8mb4' (recommended)
 */
define('DB_CHARSET', 'utf8mb4');

/**
 * Database Collation
 *
 * For MySQL only
 * Default: 'utf8mb4_unicode_ci'
 */
define('DB_COLLATION', 'utf8mb4_unicode_ci');

// =============================================================================
// APPLICATION CONFIGURATION
// =============================================================================

/**
 * Application Name
 *
 * Display name for your RoloDrawer installation
 * Shows in page titles, headers, and emails
 */
define('APP_NAME', '{{APP_NAME}}');

/**
 * Base URL
 *
 * Full URL where RoloDrawer is installed (without trailing slash)
 *
 * Examples:
 * - 'https://files.company.com'
 * - 'https://www.company.com/rolodrawer'
 * - 'http://localhost/rolodrawer'
 */
define('BASE_URL', '{{BASE_URL}}');

/**
 * Timezone
 *
 * PHP timezone identifier for date/time display
 * Full list: https://www.php.net/manual/en/timezones.php
 *
 * Common timezones:
 * - 'America/New_York'    (Eastern)
 * - 'America/Chicago'     (Central)
 * - 'America/Denver'      (Mountain)
 * - 'America/Los_Angeles' (Pacific)
 * - 'UTC'                 (Universal)
 * - 'Europe/London'
 * - 'Asia/Tokyo'
 */
define('TIMEZONE', '{{TIMEZONE}}');

/**
 * Default Language
 *
 * ISO 639-1 language code
 * Default: 'en' (English)
 *
 * Future versions will support multiple languages
 */
define('DEFAULT_LANGUAGE', 'en');

// =============================================================================
// ENVIRONMENT & DEBUGGING
// =============================================================================

/**
 * Environment Mode
 *
 * Controls error reporting and debugging features
 *
 * Values:
 * - 'production'  : Errors logged, not displayed (use for live sites)
 * - 'development' : Errors displayed and logged (use for testing)
 *
 * IMPORTANT: Always use 'production' on live servers!
 */
define('ENVIRONMENT', 'production');

/**
 * Debug Mode
 *
 * Enable detailed debugging output
 *
 * true  : Show detailed errors, SQL queries, performance metrics
 * false : Hide debug information (recommended for production)
 *
 * SECURITY WARNING: Never enable debug mode in production!
 */
define('DEBUG_MODE', false);

/**
 * Error Logging
 *
 * Enable/disable error logging to files
 * Logs stored in: logs/error.log
 *
 * true  : Log errors to file
 * false : Don't log errors
 */
define('ERROR_LOGGING', true);

/**
 * Log Slow Queries
 *
 * Log database queries that exceed threshold
 *
 * true  : Log slow queries
 * false : Don't log slow queries
 */
define('LOG_SLOW_QUERIES', true);

/**
 * Slow Query Threshold
 *
 * Time in milliseconds
 * Queries slower than this will be logged if LOG_SLOW_QUERIES is enabled
 *
 * Default: 1000 (1 second)
 */
define('SLOW_QUERY_THRESHOLD', 1000);

// =============================================================================
// SECURITY SETTINGS
// =============================================================================

/**
 * Session Lifetime
 *
 * How long users stay logged in (in seconds)
 *
 * Common values:
 * - 1800   : 30 minutes
 * - 3600   : 1 hour (default)
 * - 28800  : 8 hours
 * - 86400  : 24 hours
 */
define('SESSION_LIFETIME', 3600);

/**
 * Remember Me Duration
 *
 * How long "Remember Me" keeps users logged in (in seconds)
 *
 * Common values:
 * - 604800   : 7 days (default)
 * - 2592000  : 30 days
 * - 7776000  : 90 days
 */
define('REMEMBER_ME_DURATION', 604800);

/**
 * Force HTTPS
 *
 * Redirect HTTP requests to HTTPS
 *
 * true  : Force HTTPS (recommended for production)
 * false : Allow HTTP (only for development/testing)
 */
define('FORCE_HTTPS', true);

/**
 * Password Minimum Length
 *
 * Minimum characters required for user passwords
 * Default: 8
 * Recommended: 12 or higher
 */
define('PASSWORD_MIN_LENGTH', 8);

/**
 * Password Require Complexity
 *
 * Require passwords to contain uppercase, lowercase, numbers, and symbols
 *
 * true  : Enforce complexity rules
 * false : Only enforce minimum length
 */
define('PASSWORD_REQUIRE_COMPLEXITY', true);

/**
 * Password Expiration
 *
 * Force users to change password after this many days
 * Set to 0 to disable password expiration
 *
 * Common values:
 * - 0    : Never expire
 * - 90   : 3 months (recommended for high security)
 * - 180  : 6 months
 * - 365  : 1 year
 */
define('PASSWORD_EXPIRATION_DAYS', 90);

/**
 * Failed Login Attempts Threshold
 *
 * Number of failed login attempts before account lockout
 * Set to 0 to disable lockout
 *
 * Default: 5
 */
define('FAILED_LOGIN_THRESHOLD', 5);

/**
 * Account Lockout Duration
 *
 * How long to lock account after too many failed attempts (in seconds)
 *
 * Common values:
 * - 900   : 15 minutes (default)
 * - 1800  : 30 minutes
 * - 3600  : 1 hour
 */
define('ACCOUNT_LOCKOUT_DURATION', 900);

/**
 * Two-Factor Authentication
 *
 * Require 2FA for administrators
 *
 * true  : Admins must enable 2FA
 * false : 2FA is optional
 */
define('REQUIRE_2FA_ADMIN', false);

/**
 * IP Whitelist
 *
 * Restrict access to specific IP addresses
 * Leave empty to allow all IPs
 *
 * Examples:
 * - ['192.168.1.0/24', '10.0.0.0/8']
 * - ['203.0.113.50']
 */
define('IP_WHITELIST', []);

// =============================================================================
// FILE UPLOAD SETTINGS
// =============================================================================

/**
 * Enable File Uploads
 *
 * Allow users to attach documents to file records
 *
 * true  : Enable file uploads
 * false : Disable file uploads
 */
define('ENABLE_FILE_UPLOADS', true);

/**
 * Upload Directory
 *
 * Path to store uploaded files (relative to application root)
 * Directory must be writable by web server
 *
 * Default: 'uploads'
 */
define('UPLOAD_DIR', 'uploads');

/**
 * Maximum Upload Size
 *
 * Maximum file size in bytes
 *
 * Common values:
 * - 1048576    : 1 MB
 * - 5242880    : 5 MB
 * - 10485760   : 10 MB (default)
 * - 52428800   : 50 MB
 * - 104857600  : 100 MB
 *
 * Note: Also limited by PHP settings (upload_max_filesize, post_max_size)
 */
define('MAX_UPLOAD_SIZE', 10485760);

/**
 * Allowed File Extensions
 *
 * Array of allowed file extensions (lowercase, without dot)
 *
 * Default: Common document formats
 */
define('ALLOWED_FILE_TYPES', [
    'pdf',
    'doc', 'docx',
    'xls', 'xlsx',
    'ppt', 'pptx',
    'txt', 'rtf',
    'jpg', 'jpeg', 'png', 'gif',
    'zip', 'rar', '7z'
]);

/**
 * Encrypt Uploaded Files
 *
 * Encrypt files on upload, decrypt on download
 * Requires OpenSSL extension
 *
 * true  : Encrypt files (recommended for sensitive data)
 * false : Store files unencrypted
 */
define('ENCRYPT_UPLOADS', false);

/**
 * Encryption Key
 *
 * Secret key for file encryption
 * MUST be exactly 32 characters
 * Generate with: openssl rand -base64 32
 *
 * SECURITY WARNING:
 * - Keep this secret!
 * - If lost, encrypted files cannot be recovered!
 * - Change this from the default value!
 */
define('ENCRYPTION_KEY', 'CHANGE_THIS_TO_RANDOM_32_CHARS');

// =============================================================================
// EMAIL CONFIGURATION
// =============================================================================

/**
 * Enable Email
 *
 * Enable/disable all email functionality
 *
 * true  : Enable emails (notifications, password resets, etc.)
 * false : Disable all emails
 */
define('ENABLE_EMAIL', true);

/**
 * Email Method
 *
 * How to send emails
 *
 * Values:
 * - 'mail'     : PHP mail() function (simple, may not work on all hosts)
 * - 'smtp'     : SMTP server (recommended, more reliable)
 * - 'sendmail' : Sendmail binary
 */
define('EMAIL_METHOD', 'smtp');

/**
 * SMTP Settings
 *
 * Only required if EMAIL_METHOD is 'smtp'
 */
define('SMTP_HOST', 'smtp.example.com');           // SMTP server hostname
define('SMTP_PORT', 587);                          // SMTP port (25, 465, 587)
define('SMTP_ENCRYPTION', 'tls');                  // 'tls', 'ssl', or '' for none
define('SMTP_AUTH', true);                         // Require authentication
define('SMTP_USERNAME', 'noreply@example.com');    // SMTP username
define('SMTP_PASSWORD', 'your-smtp-password');     // SMTP password

/**
 * Email From Address
 *
 * Email address for outgoing messages
 * Format: 'email@example.com' or 'Name <email@example.com>'
 */
define('EMAIL_FROM', 'RoloDrawer <noreply@example.com>');

/**
 * Admin Email
 *
 * Email address for system notifications and alerts
 */
define('ADMIN_EMAIL', 'admin@example.com');

// =============================================================================
// NOTIFICATIONS
// =============================================================================

/**
 * Checkout Reminders
 *
 * Send email reminders for upcoming file return dates
 *
 * true  : Send reminders
 * false : Don't send reminders
 */
define('ENABLE_CHECKOUT_REMINDERS', true);

/**
 * Reminder Days Before Due
 *
 * How many days before due date to send reminder
 * Default: 1 (one day before)
 */
define('REMINDER_DAYS_BEFORE', 1);

/**
 * Overdue Notifications
 *
 * Send daily email for overdue checkouts
 *
 * true  : Send overdue notifications
 * false : Don't send overdue notifications
 */
define('ENABLE_OVERDUE_NOTIFICATIONS', true);

/**
 * Activity Notifications
 *
 * Notify file owners when their files are checked out or moved
 *
 * true  : Send activity notifications
 * false : Don't send activity notifications
 */
define('ENABLE_ACTIVITY_NOTIFICATIONS', false);

// =============================================================================
// QR CODE & BARCODE SETTINGS
// =============================================================================

/**
 * QR Code Size
 *
 * Size of generated QR codes in pixels
 *
 * Common values:
 * - 150 : Small
 * - 200 : Medium (default)
 * - 300 : Large
 */
define('QR_CODE_SIZE', 200);

/**
 * QR Code Error Correction
 *
 * Error correction level for QR codes
 *
 * Values:
 * - 'L' : Low (7% correction)
 * - 'M' : Medium (15% correction) - default
 * - 'Q' : Quartile (25% correction)
 * - 'H' : High (30% correction) - recommended if codes might be damaged
 */
define('QR_CODE_ERROR_CORRECTION', 'M');

/**
 * Barcode Type
 *
 * Default barcode format
 *
 * Values:
 * - 'code128' : Code 128 (alphanumeric, default)
 * - 'code39'  : Code 39 (alphanumeric)
 * - 'ean13'   : EAN-13 (13 digits)
 */
define('BARCODE_TYPE', 'code128');

// =============================================================================
// CACHING
// =============================================================================

/**
 * Enable Caching
 *
 * Cache frequently accessed data for better performance
 *
 * true  : Enable caching (recommended)
 * false : Disable caching (only for development)
 */
define('ENABLE_CACHE', true);

/**
 * Cache Directory
 *
 * Path to store cache files (relative to application root)
 * Directory must be writable by web server
 *
 * Default: 'cache'
 */
define('CACHE_DIR', 'cache');

/**
 * Cache Lifetime
 *
 * How long to keep cached data (in seconds)
 *
 * Common values:
 * - 300    : 5 minutes
 * - 900    : 15 minutes
 * - 3600   : 1 hour (default)
 * - 86400  : 24 hours
 */
define('CACHE_LIFETIME', 3600);

// =============================================================================
// PAGINATION & DISPLAY
// =============================================================================

/**
 * Items Per Page
 *
 * Number of files to show per page in lists
 *
 * Default: 25
 * Common values: 10, 25, 50, 100
 */
define('ITEMS_PER_PAGE', 25);

/**
 * Search Results Per Page
 *
 * Number of search results to show per page
 *
 * Default: 50
 */
define('SEARCH_RESULTS_PER_PAGE', 50);

/**
 * Recent Activity Items
 *
 * Number of recent activity items to show on dashboard
 *
 * Default: 10
 */
define('RECENT_ACTIVITY_COUNT', 10);

/**
 * Date Format
 *
 * PHP date format for displaying dates
 * See: https://www.php.net/manual/en/function.date.php
 *
 * Examples:
 * - 'Y-m-d'           : 2026-01-15
 * - 'm/d/Y'           : 01/15/2026
 * - 'F j, Y'          : January 15, 2026
 * - 'd/m/Y'           : 15/01/2026 (European)
 */
define('DATE_FORMAT', 'Y-m-d');

/**
 * Time Format
 *
 * PHP time format for displaying times
 *
 * Examples:
 * - 'H:i:s'  : 14:30:00 (24-hour)
 * - 'g:i A'  : 2:30 PM (12-hour)
 */
define('TIME_FORMAT', 'H:i:s');

/**
 * DateTime Format
 *
 * PHP format for displaying date and time together
 */
define('DATETIME_FORMAT', 'Y-m-d H:i:s');

// =============================================================================
// RETENTION & ARCHIVAL
// =============================================================================

/**
 * Enable Automatic Archiving
 *
 * Automatically archive files based on retention policies
 *
 * true  : Enable automatic archiving
 * false : Require manual archiving
 */
define('ENABLE_AUTO_ARCHIVE', false);

/**
 * Archive Reason Required
 *
 * Require users to specify reason when archiving files
 *
 * true  : Require archive reason
 * false : Archive reason optional
 */
define('ARCHIVE_REASON_REQUIRED', true);

/**
 * Destruction Approval Required
 *
 * Require admin approval before files can be destroyed
 *
 * true  : Require approval (recommended)
 * false : Allow immediate destruction (not recommended)
 */
define('DESTRUCTION_APPROVAL_REQUIRED', true);

// =============================================================================
// API SETTINGS
// =============================================================================

/**
 * Enable API
 *
 * Enable REST API for external integrations
 *
 * true  : Enable API
 * false : Disable API
 */
define('ENABLE_API', false);

/**
 * API Rate Limit
 *
 * Maximum API requests per hour per API key
 * Set to 0 for unlimited
 *
 * Default: 1000
 */
define('API_RATE_LIMIT', 1000);

// =============================================================================
// ADVANCED SETTINGS
// =============================================================================

/**
 * Maintenance Mode
 *
 * Put application in maintenance mode (only admins can access)
 *
 * true  : Enable maintenance mode
 * false : Normal operation
 */
define('MAINTENANCE_MODE', false);

/**
 * Maintenance Message
 *
 * Message to display when in maintenance mode
 */
define('MAINTENANCE_MESSAGE', 'RoloDrawer is currently undergoing maintenance. Please check back soon.');

/**
 * Enable Webhooks
 *
 * Allow webhooks for event notifications
 *
 * true  : Enable webhooks
 * false : Disable webhooks
 */
define('ENABLE_WEBHOOKS', false);

/**
 * Audit Log Retention
 *
 * How long to keep audit logs (in days)
 * Set to 0 to keep forever
 *
 * Common values:
 * - 0    : Keep forever (default for compliance)
 * - 90   : 3 months
 * - 365  : 1 year
 * - 2555 : 7 years (common legal requirement)
 */
define('AUDIT_LOG_RETENTION_DAYS', 0);

/**
 * Custom Fields Enabled
 *
 * Allow administrators to create custom fields for files
 *
 * true  : Enable custom fields
 * false : Disable custom fields
 */
define('ENABLE_CUSTOM_FIELDS', true);

// =============================================================================
// DO NOT EDIT BELOW THIS LINE
// =============================================================================

/**
 * Version Information
 *
 * Current RoloDrawer version
 * Updated automatically during upgrades
 */
define('ROLODRAWER_VERSION', '1.0.2');
define('ROLODRAWER_DB_VERSION', '1.0.2');

/**
 * Application Paths
 *
 * Automatically calculated - do not modify
 */
define('APP_ROOT', dirname(__FILE__));
define('DATA_DIR', APP_ROOT . '/data');
define('LOGS_DIR', APP_ROOT . '/logs');

// Load environment-specific overrides if present
if (file_exists(APP_ROOT . '/config.local.php')) {
    require_once APP_ROOT . '/config.local.php';
}
