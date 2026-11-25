<?php
// Save / update / delete (admin only)
require __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
requireLogin();
if (!isAdmin() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ../pages/dashboard.php');
  exit;
}

$action = $_POST['action'] ?? '';
$id     = (int)($_POST['id'] ?? 0);
$cap    = ($_POST['capacity'] ?? '') === '' ? null : max(0, (int)$_POST['capacity']);
$name   = trim($_POST['name'] ?? '');
$status = in_array($_POST['status'] ?? '', ['available', 'booked', 'repair'], true)
  ? $_POST['status'] : 'available';

if ($action === 'delete') {
  try {
    $pdo->prepare('DELETE FROM resources WHERE id = ?')->execute([$id]);
  } catch (PDOException $e) {
    $_SESSION['res_error'] = 'Cannot delete: this resource has bookings.';
  }
} elseif ($name !== '' && ($action === 'save' || $action === 'update')) {
  $vals = [$name, trim($_POST['category'] ?? ''), trim($_POST['building'] ?? ''),
           $cap, trim($_POST['features'] ?? '') ?: null, $status];
  if ($action === 'save') {
    $pdo->prepare('INSERT INTO resources (name,category,building,capacity,features,status)
                   VALUES (?,?,?,?,?,?)')->execute($vals);
  } else {
    $pdo->prepare('UPDATE resources SET name=?, category=?, building=?, capacity=?,
                   features=?, status=? WHERE id=?')->execute([...$vals, $id]);
  }
}

header('Location: ../pages/resources.php');
