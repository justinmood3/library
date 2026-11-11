<?php
require __DIR__.'/bootstrap.php';
$action = $_POST['action'] ?? $_GET['action'] ?? '';
try {
 switch ($action) {
  case 'me': respond(['ok'=>true,'user'=>user()]);
  case 'register':
   $name=postValue('name'); $email=postValue('email'); $pass=(string)($_POST['password']??''); $role=postValue('role');
   if ($name==='' || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($pass)<6 || !in_array($role,['member','author'],true)) respond(['ok'=>false,'message'=>'Enter a name, valid email, password (at least 6 characters), and valid role.'],422);
   $s=$pdo->prepare('INSERT INTO users(name,email,password,role) VALUES(?,?,?,?)'); $s->execute([$name,$email,password_hash($pass,PASSWORD_DEFAULT),$role]);
   respond(['ok'=>true,'message'=>'Account created. You can now log in.']);
  case 'login':
   $s=$pdo->prepare('SELECT id,name,email,password,role FROM users WHERE email=?'); $s->execute([postValue('email')]); $u=$s->fetch();
   if (!$u || !password_verify((string)($_POST['password']??''),$u['password'])) respond(['ok'=>false,'message'=>'Incorrect email or password.'],401);
   session_regenerate_id(true); unset($u['password']); $_SESSION['user']=$u; respond(['ok'=>true,'user'=>$u,'message'=>'Welcome back.']);
  case 'logout': $_SESSION=[]; session_destroy(); respond(['ok'=>true,'message'=>'You have logged out.']);
  case 'dashboard':
   $u=requireRole(['librarian','member','author']);
   $books=$pdo->query("SELECT b.id,b.title,b.description,b.content,b.file_path,b.created_at,u.name AS author,(SELECT COUNT(*) FROM book_reads br WHERE br.book_id=b.id) AS readers FROM books b JOIN users u ON u.id=b.author_id ORDER BY b.created_at DESC")->fetchAll();
   $data=['ok'=>true,'user'=>$u,'books'=>$books];
   if ($u['role']==='member') { $s=$pdo->prepare("SELECT br.id AS request_id,br.book_id,br.status,br.rental_days,br.rented_at,br.due_at,br.returned_at,b.title,b.file_path FROM borrow_requests br JOIN books b ON b.id=br.book_id WHERE br.member_id=? ORDER BY br.created_at DESC"); $s->execute([$u['id']]); $data['myRequests']=$s->fetchAll(); }
   if ($u['role']==='author') { $s=$pdo->prepare("SELECT b.id,b.title,b.created_at,(SELECT COUNT(*) FROM book_reads r WHERE r.book_id=b.id) AS readers FROM books b WHERE b.author_id=? ORDER BY b.created_at DESC"); $s->execute([$u['id']]); $data['myBooks']=$s->fetchAll(); $s=$pdo->prepare("SELECT b.title,u.name AS member,u.email,br.status,br.rented_at,br.due_at FROM borrow_requests br JOIN books b ON b.id=br.book_id JOIN users u ON u.id=br.member_id WHERE b.author_id=? ORDER BY br.created_at DESC"); $s->execute([$u['id']]); $data['borrowers']=$s->fetchAll(); }
   if ($u['role']==='librarian') { $data['requests']=$pdo->query("SELECT br.*,b.title,u.name AS member,u.email FROM borrow_requests br JOIN books b ON b.id=br.book_id JOIN users u ON u.id=br.member_id ORDER BY br.created_at DESC")->fetchAll(); $data['members']=$pdo->query("SELECT id,name,email,created_at FROM users WHERE role='member' ORDER BY created_at DESC")->fetchAll(); $data['stats']=['books'=>(int)$pdo->query('SELECT COUNT(*) FROM books')->fetchColumn(),'members'=>(int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='member'")->fetchColumn(),'pending'=>(int)$pdo->query("SELECT COUNT(*) FROM borrow_requests WHERE status='pending'")->fetchColumn(),'active'=>(int)$pdo->query("SELECT COUNT(*) FROM borrow_requests WHERE status='approved'")->fetchColumn()]; }
   respond($data);
  case 'add_book':
   $u=requireRole(['author']); $title=postValue('title'); $description=postValue('description'); $content=trim((string)($_POST['content']??'')); $file=$_FILES['book_file']??null;
   if ($title==='') respond(['ok'=>false,'message'=>'Book title is required.'],422);
   $hasUpload=$file && $file['error']!==UPLOAD_ERR_NO_FILE; if (!$hasUpload && $content==='') respond(['ok'=>false,'message'=>'Upload a book file or paste its content.'],422);
   $dir=dirname(__DIR__).'/uploads/books/'; if (!is_dir($dir) && !mkdir($dir,0755,true)) respond(['ok'=>false,'message'=>'Cannot create uploads/books folder.'],500);
   if (!file_exists($dir.'.htaccess')) file_put_contents($dir.'.htaccess',"Require all denied\n"); $path=null;
   if ($hasUpload) { if ($file['error']!==UPLOAD_ERR_OK) respond(['ok'=>false,'message'=>'Upload failed. Check PHP upload_max_filesize and post_max_size.'],422); if ($file['size']>20*1024*1024) respond(['ok'=>false,'message'=>'Maximum file size is 20 MB.'],422); $ext=strtolower(pathinfo($file['name'],PATHINFO_EXTENSION)); if (!in_array($ext,['pdf','doc','docx','txt','docs'],true)) respond(['ok'=>false,'message'=>'Allowed formats: PDF, DOC, DOCX, TXT and DOCS.'],422); $safe=bin2hex(random_bytes(16)).'.'.$ext; if (!move_uploaded_file($file['tmp_name'],$dir.$safe)) respond(['ok'=>false,'message'=>'Could not save uploaded file.'],500); $path='uploads/books/'.$safe; }
   else { $base=preg_replace('/[^A-Za-z0-9_-]/','_', $title); $safe=(trim($base,'_')?:'book').'_'.bin2hex(random_bytes(4)).'.docs'; if (file_put_contents($dir.$safe,$content)===false) respond(['ok'=>false,'message'=>'Could not create .docs file.'],500); $path='uploads/books/'.$safe; }
   $s=$pdo->prepare('INSERT INTO books(author_id,title,description,content,file_path) VALUES(?,?,?,?,?)'); $s->execute([$u['id'],$title,$description,$content,$path]); respond(['ok'=>true,'message'=>'Your book has been added to the bookstore.']);
  case 'borrow':
   $u=requireRole(['member']); $bookId=(int)($_POST['book_id']??0); $s=$pdo->prepare('SELECT id FROM books WHERE id=?'); $s->execute([$bookId]); if (!$s->fetch()) respond(['ok'=>false,'message'=>'Book not found.'],404);
   $s=$pdo->prepare("SELECT id,status FROM borrow_requests WHERE book_id=? AND member_id=? AND status IN ('pending','approved') LIMIT 1"); $s->execute([$bookId,$u['id']]); if ($s->fetch()) respond(['ok'=>false,'message'=>'You already have a pending request or active rental.'],409);
   $s=$pdo->prepare("INSERT INTO borrow_requests(book_id,member_id,status) VALUES(?,?,'pending')"); $s->execute([$bookId,$u['id']]); respond(['ok'=>true,'message'=>'Borrow request sent. Wait for librarian approval.']);
  case 'approve':
   requireRole(['librarian']); $id=(int)($_POST['request_id']??0); $days=(int)($_POST['days']??7); if ($days<1||$days>365) respond(['ok'=>false,'message'=>'Rental duration must be 1–365 days.'],422);
   $s=$pdo->prepare("UPDATE borrow_requests SET status='approved',rental_days=?,rented_at=NOW(),due_at=DATE_ADD(NOW(),INTERVAL ? DAY),returned_at=NULL WHERE id=? AND status='pending'"); $s->execute([$days,$days,$id]); if (!$s->rowCount()) respond(['ok'=>false,'message'=>'Pending request not found.'],404); respond(['ok'=>true,'message'=>'Request approved. The member can now download the book.']);
  case 'reject':
   requireRole(['librarian']); $s=$pdo->prepare("UPDATE borrow_requests SET status='rejected' WHERE id=? AND status='pending'"); $s->execute([(int)($_POST['request_id']??0)]); respond(['ok'=>true,'message'=>'Request rejected.']);
  case 'return':
   requireRole(['librarian']); $s=$pdo->prepare("UPDATE borrow_requests SET status='returned',returned_at=NOW() WHERE id=? AND status='approved'"); $s->execute([(int)($_POST['request_id']??0)]); respond(['ok'=>true,'message'=>'Book return recorded.']);
  case 'delete_book':
   requireRole(['librarian']); $id=(int)($_POST['book_id']??0); $s=$pdo->prepare("SELECT id FROM borrow_requests WHERE book_id=? AND status IN ('pending','approved')"); $s->execute([$id]); if ($s->fetch()) respond(['ok'=>false,'message'=>'Cannot delete a book with pending requests or active rentals.'],409);
   $pdo->beginTransaction(); try { foreach(['book_reads','borrow_requests'] as $table){$s=$pdo->prepare("DELETE FROM {$table} WHERE book_id=?");$s->execute([$id]);} $s=$pdo->prepare('DELETE FROM books WHERE id=?');$s->execute([$id]);$pdo->commit(); } catch(Throwable $e){$pdo->rollBack();throw $e;} respond(['ok'=>true,'message'=>'Book deleted.']);
  case 'read':
   $u=requireRole(['member']); $id=(int)($_GET['book_id']??0); $s=$pdo->prepare('SELECT b.id,b.title,b.description,b.content,u.name AS author FROM books b JOIN users u ON u.id=b.author_id WHERE b.id=?'); $s->execute([$id]); $book=$s->fetch(); if (!$book) respond(['ok'=>false,'message'=>'Book not found.'],404); $s=$pdo->prepare('INSERT IGNORE INTO book_reads(book_id,member_id) VALUES(?,?)');$s->execute([$id,$u['id']]); respond(['ok'=>true,'book'=>$book]);
  default: respond(['ok'=>false,'message'=>'Unknown action.'],400);
 }
} catch (PDOException $e) { $message=$e->getCode()==='23000'?'That email address is already registered.':'A database operation failed.'; respond(['ok'=>false,'message'=>$message,'detail'=>$e->getMessage()],500); }
