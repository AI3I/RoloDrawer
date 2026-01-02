# RoloDrawer Installation Guide

## Table of Contents
1. [System Requirements](#system-requirements)
2. [Installation Methods](#installation-methods)
3. [Post-Installation Setup](#post-installation-setup)
4. [Web Server Configuration](#web-server-configuration)
5. [Troubleshooting](#troubleshooting)
6. [Security Hardening](#security-hardening)

---

## System Requirements

### Minimum Requirements
- **PHP**: 7.4 or higher (8.0+ recommended)
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **Database**: SQLite 3.8+ (included with PHP) or MySQL 5.7+/PostgreSQL 12+
- **PHP Extensions**:
  - PDO (with SQLite/MySQL/PostgreSQL driver)
  - mbstring
  - OpenSSL
  - JSON
  - GD or Imagick (for QR code generation)
  - Session support
  - cURL (optional, for future integrations)

### Recommended Specifications
- **Memory**: 512MB RAM minimum, 1GB+ recommended
- **Storage**: 100MB for application + space for uploaded documents
- **PHP Memory Limit**: 128MB minimum (`memory_limit = 128M`)
- **PHP Upload Limits**:
  - `upload_max_filesize = 10M`
  - `post_max_size = 10M`
  - `max_file_uploads = 20`

### Checking Your System

Create a `phpinfo.php` file in your web root:
```php
<?php phpinfo(); ?>
```

Visit it in your browser to verify PHP version and extensions. **Delete this file after verification.**

---

## Installation Methods

### Method 1: Plesk Panel Installation

#### Step 1: Download RoloDrawer
1. Log into your Plesk control panel
2. Navigate to **Files** > **File Manager**
3. Navigate to your domain's `httpdocs` or `public_html` directory
4. Upload the RoloDrawer ZIP file
5. Extract the archive

#### Step 2: Set Up Database
1. In Plesk, go to **Databases** > **Add Database**
2. For SQLite (recommended for small installations):
   - No database creation needed
   - Ensure `data/` directory has write permissions
3. For MySQL:
   - Create a new database (e.g., `rolodrawer`)
   - Create a database user with full privileges
   - Note the database credentials

#### Step 3: Configure File Permissions
1. In File Manager, select the following directories:
   - `data/`
   - `uploads/`
   - `cache/`
   - `logs/`
2. Right-click > **Change Permissions**
3. Set to `755` for directories
4. Enable "Apply to subdirectories"

#### Step 4: Run Setup Wizard
1. Navigate to `http://yourdomain.com/setup_wizard.php`
2. Follow the on-screen instructions
3. Delete `setup_wizard.php` after completion

---

### Method 2: cPanel Installation

#### Step 1: Upload Files
1. Log into cPanel
2. Open **File Manager**
3. Navigate to `public_html/`
4. Click **Upload** and select RoloDrawer ZIP file
5. After upload, select the ZIP file > **Extract**

#### Step 2: Create Database
1. In cPanel, open **MySQL Databases**
2. Create a new database: `username_rolodrawer`
3. Create a database user with a strong password
4. Add user to database with ALL PRIVILEGES
5. Note: username, password, database name, hostname

#### Step 3: Set Permissions
1. In File Manager, select these folders:
   - `data/`
   - `uploads/`
   - `cache/`
   - `logs/`
2. Click **Permissions** in the toolbar
3. Set to `755` (rwxr-xr-x)
4. Check "Recurse into subdirectories"

#### Step 4: Configure Application
1. Copy `config.example.php` to `config.php`
2. Edit `config.php` with database credentials
3. Run `http://yourdomain.com/setup_wizard.php`
4. Delete `setup_wizard.php` when complete

---

### Method 3: Manual Installation (SSH Access)

#### Step 1: Download and Extract
```bash
# Navigate to web root
cd /var/www/html

# Download (example - adjust URL)
wget https://example.com/rolodrawer-latest.zip

# Extract
unzip rolodrawer-latest.zip
cd rolodrawer

# Or if using git
git clone https://github.com/yourusername/rolodrawer.git
cd rolodrawer
```

#### Step 2: Set Up Permissions
```bash
# Set ownership (adjust user/group for your system)
sudo chown -R www-data:www-data .

# Set directory permissions
sudo find . -type d -exec chmod 755 {} \;
sudo find . -type f -exec chmod 644 {} \;

# Set write permissions for specific directories
sudo chmod -R 775 data/ uploads/ cache/ logs/
```

#### Step 3: Configure Database

**For SQLite (default):**
```bash
# Create database directory if it doesn't exist
mkdir -p data
touch data/rolodrawer.db
chmod 664 data/rolodrawer.db
chmod 775 data/
```

**For MySQL/PostgreSQL:**
```bash
# MySQL example
mysql -u root -p
CREATE DATABASE rolodrawer CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'rolodrawer'@'localhost' IDENTIFIED BY 'secure_password_here';
GRANT ALL PRIVILEGES ON rolodrawer.* TO 'rolodrawer'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

#### Step 4: Configure Application
```bash
# Copy example configuration
cp config.example.php config.php

# Edit configuration (use nano, vim, or your preferred editor)
nano config.php

# Update these values:
# - DB_TYPE (sqlite, mysql, or pgsql)
# - DB_HOST (for MySQL/PostgreSQL)
# - DB_NAME
# - DB_USER
# - DB_PASS
# - BASE_URL
```

#### Step 5: Initialize Database
```bash
# Run setup wizard via CLI or web browser
php setup_wizard.php --cli

# Or visit in browser:
# http://yourdomain.com/setup_wizard.php
```

#### Step 6: Secure Installation
```bash
# Remove setup wizard
rm setup_wizard.php

# Protect sensitive files
chmod 600 config.php
```

---

## Post-Installation Setup

### Initial Login
1. Navigate to your RoloDrawer URL (e.g., `http://yourdomain.com/rolodrawer`)
2. Default credentials:
   - **Username**: `admin`
   - **Password**: `admin123` (change immediately!)
3. You will be prompted to change the password on first login

### Loading Sample Data

Sample data helps you understand how RoloDrawer works:

**Option 1: Via Setup Wizard**
- During setup, check "Load sample data"
- This creates example locations, cabinets, drawers, and files

**Option 2: Manual Import**
```bash
# Via CLI
php scripts/load_sample_data.php

# Or via web interface
# Login > Settings > System > Import Sample Data
```

**Sample data includes:**
- 3 Locations (Building A, Building B, Storage Facility)
- 8 Cabinets across locations
- 24 Drawers
- 50+ Sample files with various attributes
- Example tags and cross-references
- Sample checkout history

**Note**: Sample data is clearly marked and can be deleted after familiarization.

### Creating Your First Real Data

1. **Add Locations**
   - Navigate to **Locations** > **Add Location**
   - Enter: Name, Address, Contact

2. **Add Cabinets**
   - Go to **Cabinets** > **Add Cabinet**
   - Enter: Cabinet ID, Location, Number of Drawers

3. **Add Drawers**
   - Drawers are auto-created with cabinets
   - Or manually: **Drawers** > **Add Drawer**

4. **Create Files**
   - Go to **Files** > **Add File**
   - Fill in details: Name, Description, Owner, Sensitivity
   - Assign to drawer

5. **Print Labels**
   - Open any file detail page
   - Click **Print Label**
   - Print on adhesive labels
   - Affix to physical file folder

---

## Web Server Configuration

### Apache Configuration

#### .htaccess (included in installation)
The included `.htaccess` file provides:
- URL rewriting for clean URLs
- Security headers
- Directory protection

**Ensure mod_rewrite is enabled:**
```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

#### Virtual Host Example
```apache
<VirtualHost *:80>
    ServerName rolodrawer.example.com
    DocumentRoot /var/www/html/rolodrawer

    <Directory /var/www/html/rolodrawer>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Protect sensitive directories
    <Directory /var/www/html/rolodrawer/data>
        Require all denied
    </Directory>

    <Directory /var/www/html/rolodrawer/config>
        Require all denied
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/rolodrawer_error.log
    CustomLog ${APACHE_LOG_DIR}/rolodrawer_access.log combined
</VirtualHost>
```

#### Enable HTTPS (Recommended)
```bash
# Install Certbot
sudo apt install certbot python3-certbot-apache

# Obtain certificate
sudo certbot --apache -d rolodrawer.example.com

# Auto-renewal is configured automatically
```

---

### Nginx Configuration

#### Server Block Example
```nginx
server {
    listen 80;
    server_name rolodrawer.example.com;
    root /var/www/html/rolodrawer;
    index index.php index.html;

    # Disable directory listing
    autoindex off;

    # Logging
    access_log /var/log/nginx/rolodrawer_access.log;
    error_log /var/log/nginx/rolodrawer_error.log;

    # Main location
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP handling
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Deny access to sensitive files
    location ~ /\. {
        deny all;
    }

    location ~* /(data|config|logs|cache)/.*$ {
        deny all;
    }

    # Static files caching
    location ~* \.(jpg|jpeg|png|gif|ico|css|js)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }
}
```

#### Enable HTTPS
```bash
# Install Certbot
sudo apt install certbot python3-certbot-nginx

# Obtain certificate
sudo certbot --nginx -d rolodrawer.example.com
```

---

## Troubleshooting

### Common Issues and Solutions

#### 1. Blank White Page
**Cause**: PHP errors with display_errors disabled

**Solution**:
```bash
# Check error logs
tail -f /var/log/apache2/error.log
# or
tail -f /var/log/nginx/error.log

# Or check application logs
tail -f logs/app.log
```

#### 2. Database Connection Failed
**Symptoms**: Error message about database connection

**Solutions**:
- Verify `config.php` credentials are correct
- For SQLite: Check `data/` directory is writable
  ```bash
  chmod 775 data/
  chmod 664 data/rolodrawer.db
  ```
- For MySQL: Test connection manually:
  ```bash
  mysql -h localhost -u rolodrawer -p rolodrawer
  ```

#### 3. File Upload Fails
**Cause**: PHP upload limits or permission issues

**Solutions**:
```bash
# Check uploads directory permissions
chmod 775 uploads/

# Increase PHP limits in php.ini or .htaccess:
php_value upload_max_filesize 10M
php_value post_max_size 10M

# Restart web server
sudo systemctl restart apache2
```

#### 4. QR Codes Don't Generate
**Cause**: Missing GD or Imagick extension

**Solution**:
```bash
# Install GD extension
sudo apt install php-gd
sudo systemctl restart apache2

# Verify in PHP
php -m | grep -i gd
```

#### 5. Session Errors / Logged Out Immediately
**Cause**: Session directory not writable or session configuration issue

**Solution**:
```bash
# Check session directory
php -i | grep session.save_path

# Ensure it's writable
ls -ld /var/lib/php/sessions

# Or set custom session path in config.php
```

#### 6. "Permission Denied" Errors
**Solution**:
```bash
# Reset permissions
sudo chown -R www-data:www-data /var/www/html/rolodrawer
sudo find /var/www/html/rolodrawer -type d -exec chmod 755 {} \;
sudo find /var/www/html/rolodrawer -type f -exec chmod 644 {} \;
sudo chmod -R 775 data/ uploads/ cache/ logs/
```

#### 7. URL Rewriting Not Working (404 Errors)
**Apache**:
```bash
sudo a2enmod rewrite
sudo systemctl restart apache2

# Ensure AllowOverride All in Apache config
```

**Nginx**: Ensure the `try_files` directive is correct in your config

#### 8. Database Schema Errors
**Solution**: Reinitialize database
```bash
# Backup first!
cp data/rolodrawer.db data/rolodrawer.db.backup

# Re-run migrations
php scripts/migrate.php

# Or use setup wizard
```

---

## Security Hardening

### Pre-Production Checklist

#### 1. Change Default Credentials
- [ ] Change admin password from default
- [ ] Use strong passwords (12+ characters, mixed case, numbers, symbols)
- [ ] Consider implementing password policies

#### 2. Secure Configuration Files
```bash
# Protect config.php
chmod 600 config.php
chown www-data:www-data config.php

# Ensure config.example.php has no real credentials
```

#### 3. Disable Directory Listing
```bash
# Apache: Add to .htaccess or VirtualHost
Options -Indexes

# Nginx: Ensure 'autoindex off;' in config
```

#### 4. Protect Sensitive Directories
Ensure these directories are NOT web-accessible:
- `/data/` - Database files
- `/config/` - Configuration files
- `/logs/` - Log files
- `/cache/` - Cache files
- `/vendor/` - Dependencies (if using Composer)

**Verify**: Try accessing `http://yourdomain.com/data/` - should get 403 Forbidden

#### 5. Enable HTTPS
- [ ] Install SSL certificate (Let's Encrypt recommended)
- [ ] Force HTTPS redirects
- [ ] Enable HSTS headers

```apache
# Apache: Add to VirtualHost
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
```

```nginx
# Nginx: Add to server block
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
```

#### 6. Set Security Headers
Add to your web server config:

```apache
# Apache (.htaccess or VirtualHost)
Header always set X-Frame-Options "SAMEORIGIN"
Header always set X-Content-Type-Options "nosniff"
Header always set X-XSS-Protection "1; mode=block"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
```

```nginx
# Nginx (server block)
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-Content-Type-Options "nosniff" always;
add_header X-XSS-Protection "1; mode=block" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
```

#### 7. Disable PHP Information Disclosure
```bash
# In php.ini or .htaccess
expose_php = Off
display_errors = Off
log_errors = On
error_log = /var/log/php/error.log
```

#### 8. Implement Rate Limiting
```nginx
# Nginx example - limit login attempts
limit_req_zone $binary_remote_addr zone=loginlimit:10m rate=5r/m;

location /login.php {
    limit_req zone=loginlimit burst=3 nodelay;
}
```

#### 9. Regular Updates
- [ ] Subscribe to security announcements
- [ ] Keep PHP updated
- [ ] Update RoloDrawer when new versions release
- [ ] Monitor security logs regularly

#### 10. File Upload Security
```php
// In config.php - restrict upload types
$config['allowed_upload_types'] = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
$config['max_upload_size'] = 10485760; // 10MB
```

#### 11. Database Security
**SQLite**:
```bash
# Ensure database is outside web root or protected
chmod 600 data/rolodrawer.db
```

**MySQL**:
- Use strong database passwords
- Restrict database user to localhost
- Grant only necessary privileges
- Consider enabling SSL for database connections

#### 12. Backup Configuration
- [ ] Set up automated daily backups
- [ ] Test restore procedures
- [ ] Store backups securely offsite
- [ ] Document backup/restore process

```bash
# Example backup script
#!/bin/bash
BACKUP_DIR="/backup/rolodrawer"
DATE=$(date +%Y%m%d_%H%M%S)

# Backup database
cp /var/www/html/rolodrawer/data/rolodrawer.db $BACKUP_DIR/db_$DATE.db

# Backup uploads
tar -czf $BACKUP_DIR/uploads_$DATE.tar.gz /var/www/html/rolodrawer/uploads/

# Keep only last 30 days
find $BACKUP_DIR -mtime +30 -delete
```

#### 13. Logging and Monitoring
- [ ] Enable application logging
- [ ] Set up log rotation
- [ ] Monitor for suspicious activity
- [ ] Review logs regularly

```bash
# Setup logrotate for RoloDrawer
sudo nano /etc/logrotate.d/rolodrawer
```

```
/var/www/html/rolodrawer/logs/*.log {
    daily
    rotate 30
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
    sharedscripts
}
```

#### 14. Remove Development Files
```bash
# Before going to production, remove:
rm setup_wizard.php
rm phpinfo.php
rm test.php
rm -rf tests/
```

#### 15. Set Proper Error Handling
In `config.php`:
```php
// Development
$config['environment'] = 'development';
$config['debug_mode'] = true;

// Production
$config['environment'] = 'production';
$config['debug_mode'] = false;
```

---

## Next Steps

After successful installation:

1. Read the [User Guide](USER_GUIDE.md) to learn basic operations
2. Read the [Admin Guide](ADMIN_GUIDE.md) for system administration
3. Set up your organizational structure (locations, cabinets, drawers)
4. Create user accounts for your team
5. Begin cataloging files
6. Set up automated backups

---

## Getting Help

- **Documentation**: Check USER_GUIDE.md and ADMIN_GUIDE.md
- **Issue Tracker**: https://github.com/yourusername/rolodrawer/issues
- **Email Support**: support@example.com
- **Community Forum**: https://forum.example.com/rolodrawer

---

## System Information

For support requests, please include:
- RoloDrawer version
- PHP version (`php -v`)
- Web server (Apache/Nginx) and version
- Operating system
- Database type and version
- Error messages from logs

Generate a system info report:
```bash
php scripts/system_info.php
```

---

*Last updated: January 2026 - Version 1.0.1*
