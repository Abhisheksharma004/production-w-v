
# Production Management Web Application

## 📌 Project Overview

**Production Management Web Application** is a PHP-based web system designed to manage and monitor production workflows in a manufacturing or workshop environment. The system helps track materials, production stages, line-wise output, and generate detailed production reports with role-based access control.

---

## 🚀 Features

* User Authentication (Admin / Stage / Line-based users)
* Material Inward Management
* Part & Stage Management
* Line-wise Production Tracking
* Production & Line Production Reports
* Export Reports (CSV/Excel-ready)
* Dashboard for real-time monitoring

---

## 🛠️ Tech Stack

* **Backend:** PHP (Core PHP)
* **Frontend:** HTML, CSS
* **Database:** MySQL
* **Server:** Apache (XAMPP / WAMP / LAMP)

---

## 📂 Project Structure

```
production-w-v/
│── config/              # Database & app configuration
│── includes/            # Common PHP includes (DB, auth, helpers)
│── css/                 # Stylesheets
│── images/              # Images & assets
│── index.php            # Entry point
│── login.php            # User login
│── dashboard.php        # Main dashboard
│── material-in.php      # Material inward module
│── part-management.php  # Part management
│── stages-management.php# Stage configuration
│── production-report.php# Production reports
│── export-report.php    # Export functionality
```

---

## ⚙️ Setup Guide

### 1️⃣ Prerequisites

* PHP >= 7.4
* MySQL >= 5.7
* Apache Server
* Git

### 2️⃣ Clone the Repository

```bash
git clone https://github.com/Abhisheksharma004/production-w-v.git
cd production-w-v
```

### 3️⃣ Database Configuration

* Create a MySQL database (e.g., `production_db`)
* Update database credentials in:

```
config/db.php
```

### 4️⃣ Import Database Schema

```sql
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100),
  password VARCHAR(255),
  role VARCHAR(50)
);

CREATE TABLE materials (
  id INT AUTO_INCREMENT PRIMARY KEY,
  material_name VARCHAR(100),
  quantity INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE production (
  id INT AUTO_INCREMENT PRIMARY KEY,
  line_name VARCHAR(50),
  stage_name VARCHAR(50),
  output_qty INT,
  production_date DATE
);
```

*(Extend schema as per project needs)*

---

## 🔐 Security Best Practices

* Use **password_hash()** and **password_verify()**
* Implement prepared statements (PDO / MySQLi)
* Restrict direct access to config files
* Add `.gitignore` to exclude sensitive data

Example `.gitignore`:

```
/config/db.php
/vendor/
.env
```

---

## 🧪 Testing (Basic)

* Manual testing for forms and reports
* Recommended: Add **PHPUnit** for unit testing

---

## 🔄 CI/CD (Recommended)

Use **GitHub Actions** for automated checks.

Example workflow:

```yaml
name: PHP CI
on: [push]
jobs:
  build:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
```

---

## 📜 License

This project is licensed under the **MIT License**.

```
MIT License
Copyright (c) 2026 Abhishek Sharma
```

---

## 👨‍💻 Author

**Abhishek Sharma**
Full-Stack Developer
📍 Greater Noida, India

---

## ⭐ Future Enhancements

* REST API integration
* Role-based dashboards
* Graphical analytics
* PLC / IoT data integration
* Responsive UI redesign

---

> ✅ Feel free to fork, improve, and contribute to this project.
