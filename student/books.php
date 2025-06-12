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

// Process reservation request (NEW)
if (isset($_POST['request_reservation'])) {
    $bookId = (int)$_POST['book_id'];
    $notes = trim($_POST['notes']);
    $userId = $_SESSION['user_id'];
    
    $result = createReservationRequest($conn, $bookId, $userId, $notes);
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
                
                // Check if user has pending reservation request
                $stmt = $conn->prepare("SELECT id FROM reservation_requests WHERE book_id = ? AND user_id = ? AND status = 'pending'");
                $stmt->bind_param("ii", $book['id'], $_SESSION['user_id']);
                $stmt->execute();
                $hasPendingReservationRequest = $stmt->get_result()->num_rows > 0;
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
                        <?php elseif ($hasPendingReservationRequest): ?>
                            <div class="user-reservation-status">
                                <span class="badge badge-warning">
                                    <i class="fas fa-clock"></i> 
                                    &nbsp;Reservation request pending
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="book-actions">
                        <?php if ($book['available_quantity'] > 0): ?>
                            <button class="btn btn-primary btn-sm modal-trigger" data-modal="requestModal<?php echo $book['id']; ?>">
                                <i class="fas fa-book"></i> Request Book
                            </button>
                        <?php elseif (!$userHasReservation && !$hasPendingReservationRequest): ?>
                            <button class="btn btn-warning btn-sm modal-trigger" data-modal="reserveModal<?php echo $book['id']; ?>">
                                <i class="fas fa-bookmark"></i> Request Reservation
                            </button>
                        <?php elseif ($hasPendingReservationRequest): ?>
                            <button class="btn btn-secondary btn-sm" disabled>
                                <i class="fas fa-clock"></i> Request Pending
                            </button>
                        <?php else: ?>
                            <button class="btn btn-secondary btn-sm" disabled>
                                <i class="fas fa-bookmark"></i> Reserved
                            </button>
                        <?php endif; ?>
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

<!-- Modal Overlay -->
<div class="modal-overlay" id="modalOverlay">
    <div class="modal" id="modalContent">
        <div class="modal-header">
            <h3 class="modal-title" id="modalTitle">Modal Title</h3>
            <button class="modal-close" id="modalClose">&times;</button>
        </div>
        <div class="modal-body" id="modalBody">
            <!-- Modal content will be inserted here -->
        </div>
    </div>
</div>

<!-- Hidden Modal Templates -->
<?php foreach ($books as $book): ?>
    <!-- Request Modal Template -->
    <div class="modal-template" id="requestModal<?php echo $book['id']; ?>" style="display: none;">
        <div class="modal-content">
            <h3>Request Book</h3>
            <div class="book-request-info">
                <h4><?php echo htmlspecialchars($book['title']); ?></h4>
                <p class="text-muted">by <?php echo htmlspecialchars($book['author']); ?></p>
                <div class="availability-status">
                    <span class="badge badge-success">
                        <i class="fas fa-check-circle"></i>&nbsp;Available Now
                    </span>
                </div>
            </div>
            
            <form action="" method="POST">
                <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                
                <div class="form-group">
                    <label for="notes_<?php echo $book['id']; ?>">Additional Notes (Optional)</label>
                    <textarea id="notes_<?php echo $book['id']; ?>" name="notes" class="form-control" rows="3" placeholder="Any special requirements or notes..."></textarea>
                </div>
                
                <div class="form-group text-right">
                    <button type="button" class="btn btn-secondary modal-close-btn">Cancel</button>
                    <button type="submit" name="request_book" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Submit Request
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reserve Modal Template (UPDATED) -->
    <div class="modal-template" id="reserveModal<?php echo $book['id']; ?>" style="display: none;">
        <div class="modal-content">
            <h3>Request Book Reservation</h3>
            <div class="book-reserve-info">
                <h4><?php echo htmlspecialchars($book['title']); ?></h4>
                <p class="text-muted">by <?php echo htmlspecialchars($book['author']); ?></p>
                <div class="availability-status">
                    <span class="badge badge-warning">
                        <i class="fas fa-clock"></i>&nbsp;Currently Unavailable
                    </span>
                </div>
                
                <?php 
                $reservationQueue = getBookReservationQueue($conn, $book['id']);
                if (count($reservationQueue) > 0): 
                ?>
                    <div class="queue-info">
                        <p class="text-info">
                            <i class="fas fa-users"></i> 
                            <?php echo count($reservationQueue); ?> person(s) currently in the reservation queue
                        </p>
                    </div>
                <?php endif; ?>
                
                <div class="reservation-details">
                    <h5>Reservation Request Process:</h5>
                    <ul>
                        <li><strong>Step 1:</strong> Submit your reservation request</li>
                        <li><strong>Step 2:</strong> Librarian will review and approve/reject your request</li>
                        <li><strong>Step 3:</strong> If approved, you'll be added to the reservation queue</li>
                        <li><strong>Step 4:</strong> When the book becomes available, it will be automatically issued to you</li>
                        <li>You'll receive notifications at each step</li>
                    </ul>
                </div>
            </div>
            
            <form action="" method="POST">
                <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                
                <div class="form-group">
                    <label for="reserve_notes_<?php echo $book['id']; ?>">Notes (Optional)</label>
                    <textarea id="reserve_notes_<?php echo $book['id']; ?>" name="notes" class="form-control" rows="3" placeholder="Any special requirements or notes..."></textarea>
                </div>
                
                <div class="form-group text-right">
                    <button type="button" class="btn btn-secondary modal-close-btn">Cancel</button>
                    <button type="submit" name="request_reservation" class="btn btn-warning">
                        <i class="fas fa-paper-plane"></i> Submit Reservation Request
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endforeach; ?>

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

/* Modal Styles */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.5);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 1050;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.modal-overlay.active {
    display: flex;
    opacity: 1;
}

.modal {
    background-color: var(--white);
    border-radius: var(--border-radius);
    box-shadow: 0 5px 30px rgba(0, 0, 0, 0.2);
    width: 100%;
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
    transform: translateY(20px);
    transition: transform 0.3s ease;
}

.modal-overlay.active .modal {
    transform: translateY(0);
}

.modal-header {
    padding: 20px;
    border-bottom: 1px solid var(--gray-300);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--primary-color);
    color: var(--white);
    border-radius: var(--border-radius) var(--border-radius) 0 0;
}

.modal-title {
    margin: 0;
    font-size: 1.25em;
    font-weight: 600;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.5em;
    cursor: pointer;
    color: var(--white);
    padding: 0;
    line-height: 1;
    opacity: 0.8;
    transition: opacity 0.3s ease;
}

.modal-close:hover {
    opacity: 1;
}

.modal-body {
    padding: 20px;
}

.modal-template {
    display: none;
}

.modal-content h3 {
    margin: 0 0 20px 0;
    color: var(--primary-color);
    font-size: 1.3em;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: var(--text-color);
}

.form-control {
    width: 100%;
    padding: 12px;
    border: 2px solid var(--gray-300);
    border-radius: var(--border-radius);
    font-size: 1em;
    transition: var(--transition);
    box-sizing: border-box;
}

.form-control:focus {
    border-color: var(--primary-color);
    outline: none;
    box-shadow: 0 0 0 3px rgba(13, 71, 161, 0.1);
}

.text-right {
    text-align: right;
}

.modal-close-btn {
    margin-right: 10px;
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
    
    .modal {
        margin: 20px;
        max-width: calc(100% - 40px);
    }
    
    .modal-header {
        padding: 15px;
    }
    
    .modal-body {
        padding: 15px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modalOverlay = document.getElementById('modalOverlay');
    const modalContent = document.getElementById('modalContent');
    const modalTitle = document.getElementById('modalTitle');
    const modalBody = document.getElementById('modalBody');
    const modalClose = document.getElementById('modalClose');
    
    // Function to open modal
    function openModal(modalId) {
        const template = document.getElementById(modalId);
        if (!template) return;
        
        const content = template.querySelector('.modal-content');
        if (!content) return;
        
        // Set modal title
        const title = content.querySelector('h3');
        if (title) {
            modalTitle.textContent = title.textContent;
        }
        
        // Clone and insert content
        const clonedContent = content.cloneNode(true);
        modalBody.innerHTML = '';
        modalBody.appendChild(clonedContent);
        
        // Show modal
        modalOverlay.classList.add('active');
        document.body.style.overflow = 'hidden';
        
        // Add event listeners to close buttons in the modal
        const closeButtons = modalBody.querySelectorAll('.modal-close-btn');
        closeButtons.forEach(btn => {
            btn.addEventListener('click', closeModal);
        });
    }
    
    // Function to close modal
    function closeModal() {
        modalOverlay.classList.remove('active');
        document.body.style.overflow = '';
        
        // Clear content after animation
        setTimeout(() => {
            modalBody.innerHTML = '';
        }, 300);
    }
    
    // Event listeners for modal triggers
    const modalTriggers = document.querySelectorAll('.modal-trigger');
    modalTriggers.forEach(trigger => {
        trigger.addEventListener('click', function(e) {
            e.preventDefault();
            const modalId = this.getAttribute('data-modal');
            openModal(modalId);
        });
    });
    
    // Close modal when clicking close button
    modalClose.addEventListener('click', closeModal);
    
    // Close modal when clicking overlay
    modalOverlay.addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });
    
    // Close modal with ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modalOverlay.classList.contains('active')) {
            closeModal();
        }
    });
});
</script>

<?php include_once '../includes/footer.php'; ?>