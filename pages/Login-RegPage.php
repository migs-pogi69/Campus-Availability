<?php
require __DIR__ . '/../assets/config.php';
require __DIR__ . '/../assets/db.php';

if (isLoggedIn()) {
  header('Location: dashboard.php');
  exit;
}

$error = '';
$success = '';
$showRegister = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? 'login';

  /*Register (admin only, needs the access code)*/
  if ($action === 'register') {
    $showRegister = true;
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';
    $code     = $_POST['code'] ?? '';

    
    if (!hash_equals(ADMIN_SIGNUP_CODE, $code)) {
      $error = 'Invalid admin access code.';
    } elseif ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $error = 'Please enter your name and a valid email.';
    } elseif (strlen($password) < 8) {
      $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
      $error = 'Passwords do not match.';
    } else {
      $check = $pdo->prepare('SELECT id FROM users WHERE email = ?');
      $check->execute([$email]);

      if ($check->fetch()) {
        $error = 'That email is already registered.';
      } else {
        $ins = $pdo->prepare(
          'INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, "admin")'
        );
        $ins->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);

        $showRegister = false;
        $success = 'Admin account created. You can log in now.';
      }
    }
  }

  /* ---------- Login (admins only) ---------- */
  if ($action === 'login') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare(
      'SELECT id, name, password_hash, role FROM users WHERE email = ? AND role = "admin"'
    );
    $stmt->execute([$email]);
    $row = $stmt->fetch();

    if ($row && password_verify($password, $row['password_hash'])) {
      session_regenerate_id(true);
      $_SESSION['user'] = [
        'id'   => $row['id'],
        'name' => $row['name'],
        'role' => 'admin',
      ];
      header('Location: dashboard.php');
      exit;
    }
    $error = 'Incorrect email or password.';
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campus Availability</title>
    <link rel="stylesheet" href="../Styles/logreg.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">
</head>
<body>
    <div class="container <?= $showRegister ? 'active' : '' ?>">

        <div class="form-box login">
            <form method="post">
                <input type="hidden" name="action" value="login">
                <h1>Admin Login</h1>

                <?php if ($error && !$showRegister): ?>
                  <div class="form-msg error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                  <div class="form-msg ok"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>

                <div class="input-box">
                    <input type="email" name="email" placeholder="Email" required>
                    <i class="ti ti-mail"></i>
                </div>
                <div class="input-box">
                    <input type="password" name="password" placeholder="Password" required>
                    <i class="ti ti-lock"></i>
                </div>
                <div class="forgot-link">
                    <a href="#">Forgot password</a>
                </div>
                <button type="submit" class="btn">Login</button>
                <button type="submit" name="action" value="guest" class="guest-link" formnovalidate>
                    <a href="welcome.php" class="guest-link">← Back</a>
                </button>
            </form>
        </div>

        <div class="form-box register">
            <form method="post">
                <input type="hidden" name="action" value="register">
                <h1>Admin Registration</h1>

                <?php if ($error && $showRegister): ?>
                  <div class="form-msg error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <div class="input-box">
                    <input type="text" name="name" placeholder="Full name" required
                           value="<?= $showRegister ? htmlspecialchars($_POST['name'] ?? '') : '' ?>">
                    <i class="ti ti-user"></i>
                </div>
                <div class="input-box">
                    <input type="email" name="email" placeholder="Email" required
                           value="<?= $showRegister ? htmlspecialchars($_POST['email'] ?? '') : '' ?>">
                    <i class="ti ti-mail"></i>
                </div>
                <div class="input-box">
                    <input type="password" name="password" placeholder="Password (min 8 characters)" required>
                    <i class="ti ti-lock"></i>
                </div>
                <div class="input-box">
                    <input type="password" name="confirm" placeholder="Confirm Password" required>
                    <i class="ti ti-lock"></i>
                </div>
                <div class="input-box">
                    <input type="password" name="code" placeholder="Admin access code" required>
                    <i class="ti ti-key"></i>
                </div>
                <button type="submit" class="btn">Register</button>
            </form>
        </div>

        <div class="toggle-box">
            <div class="toggle-panel toggle-left">
                <h1>Hello, Welcome!</h1>
                <p>Don't have an account?</p>
                <button type="button" class="btn register-btn">Register</button>
            </div>

            <div class="toggle-panel toggle-right">
                <h1>Welcome Back!</h1>
                <p>Already have an account?</p>
                <button type="button" class="btn login-btn">Login</button>
            </div>
        </div>
    </div>

    <script src="../javascript/logreg.js" defer></script>
</body>
</html>