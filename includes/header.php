<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
include_once __DIR__ . '/config.php';
include_once __DIR__ . '/functions.php';

// Redirect to login page if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

// Get unread notifications count
$notificationCount = 0;
if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $notificationCount = $row['count'];
    }
}

// Get current page for active menu highlighting
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Management System</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="icon" type="image/svg+xml" href="../uploads/assests/book.svg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-book-reader"></i> LMS</h2>
                <p>Library Management System</p>
            </div>
            
            <div class="sidebar-menu">
                <?php
                
                if (isset($_SESSION['role']) && ($_SESSION['role'] === 'student' || $_SESSION['role'] === 'faculty')): ?>
                    <a href="dashboard.php" class="sidebar-menu-item <?php echo $currentPage == 'dashboard.php' ? 'active' : ''; ?>">
                        <i class="fas fa-tachometer-alt"></i>
                        <span class="sidebar-menu-label">Dashboard</span>
                    </a>
                    <a href="books.php" class="sidebar-menu-item <?php echo $currentPage == 'books.php' ? 'active' : ''; ?>">
                        <i class="fas fa-book"></i>
                        <span class="sidebar-menu-label">Books</span>
                    </a>
                    <a href="ebooks.php" class="sidebar-menu-item <?php echo $currentPage == 'ebooks.php' ? 'active' : ''; ?>">
                        <i class="fas fa-file-pdf"></i>
                        <span class="sidebar-menu-label">E-Books</span>
                    </a>
                    <a href="requests.php" class="sidebar-menu-item <?php echo $currentPage == 'requests.php' ? 'active' : ''; ?>">
                        <i class="fas fa-bookmark"></i>
                        <span class="sidebar-menu-label">My Requests</span>
                    </a>
                    <a href="returns.php" class="sidebar-menu-item <?php echo $currentPage == 'returns.php' ? 'active' : ''; ?>">
                        <i class="fas fa-undo"></i>
                        <span class="sidebar-menu-label">My Returns</span>
                    </a>
                    <a href="fines.php" class="sidebar-menu-item <?php echo $currentPage == 'fines.php' ? 'active' : ''; ?>">
                        <i class="fas fa-money-bill-wave"></i>
                        <span class="sidebar-menu-label">My Fines</span>
                    </a>
                    <a href="notifications.php" class="sidebar-menu-item <?php echo $currentPage == 'notifications.php' ? 'active' : ''; ?>">
                        <i class="fas fa-bell"></i>
                        <span class="sidebar-menu-label">Notifications</span>
                    </a>
                    <a href="feedback.php" class="sidebar-menu-item <?php echo $currentPage == 'feedback.php' ? 'active' : ''; ?>">
                        <i class="fas fa-comments"></i>
                        <span class="sidebar-menu-label">Feedback</span>
                    </a>
                    <a href="profile.php" class="sidebar-menu-item <?php echo $currentPage == 'profile.php' ? 'active' : ''; ?>">
                        <i class="fas fa-user-circle"></i>
                        <span class="sidebar-menu-label">Profile</span>
                    </a>
                    <a href="../logout.php" class="sidebar-menu-item">
                        <i class="fas fa-sign-out-alt"></i>
                        <span class="sidebar-menu-label">Logout</span>
                    </a>
                <?php else: ?>
                    <!-- Librarian Sidebar -->
                    <a href="dashboard.php" class="sidebar-menu-item <?php echo $currentPage == 'dashboard.php' ? 'active' : ''; ?>">
                        <i class="fas fa-tachometer-alt"></i>
                        <span class="sidebar-menu-label">Dashboard</span>
                    </a>
                    <a href="books.php" class="sidebar-menu-item <?php echo $currentPage == 'books.php' ? 'active' : ''; ?>">
                        <i class="fas fa-book"></i>
                        <span class="sidebar-menu-label">Books</span>
                    </a>
                    <a href="e-books.php" class="sidebar-menu-item <?php echo $currentPage == 'e-books.php' ? 'active' : ''; ?>">
                        <i class="fas fa-file-pdf"></i>
                        <span class="sidebar-menu-label">E-Books</span>
                    </a>
                    <a href="users.php" class="sidebar-menu-item <?php echo $currentPage == 'users.php' ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i>
                        <span class="sidebar-menu-label">Users</span>
                    </a>
                    <a href="issue_book.php" class="sidebar-menu-item <?php echo $currentPage == 'issue_book.php' ? 'active' : ''; ?>">
                        <i class="fas fa-book-open"></i>
                        <span class="sidebar-menu-label">Book Issue</span>
                    </a>
                    <a href="requests.php" class="sidebar-menu-item <?php echo $currentPage == 'requests.php' ? 'active' : ''; ?>">
                        <i class="fas fa-bookmark"></i>
                        <span class="sidebar-menu-label">Book Requests</span>
                    </a>
                    <a href="returns.php" class="sidebar-menu-item <?php echo $currentPage == 'returns.php' ? 'active' : ''; ?>">
                        <i class="fas fa-undo"></i>
                        <span class="sidebar-menu-label">Book Returns</span>
                    </a>
                    <a href="fines.php" class="sidebar-menu-item <?php echo $currentPage == 'fines.php' ? 'active' : ''; ?>">
                        <i class="fas fa-money-bill-wave"></i>
                        <span class="sidebar-menu-label">Fines</span>
                    </a>
                    <a href="notifications.php" class="sidebar-menu-item <?php echo $currentPage == 'notifications.php' ? 'active' : ''; ?>">
                        <i class="fas fa-bell"></i>
                        <span class="sidebar-menu-label">Notifications</span>
                    </a>
                    
                    <a href="weed_off_books.php" class="sidebar-menu-item <?php echo $currentPage == 'weed_off_books.php' ? 'active' : ''; ?>">
                        <i class="fas fa-trash-alt"></i>
                        <span class="sidebar-menu-label">Weed Off Books</span>
                    </a>
                    <a href="feedback.php" class="sidebar-menu-item <?php echo $currentPage == 'feedback.php' ? 'active' : ''; ?>">
                        <i class="fas fa-comments"></i>
                        <span class="sidebar-menu-label">Feedback</span>
                    </a>
                    <a href="profile.php" class="sidebar-menu-item <?php echo $currentPage == 'profile.php' ? 'active' : ''; ?>">
                        <i class="fas fa-user-circle"></i>
                        <span class="sidebar-menu-label">Profile</span>
                    </a>
                    <a href="../logout.php" class="sidebar-menu-item">
                        <i class="fas fa-sign-out-alt"></i>
                        <span class="sidebar-menu-label">Logout</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="content-wrapper">
            <!-- Header -->
            <div class="header">
                <button class="toggle-sidebar">
                    <i class="fas fa-bars"></i>
                </button>
                
                <div class="header-right">
                    <div class="notification-dropdown">
                        <div class="notification-bell">
                            <i class="fas fa-bell"></i>
                            <?php if ($notificationCount > 0): ?>
                                <span class="notification-count"><?php echo $notificationCount; ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="notification-menu">
                            <div class="notification-header">
                                <h3>Notifications</h3>
                                <a href="notifications.php">View All</a>
                            </div>
                            
                            <div class="notification-list">
                                <?php
                                $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
                                $stmt->bind_param("i", $userId);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                
                                if ($result->num_rows > 0) {
                                    while ($notification = $result->fetch_assoc()) {
                                        $unreadClass = $notification['is_read'] ? '' : 'unread';
                                        echo '<div class="notification-item ' . $unreadClass . '" data-id="' . $notification['id'] . '">';
                                        echo '<div class="notification-message">' . $notification['message'] . '</div>';
                                        echo '<div class="notification-time">' . date('M d, Y H:i', strtotime($notification['created_at'])) . '</div>';
                                        echo '</div>';
                                    }
                                } else {
                                    echo '<div class="notification-item">No notifications</div>';
                                }
                                ?>
                            </div>
                            
                            <div class="notification-footer">
                                <a href="notifications.php" class="btn btn-sm btn-primary">See All Notifications</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="user-dropdown">
                        <div class="user-info">
                            <span><?php echo $_SESSION['name']; ?></span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        
                        <div class="user-dropdown-content">
                            <a href="profile.php"><i class="fas fa-user-circle"></i> Profile</a>
                            <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Main Content Area -->
            <div class="content">