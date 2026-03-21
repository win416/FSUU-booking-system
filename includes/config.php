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

// Google OAuth configuration
// To get these credentials:
// 1. Go to https://console.cloud.google.com/
// 2. Create a project → APIs & Services → Credentials → Create OAuth 2.0 Client ID
// 3. Set Authorized redirect URI to: http://localhost/FSUU-booking-system-1/auth/google_auth.php
define('GOOGLE_CLIENT_ID',     '957700597513-3rmp02pd4md0vvsrirju98ledluc8o68.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'YOUR_GOOGLE_CLIENT_SECRET');
define('GOOGLE_REDIRECT_URI',  SITE_URL . '/auth/google_auth.php');