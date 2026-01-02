# RoloDrawer Administrator Guide

## Table of Contents
1. [Administrator Overview](#administrator-overview)
2. [User Management](#user-management)
3. [Location Management](#location-management)
4. [Cabinet and Drawer Management](#cabinet-and-drawer-management)
5. [Entity Management](#entity-management)
6. [Tag Management](#tag-management)
7. [Archive and Destruction Workflows](#archive-and-destruction-workflows)
8. [Backup and Restore](#backup-and-restore)
9. [Security Best Practices](#security-best-practices)
10. [System Maintenance](#system-maintenance)
11. [Audit and Compliance](#audit-and-compliance)
12. [Troubleshooting](#troubleshooting)

---

## Administrator Overview

As a RoloDrawer administrator, you are responsible for:
- Managing user accounts and permissions
- Maintaining organizational structure (locations, cabinets, drawers)
- Overseeing data quality and integrity
- Performing backups and system maintenance
- Ensuring security and compliance
- Generating reports for management
- Training users and providing support

### Admin Dashboard

Access admin features from the **Admin** menu (visible only to administrators):

```
┌────────────────────────────────────────────────┐
│  Admin Dashboard                               │
├────────────────────────────────────────────────┤
│  System Health                                 │
│  ✓ Database: Healthy (12.5 MB)                │
│  ✓ Last Backup: 2 hours ago                   │
│  ✓ Active Users: 12 of 25 licenses            │
│  ⚠ Overdue Checkouts: 3 files                │
│                                                │
│  Quick Stats (Last 30 Days)                    │
│  • Files Created: 28                           │
│  • Files Moved: 45                             │
│  • Checkouts: 67                               │
│  • New Users: 2                                │
│                                                │
│  Pending Actions                               │
│  • 2 new user registration requests            │
│  • 1 file destruction request pending approval │
│  • System update available (v1.0.1)            │
└────────────────────────────────────────────────┘
```

---

## User Management

### User Roles and Permissions

RoloDrawer has three role levels:

#### Viewer
**Can:**
- Search and view files
- View locations, cabinets, drawers
- View reports (limited)
- View their own profile

**Cannot:**
- Create or edit files
- Checkout files
- Modify any data
- Access admin functions

#### User
**Can:**
- Everything a Viewer can do
- Create new files
- Edit files they own
- Checkout and checkin files
- Add tags and cross-references
- Create entities
- Generate all reports

**Cannot:**
- Edit files they don't own (unless granted permission)
- Access admin functions
- Manage users
- Permanently delete files

#### Administrator
**Can:**
- Everything Users can do
- Create, edit, delete user accounts
- Manage all system settings
- Edit any file regardless of owner
- Manage locations, cabinets, drawers
- Approve file destructions
- Access audit logs
- Perform system maintenance
- Full report access

### Creating User Accounts

1. Go to **Admin** > **Users** > **Add User**
2. Fill in user details:

```
┌────────────────────────────────────────┐
│ Create New User                        │
├────────────────────────────────────────┤
│ Username: [__________]                 │
│ Email: [__________]                    │
│ Full Name: [__________]                │
│                                        │
│ Role: [User ▼]                         │
│                                        │
│ Department: [__________] (optional)    │
│                                        │
│ Password:                              │
│ ○ Auto-generate and email to user     │
│ ○ Set password: [__________]           │
│                                        │
│ ☐ Require password change on login    │
│ ☐ Account active                      │
│                                        │
│      [Cancel]        [Create User]     │
└────────────────────────────────────────┘
```

3. Choose whether to auto-generate password or set one
4. User receives email with login credentials
5. User must change password on first login

### Editing User Accounts

1. Go to **Admin** > **Users**
2. Click on username to edit
3. Modify:
   - Email address
   - Full name
   - Department
   - Role (promote/demote)
   - Active status
4. Click **Save Changes**

### Resetting User Passwords

**As Administrator:**
1. Go to **Admin** > **Users**
2. Click on username
3. Click **Reset Password**
4. Choose:
   - Auto-generate and email new password
   - Set specific password
5. Check "Require password change on next login"
6. Confirm reset

**Self-Service Reset:**
Users can reset their own passwords:
1. From login page: "Forgot Password?"
2. Enter email address
3. Receive reset link via email
4. Link expires in 24 hours

### Deactivating User Accounts

Instead of deleting, deactivate users who leave:

1. Go to **Admin** > **Users**
2. Click on username
3. Uncheck **Account Active**
4. Choose what to do with their files:
   - Reassign to another user
   - Leave as-is (still searchable)
   - Transfer ownership to you
5. Save changes

**Deactivated users:**
- Cannot log in
- Files remain in system
- Checkout history preserved
- Can be reactivated if needed

### Bulk User Import

Import multiple users from CSV:

1. Go to **Admin** > **Users** > **Import Users**
2. Download CSV template
3. Fill in user data:
   ```csv
   username,email,full_name,role,department
   jsmith,john.smith@company.com,John Smith,user,Finance
   jdoe,jane.doe@company.com,Jane Doe,user,Legal
   ```
4. Upload CSV file
5. Review import preview
6. Confirm import
7. Users receive welcome emails

---

## Location Management

Locations represent physical buildings or facilities where files are stored.

### Creating Locations

1. Go to **Admin** > **Locations** > **Add Location**
2. Fill in details:

```
┌────────────────────────────────────────┐
│ Add Location                           │
├────────────────────────────────────────┤
│ Name: [__________]                     │
│ Description: [__________]              │
│                                        │
│ Address:                               │
│ Street: [__________]                   │
│ City: [__________]                     │
│ State: [__]  ZIP: [_____]              │
│                                        │
│ Contact Information:                   │
│ Contact Name: [__________]             │
│ Phone: [__________]                    │
│ Email: [__________]                    │
│                                        │
│ Notes: [___________________________]   │
│                                        │
│      [Cancel]      [Create Location]   │
└────────────────────────────────────────┘
```

**Example:**
- **Name**: Building A - Main Office
- **Description**: Main corporate office building
- **Address**: 123 Main Street, Suite 200, Anytown, CA 90210
- **Contact**: Jane Smith (Facilities), (555) 123-4567

### Managing Locations

**View all locations:**
- Go to **Locations** > **All Locations**
- See list with cabinet counts
- Click to view details or edit

**Edit location:**
- Update address, contact, or description
- Location name can be changed (files are automatically updated)

**Delete location:**
- Only if no cabinets are assigned
- Otherwise, must reassign or delete cabinets first

---

## Cabinet and Drawer Management

### Creating Cabinets

1. Go to **Admin** > **Cabinets** > **Add Cabinet**
2. Fill in cabinet information:

```
┌────────────────────────────────────────┐
│ Add Cabinet                            │
├────────────────────────────────────────┤
│ Cabinet ID: [__________]               │
│             (e.g., CAB-001, FC-A-12)   │
│                                        │
│ Location: [Building A ▼]               │
│                                        │
│ Description: [__________]              │
│                                        │
│ Number of Drawers: [4 ▼]               │
│                                        │
│ Drawer Labels:                         │
│ ○ Letters (A, B, C, D...)              │
│ ○ Numbers (1, 2, 3, 4...)              │
│ ○ Custom (specify below)               │
│                                        │
│ Custom Labels (comma-separated):       │
│ [Top, Upper, Lower, Bottom]            │
│                                        │
│      [Cancel]      [Create Cabinet]    │
└────────────────────────────────────────┘
```

3. Drawers are automatically created based on your settings
4. Cabinet appears in the system with all drawers ready

**Cabinet ID Best Practices:**
- Use consistent naming scheme
- Include location identifier (e.g., BLD-A-CAB-001)
- Use leading zeros for sorting (CAB-001, not CAB-1)
- Consider including department or area

### Managing Drawers

**View cabinet drawers:**
1. Go to **Cabinets** > click cabinet ID
2. See all drawers with file counts
3. Click drawer to see all files inside

**Edit drawer:**
- Change drawer label
- Add description
- Mark as out of service

**Drawer capacity:**
- Set maximum file count (optional)
- System warns when drawer approaches capacity
- Helps with physical space planning

### Moving Cabinets

When physically relocating a cabinet:

1. Go to **Cabinets** > click cabinet ID
2. Click **Move Cabinet**
3. Select new location
4. Add reason/notes
5. Confirm move
6. All files in cabinet are automatically updated
7. Movement logged in audit trail

### Decommissioning Cabinets

Before removing a cabinet:

1. Ensure all files are removed or reassigned
2. Go to **Cabinets** > click cabinet ID
3. If files remain, use **Bulk Reassign**:
   - Select destination cabinet/drawer
   - Confirm reassignment
   - All files moved at once
4. Once empty, click **Delete Cabinet**
5. Drawers are automatically deleted

---

## Entity Management

Entities represent organizations, people, or projects that files relate to.

### Creating Entities

1. Go to **Admin** > **Entities** > **Add Entity**
2. Fill in entity information:

```
┌────────────────────────────────────────┐
│ Add Entity                             │
├────────────────────────────────────────┤
│ Name: [__________]                     │
│                                        │
│ Type: [Vendor ▼]                       │
│       Options: Vendor, Client,         │
│       Contractor, Project, Department, │
│       Property, Other                  │
│                                        │
│ Description: [___________________]     │
│                                        │
│ Contact Information:                   │
│ Primary Contact: [__________]          │
│ Email: [__________]                    │
│ Phone: [__________]                    │
│ Website: [__________]                  │
│                                        │
│ Address:                               │
│ [_________________________________]    │
│                                        │
│ Tags: [__________]                     │
│                                        │
│ Status:                                │
│ ○ Active  ○ Inactive  ○ Archived      │
│                                        │
│      [Cancel]      [Create Entity]     │
└────────────────────────────────────────┘
```

**Example - Vendor Entity:**
- **Name**: ACME Corporation
- **Type**: Vendor
- **Description**: Primary IT services and equipment vendor
- **Contact**: Sarah Johnson, contracts@acme.com, (555) 987-6543
- **Tags**: IT Services, Hardware, Active

### Managing Entities

**View all entities:**
- Go to **Entities** > **All Entities**
- Filter by type or status
- See file count for each

**Edit entity:**
- Update contact information
- Change status
- Modify description
- Add/remove tags

**Merge entities:**
When duplicates exist:
1. Go to **Admin** > **Entities** > **Merge Entities**
2. Select primary entity (to keep)
3. Select duplicate entity (to merge from)
4. Preview merge (shows all files that will be reassigned)
5. Confirm merge
6. Duplicate entity is deleted
7. All files now associated with primary entity

### Entity Hierarchy

Create parent-child relationships:

**Example:**
- ACME Corporation (parent)
  - ACME IT Division (child)
  - ACME Consulting (child)

**To create:**
1. Edit child entity
2. Set "Parent Entity" field
3. Save

Files can be associated with child entity but appear when viewing parent.

---

## Tag Management

### Viewing All Tags

Go to **Admin** > **Tags** to see:
- All tags in system
- Number of files per tag
- Tag creation date
- Created by whom

### Tag Standardization

Prevent tag proliferation:

1. Go to **Admin** > **Tags** > **Tag Rules**
2. Enable **Require Approval for New Tags**
3. Users can suggest tags, but admins must approve
4. Review pending tags regularly

### Merging Tags

Combine duplicate or similar tags:

1. Go to **Admin** > **Tags** > **Merge Tags**
2. Select primary tag (e.g., "Contracts")
3. Select tags to merge (e.g., "Contract", "Agreement")
4. Preview affected files
5. Confirm merge
6. All files retagged with primary tag

### Bulk Tagging

Apply tags to multiple files at once:

1. Go to **Files** > **All Files**
2. Use filters to find target files
3. Select files (checkboxes)
4. Click **Actions** > **Add Tags**
5. Enter tags to add
6. Confirm

**Example use cases:**
- Tag all files in a location with location name
- Tag files by department
- Add year tags to old files

### Tag Categories

Create tag categories for organization:

**System Default Categories:**
- Department
- Document Type
- Year
- Status
- Entity
- Project

**Custom categories:**
1. Go to **Admin** > **Tags** > **Categories**
2. Add new category
3. Assign existing tags to categories
4. Tags can belong to multiple categories

Benefits:
- Helps users find appropriate tags
- Enforces tagging standards
- Improves search filtering

---

## Archive and Destruction Workflows

### File Archiving

#### Manual Archive

1. Open file detail page
2. Click **Archive**
3. Select reason:
   - Project completed
   - Retention period expired
   - Superseded by newer version
   - Consolidated into another file
   - Other (specify)
4. Add notes
5. Confirm archive

#### Bulk Archive

For archiving multiple files:

1. Go to **Files** > **Advanced Search**
2. Filter by criteria (e.g., date created before 2020)
3. Select files to archive
4. Click **Actions** > **Archive Selected**
5. Choose archive reason
6. Confirm

**Archived files:**
- Marked with "Archived" badge
- Not shown in default searches (unless "Include Archived" checked)
- Cannot be checked out
- Remain fully searchable
- Can be un-archived if needed

#### Automatic Archiving

Set up rules for automatic archiving:

1. Go to **Admin** > **Settings** > **Archive Rules**
2. Create rule:
   - **Condition**: Files older than X years
   - **Action**: Archive automatically
   - **Reason**: Retention period expired
3. Rules run nightly
4. Email report sent to admins

**Example rules:**
- Archive files created >7 years ago
- Archive files tagged "Temporary" after 1 year
- Archive files for completed projects

### File Destruction

**IMPORTANT**: Destruction is permanent. Use with caution.

#### Destruction Workflow

File destruction requires approval workflow:

**Step 1: Request Destruction**
1. User opens file detail page
2. Clicks **Request Destruction**
3. Fills out destruction request:
   - Reason for destruction
   - Retention policy reference (if applicable)
   - Proposed destruction method
   - Confirmation statement
4. Submits request

**Step 2: Admin Review**
1. Admin receives notification
2. Goes to **Admin** > **Destruction Requests**
3. Reviews request details
4. Verifies:
   - Retention requirements met
   - No pending legal holds
   - Proper authorization
5. Approves or denies

**Step 3: Physical Destruction**
1. If approved, file appears in **Pending Destruction** queue
2. Admin or designee:
   - Retrieves physical file
   - Destroys using approved method (shred, burn, etc.)
   - Returns to RoloDrawer
   - Marks **Destruction Completed**
   - Records:
     - Destruction date
     - Destruction method
     - Witness (if required)
     - Certificate number (if applicable)

**Step 4: System Update**
1. File marked as destroyed
2. Moved to **Destruction Log** (permanent audit record)
3. File data removed from active database
4. Audit record retained indefinitely

#### Destruction Methods

Configure approved methods:

1. Go to **Admin** > **Settings** > **Destruction Methods**
2. Add approved methods:
   - Cross-cut shredding
   - Incineration
   - Pulping
   - Digital wiping (for electronic media)
   - Certified destruction service

3. Assign methods to sensitivity levels:
   - Public: Any method
   - Internal: Shredding or better
   - Confidential: Cross-cut shredding or incineration
   - Restricted: Witnessed incineration with certificate

#### Bulk Destruction

For destroying multiple files:

1. Go to **Files** > **Advanced Search**
2. Filter by destruction criteria
3. Select files
4. Click **Actions** > **Request Bulk Destruction**
5. Fill out bulk destruction form
6. Submit for approval
7. Follow same approval workflow

#### Destruction Reports

Generate reports for compliance:

1. **Destruction Log**: All destroyed files with details
2. **Pending Destructions**: Files approved but not yet destroyed
3. **Destruction Certificates**: Official certificates for auditors

---

## Backup and Restore

### Backup Strategy

Implement a comprehensive backup strategy:

#### What to Backup

1. **Database**: All file metadata, users, locations
2. **Uploaded Files**: Any documents stored in `/uploads/`
3. **Configuration**: `config.php` and settings
4. **QR Codes**: Generated QR code images (optional, can be regenerated)

#### Backup Frequency

Recommended schedule:
- **Daily**: Automated database backups (keep 7 days)
- **Weekly**: Full system backup (keep 4 weeks)
- **Monthly**: Long-term archive backup (keep 12 months)
- **Before Updates**: Manual backup before system updates

### Manual Backup

#### Via Web Interface

1. Go to **Admin** > **System** > **Backup**
2. Choose backup type:
   - Database only
   - Full system (database + files)
3. Click **Create Backup**
4. Wait for completion (progress bar shown)
5. Download backup file
6. Store securely offsite

#### Via Command Line

**SQLite database:**
```bash
# Backup database
sqlite3 data/rolodrawer.db ".backup data/backup_$(date +%Y%m%d).db"

# Or copy file
cp data/rolodrawer.db backups/rolodrawer_$(date +%Y%m%d).db
```

**MySQL database:**
```bash
# Backup database
mysqldump -u rolodrawer -p rolodrawer > backups/rolodrawer_$(date +%Y%m%d).sql

# Include routines and triggers
mysqldump -u rolodrawer -p --routines --triggers rolodrawer > backups/rolodrawer_full_$(date +%Y%m%d).sql
```

**Full system backup:**
```bash
#!/bin/bash
# Backup script

DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backup/rolodrawer"
APP_DIR="/var/www/html/rolodrawer"

# Create backup directory
mkdir -p $BACKUP_DIR

# Backup database
cp $APP_DIR/data/rolodrawer.db $BACKUP_DIR/db_$DATE.db

# Backup uploads
tar -czf $BACKUP_DIR/uploads_$DATE.tar.gz $APP_DIR/uploads/

# Backup config
cp $APP_DIR/config.php $BACKUP_DIR/config_$DATE.php

# Create full archive
tar -czf $BACKUP_DIR/full_backup_$DATE.tar.gz \
  $BACKUP_DIR/db_$DATE.db \
  $BACKUP_DIR/uploads_$DATE.tar.gz \
  $BACKUP_DIR/config_$DATE.php

# Clean up individual files
rm $BACKUP_DIR/db_$DATE.db
rm $BACKUP_DIR/uploads_$DATE.tar.gz
rm $BACKUP_DIR/config_$DATE.php

# Delete backups older than 30 days
find $BACKUP_DIR -name "full_backup_*.tar.gz" -mtime +30 -delete

echo "Backup completed: $BACKUP_DIR/full_backup_$DATE.tar.gz"
```

### Automated Backups

#### Using Cron (Linux)

```bash
# Edit crontab
crontab -e

# Add daily backup at 2 AM
0 2 * * * /usr/local/bin/rolodrawer_backup.sh

# Add weekly full backup on Sunday at 3 AM
0 3 * * 0 /usr/local/bin/rolodrawer_full_backup.sh
```

#### Using Task Scheduler (Windows)

1. Open Task Scheduler
2. Create new task
3. Set trigger (daily at 2 AM)
4. Set action: Run backup script
5. Save task

#### Backup Verification

Test backups regularly:

1. Monthly, attempt restore to test environment
2. Verify all data is present
3. Test file uploads
4. Confirm user logins work
5. Document any issues

### Restore Procedures

#### Database Restore

**SQLite:**
```bash
# Stop web server
sudo systemctl stop apache2

# Backup current database (just in case)
cp data/rolodrawer.db data/rolodrawer.db.before_restore

# Restore from backup
cp backups/rolodrawer_20260115.db data/rolodrawer.db

# Set permissions
chown www-data:www-data data/rolodrawer.db
chmod 664 data/rolodrawer.db

# Start web server
sudo systemctl start apache2
```

**MySQL:**
```bash
# Drop and recreate database
mysql -u root -p
DROP DATABASE rolodrawer;
CREATE DATABASE rolodrawer CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EXIT;

# Restore from backup
mysql -u rolodrawer -p rolodrawer < backups/rolodrawer_20260115.sql
```

#### Full System Restore

```bash
# Extract backup
cd /tmp
tar -xzf /backup/rolodrawer/full_backup_20260115.tar.gz

# Stop web server
sudo systemctl stop apache2

# Backup current state
mv /var/www/html/rolodrawer /var/www/html/rolodrawer.old

# Restore files
# (Extract and copy database, uploads, config)

# Set permissions
sudo chown -R www-data:www-data /var/www/html/rolodrawer
sudo chmod -R 755 /var/www/html/rolodrawer

# Start web server
sudo systemctl start apache2

# Test system
# Verify login, search, file access
```

#### Point-in-Time Recovery

For critical data loss, restore to specific point:

1. Restore most recent backup
2. If using MySQL with binary logging:
   ```bash
   # Replay binary logs to specific timestamp
   mysqlbinlog --start-datetime="2026-01-15 14:30:00" \
               --stop-datetime="2026-01-15 14:45:00" \
               mysql-bin.000001 | mysql -u root -p rolodrawer
   ```

### Offsite Backup Storage

Store backups securely offsite:

#### Cloud Storage Options

**AWS S3:**
```bash
# Upload backup to S3
aws s3 cp full_backup_20260115.tar.gz s3://company-backups/rolodrawer/

# With encryption
aws s3 cp full_backup_20260115.tar.gz s3://company-backups/rolodrawer/ \
  --sse AES256
```

**rsync to remote server:**
```bash
# Sync to remote backup server
rsync -avz --delete /backup/rolodrawer/ \
  backupuser@backup.company.com:/backups/rolodrawer/
```

#### Encryption

Encrypt backups before offsite storage:

```bash
# Encrypt backup with GPG
gpg --symmetric --cipher-algo AES256 full_backup_20260115.tar.gz

# Creates: full_backup_20260115.tar.gz.gpg

# Decrypt when needed
gpg --decrypt full_backup_20260115.tar.gz.gpg > full_backup_20260115.tar.gz
```

---

## Security Best Practices

### Access Control

#### Password Policies

Configure strong password requirements:

1. Go to **Admin** > **Settings** > **Security**
2. Set password policy:
   - Minimum length: 12 characters
   - Require uppercase letters
   - Require lowercase letters
   - Require numbers
   - Require special characters
   - Password expiration: 90 days
   - Password history: Cannot reuse last 5 passwords

#### Session Management

Configure session security:

```php
// In config.php
$config['session_timeout'] = 3600; // 1 hour
$config['session_secure'] = true; // HTTPS only
$config['session_httponly'] = true; // No JavaScript access
$config['remember_me_duration'] = 604800; // 7 days
```

#### Two-Factor Authentication (2FA)

Enable 2FA for administrators:

1. Go to **Admin** > **Settings** > **Security**
2. Enable **Require 2FA for Administrators**
3. Choose 2FA method:
   - TOTP (Google Authenticator, Authy)
   - Email codes
   - SMS codes
4. Admins will be prompted to set up on next login

### Audit Logging

#### What Gets Logged

RoloDrawer logs:
- User logins/logouts
- Failed login attempts
- File creation, edits, deletions
- File movements
- Checkouts and returns
- Permission changes
- Configuration changes
- User account changes
- Archive/destruction actions

#### Viewing Audit Logs

1. Go to **Admin** > **Audit Log**
2. Filter by:
   - Date range
   - User
   - Action type
   - File or entity
3. Export logs for external analysis

#### Log Retention

Configure log retention:

1. Go to **Admin** > **Settings** > **Logging**
2. Set retention period:
   - Standard logs: 90 days
   - Security logs: 1 year
   - Destruction logs: Indefinite
3. Logs are automatically archived and compressed

### File Sensitivity Enforcement

#### Sensitivity-Based Restrictions

Configure access rules by sensitivity:

1. Go to **Admin** > **Settings** > **Sensitivity Rules**
2. For each level, set:

**Restricted:**
- Viewable by: Admins and owner only
- Editable by: Owner only
- Checkout requires: Admin approval
- Print labels: Includes "RESTRICTED" watermark

**Confidential:**
- Viewable by: Admins, owner, and specific departments
- Editable by: Owner and admins
- Checkout: Logged with reason required

**Internal:**
- Viewable by: All authenticated users
- Editable by: Owner and admins
- Checkout: Standard process

**Public:**
- Viewable by: Everyone (if public access enabled)
- Editable by: Owner and admins
- Checkout: Simplified process

### Data Encryption

#### Database Encryption

**SQLite:**
```bash
# Use SQLCipher for encrypted database
# Install SQLCipher
sudo apt install sqlcipher

# Encrypt existing database
sqlcipher data/rolodrawer.db
> ATTACH DATABASE 'data/rolodrawer_encrypted.db' AS encrypted KEY 'your-encryption-key';
> SELECT sqlcipher_export('encrypted');
> DETACH DATABASE encrypted;
```

**MySQL:**
Enable encryption at rest:
```sql
-- In my.cnf
[mysqld]
early-plugin-load=keyring_file.so
keyring_file_data=/var/lib/mysql-keyring/keyring

-- Create encrypted tablespace
CREATE TABLESPACE encrypted_space ENCRYPTION='Y';
ALTER TABLE files TABLESPACE encrypted_space;
```

#### File Upload Encryption

Encrypt uploaded files:

1. Go to **Admin** > **Settings** > **File Storage**
2. Enable **Encrypt Uploaded Files**
3. Choose encryption method:
   - AES-256-CBC
   - AES-256-GCM (recommended)
4. Set encryption key (store securely!)
5. Files are encrypted on upload, decrypted on download

### IP Restrictions

Limit access by IP address:

1. Go to **Admin** > **Settings** > **Security**
2. Enable **IP Whitelist**
3. Add allowed IP addresses or ranges:
   ```
   192.168.1.0/24
   10.0.0.0/8
   203.0.113.50
   ```
4. Admins can always access from any IP
5. Regular users restricted to whitelist

### Security Monitoring

#### Failed Login Alerts

Configure alerting:

1. Go to **Admin** > **Settings** > **Alerts**
2. Enable **Failed Login Alerts**
3. Set threshold: 5 failed attempts in 15 minutes
4. Actions:
   - Email admin
   - Lock account temporarily
   - Require CAPTCHA for that IP

#### Suspicious Activity Detection

Monitor for:
- Mass file exports
- Rapid checkouts
- Permission changes outside business hours
- Access from new locations/devices
- Bulk deletions

Configure alerts to notify admins immediately.

---

## System Maintenance

### System Health Checks

#### Database Optimization

**SQLite:**
```bash
# Vacuum database (reclaim space, optimize)
sqlite3 data/rolodrawer.db "VACUUM;"

# Analyze for query optimization
sqlite3 data/rolodrawer.db "ANALYZE;"

# Check integrity
sqlite3 data/rolodrawer.db "PRAGMA integrity_check;"
```

**MySQL:**
```sql
-- Optimize tables
OPTIMIZE TABLE files, users, locations, cabinets, drawers;

-- Analyze for query optimization
ANALYZE TABLE files, users, locations;

-- Check and repair
CHECK TABLE files;
REPAIR TABLE files;
```

Run weekly via cron:
```bash
# Crontab entry
0 4 * * 0 /usr/local/bin/optimize_database.sh
```

#### Cache Clearing

Clear application cache:

1. **Via Web Interface**:
   - Go to **Admin** > **System** > **Clear Cache**
   - Select cache types to clear
   - Confirm

2. **Via Command Line**:
   ```bash
   # Clear all caches
   rm -rf cache/*

   # Clear specific cache
   rm -rf cache/views/*
   rm -rf cache/data/*
   ```

### Software Updates

#### Checking for Updates

1. Go to **Admin** > **System** > **Updates**
2. Click **Check for Updates**
3. View available updates:
   - Version number
   - Release date
   - Change log
   - Security fixes included

#### Applying Updates

**Pre-update checklist:**
- [ ] Create full system backup
- [ ] Review change log
- [ ] Schedule maintenance window
- [ ] Notify users of downtime

**Update process:**
1. Enable maintenance mode
2. Download update package
3. Extract to temporary directory
4. Run update script
5. Test critical functions
6. Disable maintenance mode
7. Monitor for issues

**Command line update:**
```bash
# Enable maintenance mode
touch maintenance.flag

# Backup first
./backup.sh

# Download update
wget https://releases.rolodrawer.com/updates/v1.0.1.zip

# Extract and apply
unzip v1.0.1.zip
./update.sh

# Disable maintenance mode
rm maintenance.flag
```

### Performance Monitoring

#### System Metrics

Monitor key metrics:

1. Go to **Admin** > **System** > **Performance**
2. View:
   - Database size and growth
   - Active users
   - Average response time
   - Peak usage times
   - Storage usage
   - Failed jobs/errors

#### Query Performance

Identify slow queries:

1. Enable query logging:
   ```php
   // In config.php
   $config['log_slow_queries'] = true;
   $config['slow_query_threshold'] = 1000; // 1 second
   ```

2. Review slow query log:
   - Go to **Admin** > **System** > **Slow Queries**
   - See queries taking >1 second
   - Identify optimization opportunities

3. Add indexes if needed:
   ```sql
   -- Example: Index on file owner
   CREATE INDEX idx_files_owner ON files(owner_id);

   -- Index on cabinet location
   CREATE INDEX idx_cabinets_location ON cabinets(location_id);
   ```

### Cleanup Tasks

#### Orphaned Files

Find and clean up orphaned records:

1. Go to **Admin** > **System** > **Maintenance**
2. Run **Find Orphaned Records**
3. Review results:
   - Files with no location
   - Drawers with no cabinet
   - Tags with no files
   - Checkouts with no file
4. Choose action:
   - Fix automatically
   - Delete orphaned records
   - Export for manual review

#### Temporary Files

Clear temporary files regularly:

```bash
# Clear temp directory
find cache/temp/ -type f -mtime +7 -delete

# Clear old session files
find /var/lib/php/sessions -type f -mtime +30 -delete

# Clear old log files
find logs/ -name "*.log" -mtime +90 -delete
```

---

## Audit and Compliance

### Compliance Reports

Generate reports for auditors:

#### File Inventory Report
- Complete list of all files
- Current locations
- Sensitivity classifications
- Owners
- Checkout status

#### Access Log Report
- All file access events
- User actions
- Date/time stamps
- IP addresses

#### Retention Compliance Report
- Files approaching retention limits
- Files overdue for review
- Archived files
- Destroyed files with certificates

### Retention Policies

Configure retention rules:

1. Go to **Admin** > **Settings** > **Retention Policies**
2. Create policy:
   - **Name**: Financial Records
   - **Description**: IRS requirement
   - **Retention Period**: 7 years from creation
   - **Post-Retention Action**: Archive then destroy
   - **Applies To**: Files tagged "Financial"

3. System automatically:
   - Tracks file age
   - Warns when approaching retention limit
   - Triggers archive at retention date
   - Queues for destruction after archive period

### Legal Hold

Place files on legal hold:

1. Go to **Admin** > **Legal Holds**
2. Create new hold:
   - **Case Name**: Smith v. Company
   - **Case Number**: 2026-CV-12345
   - **Hold Date**: 2026-01-15
   - **Releasing Attorney**: Jane Doe

3. Add files to hold:
   - Search and select files
   - Or use bulk import with file numbers

4. Files on legal hold:
   - Cannot be archived
   - Cannot be destroyed
   - Marked with hold badge
   - Separate reporting

5. Release hold when case closes:
   - Enter release date and reason
   - Files return to normal retention

---

## Troubleshooting

### Common Admin Issues

#### Users Can't Log In

**Check:**
- Account is active
- Password hasn't expired
- Account not locked from failed attempts
- User role has login permission

**Solutions:**
- Reset password
- Unlock account
- Verify email address
- Check IP restrictions

#### Files Not Appearing in Search

**Check:**
- File is not archived (unless "Include Archived" checked)
- User has permission to view sensitivity level
- Database index is intact
- Cache is current

**Solutions:**
```bash
# Rebuild search index
php scripts/rebuild_search_index.php

# Clear cache
rm -rf cache/*

# Check database integrity
sqlite3 data/rolodrawer.db "PRAGMA integrity_check;"
```

#### Slow Performance

**Check:**
- Database size
- Number of active users
- Server resources
- Missing indexes
- Slow queries

**Solutions:**
- Optimize database
- Add indexes
- Increase PHP memory limit
- Enable opcode caching
- Archive old files

#### QR Codes Not Generating

**Check:**
- GD or Imagick extension installed
- Cache directory writable
- Temp directory writable
- PHP memory limit sufficient

**Solutions:**
```bash
# Install GD
sudo apt install php-gd
sudo systemctl restart apache2

# Check permissions
sudo chmod 775 cache/
sudo chown www-data:www-data cache/
```

### Disaster Recovery

#### Complete System Failure

**Recovery steps:**

1. **Assess damage**
   - What failed? (hardware, software, data)
   - Is data recoverable?
   - Do you have backups?

2. **Restore from backup**
   - Get latest backup
   - Provision new server if needed
   - Restore database
   - Restore uploaded files
   - Restore configuration

3. **Verify restoration**
   - Test database connection
   - Verify user logins
   - Check file access
   - Test search
   - Review audit logs

4. **Resume operations**
   - Notify users
   - Monitor for issues
   - Document incident

#### Data Corruption

If database corruption detected:

```bash
# SQLite
sqlite3 data/rolodrawer.db ".dump" > dump.sql
mv data/rolodrawer.db data/rolodrawer.db.corrupt
sqlite3 data/rolodrawer.db < dump.sql

# MySQL
mysqlcheck --repair --all-databases -u root -p
```

---

## Advanced Administration

### Custom Fields

Add custom fields to files:

1. Go to **Admin** > **Settings** > **Custom Fields**
2. Click **Add Field**
3. Configure:
   - Field name
   - Field type (text, number, date, dropdown)
   - Required or optional
   - Default value
   - Help text
4. Field appears on file creation/edit forms

### API Access

Enable API for integrations:

1. Go to **Admin** > **Settings** > **API**
2. Enable API access
3. Generate API keys for applications
4. Set rate limits
5. Monitor API usage

### Webhooks

Configure webhooks for integrations:

1. Go to **Admin** > **Settings** > **Webhooks**
2. Add webhook:
   - Trigger event (file created, checked out, etc.)
   - Destination URL
   - Authentication
3. System POSTs JSON data on events

---

## Support and Resources

### Admin Resources

- **Admin Forum**: https://forum.rolodrawer.com/admin
- **Documentation**: https://docs.rolodrawer.com
- **Video Tutorials**: https://learn.rolodrawer.com
- **Knowledge Base**: https://kb.rolodrawer.com

### Getting Support

For admin issues:
- **Email**: admin-support@rolodrawer.com
- **Priority Support**: (555) 555-5555 ext. 2
- **Emergency**: emergency@rolodrawer.com (24/7)

### Training

Train your users:
- Schedule group training sessions
- Provide user guide (USER_GUIDE.md)
- Create quick reference cards
- Record video tutorials for common tasks

---

*Last updated: January 2026 - Version 1.0.0*
