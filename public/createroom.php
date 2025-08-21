<?php
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../utils/auth.php';

// --- Session Security & Authentication ---
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

// Ensure user_id is properly set from session
$user_id = $_SESSION['user'] ?? null;
if (!$user_id) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// --- CSRF Token ---
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- Form Submission Logic ---
$error_message = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['name'], $_POST['description'], $_POST['participant_limit'], $_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $participant_limit = filter_input(INPUT_POST, 'participant_limit', FILTER_VALIDATE_INT, ["options" => ["min_range" => 2, "max_range" => 50]]);

        if (empty($name) || $participant_limit === false) {
            $error_message = "Please provide a valid room name and participant limit (2-50).";
        } else {
            try {
                $conn->begin_transaction();

                // Insert into rooms table
                $stmt = $conn->prepare("INSERT INTO rooms (name, description, owner_id, participant_limit) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssii", $name, $description, $user_id, $participant_limit);
                $stmt->execute();
                $room_id = $stmt->insert_id;
                $stmt->close();

                // Add owner to participants table
                $stmt = $conn->prepare("INSERT INTO room_participants (room_id, user_id) VALUES (?, ?)");
                $stmt->bind_param("ii", $room_id, $user_id);
                $stmt->execute();
                $stmt->close();

                // Update room's current participant count
                $updateStmt = $conn->prepare("UPDATE rooms SET current_participants = 1 WHERE id = ?");
                $updateStmt->bind_param("i", $room_id);
                $updateStmt->execute();
                $updateStmt->close();


                $conn->commit();
                header("Location: index.php"); // Redirect to dashboard on success
                exit();
            } catch (Exception $e) {
                $conn->rollback();
                error_log("Room Creation Error: " . $e->getMessage());
                $error_message = "An error occurred while creating the room. Please try again.";
            }
        }
    } else {
        $error_message = "Invalid request. Please try again.";
    }
}

// Fetch user data for header, assuming it's stored in session from login
$sql = "SELECT username, email, profile_picture FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($username, $email, $profile_picture);
$stmt->fetch();
$stmt->close();

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta charset="UTF-8">
    <title>Create Study Room</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>

<body class="bg-gray-900 text-white">

    <button id="mobileMenuBtn"
        class="md:hidden fixed top-4 left-4 z-50 bg-gray-800/80 backdrop-blur-md p-3 rounded-xl border border-white/10 hover:bg-gray-700/80 transition-all duration-300">
        <i class="fas fa-bars text-white"></i>
    </button>

    <div id="sidebar" class="sidebar p-6">
        <div class="mb-8">
            <a href="index.php" class="cursor-pointer flex items-center space-x-3 group">
                <div class="relative">
                    <i
                        class="fas fa-graduation-cap text-3xl text-blue-400 group-hover:text-blue-300 transition-all duration-300 float-animation"></i>
                    <div class="absolute -top-1 -right-1 w-3 h-3 bg-green-400 rounded-full animate-pulse"></div>
                </div>
                <div>
                    <h1
                        class="text-2xl font-bold bg-gradient-to-r from-blue-400 to-purple-400 bg-clip-text text-transparent">
                        Study Room
                    </h1>
                    <p class="text-xs text-gray-400">Knowledge Hub</p>
                </div>
            </a>
        </div>

        <ul class="space-y-2">
            <li>
                <a href="createroom.php"
                    class="sidebar-item flex items-center p-4 rounded-xl group hover:shadow-lg transition-all duration-300 bg-gradient-to-r from-blue-600/20 to-purple-600/20 border border-blue-500/30">
                    <div class="relative">
                        <i
                            class="fas fa-plus-circle mr-4 text-2xl text-blue-400 group-hover:text-white group-hover:scale-110 transition-all duration-300"></i>
                        <div class="absolute -top-1 -right-1 w-2 h-2 bg-blue-400 rounded-full animate-ping"></div>
                    </div>
                    <div class="flex-1">
                        <span class="text-white font-semibold text-lg">Create Room</span>
                        <p class="text-xs text-blue-300 mt-1">Start a new study session</p>
                    </div>
                    <span
                        class="ml-auto bg-gradient-to-r from-blue-500 to-purple-500 text-white px-3 py-1 rounded-full text-xs font-bold shimmer">
                        NEW
                    </span>
                </a>
            </li>
            <li>
                <a href="#" onclick="openTaskModal()"
                    class="sidebar-item flex items-center p-4 rounded-xl group hover:glow-blue transition-all duration-300">
                    <div
                        class="w-12 h-12 bg-gradient-to-br from-blue-500/20 to-blue-600/20 rounded-xl flex items-center justify-center mr-4">
                        <i class="fas fa-tasks text-blue-400 group-hover:text-blue-300 transition-all duration-300"></i>
                    </div>
                    <div class="flex-1">
                        <span class="text-gray-300 group-hover:text-white font-medium">My Tasks</span>
                        <p class="text-xs text-gray-500 group-hover:text-gray-400">Manage your tasks</p>
                    </div>
                    <div class="flex items-center space-x-2">
                        <span
                            class="bg-blue-500/20 text-blue-400 px-2 py-1 rounded-full text-xs font-bold notification-badge">1</span>
                        <i
                            class="fas fa-chevron-right text-gray-500 group-hover:text-white group-hover:translate-x-1 transition-all duration-300"></i>
                    </div>
                </a>
            </li>

            <li>
                <a href="#" onclick="openNotesModal()"
                    class="sidebar-item flex items-center p-4 rounded-xl group hover:glow-green transition-all duration-300">
                    <div
                        class="w-12 h-12 bg-gradient-to-br from-green-500/20 to-green-600/20 rounded-xl flex items-center justify-center mr-4">
                        <i
                            class="fas fa-sticky-note text-green-400 group-hover:text-green-300 transition-all duration-300"></i>
                    </div>
                    <div class="flex-1">
                        <span class="text-gray-300 group-hover:text-white font-medium">Notes</span>
                        <p class="text-xs text-gray-500 group-hover:text-gray-400">Study notes & ideas</p>
                    </div>
                    <div class="flex items-center space-x-2">
                        <span
                            class="bg-green-500/20 text-green-400 px-2 py-1 rounded-full text-xs font-bold notification-badge">4</span>
                        <i
                            class="fas fa-chevron-right text-gray-500 group-hover:text-white group-hover:translate-x-1 transition-all duration-300"></i>
                    </div>
                </a>
            </li>

            <li>
                <a href="#"
                    class="sidebar-item flex items-center p-4 rounded-xl group hover:glow-green transition-all duration-300">
                    <div
                        class="w-12 h-12 bg-gradient-to-br from-emerald-500/20 to-emerald-600/20 rounded-xl flex items-center justify-center mr-4">
                        <i
                            class="fas fa-comments text-emerald-400 group-hover:text-emerald-300 transition-all duration-300"></i>
                    </div>
                    <div class="flex-1">
                        <span class="text-gray-300 group-hover:text-white font-medium">Group Chat</span>
                        <p class="text-xs text-gray-500 group-hover:text-gray-400">Connect with peers</p>
                    </div>
                    <div class="flex items-center space-x-2">
                        <span
                            class="bg-emerald-500/20 text-emerald-400 px-2 py-1 rounded-full text-xs font-bold notification-badge">3</span>
                        <i
                            class="fas fa-chevron-right text-gray-500 group-hover:text-white group-hover:translate-x-1 transition-all duration-300"></i>
                    </div>
                </a>
            </li>

            <li>
                <a href="#"
                    class="sidebar-item flex items-center p-4 rounded-xl group hover:glow-purple transition-all duration-300">
                    <div
                        class="w-12 h-12 bg-gradient-to-br from-purple-500/20 to-purple-600/20 rounded-xl flex items-center justify-center mr-4">
                        <i
                            class="fas fa-users text-purple-400 group-hover:text-purple-300 transition-all duration-300"></i>
                    </div>
                    <div class="flex-1">
                        <span class="text-gray-300 group-hover:text-white font-medium">Collaborators</span>
                        <p class="text-xs text-gray-500 group-hover:text-gray-400">Your study partners</p>
                    </div>
                    <i
                        class="fas fa-chevron-right text-gray-500 group-hover:text-white group-hover:translate-x-1 transition-all duration-300"></i>
                </a>
            </li>

            <li>
                <a href="#"
                    class="sidebar-item flex items-center p-4 rounded-xl group hover:shadow-lg transition-all duration-300">
                    <div
                        class="w-12 h-12 bg-gradient-to-br from-red-500/20 to-red-600/20 rounded-xl flex items-center justify-center mr-4">
                        <i class="fas fa-video text-red-400 group-hover:text-red-300 transition-all duration-300"></i>
                    </div>
                    <div class="flex-1">
                        <span class="text-gray-300 group-hover:text-white font-medium">Live Sessions</span>
                        <p class="text-xs text-gray-500 group-hover:text-gray-400">Video study rooms</p>
                    </div>
                    <div class="w-2 h-2 bg-red-400 rounded-full animate-pulse"></div>
                </a>
            </li>

            <li class="my-6">
                <div class="h-px bg-gradient-to-r from-transparent via-white/20 to-transparent"></div>
            </li>

            <li>
                <a href="#"
                    class="sidebar-item flex items-center p-4 rounded-xl group hover:shadow-lg transition-all duration-300 bg-gradient-to-r from-purple-600/10 to-pink-600/10 border border-purple-500/20">
                    <div
                        class="w-12 h-12 bg-gradient-to-br from-purple-500/20 to-pink-500/20 rounded-xl flex items-center justify-center mr-4">
                        <i
                            class="fas fa-chalkboard-teacher text-purple-400 group-hover:text-purple-300 transition-all duration-300"></i>
                    </div>
                    <div class="flex-1">
                        <span class="text-purple-300 group-hover:text-white font-semibold">Teacher Zone</span>
                        <p class="text-xs text-purple-400 group-hover:text-purple-300">Premium access</p>
                    </div>
                    <div class="flex items-center space-x-2">
                        <i class="fas fa-crown text-yellow-400 text-sm animate-pulse"></i>
                        <i class="fas fa-lock text-purple-400 text-sm"></i>
                    </div>
                </a>
            </li>

            <li class="mt-8">
                <form action="logout.php" method="POST" class="w-full">
                    <input type="hidden" name="csrf_token"
                        value="<?php echo isset($_SESSION['csrf_token']) ? htmlspecialchars($_SESSION['csrf_token']) : ''; ?>">
                    <button type="submit"
                        class="sidebar-item flex items-center w-full p-4 rounded-xl group hover:bg-red-500/20 hover:border-red-500/30 border border-transparent transition-all duration-300">
                        <div
                            class="w-12 h-12 bg-gradient-to-br from-red-500/20 to-red-600/20 rounded-xl flex items-center justify-center mr-4">
                            <i
                                class="fas fa-sign-out-alt text-red-400 group-hover:text-red-300 transition-all duration-300"></i>
                        </div>
                        <div class="flex-1 text-left">
                            <span class="text-gray-300 group-hover:text-white font-medium">Logout</span>
                            <p class="text-xs text-gray-500 group-hover:text-gray-400">Sign out safely</p>
                        </div>
                    </button>
                </form>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content p-8">
        <header class="flex justify-between items-center mb-8">
            <h1 class="text-5xl font-bold gradient-text">Create a New Room</h1>
            <div class="relative">
                <button id="profileBtn"
                    class="glass-effect p-2 rounded-full hover-glow transition-all duration-300 group">
                    <img src="<?= htmlspecialchars($profile_picture) ?>" alt="Profile"
                        class="w-10 h-10 rounded-full object-cover">
                </button>
                <div id="profileDropdown"
                    class="hidden profile-dropdown mt-4 w-72 glass-effect rounded-2xl shadow-2xl z-50 transform scale-95 opacity-0 transition-all duration-300">
                    <div class="p-6">
                        <div class="flex items-center gap-4 mb-6 pb-4 border-b border-gray-600">
                            <div
                                class="w-12 h-12 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center">
                                <img src="<?= htmlspecialchars($profile_picture) ?>" alt="Profile"
                                    class="w-full h-full rounded-full object-cover">
                            </div>
                            <div>
                                <h4 class="font-semibold"><?= htmlspecialchars($username) ?></h4>
                                <p class="text-sm text-gray-400"><?= htmlspecialchars($email) ?></p>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <a href="index.php"
                                class="w-full flex items-center gap-3 p-3 rounded-lg hover:bg-gray-700 transition-all duration-200 group"><i
                                    class="fas fa-user-circle text-blue-400 group-hover:scale-110 transition-transform"></i><span
                                    class="text-gray-300 group-hover:text-white">Profile</span></a>
                            <form action="logout.php" method="POST" class="w-full">
                                <input type="hidden" name="csrf_token"
                                    value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <button type="submit"
                                    class="w-full flex items-center gap-3 p-3 rounded-lg hover:bg-red-600 transition-all duration-200 group"><i
                                        class="fas fa-sign-out-alt text-red-400 group-hover:scale-110 transition-transform"></i><span
                                        class="text-gray-300 group-hover:text-white">Logout</span></button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <div class="max-w-3xl mx-auto">
            <div class="glass-effect rounded-2xl p-8 shadow-2xl glow-border">
                <form method="POST" action="createroom.php" class="space-y-6">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">

                    <?php if (!empty($error_message)): ?>
                        <div class="bg-red-500/20 border border-red-500/50 text-red-300 px-4 py-3 rounded-lg">
                            <?= htmlspecialchars($error_message) ?>
                        </div>
                    <?php endif; ?>

                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-300 mb-2">Room Name</label>
                        <div class="relative">
                            <i class="fas fa-chalkboard absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                            <input type="text" id="name" name="name" required
                                class="w-full pl-12 pr-4 py-3 glass-effect rounded-xl border-0 focus:ring-2 focus:ring-blue-500 transition-all"
                                placeholder="e.g., Advanced PHP Study Group">
                        </div>
                    </div>

                    <div>
                        <label for="description"
                            class="block text-sm font-medium text-gray-300 mb-2">Description</label>
                        <div class="relative">
                            <i class="fas fa-align-left absolute left-4 top-4 text-gray-400"></i>
                            <textarea id="description" name="description" rows="4"
                                class="w-full pl-12 pr-4 py-3 glass-effect rounded-xl border-0 focus:ring-2 focus:ring-blue-500 transition-all resize-none"
                                placeholder="What will this room be about?"></textarea>
                        </div>
                    </div>

                    <div>
                        <label for="participant_limit" class="block text-sm font-medium text-gray-300 mb-2">Participant
                            Limit (2-50)</label>
                        <div class="relative">
                            <i class="fas fa-users absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                            <input type="number" id="participant_limit" name="participant_limit" min="2" max="50"
                                value="10" required
                                class="w-full pl-12 pr-4 py-3 glass-effect rounded-xl border-0 focus:ring-2 focus:ring-blue-500 transition-all">
                        </div>
                    </div>

                    <div class="pt-4 flex items-center gap-4">
                        <a href="index.php"
                            class="w-1/2 text-center glass-effect hover:bg-gray-700 px-6 py-4 rounded-xl font-semibold text-white transition-all duration-300">
                            <i class="fas fa-arrow-left mr-2"></i>Back
                        </a>
                        <button type="submit"
                            class="w-full bg-gradient-to-r from-blue-500 to-purple-600 hover:from-blue-600 hover:to-purple-700 px-6 py-4 rounded-xl font-semibold text-white transition-all duration-300 hover:scale-105 hover:shadow-2xl hover:shadow-blue-500/50">
                            <i class="fas fa-rocket mr-2"></i>Launch Room
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const sidebar = document.getElementById('sidebar');
            const profileBtn = document.getElementById('profileBtn');
            const profileDropdown = document.getElementById('profileDropdown');

            profileBtn?.addEventListener('click', (e) => {
                e.stopPropagation();
                profileDropdown.classList.toggle('hidden');
                profileDropdown.classList.toggle('show');
            });

            mobileMenuBtn?.addEventListener('click', (e) => { e.stopPropagation(); sidebar?.classList.toggle('open'); });

            document.addEventListener('click', (e) => {
                if (sidebar && !sidebar.contains(e.target) && !mobileMenuBtn?.contains(e.target)) {
                    sidebar.classList.remove('open');
                }
                if (profileDropdown && !profileBtn.contains(e.target) && !profileDropdown.contains(e.target)) {
                    profileDropdown.classList.remove('show');
                    profileDropdown.classList.add('hidden');
                }
            });
            gsap.from(".glass-effect", { y: 50, opacity: 0, duration: 0.8, ease: "power2.out" });
        });
    </script>
</body>

</html>