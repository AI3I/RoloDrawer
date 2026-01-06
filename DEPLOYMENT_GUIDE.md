# RoloDrawer Expiration Feature Deployment Guide

## Overview
This guide covers deploying the new expiration tracking features to your production server at plesk.eastbrady.net.

## Features Implemented

### 1. Security & UI Fixes
- ✅ Removed default admin email from login page
- ✅ Fixed navy navigation background (extends full height)
- ✅ Fixed double scrollbar issue on dashboard

### 2. Expiration Date Tracking
- ✅ Optional expiration date field on file creation/edit forms
- ✅ Expiration date display on file detail view with status badges
- ✅ Helper functions for expiration calculations
- ✅ Dashboard alerts showing expired and expiring files (30/60/90 days)

### 3. Database Changes
- ✅ `expiration_date` column added to files table
- ✅ `expiration_handled_at` column for tracking post-expiration handling
- ✅ `expiration_reminders` table for tracking sent reminders
- ✅ Indexes for performance optimization

### 4. Cron Job
- ✅ Daily cron script to track and log expiration reminders

## Deployment Steps

### Step 1: Backup Current Production
```bash
# SSH into server
ssh eastbrady.net@plesk.eastbrady.net

# Create backup
cd httpdocs/rolodrawer
cp -r . ../rolodrawer_backup_$(date +%Y%m%d)

# Backup database
cp storage/database/rolodrawer.sqlite storage/database/rolodrawer.sqlite.backup_$(date +%Y%m%d)
```

### Step 2: Deploy Updated Files
From your local machine:
```bash
cd /home/jdlewis/GitHub/RoloDrawer

# Deploy main application file
scp index.php eastbrady.net@plesk.eastbrady.net:httpdocs/rolodrawer/

# Deploy migration script
scp migrate_add_expiration.php eastbrady.net@plesk.eastbrady.net:httpdocs/rolodrawer/

# Deploy cron script
scp expiration_cron.php eastbrady.net@plesk.eastbrady.net:httpdocs/rolodrawer/
```

### Step 3: Run Database Migration
```bash
# SSH into server
ssh eastbrady.net@plesk.eastbrady.net

# Navigate to application directory
cd httpdocs/rolodrawer

# Run migration
php migrate_add_expiration.php
```

Expected output:
```
RoloDrawer Expiration Date Migration
=====================================

Adding expiration_date column to files table...
✓ Added expiration_date column
Adding expiration_handled_at column to files table...
✓ Added expiration_handled_at column

Creating expiration_reminders table...
✓ Created expiration_reminders table

Creating indexes...
✓ Created index on files.expiration_date
✓ Created index on expiration_reminders.file_id
✓ Created index on expiration_reminders.reminder_type

✅ Migration completed successfully!
```

### Step 4: Set Up Cron Job in Plesk

1. Log into Plesk Control Panel
2. Go to **Websites & Domains** → **eastbrady.net** → **Cron Jobs**
3. Click **Add Task**
4. Configure:
   - **Task type**: Run a PHP script
   - **Script**: `/httpdocs/rolodrawer/expiration_cron.php`
   - **Schedule**: Daily at 06:00 AM
   - **System user**: eastbrady.net
5. Save the cron job

Alternatively, use command-line cron (if available):
```bash
# Edit crontab
crontab -e

# Add this line:
0 6 * * * php /var/www/vhosts/eastbrady.net/httpdocs/rolodrawer/expiration_cron.php >> /var/www/vhosts/eastbrady.net/httpdocs/rolodrawer/storage/logs/expiration_cron.log 2>&1
```

### Step 5: Test the Features

1. **Test file creation with expiration**:
   - Go to Files → Add New File
   - Fill in required fields
   - Set an expiration date (try one in 25 days to see "Expiring Soon" status)
   - Create the file

2. **Verify expiration display**:
   - View the file detail page
   - Confirm expiration date and status badge appear

3. **Check dashboard alerts**:
   - Go to Dashboard
   - Confirm "Expiration Alerts" section appears (if any files are expiring)

4. **Test cron script manually**:
   ```bash
   php expiration_cron.php
   ```
   - Should run without errors
   - Check output for summary

5. **Test file editing**:
   - Edit an existing file
   - Add or change expiration date
   - Confirm it saves correctly

## Verification Checklist

- [ ] Login page no longer shows default admin email
- [ ] Navigation sidebar extends full height (no double scrollbar)
- [ ] Can create files with expiration dates
- [ ] Can edit files and modify expiration dates
- [ ] Expiration date appears on file detail view
- [ ] Dashboard shows expiration alerts (if applicable)
- [ ] Cron script runs successfully
- [ ] Database migration completed without errors

## Rollback Plan

If issues occur:

```bash
# SSH into server
ssh eastbrady.net@plesk.eastbrady.net
cd httpdocs/rolodrawer

# Restore previous version
cp ../rolodrawer_backup_YYYYMMDD/index.php ./

# Restore database
cp storage/database/rolodrawer.sqlite.backup_YYYYMMDD storage/database/rolodrawer.sqlite

# Disable cron job in Plesk
# (Go to Cron Jobs in Plesk and disable/delete the task)
```

## Future Enhancements (Not Yet Implemented)

The following features are designed but not yet implemented:

1. **Expiration Reports**:
   - Upcoming Expirations report
   - Expired Files report
   - Links from dashboard alerts (will show 404 until implemented)

2. **File List Indicators**:
   - Expiration status badges on main file listings
   - Sort/filter by expiration date

3. **Email Notifications**:
   - Automated emails for expiration reminders
   - Requires email configuration

## Support

If you encounter issues:
1. Check `/storage/logs/expiration_cron.log` for cron errors
2. Verify database schema with: `sqlite3 storage/database/rolodrawer.sqlite ".schema files"`
3. Test migration script in safe mode: `php migrate_add_expiration.php`

## Notes

- Expiration dates are **optional** - files can exist without them
- The cron job only logs reminders; it doesn't send emails (yet)
- Dashboard alerts only appear if there are files approaching expiration
- The system tracks 30/60/90 day intervals before AND after expiration
