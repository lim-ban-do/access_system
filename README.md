# Smart Attendance System (SAS)

A modern, responsive, and secure web-based student attendance system featuring offline QR code generation, real-time webcam QR scanning, manual registry checks, and exports to PDF, Excel, and Print. Built using pure PHP, MySQL (PDO), HTML, CSS, JavaScript, and Bootstrap 5.

---

## 🌟 Key Features

- **Double Tracking Registry**: Support for both manual checkbox checklists and fast camera QR code scanners.
- **Offline Vector QR Codes**: Automatically generates vector-based SVG QR codes for students on creation. No internet connection or third-party APIs needed.
- **Secure Authentication**: Built-in session guards, secure password hashing, and session timeout parameters.
- **Role-Based Access**: Admins have full access (including User Management), while Teachers have regular access.
- **Dynamic Dashboard**: Responsive metrics, live activity log, and interactive Chart.js widgets.
- **Advanced Reports**: Filter reports by Class, Student, and Date Ranges, then export directly to PDF, Excel (CSV), or Print.
- **Modern UI**: Full-fledged dashboard using Bootstrap 5, custom Google Fonts (Outfit), CSS animations, and SweetAlert2 alerts.

---

## 📂 Project Structure

```text
smart-attendance-system/
├── assets/
│   ├── css/
│   │   └── style.css          # Custom style, themes, animations, print media rules
│   ├── js/
│   │   └── main.js           # Core sidebar controls and tooltip settings
│   ├── images/                # App asset images
│   └── qr/                    # Generated student QR codes (SVG format)
├── config/
│   └── database.php           # PDO connection configuration
├── includes/
│   ├── auth.php               # Authentication guard and session timeout settings
│   ├── functions.php          # Core security sanitization, XSS, CSRF, & QR gen helpers
│   ├── header.php             # HTML header layout and topbar navigation
│   ├── sidebar.php            # Active state navigation links with role filter
│   ├── footer.php             # Layout closing tags, scripts, and libraries
│   └── QRCode.php             # Self-contained offline QR Code generator class
├── uploads/                   # Optional student uploads directory
├── login.php                  # Secure login gateway
├── logout.php                 # Destroys active sessions
├── dashboard.php              # Systems analytics and Chart.js dashboards
├── users.php                  # User registry CRUD panel (Admin Only)
├── students.php               # Student registry CRUD and QR preview/download
├── classes.php                # Class levels registry CRUD
├── attendance.php             # Manual checklists and Webcam QR scanner
├── reports.php                # Custom reports filtering and exports
├── profile.php                # Account details updates
├── change_password.php        # Password change panel
└── database.sql               # MySQL database schema and sample accounts
```

---

## 🛠️ Installation Instructions

### Prerequisites
1. **PHP 8.0+** (with standard PDO extensions enabled).
2. **MySQL / MariaDB**.
3. A local web server stack such as **XAMPP**, **WampServer**, or **Laragon**.
4. A web camera (for using the QR code scanning functionality).

---

### Step-by-Step Setup

#### 1. Copy Project Files
Place the `smart-attendance-system` directory inside your web server's root folder:
- **XAMPP**: `C:\xampp\htdocs\smart-attendance-system`
- **WampServer**: `C:\wamp64\www\smart-attendance-system`

#### 2. Import Database Schema
1. Open your browser and navigate to **phpMyAdmin** (`http://localhost/phpmyadmin`).
2. Create a new database named `smart_attendance_db`.
3. Select the database, go to the **Import** tab.
4. Choose the `database.sql` file located in the project root and click **Go/Import**.
*(Alternatively, run the SQL script inside `database.sql` using your MySQL command line client).*

#### 3. Database Connection Config
The system is configured to connect to `localhost` using `root` and an empty password `""` by default. If your MySQL credentials differ:
1. Open `config/database.php` in a text editor.
2. Edit the database credentials accordingly:
   ```php
   define('DB_HOST', 'YOUR_HOST');
   define('DB_USER', 'YOUR_USER');
   define('DB_PASS', 'YOUR_PASSWORD');
   define('DB_NAME', 'smart_attendance_db');
   ```

#### 4. Launch System
Open your web browser and go to:
`http://localhost/smart-attendance-system/login.php`

---

## 🔐 Default Sample Accounts

Use the following default accounts to sign in and test the system:

### 1. Admin Account (Full access: view settings + user management)
- **Username**: `admin`
- **Password**: `adminpassword`

### 2. Teacher Account (Standard access: checklist, scanner, classes, reports)
- **Username**: `teacher`
- **Password**: `teacherpassword`

---

## 🔒 Security Configurations Implemented

- **Prepared Statements**: All database operations use PDO prepared statement queries, protecting the system from SQL injection.
- **CSRF Tokens**: Form submissions generate a session-bound cryptographically secure token that is verified on POST.
- **XSS Escaping**: A helper function `e()` is used on all dynamic output fields to escape user input.
- **Secure Password Hashing**: Passwords are securely hashed in the database using the bcrypt algorithm.
- **Session Protections**: Cookie parameters set `httponly` and `samesite=Strict`. An inactivity timeout auto-terminates idle sessions after 30 minutes.
