<?php
session_start();
include 'includes/config.php';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    // Redirect based on user role
    if ($_SESSION['role'] == 'librarian') {
        header('Location: librarian/dashboard.php');
    } else {
        header('Location: student/dashboard.php');
    }
    exit();
}

// Process login form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $login_identifier = trim($_POST['login_identifier']); // Can be email or unique_id
    $password = $_POST['password'];
    $ipAddress = getUserIpAddress();
    
    // Validate input
    if (empty($login_identifier) || empty($password)) {
        $error = "Please enter both login identifier and password";
    } else {
        // Check login attempts and security
        $securityCheck = checkLoginAttempts($conn, $login_identifier, $ipAddress);
        
        if ($securityCheck['blocked']) {
            $error = $securityCheck['message'];
        } else {
            // Prepare SQL statement to check both email and unique_id
            $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? OR unique_id = ?");
            $stmt->bind_param("ss", $login_identifier, $login_identifier);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 1) {
                $user = $result->fetch_assoc();
                if (password_verify($password, $user['password'])) {
                    // Record successful login attempt
                    recordLoginAttempt($conn, $login_identifier, $ipAddress, true);
                    
                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['name'] = $user['name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['unique_id'] = $user['unique_id'];
                    
                    // Redirect based on role
                    if ($user['role'] == 'librarian') {
                        header('Location: librarian/dashboard.php');
                    } else {
                        header('Location: student/dashboard.php');
                    }
                    exit();
                } else {
                    // Record failed login attempt
                    recordLoginAttempt($conn, $login_identifier, $ipAddress, false);
                    $error = "Invalid password";
                }
            } else {
                // Record failed login attempt
                recordLoginAttempt($conn, $login_identifier, $ipAddress, false);
                $error = "User not found";
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
    <title>Library Management System - Login</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="icon" type="image/svg+xml" href="../uploads/assests/book.svg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .login-page {
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

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 450px;
            overflow: hidden;
            animation: slideUp 0.5s ease-out;
            backdrop-filter: blur(10px);
        }

        .login-header {
            background: #0d47a1;
            color: white;
            padding: 40px 20px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .login-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.2) 0%, transparent 60%);
            transform: rotate(45deg);
        }

        .login-header h1 {
            margin: 0;
            font-size: 2.2em;
            font-weight: 600;
            position: relative;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }

        .login-header p {
            margin: 15px 0 0;
            opacity: 0.9;
            font-size: 1.1em;
            position: relative;
        }

        .login-form {
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

        .form-group input {
            width: 100%;
            padding: 15px;
            border: 2px solid #e1e1e1;
            border-radius: 12px;
            font-size: 1em;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.9);
        }

        .form-group input:focus {
            border-color: #0d47a1;
            box-shadow: 0 0 0 4px rgba(13, 71, 161, 0.1);
            outline: none;
        }

        .form-group i {
            position: absolute;
            right: 15px;
            top: 45px;
            color: #666;
        }

        .login-help {
            background: #e3f2fd;
            border: 1px solid #2196f3;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 0.9em;
        }

        .login-help h4 {
            margin: 0 0 10px 0;
            color: #1976d2;
            font-size: 1em;
        }

        .login-help ul {
            margin: 0;
            padding-left: 20px;
            color: #0d47a1;
        }

        .login-help li {
            margin-bottom: 5px;
        }

        .password-requirements {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            margin-top: 10px;
            font-size: 0.9em;
            display: none;
        }

        .password-requirements h4 {
            margin: 0 0 10px 0;
            color: #495057;
            font-size: 1em;
        }

        .requirement {
            display: flex;
            align-items: center;
            margin-bottom: 5px;
            color: #6c757d;
        }

        .requirement i {
            margin-right: 8px;
            width: 16px;
            position: static;
        }

        .requirement.valid {
            color: #28a745;
        }

        .requirement.invalid {
            color: #dc3545;
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
        }

        .btn-primary:hover {
            background: #1565c0;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(13, 71, 161, 0.3);
        }

        .login-footer {
            text-align: center;
            padding: 20px;
            color: #666;
            border-top: 1px solid #eee;
            background: rgba(245, 245, 245, 0.9);
        }

        .login-links {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 20px;
        }

        .login-link {
            display: block;
            color: #0d47a1;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            padding: 8px;
            border-radius: 6px;
        }

        .login-link:hover {
            color: #1565c0;
            background: rgba(13, 71, 161, 0.05);
            transform: translateX(5px);
        }

        .login-link.primary {
            background: rgba(13, 71, 161, 0.1);
            border: 1px solid rgba(13, 71, 161, 0.2);
        }

        .alert {
            padding: 15px 20px;
            margin-bottom: 25px;
            border-radius: 12px;
            animation: fadeIn 0.3s ease-out;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-danger {
            background-color: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 45px;
            cursor: pointer;
            color: #666;
            z-index: 10;
        }

        .password-toggle:hover {
            color: #0d47a1;
        }

        .security-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .countdown-timer {
            font-weight: bold;
            color: #dc3545;
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

        @media (max-width: 480px) {
            .login-container {
                margin: 10px;
            }
            
            .login-header {
                padding: 30px 20px;
            }
            
            .login-form {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-header">
            <h1><i class="fas fa-book-reader"></i> Library Management System</h1>
            <p>Access your library dashboard</p>
        </div>
        
        <div class="login-form">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="loginForm">
                <div class="form-group">
                    <label for="login_identifier"><i class="fas fa-user"></i> Unique ID</label>
                    <input type="text" id="login_identifier" name="login_identifier" placeholder="Unique ID or Email" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Password" required>
                    <i class="fas fa-eye password-toggle" id="toggleIcon" onclick="togglePassword()"></i>
                    
                    <div class="password-requirements" id="passwordRequirements">
                        <h4>Password Requirements:</h4>
                        <div class="requirement" id="length-req">
                            <i class="fas fa-times"></i>
                            <span>At least 8 characters long</span>
                        </div>
                        <div class="requirement" id="uppercase-req">
                            <i class="fas fa-times"></i>
                            <span>At least one uppercase letter (A-Z)</span>
                        </div>
                        <div class="requirement" id="special-req">
                            <i class="fas fa-times"></i>
                            <span>At least one special character (@, #, $)</span>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary" id="loginButton">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>

                <div class="login-links">
                    <a href="recover_account.php" class="login-link primary">
                        <i class="fas fa-search"></i> Forgot your ID or Email? Find your account
                    </a>
                    
                    <a href="forgot_password.php" class="login-link">
                        <i class="fas fa-key"></i> Forgot your password?
                    </a>

                    <a href="register.php" class="login-link">
                        <i class="fas fa-user-plus"></i> Don't have an account? Register here
                    </a>
                </div>
            </form>
            
            <div class="login-footer">
                <p>&copy; 2025 Library Management System</p>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.className = 'fas fa-eye-slash';
            } else {
                passwordInput.type = 'password';
                toggleIcon.className = 'fas fa-eye';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('password');
            const passwordRequirements = document.getElementById('passwordRequirements');
            const lengthReq = document.getElementById('length-req');
            const uppercaseReq = document.getElementById('uppercase-req');
            const specialReq = document.getElementById('special-req');

            passwordInput.addEventListener('focus', function() {
                passwordRequirements.style.display = 'block';
            });

            passwordInput.addEventListener('blur', function() {
                setTimeout(() => {
                    passwordRequirements.style.display = 'none';
                }, 200);
            });

            passwordInput.addEventListener('input', function() {
                const password = this.value;
                
                // Check length
                if (password.length >= 8) {
                    lengthReq.classList.add('valid');
                    lengthReq.classList.remove('invalid');
                    lengthReq.querySelector('i').className = 'fas fa-check';
                } else {
                    lengthReq.classList.add('invalid');
                    lengthReq.classList.remove('valid');
                    lengthReq.querySelector('i').className = 'fas fa-times';
                }

                // Check uppercase
                if (/[A-Z]/.test(password)) {
                    uppercaseReq.classList.add('valid');
                    uppercaseReq.classList.remove('invalid');
                    uppercaseReq.querySelector('i').className = 'fas fa-check';
                } else {
                    uppercaseReq.classList.add('invalid');
                    uppercaseReq.classList.remove('valid');
                    uppercaseReq.querySelector('i').className = 'fas fa-times';
                }

                // Check special characters
                if (/[@#$]/.test(password)) {
                    specialReq.classList.add('valid');
                    specialReq.classList.remove('invalid');
                    specialReq.querySelector('i').className = 'fas fa-check';
                } else {
                    specialReq.classList.add('invalid');
                    specialReq.classList.remove('valid');
                    specialReq.querySelector('i').className = 'fas fa-times';
                }
            });

            // Check for security warning in error message and start countdown
            const errorAlert = document.querySelector('.alert-danger');
            if (errorAlert && errorAlert.textContent.includes('Please wait')) {
                const match = errorAlert.textContent.match(/(\d+) seconds/);
                if (match) {
                    let remainingTime = parseInt(match[1]);
                    const loginButton = document.getElementById('loginButton');
                    
                    // Disable login button
                    loginButton.disabled = true;
                    loginButton.style.background = '#ccc';
                    loginButton.style.cursor = 'not-allowed';
                    
                    // Start countdown
                    const countdownInterval = setInterval(() => {
                        remainingTime--;
                        
                        if (remainingTime > 0) {
                            errorAlert.innerHTML = `<i class="fas fa-exclamation-circle"></i> Too many failed login attempts. Please wait <span class="countdown-timer">${remainingTime}</span> seconds before trying again.`;
                        } else {
                            clearInterval(countdownInterval);
                            errorAlert.style.display = 'none';
                            loginButton.disabled = false;
                            loginButton.style.background = '#0d47a1';
                            loginButton.style.cursor = 'pointer';
                        }
                    }, 1000);
                }
            }
        });
    </script>
</body>
</html>