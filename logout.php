<?php
session_start();
session_unset();
session_destroy();
header("Location: index.php"); // back to login form
exit();
?>
