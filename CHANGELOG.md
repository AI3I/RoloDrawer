# RoloDrawer Changelog

All notable changes to RoloDrawer will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

---

## [1.0.5] - 2026-01-02

### Added
- **Tag Management**: Complete tag editing and deletion functionality
  - Edit tag name and color
  - Smart delete protection: prevents deleting tags in use
  - Delete button grayed out with tooltip for tags with files
  - Edit/Delete buttons added to tags list
  - Full CRUD operations for tags
- **System Settings Page**: New admin-only Settings page with backup/restore functionality
  - Create database backups with timestamps
  - List all existing backups with date and file size
  - Download backups securely
  - Delete old backups
  - Restore from backup file upload with validation
  - Automatic pre-restore backup creation
  - Auto-saved backups labeled for clarity
  - Security: Admin-only access, path validation, filename validation
- **Settings Navigation**: Added Settings link to admin navigation menu

### Enhanced
- **File Move Functionality**: Made file moves more robust with comprehensive update capabilities
  - Added entity assignment field to move forms
  - Implemented location/cabinet filtering with dynamic dropdown (matching create/edit forms)
  - Added vertical and horizontal position fields
  - Move handler now updates entity_id, positions, and cabinet in a single operation
  - Movement history only logged when cabinet actually changes
  - Success message indicates which attributes changed (location/entity)
  - Renamed action from "Move File" to "Update File Location" to reflect broader functionality

### Fixed
- **File Movements Page Display**: Fixed duplicate cabinet labels showing 3 times in movement history
  - Changed format to clean "Location > Cabinet Label" display
  - Removed duplicate SQL JOINs that were causing 500 errors
  - Fixed SELECT clause to avoid duplicate column selections

### Security
- **Backup/Restore Security**: Multiple security layers implemented
  - Admin-only access checks on all backup operations
  - Directory traversal prevention using basename()
  - Regex validation for backup filenames
  - Database validation before restore (checks for 'users' table)
  - Automatic pre-restore backup creation for safety

---

## [1.0.4] - 2026-01-02

### Changed
- **MAJOR REFACTOR**: Simplified file organization structure by removing drawer entities
  - Changed from Location → Cabinet → Drawer to Location → Cabinet with position tracking
  - Vertical position now represents which drawer (Top, Upper, Lower, Bottom)
  - Horizontal position represents position within drawer (Front, Center, Back)
  - File location structure is now: Location → Cabinet → Vertical Position → Horizontal Position
  - This eliminates redundancy where "which drawer" was tracked both as a drawer entity AND as vertical position

### Removed
- Drawer entities have been removed from the system
- Drawer management UI (create, edit, QR codes)
- Drawer statistics from dashboard
- All drawer-related database tables, columns, and foreign keys

### Added
- Migration script `migrate_remove_drawers.php` for existing installations
  - Automatically converts drawer assignments to cabinet + vertical position
  - Preserves all existing file locations during migration
  - Maps drawer positions to vertical positions (Top, Upper, Lower, Bottom)
  - Migrates movement history from drawers to cabinets

### Fixed
- File movement now tracks cabinet changes instead of drawer changes
- All location displays now show: Cabinet (Vertical/Horizontal)
- Reports updated to show cabinet + position instead of drawer
- SQL queries optimized by removing unnecessary drawer joins
- **QR Lookup functionality**: Fixed redirect issue by switching from PHP header() to JavaScript redirect
- **Storage page alignment**: Edit/Delete buttons now properly aligned horizontally
- **Input handling**: QR Lookup now trims whitespace from search inputs

### Improved
- **File view page UI enhancements**:
  - Enlarged file name heading (h2 text-2xl) for better visual hierarchy
  - Renamed "Proposed Location" to "New Destination" with green styling
  - File History now expanded by default for immediate visibility
  - Removed bold font from "Update File Location" buttons for cleaner appearance
- **Files list page**: Swapped View/Edit button colors (Edit=blue, View=green) for consistency with edit page
- **Destroy File UI**: Replaced collapsible form with always-visible simpler interface
  - Streamlined destruction method options
  - Clearer warning messages with admin-only badge
  - Better positioning after File History section
- **QR Lookup error messages**: Enhanced with character length display, case-sensitivity notice, and example file numbers

### Database Changes
- Removed `drawers` table
- Changed `files.current_drawer_id` to `files.current_cabinet_id`
- Changed `file_movements.from_drawer_id` to `file_movements.from_cabinet_id`
- Changed `file_movements.to_drawer_id` to `file_movements.to_cabinet_id`
- Added database index on `files.current_cabinet_id`

### Technical Details
This refactor addresses the architectural issue where vertical position (which drawer in a cabinet) was redundantly tracked in two places: as a separate drawer entity AND as the vertical_position field. The new simplified structure uses the vertical_position field to represent which drawer, while the cabinet entity represents the physical filing cabinet. This makes the data model clearer and reduces complexity throughout the application.

**Migration Path**: Existing installations should run `migrate_remove_drawers.php` ONCE after updating to v1.0.4. The script preserves all data and can safely be deleted after successful migration.

---

## [1.0.3] - 2026-01-02

### Fixed
- **CRITICAL**: Fixed session persistence issues preventing login on production servers
  - Implemented custom session directory (`storage/sessions`) with automatic creation
  - Added session cookie configuration for better browser compatibility (HttpOnly, SameSite=Lax)
  - Resolves issue where sessions weren't persisting between page requests
- **CRITICAL**: Fixed logout functionality
  - Moved logout logic before HTML output to allow proper header redirects
  - Logout now correctly destroys session and redirects to login page
  - Previously logout would clear content but leave sidebar visible
- Fixed hardcoded base URL in QR code generation
  - Changed `getBaseURL()` to automatically detect current protocol, host, and path
  - Application now works on any domain without code changes
- Fixed login form default email to match actual admin credentials (`admin@rolodrawer.local`)

### Added
- Login page icon matching main application (file cabinet emoji 🗂️)
- Database migration capability for adding missing schema columns on existing installations

### Technical Details
Production servers (particularly Plesk environments) often lack the default PHP session directory (`/var/lib/php/session`) or have restrictive permissions. The custom session directory ensures sessions work reliably across all hosting environments. Additionally, modern browsers require proper SameSite cookie attributes for session cookies to function correctly.

---

## [1.0.2] - 2026-01-02

### Fixed
- **CRITICAL**: Fixed database initialization race condition that prevented automatic database creation on first install
  - Added `isInitialized()` method to Database class to properly check if database has been set up
  - Updated index.php to use table existence check instead of file existence check
  - Resolves 500 error "no such table: tags" on fresh installations
  - Database now correctly initializes on first access as documented in README
- **CRITICAL**: Fixed default admin login credentials
  - Changed default admin username from 'admin' to 'admin@rolodrawer.local' (valid email format required by login form)
  - Updated schema.sql, README.md, and test_installation.php with correct email address
  - Password remains: RoloDrawer2026!

### Technical Details
The previous implementation checked if the database file existed before initializing, but PDO automatically creates an empty SQLite file when connecting. This meant the initialization check always failed, leaving users with an empty database file and 500 errors. The fix checks for the existence of database tables instead of the database file.

Additionally, the login form requires a valid email address, but the default admin user was created with username 'admin' instead of a proper email, preventing login with documented credentials.

---

## [1.0.1] - 2026-01-02

### Added
- Floor field to locations (Building → Floor → Room hierarchy)
- Installation validation and test scripts
- Acknowledgments section in README dedicated to Matthew Ferry

### Fixed
- **CRITICAL**: Avery label printing now correctly renders only labels (not entire page)
- Moved label print logic before main layout to prevent double rendering
- Corrected release date from future date (2026-01-15) to actual date (2026-01-02)
- Removed non-existent support contact information from documentation
- Updated all fake URLs (forums, knowledge base) to point to GitHub resources

### Changed
- Users menu item relocated above Change Password in navigation (below admin separator)
- Consolidated documentation URLs to GitHub repository
- Updated support sections to reflect open-source nature (no official support)

---

## [1.0.0] - 2026-01-02

### Initial Release

The first production-ready release of RoloDrawer, a comprehensive web-based filing cabinet management system.

### Added

#### Core Features
- **File Management System**
  - Create, read, update, and archive physical file records
  - Auto-incrementing file numbers with UUID backend tracking
  - File metadata including name, description, owner, and sensitivity level
  - Four sensitivity levels: Public, Internal, Confidential, Restricted
  - File status tracking (In Drawer, Checked Out, Archived, Destroyed)
  - Complete file history and audit trail
  - Bulk file operations (edit, move, archive)

#### Location Management
- **Physical Location Tracking**
  - Multi-level location hierarchy (Location > Cabinet > Drawer)
  - Location details with address and contact information
  - Cabinet management with customizable drawer configurations
  - Drawer labeling options (letters, numbers, or custom)
  - Visual location browser with tree view
  - Capacity tracking and warnings

#### Checkout System
- **File Checkout/Checkin Workflow**
  - Checkout files with expected return dates
  - Purpose and notes tracking for checkouts
  - Email reminders for upcoming due dates
  - Overdue file notifications
  - Checkout history for each file
  - "My Checkouts" dashboard for users
  - Quick return functionality

#### Search and Discovery
- **Advanced Search**
  - Quick search across file numbers, names, descriptions
  - Advanced search with multiple filter criteria
  - Search by location, cabinet, drawer
  - Filter by owner, sensitivity, status
  - Date range filtering
  - Tag-based search
  - Entity-based search
  - Saved searches for frequently used queries
  - Sort options (by number, name, date, location)

#### Tags and Cross-Referencing
- **Tag System**
  - Unlimited tags per file
  - Tag autocomplete
  - Tag categories for organization
  - Tag cloud visualization
  - Browse files by tag
  - Bulk tagging operations
  - Tag merging for standardization
  - Tag usage statistics

- **File Cross-Referencing**
  - Link related files together
  - Relationship types (Related to, Supersedes, Referenced in)
  - Bidirectional relationships
  - Visual relationship display
  - Quick navigation between related files

#### Entity Management
- **Entity System**
  - Create entities for vendors, clients, contractors, projects, etc.
  - Entity types: Vendor, Client, Contractor, Project, Department, Property, Other
  - Contact information tracking
  - Entity hierarchy (parent-child relationships)
  - Associate multiple files with entities
  - Browse files by entity
  - Entity merge functionality
  - Entity status tracking (Active, Inactive, Archived)

#### QR Code and Label Printing
- **Label Generation**
  - QR code generation for each file
  - Barcode generation (Code 128, Code 39)
  - Individual label printing
  - Batch label printing with templates
  - Avery label template support (5160, 5161, 5162)
  - Customizable label content (name, location, sensitivity)
  - Printable PDF generation
  - QR code scanning for quick file lookup
  - Mobile-friendly QR scanner interface

#### Archive and Destruction
- **Archive Workflow**
  - Soft archive with reason tracking
  - Archive reasons (Project completed, Retention expired, Superseded, Other)
  - Archived files remain searchable with filter
  - Un-archive functionality
  - Bulk archive operations
  - Automatic archiving based on retention rules

- **Destruction Workflow**
  - Formal destruction request system
  - Admin approval required for destruction
  - Destruction methods tracking (Shredding, Incineration, Pulping, etc.)
  - Destruction certification
  - Permanent audit log of destroyed files
  - Witness tracking for sensitive destructions
  - Bulk destruction support
  - Compliance reporting

#### Reporting and Analytics
- **Built-in Reports**
  - Inventory Report (all files and locations)
  - Checkout Report (currently checked out files)
  - Overdue Report (files past due date)
  - Movement History Report
  - Activity Report (all user actions)
  - Files by Entity Report
  - Sensitivity Classification Report
  - Archive Report
  - Destruction Log Report
  - Retention Compliance Report

- **Report Features**
  - Customizable filters and date ranges
  - Export to PDF, Excel, CSV
  - Scheduled report generation
  - Email delivery of reports
  - Visual charts and graphs
  - Print-friendly formats

#### User Management
- **Authentication and Authorization**
  - Secure user registration and login
  - Three user roles: Viewer, User, Administrator
  - Role-based permissions
  - Password strength requirements
  - Password expiration policies
  - Account activation/deactivation
  - Failed login attempt tracking and lockout
  - Password reset via email
  - "Remember me" functionality
  - Session timeout for security

- **User Administration**
  - Create, edit, delete user accounts
  - Bulk user import from CSV
  - User profile management
  - Department assignment
  - User activity tracking
  - Email notifications to users

#### Security Features
- **Data Protection**
  - Role-based access control
  - Sensitivity-based file restrictions
  - Secure session management
  - HTTPS enforcement
  - Security headers (X-Frame-Options, X-XSS-Protection, etc.)
  - CSRF protection
  - SQL injection prevention (prepared statements)
  - XSS protection (input sanitization)
  - Password hashing (bcrypt)
  - Optional file upload encryption
  - Optional database encryption

- **Audit Trail**
  - Complete action logging
  - User login/logout tracking
  - File creation, modification, deletion logging
  - Movement history
  - Checkout/return logging
  - Permission change tracking
  - Configuration change logging
  - Exportable audit logs
  - Configurable log retention

#### System Administration
- **Admin Dashboard**
  - System health monitoring
  - Quick statistics overview
  - Recent activity feed
  - Pending actions notifications
  - User management interface
  - System settings configuration

- **Maintenance Tools**
  - Database backup and restore
  - Manual and automated backup scheduling
  - Database optimization tools
  - Cache management
  - System update checker
  - Error log viewer
  - Performance monitoring
  - Slow query detection

- **Configuration**
  - Customizable system settings
  - Email configuration (SMTP)
  - Notification settings
  - Retention policy management
  - Legal hold management
  - Custom fields for files
  - API access configuration
  - Webhook configuration

#### Integration and API
- **API Access**
  - RESTful API for external integrations
  - API key authentication
  - Rate limiting
  - API usage monitoring
  - Webhook support for events
  - JSON responses

#### User Interface
- **Modern, Responsive Design**
  - Mobile-friendly responsive layout
  - Clean, intuitive interface
  - Dashboard with customizable widgets
  - Breadcrumb navigation
  - Contextual help system
  - Keyboard shortcuts
  - Dark/light theme support
  - Accessibility features (WCAG 2.1 compliant)
  - Browser support: Chrome, Firefox, Safari, Edge (latest versions)

- **User Experience Enhancements**
  - Auto-save for forms
  - Drag-and-drop file uploads
  - Inline editing
  - Real-time search suggestions
  - Confirmation dialogs for destructive actions
  - Toast notifications for actions
  - Progress indicators for long operations
  - Pagination for large datasets

#### Database Support
- **Multiple Database Options**
  - SQLite (default for easy setup)
  - MySQL 5.7+
  - PostgreSQL 12+
  - Database migrations for version updates
  - Automatic schema creation
  - Sample data loader

#### Deployment
- **Flexible Hosting**
  - Works on shared hosting (Plesk, cPanel)
  - VPS/dedicated server support
  - Apache and Nginx support
  - Docker container support
  - URL rewriting for clean URLs
  - .htaccess included for Apache
  - nginx.conf examples provided

#### Documentation
- **Comprehensive Documentation**
  - Installation Guide (INSTALLATION.md)
  - User Guide (USER_GUIDE.md)
  - Administrator Guide (ADMIN_GUIDE.md)
  - This Changelog
  - Setup Wizard for first-time installation
  - In-app help system
  - Video tutorials (planned)
  - API documentation (planned)

### Technical Specifications

#### System Requirements
- PHP 7.4 or higher (8.0+ recommended)
- Web server (Apache 2.4+ or Nginx 1.18+)
- Database (SQLite 3.8+ / MySQL 5.7+ / PostgreSQL 12+)
- 512MB RAM minimum (1GB+ recommended)
- 100MB disk space + storage for uploads

#### PHP Extensions Required
- PDO (with appropriate database driver)
- mbstring
- OpenSSL
- JSON
- GD or Imagick
- Session support
- cURL (optional)

#### Security
- Follows OWASP security best practices
- Regular security updates planned
- Vulnerability reporting process
- Security advisory notifications

### Known Limitations

- Maximum file upload size limited by PHP configuration
- SQLite recommended for <10,000 files (use MySQL/PostgreSQL for larger installations)
- QR code scanning requires HTTPS for browser camera access
- Email notifications require SMTP server configuration
- Report generation for very large datasets may be slow

### Roadmap for Future Versions

#### Planned for v1.1 (Q2 2026)
- Advanced barcode scanner integration
- Mobile native apps (iOS/Android)
- Advanced reporting with custom report builder
- Workflow automation rules
- Document scanning integration
- Email-to-file functionality

#### Planned for v1.2 (Q3 2026)
- Multi-language support (i18n)
- LDAP/Active Directory integration
- SSO support (SAML, OAuth)
- Advanced permissions (field-level)
- File versioning
- Document preview

#### Under Consideration
- Integration with document management systems
- Cloud storage backend support (S3, Azure Blob)
- Electronic signature integration
- Retention policy automation
- Machine learning for auto-tagging
- Blockchain for immutable audit trail

### Acknowledgments

RoloDrawer was designed to solve the real-world problem of tracking physical file folders in organizations. Thank you to all beta testers and early adopters who provided valuable feedback.

### License

Copyright (c) 2026 RoloDrawer Development Team. All rights reserved.

See LICENSE.txt for licensing information.

### Support

- Documentation: https://docs.rolodrawer.com
- Issue Tracker: https://github.com/yourusername/rolodrawer/issues
- Email Support: support@example.com
- Community Forum: https://forum.example.com/rolodrawer

---

## Version History

- **[1.0.0] - 2026-01-02**: Initial release

---

*For detailed information about using RoloDrawer, see USER_GUIDE.md and ADMIN_GUIDE.md*
*For installation instructions, see INSTALLATION.md*
