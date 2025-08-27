-- CREATE DATABASE (ejecuta esto una sola vez)
CREATE DATABASE IF NOT EXISTS recyclego
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE recyclego;

DROP TABLE IF EXISTS user_medals;
DROP TABLE IF EXISTS recycle_logs;
DROP TABLE IF EXISTS medals;
DROP TABLE IF EXISTS tips;
DROP TABLE IF EXISTS users;

CREATE TABLE users(
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  avatar_url VARCHAR(255),
  points INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE recycle_logs(
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  material VARCHAR(40) NOT NULL,
  weight_kg DECIMAL(8,2) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE medals(
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  threshold_kg DECIMAL(10,2) NOT NULL,
  icon VARCHAR(50) NOT NULL
);

CREATE TABLE user_medals(
  user_id INT NOT NULL,
  medal_id INT NOT NULL,
  awarded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, medal_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (medal_id) REFERENCES medals(id) ON DELETE CASCADE
);

CREATE TABLE tips(
  id INT AUTO_INCREMENT PRIMARY KEY,
  text VARCHAR(255) NOT NULL
);

-- Semillas
INSERT INTO medals(name, threshold_kg, icon) VALUES
('Green Sprout',    5,  '🌱'),
('Eco Rookie',     15,  '🥉'),
('Planet Protector',40, '🥈'),
('Zero Waste Hero',80, '🥇'),
('Earth Guardian',150, '🏆');

INSERT INTO tips(text) VALUES
('Rinse containers before recycling to avoid contamination.'),
('Flatten cardboard to save space in the bin.'),
('Glass can be recycled endlessly without loss in quality.'),
('Bring your own bag to reduce plastic use.'),
('One recycled can saves enough energy to run a TV for 3 hours.');
