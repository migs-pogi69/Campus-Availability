<?php
require __DIR__ . '/../assets/booking-ui.php';
requireLogin();

/** @var PDO $pdo */
/** @var array $resources */
/** @var array $types */
/** @var array $locations */

$flash = takeFlash();
$now   = date('Y-m-d H:i:s');
$today = date('Y-m-d');

if (!isAdmin()) {

  if (isset($_GET['switch'])) {
    unset($_SESSION['guest_email']);
    header('Location: bookings.php');
    exit;
  }

  if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'lookup') {
    $email = trim($_POST['email'] ?? '');
    if (csrfValid() && filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $_SESSION['guest_email'] = $email;
    } else {
      flash('Please enter a valid email address.', false);
    }
    header('Location: bookings.php');
    exit;
  }

  $guestEmail = $_SESSION['guest_email'] ?? '';
  $all = [];
  if ($guestEmail !== '') {
    $st = $pdo->prepare(BOOKING_SELECT . ' WHERE b.requester_email = ? ORDER BY b.start_time ASC');
    $st->execute([$guestEmail]);
    $all = $st->fetchAll();
  }

  $upcoming = $pending = $past = [];
  foreach ($all as $b) {
    $s = shownStatus($b);
    if ($s === 'conflict') $s = 'pending';
    $b['shown'] = $s;
    if (in_array($s, ['completed', 'expired', 'cancelled', 'rejected'], true)) {
      $past[] = $b;
    } else {
      $upcoming[] = $b;
      if ($s === 'pending') $pending[] = $b;
    }
  }
  $past = array_reverse($past);
  $next = $upcoming[0] ?? null;                  

  $availableNow = array_slice(
    array_values(array_filter($resources, fn($r) => $r['status'] === 'available')), 0, 5
  );
  $recent = array_slice($past, 0, 4);

} else {

  $conflicts = conflictIds($pdo, $now);

  $count = function (string $sql, array $p) use ($pdo): int {
    $st = $pdo->prepare($sql);
    $st->execute($p);
    return (int)$st->fetchColumn();
  };
  $statToday    = $count("SELECT COUNT(*) FROM bookings WHERE DATE(start_time) = ? AND status IN ('pending','conflict','approved','completed')", [$today]);
  $statPending  = $count("SELECT COUNT(*) FROM bookings WHERE status IN ('pending','conflict') AND end_time >= ?", [$now]);
  $statApproved = $count("SELECT COUNT(*) FROM bookings WHERE status = 'approved' AND end_time >= ?", [$now]);
  $statConflict = count($conflicts);

  // Filters (GET)
  $q     = trim($_GET['q'] ?? '');
  $fType = $_GET['type'] ?? '';
  $fLoc  = $_GET['loc'] ?? '';
  $fStat = $_GET['status'] ?? '';
  $fWhen = $_GET['when'] ?? '';

  $where = [];
  $params = [];

  if ($q !== '') {
    $like    = '%' . $q . '%';
    $idGuess = preg_match('/^abk-?(\d+)$/i', $q, $m) ? ((int)$m[1] - 1000) : 0;
    $where[] = '(r.name LIKE ? OR b.requester_name LIKE ? OR b.requester_email LIKE ? OR b.id = ?)';
    array_push($params, $like, $like, $like, $idGuess);
  }
  if ($fType !== '') { $where[] = 'r.category = ?'; $params[] = $fType; }
  if ($fLoc  !== '') { $where[] = 'r.building = ?'; $params[] = $fLoc; }

  switch ($fStat) {
    case 'pending':
      $where[] = "b.status IN ('pending','conflict') AND b.end_time >= ?"; $params[] = $now; break;
    case 'approved':
      $where[] = "b.status = 'approved' AND b.end_time >= ?"; $params[] = $now; break;
    case 'completed':
      $where[] = "b.status = 'approved' AND b.end_time < ?"; $params[] = $now; break;
    case 'rejected':
    case 'cancelled':
      $where[] = 'b.status = ?'; $params[] = $fStat; break;
    case 'conflict':
      $ids = $conflicts ?: [0];
      $where[] = 'b.id IN (' . implode(',', array_map('intval', $ids)) . ')';
      break;
  }

  if ($fWhen === 'today') {
    $where[] = 'DATE(b.start_time) = ?'; $params[] = $today;
  } elseif ($fWhen === 'week') {
    $where[] = 'DATE(b.start_time) BETWEEN ? AND ?';
    $params[] = date('Y-m-d', strtotime('monday this week'));
    $params[] = date('Y-m-d', strtotime('sunday this week'));
  }

  $sql = BOOKING_SELECT
       . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
       . ' ORDER BY b.id DESC LIMIT 200';
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll();
}

function sel($a, $b): string { return (string)$a === (string)$b ? 'selected' : ''; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= isAdmin() ? 'Bookings' : 'My Bookings' ?> | Campus Availability</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../Styles/style.css">
  <link rel="stylesheet" href="../Styles/bookings.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">
</head>
<body class="bk-page">
  <?php include __DIR__ . '/../assets/navbar.php'; ?>

<?php if (isAdmin()): ?>
  <!-- ADMIN  -->
  <main class="container">

    <div class="gb-head">
      <div>
        <h1>Bookings</h1>
        <p>View, track, and manage resource reservations.</p>
      </div>
      <a href="new-booking.php" class="gb-cta">+ New booking</a>
    </div>

    <?php if ($flash): ?>
      <div class="alert alert-<?= $flash['ok'] ? 'success' : 'error' ?>"><?= e($flash['msg']) ?></div>
    <?php endif; ?>

    <section class="stats">
      <div class="stat-card">
        <div class="stat-icon purple"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M8 2v4M16 2v4M3 10h18"/></svg></div>
        <div><div class="stat-number purple"><?= $statToday ?></div><div class="stat-label">Total today</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon orange"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></div>
        <div><div class="stat-number orange"><?= $statPending ?></div><div class="stat-label">Pending requests</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon green"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M8 12l3 3 5-6"/></svg></div>
        <div><div class="stat-number green"><?= $statApproved ?></div><div class="stat-label">Approved</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon red"><svg viewBox="0 0 24 24"><path d="M12 3l9 16H3z"/><path d="M12 10v4M12 17h.01"/></svg></div>
        <div><div class="stat-number red"><?= $statConflict ?></div><div class="stat-label">Conflicts to resolve</div></div>
      </div>
    </section>

    <form class="toolbar" method="get">
      <label class="search-box">
        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
        <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search by resource, user or booking ID">
      </label>

      <select name="type" class="select" onchange="this.form.submit()">
        <option value="">All Types</option>
        <?php foreach ($types as $t): ?>
          <option value="<?= e($t) ?>" <?= sel($fType, $t) ?>><?= e(ucfirst($t)) ?></option>
        <?php endforeach; ?>
      </select>

      <select name="loc" class="select" onchange="this.form.submit()">
        <option value="">All Locations</option>
        <?php foreach ($locations as $l): ?>
          <option value="<?= e($l) ?>" <?= sel($fLoc, $l) ?>><?= e($l) ?></option>
        <?php endforeach; ?>
      </select>

      <select name="status" class="select" onchange="this.form.submit()">
        <option value="">All Statuses</option>
        <?php foreach (['pending', 'approved', 'conflict', 'completed', 'rejected', 'cancelled'] as $s): ?>
          <option value="<?= $s ?>" <?= sel($fStat, $s) ?>><?= e(STATUS_LABELS[$s]) ?></option>
        <?php endforeach; ?>
      </select>

      <select name="when" class="select" onchange="this.form.submit()">
        <option value="">Any date</option>
        <option value="today" <?= sel($fWhen, 'today') ?>>Today</option>
        <option value="week"  <?= sel($fWhen, 'week') ?>>This week</option>
      </select>
    </form>

    <div class="table-card">
      <div class="table-scroll">
        <table class="bk-table">
          <thead>
            <tr>
              <th>ID</th><th>Resource</th><th>Date</th><th>Time</th>
              <th>Booked by</th><th>Status</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="7" class="empty-cell">No bookings match your filters.</td></tr>
          <?php endif; ?>

          <?php foreach ($rows as $b):
            $id   = (int)$b['id'];
            $s    = shownStatus($b, in_array($id, $conflicts, true));
            $open = in_array($s, ['pending', 'conflict'], true);
          ?>
            <tr>
              <td class="muted"><?= e(bookingCode($id)) ?></td>
              <td><strong><?= e($b['resource_name']) ?></strong><br><span class="muted"><?= e($b['building']) ?></span></td>
              <td><?= e(fmtDay($b['start_time'])) ?></td>
              <td><?= e(fmtRange($b['start_time'], $b['end_time'])) ?></td>
              <td><strong><?= e($b['requester_name']) ?></strong><br><span class="muted"><?= e($b['purpose']) ?></span></td>
              <td><span class="status-tag status-<?= e($s) ?>"><?= e(STATUS_LABELS[$s] ?? ucfirst($s)) ?></span></td>
              <td>
                <div class="bk-actions">
                  <?php if ($open): ?>
                    <?php actionForm($id, 'approve', 'Approve', 'bk-btn approve'); ?>
                    <?php actionForm($id, 'reject', 'Reject', 'bk-btn reject', 'Reject this request?'); ?>
                  <?php elseif (canChange($b)): ?>
                    <a class="bk-btn outline" href="new-booking.php?edit=<?= $id ?>">Reschedule</a>
                  <?php endif; ?>
                  <button type="button" class="bk-btn" data-toggle-detail="detail-<?= $id ?>">Details</button>
                </div>
              </td>
            </tr>
            <tr class="bk-detail" id="detail-<?= $id ?>" hidden>
              <td colspan="7">
                <div class="bk-detail-grid">
                  <div><span>Email</span><?= e($b['requester_email']) ?></div>
                  <div><span>Attendees</span><?= (int)$b['attendees'] ?></div>
                  <div><span>Purpose</span><?= e($b['purpose']) ?></div>
                  <div><span>Requested</span><?= e(date('M j, g:i A', strtotime($b['created_at']))) ?></div>
                  <?php if (!empty($b['admin_note'])): ?>
                    <div><span>Note</span><?= e($b['admin_note']) ?></div>
                  <?php endif; ?>
                </div>
                <?php if ($s === 'conflict'): ?>
                  <p class="muted">This request overlaps another one. Approving it rejects the others for the same time.</p>
                <?php endif; ?>
                <?php if (in_array($b['status'], ['pending', 'conflict', 'approved'], true) && strtotime($b['end_time']) > time()): ?>
                  <?php cancelForm($id, 'bk-btn reject'); ?>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>

<?php else: ?>
  <!-- GUEST  -->
  <main class="container gb-layout">

    <div class="gb-main">
      <div class="gb-head">
        <div>
          <h1>My Bookings</h1>
          <p>
            Your reservations at a glance
            <?php if ($guestEmail !== ''): ?>
              · <?= e($guestEmail) ?> (<a href="bookings.php?switch=1">not you?</a>)
            <?php endif; ?>
          </p>
        </div>
        <a href="new-booking.php" class="gb-cta">+ New Booking</a>
      </div>

      <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['ok'] ? 'success' : 'error' ?>"><?= e($flash['msg']) ?></div>
      <?php endif; ?>

      <?php if ($guestEmail === ''): ?>
        <!-- No email yet: ask for it -->
        <div class="form-card lookup-card">
          <h2>Find your bookings</h2>
          <p class="subtitle">Enter the email you used when you booked. You can also start a new booking above.</p>
          <form method="post" class="lookup-form">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="lookup">
            <input type="email" name="email" placeholder="you@email.com" required>
            <button type="submit" class="btn-primary">Show my bookings</button>
          </form>
        </div>

      <?php else: ?>

        <div class="tabs">
          <button type="button" class="tab active" data-tab="upcoming">Upcoming · <?= count($upcoming) ?></button>
          <button type="button" class="tab" data-tab="pending">Pending · <?= count($pending) ?></button>
          <button type="button" class="tab" data-tab="past">Past · <?= count($past) ?></button>
        </div>

        <!-- Upcoming -->
        <div data-panel="upcoming">
          <?php if ($next): $ns = $next['shown']; ?>
            <div class="next-booking">
              <div class="status-badge <?= $ns === 'approved' ? 'approved' : '' ?>">
                <?= $ns === 'approved' ? 'Approved' : 'Pending approval' ?>
              </div>
              <div class="label">Your next booking</div>
              <h2><?= e($next['resource_name']) ?></h2>
              <div class="meta">
                <?= e($next['building']) ?> ·
                <?= e(fmtDay($next['start_time'])) ?> ·
                <?= e(fmtRange($next['start_time'], $next['end_time'])) ?> ·
                <?= (int)$next['attendees'] ?> attendees
              </div>

              <div class="progress-steps">
                <div class="step done"><div class="step-dot"></div><span>Submitted</span><div class="progress-line"></div></div>
                <div class="step <?= $ns === 'approved' ? 'done' : 'active' ?>"><div class="step-dot"></div><span>Under review</span><div class="progress-line"></div></div>
                <div class="step <?= $ns === 'approved' ? 'active' : '' ?>"><div class="step-dot"></div><span>Approved</span></div>
              </div>

              <?php if (canChange($next)): ?>
                <div class="card-actions">
                  <a class="gb-btn gb-btn-outline" href="new-booking.php?edit=<?= (int)$next['id'] ?>">Edit request</a>
                  <?php cancelForm((int)$next['id'], 'gb-btn gb-btn-ghost'); ?>
                </div>
              <?php endif; ?>
            </div>
          <?php endif; ?>

          <div class="bookings-grid">
            <?php $i = 0; foreach ($upcoming as $b): ?>
              <?php if ($next && (int)$b['id'] === (int)$next['id']) continue; ?>
              <?php renderBookingCard($b, ++$i); ?>
            <?php endforeach; ?>
          </div>

          <?php if (empty($upcoming)): ?>
            <p class="empty-note">Nothing coming up. <a href="new-booking.php">Book a room or equipment</a>.</p>
          <?php endif; ?>
        </div>

        <!-- Pending -->
        <div data-panel="pending" hidden>
          <div class="bookings-grid">
            <?php foreach ($pending as $i => $b): renderBookingCard($b, $i); endforeach; ?>
          </div>
          <?php if (empty($pending)): ?><p class="empty-note">No requests waiting for approval.</p><?php endif; ?>
        </div>

        <!-- Past -->
        <div data-panel="past" hidden>
          <div class="bookings-grid">
            <?php foreach ($past as $i => $b): renderBookingCard($b, $i); endforeach; ?>
          </div>
          <?php if (empty($past)): ?><p class="empty-note">No past bookings yet.</p><?php endif; ?>
        </div>

        <!-- Table of everything -->
        <div class="table-card">
          <h3>All my bookings</h3>
          <div class="table-scroll">
            <table class="bk-table">
              <thead>
                <tr><th>ID</th><th>Resource</th><th>Building</th><th>Date</th><th>Time</th><th>Attendees</th><th>Status</th></tr>
              </thead>
              <tbody>
              <?php if (empty($all)): ?>
                <tr><td colspan="7" class="empty-cell">No bookings found for this email.</td></tr>
              <?php else: foreach (array_reverse($all) as $b):
                $s = shownStatus($b);
                if ($s === 'conflict') $s = 'pending';
              ?>
                <tr>
                  <td class="muted"><?= e(bookingCode((int)$b['id'])) ?></td>
                  <td><?= e($b['resource_name']) ?></td>
                  <td><?= e($b['building']) ?></td>
                  <td><?= e(date('M j, Y', strtotime($b['start_time']))) ?></td>
                  <td><?= e(fmtRange($b['start_time'], $b['end_time'])) ?></td>
                  <td><?= (int)$b['attendees'] ?></td>
                  <td><span class="status-tag status-<?= e($s) ?>"><?= e(STATUS_LABELS[$s] ?? ucfirst($s)) ?></span></td>
                </tr>
              <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>

      <?php endif; ?>
    </div>

    <!-- Sidebar -->
    <aside class="gb-side">
      <div class="side-card">
        <h3>Available now</h3>
        <div class="sub">Reserve in a few taps</div>
        <?php $dots = ['dot-green', 'dot-purple', 'dot-orange', 'dot-blue', 'dot-green']; ?>
        <?php foreach ($availableNow as $i => $res): ?>
          <a class="available-item" href="new-booking.php?resource=<?= (int)$res['id'] ?>">
            <div class="color-dot <?= $dots[$i % 5] ?>"></div>
            <div class="info">
              <div class="name"><?= e($res['name']) ?></div>
              <div class="detail"><?= e(resourceDetail($res)) ?></div>
            </div>
          </a>
        <?php endforeach; ?>
        <?php if (empty($availableNow)): ?><p class="empty-note">Everything is in use right now.</p><?php endif; ?>
      </div>

      <div class="side-card">
        <h3>Recent</h3>
        <div class="sub">Past activity</div>
        <?php if (empty($recent)): ?>
          <p class="empty-note">No recent activity.</p>
        <?php else: foreach ($recent as $r): ?>
          <div class="recent-item">
            <div class="name"><?= e($r['resource_name']) ?></div>
            <div class="meta"><?= e(fmtDay($r['start_time'])) ?> · <?= e(STATUS_LABELS[$r['shown']] ?? ucfirst($r['shown'])) ?></div>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </aside>

  </main>
<?php endif; ?>

  <script src="../javascript/bookings.js"></script>
</body>
</html>
