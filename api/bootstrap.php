<?php
// Shared database connection, schema setup and API helpers.
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
$host = 'localhost'; $dbname = 'library_system'; $dbUser = 'root'; $dbPass = '';
try {
    $pdo = new PDO("mysql:host={$host};charset=utf8mb4", $dbUser, $dbPass, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$dbname}`");
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(100) NOT NULL,email VARCHAR(150) UNIQUE NOT NULL,password VARCHAR(255) NOT NULL,role ENUM('librarian','member','author') NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE IF NOT EXISTS books (id INT AUTO_INCREMENT PRIMARY KEY,author_id INT NOT NULL,title VARCHAR(200) NOT NULL,description TEXT,content LONGTEXT,file_path VARCHAR(255) DEFAULT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(author_id) REFERENCES users(id)) ENGINE=InnoDB");
    $col = $pdo->query("SHOW COLUMNS FROM books LIKE 'file_path'"); if (!$col->fetch()) $pdo->exec("ALTER TABLE books ADD COLUMN file_path VARCHAR(255) DEFAULT NULL");
    $pdo->exec("CREATE TABLE IF NOT EXISTS borrow_requests (id INT AUTO_INCREMENT PRIMARY KEY,book_id INT NOT NULL,member_id INT NOT NULL,status ENUM('pending','approved','rejected','returned') DEFAULT 'pending',rental_days INT DEFAULT NULL,rented_at DATETIME DEFAULT NULL,due_at DATETIME DEFAULT NULL,returned_at DATETIME DEFAULT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(book_id) REFERENCES books(id),FOREIGN KEY(member_id) REFERENCES users(id)) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE IF NOT EXISTS book_reads (id INT AUTO_INCREMENT PRIMARY KEY,book_id INT NOT NULL,member_id INT NOT NULL,read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY unique_reader(book_id,member_id),FOREIGN KEY(book_id) REFERENCES books(id),FOREIGN KEY(member_id) REFERENCES users(id)) ENGINE=InnoDB");
    $q=$pdo->prepare('SELECT id FROM users WHERE email=?'); $q->execute(['admin@library.com']);
    if (!$q->fetch()) { $q=$pdo->prepare("INSERT INTO users(name,email,password,role) VALUES(?,?,?,'librarian')"); $q->execute(['Main Librarian','admin@library.com',password_hash('Admin123!',PASSWORD_DEFAULT)]); }
} catch (Throwable $e) { http_response_code(500); header('Content-Type: application/json'); echo json_encode(['ok'=>false,'message'=>'Database error. Check XAMPP MySQL and api/bootstrap.php settings.','detail'=>$e->getMessage()]); exit; }
function respond($data, int $status=200): never { http_response_code($status); header('Content-Type: application/json; charset=utf-8'); echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }
function user(): ?array { return $_SESSION['user'] ?? null; }
function requireRole(array $roles): array { $u=user(); if (!$u) respond(['ok'=>false,'message'=>'Please log in first.'],401); if (!in_array($u['role'],$roles,true)) respond(['ok'=>false,'message'=>'You are not allowed to do that.'],403); return $u; }
function postValue(string $key, string $default=''): string { return trim((string)($_POST[$key] ?? $default)); }
