# 🗂️ RoloDrawer

**Enterprise Document Management System for Physical Files**

RoloDrawer is a comprehensive web-based document tracking and management system designed for organizations that need to track physical files and documents. With features like checkout/checkin workflows, QR code labels, archive management, and compliance tracking, RoloDrawer brings modern digital organization to physical document management.

[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-blue)](https://www.php.net/)
[![SQLite](https://img.shields.io/badge/Database-SQLite-green)](https://www.sqlite.org/)
[![License](https://img.shields.io/badge/License-MIT-yellow)](LICENSE)

## ✨ Features

### Core Functionality
- **📄 File Management** - Track files with UUID backend + custom display numbers
- **📍 Location Hierarchy** - Organize by Location → Cabinet → Drawer → File
- **🔍 Advanced Search** - Full-text search with filters (tags, entity, sensitivity)
- **🏷️ Tag System** - Color-coded tags for cross-referencing
- **🏢 Entity Management** - Associate files with organizations/companies
- **👥 User Management** - Multi-user with roles (Admin, User, Viewer)

### Workflow Features
- **📤 Checkout/Checkin** - Track who has files with due dates and overdue alerts
- **📦 Archive Workflow** - Compliant archiving with audit trail
- **🗑️ Destruction Workflow** - Certificate of destruction for compliance
- **🔄 Movement Tracking** - Complete audit trail of file movements

### Reporting & Analysis
- **📈 10 Comprehensive Reports** - System statistics, file inventory, checkout status, overdue files, archive summary, movement history, files by location, files by entity, tag usage, and user activity

### Physical Integration
- **📱 QR Code System** - Generate and scan QR codes for physical files
- **🖨️ Label Printing** - Avery 5160 compatible labels (30 per sheet)
- **📲 Mobile Lookup** - Scan QR codes with any mobile device

## 🚀 Quick Start

### Requirements
- PHP 7.4 or higher
- SQLite support (enabled by default in most PHP installations)
- Web server (Apache, Nginx, or similar)

### Installation

1. **Clone or download the repository**
   ```bash
   git clone https://github.com/AI3I/RoloDrawer.git
   cd RoloDrawer
   ```

2. **Set up file permissions**
   ```bash
   chmod 755 index.php
   chmod -R 777 storage/
   ```

3. **Access in browser**
   ```
   http://yourdomain.com/rolodrawer/
   ```

The database will be created automatically on first access!

### First-Time Login
```
Email: admin@rolodrawer.local
Password: RoloDrawer2026!
```

⚠️ **IMPORTANT**: Change the default password immediately after first login!

## 📖 Documentation

- **[INSTALLATION.md](INSTALLATION.md)** - Detailed installation guide for various platforms
- **[USER_GUIDE.md](USER_GUIDE.md)** - Complete user documentation
- **[ADMIN_GUIDE.md](ADMIN_GUIDE.md)** - Administrator guide
- **[CHANGELOG.md](CHANGELOG.md)** - Version history and roadmap

## 🔐 Security Features

- Password hashing with bcrypt
- Session management with activity tracking
- Role-based access control
- Account lockout protection
- File sensitivity levels
- Complete audit trails

## 📊 Sample Data

Load sample data to try RoloDrawer:

```bash
sqlite3 storage/database/rolodrawer.sqlite < sample_data.sql
```

Sample includes:
- 5 locations with complete hierarchy
- 8 entities (companies/departments)
- 12 color-coded tags
- 10 realistic sample files
- 4 demo users (all password: RoloDrawer2026!)

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## 📝 License

This project is licensed under the GNU GPLv3 - see the [LICENSE](LICENSE) file for details.

## 💫 Acknowledgments

**Dedicated to Matthew Ferry**, who for decades has sought a solution to herd and tame chaos. May this tool bring order to your filing cabinets and peace to your document management.

## 🆘 Support

- **Issues**: Report bugs via [GitHub Issues](https://github.com/AI3I/RoloDrawer/issues)
- **Documentation**: See the complete guides in this repository

---

**Version**: 1.0.4 | **Last Updated**: January 2026
