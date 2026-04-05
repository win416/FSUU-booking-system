<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'fsuu_dental_booking');

// Application configuration
define('SITE_NAME', 'FSUU Dental Clinic');
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
define('SITE_URL', $protocol . $host . '/FSUU-booking-system-1');
define('TIMEZONE', 'Asia/Manila');  // Correct timezone for the Philippines
date_default_timezone_set(TIMEZONE);

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS

// Email configuration (Loaded from secrets)
if (file_exists(__DIR__ . '/config.secrets.php')) {
    require_once __DIR__ . '/config.secrets.php';
}

// Appointment rules
define('MAX_BOOKINGS_PER_DAY', 20);
define('MIN_HOURS_BEFORE_CANCEL', 24);
define('SESSION_TIMEOUT', 1800); // 30 minutes in seconds

// DELETE OR COMMENT OUT THIS LINE (Line 31 in your image):
// define('SMTP_FROM_NAME', 'FSUU Dental Clinic'); 

define('OTP_EXPIRY_MINUTES', 5);

// KEEP THIS PART (Lines 34-36):
if (!defined('SMTP_FROM_NAME')) {
    define('SMTP_FROM_NAME', 'FSUU Dental Clinic');
}

// Google OAuth — real credentials loaded from gitignored config.secrets.php
// To create credentials: https://console.cloud.google.com/
// Redirect URI: http://localhost/FSUU-booking-system-1/auth/google_auth.php
if (!defined('GOOGLE_CLIENT_ID'))     define('GOOGLE_CLIENT_ID',     'YOUR_GOOGLE_CLIENT_ID');
if (!defined('GOOGLE_CLIENT_SECRET')) define('GOOGLE_CLIENT_SECRET', 'YOUR_GOOGLE_CLIENT_SECRET');
define('GOOGLE_REDIRECT_URI', SITE_URL . '/auth/google_auth.php');
