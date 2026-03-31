<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';

// Step 1: Redirect user to Google's OAuth consent screen
if (isset($_GET['action']) && $_GET['action'] === 'redirect') {
    $_SESSION['oauth_state'] = bin2hex(random_bytes(16));

    $params = http_build_query([
        'client_id'     => GOOGLE_CLIENT_ID,
        'redirect_uri'  => GOOGLE_REDIRECT_URI,
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'state'         => $_SESSION['oauth_state'],
        'access_type'   => 'online',
        'prompt'        => 'select_account',
    ]);

    header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $params);
    exit();
}

// Step 2: Handle the callback from Google
if (!isset($_GET['code'])) {
    header('Location: ' . SITE_URL . '/auth/login.php');
    exit();
}

// Validate state to prevent CSRF
if (!isset($_GET['state']) || !isset($_SESSION['oauth_state']) || $_GET['state'] !== $_SESSION['oauth_state']) {
    die('Invalid OAuth state. Please try again.');
}
unset($_SESSION['oauth_state']);

// Exchange authorization code for access token
$ch = curl_init('https://oauth2.googleapis.com/token');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query([
        'code'          => $_GET['code'],
        'client_id'     => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri'  => GOOGLE_REDIRECT_URI,
        'grant_type'    => 'authorization_code',
    ]),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
]);
$tokenResponse = curl_exec($ch);
$curlError     = curl_error($ch);
curl_close($ch);

if (!$tokenResponse || $curlError) {
    die('Failed to get access token from Google: ' . htmlspecialchars($curlError));
}

$tokenData = json_decode($tokenResponse, true);

if (empty($tokenData['access_token'])) {
    $errorMsg = $tokenData['error_description'] ?? $tokenData['error'] ?? 'Unknown error';
    die('Invalid token response from Google: ' . htmlspecialchars($errorMsg));
}

// Fetch user profile from Google
$ch = curl_init('https://www.googleapis.com/oauth2/v3/userinfo');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $tokenData['access_token']],
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
]);
$profileResponse = curl_exec($ch);
$curlError       = curl_error($ch);
curl_close($ch);

if (!$profileResponse || $curlError) {
    die('Failed to get user profile from Google: ' . htmlspecialchars($curlError));
}

$profile = json_decode($profileResponse, true);

$googleId  = $profile['sub'];
$email     = $profile['email'];
$firstName = $profile['given_name'] ?? '';
$lastName  = $profile['family_name'] ?? '';

$db = getDB();

// Check if user already exists by email
$stmt = $db->prepare("SELECT user_id, fsuu_id, email, password, first_name, last_name, role, contact_number, profile_picture FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    // Existing user — log them in
    $user = $result->fetch_assoc();
    SessionManager::setUser($user);
} else {
    // New user — create account using Google profile
    $fsuu_id = 'GOOGLE-' . $googleId;

    $insert = $db->prepare("INSERT INTO users (fsuu_id, email, password, first_name, last_name, contact_number, role) VALUES (?, ?, '', ?, ?, '', 'student')");
    $insert->bind_param("ssss", $fsuu_id, $email, $firstName, $lastName);

    if (!$insert->execute()) {
        die('Failed to create account. Please try again.');
    }

    $newUserId = $db->insert_id;

    // Create empty medical info
    $medical = $db->prepare("INSERT INTO medical_info (user_id) VALUES (?)");
    $medical->bind_param("i", $newUserId);
    $medical->execute();

    // Fetch the new user and set session
    $stmt2 = $db->prepare("SELECT user_id, fsuu_id, email, password, first_name, last_name, role, contact_number, profile_picture FROM users WHERE user_id = ?");
    $stmt2->bind_param("i", $newUserId);
    $stmt2->execute();
    $user = $stmt2->get_result()->fetch_assoc();
    SessionManager::setUser($user);
}

// Redirect based on role
if (SessionManager::isAdmin()) {
    header('Location: ' . SITE_URL . '/admin/dashboard.php');
} else {
    header('Location: ' . SITE_URL . '/patient/dashboard.php');
}
exit();
