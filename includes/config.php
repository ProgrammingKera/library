<?php
// Database connection settings
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "library_management";

// Create connection
$conn = new mysqli($servername, $username, $password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

date_default_timezone_set('Asia/Karachi'); 
$conn->query("SET time_zone = '+05:00'");

// Check if database exists, if not create it
$sql = "CREATE DATABASE IF NOT EXISTS $dbname";
if ($conn->query($sql) !== TRUE) {
    die("Error creating database: " . $conn->error);
}

// Select the database
$conn->select_db($dbname);

// Create Users table with unique_id field
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    unique_id VARCHAR(20) UNIQUE,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('librarian', 'student', 'faculty') NOT NULL,
    department VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if ($conn->query($sql) !== TRUE) {
    die("Error creating users table: " . $conn->error);
}

// Add unique_id column if it doesn't exist
$sql = "ALTER TABLE users ADD COLUMN IF NOT EXISTS unique_id VARCHAR(20) UNIQUE AFTER id";
$conn->query($sql);

// Create Books table
$sql = "CREATE TABLE IF NOT EXISTS books (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(100) NOT NULL,
    isbn VARCHAR(20) UNIQUE,
    publisher VARCHAR(100),
    publication_year INT(4),
    category VARCHAR(50),
    available_quantity INT(11) NOT NULL DEFAULT 0,
    total_quantity INT(11) NOT NULL DEFAULT 0,
    shelf_location VARCHAR(50),
    description TEXT,
    cover_image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
if ($conn->query($sql) !== TRUE) {
    die("Error creating books table: " . $conn->error);
}

// Create E-books table
$sql = "CREATE TABLE IF NOT EXISTS ebooks (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(100) NOT NULL,
    category VARCHAR(50),
    file_path VARCHAR(255) NOT NULL,
    file_size VARCHAR(20),
    file_type VARCHAR(20),
    description TEXT,
    uploaded_by INT(11),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
)";
if ($conn->query($sql) !== TRUE) {
    die("Error creating ebooks table: " . $conn->error);
}

// Create Issued Books table
$sql = "CREATE TABLE IF NOT EXISTS issued_books (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    book_id INT(11) NOT NULL,
    user_id INT(11) NOT NULL,
    issue_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    return_date DATE NOT NULL,
    actual_return_date DATE,
    status ENUM('issued', 'returned', 'overdue') NOT NULL DEFAULT 'issued',
    fine_amount DECIMAL(10,2) DEFAULT 0.00,
    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";
if ($conn->query($sql) !== TRUE) {
    die("Error creating issued_books table: " . $conn->error);
}

// Create Book Requests table
$sql = "CREATE TABLE IF NOT EXISTS book_requests (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    book_id INT(11) NOT NULL,
    user_id INT(11) NOT NULL,
    request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    notes TEXT,
    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";
if ($conn->query($sql) !== TRUE) {
    die("Error creating book_requests table: " . $conn->error);
}

// Create Reservation Requests table (NEW)
$sql = "CREATE TABLE IF NOT EXISTS reservation_requests (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    book_id INT(11) NOT NULL,
    user_id INT(11) NOT NULL,
    request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    notes TEXT,
    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";
if ($conn->query($sql) !== TRUE) {
    die("Error creating reservation_requests table: " . $conn->error);
}

// Create Book Reservations table
$sql = "CREATE TABLE IF NOT EXISTS book_reservations (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    book_id INT(11) NOT NULL,
    user_id INT(11) NOT NULL,
    reservation_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active', 'fulfilled', 'cancelled', 'expired') NOT NULL DEFAULT 'active',
    priority_number INT(11) NOT NULL,
    expires_at DATETIME NOT NULL,
    notified_at DATETIME NULL,
    notes TEXT,
    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_book_priority (book_id, priority_number),
    INDEX idx_status_expires (status, expires_at)
)";
if ($conn->query($sql) !== TRUE) {
    die("Error creating book_reservations table: " . $conn->error);
}

// Create Login Attempts table for security
$sql = "CREATE TABLE IF NOT EXISTS login_attempts (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(100) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    success BOOLEAN DEFAULT FALSE,
    INDEX idx_identifier_time (identifier, attempt_time),
    INDEX idx_ip_time (ip_address, attempt_time)
)";
if ($conn->query($sql) !== TRUE) {
    die("Error creating login_attempts table: " . $conn->error);
}

// Create Fines table
$sql = "CREATE TABLE IF NOT EXISTS fines (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    issued_book_id INT(11) NOT NULL,
    user_id INT(11) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    reason TEXT,
    status ENUM('pending', 'paid') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (issued_book_id) REFERENCES issued_books(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";
if ($conn->query($sql) !== TRUE) {
    die("Error creating fines table: " . $conn->error);
}

// Create Payments table
$sql = "CREATE TABLE IF NOT EXISTS payments (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    fine_id INT(11) NOT NULL,
    user_id INT(11) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    payment_method VARCHAR(50),
    receipt_number VARCHAR(50),
    transaction_id VARCHAR(100),
    payment_details TEXT,
    FOREIGN KEY (fine_id) REFERENCES fines(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";
if ($conn->query($sql) !== TRUE) {
    die("Error creating payments table: " . $conn->error);
}

// Create Notifications table
$sql = "CREATE TABLE IF NOT EXISTS notifications (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";
if ($conn->query($sql) !== TRUE) {
    die("Error creating notifications table: " . $conn->error);
}

// Function to generate unique ID
function generateUniqueId($conn, $role) {
    $prefix = '';
    switch($role) {
        case 'student':
            $prefix = 'STU';
            break;
        case 'faculty':
            $prefix = 'FAC';
            break;
        case 'librarian':
            $prefix = 'LIB';
            break;
    }
    
    do {
        $randomNumber = str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
        $uniqueId = $prefix . $randomNumber;
        
        // Check if this ID already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE unique_id = ?");
        $stmt->bind_param("s", $uniqueId);
        $stmt->execute();
        $result = $stmt->get_result();
    } while ($result->num_rows > 0);
    
    return $uniqueId;
}

// Function to check login attempts and implement security
function checkLoginAttempts($conn, $identifier, $ipAddress) {
    // Clean old attempts (older than 30 minutes)
    $cleanupSql = "DELETE FROM login_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 30 MINUTE)";
    $conn->query($cleanupSql);
    
    // Check failed attempts in last 30 seconds
    $stmt = $conn->prepare("
        SELECT COUNT(*) as failed_attempts, 
               MAX(attempt_time) as last_attempt
        FROM login_attempts 
        WHERE (identifier = ? OR ip_address = ?) 
        AND success = FALSE 
        AND attempt_time > DATE_SUB(NOW(), INTERVAL 30 SECOND)
    ");
    $stmt->bind_param("ss", $identifier, $ipAddress);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row['failed_attempts'] >= 5) {
        $lastAttempt = new DateTime($row['last_attempt']);
        $now = new DateTime();
        $timeDiff = $now->getTimestamp() - $lastAttempt->getTimestamp();
        
        if ($timeDiff < 30) {
            return array(
                'blocked' => true,
                'remaining_time' => 30 - $timeDiff,
                'message' => 'Too many failed login attempts. Please wait ' . (30 - $timeDiff) . ' seconds before trying again.'
            );
        }
    }
    
    return array('blocked' => false);
}

// Function to record login attempt
function recordLoginAttempt($conn, $identifier, $ipAddress, $success) {
    $stmt = $conn->prepare("INSERT INTO login_attempts (identifier, ip_address, success) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $identifier, $ipAddress, $success);
    $stmt->execute();
}

// Function to get user's IP address
function getUserIpAddress() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

// Check if default librarian exists, if not create one
$stmt = $conn->prepare("SELECT * FROM users WHERE email = 'admin@library.com' AND role = 'librarian'");
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    // Create default librarian account
    $name = "Admin Librarian";
    $email = "admin@library.com";
    $password = password_hash("password123", PASSWORD_DEFAULT);
    $role = "librarian";
    $uniqueId = generateUniqueId($conn, $role);
    
    $stmt = $conn->prepare("INSERT INTO users (unique_id, name, email, password, role) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $uniqueId, $name, $email, $password, $role);
    
    if (!$stmt->execute()) {
        die("Error creating default librarian: " . $stmt->error);
    }
}
?>