<?php
require __DIR__ . '/../assets/booking-ui.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrfValid()) {
  flash('Your session expired. Please try again.', false);
  header('Location: bookings.php');
  exit;
}

$id = (int)($_POST['id'] ?? 0);

switch ($_POST['action'] ?? '') {
  case 'approve': $result = approveBooking($pdo, $id); break;
  case 'reject':  $result = rejectBooking($pdo, $id, trim($_POST['note'] ?? '')); break;
  case 'cancel':  $result = cancelBooking($pdo, $id); break;
  default:        $result = fail('Unknown action.');
}

flash($result['message'], $result['ok']);
header('Location: bookings.php');
exit;
