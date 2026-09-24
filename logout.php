<?php
require_once __DIR__ . '/includes/functions.php';
session_unset();
session_destroy();

session_start();
$_SESSION['notif_sound'] = 'logout';

header('Location: login.php');
exit;