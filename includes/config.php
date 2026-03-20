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

// Email configuration (for Gmail)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your-email@gmail.com');
define('SMTP_PASS', 'your-app-password'); // Use App Password for Gmail

// Appointment rules
define('MAX_BOOKINGS_PER_DAY', 20);
define('MIN_HOURS_BEFORE_CANCEL', 24);
define('SESSION_TIMEOUT', 1800); // 30 minutes in seconds

define('SMTP_FROM_NAME', 'FSUU Dental Clinic');
define('OTP_EXPIRY_MINUTES', 5);
define('CLINIC_EMAIL', SMTP_USER); // Dental clinic contact email