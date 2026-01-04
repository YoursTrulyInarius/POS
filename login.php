<?php
session_start();
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $username = trim($_POST['username']);
  $password = trim($_POST['password']);

  $stmt = $conn->prepare("SELECT id, password FROM users WHERE username=?");
  $stmt->bind_param("s", $username);
  $stmt->execute();
  $stmt->store_result();

  if ($stmt->num_rows > 0) {
    $stmt->bind_result($id, $dbPassword);
    $stmt->fetch();

    if ($password === $dbPassword) { // plain password match
      $_SESSION['user_id'] = $id;
      $_SESSION['username'] = $username;
      echo "<script>alert('Login successful!'); window.location.href='dashboard.php';</script>";
    } else {
      echo "<script>alert('Invalid password!'); window.location.href='index.php';</script>";
    }
  } else {
    echo "<script>alert('User not registered yet!'); window.location.href='index.php';</script>";
  }

  $stmt->close(); $conn->close();
}
?>
