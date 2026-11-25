<?php
require __DIR__ . '/../assets/config.php';
requireLogin();
if (!isAdmin()) { header('Location: dashboard.php'); exit; }
require_once __DIR__ . '/../assets/db.php';

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

/* ---------- Period: last 30 days vs the 30 days before ---------- */
const DAYS = 30, OPEN_H = 8, CLOSE_H = 22;           // 8 AM to 10 PM
$todayStart = new DateTime('today');
$curStart   = (clone $todayStart)->modify('-' . (DAYS - 1) . ' days');
$prevStart  = (clone $curStart)->modify('-' . DAYS . ' days');
$end        = (clone $todayStart)->modify('+1 day');

$st = $pdo->prepare('SELECT b.*, r.name AS resource FROM bookings b
                     JOIN resources r ON r.id = b.resource_id
                     WHERE b.start_time >= ? AND b.start_time < ?');
$st->execute([$prevStart->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')]);
$bookings = $st->fetchAll();

$resCount = max(1, (int)$pdo->query("SELECT COUNT(*) FROM resources WHERE status <> 'repair'")->fetchColumn());
$capacityHours = $resCount * (CLOSE_H - OPEN_H);       // per day

/* ---------- Tally ---------- */
$per = ['cur' => ['n' => 0, 'cancel' => 0, 'hours' => 0.0, 'daily' => []],
        'prev' => ['n' => 0, 'cancel' => 0, 'hours' => 0.0, 'daily' => []]];
$heat = array_fill(0, 7, array_fill(0, 7, 0));          // Mon..Sun x 2-hour slots
$byResource = [];

foreach ($bookings as $b) {
  $s = new DateTime($b['start_time']);
  $e = new DateTime($b['end_time']);
  $key = $s >= $curStart ? 'cur' : 'prev';
  $per[$key]['n']++;

  if (in_array($b['status'], ['cancelled', 'rejected'], true)) { $per[$key]['cancel']++; continue; }
  if (!in_array($b['status'], ['approved', 'completed'], true)) continue;

  // hours inside opening time, counted per day
  $day = $s->format('Y-m-d');
  $open  = (clone $s)->setTime(OPEN_H, 0);
  $close = (clone $s)->setTime(CLOSE_H, 0);
  $from = max($s, $open);
  $to   = min($e, $close);
  $hrs  = max(0, ($to->getTimestamp() - $from->getTimestamp()) / 3600);
  $per[$key]['hours'] += $hrs;
  $per[$key]['daily'][$day] = ($per[$key]['daily'][$day] ?? 0) + $hrs;

  if ($key === 'cur') {
    $byResource[$b['resource']] = ($byResource[$b['resource']] ?? 0) + 1;
    $wd = (int)$s->format('N') - 1;
    $endH = (int)ceil((int)$to->format('G') + (int)$to->format('i') / 60);
    for ($t = (int)$from->format('G'); $t < $endH; $t++) {
      $slot = intdiv($t - OPEN_H, 2);
      if ($slot >= 0 && $slot < 7) $heat[$wd][$slot]++;
    }
  }
}

/* ---------- Stat cards ---------- */
$util   = fn($p) => min(100, $p['hours'] / ($capacityHours * DAYS) * 100);
$cancel = fn($p) => $p['n'] ? $p['cancel'] / $p['n'] * 100 : 0;
$peak   = fn($p) => $p['daily'] ? max($p['daily']) : 0;
$pct    = fn($a, $b) => $b ? ($a - $b) / $b * 100 : ($a ? 100 : 0);

$cards = [
  ['Utilization rate',      round($util($per['cur'])) . '%',          $util($per['cur']) - $util($per['prev']),      round(abs($util($per['cur']) - $util($per['prev'])), 1) . '%', true],
  ['Total bookings',        $per['cur']['n'],                         $pct($per['cur']['n'], $per['prev']['n']),     round(abs($pct($per['cur']['n'], $per['prev']['n']))) . '%', true],
  ['Cancellation / no-show', round($cancel($per['cur']), 1) . '%',    $cancel($per['cur']) - $cancel($per['prev']),  round(abs($cancel($per['cur']) - $cancel($per['prev'])), 1) . '%', false],
  ['Peak usage',            round($peak($per['cur'])) . ' h',         $peak($per['cur']) - $peak($per['prev']),      round(abs($peak($per['cur']) - $peak($per['prev']))) . ' h', true],
];

/* ---------- Line chart: 5 buckets of 6 days ---------- */
function buckets(array $p, DateTime $start, float $cap): array {
  $out = [];
  for ($i = 0; $i < 5; $i++) {
    $sum = 0;
    for ($d = 0; $d < 6; $d++) {
      $day = (clone $start)->modify('+' . ($i * 6 + $d) . ' days')->format('Y-m-d');
      $sum += $p['daily'][$day] ?? 0;
    }
    $out[] = min(100, $sum / ($cap * 6) * 100);
  }
  return $out;
}
$lineCur  = buckets($per['cur'],  $curStart,  $capacityHours);
$linePrev = buckets($per['prev'], $prevStart, $capacityHours);
$labels = [];
for ($i = 0; $i < 5; $i++) $labels[] = (clone $curStart)->modify('+' . ($i * 6) . ' days')->format('M j');

$W = 480; $Ht = 190; $L = 40; $T = 10;
$pts = function (array $v) use ($W, $Ht, $L, $T) {
  $o = [];
  foreach ($v as $i => $y) $o[] = round($L + $i * (($W - $L - 10) / 4), 1) . ',' . round($T + (1 - $y / 100) * ($Ht - $T - 20), 1);
  return implode(' ', $o);
};

/* ---------- Heatmap + bars ---------- */
$maxHeat = max(1, max(array_map('max', $heat)));
arsort($byResource);
$top = array_slice($byResource, 0, 5, true);
$maxBar = max(1, ...array_values($top ?: [1]));
$slotLabels = ['8 AM', '10 AM', '12 PM', '2 PM', '4 PM', '6 PM', '8 PM'];
$days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reports | Campus Availability</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../Styles/style.css">
  <link rel="stylesheet" href="../Styles/reports.css">
</head>
<body>
  <?php include __DIR__ . '/../assets/navbar.php'; ?>

  <main class="rep-page">
    <h1>Reports &amp; Analytics</h1>
    <p class="rep-sub">Last <?= DAYS ?> days compared with the <?= DAYS ?> days before</p>

    <section class="rep-stats">
      <?php foreach ($cards as [$label, $value, $diff, $diffText, $upGood]): ?>
        <?php $good = $diff == 0 ? true : (($diff > 0) === $upGood); ?>
        <div class="rep-card">
          <div class="rep-label"><?= $h($label) ?></div>
          <div class="rep-value">
            <?= $h($value) ?>
            <span class="rep-delta <?= $good ? 'good' : 'bad' ?>"><?= $diff >= 0 ? '▲' : '▼' ?> <?= $h($diffText) ?></span>
          </div>
        </div>
      <?php endforeach; ?>
    </section>

    <section class="rep-row">
      <div class="rep-card rep-wide">
        <h3>Utilization over time</h3>
        <svg viewBox="0 0 <?= $W ?> <?= $Ht ?>" class="rep-line" role="img" aria-label="Utilization over time">
          <?php foreach ([0, 25, 50, 75, 100] as $g): $y = $T + (1 - $g / 100) * ($Ht - $T - 20); ?>
            <line x1="<?= $L ?>" x2="<?= $W - 10 ?>" y1="<?= $y ?>" y2="<?= $y ?>" class="grid"/>
            <text x="<?= $L - 6 ?>" y="<?= $y + 4 ?>" text-anchor="end"><?= $g ?>%</text>
          <?php endforeach; ?>
          <?php foreach ($labels as $i => $lb): ?>
            <text x="<?= $L + $i * (($W - $L - 10) / 4) ?>" y="<?= $Ht - 4 ?>" text-anchor="middle"><?= $h($lb) ?></text>
          <?php endforeach; ?>
          <polyline points="<?= $pts($linePrev) ?>" class="prev"/>
          <polyline points="<?= $pts($lineCur) ?>" class="cur"/>
        </svg>
        <p class="rep-note">Solid: this period · Dashed: previous period</p>
      </div>

      <div class="rep-card rep-wide">
        <h3>Peak hours</h3>
        <div class="heat">
          <span></span>
          <?php foreach ($slotLabels as $sl): ?><span class="hl"><?= $sl ?></span><?php endforeach; ?>
          <?php foreach ($days as $di => $d): ?>
            <span class="hd"><?= $d ?></span>
            <?php foreach ($heat[$di] as $v): ?>
              <span class="cell" style="--a: <?= round(0.1 + 0.9 * $v / $maxHeat, 2) ?>" title="<?= $v ?> bookings"></span>
            <?php endforeach; ?>
          <?php endforeach; ?>
        </div>
        <p class="rep-note">Mon - Sun · 8 AM - 10 PM · darker is busier</p>
      </div>
    </section>

    <section class="rep-card rep-bars">
      <h3>Most booked resources</h3>
      <?php if (!$top): ?>
        <p class="rep-note">No approved bookings in this period yet.</p>
      <?php endif; ?>
      <?php foreach ($top as $name => $n): ?>
        <div class="bar-row">
          <span class="bar-name"><?= $h($name) ?></span>
          <span class="bar-track"><span class="bar-fill" style="width: <?= round($n / $maxBar * 100) ?>%"></span></span>
          <span class="bar-n"><?= $n ?></span>
        </div>
      <?php endforeach; ?>
    </section>
  </main>
</body>
</html>
