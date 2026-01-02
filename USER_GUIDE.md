# RoloDrawer User Guide

## Table of Contents
1. [Getting Started](#getting-started)
2. [Dashboard Overview](#dashboard-overview)
3. [Managing Files](#managing-files)
4. [Checkout and Checkin System](#checkout-and-checkin-system)
5. [Search and Filtering](#search-and-filtering)
6. [Tags and Cross-Referencing](#tags-and-cross-referencing)
7. [Entities and Relationships](#entities-and-relationships)
8. [Printing QR Code Labels](#printing-qr-code-labels)
9. [Reports and Analytics](#reports-and-analytics)
10. [Tips and Best Practices](#tips-and-best-practices)

---

## Getting Started

### First Login

1. Navigate to your RoloDrawer URL (e.g., `https://files.yourcompany.com`)
2. Enter your username and password
3. If this is your first time logging in with default credentials, you'll be prompted to change your password

### Understanding Your Role

RoloDrawer has three user roles:

- **Viewer**: Can search and view files, but cannot modify anything
- **User**: Can create, edit, checkout files, and manage their own data
- **Admin**: Full access to all features including user management and system settings

Your role determines which features you can access.

---

## Dashboard Overview

When you log in, you'll see the dashboard with key information:

### Dashboard Sections

```
┌──────────────────────────────────────────────┐
│  RoloDrawer                     [Search...]  │
├──────────────────────────────────────────────┤
│  Quick Stats                                 │
│  ┌─────────┬─────────┬─────────┬──────────┐ │
│  │ 64      │ 12      │ 3       │ 5        │ │
│  │ Files   │ Cabinets│ Locations│ Checked │ │
│  │         │         │         │ Out      │ │
│  └─────────┴─────────┴─────────┴──────────┘ │
│                                              │
│  Your Checkouts (3)                          │
│  • File #31 - ACME Contract (due in 2 days) │
│  • File #45 - Budget Report (overdue 1 day) │
│  • File #27 - Invoice History (due today)   │
│                                              │
│  Recent Activity                             │
│  • File #99 moved to CAB-001-B by John      │
│  • File #102 created by Jane                │
│  • File #45 checked out by You              │
└──────────────────────────────────────────────┘
```

### Navigation Menu

The left sidebar contains:
- **Dashboard**: Overview and quick stats
- **Files**: Browse and manage all files
- **Locations**: View physical locations
- **Cabinets & Drawers**: Browse by storage location
- **Tags**: Browse files by tag
- **Entities**: Browse files by entity (companies, projects, etc.)
- **Reports**: View analytics and reports
- **My Account**: Update your profile and password

---

## Managing Files

### Creating a New File

1. Click **Files** in the navigation menu
2. Click the **+ Add File** button
3. Fill in the required information:

#### Basic Information
- **File Number**: Auto-assigned (e.g., #103)
- **Name**: Descriptive name for the file (e.g., "ACME Vendor Contract")
- **Description**: Detailed description of contents

#### Classification
- **Owner**: Person or department responsible for this file
- **Sensitivity Level**: Choose appropriate level:
  - **Public**: Can be shared freely
  - **Internal**: For internal use only
  - **Confidential**: Restricted to specific personnel
  - **Restricted**: Highest security, very limited access

#### Physical Location
- **Location**: Building or facility (e.g., "Building A")
- **Cabinet**: Specific cabinet ID (e.g., "CAB-001")
- **Drawer**: Drawer identifier (e.g., "Drawer B")

4. Click **Create File**

**Example:**
```
Name: ACME Corporation Vendor Agreement
Description: Master service agreement with ACME Corp, including
             pricing schedules and terms effective 2024-2026
Owner: Jane Smith (Procurement)
Sensitivity: Confidential
Location: Building A > CAB-001 > Drawer B
```

### Viewing File Details

Click on any file number or name to view complete details:

```
┌────────────────────────────────────────────────┐
│ File #31 - ACME Vendor Contract                │
│ [Edit] [Move] [Checkout] [Archive] [Print]    │
├────────────────────────────────────────────────┤
│ UUID: 550e8400-e29b-41d4-a716-446655440000     │
│ Owner: Jane Smith                              │
│ Sensitivity: ⚠ Confidential                   │
│ Status: ✓ In Drawer                           │
│                                                │
│ Current Location:                              │
│ Building A > CAB-001 > Drawer B                │
│                                                │
│ Description:                                   │
│ Master service agreement with ACME Corp...     │
│                                                │
│ Tags: [ACME Corp] [Contracts] [Vendors] [2024]│
│                                                │
│ Related Files:                                 │
│ #27: ACME Invoice History                      │
│ #99: ACME Contact Information                  │
│ #41: ACME Service Tickets                      │
│                                                │
│ History:                                       │
│ Jan 15, 2026 - Created by Jane Smith           │
│ Jan 16, 2026 - Moved to CAB-001-B             │
│ Jan 20, 2026 - Checked out by Bob Jones        │
│ Jan 22, 2026 - Returned to drawer              │
└────────────────────────────────────────────────┘
```

### Editing File Information

1. Open the file detail page
2. Click **Edit** button
3. Update any field as needed
4. Click **Save Changes**

**Note**: You can only edit files if:
- You are the owner, or
- You have User/Admin role, or
- The file is not currently checked out by someone else

### Moving Files

When you physically move a file folder to a different location:

1. Open the file detail page
2. Click **Move** button
3. Select new location:
   - Location (building/facility)
   - Cabinet
   - Drawer
4. Add optional note (e.g., "Moved for reorganization")
5. Click **Confirm Move**

The system logs all movements with timestamp and user.

### Archiving Files

When a file is no longer active but should be retained:

1. Open the file detail page
2. Click **Archive** button
3. Select archive reason:
   - Project completed
   - Retention period expired
   - Superseded by new version
   - Other (specify)
4. Click **Archive File**

**Archived files:**
- Remain searchable with "Include Archived" filter
- Cannot be checked out
- Are marked with archive badge
- Can be un-archived if needed

### Destroying Files

**Note**: This feature is typically restricted to administrators. See [Admin Guide](ADMIN_GUIDE.md) for details.

---

## Checkout and Checkin System

The checkout system tracks when files leave their physical storage location.

### Checking Out a File

1. Navigate to the file you need
2. Click **Checkout** button
3. Enter checkout details:
   - **Expected Return Date**: When you plan to return it
   - **Purpose** (optional): Why you need the file
   - **Notes** (optional): Any additional information
4. Enable reminder option if you want an email notification before due date
5. Click **Checkout File**

**Example:**
```
┌─────────────────────────────────┐
│ Checkout File #31               │
├─────────────────────────────────┤
│ Expected Return Date:           │
│ [2026-01-25] 📅                 │
│                                 │
│ Purpose:                        │
│ [Vendor contract review]        │
│                                 │
│ Notes:                          │
│ [Need for quarterly meeting]    │
│                                 │
│ ☑ Send reminder 1 day before   │
│                                 │
│     [Cancel]  [Checkout File]   │
└─────────────────────────────────┘
```

### Checking In a File

When you return the file to its drawer:

1. Go to **My Account** > **My Checkouts**
2. Find the file in your checkout list
3. Click **Return** button
4. Confirm the file is back in its designated drawer
5. Add optional note about condition or changes
6. Click **Return File**

**Alternatively:**
- From the file detail page, click **Return to Drawer**
- Scan the file's QR code and select "Return"

### Viewing Your Checkouts

See all files currently checked out to you:

1. Click your name in the top right
2. Select **My Checkouts**
3. View list with:
   - File number and name
   - Due date (highlighted if overdue)
   - Days remaining/overdue
   - Quick return button

### Overdue Notifications

If a file becomes overdue:
- You'll receive an email notification
- The dashboard shows overdue count
- File appears highlighted in "My Checkouts"
- Reminder emails sent daily until returned

---

## Search and Filtering

### Quick Search

The search box in the top navigation searches across:
- File numbers
- File names
- Descriptions
- Tags
- Entity names
- Owner names

**Tips:**
- Search is case-insensitive
- Partial matches work (searching "acme" finds "ACME Corporation")
- Use quotes for exact phrases: `"vendor agreement"`

### Advanced Search

Click **Advanced Search** for detailed filtering:

```
┌──────────────────────────────────────────┐
│ Advanced File Search                     │
├──────────────────────────────────────────┤
│ File Number: [_______]                   │
│                                          │
│ Name contains: [_______]                 │
│                                          │
│ Description contains: [_______]          │
│                                          │
│ Owner: [Select...▼]                      │
│                                          │
│ Sensitivity: [All ▼]                     │
│                                          │
│ Location: [All ▼]                        │
│ Cabinet: [All ▼]                         │
│ Drawer: [All ▼]                          │
│                                          │
│ Tags: [__________]                       │
│ Entity: [All ▼]                          │
│                                          │
│ Status:                                  │
│ ☑ In Drawer                              │
│ ☑ Checked Out                            │
│ ☐ Archived                               │
│                                          │
│ Date Range:                              │
│ Created: [From] [To]                     │
│                                          │
│     [Clear]         [Search]             │
└──────────────────────────────────────────┘
```

### Search Results

Results show:
- File number and name
- Current location/status
- Owner and sensitivity
- Relevant tags
- Quick action buttons

Sort results by:
- File number (ascending/descending)
- Name (A-Z/Z-A)
- Date created (newest/oldest)
- Location
- Last modified

### Saved Searches

Save frequently used searches:

1. Perform an advanced search
2. Click **Save Search**
3. Name it (e.g., "My Confidential Files")
4. Access from **Saved Searches** dropdown

**Examples:**
- "All Checked Out Files"
- "Confidential Files in Building A"
- "Files Tagged with 'Legal'"
- "Files Owned by My Department"

---

## Tags and Cross-Referencing

Tags help you organize and find related files.

### Understanding Tags

Tags are labels you can attach to files for categorization:
- **Department**: Legal, Finance, HR, IT
- **Project**: Project Alpha, Migration 2024
- **Type**: Contract, Invoice, Report, Policy
- **Entity**: Company/person name
- **Status**: Active, Pending, Completed
- **Year**: 2024, 2025, 2026

### Adding Tags to a File

1. Open the file detail page
2. In the **Tags** section, click **+ Add Tag**
3. Start typing - existing tags will autocomplete
4. Select an existing tag or create a new one
5. Press Enter or click Add

**Example:**
File #31 might have tags:
`[ACME Corp] [Vendors] [Contracts] [Finance] [2024] [Active]`

### Browsing Files by Tag

1. Click **Tags** in the navigation
2. See tag cloud showing all tags
3. Click any tag to view all files with that tag
4. Tags show file count in parentheses

```
Tags (42)

ACME Corp (4)          Contracts (23)        Finance (31)
HR (12)                Legal (18)            Projects (9)
Vendors (15)           2024 (45)             2025 (33)
Active (52)            Archived (8)          Confidential (19)
```

### Cross-Referencing Files

Link related files together:

1. Open a file detail page
2. Scroll to **Related Files** section
3. Click **+ Add Related File**
4. Search for and select the related file
5. Optionally specify relationship type:
   - Related to
   - Supersedes
   - Superseded by
   - Referenced in
   - Attachment to

**Example Use Case:**
For "ACME Corporation", you might link:
- File #31: Vendor Agreement
- File #27: Invoice History
- File #99: Contact Information
- File #41: Service Tickets

Now viewing any of these files shows the others as related.

---

## Entities and Relationships

Entities represent companies, people, projects, or other organizational units that files relate to.

### Understanding Entities

An **Entity** is any person or organization you track:
- Vendors and suppliers
- Clients and customers
- Contractors
- Projects
- Departments
- Properties or assets

### Viewing Files by Entity

1. Click **Entities** in navigation
2. Browse the list of entities
3. Click an entity to see all associated files

**Example:**
```
┌───────────────────────────────────────────┐
│ Entity: ACME Corporation                  │
├───────────────────────────────────────────┤
│ Type: Vendor                              │
│ Description: Primary IT services vendor   │
│                                           │
│ Contact:                                  │
│ Email: contracts@acme.com                 │
│ Phone: (555) 123-4567                     │
│                                           │
│ Associated Files (4):                     │
│ #31: Vendor Agreement                     │
│ #27: Invoice History                      │
│ #99: Contact Information                  │
│ #41: Service Tickets                      │
│                                           │
│ Tags: [Vendors] [IT Services] [Active]    │
└───────────────────────────────────────────┘
```

### Associating Files with Entities

1. Open a file detail page
2. Click **Edit**
3. In the **Entity** field, search and select
4. Save changes

Or use the **Add to Entity** quick action from file list.

---

## Printing QR Code Labels

QR code labels help you quickly identify and scan physical file folders.

### Printing Individual Labels

1. Open the file you want to label
2. Click **Print Label** button
3. Choose label options:
   - **Label size**: Standard, Large, Extra Large
   - **Include**: QR code, Barcode, or Both
   - **Show details**: Name, Location, Sensitivity
4. Click **Generate Label**
5. A PDF opens - print it
6. Cut and affix to your file folder tab

**Label Preview:**
```
┌─────────────────────────┐
│  File #31               │
│                         │
│  ┌──────────────┐       │
│  │  [QR CODE]   │       │
│  │              │       │
│  └──────────────┘       │
│                         │
│  ACME Vendor Contract   │
│  Location: CAB-001-B    │
│  Owner: Jane Smith      │
│  ⚠ Confidential        │
└─────────────────────────┘
```

### Printing Batch Labels

For labeling multiple files at once:

1. Go to **Files** list
2. Select checkboxes for files you want to label
3. Click **Actions** > **Print Labels**
4. Choose label template:
   - Avery 5160 (30 labels per sheet)
   - Avery 5161 (20 labels per sheet)
   - Avery 5162 (14 labels per sheet)
   - Custom size
5. Click **Generate Sheet**
6. Print on compatible label sheets

### Scanning QR Codes

Use your phone or a QR scanner to:

1. **Quick Lookup**: Scan to instantly view file details
2. **Quick Checkout**: Scan and checkout in one step
3. **Quick Return**: Scan to return file
4. **Location Verification**: Confirm file is in correct drawer

**To use:**
1. Open your phone camera or QR scanner app
2. Point at the QR code on file folder
3. Tap the notification/link
4. Opens RoloDrawer to that file
5. Choose action: View, Checkout, Return

---

## Reports and Analytics

### Available Reports

#### 1. Inventory Report
Shows all files with current locations.

**Fields:**
- File number and name
- Current location (or "Checked Out")
- Owner
- Sensitivity
- Tags

**Filters:**
- By location
- By cabinet
- By sensitivity
- By status

**Export options:** PDF, Excel, CSV

#### 2. Checkout Report
Lists all currently checked out files.

**Fields:**
- File number and name
- Checked out by
- Checkout date
- Due date
- Days remaining/overdue

**Use for:**
- Following up on overdue returns
- Planning file availability
- Audit trail

#### 3. Movement History Report
Tracks file movements over time.

**Fields:**
- File number and name
- From location
- To location
- Moved by
- Move date
- Reason/notes

**Filters:**
- Date range
- Specific file
- Specific location
- Moved by user

#### 4. Activity Report
Shows all actions taken in the system.

**Fields:**
- Date/time
- User
- Action (created, edited, moved, checked out, returned)
- File affected
- Details

**Use for:**
- Audit compliance
- Understanding usage patterns
- Security review

#### 5. Files by Entity Report
Groups files by associated entity.

Shows:
- Entity name
- Number of files
- List of file numbers
- Total by entity type

#### 6. Sensitivity Report
Breaks down files by sensitivity level.

**Useful for:**
- Security audits
- Understanding data classification
- Compliance reporting

### Generating Reports

1. Click **Reports** in navigation
2. Select report type
3. Configure filters and options
4. Click **Generate Report**
5. View on-screen or export

### Scheduling Reports

Admin users can schedule automatic reports:

1. Generate a report with desired filters
2. Click **Schedule This Report**
3. Choose frequency (daily, weekly, monthly)
4. Enter email recipients
5. Save schedule

Reports will be automatically generated and emailed.

---

## Tips and Best Practices

### File Naming Conventions

Use consistent, descriptive names:
- **Good**: "ACME Corp Vendor Agreement 2024-2026"
- **Bad**: "Contract", "File1", "Misc"

Include key information:
- Entity/party names
- Document type
- Date or period
- Version if applicable

### Tagging Strategy

Create a tagging taxonomy:
- Use consistent terminology
- Don't create duplicate tags (e.g., "Vendor" and "Vendors")
- Use broad categories (Department, Type, Status, Year)
- Tag files when created, not later

### Checkout Best Practices

- Always estimate return dates conservatively
- Return files promptly when done
- Use notes field to explain why you need it
- Enable reminder emails
- Check your dashboard regularly for due/overdue files

### Search Tips

- Start broad, then narrow with filters
- Use tags for categorical searches
- Save frequently used searches
- Use quotes for exact phrase matching
- Combine multiple criteria in advanced search

### Physical Organization

- Keep QR labels visible on folder tabs
- Maintain consistent folder orientation in drawers
- Don't overfill drawers (makes finding files difficult)
- Update locations immediately when moving files
- Periodically verify physical locations match system

### Security Awareness

- Respect sensitivity classifications
- Don't share confidential files inappropriately
- Log out when leaving your workstation
- Report missing files immediately
- Use strong, unique passwords

### Data Quality

- Fill in all fields completely when creating files
- Keep descriptions current and accurate
- Update owner when responsibility changes
- Archive old files rather than delete
- Cross-reference related files for context

### Performance Tips

- Use specific searches rather than browsing all files
- Bookmark frequently accessed files
- Use saved searches for routine queries
- Clear browser cache if pages load slowly

---

## Keyboard Shortcuts

Speed up your workflow with these shortcuts:

- **Ctrl/Cmd + K**: Focus search box
- **Ctrl/Cmd + N**: Create new file (from Files page)
- **Ctrl/Cmd + E**: Edit current file
- **Ctrl/Cmd + P**: Print label for current file
- **Ctrl/Cmd + /**: Show shortcuts help
- **Esc**: Close modal dialogs
- **Arrow keys**: Navigate search results

---

## Mobile Usage

RoloDrawer is mobile-responsive:

### Mobile Features

- Search and browse files
- View file details
- Checkout and checkin files
- Scan QR codes with camera
- View your checkouts
- Receive push notifications (if enabled)

### Mobile Tips

- Add RoloDrawer to your home screen for quick access
- Use camera to scan QR codes for instant lookup
- Enable notifications for checkout reminders
- Portrait mode recommended for browsing
- Landscape mode better for reading details

---

## Getting Help

### In-App Help

Click the **?** icon in the top navigation for:
- Quick tips
- Keyboard shortcuts
- Video tutorials
- Context-sensitive help

### Getting Help

Need assistance?
- Check this user guide (USER_GUIDE.md)
- Review the admin guide (ADMIN_GUIDE.md)
- Contact your system administrator

### Feedback & Bug Reports

Found a bug or have suggestions?
- Report issues on GitHub: https://github.com/AI3I/RoloDrawer/issues
- Join discussions: https://github.com/AI3I/RoloDrawer/discussions

---

## Appendix: Common Workflows

### Workflow 1: Adding a New Physical File

1. Create file folder physically
2. Log into RoloDrawer
3. Click **Files** > **Add File**
4. Fill in all details
5. Assign to cabinet/drawer location
6. Add relevant tags and entity
7. Save file
8. Print QR label
9. Affix label to file folder tab
10. Place in designated drawer

### Workflow 2: Checking Out for Review

1. Search for the file you need
2. Open file detail page
3. Click **Checkout**
4. Set return date (e.g., 1 week)
5. Note purpose (e.g., "Annual review")
6. Enable reminder
7. Confirm checkout
8. Retrieve physical file from drawer
9. Take the file with you

### Workflow 3: Returning a File

1. Return physical file to its drawer
2. Open RoloDrawer
3. Go to **My Checkouts**
4. Find the file
5. Click **Return**
6. Confirm it's back in drawer
7. Add any notes (optional)
8. Confirm return

### Workflow 4: Moving Files During Reorganization

1. Plan new organization structure
2. Physically move files to new locations
3. For each file:
   - Search in RoloDrawer
   - Click **Move**
   - Select new location/cabinet/drawer
   - Add note: "2026 Reorganization"
   - Confirm move
4. Generate inventory report to verify
5. Print new labels if cabinet IDs changed

### Workflow 5: Finding All Files for an Entity

1. Click **Entities** in navigation
2. Search for entity (e.g., "ACME Corp")
3. Click entity name
4. View list of associated files
5. Click any file to open details
6. Or export list for external use

---

*Last updated: January 2026 - Version 1.0.1*
