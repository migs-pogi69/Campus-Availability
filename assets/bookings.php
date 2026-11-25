<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

function bookingCode(int $id): string { return 'ABK-' . (1000 + $id); }
function ok(string $msg): array   { return ['ok' => true,  'message' => $msg]; }
function fail(string $msg): array { return ['ok' => false, 'message' => $msg]; }

function getBooking(PDO $pdo, int $id): ?array {
  $st = $pdo->prepare('SELECT * FROM bookings WHERE id = ?');
  $st->execute([$id]);
  return $st->fetch() ?: null;
}

function canManage(array $b): bool {
  if (isAdmin()) return true;
  $mine = $_SESSION['guest_email'] ?? '';
  return $mine !== '' && strcasecmp($mine, $b['requester_email']) === 0;
}

function slotTaken(PDO $pdo, int $resourceId, string $start, string $end, int $ignoreId = 0): bool {
  $st = $pdo->prepare(
    "SELECT COUNT(*) FROM bookings
      WHERE resource_id = ? AND status = 'approved' AND id <> ?
        AND start_time < ? AND end_time > ?"
  );
  $st->execute([$resourceId, $ignoreId, $end, $start]);
  return $st->fetchColumn() > 0;
}

function parseSlot(string $start, string $end): array {
  $s = strtotime($start);
  $e = strtotime($end);
  if (!$s || !$e)          return [null, 'Please enter a valid start and end time.'];
  if ($e <= $s)            return [null, 'End time must be after the start time.'];
  if ($s < time() - 300)   return [null, "You can't book a time that has already passed."];
  return [['start' => date('Y-m-d H:i:s', $s), 'end' => date('Y-m-d H:i:s', $e)], null];
}

/* ---------- actions ---------- */

function createBooking(PDO $pdo, array $in): array {
  $resourceId = (int)($in['resource_id'] ?? 0);
  $purpose    = trim($in['purpose'] ?? '');
  $attendees  = (int)($in['attendees'] ?? 0);

  if ($purpose === '')  return fail('Please enter a purpose.');
  if ($attendees < 1)   return fail('Attendees must be at least 1.');

  if (isAdmin()) {
    $name  = $_SESSION['user']['name'];
    $st = $pdo->prepare('SELECT email FROM users WHERE id = ?');
    $st->execute([$_SESSION['user']['id']]);
    $email = $st->fetchColumn() ?: 'admin@campus.local';
    $status = 'approved';
  } else {
    $name  = trim($in['name'] ?? '');
    $email = trim($in['email'] ?? '');
    if ($name === '')                               return fail('Please enter your name.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return fail('Please enter a valid email.');
    $status = 'pending';
  }

  [$slot, $error] = parseSlot($in['start'] ?? '', $in['end'] ?? '');
  if ($error) return fail($error);

  $st = $pdo->prepare('SELECT status FROM resources WHERE id = ?');
  $st->execute([$resourceId]);
  $resStatus = $st->fetchColumn();
  if ($resStatus === false)    return fail('Resource not found.');
  if ($resStatus === 'repair') return fail('This resource is under repair and cannot be booked.');

  if (slotTaken($pdo, $resourceId, $slot['start'], $slot['end'])) {
    return fail('That time is already booked. Please pick another time.');
  }

  $pdo->prepare(
    'INSERT INTO bookings (resource_id, requester_name, requester_email, purpose, attendees, start_time, end_time, status)
     VALUES (?,?,?,?,?,?,?,?)'
  )->execute([$resourceId, $name, $email, $purpose, $attendees, $slot['start'], $slot['end'], $status]);

  if (!isAdmin()) $_SESSION['guest_email'] = $email;   // remember who this guest is

  $code = bookingCode((int)$pdo->lastInsertId());
  return ok($status === 'approved' ? "$code is confirmed." : "$code submitted and waiting for approval.");
}

function approveBooking(PDO $pdo, int $id): array {
  if (!isAdmin()) return fail('Only admins can approve bookings.');
  $b = getBooking($pdo, $id);
  if (!$b) return fail('Booking not found.');
  if (!in_array($b['status'], ['pending', 'conflict'], true)) return fail('Only pending bookings can be approved.');
  if (slotTaken($pdo, (int)$b['resource_id'], $b['start_time'], $b['end_time'], $id)) {
    return fail('Another approved booking already uses this time.');
  }

  $pdo->prepare("UPDATE bookings SET status = 'approved', admin_note = NULL WHERE id = ?")->execute([$id]);

  // Everyone else waiting for the same slot loses
  $pdo->prepare(
    "UPDATE bookings SET status = 'rejected', admin_note = ?
      WHERE resource_id = ? AND id <> ? AND status IN ('pending','conflict')
        AND start_time < ? AND end_time > ?"
  )->execute(['Slot taken by ' . bookingCode($id), $b['resource_id'], $id, $b['end_time'], $b['start_time']]);

  return ok(bookingCode($id) . ' approved.');
}

function rejectBooking(PDO $pdo, int $id, string $note = ''): array {
  if (!isAdmin()) return fail('Only admins can reject bookings.');
  $b = getBooking($pdo, $id);
  if (!$b) return fail('Booking not found.');
  if (!in_array($b['status'], ['pending', 'conflict'], true)) return fail('Only pending bookings can be rejected.');

  $pdo->prepare("UPDATE bookings SET status = 'rejected', admin_note = ? WHERE id = ?")
      ->execute([$note !== '' ? $note : null, $id]);
  return ok(bookingCode($id) . ' rejected.');
}

function cancelBooking(PDO $pdo, int $id): array {
  $b = getBooking($pdo, $id);
  if (!$b)             return fail('Booking not found.');
  if (!canManage($b))  return fail('You can only cancel your own bookings.');
  if (!in_array($b['status'], ['pending', 'approved', 'conflict'], true)) return fail('This booking can no longer be cancelled.');

  $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?")->execute([$id]);
  return ok(bookingCode($id) . ' cancelled.');
}

function editBooking(PDO $pdo, int $id, array $in): array {
  $b = getBooking($pdo, $id);
  if (!$b)             return fail('Booking not found.');
  if (!canManage($b))  return fail('You can only change your own bookings.');
  if (!in_array($b['status'], ['pending', 'approved', 'conflict'], true)) return fail('This booking can no longer be changed.');

  $purpose   = trim($in['purpose'] ?? $b['purpose']);
  $attendees = (int)($in['attendees'] ?? $b['attendees']);
  if ($purpose === '') return fail('Please enter a purpose.');
  if ($attendees < 1)  return fail('Attendees must be at least 1.');

  [$start, $end, $status] = [$b['start_time'], $b['end_time'], $b['status']];

  if (!empty($in['start']) && !empty($in['end'])) {
    [$slot, $error] = parseSlot($in['start'], $in['end']);
    if ($error) return fail($error);
    if ($slot['start'] !== $start || $slot['end'] !== $end) {
      if (slotTaken($pdo, (int)$b['resource_id'], $slot['start'], $slot['end'], $id)) {
        return fail('That time is already booked. Please pick another time.');
      }
      [$start, $end] = [$slot['start'], $slot['end']];
      if (!isAdmin()) $status = 'pending';   // a guest moving a booking needs a new approval
    }
  }

  $pdo->prepare('UPDATE bookings SET purpose = ?, attendees = ?, start_time = ?, end_time = ?, status = ? WHERE id = ?')
      ->execute([$purpose, $attendees, $start, $end, $status, $id]);
  return ok(bookingCode($id) . ' updated.');
}
