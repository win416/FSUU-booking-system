<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'fsuu_dental_booking');

// Application configuration
define('SITE_NAME', 'FSUU Dental Clinic');
define('SITE_URL', 'http://localhost/FSUU-booking-system-1');
define('TIMEZONE', 'Asia/Manila');  // Correct timezone for the Philippines
date_default_timezone_set(TIMEZONE);

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS

// Email configuration (Gmail SMTP with App Password)
// 1. Enable 2-Step Verification on your Gmail account.
// 2. Go to https://myaccount.google.com/apppasswords and generate an App Password.
// 3. Replace the values below with your actual Gmail address and the generated App Password.
define('SMTP_HOST',      'smtp.gmail.com');
define('SMTP_PORT',      587);
define('SMTP_USER',      'your-email@gmail.com');   // ← your Gmail address
define('SMTP_PASS',      'your-app-password');       // ← 16-char Gmail App Password
define('SMTP_FROM_NAME', 'FSUU Dental Clinic');

// OTP verification
define('OTP_EXPIRY_MINUTES', 15);  // code expires after N minutes
define('OTP_MAX_ATTEMPTS',    5);  // wrong-code attempts before lockout

// Appointment rules
define('MAX_BOOKINGS_PER_DAY', 20);
define('MIN_HOURS_BEFORE_CANCEL', 24);
define('SESSION_TIMEOUT', 1800); // 30 minutes in seconds
?>