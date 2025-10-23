<?php
define('APP_LOADED', true);
session_start();

// Enable error reporting for debugging 
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'backend/db1.php';

// Handle Sign In
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'signin') {
    header('Content-Type: application/json');
    
    $usernameOrEmail = trim($_POST['username_email']);
    $password = trim($_POST['password']);
    
    $errors = [];
    
    if (empty($usernameOrEmail)) {
        $errors[] = "Username or email is required";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    }
    
    if (empty($errors)) {
        // Check if user exists
        $stmt = $conn->prepare("SELECT user_id, name, email, username, password_hash, role, status FROM users WHERE (email = ? OR username = ?) LIMIT 1");
        $stmt->bind_param("ss", $usernameOrEmail, $usernameOrEmail);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // Check if account is active
            if ($user['status'] !== 'active') {
                $errors[] = "Your account is " . $user['status'] . ". Please contact support.";
            } else {
                // Verify password
                if (password_verify($password, $user['password_hash'])) {
                    // Password is correct - create session
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['name'] = $user['name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    
                    // Determine redirect based on role
                    $redirect = '';
                    
                    if ($user['role'] === 'admin') {
                        $redirect = 'admin/modules/drivers.php';
                    } elseif ($user['role'] === 'driver') {
                        // Get driver ID for drivers
                        $driver_stmt = $conn->prepare("SELECT drivers_id FROM drivers WHERE user_id = ?");
                        $driver_stmt->bind_param("s", $user['user_id']);
                        $driver_stmt->execute();
                        $driver_result = $driver_stmt->get_result();
                        if ($driver_result->num_rows > 0) {
                            $driver = $driver_result->fetch_assoc();
                            $_SESSION['driver_id'] = $driver['drivers_id'];
                        }
                        $driver_stmt->close();
                        $redirect = 'user/dashboard.php';
                    } else {
                        // Default user role
                        $redirect = 'user/dashboard.php';
                    }
                    
                    echo json_encode(['success' => true, 'message' => 'Sign in successful!', 'redirect' => $redirect]);
                    exit;
                } else {
                    $errors[] = "Incorrect password. Please try again.";
                }
            }
        } else {
            $errors[] = "No account found with this username or email.";
        }
        $stmt->close();
    }
    
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

// Handle Sign Up
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'signup') {
    header('Content-Type: application/json');
    
    try {
        $fullName = trim($_POST['full_name']);
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $contactNumber = trim($_POST['contact_number']);
        $licenseNumber = trim($_POST['license_number']);
        $licenseExpiry = trim($_POST['license_expiry']);
        $address = trim($_POST['address']);
        $password = trim($_POST['password']);
        $confirmPassword = trim($_POST['confirm_password']);
        
        $errors = [];
        
        // Validation
        if (empty($fullName)) $errors[] = "Full name is required";
        if (empty($username)) $errors[] = "Username is required";
        if (empty($email)) $errors[] = "Email is required";
        if (empty($contactNumber)) $errors[] = "Contact number is required";
        if (empty($licenseNumber)) $errors[] = "Driver license is required";
        if (empty($licenseExpiry)) $errors[] = "License expiry date is required";
        if (empty($address)) $errors[] = "Complete address is required";
        if (empty($password)) $errors[] = "Password is required";
        if (empty($confirmPassword)) $errors[] = "Confirm password is required";
        
        // Email validation
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        }
        
        // Username validation (alphanumeric and underscore only)
        if (!empty($username) && !preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
            $errors[] = "Username must be 3-20 characters and contain only letters, numbers, and underscores";
        }
        
        // Password validation
        if (!empty($password)) {
            if (strlen($password) < 6) {
                $errors[] = "Password must be at least 6 characters long";
            }
            if ($password !== $confirmPassword) {
                $errors[] = "Passwords do not match";
            }
        }
        
        // License expiry validation
        if (!empty($licenseExpiry)) {
            $expiryDate = new DateTime($licenseExpiry);
            $today = new DateTime();
            if ($expiryDate < $today) {
                $errors[] = "Driver license has already expired";
            }
        }
        
        if (empty($errors)) {
            // Check if email already exists
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
            if (!$stmt) {
                throw new Exception("Database error: " . $conn->error);
            }
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $errors[] = "This email is already registered. Please use a different email or try signing in.";
            }
            $stmt->close();
            
            // Check if username already exists
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
            if (!$stmt) {
                throw new Exception("Database error: " . $conn->error);
            }
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $errors[] = "This username is already taken. Please choose a different username.";
            }
            $stmt->close();
            
            // Check if license number already exists
            $stmt = $conn->prepare("SELECT drivers_id FROM drivers WHERE license_number = ?");
            if (!$stmt) {
                throw new Exception("Database error: " . $conn->error);
            }
            $stmt->bind_param("s", $licenseNumber);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $errors[] = "This driver license number is already registered.";
            }
            $stmt->close();
        }
        
        if (empty($errors)) {
            // Generate UUIDs
            function generateUUID() {
                return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                    mt_rand(0, 0xffff),
                    mt_rand(0, 0x0fff) | 0x4000,
                    mt_rand(0, 0x3fff) | 0x8000,
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
                );
            }
            
            $userId = generateUUID();
            $driverId = generateUUID();
            $walletId = generateUUID();
            
            // Hash password
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // Insert user
                $stmt = $conn->prepare("INSERT INTO users (user_id, name, email, username, password_hash, role, status) VALUES (?, ?, ?, ?, ?, 'driver', 'active')");
                if (!$stmt) {
                    throw new Exception("Failed to prepare user insert: " . $conn->error);
                }
                $stmt->bind_param("sssss", $userId, $fullName, $email, $username, $passwordHash);
                if (!$stmt->execute()) {
                    throw new Exception("Failed to insert user: " . $stmt->error);
                }
                $stmt->close();
                
                // Insert driver
                $stmt = $conn->prepare("INSERT INTO drivers (drivers_id, user_id, license_number, license_expiry, contact_number, address, rating) VALUES (?, ?, ?, ?, ?, ?, 0.00)");
                if (!$stmt) {
                    throw new Exception("Failed to prepare driver insert: " . $conn->error);
                }
                $stmt->bind_param("ssssss", $driverId, $userId, $licenseNumber, $licenseExpiry, $contactNumber, $address);
                if (!$stmt->execute()) {
                    throw new Exception("Failed to insert driver: " . $stmt->error);
                }
                $stmt->close();
                
                // Create driver wallet
                $stmt = $conn->prepare("INSERT INTO driver_wallets (wallet_id, driver_id, current_balance) VALUES (?, ?, 0.00)");
                if (!$stmt) {
                    throw new Exception("Failed to prepare wallet insert: " . $conn->error);
                }
                $stmt->bind_param("ss", $walletId, $driverId);
                if (!$stmt->execute()) {
                    throw new Exception("Failed to insert wallet: " . $stmt->error);
                }
                $stmt->close();
                
                // Commit transaction
                $conn->commit();
                
                echo json_encode(['success' => true, 'message' => 'Account created successfully! You can now sign in.']);
                exit;
                
            } catch (Exception $e) {
                // Rollback on error
                $conn->rollback();
                $errors[] = "Registration failed: " . $e->getMessage();
                echo json_encode(['success' => false, 'errors' => $errors]);
                exit;
            }
        } else {
            echo json_encode(['success' => false, 'errors' => $errors]);
            exit;
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'errors' => ['System error: ' . $e->getMessage()]]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver App - Sign In/Up</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>
    <div class="container" id="container">
        <div class="welcome-section" id="welcomeSection">
            <h1 id="welcomeTitle"><i class="fas fa-hand-wave"></i> Hello, Friend!</h1>
            <p id="welcomeText">Enter your personal details and start your journey with us</p>
            <button onclick="toggleForm()" id="toggleBtn"><i class="fas fa-user-plus"></i> SIGN UP</button>
        </div>

        <div class="form-section">
            <div class="forms-container">
                <!-- Sign In Form (Default) -->
                <div id="signinForm" class="form-content active">
                    <h2><i class="fas fa-sign-in-alt"></i> Sign In</h2>
                    <div id="signinErrors"></div>
                    <form id="signinFormElement">
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Username or Email *</label>
                            <input type="text" name="username_email" placeholder="Enter username or email" required>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-lock"></i> Password *</label>
                            <div class="password-input-wrapper">
                                <input type="password" id="signinPassword" name="password" placeholder="Enter your password" required>
                                <button type="button" class="toggle-password" onclick="togglePasswordVisibility('signinPassword', this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div style="text-align: right; margin: 10px 0;">
                            <a href="#" style="color: #1a1a1a; text-decoration: none; font-size: 14px;">
                                <i class="fas fa-key"></i> Forgot Password?
                            </a>
                        </div>

                        <button type="submit" class="submit-btn">
                            <i class="fas fa-arrow-right"></i> SIGN IN
                        </button>
                    </form>
                </div>

                <!-- Sign Up Form -->
                <div id="signupForm" class="form-content hidden">
                    <h2><i class="fas fa-user-plus"></i> Create Account</h2>
                    <div id="signupErrors"></div>
                    <form id="signupFormElement">
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Full Name *</label>
                            <input type="text" name="full_name" placeholder="Enter your full name" required>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-at"></i> Username *</label>
                            <input type="text" name="username" placeholder="Enter your username" required>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-envelope"></i> Email Address *</label>
                            <input type="email" name="email" placeholder="Enter your email" required>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-phone"></i> Contact Number *</label>
                                <input type="tel" name="contact_number" placeholder="Your phone number" required>
                            </div>

                            <div class="form-group">
                                <label><i class="fas fa-id-card"></i> Driver License *</label>
                                <input type="text" name="license_number" placeholder="License number" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-calendar-alt"></i> License Expiry *</label>
                            <input type="date" name="license_expiry" required>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-map-marker-alt"></i> Complete Address *</label>
                            <input type="text" name="address" placeholder="Street, City, State, ZIP" required>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-lock"></i> Password *</label>
                                <div class="password-input-wrapper">
                                    <input type="password" id="signupPassword" name="password" placeholder="Create password" required>
                                    <button type="button" class="toggle-password" onclick="togglePasswordVisibility('signupPassword', this)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="form-group">
                                <label><i class="fas fa-lock"></i> Confirm Password *</label>
                                <div class="password-input-wrapper">
                                    <input type="password" id="confirmPassword" name="confirm_password" placeholder="Confirm password" required>
                                    <button type="button" class="toggle-password" onclick="togglePasswordVisibility('confirmPassword', this)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="checkbox-group">
                            <input type="checkbox" id="terms" required>
                            <label for="terms">I agree to the <a href="#"><i class="fas fa-file-contract"></i> Terms of Service</a> and <a href="#"><i class="fas fa-shield-alt"></i> Privacy Policy</a></label>
                        </div>

                        <button type="submit" class="submit-btn">
                            <i class="fas fa-user-check"></i> SIGN UP
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Toggle password visibility
        function togglePasswordVisibility(inputId, button) {
            const input = document.getElementById(inputId);
            const icon = button.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        function toggleForm() {
            const container = document.getElementById('container');
            const signupForm = document.getElementById('signupForm');
            const signinForm = document.getElementById('signinForm');
            const welcomeTitle = document.getElementById('welcomeTitle');
            const welcomeText = document.getElementById('welcomeText');
            const toggleBtn = document.getElementById('toggleBtn');

            // Clear error messages
            document.getElementById('signinErrors').innerHTML = '';
            document.getElementById('signupErrors').innerHTML = '';

            container.classList.toggle('sign-up-mode');

            if (signinForm.classList.contains('active')) {
                // Show signup
                signinForm.classList.remove('active');
                setTimeout(() => {
                    signinForm.classList.add('hidden');
                    signupForm.classList.remove('hidden');
                    setTimeout(() => {
                        signupForm.classList.add('active');
                    }, 50);
                }, 300);
                
                welcomeTitle.innerHTML = '<i class="fas fa-smile"></i> Welcome Back!';
                welcomeText.textContent = 'To keep connected with us please login with your personal info';
                toggleBtn.innerHTML = '<i class="fas fa-sign-in-alt"></i> SIGN IN';
            } else {
                // Show signin
                signupForm.classList.remove('active');
                setTimeout(() => {
                    signupForm.classList.add('hidden');
                    signinForm.classList.remove('hidden');
                    setTimeout(() => {
                        signinForm.classList.add('active');
                    }, 50);
                }, 300);
                
                welcomeTitle.innerHTML = '<i class="fas fa-hand-wave"></i> Hello, Friend!';
                welcomeText.textContent = 'Enter your personal details and start your journey with us';
                toggleBtn.innerHTML = '<i class="fas fa-user-plus"></i> SIGN UP';
            }
        }

        // Handle Sign In Form
        document.getElementById('signinFormElement').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'signin');
            
            const submitBtn = this.querySelector('.submit-btn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> SIGNING IN...';
            
            fetch('index.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text();
            })
            .then(text => {
                console.log('Response:', text); // Debug log
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        document.getElementById('signinErrors').innerHTML = 
                            '<div class="success-message"><i class="fas fa-check-circle"></i> ' + data.message + '</div>';
                        setTimeout(() => {
                            window.location.href = data.redirect;
                        }, 1000);
                    } else {
                        let errorHtml = '<div class="error-message"><i class="fas fa-exclamation-triangle"></i> <ul>';
                        data.errors.forEach(error => {
                            errorHtml += '<li>' + error + '</li>';
                        });
                        errorHtml += '</ul></div>';
                        document.getElementById('signinErrors').innerHTML = errorHtml;
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="fas fa-arrow-right"></i> SIGN IN';
                    }
                } catch (e) {
                    console.error('Parse error:', e, 'Response:', text);
                    document.getElementById('signinErrors').innerHTML = 
                        '<div class="error-message"><i class="fas fa-exclamation-circle"></i> Server error. Check console for details.</div>';
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-arrow-right"></i> SIGN IN';
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                document.getElementById('signinErrors').innerHTML = 
                    '<div class="error-message"><i class="fas fa-exclamation-circle"></i> An error occurred. Please try again.</div>';
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-arrow-right"></i> SIGN IN';
            });
        });

        // Handle Sign Up Form
        document.getElementById('signupFormElement').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'signup');
            
            const submitBtn = this.querySelector('.submit-btn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> CREATING ACCOUNT...';
            
            fetch('index.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text();
            })
            .then(text => {
                console.log('Response:', text); // Debug log
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        document.getElementById('signupErrors').innerHTML = 
                            '<div class="success-message"><i class="fas fa-check-circle"></i> ' + data.message + '</div>';
                        this.reset();
                        setTimeout(() => {
                            toggleForm(); // Switch to sign in form
                        }, 2000);
                    } else {
                        let errorHtml = '<div class="error-message"><i class="fas fa-exclamation-triangle"></i> <ul>';
                        data.errors.forEach(error => {
                            errorHtml += '<li>' + error + '</li>';
                        });
                        errorHtml += '</ul></div>';
                        document.getElementById('signupErrors').innerHTML = errorHtml;
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="fas fa-user-check"></i> SIGN UP';
                    }
                } catch (e) {
                    console.error('Parse error:', e, 'Response:', text);
                    document.getElementById('signupErrors').innerHTML = 
                        '<div class="error-message"><i class="fas fa-exclamation-circle"></i> Server error: ' + text.substring(0, 200) + '</div>';
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-user-check"></i> SIGN UP';
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                document.getElementById('signupErrors').innerHTML = 
                    '<div class="error-message"><i class="fas fa-exclamation-circle"></i> Network error occurred. Please check your connection and try again.</div>';
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-user-check"></i> SIGN UP';
            });
        });
    </script>

</body>
</html>