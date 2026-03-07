<?php
require_once 'config.php';
if (isLoggedIn()) {
    header('Location: admin/Dashboard.php');
} else {
    header('Location: admin/Login.php');
}
exit;
