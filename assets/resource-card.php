<?php /** @var array $r**/ ?>
<?php

$statusLabels = [
  'available' => 'Available',
  'booked'    => 'Booked',
  'repair'    => 'Under Repair',
];

$icons = [
  'classroom' => '<rect x="3" y="4" width="18" height="12" rx="1.5"/><path d="M8 20l4-4 4 4M12 16v4"/>',
  'lab'       => '<path d="M9 3h6M10 3v6l-5 9a2 2 0 002 3h10a2 2 0 002-3l-5-9V3"/>',
  'locker'    => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M12 3v18M9 11v2M15 11v2"/>',
  'projector' => '<rect x="3" y="8" width="18" height="9" rx="2"/><circle cx="8" cy="12.5" r="2"/><path d="M16 11h2M16 14h2M7 17v2M17 17v2"/>',
];
$iconPath = $icons[$r['category']] ?? $icons['classroom'];
$status   = $r['status'];
?>
<article class="res-card <?= htmlspecialchars($status) ?>"
         data-name="<?= htmlspecialchars(strtolower($r['name'])) ?>"
         data-building="<?= htmlspecialchars($r['building']) ?>"
         data-category="<?= htmlspecialchars($r['category']) ?>"
         data-status="<?= htmlspecialchars($status) ?>">

  <div class="res-head">
    <div class="res-icon <?= htmlspecialchars($status) ?>">
      <svg viewBox="0 0 24 24"><?= $iconPath ?></svg>
    </div>
    <div class="res-title">
      <h3>
        <?= htmlspecialchars($r['name']) ?>
        <?php if (isAdmin()): ?>
          <a class="edit-btn" aria-label="Edit" href="resources.php?edit=<?= (int)$r['id'] ?>">
            <svg viewBox="0 0 24 24"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4z"/></svg>
          </a>
        <?php endif; ?>
      </h3>
      <p><?= htmlspecialchars($r['building']) ?></p>
      <span class="pill <?= htmlspecialchars($status) ?>"><?= $statusLabels[$status] ?></span>
    </div>
  </div>

  <dl class="res-details">
    <?php foreach ($r['details'] as $label => $value): ?>
      <div class="res-row">
        <dt><?= htmlspecialchars($label) ?></dt>
        <dd><?= htmlspecialchars($value) ?></dd>
      </div>
    <?php endforeach; ?>
  </dl>

  <div class="res-actions">
    <?php if ($status === 'available'): ?>
      <a class="btn solid available" href="new-booking.php?resource=<?= (int)$r['id'] ?>">Reserve Room</a>
      <button class="btn outline <?= $status ?>">View Schedule</button>

    <?php elseif ($status === 'booked'): ?>
      <button class="btn solid <?= $status ?>">View Schedule</button>
      <button class="btn outline <?= $status ?>">Details</button>

    <?php else: /* repair */ ?>
      <?php if (isAdmin()): ?>
        <button class="btn solid <?= $status ?>">Maintenance</button>
      <?php endif; ?>
      <button class="btn outline <?= $status ?>">Details</button>
    <?php endif; ?>
  </div>

</article>