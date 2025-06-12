<?php
// Include header
include_once '../includes/header.php';

// Check if user is a librarian
checkUserRole('librarian');

// Process request operations
$message = '';
$messageType = '';

// Process a book request (approve/reject)
if (isset($_GET['id']) && isset($_GET['action']) && isset($_GET['type'])) {
    $id = (int)$_GET['id'];
    $action = $_GET['action'];
    $type = $_GET['type']; // 'book' or 'reservation'
    
    if ($type == 'book') {
        // Handle book requests
        if ($action == 'approve') {
            // Get request details
            $stmt = $conn->prepare("
                SELECT br.*, b.title, b.available_quantity, u.id as user_id, u.name as user_name 
                FROM book_requests br
                JOIN books b ON br.book_id = b.id
                JOIN users u ON br.user_id = u.id
                WHERE br.id = ?
            ");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 1) {
                $request = $result->fetch_assoc();
                
                // Check if book is still available
                if ($request['available_quantity'] > 0) {
                    // Start transaction
                    $conn->begin_transaction();
                    try {
                        // Update request status
                        $stmt = $conn->prepare("UPDATE book_requests SET status = 'approved' WHERE id = ?");
                        $stmt->bind_param("i", $id);
                        $stmt->execute();
                        
                        // Generate return date (14 days from now)
                        $returnDate = generateDueDate();
                        
                        // Create issued book record
                        $stmt = $conn->prepare("
                            INSERT INTO issued_books (book_id, user_id, return_date)
                            VALUES (?, ?, ?)
                        ");
                        $stmt->bind_param("iis", $request['book_id'], $request['user_id'], $returnDate);
                        $stmt->execute();
                        
                        // Update book availability
                        updateBookAvailability($conn, $request['book_id'], 'issue');
                        
                        // Send notification to user
                        $notificationMsg = "Your request for '{$request['title']}' has been approved. Please collect the book from the library.";
                        sendNotification($conn, $request['user_id'], $notificationMsg);
                        
                        // Commit transaction
                        $conn->commit();
                        
                        $message = "Book request approved successfully. Book issued to {$request['user_name']}.";
                        $messageType = "success";
                    } catch (Exception $e) {
                        // Rollback transaction on error
                        $conn->rollback();
                        $message = "Error approving request: " . $e->getMessage();
                        $messageType = "danger";
                    }
                } else {
                    $message = "Cannot approve request. The book is not available.";
                    $messageType = "danger";
                }
            } else {
                $message = "Request not found.";
                $messageType = "danger";
            }
        } elseif ($action == 'reject') {
            // Get request details for notification
            $stmt = $conn->prepare("
                SELECT br.*, b.title, u.id as user_id
                FROM book_requests br
                JOIN books b ON br.book_id = b.id
                JOIN users u ON br.user_id = u.id
                WHERE br.id = ?
            ");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 1) {
                $request = $result->fetch_assoc();
                
                // Update request status
                $stmt = $conn->prepare("UPDATE book_requests SET status = 'rejected' WHERE id = ?");
                $stmt->bind_param("i", $id);
                
                if ($stmt->execute()) {
                    // Send notification to user
                    $notificationMsg = "Your request for '{$request['title']}' has been rejected. Please contact the librarian for more information.";
                    sendNotification($conn, $request['user_id'], $notificationMsg);
                    
                    $message = "Book request rejected successfully.";
                    $messageType = "success";
                } else {
                    $message = "Error rejecting request: " . $stmt->error;
                    $messageType = "danger";
                }
            } else {
                $message = "Request not found.";
                $messageType = "danger";
            }
        }
    } elseif ($type == 'reservation') {
        // Handle reservation requests
        if ($action == 'approve') {
            $result = approveReservationRequest($conn, $id);
            $message = $result['message'];
            $messageType = $result['success'] ? 'success' : 'danger';
        } elseif ($action == 'reject') {
            $result = rejectReservationRequest($conn, $id);
            $message = $result['message'];
            $messageType = $result['success'] ? 'success' : 'danger';
        }
    }
}

// Handle search and filtering
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$requestType = isset($_GET['request_type']) ? trim($_GET['request_type']) : '';

// Get book requests
$bookRequests = [];
$sql = "
    SELECT br.*, b.title as book_title, u.name as user_name, 'book' as request_type
    FROM book_requests br
    JOIN books b ON br.book_id = b.id
    JOIN users u ON br.user_id = u.id
    WHERE 1=1
";
$params = [];
$types = "";

if (!empty($search)) {
    $sql .= " AND (b.title LIKE ? OR u.name LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "ss";
}

if (!empty($status)) {
    $sql .= " AND br.status = ?";
    $params[] = $status;
    $types .= "s";
}

if (empty($requestType) || $requestType == 'book') {
    $sql .= " ORDER BY br.request_date DESC";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $bookRequests[] = $row;
    }
}

// Get reservation requests
$reservationRequests = [];
if (empty($requestType) || $requestType == 'reservation') {
    $sql = "
        SELECT rr.*, b.title as book_title, u.name as user_name, 'reservation' as request_type
        FROM reservation_requests rr
        JOIN books b ON rr.book_id = b.id
        JOIN users u ON rr.user_id = u.id
        WHERE 1=1
    ";
    $params = [];
    $types = "";

    if (!empty($search)) {
        $sql .= " AND (b.title LIKE ? OR u.name LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $types .= "ss";
    }

    if (!empty($status)) {
        $sql .= " AND rr.status = ?";
        $params[] = $status;
        $types .= "s";
    }

    $sql .= " ORDER BY rr.request_date DESC";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $reservationRequests[] = $row;
    }
}

// Combine and sort all requests
$allRequests = array_merge($bookRequests, $reservationRequests);
usort($allRequests, function($a, $b) {
    return strtotime($b['request_date']) - strtotime($a['request_date']);
});
?>

<h1 class="page-title">Book & Reservation Requests</h1>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $messageType; ?>">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<div class="d-flex justify-between align-center mb-4">
    <div class="request-summary">
        <div class="badge-container">
            <?php
            // Get request counts by status and type
            $countSql = "
                SELECT 
                    'book' as type,
                    status, 
                    COUNT(*) as count 
                FROM book_requests 
                GROUP BY status
                UNION ALL
                SELECT 
                    'reservation' as type,
                    status, 
                    COUNT(*) as count 
                FROM reservation_requests 
                GROUP BY status
            ";
            $countResult = $conn->query($countSql);
            $counts = [
                'book' => ['pending' => 0, 'approved' => 0, 'rejected' => 0],
                'reservation' => ['pending' => 0, 'approved' => 0, 'rejected' => 0]
            ];
            
            if ($countResult) {
                while ($row = $countResult->fetch_assoc()) {
                    $counts[$row['type']][$row['status']] = $row['count'];
                }
            }
            
            $totalPending = $counts['book']['pending'] + $counts['reservation']['pending'];
            $totalApproved = $counts['book']['approved'] + $counts['reservation']['approved'];
            $totalRejected = $counts['book']['rejected'] + $counts['reservation']['rejected'];
            ?>
            <span class="badge badge-warning">Pending: <?php echo $totalPending; ?></span>
            <span class="badge badge-success">Approved: <?php echo $totalApproved; ?></span>
            <span class="badge badge-danger">Rejected: <?php echo $totalRejected; ?></span>
            <span class="badge badge-info">Book Requests: <?php echo array_sum($counts['book']); ?></span>
            <span class="badge badge-secondary">Reservation Requests: <?php echo array_sum($counts['reservation']); ?></span>
        </div>
    </div>
    
    <div class="d-flex">
        <form action="" method="GET" class="d-flex">
            <div class="form-group mr-2" style="margin-bottom: 0; margin-right: 10px;">
                <input type="text" name="search" placeholder="Search requests..." class="form-control" value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <div class="form-group mr-2" style="margin-bottom: 0; margin-right: 10px;">
                <select name="status" class="form-control">
                    <option value="">All Status</option>
                    <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved" <?php echo $status == 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="rejected" <?php echo $status == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
            </div>
            
            <div class="form-group mr-2" style="margin-bottom: 0; margin-right: 10px;">
                <select name="request_type" class="form-control">
                    <option value="">All Types</option>
                    <option value="book" <?php echo $requestType == 'book' ? 'selected' : ''; ?>>Book Requests</option>
                    <option value="reservation" <?php echo $requestType == 'reservation' ? 'selected' : ''; ?>>Reservation Requests</option>
                </select>
            </div>
            
            <button type="submit" class="btn btn-secondary">
                <i class="fas fa-search"></i> Search
            </button>
        </form>
    </div>
</div>

<div class="table-container" style="margin-top:30px;">
    <table class="table table-striped">
        <thead>
            <tr>
                <th>Type</th>
                <th>Book Title</th>
                <th>Requested By</th>
                <th>Request Date</th>
                <th>Status</th>
                <th>Notes</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($allRequests) > 0): ?>
                <?php foreach ($allRequests as $request): ?>
                    <tr>
                        <td>
                            <?php if ($request['request_type'] == 'book'): ?>
                                <span class="badge badge-primary">
                                    <i class="fas fa-book"></i>&nbsp;Book Request
                                </span>
                            <?php else: ?>
                                <span class="badge badge-warning">
                                    <i class="fas fa-bookmark"></i>&nbsp;Reservation Request
                                </span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($request['book_title']); ?></td>
                        <td><?php echo htmlspecialchars($request['user_name']); ?></td>
                        <td><?php echo date('M d, Y H:i', strtotime($request['request_date'])); ?></td>
                        <td>
                            <?php 
                            switch ($request['status']) {
                                case 'pending':
                                    echo '<span class="badge badge-warning">Pending</span>';
                                    break;
                                case 'approved':
                                    echo '<span class="badge badge-success">Approved</span>';
                                    break;
                                case 'rejected':
                                    echo '<span class="badge badge-danger">Rejected</span>';
                                    break;
                                default:
                                    echo htmlspecialchars($request['status']);
                            }
                            ?>
                        </td>
                        <td><?php echo htmlspecialchars($request['notes']); ?></td>
                        <td>
                            <?php if ($request['status'] == 'pending'): ?>
                                <a href="?id=<?php echo $request['id']; ?>&action=approve&type=<?php echo $request['request_type']; ?>" 
                                   class="btn btn-sm btn-success" 
                                   onclick="return confirm('Are you sure you want to approve this <?php echo $request['request_type']; ?> request?')">
                                    <i class="fas fa-check"></i> Approve
                                </a>
                                <a href="?id=<?php echo $request['id']; ?>&action=reject&type=<?php echo $request['request_type']; ?>" 
                                   class="btn btn-sm btn-danger"
                                   onclick="return confirm('Are you sure you want to reject this <?php echo $request['request_type']; ?> request?')">
                                    <i class="fas fa-times"></i> Reject
                                </a>
                            <?php else: ?>
                                <span class="text-muted">Already processed</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" class="text-center">No requests found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<style>
.badge-container {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
}

@media (max-width: 768px) {
    .badge-container {
        justify-content: center;
        gap: 8px;
    }
    
    .d-flex.justify-between {
        flex-direction: column;
        gap: 15px;
    }
    
    .d-flex form {
        flex-direction: column;
        gap: 10px;
    }
    
    .form-group {
        margin-right: 0 !important;
    }
}
</style>

<?php
// Include footer
include_once '../includes/footer.php';
?>