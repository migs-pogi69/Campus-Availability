<?php
session_start();
date_default_timezone_set('Asia/Manila');
const ADMIN_SIGNUP_CODE = '112335';

function isLoggedIn(): bool {
  return !empty($_SESSION['user']);
}

function requireLogin(): void {
  if (!isLoggedIn()) {
    header('Location: ../pages/welcome.php');
    exit;
  }
}

$role = $_SESSION['user']['role'] ?? 'guest';

function isAdmin(): bool {
  global $role;
  return $role === 'admin';
}

//name: "Juan Karlo" -> "JK" kukuning yung first letter ng First name at Last name tas sa "Guest" -> "GU"
function initials(string $name): string {
  $parts = preg_split('/\s+/', trim($name));
  $out = '';
  foreach (array_slice($parts, 0, 2) as $p) {
    $out .= mb_strtoupper(mb_substr($p, 0, 1));
  }
  return $out ?: '?';
}

$user = [
  'name'     => $_SESSION['user']['name'] ?? 'Guest',
  'initials' => initials($_SESSION['user']['name'] ?? 'Guest'),
];

require_once __DIR__ . '/data.php';