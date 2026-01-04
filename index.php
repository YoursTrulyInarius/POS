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

        if ($password === $dbPassword) { // plain password
            $_SESSION['user_id'] = $id;
            $_SESSION['username'] = $username;
            echo "<script>alert('Welcome aboard, $username!'); window.location.href='dashboard.php';</script>";
        } else {
            echo "<script>alert('Wrong password!'); window.location.href='index.php';</script>";
        }
    } else {
        echo "<script>alert('User not found! Please register.'); window.location.href='register.php';</script>";
    }

    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Sonjeb Gwapo sakatanan</title>
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
    input {
      width:100%; padding:12px; margin:10px 0;
      border:none; border-radius:8px;
      background:rgba(0,0,0,0.6); color:#fff; font-size:14px;
    }
    input:focus { outline:2px solid #ffcc00; }
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
</head>
<body>
  <video autoplay loop muted playsinline class="video-bg">
    <source src="g5.mp4" type="video/mp4">
  </video>
  <div class="overlay"></div>

  <div class="form-container">
    <h2>Login</h2>
    <form method="POST" action="index.php">
      <input type="text" name="username" placeholder="Enter Username" required>
      <input type="password" name="password" placeholder="Enter Password" required>
      <button type="submit">Enter Grand Line</button>
    </form>
    <p>Not a crew member yet? <a href="register.php">Register</a></p>
  </div>
</body>
</html>
