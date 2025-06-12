<?php
session_start();
include 'includes/config.php';

$message = '';
$messageType = '';
$step = 1; // Step 1: Enter recovery info, Step 2: Show results

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['step']) && $_POST['step'] == '1') {
        $name = trim($_POST['name']);
        $phone = trim($_POST['phone']);
        $department = trim($_POST['department']);
        
        // Validate input
        if (empty($name)) {
            $message = "Please enter your full name";
            $messageType = "danger";
        } else {
            // Search for user with provided information
            $searchConditions = ["name LIKE ?"];
            $searchParams = ["%$name%"];
            $paramTypes = "s";
            
            // Add phone condition if provided
            if (!empty($phone)) {
                $searchConditions[] = "phone = ?";
                $searchParams[] = $phone;
                $paramTypes .= "s";
            }
            
            // Add department condition if provided
            if (!empty($department)) {
                $searchConditions[] = "department LIKE ?";
                $searchParams[] = "%$department%";
                $paramTypes .= "s";
            }
            
            $sql = "SELECT unique_id, email, name, department, phone, role FROM users WHERE " . implode(" AND ", $searchConditions);
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($paramTypes, ...$searchParams);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $users = [];
                while ($row = $result->fetch_assoc()) {
                    $users[] = $row;
                }
                $step = 2;
            } else {
                $message = "No account found with the provided information. Please check your details and try again.";
                $messageType = "danger";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Recovery - Library Management System</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="icon" type="image/svg+xml" href="../uploads/assests/book.svg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .recovery-page {
            min-height: 100vh;
            background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)),
                        url('../uploads/assests/login.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .recovery-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 700px;
            overflow: hidden;
            animation: slideUp 0.5s ease-out;
            backdrop-filter: blur(10px);
        }

        .recovery-header {
            background: #0d47a1;
            color: white;
            padding: 40px 20px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .recovery-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.2) 0%, transparent 60%);
            transform: rotate(45deg);
        }

        .recovery-header h1 {
            margin: 0;
            font-size: 2.2em;
            font-weight: 600;
            position: relative;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }

        .recovery-header p {
            margin: 15px 0 0;
            opacity: 0.9;
            font-size: 1.1em;
            position: relative;
        }

        .recovery-form {
            padding: 40px 30px;
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 10px;
            color: #333;
            font-weight: 500;
            font-size: 0.95em;
        }

        .form-group input, .form-group select {
            width: 100%;
            padding: 15px;
            border: 2px solid #e1e1e1;
            border-radius: 12px;
            font-size: 1em;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.9);
            box-sizing: border-box;
        }

        .form-group input:focus, .form-group select:focus {
            border-color: #0d47a1;
            box-shadow: 0 0 0 4px rgba(13, 71, 161, 0.1);
            outline: none;
        }

        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 25px;
        }

        .form-col {
            flex: 1;
        }

        .recovery-help {
            background: #e3f2fd;
            border: 1px solid #2196f3;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            font-size: 0.9em;
        }

        .recovery-help h4 {
            margin: 0 0 15px 0;
            color: #1976d2;
            font-size: 1.1em;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .recovery-help ul {
            margin: 0;
            padding-left: 20px;
            color: #0d47a1;
        }

        .recovery-help li {
            margin-bottom: 8px;
            line-height: 1.5;
        }

        .btn-primary {
            background: #0d47a1;
            color: white;
            padding: 15px 25px;
            border: none;
            border-radius: 12px;
            width: 100%;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(13, 71, 161, 0.2);
            margin-bottom: 20px;
        }

        .btn-primary:hover {
            background: #1565c0;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(13, 71, 161, 0.3);
        }

        .btn-link {
            color: #0d47a1;
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease;
            padding: 8px 12px;
            border-radius: 6px;
        }

        .btn-link:hover {
            color: #1565c0;
            background: rgba(13, 71, 161, 0.05);
            transform: translateX(-3px);
        }

        .alert {
            padding: 18px 20px;
            margin-bottom: 25px;
            border-radius: 12px;
            animation: fadeIn 0.3s ease-out;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
        }

        .alert-success {
            background-color: #d1fae5;
            color: #047857;
            border: 1px solid #a7f3d0;
        }

        .alert-danger {
            background-color: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .alert-info {
            background-color: #dbeafe;
            color: #1e40af;
            border: 1px solid #93c5fd;
        }

        .text-center {
            text-align: center;
        }

        /* Enhanced Results Section */
        .results-container {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            border: 1px solid #dee2e6;
        }

        .results-header {
            text-align: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #0d47a1;
        }

        .results-header h2 {
            color: #0d47a1;
            margin: 0;
            font-size: 1.5em;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .user-result {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border-left: 5px solid #0d47a1;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .user-result::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: linear-gradient(45deg, rgba(13, 71, 161, 0.05) 0%, transparent 50%);
            border-radius: 0 0 0 100px;
        }

        .user-result:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .user-result:last-child {
            margin-bottom: 0;
        }

        .user-result-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e9ecef;
        }

        .user-result-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0;
            color: #0d47a1;
            font-size: 1.3em;
            font-weight: 600;
        }

        .user-badge {
            background: #0d47a1;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .user-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }

        .info-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .info-card:hover {
            background: #e9ecef;
            transform: translateY(-2px);
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .info-label {
            font-weight: 600;
            color: #495057;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .info-value-container {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-value {
            color: #212529;
            background: white;
            padding: 10px 15px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-weight: 600;
            font-size: 1em;
            border: 2px solid #dee2e6;
            flex: 1;
            word-break: break-all;
        }

        .copy-btn {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.85em;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
        }

        .copy-btn:hover {
            background: linear-gradient(135deg, #218838 0%, #1ea085 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
        }

        .copy-btn.copied {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
        }

        .step-indicator {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 30px;
            padding: 20px;
            background: rgba(13, 71, 161, 0.05);
            border-radius: 15px;
        }

        .step {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e1e1e1;
            color: #666;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.1em;
            transition: all 0.3s ease;
        }

        .step-number.active {
            background: #0d47a1;
            color: white;
            box-shadow: 0 4px 15px rgba(13, 71, 161, 0.3);
        }

        .step-number.completed {
            background: #28a745;
            color: white;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }

        .step-text {
            font-weight: 600;
            color: #495057;
        }

        .step-text.active {
            color: #0d47a1;
        }

        .step-text.completed {
            color: #28a745;
        }

        .step-line {
            width: 60px;
            height: 3px;
            background: #e1e1e1;
            margin: 0 15px;
            border-radius: 2px;
            transition: all 0.3s ease;
        }

        .step-line.completed {
            background: #28a745;
        }

        .login-prompt {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            border: 2px solid #2196f3;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            margin: 25px 0;
        }

        .login-prompt h3 {
            color: #1976d2;
            margin: 0 0 10px 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .login-prompt p {
            color: #0d47a1;
            margin: 0;
            font-weight: 500;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
            100% {
                transform: scale(1);
            }
        }

        .copy-btn.copied {
            animation: pulse 0.3s ease-in-out;
        }

        @media (max-width: 768px) {
            .recovery-container {
                margin: 10px;
                max-width: 95%;
            }
            
            .recovery-header {
                padding: 30px 20px;
            }
            
            .recovery-form {
                padding: 30px 20px;
            }

            .form-row {
                flex-direction: column;
                gap: 0;
            }

            .user-info-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .info-value-container {
                flex-direction: column;
                align-items: stretch;
                gap: 8px;
            }

            .copy-btn {
                justify-content: center;
            }

            .step-indicator {
                padding: 15px;
            }

            .step-line {
                width: 40px;
                margin: 0 10px;
            }

            .user-result-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }

        @media (max-width: 480px) {
            .recovery-header h1 {
                font-size: 1.8em;
            }

            .user-result {
                padding: 20px 15px;
            }

            .info-value {
                font-size: 0.9em;
                padding: 8px 12px;
            }
        }
    </style>
</head>
<body class="recovery-page">
    <div class="recovery-container">
        <div class="recovery-header">
            <h1><i class="fas fa-search"></i> Account Recovery</h1>
            <p>Find your forgotten ID or Email</p>
        </div>
        
        <div class="recovery-form">
            <!-- Step Indicator -->
            <div class="step-indicator">
                <div class="step">
                    <div class="step-number <?php echo $step >= 1 ? ($step > 1 ? 'completed' : 'active') : ''; ?>">
                        <?php echo $step > 1 ? '<i class="fas fa-check"></i>' : '1'; ?>
                    </div>
                    <span class="step-text <?php echo $step >= 1 ? ($step > 1 ? 'completed' : 'active') : ''; ?>">Enter Details</span>
                </div>
                <div class="step-line <?php echo $step > 1 ? 'completed' : ''; ?>"></div>
                <div class="step">
                    <div class="step-number <?php echo $step >= 2 ? 'active' : ''; ?>">
                        <?php echo $step >= 2 ? '<i class="fas fa-eye"></i>' : '2'; ?>
                    </div>
                    <span class="step-text <?php echo $step >= 2 ? 'active' : ''; ?>">View Results</span>
                </div>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <i class="fas fa-<?php echo $messageType == 'success' ? 'check-circle' : ($messageType == 'info' ? 'info-circle' : 'exclamation-circle'); ?>"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($step == 1): ?>
                <div class="recovery-help">
                    <h4><i class="fas fa-info-circle"></i> How to recover your account</h4>
                    <ul>
                        <li><strong>Enter your full name</strong> as registered in the system</li>
                        <li><strong>Add phone number or department</strong> for more accurate results</li>
                        <li><strong>More details help us</strong> find your account quickly</li>
                        <li><strong>Search is case-insensitive</strong> for easier matching</li>
                    </ul>
                </div>

                <form method="POST" action="">
                    <input type="hidden" name="step" value="1">
                    
                    <div class="form-group">
                        <label for="name"><i class="fas fa-user"></i> Full Name *</label>
                        <input type="text" id="name" name="name" placeholder="Enter your full name as registered" required value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="phone"><i class="fas fa-phone"></i> Phone Number</label>
                                <input type="tel" id="phone" name="phone" placeholder="Your registered phone number" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="department"><i class="fas fa-building"></i> Department</label>
                                <input type="text" id="department" name="department" placeholder="Your department" value="<?php echo isset($_POST['department']) ? htmlspecialchars($_POST['department']) : ''; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Search Account
                    </button>
                </form>
            <?php endif; ?>

            <?php if ($step == 2 && isset($users)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    Great! Found <?php echo count($users); ?> account(s) matching your information.
                </div>

                <div class="results-container">
                    <div class="results-header">
                        <h2><i class="fas fa-user-check"></i> Your Account Details</h2>
                    </div>

                    <?php foreach ($users as $index => $user): ?>
                        <div class="user-result">
                            <div class="user-result-header">
                                <h3 class="user-result-title">
                                    <i class="fas fa-user-circle"></i>
                                    <?php echo htmlspecialchars($user['name']); ?>
                                </h3>
                                <span class="user-badge"><?php echo ucfirst(htmlspecialchars($user['role'])); ?></span>
                            </div>
                            
                            <div class="user-info-grid">
                                <div class="info-card">
                                    <div class="info-item">
                                        <span class="info-label">
                                            <i class="fas fa-id-card"></i> Unique ID
                                        </span>
                                        <div class="info-value-container">
                                            <span class="info-value" id="uid-<?php echo $index; ?>"><?php echo htmlspecialchars($user['unique_id']); ?></span>
                                            <button class="copy-btn" onclick="copyToClipboard('uid-<?php echo $index; ?>', this)">
                                                <i class="fas fa-copy"></i> Copy
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="info-card">
                                    <div class="info-item">
                                        <span class="info-label">
                                            <i class="fas fa-envelope"></i> Email Address
                                        </span>
                                        <div class="info-value-container">
                                            <span class="info-value" id="email-<?php echo $index; ?>"><?php echo htmlspecialchars($user['email']); ?></span>
                                            <button class="copy-btn" onclick="copyToClipboard('email-<?php echo $index; ?>', this)">
                                                <i class="fas fa-copy"></i> Copy
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <?php if (!empty($user['department'])): ?>
                                <div class="info-card">
                                    <div class="info-item">
                                        <span class="info-label">
                                            <i class="fas fa-building"></i> Department
                                        </span>
                                        <div class="info-value-container">
                                            <span class="info-value"><?php echo htmlspecialchars($user['department']); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <?php if (!empty($user['phone'])): ?>
                                <div class="info-card">
                                    <div class="info-item">
                                        <span class="info-label">
                                            <i class="fas fa-phone"></i> Phone Number
                                        </span>
                                        <div class="info-value-container">
                                            <span class="info-value"><?php echo htmlspecialchars($user['phone']); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="login-prompt">
                    <h3><i class="fas fa-lightbulb"></i> Ready to Login?</h3>
                    <p>You can now use either your <strong>Unique ID</strong> or <strong>Email</strong> to access your account.</p>
                </div>

                <a href="index.php" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i> Go to Login Page
                </a>
            <?php endif; ?>
            
            <div class="text-center">
                <a href="index.php" class="btn btn-link">
                    <i class="fas fa-arrow-left"></i> Back to Login
                </a>
                <?php if ($step == 1): ?>
                    <br>
                    <a href="forgot_password.php" class="btn btn-link">
                        <i class="fas fa-key"></i> Forgot Password?
                    </a>
                    <br>
                    <a href="register.php" class="btn btn-link">
                        <i class="fas fa-user-plus"></i> Create New Account
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function copyToClipboard(elementId, buttonElement) {
            const element = document.getElementById(elementId);
            const text = element.textContent;
            
            navigator.clipboard.writeText(text).then(function() {
                // Update button to show success
                const originalHTML = buttonElement.innerHTML;
                buttonElement.innerHTML = '<i class="fas fa-check"></i> Copied!';
                buttonElement.classList.add('copied');
                
                // Reset button after 2 seconds
                setTimeout(function() {
                    buttonElement.innerHTML = originalHTML;
                    buttonElement.classList.remove('copied');
                }, 2000);
            }).catch(function(err) {
                console.error('Could not copy text: ', err);
                alert('Failed to copy. Please select and copy manually.');
            });
        }

        // Auto-focus on name field when page loads
        document.addEventListener('DOMContentLoaded', function() {
            const nameField = document.getElementById('name');
            if (nameField) {
                nameField.focus();
            }
        });
    </script>
</body>
</html>