<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/appointment_reminders.php';

class SessionManager {
    
    public static function startSession() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    public static function setUser($userData) {
        $_SESSION['user_id']         = $userData['user_id'];
        $_SESSION['fsuu_id']         = $userData['fsuu_id'];
        $_SESSION['email']           = $userData['email'];
        $_SESSION['first_name']      = $userData['first_name'];
        $_SESSION['last_name']       = $userData['last_name'];
        $_SESSION['contact_number']  = $userData['contact_number'] ?? '';
        $_SESSION['role']            = $userData['role'];
        $_SESSION['profile_picture'] = $userData['profile_picture'] ?? null;
        $_SESSION['last_activity']   = time();
    }
    
    public static function isLoggedIn() {
        self::startSession();
        
        // Check if user is logged in
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        
        // Check session timeout
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
            self::logout();
            return false;
        }
        
        // Update last activity
        $_SESSION['last_activity'] = time();
        processAppointmentRemindersIfDue();
        return true;
    }
    
    public static function getUser() {
        self::startSession();
        return [
            'user_id'         => $_SESSION['user_id'] ?? null,
            'fsuu_id'         => $_SESSION['fsuu_id'] ?? null,
            'email'           => $_SESSION['email'] ?? null,
            'first_name'      => $_SESSION['first_name'] ?? null,
            'last_name'       => $_SESSION['last_name'] ?? null,
            'contact_number'  => $_SESSION['contact_number'] ?? null,
            'role'            => $_SESSION['role'] ?? null,
            'profile_picture' => $_SESSION['profile_picture'] ?? null,
        ];
    }
    
    public static function isAdmin() {
        return (self::getUser()['role'] == 'admin');
    }
    
    public static function isDentist() {
        return (self::getUser()['role'] == 'dentist');
    }
    
    public static function isStaff() {
        return (self::getUser()['role'] == 'staff');
    }
    
    public static function isStudent() {
        return (self::getUser()['role'] == 'student');
    }
    
    public static function logout() {
        self::startSession();
        $_SESSION = array();
        session_destroy();
    }
    
    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            header('Location: ' . SITE_URL . '/auth/login.php');
            exit();
        }
    }
    
    public static function requireAdmin() {
        self::requireLogin();
        if (!self::isAdmin()) {
            header('Location: ' . SITE_URL . '/index.php');
            exit();
        }
    }
}

// Initialize session
SessionManager::startSession();
?>
