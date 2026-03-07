<?php
require_once 'Config.php';
if (isLoggedIn()) {
    header('Location: admin/Dashboard.php');
} else {
    header('Location: admin/Login.php');
}
exit;
