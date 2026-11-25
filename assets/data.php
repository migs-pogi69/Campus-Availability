<?php
require_once __DIR__ . '/db.php';

$resources = [];
foreach ($pdo->query('SELECT * FROM resources ORDER BY id') as $row) {
  $details = ['Type' => ucfirst($row['category'])];
  if ($row['capacity'] !== null) $details['Capacity'] = (string)$row['capacity'];
  if (!empty($row['features']))  $details['Features'] = $row['features'];

  $resources[] = [
    'id'       => (int)$row['id'],
    'name'     => $row['name'],
    'building' => $row['building'],
    'status'   => $row['status'],
    'category' => $row['category'],
    'details'  => $details,
  ];
}

$counts = ['available' => 0, 'booked' => 0, 'repair' => 0];
foreach ($resources as $r) {
  $counts[$r['status']]++;
}
$counts['total'] = count($resources);
$types = array_values(array_unique(array_column($resources, 'category')));
$locations = array_values(array_unique(array_column($resources, 'building')));
sort($types);
sort($locations);
