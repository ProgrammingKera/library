<?php
include_once '../includes/header.php';

// Check if user is student or faculty
if ($_SESSION['role'] != 'student' && $_SESSION['role'] != 'faculty') {
    header('Location: ../index.php');
    exit();
}

// Process book request
if (isset($_POST['request_book'])) {
    $bookId = (int)$_POST['book_id'];
    $notes = trim($_POST['notes']);
    $userId = $_SESSION['user_id'];
    
    // Check if book is available
    $stmt = $conn->prepare("SELECT available_quantity FROM books WHERE id = ?");
    $stmt->bind_param("i", $bookId);
    $stmt->execute();
    $result = $stmt->get_result();
    $book = $result->fetch_assoc();
    
    if ($book['available_quantity'] > 0) {
        $stmt = $conn->prepare("INSERT INTO book_requests (book_id, user_id, notes) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $bookId, $userId, $notes);
        
        if ($stmt->execute()) {
            $message = "Book request submitted successfully.";
            $messageType = "success";
        } else {
            $message = "Error submitting request: " . $stmt->error;
            $messageType = "danger";
        }
    } else {
        $message = "Book is not available for immediate issue.";
        $messageType = "warning";
    }
}

// Process book reservation
if (isset($_POST['reserve_book'])) {
    $bookId = (int)$_POST['book_id'];
    $notes = trim($_POST['notes']);
    $userId = $_SESSION['user_id'];
    
    $result = createBookReservation($conn, $bookId, $userId, $notes);
    $message = $result['message'];
    $messageType = $result['success'] ? 'success' : 'danger';
}

// Handle search and filtering
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';

// Get all categories
$categories = [];
$result = $conn->query("SELECT DISTINCT category FROM books WHERE category != '' ORDER BY category");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row['category'];
    }
}

// Build search query
$sql = "SELECT * FROM books WHERE 1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $sql .= " AND (title LIKE ? OR author LIKE ? OR isbn LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "sss";
}

if (!empty($category)) {
    $sql .= " AND category = ?";
    $params[] = $category;
    $types .= "s";
}

$sql .= " ORDER BY title";

// Execute search
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$books = [];
while ($row = $result->fetch_assoc()) {
    $books[] = $row;
}

// Clean expired reservations
cleanExpiredReservations($conn);
?>

<div class="container">
    <h1 class="page-title">Library Books</h1>

    <?php if (isset($message)): ?>
        <div class="alert alert-<?php echo $messageType; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <div class="search-section mb-4">
        <form action="" method="GET" class="search-form">
            <div class="search-row">
                <div class="search-input-group">
                    <input type="text" name="search" placeholder="Search books by title, author, or ISBN..." 
                           class="form-control search-input" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="search-select-group">
                    <select name="category" class="form-control category-select">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $category == $cat ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="search-button-group">
                    <button type="submit" class="btn btn-primary search-btn">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <?php if (!empty($search) || !empty($category)): ?>
                        <a href="books.php" class="btn btn-secondary clear-btn">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <div class="books-grid">
        <?php if (count($books) > 0): ?>
            <?php foreach ($books as $book): ?>
                <?php
                // Get reservation queue for this book
                $reservationQueue = getBookReservationQueue($conn, $book['id']);
                $userHasReservation = false;
                $userReservationPosition = 0;
                
                foreach ($reservationQueue as $index => $reservation) {
                    if ($reservation['user_id'] == $_SESSION['user_id']) {
                        $userHasReservation = true;
                        $userReservationPosition = $index + 1;
                        break;
                    }
                }
                ?>
                <div class="book-card">
                    <div class="book-cover">
                        <?php if (!empty($book['cover_image'])): ?>
                            <img src="<?php echo htmlspecialchars($book['cover_image']); ?>" alt="<?php echo htmlspecialchars($book['title']); ?>">
                        <?php else: ?>
                            <i class="fas fa-book fa-3x"></i>
                        <?php endif; ?>
                    </div>
                    <div class="book-info">
                        <h3 class="book-title"><?php echo htmlspecialchars($book['title']); ?></h3>
                        <p class="book-author">By <?php echo htmlspecialchars($book['author']); ?></p>
                        <div class="book-details">
                            <span><?php echo htmlspecialchars($book['category']); ?></span>
                            <span>
                                <?php echo $book['available_quantity']; ?> / <?php echo $book['total_quantity']; ?> available
                            </span>
                        </div>
                        
                        <?php if (count($reservationQueue) > 0): ?>
                            <div class="reservation-info">
                                <small class="text-info">
                                    <i class="fas fa-users"></i> 
                                    <?php echo count($reservationQueue); ?> person(s) in reservation queue
                                </small>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($userHasReservation): ?>
                            <div class="user-reservation-status">
                                <span class="badge badge-info">
                                    <i class="fas fa-bookmark"></i> 
                                    You're #<?php echo $userReservationPosition; ?> in queue
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="book-actions">
                        <?php if ($book['available_quantity'] > 0): ?>
                            <button class="btn btn-primary btn-sm" data-modal-target="requestModal<?php echo $book['id']; ?>">
                                <i class="fas fa-book"></i> Request Book
                            </button>
                        <?php elseif (!$userHasReservation): ?>
                            <button class="btn btn-warning btn-sm" data-modal-target="reserveModal<?php echo $book['id']; ?>">
                                <i class="fas fa-bookmark"></i> Reserve Book
                            </button>
                        <?php else: ?>
                            <button class="btn btn-secondary btn-sm" disabled>
                                <i class="fas fa-bookmark"></i> Reserved
                            </button>
                        <?php endif; ?>
                    </div>

                    <!-- Request Modal -->
                    <div class="modal-overlay" id="requestModal<?php echo $book['id']; ?>">
                        <div class="modal">
                            <div class="modal-header">
                                <h3 class="modal-title">Request Book</h3>
                                <button class="modal-close">&times;</button>
                            </div>
                            <div class="modal-body">
                                <div class="book-request-info">
                                    <h4><?php echo htmlspecialchars($book['title']); ?></h4>
                                    <p class="text-muted">by <?php echo htmlspecialchars($book['author']); ?></p>
                                    <div class="availability-status">
                                        <span class="badge badge-success">
                                            <i class="fas fa-check-circle"></i> Available Now
                                        </span>
                                    </div>
                                </div>
                                
                                <form action="" method="POST">
                                    <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                                    
                                    <div class="form-group">
                                        <label for="notes">Additional Notes (Optional)</label>
                                        <textarea id="notes" name="notes" class="form-control" rows="3" placeholder="Any special requirements or notes..."></textarea>
                                    </div>
                                    
                                    <div class="form-group text-right">
                                        <button type="button" class="btn btn-secondary modal-close">Cancel</button>
                                        <button type="submit" name="request_book" class="btn btn-primary">
                                            <i class="fas fa-paper-plane"></i> Submit Request
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Reserve Modal -->
                    <div class="modal-overlay" id="reserveModal<?php echo $book['id']; ?>">
                        <div class="modal">
                            <div class="modal-header">
                                <h3 class="modal-title">Reserve Book</h3>
                                <button class="modal-close">&times;</button>
                            </div>
                            <div class="modal-body">
                                <div class="book-reserve-info">
                                    <h4><?php echo htmlspecialchars($book['title']); ?></h4>
                                    <p class="text-muted">by <?php echo htmlspecialchars($book['author']); ?></p>
                                    <div class="availability-status">
                                        <span class="badge badge-warning">
                                            <i class="fas fa-clock"></i> Currently Unavailable
                                        </span>
                                    </div>
                                    
                                    <?php if (count($reservationQueue) > 0): ?>
                                        <div class="queue-info">
                                            <p class="text-info">
                                                <i class="fas fa-users"></i> 
                                                <?php echo count($reservationQueue); ?> person(s) ahead of you in the queue
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="reservation-details">
                                        <h5>Reservation Details:</h5>
                                        <ul>
                                            <li>You will be notified when the book becomes available</li>
                                            <li>You'll have 24 hours to collect the book once notified</li>
                                            <li>Reservations expire after 7 days if not fulfilled</li>
                                            <li>You can cancel your reservation anytime</li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <form action="" method="POST">
                                    <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                                    
                                    <div class="form-group">
                                        <label for="reserve_notes">Notes (Optional)</label>
                                        <textarea id="reserve_notes" name="notes" class="form-control" rows="3" placeholder="Any special requirements or notes..."></textarea>
                                    </div>
                                    
                                    <div class="form-group text-right">
                                        <button type="button" class="btn btn-secondary modal-close">Cancel</button>
                                        <button type="submit" name="reserve_book" class="btn btn-warning">
                                            <i class="fas fa-bookmark"></i> Reserve Book
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-results">
                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                <h3>No Books Found</h3>
                <p class="text-muted">
                    <?php if (!empty($search) || !empty($category)): ?>
                        No books match your search criteria. Try adjusting your search terms.
                    <?php else: ?>
                        No books are currently available in the library.
                    <?php endif; ?>
                </p>
                <?php if (!empty($search) || !empty($category)): ?>
                    <a href="books.php" class="btn btn-primary">
                        <i class="fas fa-list"></i> View All Books
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.search-section {
    background: var(--white);
    padding: 20px;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    margin-bottom: 30px;
}

.search-form {
    width: 100%;
}

.search-row {
    display: flex;
    gap: 15px;
    align-items: flex-end;
    flex-wrap: wrap;
}

.search-input-group {
    flex: 2;
    min-width: 250px;
}

.search-select-group {
    flex: 1;
    min-width: 200px;
}

.search-button-group {
    display: flex;
    gap: 10px;
}

.search-input, .category-select {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid var(--gray-300);
    border-radius: var(--border-radius);
    font-size: 1em;
    transition: var(--transition);
}

.search-input:focus, .category-select:focus {
    border-color: var(--primary-color);
    outline: none;
    box-shadow: 0 0 0 3px rgba(13, 71, 161, 0.1);
}

.search-btn, .clear-btn {
    padding: 12px 20px;
    white-space: nowrap;
    font-weight: 500;
}

.clear-btn {
    background-color: var(--gray-400);
    color: var(--white);
}

.clear-btn:hover {
    background-color: var(--gray-500);
}

.no-results {
    text-align: center;
    padding: 60px 20px;
    background: var(--white);
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
}

.no-results h3 {
    color: var(--text-color);
    margin-bottom: 15px;
}

.reservation-info {
    margin-top: 10px;
    padding: 8px;
    background: rgba(23, 162, 184, 0.1);
    border-radius: 4px;
}

.user-reservation-status {
    margin-top: 10px;
}

.book-request-info, .book-reserve-info {
    margin-bottom: 20px;
    padding: 15px;
    background: var(--gray-100);
    border-radius: var(--border-radius);
}

.book-request-info h4, .book-reserve-info h4 {
    margin: 0 0 5px 0;
    color: var(--primary-color);
}

.availability-status {
    margin: 10px 0;
}

.queue-info {
    margin: 15px 0;
    padding: 10px;
    background: rgba(255, 193, 7, 0.1);
    border-radius: 4px;
}

.reservation-details {
    margin-top: 15px;
    padding: 15px;
    background: var(--white);
    border-radius: var(--border-radius);
    border-left: 4px solid var(--warning-color);
}

.reservation-details h5 {
    margin: 0 0 10px 0;
    color: var(--primary-color);
}

.reservation-details ul {
    margin: 0;
    padding-left: 20px;
}

.reservation-details li {
    margin-bottom: 5px;
    color: var(--text-color);
}

@media (max-width: 768px) {
    .search-row {
        flex-direction: column;
        align-items: stretch;
    }
    
    .search-input-group,
    .search-select-group {
        flex: none;
        min-width: auto;
    }
    
    .search-button-group {
        justify-content: center;
    }
    
    .search-btn, .clear-btn {
        flex: 1;
        max-width: 150px;
    }
}
</style>

<?php include_once '../includes/footer.php'; ?>