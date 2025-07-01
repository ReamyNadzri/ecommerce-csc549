<?php
// auth.php

// --- Start Session ---
// A session is started to manage user login state.
// It must be called before any HTML output.
session_start();

// --- Include Database Connection ---
// This line includes the database connection file, making the $conn variable available.
require_once '../config/connection.php';
// Do NOT include header.php here; include it after all PHP logic and redirects.

// --- Initialize Message Variables ---
// These variables will hold feedback messages for the user.
$register_message = '';
$login_message = '';

// --- Registration Logic ---
// Check if the form was submitted by checking the 'register' POST variable.
if (isset($_POST['register'])) {
    // Retrieve and sanitize user inputs.
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // --- Server-side Validation ---
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $register_message = '<div class="alert alert-danger">All fields are required.</div>';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $register_message = '<div class="alert alert-danger">Invalid email format.</div>';
    } elseif ($password !== $confirm_password) {
        $register_message = '<div class="alert alert-danger">Passwords do not match.</div>';
    } else {
        // --- Check if Email or Username already exists ---
        $stmt = $conn->prepare("SELECT UserID FROM users WHERE Username = ? OR Email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $register_message = '<div class="alert alert-danger">Username or Email already exists.</div>';
        } else {
            // --- Hash Password ---
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            // --- Generate a unique UserID (UUID) ---
            // This creates a secure, random, and unique ID for the user.
            $user_id = bin2hex(random_bytes(18)); // 36 characters

            // --- Insert New User into Database ---
            $stmt = $conn->prepare("INSERT INTO users (UserID, Username, Email, PasswordHash, CreatedAt) VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param("ssss", $user_id, $username, $email, $password_hash);

            if ($stmt->execute()) {
                $register_message = '<div class="alert alert-success">Registration successful! You can now log in.</div>';
            } else {
                $register_message = '<div class="alert alert-danger">Error: ' . $stmt->error . '</div>';
            }
        }
        $stmt->close();
    }
}

// --- Login Logic ---
// Check if the form was submitted by checking the 'login' POST variable.
if (isset($_POST['login'])) {
    // Retrieve and sanitize inputs.
    $email_or_username = trim($_POST['email']);
    $password = $_POST['password'];

    // --- Server-side Validation ---
    if (empty($email_or_username) || empty($password)) {
        $login_message = '<div class="alert alert-danger">Email/Username and Password are required.</div>';
    } else {
        // --- Prepare and Execute Query ---
        // Fetches the user based on email or username.
        $stmt = $conn->prepare("SELECT UserID, Username, PasswordHash FROM users WHERE Email = ? OR Username = ?");
        $stmt->bind_param("ss", $email_or_username, $email_or_username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // --- Verify Password ---
            if (password_verify($password, $user['PasswordHash'])) {
                // --- Update Last Login Timestamp ---
                $update_stmt = $conn->prepare("UPDATE users SET LastLogin = NOW() WHERE UserID = ?");
                $update_stmt->bind_param("s", $user['UserID']);
                $update_stmt->execute();
                $update_stmt->close();

                // --- Set Session Variables ---
                $_SESSION['user_id'] = $user['UserID'];
                $_SESSION['username'] = $user['Username'];

                // REDIRECT BEFORE ANY OUTPUT!
                header("Location: ../index.php");
                exit();
            } else {
                $login_message = '<div class="alert alert-danger">Invalid password.</div>';
            }
        } else {
            $login_message = '<div class="alert alert-danger">No user found with that email or username.</div>';
        }
        $stmt->close();
    }
}

// Close the database connection at the end of the script.
$conn->close();

// Now include the header, after all PHP logic and before HTML output.
include '../includes/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login & Register</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts - Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        /* Custom Styles */
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
        }
        .card {
            border: none;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            border-radius: 1rem;
        }
        .nav-tabs .nav-link {
            border: none;
            border-bottom: 2px solid transparent;
            color: #6c757d;
        }
        .nav-tabs .nav-link.active {
            border-color: #dc3545;
            color: #dc3545;
        }
        .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
            transition: background-color 0.3s;
        }
        .btn-danger:hover {
            background-color: #c82333;
            border-color: #bd2130;
        }
        .form-control-lg {
            min-height: calc(1.5em + 1rem + 2px);
            border-radius: 0.5rem;
        }
    </style>
</head>
<body>
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card">
                <div class="card-body p-5">
                    <ul class="nav nav-tabs mb-4" id="authTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active fw-bold" id="login-tab" data-bs-toggle="tab" data-bs-target="#login-tab-pane" type="button" role="tab" aria-controls="login-tab-pane" aria-selected="true">
                                Login
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-bold" id="register-tab" data-bs-toggle="tab" data-bs-target="#register-tab-pane" type="button" role="tab" aria-controls="register-tab-pane" aria-selected="false">
                                Register
                            </button>
                        </li>
                    </ul>
                    <div class="tab-content" id="authTabContent">
                        <!-- Login Tab -->
                        <div class="tab-pane fade show active" id="login-tab-pane" role="tabpanel" aria-labelledby="login-tab">
                            <h2 class="card-title text-center fw-bold text-dark mb-4">Welcome Back!</h2>
                            <?php if (!empty($login_message)) echo $login_message; ?>
                            <form method="POST" action="auth.php">
                                <input type="hidden" name="login" value="1">
                                <div class="mb-4">
                                    <label for="email" class="form-label fw-semibold">Email or Username</label>
                                    <input
                                        type="text"
                                        id="email"
                                        name="email"
                                        class="form-control form-control-lg"
                                        placeholder="you@example.com"
                                        required
                                    >
                                </div>
                                <div class="mb-3">
                                    <label for="password" class="form-label fw-semibold">Password</label>
                                    <input
                                        type="password"
                                        id="password"
                                        name="password"
                                        class="form-control form-control-lg"
                                        placeholder="************"
                                        required
                                    >
                                </div>
                                <div class="d-grid">
                                    <button
                                        type="submit"
                                        class="btn btn-danger btn-lg fw-bold text-white"
                                    >
                                        Login
                                    </button>
                                </div>
                                <div class="text-center mt-4">
                                    <p class="text-muted small">Don't have an account? <a href="#" class="fw-bold text-danger text-decoration-none" data-bs-toggle="tab" data-bs-target="#register-tab-pane">Sign Up</a></p>
                                </div>
                            </form>
                        </div>
                        <!-- Register Tab -->
                        <div class="tab-pane fade" id="register-tab-pane" role="tabpanel" aria-labelledby="register-tab">
                            <h2 class="card-title text-center fw-bold text-dark mb-4">Create Account</h2>
                            <?php if (!empty($register_message)) echo $register_message; ?>
                            <form method="POST" action="auth.php">
                                <input type="hidden" name="register" value="1">
                                <div class="mb-4">
                                    <label for="reg-username" class="form-label fw-semibold">Username</label>
                                    <input
                                        type="text"
                                        id="reg-username"
                                        name="username"
                                        class="form-control form-control-lg"
                                        placeholder="Choose a username"
                                        required
                                    >
                                </div>
                                <div class="mb-4">
                                    <label for="reg-email" class="form-label fw-semibold">Email</label>
                                    <input
                                        type="email"
                                        id="reg-email"
                                        name="email"
                                        class="form-control form-control-lg"
                                        placeholder="you@example.com"
                                        required
                                    >
                                </div>
                                <div class="mb-3">
                                    <label for="reg-password" class="form-label fw-semibold">Password</label>
                                    <input
                                        type="password"
                                        id="reg-password"
                                        name="password"
                                        class="form-control form-control-lg"
                                        placeholder="************"
                                        required
                                    >
                                </div>
                                <div class="mb-3">
                                    <label for="reg-confirm-password" class="form-label fw-semibold">Confirm Password</label>
                                    <input
                                        type="password"
                                        id="reg-confirm-password"
                                        name="confirm_password"
                                        class="form-control form-control-lg"
                                        placeholder="************"
                                        required
                                    >
                                </div>
                                <div class="d-grid">
                                    <button
                                        type="submit"
                                        class="btn btn-danger btn-lg fw-bold text-white"
                                    >
                                        Register
                                    </button>
                                </div>
                                <div class="text-center mt-4">
                                    <p class="text-muted small">Already have an account? <a href="#" class="fw-bold text-danger text-decoration-none" data-bs-toggle="tab" data-bs-target="#login-tab-pane">Login</a></p>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // This script ensures that if there's a registration message, the register tab is shown.
    // This is useful for showing registration errors to the user after a page reload.
    document.addEventListener('DOMContentLoaded', function() {
        const registerTabPane = document.getElementById('register-tab-pane');
        const loginTabPane = document.getElementById('login-tab-pane');
        const registerTab = new bootstrap.Tab(document.getElementById('register-tab'));
        const loginTab = new bootstrap.Tab(document.getElementById('login-tab'));

        <?php if (!empty($register_message)): ?>
            // If there is a registration message, switch to the register tab.
            registerTab.show();
        <?php elseif (!empty($login_message)): ?>
            // If there is a login message, ensure the login tab is active.
            loginTab.show();
        <?php endif; ?>

        // Handle clicking on the "Sign Up" or "Login" links within the forms
        document.querySelectorAll('a[data-bs-toggle="tab"]').forEach((el) => {
            el.addEventListener('click', function (event) {
                event.preventDefault();
                const targetTabId = this.getAttribute('data-bs-target');
                const targetTab = new bootstrap.Tab(document.querySelector(`button[data-bs-target="${targetTabId}"]`));
                targetTab.show();
            });
        });
    });
</script>
</body>
</html>
<?php
// End of auth.php
include '../includes/footer.php'; // Include footer if needed
// Close the database connection
// End of file
?>
