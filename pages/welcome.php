<?php
require __DIR__ . '/../assets/config.php';

if (isLoggedIn()) {
  header('Location: dashboard.php');
  exit;
}

// for guest
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['role'] ?? '') === 'guest') {
  session_regenerate_id(true);
  $_SESSION['user'] = ['id' => 0, 'name' => 'Guest', 'role' => 'guest'];
  header('Location: dashboard.php');
  exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Campus Availability</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../Styles/welcome.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">
</head>
<body>
  <main class="welcome">
    <h1>Campus Availability</h1>
    <p>Who are you signing in as?</p>

    <div class="role-grid">
      <form method="post" class="role-card">
        <input type="hidden" name="role" value="guest">
        <i class="ti ti-user"></i>
        <h2>Guest</h2>
        <span>View live availability and reserve resources. No account needed.</span>
        <button type="submit">Continue as Guest</button>
      </form>

      <div class="role-card">
        <i class="ti ti-shield-lock"></i>
        <h2>Admin</h2>
        <span>Manage resources, bookings, and reports. Login required.</span>
        <a href="Login-RegPage.php" class="btn-link">Continue as Admin</a>
      </div>
    </div>
  </main>
</body>
</html>