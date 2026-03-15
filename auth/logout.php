<?php
require_once '../includes/session.php';

SessionManager::logout();

header('Location: ' . SITE_URL . '/index.php');
exit();
?>