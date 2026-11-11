# PageTurn — Library Management System

A modern, responsive PHP + MySQL library system. The user interface is separated into standalone HTML pages, CSS and JavaScript. PHP is used only for the backend API and protected downloads.

## Folder structure

```text
Library-Management-System/
├── index.html                 # Main dashboard / welcome page
├── login.html                 # Sign-in page
├── register.html              # Registration page
├── api/
│   ├── bootstrap.php          # Database connection, schema and helpers
│   ├── api.php                # Authentication and library API actions
│   └── download.php           # Approval-protected book download
├── assets/
│   ├── css/style.css          # Modern responsive UI
│   └── js/app.js              # Frontend behaviour and API calls
└── uploads/books/             # Uploaded books and generated .docs files
```

## Run using XAMPP (Windows)

1. Extract the ZIP file.
2. Copy the `Library-Management-System` folder into `C:\xampp\htdocs\`.
3. Open XAMPP Control Panel and start **Apache** and **MySQL**.
4. Open `http://localhost/Library-Management-System/`.
5. The PHP backend creates/updates the `library_system` database and required tables automatically. Existing records in that database are retained.

### Default librarian account

- Email: `admin@library.com`
- Password: `Admin123!`

Sign in and change this default password before using the site with real users. The default account is created only if it does not already exist.

## Features

### Librarian
- Dashboard statistics for books, members, pending requests and active rentals.
- Approve or reject member borrowing requests.
- Set a rental duration from 1 to 365 days.
- Record returned books.
- View registered members and manage books.
- Cannot delete a book while it has pending requests or active rentals.

### Member
- Browse and search the bookstore.
- Request books and see pending, approved, rejected or returned status.
- Read available book content online.
- Download an uploaded/generated book file only when its request is approved. Pending requests show a disabled waiting status.
- View rental duration and due date.

### Author
- Register as an author.
- Upload PDF, DOC, DOCX, TXT or DOCS files (20 MB application limit).
- Or paste content; the system creates a title-based `.docs` file.
- View published books, unique reader counts and borrower activity.

## PHP upload limits

The application accepts files up to 20 MB. PHP may have a lower limit. If uploads fail, open `C:\xampp\php\php.ini` and set for example:

```ini
upload_max_filesize=25M
post_max_size=30M
```

Save the file and restart Apache.

## Database configuration

Default XAMPP settings are in `api/bootstrap.php`:

```php
$host = 'localhost';
$dbname = 'library_system';
$dbUser = 'root';
$dbPass = '';
```

Change these if your MySQL username or password is different.

## Notes

- `.docs` is a custom plain-text file extension generated for pasted content; it is not a Microsoft Word `.docx` document.
- Uploaded files are stored in `uploads/books/`. Apache access is denied there; downloads must go through `api/download.php`, which checks the member's approved request.
- This is a school-project starter. Before public production deployment, add CSRF protection, password reset, librarian password management, rate limiting, HTTPS and stronger upload scanning. Do not expose the default librarian credentials publicly.
- Use PHP 8+ and MySQL/MariaDB with PDO MySQL enabled.
