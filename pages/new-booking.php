<?php
require __DIR__ . '/../assets/booking-ui.php';
requireLogin();

/** @var PDO $pdo */
/** @var array $resources */

$editId  = (int)($_GET['edit'] ?? $_POST['edit_id'] ?? 0);
$editing = null;
if ($editId) {
  $editing = getBooking($pdo, $editId);
  if (!$editing || !canManage($editing)) {
    flash('You can only change your own bookings.', false);
    header('Location: bookings.php');
    exit;
  }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $date = trim($_POST['date'] ?? '');

  if (!csrfValid()) {
    $error = 'Your session expired. Please try again.';
  } elseif ($date === '') {
    $error = 'Please pick a date.';
  } else {
    $in = [
      'resource_id' => $_POST['resource_id'] ?? 0,
      'name'        => $_POST['name'] ?? '',
      'email'       => $_POST['email'] ?? '',
      'purpose'     => $_POST['purpose'] ?? '',
      'attendees'   => $_POST['attendees'] ?? 0,
      'start'       => $date . ' ' . ($_POST['start_time'] ?? ''),
      'end'         => $date . ' ' . ($_POST['end_time'] ?? ''),
    ];

    $result = $editing ? editBooking($pdo, $editId, $in) : createBooking($pdo, $in);

    if ($result['ok']) {
      if (!$editing && !isAdmin()) $_SESSION['guest_name'] = trim($_POST['name'] ?? '');
      flash($result['message'], true);
      header('Location: bookings.php');
      exit;
    }
    $error = $result['message'];
  }
}

// Resources people are allowed to book
$bookable = array_values(array_filter($resources, fn($r) => $r['status'] !== 'repair'));

// Values shown in the form: what was just posted, else the booking being edited, else defaults
$val = [
  'resource_id' => $_POST['resource_id'] ?? ($editing['resource_id'] ?? ($_GET['resource'] ?? '')),
  'name'        => $_POST['name']  ?? ($_SESSION['guest_name'] ?? ''),
  'email'       => $_POST['email'] ?? ($_SESSION['guest_email'] ?? ''),
  'date'        => $_POST['date'] ?? ($editing ? date('Y-m-d', strtotime($editing['start_time'])) : ''),
  'start_time'  => $_POST['start_time'] ?? ($editing ? date('H:i', strtotime($editing['start_time'])) : '09:00'),
  'end_time'    => $_POST['end_time']   ?? ($editing ? date('H:i', strtotime($editing['end_time']))   : '11:00'),
  'attendees'   => $_POST['attendees']  ?? ($editing['attendees'] ?? 1),
  'purpose'     => $_POST['purpose']    ?? ($editing['purpose'] ?? ''),
];

$showWho = !$editing && !isAdmin();   // only new guest bookings ask for name + email
$title   = $editing ? 'Edit booking' : (isAdmin() ? 'New booking' : 'New booking request');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title) ?> | Campus Availability</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../Styles/style.css">
  <link rel="stylesheet" href="../Styles/bookings.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">
</head>
<body class="bk-page">
  <?php include __DIR__ . '/../assets/navbar.php'; ?>

  <main class="form-container">
    <a href="bookings.php" class="back-link">← Back to <?= isAdmin() ? 'Bookings' : 'My Bookings' ?></a>

    <div class="form-card">
      <h1><?= e($title) ?></h1>
      <p class="subtitle">
        <?php if ($editing): ?>
          Change the details below. <?= isAdmin() ? '' : 'Moving the time sends the request back for approval.' ?>
        <?php elseif (isAdmin()): ?>
          Bookings you create are approved straight away.
        <?php else: ?>
          Fill out the form to request a room or equipment. An admin will review it.
        <?php endif; ?>
      </p>

      <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
      <?php endif; ?>

      <form method="post">
        <?= csrfField() ?>
        <?php if ($editing): ?><input type="hidden" name="edit_id" value="<?= $editId ?>"><?php endif; ?>

        <div class="form-group">
          <label>Resource <span class="required">*</span></label>
          <?php if ($editing): ?>
            <?php
              $cur = array_values(array_filter($resources, fn($r) => (int)$r['id'] === (int)$editing['resource_id']))[0] ?? null;
            ?>
            <input type="text" value="<?= e($cur ? $cur['name'] . ' (' . $cur['building'] . ')' : 'Resource #' . $editing['resource_id']) ?>" disabled>
            <input type="hidden" name="resource_id" value="<?= (int)$editing['resource_id'] ?>">
          <?php else: ?>
            <select name="resource_id" required>
              <option value="">Select a room or equipment</option>
              <?php foreach ($bookable as $r): ?>
                <option value="<?= (int)$r['id'] ?>" <?= (string)$val['resource_id'] === (string)$r['id'] ? 'selected' : '' ?>>
                  <?= e($r['name'] . ' (' . $r['building'] . ')') ?>
                  <?php $d = resourceDetail($r); if ($d !== '') echo e(' · ' . $d); ?>
                </option>
              <?php endforeach; ?>
            </select>
          <?php endif; ?>
        </div>

        <?php if ($showWho): ?>
          <div class="form-row">
            <div class="form-group">
              <label>Your name <span class="required">*</span></label>
              <input type="text" name="name" required maxlength="100" value="<?= e($val['name']) ?>" placeholder="Full name">
            </div>
            <div class="form-group">
              <label>Email <span class="required">*</span></label>
              <input type="email" name="email" required value="<?= e($val['email']) ?>" placeholder="you@email.com">
            </div>
          </div>
        <?php endif; ?>

        <div class="form-group">
          <label>Date <span class="required">*</span></label>
          <input type="date" name="date" required min="<?= date('Y-m-d') ?>" value="<?= e($val['date']) ?>">
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Start time <span class="required">*</span></label>
            <input type="time" name="start_time" required value="<?= e($val['start_time']) ?>">
          </div>
          <div class="form-group">
            <label>End time <span class="required">*</span></label>
            <input type="time" name="end_time" required value="<?= e($val['end_time']) ?>">
          </div>
        </div>

        <div class="form-group">
          <label>Number of attendees</label>
          <input type="number" name="attendees" min="1" max="500" value="<?= (int)$val['attendees'] ?>">
        </div>

        <div class="form-group">
          <label>Purpose <span class="required">*</span></label>
          <textarea name="purpose" rows="3" required maxlength="150" placeholder="What is this booking for?"><?= e($val['purpose']) ?></textarea>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn-primary"><?= $editing ? 'Save changes' : (isAdmin() ? 'Create booking' : 'Submit request') ?></button>
          <a href="bookings.php" class="btn-secondary">Cancel</a>
        </div>
      </form>
    </div>
  </main>
</body>
</html>
