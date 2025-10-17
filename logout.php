<?php
include 'includes/log_helper.php';
session_start();

log_login_event($mysqli, $_SESSION['user_id'], $_SESSION['role'], false);
session_destroy();
header("Location: login.php");
exit;
