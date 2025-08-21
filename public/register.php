<?php
require __DIR__ . '/../config/config.php';

if (isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}

$error_message = '';
$success_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['username'], $_POST['email'], $_POST['password'], $_POST['confirm_password'], $_POST['role'])) {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);
        $confirm_password = trim($_POST['confirm_password']);
        $role = trim($_POST['role']);

        if (empty($username) || empty($email) || empty($password) || empty($role)) {
            $error_message = "Please fill out all fields.";
        } elseif ($password !== $confirm_password) {
            $error_message = "Passwords do not match.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Invalid email format.";
        } else {
            // Check if email already exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $error_message = "An account with this email already exists.";
            } else {
                // Insert new user
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                $insert_stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
                $insert_stmt->bind_param("ssss", $username, $email, $hashed_password, $role);

                if ($insert_stmt->execute()) {
                    // Automatically log in the user after registration
                    $_SESSION['user'] = [
                        'id' => $insert_stmt->insert_id,
                        'username' => $username,
                        'email' => $email,
                        'role' => $role
                    ];
                    header("Location: index.php");
                    exit();
                } else {
                    $error_message = "Registration failed. Please try again.";
                }
                $insert_stmt->close();
            }
            $stmt->close();
        }
    } else {
        $error_message = "Please fill out all required fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Study Room</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: url('https://i.gifer.com/1lDs.gif') no-repeat center center fixed;
            background-size: cover;
        }
        .glass-container {
            background: rgba(10, 10, 20, 0.6);
            backdrop-filter: blur(15px) saturate(150%);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
        }
        .select-wrapper {
            position: relative;
        }
        .select-wrapper::after {
            content: '\f078';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            right: 1.25rem;
            top: 50%;
            transform: translateY(-50%);
            pointer-events: none;
            color: rgba(255, 255, 255, 0.6);
        }
        .form-input.appearance-none {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
        }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen text-gray-200 py-8">
    
    <div class="glass-container p-8 rounded-2xl w-full max-w-md mx-4">
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold text-white">Create Account</h1>
            <p class="text-gray-300 mt-2">Join our community to start studying</p>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="bg-red-500/30 border border-red-500/50 text-red-200 px-4 py-3 rounded-lg mb-6 text-center flex items-center justify-center">
                <i class="fa-solid fa-circle-exclamation mr-2"></i>
                <span><?= htmlspecialchars($error_message) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="register.php" class="space-y-6">
            <div class="relative">
                <i class="fa-solid fa-user absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input type="text" id="username" name="username" class="form-input bg-black/20 border-gray-700/50 focus:ring-2 focus:ring-purple-500/50 focus:border-purple-500 block w-full pl-12 pr-4 py-3 rounded-lg transition-all duration-300" placeholder="Username" required>
            </div>
             <div class="relative">
                <i class="fa-solid fa-envelope absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input type="email" id="email" name="email" class="form-input bg-black/20 border-gray-700/50 focus:ring-2 focus:ring-purple-500/50 focus:border-purple-500 block w-full pl-12 pr-4 py-3 rounded-lg transition-all duration-300" placeholder="Email Address" required>
            </div>
            <div class="relative">
                <i class="fa-solid fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input type="password" id="password" name="password" class="form-input bg-black/20 border-gray-700/50 focus:ring-2 focus:ring-purple-500/50 focus:border-purple-500 block w-full pl-12 pr-4 py-3 rounded-lg transition-all duration-300" placeholder="Password" required>
            </div>
             <div class="relative">
                <i class="fa-solid fa-check-double absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input type="password" id="confirm_password" name="confirm_password" class="form-input bg-black/20 border-gray-700/50 focus:ring-2 focus:ring-purple-500/50 focus:border-purple-500 block w-full pl-12 pr-4 py-3 rounded-lg transition-all duration-300" placeholder="Confirm Password" required>
            </div>
            <div class="relative select-wrapper">
                <i class="fa-solid fa-user-shield absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <select id="role" name="role" class="form-input bg-black/20 border-gray-700/50 focus:ring-2 focus:ring-purple-500/50 focus:border-purple-500 block w-full pl-12 pr-4 py-3 rounded-lg transition-all duration-300 appearance-none" required>
                    <option value="" disabled selected class="text-gray-500">Select your role</option>
                    <option value="admin" class="bg-gray-800 text-white">Admin</option>
                    <option value="user" class="bg-gray-800 text-white">User</option>
                    <option value="guest" class="bg-gray-800 text-white">Guest</option>
                </select>
            </div>
            <div>
                <button type="submit" class="w-full bg-gradient-to-r from-purple-600 to-indigo-600 text-white font-bold py-3 px-4 rounded-lg hover:from-purple-700 hover:to-indigo-700 focus:outline-none focus:ring-4 focus:ring-purple-500/50 transition-all duration-300 transform hover:-translate-y-1 shadow-lg hover:shadow-2xl">
                    Create Account
                </button>
            </div>
        </form>

        <div class="relative flex py-5 items-center">
            <div class="flex-grow border-t border-gray-600/50"></div>
            <span class="flex-shrink mx-4 text-gray-400">Or sign up with</span>
            <div class="flex-grow border-t border-gray-600/50"></div>
        </div>

        <div class="flex justify-center gap-4">
            <div id="g_id_onload"
                data-client_id="28282326291-fjromftq1ui77v6d09j0naksrrsdvvsl.apps.googleusercontent.com"
                data-context="signin" data-ux_mode="popup" data-callback="handleGoogleSignIn" data-auto_prompt="false">
            </div>
            <div class="g_id_signin" data-type="standard" data-shape="rectangular" data-theme="outline"
                data-text="continue_with" data-size="large" data-logo_alignment="left" data-width="300">
            </div>
        </div>

        <div class="mt-8 text-center">
            <p class="text-sm text-gray-400">Already have an account? <a href="login.php" class="font-semibold text-purple-400 hover:text-purple-300 hover:underline">Log In</a></p>
        </div>
    </div>
    
    <script>
        function handleGoogleSignIn(response) {
            console.log("Encoded JWT ID token: " + response.credential);
            // Here you would send the token to your backend to create a new user or log them in.
            // fetch('/google_auth.php', { method: 'POST', ... })
        }
    </script>
</body>
</html>
