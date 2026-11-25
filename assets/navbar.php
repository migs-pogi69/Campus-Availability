<?php
$current = basename($_SERVER['PHP_SELF']);

$links = [
  ['label' => 'Dashboard', 'href' => 'dashboard.php',    'admin_only' => false,
   'icon' => '<svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>'],
  ['label' => 'Resources', 'href' => 'resources.php', 'admin_only' => true,
   'icon' => '<svg viewBox="0 0 24 24"><path d="M12 2l9 5v10l-9 5-9-5V7z"/><path d="M12 22V12M3 7l9 5 9-5"/></svg>'],
  ['label' => 'Bookings',  'href' => 'bookings.php',  'admin_only' => false,
   'icon' => '<svg viewBox="0 0 24 24"><rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4h6v3H9zM9 12h6M9 16h6"/></svg>'],
  ['label' => 'Reports',   'href' => 'reports.php',   'admin_only' => true,
   'icon' => '<svg viewBox="0 0 24 24"><path d="M6 20V10M12 20V4M18 20v-7"/></svg>'],
];
?>
<?php /** @var array $user**/ ?>
<header class="navbar">
  <div class="nav-left">
    <a class="brand" href="dashboard.php">
      <svg viewBox="0 0 24 24" class="brand-icon"><path d="M3 9l9-5 9 5M5 9v9M9 9v9M15 9v9M19 9v9M3 21h18"/></svg>
      <span>Campus Availability</span>
    </a>

    <nav class="nav-links">
      <?php foreach ($links as $link): ?>
        <?php if ($link['admin_only'] && !isAdmin()) continue; ?>
        <a href="<?= $link['href'] ?>"
           class="nav-link <?= $current === $link['href'] ? 'active' : '' ?>">
          <?= $link['icon'] ?>
          <?= htmlspecialchars($link['label']) ?>
        </a>
      <?php endforeach; ?>
    </nav>
  </div>

  <div class="nav-right">
    <button class="bell" aria-label="Notifications">
      <svg viewBox="0 0 24 24"><path d="M6 8a6 6 0 0112 0c0 7 3 9 3 9H3s3-2 3-9M10 21a2 2 0 004 0"/></svg>
      <span class="bell-dot"></span>
    </button>
    <span class="user-name"><?= htmlspecialchars($user['name']) ?></span>
    <span class="avatar"><?= htmlspecialchars($user['initials']) ?></span>
    <a href="../assets/logout.php" class="logout-link">Logout</a>
    <svg viewBox="0 0 24 24" class="chevron"><path d="M6 9l6 6 6-6"/></svg>
  </div>
</header>