<?php
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);

    if ($password !== $confirm_password) {
        echo "<script>alert('Passwords do not match!'); window.location.href='register.php';</script>";
        exit();
    }

    // Check if username or email already exists
    $check = $conn->prepare("SELECT id FROM users WHERE username=? OR email=?");
    $check->bind_param("ss", $username, $email);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        echo "<script>alert('Username or Email already taken!'); window.location.href='register.php';</script>";
        exit();
    }

    // Save plain password
    $stmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $username, $email, $password);

    if ($stmt->execute()) {
        echo "<script>alert('Registration successful! Time to log in.'); window.location.href='index.php';</script>";
    } else {
        echo "<script>alert('Registration failed: " . $stmt->error . "'); window.location.href='register.php';</script>";
    }

    $stmt->close();
    $check->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>One Piece Register</title>
  <style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body, html { height:100%; font-family:'Trebuchet MS', sans-serif; color:#fff; overflow:hidden; }

    .video-bg {
      position: fixed;
      top: 0; left: 0;
      width: 100%; height: 100%;
      object-fit: cover;
      z-index: -2;
    }
    .overlay {
      position: fixed;
      top:0; left:0;
      width:100%; height:100%;
      background: rgba(0,0,30,0.7);
      z-index:-1;
    }

    .form-container {
      position:absolute;
      top:50%; right:6%;
      transform:translateY(-50%);
      background: rgba(0,0,50,0.5);
      backdrop-filter: blur(6px);
      padding:40px;
      border-radius:12px;
      width:350px;
      text-align:center;
      box-shadow: 0 0 20px rgba(0,180,255,0.7);
    }

    h2 { margin-bottom:20px; color:#00d4ff; text-shadow:0 0 15px #ffcc00; }
    .input-group {
      position: relative;
      width: 100%;
    }
    input {
      width:100%; padding:12px; margin:10px 0;
      border:none; border-radius:8px;
      background:rgba(0,0,0,0.6); color:#fff; font-size:14px;
    }
    input:focus { outline:2px solid #ffcc00; }
    .toggle-eye {
      position: absolute;
      right: 15px;
      top: 50%;
      transform: translateY(-50%);
      cursor: pointer;
      color: #ccc;
      font-size: 18px;
    }
    button {
      background: linear-gradient(90deg,#ff6600,#ffcc00);
      border:none; padding:12px; width:100%;
      color:#000; font-weight:bold; border-radius:8px;
      cursor:pointer; transition:0.3s;
    }
    button:hover { box-shadow:0 0 20px #ffcc00; }
    p { margin-top:15px; font-size:0.9rem; }
    a { color:#00d4ff; text-decoration:none; }
    a:hover { text-shadow:0 0 8px #ffcc00; }
  </style>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
  <video autoplay loop muted playsinline class="video-bg">
    <source src="g5.mp4" type="video/mp4">
  </video>
  <div class="overlay"></div>

  <div class="form-container">
    <h2>Join the Crew</h2>
    <form method="POST" action="register.php">
      <input type="text" name="username" placeholder="Choose Username" required>
      <input type="email" name="email" placeholder="Enter Email" required>

      <div class="input-group">
        <input type="password" id="password" name="password" placeholder="Enter Password" required>
        <i class="fa-solid fa-eye toggle-eye" onclick="togglePassword('password', this)"></i>
      </div>

      <div class="input-group">
        <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm Password" required>
        <i class="fa-solid fa-eye toggle-eye" onclick="togglePassword('confirm_password', this)"></i>
      </div>

      <button type="submit">Set Sail</button>
    </form>
    <p>Already part of the crew? <a href="index.php">Login</a></p>
  </div>

  <script>
    function togglePassword(fieldId, icon) {
      const field = document.getElementById(fieldId);
      if (field.type === "password") {
        field.type = "text";
        icon.classList.remove("fa-eye");
        icon.classList.add("fa-eye-slash");
      } else {
        field.type = "password";
        icon.classList.remove("fa-eye-slash");
        icon.classList.add("fa-eye");
      }
    }
  </script>
</body>
</html>
