<?php
// Check if user is logged in and has the right role
function checkUserRole($requiredRole) {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        header('Location: ../index.php');
        exit();
    }
    
    if ($_SESSION['role'] != $requiredRole) {
        header('Location: ../index.php');
        exit();
    }
}

// Get total number of books in library
function getTotalBooks($conn) {
    $sql = "SELECT SUM(total_quantity) as total FROM books";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    return $row['total'] ? $row['total'] : 0;
}

// Get total number of issued books
function getIssuedBooks($conn) {
    $sql = "SELECT COUNT(*) as total FROM issued_books WHERE status = 'issued' OR status = 'overdue'";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    return $row['total'] ? $row['total'] : 0;
}

// Get total number of users
function getTotalUsers($conn) {
    $sql = "SELECT COUNT(*) as total FROM users WHERE role != 'librarian'";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    return $row['total'] ? $row['total'] : 0;
}

// Get total number of pending requests
function getPendingRequests($conn) {
    $sql = "SELECT COUNT(*) as total FROM book_requests WHERE status = 'pending'";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    return $row['total'] ? $row['total'] : 0;
}

// Get total unpaid fines
function getTotalUnpaidFines($conn) {
    $sql = "SELECT SUM(amount) as total FROM fines WHERE status = 'pending'";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    return $row['total'] ? $row['total'] : 0;
}

// Generate due date for book issue (14 days from now by default)
function generateDueDate($days = 14) {
    $date = new DateTime();
    $date->add(new DateInterval("P{$days}D"));
    return $date->format('Y-m-d');
}

// Calculate fine amount based on days overdue
function calculateFine($dueDate, $returnDate, $finePerDay = 1.00) {
    $due = new DateTime($dueDate);
    $return = new DateTime($returnDate);
    $diff = $return->diff($due);
    
    if ($return > $due) {
        return $diff->days * $finePerDay;
    }
    
    return 0;
}

// Upload file and return path
function uploadFile($file, $targetDir = '../uploads/ebooks/') {
    // Create directory if it doesn't exist
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    
    $fileName = basename($file['name']);
    $targetFilePath = $targetDir . $fileName;
    $fileType = pathinfo($targetFilePath, PATHINFO_EXTENSION);
    
    // Generate unique file name to prevent overwriting
    $fileName = uniqid() . '_' . $fileName;
    $targetFilePath = $targetDir . $fileName;
    
    // Allow only certain file formats
    $allowedTypes = array('pdf', 'doc', 'docx', 'epub');
    if (!in_array(strtolower($fileType), $allowedTypes)) {
        return array('success' => false, 'message' => 'Only PDF, DOC, DOCX & EPUB files are allowed.');
    }
    
    // Check file size (limit to 10MB)
    if ($file['size'] > 10 * 1024 * 1024) {
        return array('success' => false, 'message' => 'File size should be less than 10MB.');
    }
    
    // Upload file
    if (move_uploaded_file($file['tmp_name'], $targetFilePath)) {
        return array(
            'success' => true,
            'file_path' => $targetFilePath,
            'file_name' => $fileName,
            'file_size' => formatFileSize($file['size']),
            'file_type' => $fileType
        );
    } else {
        return array('success' => false, 'message' => 'There was an error uploading your file.');
    }
}

// Format file size
function formatFileSize($size) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $i = 0;
    while ($size >= 1024 && $i < 4) {
        $size /= 1024;
        $i++;
    }
    return round($size, 2) . ' ' . $units[$i];
}

// Send notification to user
function sendNotification($conn, $userId, $message) {
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
    $stmt->bind_param("is", $userId, $message);
    return $stmt->execute();
}

// Format date for display
function formatDate($date) {
    return date('F j, Y', strtotime($date));
}

// Check if a book can be issued (available quantity > 0)
function canIssueBook($conn, $bookId) {
    $stmt = $conn->prepare("SELECT available_quantity FROM books WHERE id = ?");
    $stmt->bind_param("i", $bookId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $book = $result->fetch_assoc();
        return $book['available_quantity'] > 0;
    }
    
    return false;
}

// Update book availability when issued or returned
function updateBookAvailability($conn, $bookId, $action = 'issue') {
    if ($action == 'issue') {
        $sql = "UPDATE books SET available_quantity = available_quantity - 1 WHERE id = ? AND available_quantity > 0";
    } else {
        $sql = "UPDATE books SET available_quantity = available_quantity + 1 WHERE id = ?";
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $bookId);
    return $stmt->execute();
}

// Get user name by ID
function getUserName($conn, $userId) {
    $stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        return $user['name'];
    }
    
    return 'Unknown User';
}

// Get book title by ID
function getBookTitle($conn, $bookId) {
    $stmt = $conn->prepare("SELECT title FROM books WHERE id = ?");
    $stmt->bind_param("i", $bookId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $book = $result->fetch_assoc();
        return $book['title'];
    }
    
    return 'Unknown Book';
}

// Book Reservation Functions

// Create a book reservation
function createBookReservation($conn, $bookId, $userId, $notes = '') {
    // Check if user already has an active reservation for this book
    $stmt = $conn->prepare("
        SELECT id FROM book_reservations 
        WHERE book_id = ? AND user_id = ? AND status = 'active'
    ");
    $stmt->bind_param("ii", $bookId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return array('success' => false, 'message' => 'You already have an active reservation for this book.');
    }
    
    // Get next priority number
    $stmt = $conn->prepare("
        SELECT COALESCE(MAX(priority_number), 0) + 1 as next_priority 
        FROM book_reservations 
        WHERE book_id = ? AND status = 'active'
    ");
    $stmt->bind_param("i", $bookId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $priorityNumber = $row['next_priority'];
    
    // Set expiration date (7 days from now)
    $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));
    
    // Create reservation
    $stmt = $conn->prepare("
        INSERT INTO book_reservations (book_id, user_id, priority_number, expires_at, notes)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("iiiss", $bookId, $userId, $priorityNumber, $expiresAt, $notes);
    
    if ($stmt->execute()) {
        // Send notification
        $bookTitle = getBookTitle($conn, $bookId);
        $message = "You have successfully reserved '{$bookTitle}'. You are #$priorityNumber in the queue. You will be notified when the book becomes available.";
        sendNotification($conn, $userId, $message);
        
        return array(
            'success' => true, 
            'message' => "Book reserved successfully! You are #$priorityNumber in the queue.",
            'priority' => $priorityNumber
        );
    } else {
        return array('success' => false, 'message' => 'Error creating reservation.');
    }
}

// Cancel a book reservation
function cancelBookReservation($conn, $reservationId, $userId) {
    $stmt = $conn->prepare("
        UPDATE book_reservations 
        SET status = 'cancelled' 
        WHERE id = ? AND user_id = ? AND status = 'active'
    ");
    $stmt->bind_param("ii", $reservationId, $userId);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        // Reorder priorities for remaining reservations
        reorderReservationPriorities($conn, $reservationId);
        return array('success' => true, 'message' => 'Reservation cancelled successfully.');
    } else {
        return array('success' => false, 'message' => 'Reservation not found or cannot be cancelled.');
    }
}

// Reorder reservation priorities after cancellation
function reorderReservationPriorities($conn, $cancelledReservationId) {
    // Get the cancelled reservation details
    $stmt = $conn->prepare("
        SELECT book_id, priority_number 
        FROM book_reservations 
        WHERE id = ?
    ");
    $stmt->bind_param("i", $cancelledReservationId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $cancelled = $result->fetch_assoc();
        
        // Update priorities for reservations with higher priority numbers
        $stmt = $conn->prepare("
            UPDATE book_reservations 
            SET priority_number = priority_number - 1 
            WHERE book_id = ? AND priority_number > ? AND status = 'active'
        ");
        $stmt->bind_param("ii", $cancelled['book_id'], $cancelled['priority_number']);
        $stmt->execute();
    }
}

// Process reservations when a book is returned
function processBookReservations($conn, $bookId) {
    // Get the next reservation in queue
    $stmt = $conn->prepare("
        SELECT r.*, u.name as user_name, u.email as user_email, b.title as book_title
        FROM book_reservations r
        JOIN users u ON r.user_id = u.id
        JOIN books b ON r.book_id = b.id
        WHERE r.book_id = ? AND r.status = 'active'
        ORDER BY r.priority_number ASC
        LIMIT 1
    ");
    $stmt->bind_param("i", $bookId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $reservation = $result->fetch_assoc();
        
        // Mark reservation as fulfilled
        $stmt = $conn->prepare("
            UPDATE book_reservations 
            SET status = 'fulfilled', notified_at = NOW() 
            WHERE id = ?
        ");
        $stmt->bind_param("i", $reservation['id']);
        $stmt->execute();
        
        // Send notification to user
        $message = "Good news! The book '{$reservation['book_title']}' you reserved is now available. Please visit the library within 24 hours to collect it, or your reservation will be cancelled.";
        sendNotification($conn, $reservation['user_id'], $message);
        
        // Reorder remaining reservations
        reorderReservationPriorities($conn, $reservation['id']);
        
        return array(
            'success' => true,
            'user_notified' => $reservation['user_name'],
            'message' => "Book reserved for {$reservation['user_name']}. They have been notified."
        );
    }
    
    return array('success' => false, 'message' => 'No active reservations found.');
}

// Get user's reservations
function getUserReservations($conn, $userId) {
    $stmt = $conn->prepare("
        SELECT r.*, b.title, b.author, b.available_quantity
        FROM book_reservations r
        JOIN books b ON r.book_id = b.id
        WHERE r.user_id = ?
        ORDER BY r.status ASC, r.priority_number ASC
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $reservations = array();
    while ($row = $result->fetch_assoc()) {
        $reservations[] = $row;
    }
    
    return $reservations;
}

// Get book reservation queue
function getBookReservationQueue($conn, $bookId) {
    $stmt = $conn->prepare("
        SELECT r.*, u.name as user_name, u.email as user_email
        FROM book_reservations r
        JOIN users u ON r.user_id = u.id
        WHERE r.book_id = ? AND r.status = 'active'
        ORDER BY r.priority_number ASC
    ");
    $stmt->bind_param("i", $bookId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $queue = array();
    while ($row = $result->fetch_assoc()) {
        $queue[] = $row;
    }
    
    return $queue;
}

// Clean expired reservations
function cleanExpiredReservations($conn) {
    // Get expired reservations
    $stmt = $conn->prepare("
        SELECT id, book_id, user_id, priority_number
        FROM book_reservations 
        WHERE status = 'active' AND expires_at < NOW()
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $expiredReservations = array();
    while ($row = $result->fetch_assoc()) {
        $expiredReservations[] = $row;
    }
    
    // Mark as expired and reorder priorities
    foreach ($expiredReservations as $reservation) {
        $stmt = $conn->prepare("
            UPDATE book_reservations 
            SET status = 'expired' 
            WHERE id = ?
        ");
        $stmt->bind_param("i", $reservation['id']);
        $stmt->execute();
        
        // Reorder priorities
        reorderReservationPriorities($conn, $reservation['id']);
        
        // Send notification to user
        $bookTitle = getBookTitle($conn, $reservation['book_id']);
        $message = "Your reservation for '{$bookTitle}' has expired. You can create a new reservation if the book is still unavailable.";
        sendNotification($conn, $reservation['user_id'], $message);
    }
    
    return count($expiredReservations);
}
?>