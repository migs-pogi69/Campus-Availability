-- optional lang to para sa reports analytics
-- Run sa phpMyAdmin

INSERT INTO bookings (resource_id, requester_name, requester_email, purpose, attendees, start_time, end_time, status)
SELECT
  r.id,
  'Demo User',
  CONCAT('demo', n.i, '@campus.test'),
  'Demo booking',
  1 + MOD(n.i, 30),
  TIMESTAMP(CURDATE() - INTERVAL (1 + MOD(n.i * 7, 58)) DAY, MAKETIME(8 + MOD(n.i * 3, 12), 0, 0)),
  TIMESTAMP(CURDATE() - INTERVAL (1 + MOD(n.i * 7, 58)) DAY, MAKETIME(8 + MOD(n.i * 3, 12) + 1 + MOD(n.i, 3), 0, 0)),
  ELT(1 + MOD(n.i, 10), 'approved','approved','approved','approved','approved','approved','completed','cancelled','rejected','pending')
FROM (
  SELECT a.x * 10 + b.y + 1 AS i
  FROM (SELECT 0 x UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7) a
  CROSS JOIN (SELECT 0 y UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) b
) n
JOIN (
  SELECT id, ROW_NUMBER() OVER (ORDER BY id) - 1 AS rn
  FROM resources WHERE status <> 'repair'
) r ON r.rn = MOD(n.i, (SELECT COUNT(*) FROM resources WHERE status <> 'repair'));

-- para maremove sa data base, type lang to sa sql sa phpmyadmins:
-- DELETE FROM bookings WHERE requester_email LIKE 'demo%@campus.test';
