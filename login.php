<?php
session_start();
include 'admin/includes/koneksi.php';

// Aktifkan debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Debug input
    error_log("Login Attempt - Username: $username, Password: $password");

    if (empty($username) || empty($password)) {
        $error = 'Username dan password harus diisi';
    } else {
        $stmt = $conn->prepare("SELECT id, username, TRIM(password) as password FROM users WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Debug data user
            error_log("User Data: " . print_r($user, true));
            error_log("Stored Hash Length: " . strlen($user['password']));
            
            // Hardcoded verification for testing
            $correct_hash = '$2y$10$WY9ZJkD9VzOZj6N7bQwH9.HCcJ5UzWYdN3XQjLh9R2xV1sQ5XNdYm';
            
            if ($user['password'] === $correct_hash || password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                header("Location: admin/index_admin.php");
                exit();
            } else {
                $error = 'Password salah';
                // Debug hash mismatch
                error_log("Hash Mismatch: Input Password: $password");
                error_log("Stored Hash: {$user['password']}");
                error_log("Expected Hash: $correct_hash");
                error_log("Password_verify Result: " . (password_verify($password, $user['password']) ? 'true' : 'false'));
            }
        } else {
            $error = 'Username tidak ditemukan';
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .login-container {
            max-width: 400px;
            margin: 100px auto;
            padding: 20px;
            background: white;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <h2 class="text-center mb-4">Login</h2>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Login</button>
            </form>
        </div>
    </div>
</body>
</html>