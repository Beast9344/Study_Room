<?php
require __DIR__ . '/../config/config.php';

// Optional: Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';

    // Optional: CSRF check
    if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        $error_message = "Invalid CSRF token.";
    } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) && !empty($password) && !empty($role)) {
        $stmt = $conn->prepare("SELECT id, username, password as password_hash, role FROM users WHERE email = ? AND role = ?");
        $stmt->bind_param("ss", $email, $role);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password_hash'])) {
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $email,
                    'role' => $user['role']
                ];
                header('Location: index.php');
                exit();
            } else {
                $error_message = "Incorrect password.";
            }
        } else {
            $error_message = "User not found or role mismatch.";
        }
    } else {
        $error_message = "Please fill in all fields correctly.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Login - Study Room</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
  <script src="https://accounts.google.com/gsi/client" async defer></script>
  <style>
    body {
      font-family: 'Inter', sans-serif;
      background: url('https://i.gifer.com/2DV.gif') no-repeat center center fixed;
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
      appearance: none;
    }
  </style>
</head>
<body class="flex items-center justify-center min-h-screen text-gray-200">

  <div class="glass-container p-8 rounded-2xl w-full max-w-md mx-4">
    <div class="text-center mb-8">
      <h1 class="text-4xl font-bold text-white">Welcome Back</h1>
      <p class="text-gray-300 mt-2">Log in to continue your journey</p>
    </div>

    <?php if (!empty($error_message)): ?>
      <div class="bg-red-500/30 border border-red-500/50 text-red-200 px-4 py-3 rounded-lg mb-6 text-center flex items-center justify-center">
        <i class="fa-solid fa-circle-exclamation mr-2"></i>
        <span><?= htmlspecialchars($error_message) ?></span>
      </div>
    <?php endif; ?>

    <form method="POST" action="login.php" class="space-y-6">
      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
      <div class="relative">
        <i class="fa-solid fa-envelope absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
        <input type="email" name="email" required
          class="form-input bg-black/20 border-gray-700/50 focus:ring-2 focus:ring-purple-500/50 block w-full pl-12 pr-4 py-3 rounded-lg"
          placeholder="Email Address">
      </div>
      <div class="relative">
        <i class="fa-solid fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
        <input type="password" name="password" required
          class="form-input bg-black/20 border-gray-700/50 focus:ring-2 focus:ring-purple-500/50 block w-full pl-12 pr-4 py-3 rounded-lg"
          placeholder="Password">
      </div>
      <div class="relative select-wrapper">
        <i class="fa-solid fa-user-shield absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
        <select name="role" required
          class="form-input bg-black/20 border-gray-700/50 focus:ring-2 focus:ring-purple-500/50 block w-full pl-12 pr-4 py-3 rounded-lg appearance-none">
          <option value="" disabled selected class="text-gray-500">Select your role</option>
          <option value="admin" class="bg-gray-800 text-white">Admin</option>
          <option value="user" class="bg-gray-800 text-white">User</option>
          <option value="guest" class="bg-gray-800 text-white">Guest</option>
        </select>
      </div>
      <button type="submit"
        class="w-full bg-gradient-to-r from-purple-600 to-indigo-600 text-white font-bold py-3 px-4 rounded-lg hover:from-purple-700 hover:to-indigo-700 shadow-lg transition hover:-translate-y-1">
        Secure Login
      </button>
    </form>

    <div class="relative flex py-5 items-center">
      <div class="flex-grow border-t border-gray-600/50"></div>
      <span class="flex-shrink mx-4 text-gray-400">Or continue with</span>
      <div class="flex-grow border-t border-gray-600/50"></div>
    </div>

    <div class="flex justify-center gap-4">
      <div id="g_id_onload"
        data-client_id="28282326291-fjromftq1ui77v6d09j0naksrrsdvvsl.apps.googleusercontent.com"
        data-context="signin"
        data-ux_mode="popup"
        data-callback="handleGoogleSignIn"
        data-auto_prompt="false">
      </div>
      <div class="g_id_signin"
        data-type="standard"
        data-shape="rectangular"
        data-theme="outline"
        data-text="continue_with"
        data-size="large"
        data-logo_alignment="left"
        data-width="300">
      </div>
    </div>

    <div class="mt-8 text-center">
      <p class="text-sm text-gray-400">Don't have an account?
        <a href="register.php" class="font-semibold text-purple-400 hover:underline">Register Now</a>
      </p>
    </div>
  </div>

  <script>
    function handleGoogleSignIn(response) {
      fetch('google-auth.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ credential: response.credential })
      })
      .then(res => res.json())
      .then(data => {
        if (data.success && data.redirect) {
          window.location.href = data.redirect;
        } else {
          showError(data.error || "Google login failed.");
        }
      })
      .catch(() => showError("Network error occurred."));
    }

    function showError(message) {
      const div = document.createElement('div');
      div.className = 'fixed top-5 right-5 bg-red-500 text-white py-2 px-4 rounded-lg shadow-lg animate-bounce';
      div.innerText = message;
      document.body.appendChild(div);
      setTimeout(() => div.remove(), 5000);
    }
  </script>
</body>
</html>
