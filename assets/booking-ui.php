<?php
// assets/booking-ui.php - helpers shared by the booking pages
require_once __DIR__ . '/bookings.php';

function e($s): string {
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/* ---------- CSRF + one-time messages ---------- */

function csrfToken(): string {
  if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
  return $_SESSION['csrf'];
}

function csrfField(): string {
  return '<input type="hidden" name="csrf" value="' . e(csrfToken()) . '">';
}

function csrfValid(): bool {
  return !empty($_POST['csrf']) && !empty($_SESSION['csrf'])
      && hash_equals($_SESSION['csrf'], (string)$_POST['csrf']);
}

function flash(string $msg, bool $ok): void {
  $_SESSION['flash'] = ['msg' => $msg, 'ok' => $ok];
}

function takeFlash(): ?array {
  $f = $_SESSION['flash'] ?? null;
  unset($_SESSION['flash']);
  return $f;
}

/* status */

const STATUS_LABELS = [
  'pending'   => 'Pending',
  'approved'  => 'Approved',
  'conflict'  => 'Conflict',
  'completed' => 'Completed',
  'expired'   => 'Expired',
  'rejected'  => 'Rejected',
  'cancelled' => 'Cancelled',
];

const BOOKING_SELECT =
  'SELECT b.*, r.name AS resource_name, r.building, r.category
     FROM bookings b JOIN resources r ON r.id = b.resource_id';

function shownStatus(array $b, bool $inConflict = false): string {
  $over = strtotime($b['end_time']) < time();
  switch ($b['status']) {
    case 'approved':
      return $over ? 'completed' : 'approved';
    case 'pending':
    case 'conflict':
      if ($over) return 'expired';
      return ($b['status'] === 'conflict' || $inConflict) ? 'conflict' : 'pending';
    default:
      return $b['status'];
  }
}

function conflictIds(PDO $pdo, string $now): array {
  $st = $pdo->prepare(
    "SELECT DISTINCT a.id
       FROM bookings a
       JOIN bookings b ON b.resource_id = a.resource_id AND b.id <> a.id
                      AND a.start_time < b.end_time AND a.end_time > b.start_time
      WHERE a.status IN ('pending','conflict') AND b.status IN ('pending','conflict')
        AND a.end_time >= ?"
  );
  $st->execute([$now]);
  return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}

function canChange(array $b): bool {
  return in_array($b['status'], ['pending', 'approved', 'conflict'], true)
      && strtotime($b['start_time']) > time();
}

/* formatting */

function fmtDay(string $dt): string  { return date('M j', strtotime($dt)); }
function fmtTime(string $dt): string { return date('g:i A', strtotime($dt)); }
function fmtRange(string $s, string $e): string { return fmtTime($s) . ' – ' . fmtTime($e); }

// "Capacity 40 · Whiteboard" for the sidebar
function resourceDetail(array $r): string {
  $d = $r['details'] ?? [];
  $parts = [];
  if (!empty($d['Capacity'])) $parts[] = 'Capacity ' . (int)$d['Capacity'];
  $extra = $d['Features'] ?? ($d['Type'] ?? '');
  if ($extra !== '') $parts[] = trim(explode(',', $extra)[0]);
  return implode(' · ', $parts);
}

/*small pieces of HTML*/

function cancelForm(int $id, string $class): void { ?>
  <form method="post" action="booking-action.php" class="inline-form"
        onsubmit="return confirm('Cancel this booking?');">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="cancel">
    <input type="hidden" name="id" value="<?= $id ?>">
    <button type="submit" class="<?= e($class) ?>">Cancel</button>
  </form>
<?php }

function actionForm(int $id, string $action, string $label, string $class, string $confirm = ''): void {
  $onsubmit = $confirm !== ''
    ? ' onsubmit="return confirm(' . e(json_encode($confirm)) . ');"'
    : '';
  ?>
  <form method="post" action="booking-action.php" class="inline-form"<?= $onsubmit ?>>
    <?= csrfField() ?>
    <input type="hidden" name="action" value="<?= e($action) ?>">
    <input type="hidden" name="id" value="<?= $id ?>">
    <button type="submit" class="<?= e($class) ?>"><?= e($label) ?></button>
  </form>
<?php }

// One small booking card (guest page)
function renderBookingCard(array $b, int $i): void {
  $colors = ['icon-green', 'icon-pink', 'icon-blue', 'icon-purple', 'icon-orange'];
  $s = $b['shown'];
  ?>
  <div class="booking-card">
    <div class="top">
      <div class="resource-icon <?= $colors[$i % 5] ?>"><?= e(strtoupper(substr($b['resource_name'], 0, 2))) ?></div>
      <span class="status-tag status-<?= e($s) ?>"><?= e(STATUS_LABELS[$s] ?? ucfirst($s)) ?></span>
    </div>
    <h3><?= e($b['resource_name']) ?></h3>
    <div class="building"><?= e($b['building']) ?></div>
    <div class="datetime">
      <span class="dt-item"><i class="ti ti-calendar"></i><?= e(fmtDay($b['start_time'])) ?></span>
      <span class="dt-item"><i class="ti ti-clock"></i><?= e(fmtRange($b['start_time'], $b['end_time'])) ?></span>
    </div>
    <?php if (canChange($b)): ?>
      <div class="card-actions-sm">
        <a class="btn-sm" href="new-booking.php?edit=<?= (int)$b['id'] ?>">Edit</a>
        <?php cancelForm((int)$b['id'], 'btn-sm danger'); ?>
      </div>
    <?php endif; ?>
  </div>
<?php }
