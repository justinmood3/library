<?php
require __DIR__.'/bootstrap.php';
$u=user(); if (!$u || $u['role']!=='member') { http_response_code(403); exit('Only logged-in members can download books.'); }
$id=(int)($_GET['id']??0); $s=$pdo->prepare("SELECT b.title,b.file_path FROM books b JOIN borrow_requests r ON r.book_id=b.id WHERE b.id=? AND r.member_id=? AND r.status='approved' LIMIT 1"); $s->execute([$id,$u['id']]); $book=$s->fetch();
if (!$book) { http_response_code(403); exit('Download unavailable. Your request must be approved by the librarian.'); }
if (!$book['file_path']) { http_response_code(404); exit('No downloadable file exists for this book.'); }
$base=realpath(dirname(__DIR__).'/uploads/books'); $path=realpath(dirname(__DIR__).'/'.$book['file_path']); if (!$base||!$path||!str_starts_with($path,$base.DIRECTORY_SEPARATOR)||!is_file($path)) { http_response_code(404); exit('Book file not found.'); }
$ext=strtolower(pathinfo($path,PATHINFO_EXTENSION)); $name=preg_replace('/[^A-Za-z0-9_-]/','_', $book['title']); $name=trim($name,'_')?:'book'; header('Content-Type: application/octet-stream'); header('X-Content-Type-Options: nosniff'); header('Content-Disposition: attachment; filename="'.$name.'.'.$ext.'"'); header('Content-Length: '.filesize($path)); readfile($path); exit;
