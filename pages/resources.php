<?php
require __DIR__ . '/../assets/config.php';
requireLogin();
if (!isAdmin()) { header('Location: dashboard.php'); exit; }
require_once __DIR__ . '/../assets/db.php';

$categories = array_values(array_unique(array_merge(
  ['classroom', 'lab', 'locker', 'projector'],
  $pdo->query('SELECT DISTINCT category FROM resources')->fetchAll(PDO::FETCH_COLUMN))));
$buildings = array_values(array_unique(array_merge(
  ['Mother Rita Building', 'Saint Agustin Building', 'BMC Building'],
  $pdo->query('SELECT DISTINCT building FROM resources')->fetchAll(PDO::FETCH_COLUMN))));
sort($categories);
sort($buildings);
$statuses   = ['available' => 'Available', 'booked' => 'Booked', 'repair' => 'Under Repair'];
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$fq = trim($_GET['q'] ?? '');
$fc = $_GET['category'] ?? '';
$fb = $_GET['building'] ?? '';
$fs = $_GET['status'] ?? '';

$where = 'WHERE name LIKE ? AND category LIKE ? AND building LIKE ? AND status LIKE ?';
$args  = ["%$fq%", "%$fc%", "%$fb%", "%$fs%"];

$perPage = 10;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

$c = $pdo->prepare("SELECT COUNT(*) FROM resources $where");
$c->execute($args);
$total = (int)$c->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));

$st = $pdo->prepare("SELECT * FROM resources $where ORDER BY id LIMIT $perPage OFFSET $offset");
$st->execute($args);
$rows   = $st->fetchAll();
$editId = (int)($_GET['edit'] ?? 0);
$error  = $_SESSION['res_error'] ?? null;
unset($_SESSION['res_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Resources | Campus Availability</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../Styles/style.css">
  <link rel="stylesheet" href="../Styles/resources.css">
</head>
<body>
  <?php include __DIR__ . '/../assets/navbar.php'; ?>

  <main class="res-page">
    <h1>All Resources</h1>
    <p class="sub">Manage rooms, labs and equipment</p>
    <?php if ($error): ?><div class="res-error"><?= $h($error) ?></div><?php endif; ?>

    <form method="get" class="filters">
      <input type="text" name="q" placeholder="Search resources..." value="<?= $h($fq) ?>">
      <select name="category">
        <option value="">All Types</option>
        <?php foreach ($categories as $t): ?>
          <option value="<?= $t ?>" <?= $fc === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="building">
        <option value="">All Buildings</option>
        <?php foreach ($buildings as $b): ?>
          <option <?= $fb === $b ? 'selected' : '' ?>><?= $h($b) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="status">
        <option value="">All Statuses</option>
        <?php foreach ($statuses as $v => $l): ?>
          <option value="<?= $v ?>" <?= $fs === $v ? 'selected' : '' ?>><?= $l ?></option>
        <?php endforeach; ?>
      </select>
      <button>Filter</button>
    </form>

    <div class="res-layout">
      <div class="res-table-box">
        <table>
          <tr><th>Name</th><th>Type</th><th>Building</th><th>Capacity</th><th>Features</th><th>Status</th><th>Actions</th></tr>
          <?php foreach ($rows as $row): ?>
            <?php if ((int)$row['id'] === $editId): ?>
              <tr class="editing">
                <td>
                  <input form="edit-form" name="name" value="<?= $h($row['name']) ?>" required>
                </td>
                <td>
                  <select form="edit-form" name="category">
                    <?php foreach ($categories as $t): ?>
                      <option value="<?= $t ?>" <?= $row['category'] === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
                <td>
                  <select form="edit-form" name="building">
                    <?php foreach ($buildings as $b): ?>
                      <option <?= $row['building'] === $b ? 'selected' : '' ?>><?= $h($b) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
                <td><input form="edit-form" type="number" name="capacity" value="<?= $h($row['capacity']) ?>"></td>
                <td><input form="edit-form" name="features" value="<?= $h($row['features']) ?>"></td>
                <td>
                  <select form="edit-form" name="status">
                    <?php foreach ($statuses as $v => $l): ?>
                      <option value="<?= $v ?>" <?= $row['status'] === $v ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
                <td class="res-actions">
                  <form id="edit-form" method="post" action="../assets/resource-actions.php">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                    <button class="save-sm">Save</button>
                  </form>
                  <a href="resources.php">Cancel</a>
                </td>
              </tr>
            <?php else: ?>
              <tr>
                <td><?= $h($row['name']) ?></td>
                <td><?= $h(ucfirst($row['category'])) ?></td>
                <td><?= $h($row['building']) ?></td>
                <td><?= $h($row['capacity']) ?></td>
                <td><?= $h($row['features']) ?></td>
                <td><span class="res-badge <?= $h($row['status']) ?>"><?= $h($statuses[$row['status']] ?? $row['status']) ?></span></td>
                <td class="res-actions">
                  <a href="resources.php?edit=<?= (int)$row['id'] ?>">✏️</a>
                  <form method="post" action="../assets/resource-actions.php"
                        onsubmit="return confirm('Delete this resource?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                    <button aria-label="Delete">🗑</button>
                  </form>
                </td>
              </tr>
            <?php endif; ?>
          <?php endforeach; ?>
        </table>

        <div class="res-pager">
          <span>Showing <?= $total ? $offset + 1 : 0 ?> to <?= min($offset + $perPage, $total) ?> of <?= $total ?> resources</span>
          <?php for ($i = 1; $i <= $pages; $i++): ?>
            <a href="?<?= $h(http_build_query(array_merge($_GET, ['page' => $i]))) ?>"
               class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
          <?php endfor; ?>
        </div>
      </div>

      <form class="res-form-box" method="post" action="../assets/resource-actions.php">
        <input type="hidden" name="action" value="save">
        <h3>Add Resource</h3>
        <label>Name</label>
        <input type="text" name="name" placeholder="Enter resource name" required>
        <label>Type</label>
        <select name="category">
          <?php foreach ($categories as $t): ?><option value="<?= $t ?>"><?= ucfirst($t) ?></option><?php endforeach; ?>
        </select>
        <label>Building</label>
        <select name="building">
          <?php foreach ($buildings as $b): ?><option><?= $h($b) ?></option><?php endforeach; ?>
        </select>
        <label>Capacity</label>
        <input type="number" name="capacity" min="0" placeholder="Enter capacity">
        <label>Features</label>
        <textarea name="features" placeholder="e.g., Projector, WiFi"></textarea>
        <label>Status</label>
        <select name="status">
          <?php foreach ($statuses as $v => $l): ?><option value="<?= $v ?>"><?= $l ?></option><?php endforeach; ?>
        </select>
        <div class="btns">
          <button class="save">Save</button>
          <button type="reset" class="cancel">Cancel</button>
        </div>
      </form>
    </div>
  </main>
</body>
</html>
