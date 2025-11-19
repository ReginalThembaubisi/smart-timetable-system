<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
	header('Location: admin/login.php');
	exit;
}
header('Location: admin/index.php');
exit;
?>


