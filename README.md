FarmFlow MVP — Installation & Setup Guide
Requirements

Before you begin, make sure your system has the following installed:

PHP 8.1 or higher (with pdo, pdo_mysql, mbstring, openssl enabled)
MySQL 8.0+ or MariaDB 10.6+
Apache or Nginx
Composer (optional, for email functionality)
A local server like XAMPP, WAMP, or LAMP
Step 1: Add the Project Files

Place the “farmflow” folder inside your web server directory.

For example:
Windows (XAMPP): /xampp/htdocs/farmflow/
Linux: /var/www/html/farmflow/
Mac (MAMP): /Applications/MAMP/htdocs/farmflow/

Step 2: Create the Database

Open phpMyAdmin or your MySQL client and create a new database named “farmflow”.

Run this SQL:

CREATE DATABASE farmflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

Then import the database files:

mysql -u root -p farmflow < database/schema.sql
mysql -u root -p farmflow < database/seed.sql

Alternatively, you can use phpMyAdmin → Import and select both SQL files.

Step 3: Configure the Application

Open the file config/config.php and update your settings:

Set your database credentials (host, name, user, password)
Set your app URL (e.g. http://localhost/farmflow
)
Add your email and app password if you want email notifications

For Gmail setup:

Enable 2-Step Verification on your Google account
Go to Security → App Passwords
Generate a password for Mail
Paste it into your MAIL_PASSWORD
Step 4: Install PHPMailer (Optional)

If you want email notifications to work, install PHPMailer using Composer:

cd /path/to/farmflow
composer require phpmailer/phpmailer

If you don’t use Composer, download PHPMailer from GitHub and place it inside:

farmflow/vendor/phpmailer/phpmailer/

If you skip this step, the app will still work — emails just won’t be sent.

Step 5: Enable URL Rewrite (Apache Only)

On Linux:

sudo a2enmod rewrite
sudo systemctl restart apache2

On XAMPP:

Open httpd.conf
Find “AllowOverride None”
Change it to “AllowOverride All”
Restart Apache
Step 6: Set Permissions (Linux/Mac Only)

chmod 755 farmflow/
chmod -R 644 farmflow/*.php

Step 7: Open the Application

Go to your browser and visit:

http://localhost/farmflow

Default Login Details

Email: admin@farmflow.com

Password: password123

There are also other users (managers and viewers) included in the seed data.

Make sure you change all default passwords after logging in.

Before Deploying to Production
Change all default passwords
Update APP_URL to your real domain
Set real database credentials
Configure email properly
Turn off error display (display_errors = 0)
Enable HTTPS
Set session timeout
Set up database backups
Project Structure Overview

farmflow/

config/ (configuration files)
core/ (database, auth, RBAC, helpers)
modules/ (features like animals, crops, users)
templates/ (header, footer, layouts)
assets/ (CSS and JS)
auth/ (login/logout)
api/ (AJAX endpoints)
database/ (schema and seed files)
dashboard.php
index.php
.htaccess
Role Permissions

Owner: full access to everything
Manager: can manage assigned farm data but limited deletion
Viewer: read-only access

Notes
You can freely enter animal and crop types — no restriction
To change session timeout, edit SESSION_LIFETIME in config
To add new roles or permissions, update the RBAC configuration
Tech Stack

Backend: PHP (no framework)
Database: MySQL with PDO
Frontend: HTML + Bootstrap
Charts: Chart.js
Email: PHPMailer
Authentication: PHP sessions with CSRF protection
Security: role-based access control and password hashing