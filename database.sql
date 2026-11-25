-- Step 1 run first
CREATE DATABASE IF NOT EXISTS campus_availability
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE campus_availability;

CREATE TABLE users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100) NOT NULL,
  email         VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('admin','guest') NOT NULL DEFAULT 'guest',
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
--- Step 2 run mo next
USE campus_availability;

CREATE TABLE IF NOT EXISTS resources (
  id       INT AUTO_INCREMENT PRIMARY KEY,
  name     VARCHAR(100) NOT NULL,
  building VARCHAR(100) NOT NULL,
  category VARCHAR(50)  NOT NULL,
  capacity INT          NULL,
  features VARCHAR(255) NULL,
  status   ENUM('available','booked','repair') NOT NULL DEFAULT 'available'
);
-- Step 3 run mo next
CREATE TABLE IF NOT EXISTS bookings (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  resource_id     INT NOT NULL,
  requester_name  VARCHAR(100) NOT NULL,
  requester_email VARCHAR(150) NOT NULL,
  purpose         VARCHAR(255) NOT NULL,
  attendees       INT NOT NULL DEFAULT 1,
  start_time      DATETIME NOT NULL,
  end_time        DATETIME NOT NULL,
  status          ENUM('pending','approved','rejected','cancelled','conflict','completed')
                  NOT NULL DEFAULT 'pending',
  admin_note      VARCHAR(255) NULL,
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_resource_time (resource_id, start_time, end_time),
  CONSTRAINT fk_booking_resource FOREIGN KEY (resource_id) REFERENCES resources(id)
);

-- Step 4 run mo next
INSERT INTO resources (id, name, building, category, capacity, features, status) VALUES
  (1, 'Classroom 302',       'Mother Rita Building',   'classroom', 40,   'Whiteboard, PC, Mic',   'available'),
  (2, 'Robotics & IoT Lab',  'Saint Agustin Building', 'lab',       4,    'Robots, 3D Printers',   'booked'),
  (3, 'AV Locker 3 + Bay A', 'BMC Building',           'locker',    12,   'Staff & Students',      'available'),
  (4, 'Epson Pro Cinema 4K', 'Saint Agustin Building', 'projector', NULL, 'PRJ-EPC4K-07, AV Storage', 'repair'),
  (5, 'MR 220',              'Mother Rita Building',   'classroom', NULL, NULL,                    'available'),
  (6, 'MR 210',              'Mother Rita Building',   'classroom', NULL, NULL,                    'available'),
  (7, 'SA 304',              'Saint Agustin Building', 'classroom', NULL, NULL,                    'available');