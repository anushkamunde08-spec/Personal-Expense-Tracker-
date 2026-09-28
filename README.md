# Personal Expense Tracker (PHP + MySQL for XAMPP)

A complete, fully functional, modern Personal Expense Tracker web application built using native **PHP 8+**, **MySQL/MariaDB**, **Bootstrap 5**, and **Chart.js**, designed to run out-of-the-box on **XAMPP** (Apache + MySQL + phpMyAdmin).

---

## Technical Stack
- **Backend**: Native PHP 8+ (Procedural / Modular OOP with **PDO & Prepared Statements**).
- **Database**: MySQL / MariaDB via phpMyAdmin.
- **Frontend**: HTML5, CSS3, Bootstrap 5 (CSS & JS), Bootstrap Icons, Vanilla JavaScript.
- **Charts**: Chart.js for analytics, trend lines, and category distribution donut charts.
- **Authentication**: Session-based auth (`$_SESSION`) with BCrypt password hashing (`password_hash` / `password_verify`).
- **Security**: CSRF tokens on all POST forms, output escaping via `htmlspecialchars()`, sanitized file uploads, session ID regeneration.

---

## 🚀 Quick Setup & Installation Guide

### Step 1: Ensure XAMPP is Running
1. Open **XAMPP Control Panel**.
2. Start both **Apache** and **MySQL** modules.

### Step 2: Import the Database Schema
1. Open your browser and navigate to phpMyAdmin: `http://localhost/phpmyadmin`
2. Click on the **Import** tab.
3. Click **Choose File** and select `database/schema.sql` from this project folder.
4. Click **Import** (or **Go**).
   - This automatically creates the `expense_tracker` database, sets up all normalized tables, foreign key constraints, default categories (Food, Transport, Salary, Rent, etc.), and seeds demo sample data.

### Step 3: Deploy Project Files
Copy the `expense-tracker` project folder into your XAMPP `htdocs` directory:
```
C:\xampp\htdocs\expense-tracker
```

### Step 4: Verify Database Configuration
Open `config/config.php` and confirm default XAMPP credentials match:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'expense_tracker');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### Step 5: Launch the Application
Open your web browser and navigate to:
```
http://localhost/expense-tracker
```

---

## 🔑 Demo Account Credentials
The imported database includes a pre-configured demo user with pre-seeded transactions and budget targets:

- **Email**: `demo@example.com`
- **Password**: `Password123!`

You can also register a new account on the registration page (`auth/register.php`).

---

## ✨ Features & Application Architecture

### 1. Authentication & Guarded Sessions
- **Registration**: Name, email, password hashing, currency preference selection.
- **Login**: Email + password verification, session initialization, "Remember Me" option.
- **Logout**: Complete session destruction and cookie invalidation.
- **Forgot Password**: Token-based password reset flow (`auth/forgot-password.php` & `auth/reset-password.php`), displaying simulated reset links on-screen for local XAMPP environments without an active mail server.

### 2. Interactive Dashboard
- Summary KPI Cards: Total Income, Total Expenses, Net Balance, This Month's Spending.
- **Budget Alert Cards**: Dynamic warnings when category spending reaches or exceeds 80% / 100% of monthly limits.
- **Visual Analytics**:
  - Doughnut chart: Current month's expense distribution.
  - Bar chart: 6-month Income vs. Expense trends.
- Recent Transactions list with category badges and receipt links.

### 3. Expense & Income CRUD Management
- Add, Edit, and Delete transactions with Amount, Category, Date, Payment Method (Cash, Card, UPI, Bank Transfer), Description, and optional Receipt Image upload.
- **Filters & Search**: Search-as-you-type description filter, category filter, transaction type filter, date range filter, sorting by date and amount.
- **Pagination**: Server-side paginated list with preserved query string parameters.

### 4. Category Management
- Pre-seeded system categories + support for custom user-created categories.
- Assign custom color badges and Bootstrap icons.
- **Deletion Guard**: Prevents deletion of categories containing existing transactions.

### 5. Budgeting & Progress Indicators
- Set/edit monthly budget targets per category.
- Color-coded progress bars (Green < 80%, Yellow 80-100%, Red > 100%).

### 6. Reports, Analytics & CSV Export
- Monthly & yearly trend line charts.
- Category breakdown pie chart.
- **CSV Data Export**: Download full transaction logs with custom filter support (`Content-Type: text/csv`).

### 7. User Profile & Application Settings
- Profile picture image upload & avatar preview.
- Change password with current password verification.
- Currency symbol preference selection ($ / € / £ / ₹ / ¥ / etc.).
- Complete CSV backup export.
- Account deletion option with explicit password confirmation modal.

### 8. UX Enhancements
- **Dark Mode**: Persistent dark mode toggle saved in `localStorage`.
- **Responsive Layout**: Mobile-friendly sidebar toggle and responsive tables.
- **Flash Messages**: Toast / Bootstrap dismissible session alerts for success/warning/error feedback.

---

## 🔒 Security Summary
- **SQL Injection Prevention**: 100% of database queries use PDO prepared statements with strict parameter binding.
- **Password Security**: Passwords hashed with standard `password_hash($pass, PASSWORD_BCRYPT)`.
- **CSRF Protection**: Form submit verification via token generation (`verify_csrf_token()`).
- **XSS Prevention**: Output escaping using `e()` helper wrapping `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
- **File Upload Protection**: Verification of MIME type, file size limit (5MB), extension whitelist (`jpg`, `jpeg`, `png`, `pdf`), and randomized filename generation to prevent path traversal attacks.
