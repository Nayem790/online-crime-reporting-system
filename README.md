# Online Crime Reporting & Case Management System

A web-based crime reporting and case management system developed as a academic project.

The system allows citizens to submit crime reports and evidence, track report status, and receive notifications. Police officers can verify reports and manage investigations, while administrators can manage users, crime categories, reports, and system resources.

## Features

### Citizen
- Registration and Login
- Profile Management
- Submit Crime Report
- Upload Evidence
- Track Report Status
- View Report History
- Notifications
- Help & Resources

### Police Officer
- Login
- View and Search Crime Reports
- Verify or Reject Reports
- Manage Investigations
- Add Investigation Remarks
- Update Investigation Status
- Notifications

### Administrator
- Manage Users
- Manage Police Officers
- Manage Crime Categories
- View All Reports
- Dashboard Statistics

## Technologies Used

- PHP
- MySQL
- HTML5
- CSS3
- JavaScript
- Bootstrap
- XAMPP
- phpMyAdmin

## System Architecture

The system follows a three-tier architecture:

1. Presentation Layer
2. Application Layer
3. Data Layer

## Project Structure

online_crime_reporting/
│
├── index.php
├── login.php
├── register.php
├── logout.php
├── database.sql
│
├── config/
├── includes/
├── assets/
├── uploads/
│   └── evidence/
│
├── citizen/
├── police/
└── admin/


## 📸 System Screenshots & Interface Preview

### 1. User & Authentication Portals
* **User Registration:**
  ![User Registration](assets/screenshots/user_reg.png)

* **User Login:**
  ![User Login](assets/screenshots/log_in.png)

### 2. Citizen Portal & Case Tracking
* **Citizen Dashboard:**
  ![Citizen Dashboard](assets/screenshots/user_dashboard.png)

* **Report Tracking Timeline:**
  ![Report Tracking](assets/screenshots/user_report_track.png)

* **Closed Case View:**
  ![Report Closed](assets/screenshots/Report_Closed.png)

* **Post-Closure Status:**
  ![User After Report Closed](assets/screenshots/user_after_report_closed.png)

* **In-System Notifications:**
  ![User Notifications](assets/screenshots/user_notification.png)

* **Help & Resources:**
  ![Help & Resource](assets/screenshots/help%20&%20resource.png)

### 3. Police Officer Portal
* **Officer Dashboard:**
  ![Police Dashboard](assets/screenshots/Police%20dashboard.png)

* **Verified Section:**
  ![Police Verified Section](assets/screenshots/Police_Verified_Section.png)

* **Verify / Reject Triage Workflow:**
  ![Verify Reject Section](assets/screenshots/Police_Verify_Reject%20Section.png)

### 4. Administrator Portal
* **Admin Executive Dashboard:**
  ![Admin Dashboard](assets/screenshots/Admin_Dashboard.png)

* **System-Wide Reports & Filtering:**
  ![System Wide Reports](assets/screenshots/Admin_System_Wide-Reports.png)