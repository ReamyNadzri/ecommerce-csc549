<?php
// auth.php - CORRECTED

// --- Step 1: All PHP Logic and Processing First ---

// Start the session at the very beginning.
// This is crucial for setting session variables upon login.

session_start();

// Include the database connection file.
require_once '../config/connection.php';

// Initialize message variables to hold feedback for the user.
$register_message = '';
$login_message = '';

// --- Registration Logic ---
// Check if the registration form was submitted.
if (isset($_POST['register'])) {
    // Retrieve and sanitize user inputs.
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Server-side validation.
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $register_message = '<div class="alert alert-danger">All fields are required.</div>';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $register_message = '<div class="alert alert-danger">Invalid email format.</div>';
    } elseif ($password !== $confirm_password) {
        $register_message = '<div class="alert alert-danger">Passwords do not match.</div>';
    } else {
        // Check if the username or email already exists in the database.
        $stmt = $conn->prepare("SELECT UserID FROM users WHERE Username = ? OR Email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $register_message = '<div class="alert alert-danger">Username or Email already taken.</div>';
        } else {
            // Hash the password for security.
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            // Generate a unique ID for the new user.
            $user_id = bin2hex(random_bytes(18));

            // Insert the new user into the database.
            $insert_stmt = $conn->prepare("INSERT INTO users (UserID, Username, Email, PasswordHash, CreatedAt) VALUES (?, ?, ?, ?, NOW())");
            $insert_stmt->bind_param("ssss", $user_id, $username, $email, $password_hash);

            if ($insert_stmt->execute()) {
                $register_message = '<div class="alert alert-success">Registration successful! You can now log in.</div>';
            } else {
                $register_message = '<div class="alert alert-danger">Error during registration. Please try again.</div>';
            }
            $insert_stmt->close();
        }
        $stmt->close();
    }
}

// --- Login Logic ---
// Check if the login form was submitted.
if (isset($_POST['login'])) {
    $email_or_username = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email_or_username) || empty($password)) {
        $login_message = '<div class="alert alert-danger">Email/Username and Password are required.</div>';
    } else {
        // Fetch the user from the database based on email or username.
        $stmt = $conn->prepare("SELECT UserID, Username, PasswordHash FROM users WHERE Email = ? OR Username = ?");
        $stmt->bind_param("ss", $email_or_username, $email_or_username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // Verify the submitted password against the stored hash.
            if (password_verify($password, $user['PasswordHash'])) {
                // Password is correct, set session variables.
                $_SESSION['user_id'] = $user['UserID'];
                $_SESSION['username'] = $user['Username'];

                // Update the last login timestamp.
                $update_stmt = $conn->prepare("UPDATE users SET LastLogin = NOW() WHERE UserID = ?");
                $update_stmt->bind_param("s", $user['UserID']);
                $update_stmt->execute();
                $update_stmt->close();

                // --- REDIRECT and STOP SCRIPT ---
                // This is the crucial part. We redirect *before* any HTML is sent.
                header("Location: /ecommerce-csc549/index.php");
                exit(); // Always call exit() after a header redirect.
            } else {
                $login_message = '<div class="alert alert-danger">Invalid password.</div>';
            }
        } else {
            $login_message = '<div class="alert alert-danger">No user found with that email or username.</div>';
        }
        $stmt->close();
    }
}

// --- Step 2: HTML Output ---
// The code below will ONLY run if the user has NOT been redirected.
// Now it's safe to include the header and print the HTML page.
$pageTitle = "Login & Register"; // You can set a dynamic page title
include '../includes/header.php';

?>
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-lg border-0" style="border-radius: 1rem;">
                <div class="card-body p-5">
                    <ul class="nav nav-tabs mb-4" id="authTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-bold <?php if(empty($register_message)) echo 'active'; ?>" id="login-tab" data-bs-toggle="tab" data-bs-target="#login-tab-pane" type="button" role="tab" aria-controls="login-tab-pane" aria-selected="true">
                                Login
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-bold <?php if(!empty($register_message)) echo 'active'; ?>" id="register-tab" data-bs-toggle="tab" data-bs-target="#register-tab-pane" type="button" role="tab" aria-controls="register-tab-pane" aria-selected="false">
                                Register
                            </button>
                        </li>
                    </ul>
                    <div class="tab-content" id="authTabContent">
                        <!-- Login Tab -->
                        <div class="tab-pane fade <?php if(empty($register_message)) echo 'show active'; ?>" id="login-tab-pane" role="tabpanel" aria-labelledby="login-tab">
                            <h2 class="card-title text-center fw-bold text-dark mb-4">Welcome Back!</h2>
                            <?php if (!empty($login_message)) echo $login_message; ?>
                            <form method="POST" action="auth.php">
                                <input type="hidden" name="login" value="1">
                                <div class="mb-4">
                                    <label for="email" class="form-label fw-semibold">Email or Username</label>
                                    <input type="text" id="email" name="email" class="form-control form-control-lg" placeholder="you@example.com" required>
                                </div>
                                <div class="mb-3">
                                    <label for="password" class="form-label fw-semibold">Password</label>
                                    <input type="password" id="password" name="password" class="form-control form-control-lg" placeholder="************" required>
                                </div>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-danger btn-lg fw-bold text-white">Login</button>
                                </div>
                            </form>
                        </div>
                        <!-- Register Tab -->
                        <div class="tab-pane fade <?php if(!empty($register_message)) echo 'show active'; ?>" id="register-tab-pane" role="tabpanel" aria-labelledby="register-tab">
                            <h2 class="card-title text-center fw-bold text-dark mb-4">Create Account</h2>
                            <?php if (!empty($register_message)) echo $register_message; ?>
                            <form method="POST" action="auth.php">
                                <input type="hidden" name="register" value="1">
                                <div class="mb-4">
                                    <label for="reg-username" class="form-label fw-semibold">Username</label>
                                    <input type="text" id="reg-username" name="username" class="form-control form-control-lg" placeholder="Choose a username" required>
                                </div>
                                <div class="mb-4">
                                    <label for="reg-email" class="form-label fw-semibold">Email</label>
                                    <input type="email" id="reg-email" name="email" class="form-control form-control-lg" placeholder="you@example.com" required>
                                </div>
                                <div class="mb-3">
                                    <label for="reg-password" class="form-label fw-semibold">Password</label>
                                    <input type="password" id="reg-password" name="password" class="form-control form-control-lg" placeholder="************" required>
                                </div>
                                <div class="mb-3">
                                    <label for="reg-confirm-password" class="form-label fw-semibold">Confirm Password</label>
                                    <input type="password" id="reg-confirm-password" name="confirm_password" class="form-control form-control-lg" placeholder="************" required>
                                </div>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-danger btn-lg fw-bold text-white">Register</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Finally, include the footer if you have one.
include '../includes/footer.php';
?>
