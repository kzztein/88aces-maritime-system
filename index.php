<?php
require_once 'config.php';
if (isLoggedIn()) {
    header('Location: admin/dashboard.php');
} else {
    header('Location: admin/login.php');
}
exit;
