<?php
require '../config/config.php';
require '../utils/auth.php';

// Regenerate session ID periodically
if (!isset($_SESSION['created'])) {
    session_regenerate_id(true);
    $_SESSION['created'] = time();
} elseif (time() - $_SESSION['created'] > 1800) {
    session_regenerate_id(true);
    $_SESSION['created'] = time();
}

// Check authentication
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch user data using prepared statements
try {
    $user_id = $_SESSION['user'];
    $sql = "SELECT id, username, email, role, profile_picture FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        session_destroy();
        header("Location: login.php");
        exit();
    }

    $stmt->bind_result($id, $username, $email, $role, $profile_picture);
    $stmt->fetch();
    $stmt->close();
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    die("Error loading user data");
}

// Fetch tasks
$tasks = [];
$sql = "SELECT id, title, description, progress, status FROM tasks WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $tasks[] = $row;
}
$stmt->close();

// Fetch study rooms
$rooms = [];
$sql = "SELECT r.*, u.username as owner_name 
        FROM rooms r 
        JOIN users u ON r.owner_id = u.id 
        ORDER BY created_at DESC";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $rooms[] = $row;
    }
}

// Fetch suggested rooms (rooms with available space)
$suggested_rooms = [];
$sql = "SELECT r.*, u.username as owner_name 
        FROM rooms r 
        JOIN users u ON r.owner_id = u.id 
        WHERE r.current_participants < r.participant_limit
        ORDER BY created_at DESC 
        LIMIT 5";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $suggested_rooms[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta charset="UTF-8">
    <title>User Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://d3js.org/d3.v7.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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

    <div class="main-content p-8">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-5xl font-bold gradient-text">KNOWLEDGE IS POWER</h1>

            <div class="flex items-center gap-4">
                <div class="relative">
                    <button class="glass-effect p-3 rounded-full hover-glow transition-all duration-300 group">
                        <i class="fas fa-bell text-xl text-blue-400 group-hover:text-blue-300"></i>
                        <span class="absolute -top-1 -right-1 w-3 h-3 bg-red-500 rounded-full notification-dot"></span>
                    </button>
                </div>

                <div class="relative">
                    <button id="profileBtn"
                        class="glass-effect p-3 rounded-full hover-glow transition-all duration-300 group">
                        <i class="fas fa-user text-xl text-purple-400 group-hover:text-purple-300"></i>
                    </button>

                    <div id="profileDropdown"
                        class="hidden absolute right-0 mt-4 w-72 glass-effect rounded-2xl shadow-2xl z-50 transform scale-95 opacity-0 transition-all duration-300">
                        <div class="p-6">
                            <div class="flex items-center gap-4 mb-6 pb-4 border-b border-gray-600">
                                <div
                                    class="w-12 h-12 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center">
                                    <i class="fas fa-user text-white"></i>
                                </div>
                                <div>
                                    <h4 class="font-semibold"><?= htmlspecialchars($username) ?></h4>
                                    <p class="text-sm text-gray-400"><?= htmlspecialchars($email) ?></p>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <button onclick="showProfilePage()"
                                    class="w-full flex items-center gap-3 p-3 rounded-lg hover:bg-gray-700 transition-all duration-200 group">
                                    <i
                                        class="fas fa-user-circle text-blue-400 group-hover:scale-110 transition-transform"></i>
                                    <span class="text-gray-300 group-hover:text-white">Profile</span>
                                </button>

                                <button
                                    class="w-full flex items-center gap-3 p-3 rounded-lg hover:bg-gray-700 transition-all duration-200 group">
                                    <i
                                        class="fas fa-key text-yellow-400 group-hover:scale-110 transition-transform"></i>
                                    <span class="text-gray-300 group-hover:text-white">Change Password</span>
                                </button>

                                <button
                                    class="w-full flex items-center gap-3 p-3 rounded-lg hover:bg-gray-700 transition-all duration-200 group">
                                    <i class="fas fa-cog text-gray-400 group-hover:scale-110 transition-transform"></i>
                                    <span class="text-gray-300 group-hover:text-white">Settings</span>
                                </button>

                                <button
                                    class="w-full flex items-center gap-3 p-3 rounded-lg hover:bg-red-600 transition-all duration-200 group">
                                    <i
                                        class="fas fa-sign-out-alt text-red-400 group-hover:scale-110 transition-transform"></i>
                                    <span class="text-gray-300 group-hover:text-white">Logout</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mb-12">
            <div class="flex gap-4 mb-8">
                <a href="createroom.php" class="bg-blue-600 px-6 py-3 rounded-lg hover:bg-blue-700 transition">
                    Create Room
                </a>
                <button class="bg-gray-800 px-6 py-3 rounded-lg hover:bg-gray-700 transition">
                    Join Feature Room
                </button>
            </div>

            <div class="space-y-6">
                <?php if (count($suggested_rooms) > 0): ?>
                    <div class="mb-8">
                        <h2 class="text-2xl mb-4">Suggested Rooms</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?php foreach ($suggested_rooms as $room): ?>
                                <div class="bg-gray-800 p-6 rounded-lg hover:bg-gray-750 transition">
                                    <h3 class="text-xl font-bold"><?= htmlspecialchars($room['name']) ?></h3>
                                    <p class="text-gray-400 mt-2"><?= htmlspecialchars($room['description']) ?></p>
                                    <div class="flex items-center justify-between mt-4">
                                        <span class="text-sm text-blue-400">
                                            <?= $room['current_participants'] ?>/<?= $room['participant_limit'] ?> participants
                                        </span>
                                        <?php if ($room['current_participants'] < $room['participant_limit']): ?>
                                            <a href="joinroom.php?id=<?= $room['id'] ?>"
                                                class="bg-green-600 hover:bg-green-700 px-4 py-2 rounded text-sm">
                                                Join Room
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <h2 class="text-2xl mb-4">All Study Rooms</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php foreach ($rooms as $room): ?>
                        <div class="bg-gray-800 p-6 rounded-lg hover:bg-gray-750 transition">
                            <div class="flex items-center justify-between">
                                <h3 class="text-xl font-bold"><?= htmlspecialchars($room['name']) ?></h3>
                                <span class="text-sm text-gray-400">
                                    <?= date('M j, Y', strtotime($room['created_at'])) ?>
                                </span>
                            </div>
                            <p class="text-gray-400 mt-2"><?= htmlspecialchars($room['description']) ?></p>
                            <div class="flex items-center justify-between mt-4">
                                <div class="flex items-center">
                                    <span class="text-sm text-blue-400 mr-4">
                                        Owner: <?= htmlspecialchars($room['owner_name']) ?>
                                    </span>
                                    <span class="text-sm text-gray-400">
                                        <?= $room['current_participants'] ?>/<?= $room['participant_limit'] ?> participants
                                    </span>
                                </div>
                                <?php if ($room['current_participants'] < $room['participant_limit']): ?>
                                    <a href="joinroom.php?id=<?= $room['id'] ?>"
                                        class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded text-sm">
                                        Join
                                    </a>
                                <?php else: ?>
                                    <span class="text-sm text-red-400">Full</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div id="notesModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-gray-800 rounded-lg w-full max-w-2xl max-h-[90vh] flex flex-col">
            <div class="flex justify-between items-center p-4 border-b border-gray-700">
                <h3 class="text-xl font-bold">
                    <i class="fas fa-book mr-2"></i>Study Notes
                </h3>
                <button onclick="closeNotesModal()" class="text-gray-400 hover:text-white">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto p-4">
                <div class="mb-4">
                    <input type="text" id="noteTitle" class="w-full mb-2 p-2 bg-gray-700 rounded"
                        placeholder="Note Title">
                    <textarea id="noteContent" class="w-full h-64 p-3 bg-gray-700 rounded-lg font-mono text-sm"
                        placeholder="Write your notes here..."></textarea>
                </div>

                <div id="savedNotes" class="space-y-3">
                </div>
            </div>

            <div class="flex justify-between items-center p-4 border-t border-gray-700">
                <div class="flex gap-2">
                    <select id="noteCategory" class="bg-gray-700 rounded px-2 py-1 text-sm">
                        <option value="study">📚 Study Note</option>
                        <option value="important">❗ Important Note</option>
                        <option value="personal">🔒 Personal Note</option>
                        <option value="meeting">👥 Meeting Notes</option>
                    </select>
                    <button onclick="saveNote()" class="bg-green-600 hover:bg-green-700 px-4 py-2 rounded">
                        <i class="fas fa-save mr-2"></i>Save
                    </button>
                </div>
                <button onclick="closeNotesModal()" class="bg-gray-700 hover:bg-gray-600 px-4 py-2 rounded">
                    Close
                </button>
            </div>
        </div>
    </div>

    <div id="taskModal" class="hidden fixed inset-0 modal-backdrop flex items-center justify-center z-50">
        <div
            class="glass-effect rounded-3xl w-full max-w-4xl max-h-[90vh] flex flex-col transform scale-95 opacity-0 transition-all duration-300">
            <div class="flex justify-between items-center p-6 border-b border-gray-600">
                <div class="flex items-center gap-3">
                    <div
                        class="w-10 h-10 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center">
                        <i class="fas fa-tasks text-white"></i>
                    </div>
                    <h3 class="text-2xl font-bold gradient-text">Task Manager</h3>
                </div>
                <button onclick="closeTaskModal()"
                    class="text-gray-400 hover:text-white hover:bg-gray-700 p-2 rounded-full transition-all">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto p-6">
                <div class="glass-effect p-6 rounded-2xl mb-6">
                    <h4 class="text-lg font-semibold mb-4 flex items-center gap-2">
                        <i class="fas fa-plus-circle text-green-400"></i>
                        Create New Task
                    </h4>

                    <div class="space-y-4">
                        <div class="relative">
                            <i class="fas fa-heading absolute left-3 top-3 text-gray-400"></i>
                            <input type="text" id="taskTitle"
                                class="w-full pl-10 pr-4 py-3 glass-effect rounded-xl border-0 focus:ring-2 focus:ring-blue-500 transition-all"
                                placeholder="Enter task title...">
                        </div>

                        <div class="relative">
                            <i class="fas fa-align-left absolute left-3 top-3 text-gray-400"></i>
                            <textarea id="taskDescription"
                                class="w-full pl-10 pr-4 py-3 h-32 glass-effect rounded-xl border-0 focus:ring-2 focus:ring-blue-500 transition-all resize-none"
                                placeholder="Task description..."></textarea>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="relative">
                                <i class="fas fa-calendar absolute left-3 top-3 text-gray-400"></i>
                                <input type="datetime-local" id="taskDeadline"
                                    class="w-full pl-10 pr-4 py-3 glass-effect rounded-xl border-0 focus:ring-2 focus:ring-blue-500 transition-all">
                            </div>

                            <div class="relative">
                                <i class="fas fa-flag absolute left-3 top-3 text-gray-400"></i>
                                <select id="taskPriority"
                                    class="w-full pl-10 pr-4 py-3 glass-effect rounded-xl border-0 focus:ring-2 focus:ring-blue-500 transition-all">
                                    <option value="low">🟢 Low Priority</option>
                                    <option value="medium">🟡 Medium Priority</option>
                                    <option value="high">🔴 High Priority</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium mb-2 text-gray-300">Progress</label>
                                <div class="flex items-center gap-4">
                                    <input type="range" id="taskProgress" min="0" max="100" value="0"
                                        class="flex-1 h-2 bg-gray-700 rounded-lg appearance-none cursor-pointer">
                                    <span id="progressValue"
                                        class="text-sm font-semibold text-blue-400 min-w-[40px]">0%</span>
                                </div>
                            </div>

                            <div class="relative">
                                <i class="fas fa-chart-line absolute left-3 top-9 text-gray-400"></i>
                                <label class="block text-sm font-medium mb-2 text-gray-300">Status</label>
                                <select id="taskStatus"
                                    class="w-full pl-10 pr-4 py-3 glass-effect rounded-xl border-0 focus:ring-2 focus:ring-blue-500 transition-all">
                                    <option value="not_started">🔴 Not Started</option>
                                    <option value="in_progress">🟡 In Progress</option>
                                    <option value="completed">🟢 Completed</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="taskList" class="space-y-4">
                </div>
            </div>

            <div class="flex justify-between items-center p-6 border-t border-gray-600">
                <div class="flex gap-3">
                    <button onclick="saveTask()"
                        class="bg-gradient-to-r from-blue-500 to-purple-600 hover:from-blue-600 hover:to-purple-700 px-6 py-3 rounded-xl font-medium transition-all duration-300 hover:scale-105">
                        <i class="fas fa-save mr-2"></i>Save Task
                    </button>
                    <button onclick="clearTaskForm()"
                        class="glass-effect hover:bg-gray-700 px-6 py-3 rounded-xl font-medium transition-all duration-300">
                        <i class="fas fa-eraser mr-2"></i>Clear
                    </button>
                </div>
                <button onclick="closeTaskModal()"
                    class="glass-effect hover:bg-gray-700 px-6 py-3 rounded-xl font-medium transition-all duration-300">
                    Close
                </button>
            </div>
        </div>
    </div>


    <div id="profilePage"
        class="hidden fixed top-0 right-0 w-96 h-screen glass-effect border-l border-gray-600 z-50 overflow-y-auto transform translate-x-full transition-all duration-500">
        <button onclick="hideProfilePage()"
            class="m-4 glass-effect hover:bg-gray-700 p-3 rounded-xl transition-all duration-300 group">
            <i class="fas fa-arrow-left mr-2 group-hover:scale-110 transition-transform"></i>
            Back to Dashboard
        </button>

        <div class="p-6">
            <div class="profile-card p-6 mb-6 text-center">
                <div class="relative inline-block mb-4">
                    <div class="w-24 h-24 bg-white rounded-full flex items-center justify-center mx-auto">
                        <i class="fas fa-user text-3xl text-gray-600"></i>
                    </div>
                    <button onclick="openUploadModal()"
                        class="absolute bottom-0 right-0 bg-blue-500 hover:bg-blue-600 p-2 rounded-full transition-all duration-300 hover:scale-110">
                        <i class="fas fa-camera text-white"></i>
                    </button>
                </div>

                <h2 class="text-2xl font-bold text-white mb-2"><?= htmlspecialchars($username) ?></h2>
                <p class="text-gray-200 opacity-90"><?= htmlspecialchars($email) ?></p>
            </div>

            <div class="glass-effect p-6 rounded-2xl mb-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
                    <i class="fas fa-info-circle text-blue-400"></i>
                    Profile Details
                </h3>
                <div class="space-y-4">
                    <div class="flex items-center gap-3">
                        <i class="fas fa-user text-gray-400"></i>
                        <div>
                            <p class="text-sm text-gray-400">Username</p>
                            <p class="font-medium"><?= htmlspecialchars($username) ?></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <i class="fas fa-envelope text-gray-400"></i>
                        <div>
                            <p class="text-sm text-gray-400">Email</p>
                            <p class="font-medium"><?= htmlspecialchars($email) ?></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <i class="fas fa-shield-alt text-gray-400"></i>
                        <div>
                            <p class="text-sm text-gray-400">Role</p>
                            <p class="font-medium"><?= htmlspecialchars($role) ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="glass-effect p-6 rounded-2xl">
                <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
                    <i class="fas fa-clock text-green-400"></i>
                    Recent Activities
                </h3>
                <div class="space-y-3">
                    <div class="flex items-center gap-3 p-3 bg-gray-800/50 rounded-xl">
                        <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                            <i class="fas fa-plus text-white text-sm"></i>
                        </div>
                        <div>
                            <p class="font-medium">Joined "Web Development"</p>
                            <p class="text-sm text-gray-400">2 hours ago</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 p-3 bg-gray-800/50 rounded-xl">
                        <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                            <i class="fas fa-check text-white text-sm"></i>
                        </div>
                        <div>
                            <p class="font-medium">Completed "Algorithm Study"</p>
                            <p class="text-sm text-gray-400">1 day ago</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="uploadModal" class="hidden fixed inset-0 modal-backdrop flex items-center justify-center z-50">
        <div class="glass-effect rounded-3xl w-full max-w-md transform scale-95 opacity-0 transition-all duration-300">
            <div class="p-6">
                <h3 class="text-xl font-bold mb-4 flex items-center gap-2">
                    <i class="fas fa-camera text-blue-400"></i>
                    Upload Profile Picture
                </h3>

                <form class="space-y-4">
                    <div
                        class="border-2 border-dashed border-gray-600 rounded-xl p-8 text-center hover:border-blue-400 transition-all duration-300">
                        <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-4"></i>
                        <p class="text-gray-400 mb-2">Drop your image here or</p>
                        <input type="file" name="profile_picture" class="hidden" id="fileInput" accept="image/*">
                        <button type="button" onclick="document.getElementById('fileInput').click()"
                            class="bg-blue-500 hover:bg-blue-600 px-4 py-2 rounded-lg transition-all duration-300">
                            Choose File
                        </button>
                    </div>

                    <div class="flex justify-end gap-3">
                        <button type="button" onclick="closeUploadModal()"
                            class="glass-effect hover:bg-gray-700 px-6 py-3 rounded-xl transition-all duration-300">
                            Cancel
                        </button>
                        <button type="submit"
                            class="bg-gradient-to-r from-blue-500 to-purple-600 hover:from-blue-600 hover:to-purple-700 px-6 py-3 rounded-xl transition-all duration-300">
                            <i class="fas fa-upload mr-2"></i>Upload
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
// Global variables
let currentNoteId = null;
let currentTaskId = null;

// Mobile menu functionality
const mobileMenuBtn = document.getElementById('mobileMenuBtn');
const sidebar = document.getElementById('sidebar');

mobileMenuBtn.addEventListener('click', () => {
    sidebar.classList.toggle('active');
});

// Close mobile menu when clicking outside
document.addEventListener('click', (e) => {
    if (!sidebar.contains(e.target) && !mobileMenuBtn.contains(e.target)) {
        sidebar.classList.remove('active');
    }
});

// Notes Modal Functions
function openNotesModal() {
    const modal = document.getElementById('notesModal');
    modal.classList.remove('hidden');
    loadNotes();
}

function closeNotesModal() {
    const modal = document.getElementById('notesModal');
    modal.classList.add('hidden');
    resetNotesForm();
}

function resetNotesForm() {
    currentNoteId = null;
    document.getElementById('noteTitle').value = '';
    document.getElementById('noteContent').value = '';
    document.getElementById('noteCategory').value = 'study';
}

async function loadNotes() {
    try {
        const response = await fetch('fetchnotes.php');
        if (!response.ok) {
            throw new Error('Failed to fetch notes');
        }
        const notes = await response.json();
        
        const notesContainer = document.getElementById('savedNotes');
        notesContainer.innerHTML = '';
        
        if (notes.length === 0) {
            notesContainer.innerHTML = '<p class="text-gray-400 text-center py-4">No notes saved yet</p>';
            return;
        }
        
        notes.forEach(note => {
            const noteElement = document.createElement('div');
            noteElement.className = 'bg-gray-700 p-4 rounded-lg mb-3 hover:bg-gray-600 transition-all duration-200';
            noteElement.innerHTML = `
                <div class="flex justify-between items-start">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-2">
                            <h5 class="font-semibold">${escapeHtml(note.title)}</h5>
                            <span class="text-xs px-2 py-1 rounded-full ${getCategoryClass(note.category)}">
                                ${getCategoryIcon(note.category)} ${note.category}
                            </span>
                        </div>
                        <p class="text-gray-300 text-sm mb-2 line-clamp-2">${escapeHtml(note.content.substring(0, 150))}${note.content.length > 150 ? '...' : ''}</p>
                        <div class="flex items-center gap-4 text-xs text-gray-400">
                            <span><i class="fas fa-calendar mr-1"></i>${formatDate(note.created_at)}</span>
                            <span><i class="fas fa-edit mr-1"></i>${formatDate(note.updated_at)}</span>
                        </div>
                    </div>
                    <div class="flex gap-2 ml-4">
                        <button onclick="editNote(${note.id}, '${escapeHtml(note.title).replace(/'/g, "\\'")}', '${escapeHtml(note.content).replace(/'/g, "\\'")}', '${note.category}')" 
                                class="text-blue-400 hover:text-blue-300 hover:bg-blue-500/20 p-2 rounded-full transition-all">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="deleteNote(${note.id})" 
                                class="text-red-400 hover:text-red-300 hover:bg-red-500/20 p-2 rounded-full transition-all">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `;
            notesContainer.appendChild(noteElement);
        });
    } catch (error) {
        console.error('Error loading notes:', error);
        document.getElementById('savedNotes').innerHTML = '<p class="text-red-400 text-center py-4">Error loading notes</p>';
    }
}

async function saveNote() {
    const title = document.getElementById('noteTitle').value.trim();
    const content = document.getElementById('noteContent').value.trim();
    const category = document.getElementById('noteCategory').value;

    if (!title || !content) {
        alert('Please fill in both title and content');
        return;
    }

    const noteData = {
        id: currentNoteId,
        title: title,
        content: content,
        category: category
    };

    try {
        const response = await fetch('savenote.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(noteData)
        });

        const result = await response.json();
        
        if (response.ok && result.success) {
            loadNotes();
            resetNotesForm();
            showNotification('Note saved successfully!', 'success');
        } else {
            throw new Error(result.message || 'Failed to save note');
        }
    } catch (error) {
        console.error('Error saving note:', error);
        showNotification('Error saving note', 'error');
    }
}

function editNote(id, title, content, category) {
    currentNoteId = id;
    document.getElementById('noteTitle').value = title;
    document.getElementById('noteContent').value = content;
    document.getElementById('noteCategory').value = category;
}

async function deleteNote(id) {
    if (!confirm('Are you sure you want to delete this note?')) {
        return;
    }

    try {
        const response = await fetch('deletenote.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: id })
        });

        const result = await response.json();

        if (response.ok && result.success) {
            loadNotes();
            showNotification('Note deleted successfully!', 'success');
        } else {
            throw new Error(result.message || 'Failed to delete note');
        }
    } catch (error) {
        console.error('Error deleting note:', error);
        showNotification('Error deleting note', 'error');
    }
}

// Task Modal Functions
function openTaskModal() {
    const modal = document.getElementById('taskModal');
    modal.classList.remove('hidden');
    gsap.to(modal.firstElementChild, {
        scale: 1,
        opacity: 1,
        duration: 0.4,
        ease: "power2.out"
    });
    loadTasks();
}

function closeTaskModal() {
    const modal = document.getElementById('taskModal');
    const modalContent = modal.firstElementChild;
    gsap.to(modalContent, {
        scale: 0.95,
        opacity: 0,
        duration: 0.3,
        ease: "power2.in",
        onComplete: () => {
            modal.classList.add('hidden');
            resetTaskForm();
        }
    });
}

function resetTaskForm() {
    currentTaskId = null;
    document.getElementById('taskTitle').value = '';
    document.getElementById('taskDescription').value = '';
    document.getElementById('taskDeadline').value = '';
    document.getElementById('taskPriority').value = 'low';
    document.getElementById('taskProgress').value = '0';
    document.getElementById('progressValue').textContent = '0%';
    document.getElementById('taskStatus').value = 'not_started';
}

// Update progress value display
document.getElementById('taskProgress').addEventListener('input', function () {
    document.getElementById('progressValue').textContent = this.value + '%';
});

async function loadTasks() {
    try {
        const response = await fetch('fetchtasks.php');
        if (!response.ok) {
            throw new Error('Failed to fetch tasks');
        }
        const tasks = await response.json();

        const taskContainer = document.getElementById('taskList');
        taskContainer.innerHTML = '';

        if (tasks.length === 0) {
            taskContainer.innerHTML = '<p class="text-gray-400 text-center py-4">No tasks created yet</p>';
            return;
        }

        tasks.forEach(task => {
            const taskElement = document.createElement('div');
            taskElement.className = 'glass-effect p-4 rounded-2xl hover-glow transition-all duration-300';
            taskElement.innerHTML = `
                <div class="flex justify-between items-start">
                    <div class="flex-1">
                        <div class="flex items-center gap-3 mb-2">
                            <h4 class="font-semibold text-lg">${escapeHtml(task.title)}</h4>
                            <span class="px-3 py-1 rounded-full text-xs font-medium text-white ${getPriorityClass(task.priority)}">
                                ${task.priority.toUpperCase()}
                            </span>
                        </div>
                        <p class="text-gray-400 text-sm mb-3">${escapeHtml(task.description)}</p>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-4 text-sm">
                                <span class="flex items-center gap-1">
                                    <i class="fas fa-calendar text-blue-400"></i>
                                    ${task.deadline ? formatDate(task.deadline) : 'No deadline'}
                                </span>
                                <span class="flex items-center gap-1">
                                    <i class="fas fa-circle ${getStatusColor(task.status)}"></i>
                                    ${getStatusLabel(task.status)}
                                </span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-24 h-2 bg-gray-700 rounded-full">
                                    <div class="h-2 task-progress-bar rounded-full" style="width: ${task.progress}%"></div>
                                </div>
                                <span class="text-sm font-medium">${task.progress}%</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-2 ml-4">
                        <button onclick="editTask(${task.id}, '${escapeHtml(task.title).replace(/'/g, "\\'")}', '${escapeHtml(task.description).replace(/'/g, "\\'")}', '${task.deadline || ''}', '${task.priority}', ${task.progress}, '${task.status}')" class="text-blue-400 hover:text-blue-300 hover:bg-blue-500/20 p-2 rounded-full transition-all">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="deleteTask(${task.id})" class="text-red-400 hover:text-red-300 hover:bg-red-500/20 p-2 rounded-full transition-all">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>`;
            taskContainer.appendChild(taskElement);
        });
    } catch (error) {
        console.error('Error loading tasks:', error);
        document.getElementById('taskList').innerHTML = '<p class="text-red-400 text-center py-4">Error loading tasks</p>';
    }
}

function editTask(id, title, description, deadline, priority, progress, status) {
    currentTaskId = id;
    document.getElementById('taskTitle').value = title;
    document.getElementById('taskDescription').value = description;
    document.getElementById('taskDeadline').value = deadline ? deadline.substring(0, 16) : '';
    document.getElementById('taskPriority').value = priority;
    document.getElementById('taskProgress').value = progress;
    document.getElementById('progressValue').textContent = progress + '%';
    document.getElementById('taskStatus').value = status;
}

async function saveTask() {
    const title = document.getElementById('taskTitle').value.trim();
    const description = document.getElementById('taskDescription').value.trim();

    if (!title) {
        alert('Please enter a task title');
        return;
    }

    const taskData = {
        id: currentTaskId,
        title: title,
        description: description,
        deadline: document.getElementById('taskDeadline').value,
        priority: document.getElementById('taskPriority').value,
        progress: document.getElementById('taskProgress').value,
        status: document.getElementById('taskStatus').value
    };

    try {
        const response = await fetch('savetask.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(taskData)
        });

        const result = await response.json();

        if (response.ok && result.success) {
            loadTasks();
            resetTaskForm();
            showNotification('Task saved successfully!', 'success');
        } else {
            throw new Error(result.message || 'Failed to save task');
        }
    } catch (error) {
        console.error('Error saving task:', error);
        showNotification('Error saving task', 'error');
    }
}

async function deleteTask(id) {
    if (!confirm('Are you sure you want to delete this task?')) {
        return;
    }

    try {
        const response = await fetch('deletetask.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: id })
        });

        const result = await response.json();

        if (response.ok && result.success) {
            loadTasks();
            showNotification('Task deleted successfully!', 'success');
        } else {
            throw new Error(result.message || 'Failed to delete task');
        }
    } catch (error) {
        console.error('Error deleting task:', error);
        showNotification('Error deleting task', 'error');
    }
}

function clearTaskForm() {
    resetTaskForm();
}

// Profile functionality
const profileBtn = document.getElementById('profileBtn');
const profileDropdown = document.getElementById('profileDropdown');

if (profileBtn && profileDropdown) {
    profileBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        toggleDropdown();
    });
}

function toggleDropdown() {
    const isHidden = profileDropdown.classList.contains('hidden');
    if (isHidden) {
        profileDropdown.classList.remove('hidden');
        gsap.to(profileDropdown, {
            scale: 1,
            opacity: 1,
            duration: 0.3,
            ease: "power2.out"
        });
    } else {
        gsap.to(profileDropdown, {
            scale: 0.95,
            opacity: 0,
            duration: 0.2,
            ease: "power2.in",
            onComplete: () => {
                profileDropdown.classList.add('hidden');
            }
        });
    }
}

function showProfilePage() {
    const profilePage = document.getElementById('profilePage');
    profilePage.classList.remove('hidden');
    gsap.to(profilePage, {
        x: 0,
        duration: 0.5,
        ease: "power2.out"
    });
    toggleDropdown();
}

function hideProfilePage() {
    const profilePage = document.getElementById('profilePage');
    gsap.to(profilePage, {
        x: '100%',
        duration: 0.5,
        ease: "power2.in",
        onComplete: () => {
            profilePage.classList.add('hidden');
        }
    });
}

function openUploadModal() {
    const modal = document.getElementById('uploadModal');
    modal.classList.remove('hidden');
    gsap.to(modal.firstElementChild, {
        scale: 1,
        opacity: 1,
        duration: 0.4,
        ease: "power2.out"
    });
}

function closeUploadModal() {
    const modal = document.getElementById('uploadModal');
    const modalContent = modal.firstElementChild;
    gsap.to(modalContent, {
        scale: 0.95,
        opacity: 0,
        duration: 0.3,
        ease: "power2.in",
        onComplete: () => {
            modal.classList.add('hidden');
        }
    });
}

// Close dropdowns when clicking outside
document.addEventListener('click', (e) => {
    if (profileDropdown && profileBtn && 
        !profileDropdown.contains(e.target) && !profileBtn.contains(e.target)) {
        gsap.to(profileDropdown, {
            scale: 0.95,
            opacity: 0,
            duration: 0.2,
            ease: "power2.in",
            onComplete: () => {
                profileDropdown.classList.add('hidden');
            }
        });
    }
});

// Utility Functions
function escapeHtml(unsafe) {
    return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function formatDate(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diffTime = Math.abs(now - date);
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    
    if (diffDays === 1) {
        return 'Yesterday';
    } else if (diffDays < 7) {
        return `${diffDays} days ago`;
    } else {
        return date.toLocaleDateString();
    }
}

// Notes helper functions
function getCategoryIcon(category) {
    const icons = {
        study: '📚',
        important: '❗',
        personal: '🔒',
        meeting: '👥'
    };
    return icons[category] || '📝';
}

function getCategoryClass(category) {
    const classes = {
        study: 'bg-blue-500/20 text-blue-400',
        important: 'bg-red-500/20 text-red-400',
        personal: 'bg-purple-500/20 text-purple-400',
        meeting: 'bg-green-500/20 text-green-400'
    };
    return classes[category] || 'bg-gray-500/20 text-gray-400';
}

// Task helper functions
function getPriorityClass(priority) {
    const classes = {
        low: 'bg-green-500',
        medium: 'bg-yellow-500',
        high: 'bg-red-500'
    };
    return classes[priority] || 'bg-gray-500';
}

function getStatusColor(status) {
    const colors = {
        not_started: 'text-red-400',
        in_progress: 'text-yellow-400',
        completed: 'text-green-400'
    };
    return colors[status] || 'text-gray-400';
}

function getStatusLabel(status) {
    const labels = {
        not_started: 'Not Started',
        in_progress: 'In Progress',
        completed: 'Completed'
    };
    return labels[status] || 'Unknown';
}

// Notification system
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 px-6 py-4 rounded-lg shadow-lg z-50 transform translate-x-full transition-all duration-300 ${
        type === 'success' ? 'bg-green-600 text-white' : 
        type === 'error' ? 'bg-red-600 text-white' : 
        'bg-blue-600 text-white'
    }`;
    notification.innerHTML = `
        <div class="flex items-center gap-3">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>
            <span>${message}</span>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Slide in
    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
    }, 100);
    
    // Slide out and remove
    setTimeout(() => {
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 300);
    }, 3000);
}

// Initialize animations on page load
document.addEventListener('DOMContentLoaded', () => {
    // Initialize animations
    gsap.from(".main-content", {
        duration: 0.5,
        opacity: 0,
        x: 50
    });
    gsap.from(".gradient-text", {
        y: -20,
        opacity: 0,
        duration: 0.8,
        ease: "power2.out"
    });
    
    // Initialize task progress display
    const progressInput = document.getElementById('taskProgress');
    if (progressInput) {
        progressInput.addEventListener('input', function() {
            document.getElementById('progressValue').textContent = this.value + '%';
        });
    }
});

// Handle escape key to close modals
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        // Close any open modals
        const modals = ['notesModal', 'taskModal', 'uploadModal'];
        modals.forEach(modalId => {
            const modal = document.getElementById(modalId);
            if (modal && !modal.classList.contains('hidden')) {
                if (modalId === 'notesModal') closeNotesModal();
                if (modalId === 'taskModal') closeTaskModal();
                if (modalId === 'uploadModal') closeUploadModal();
            }
        });
        
        // Close profile page
        const profilePage = document.getElementById('profilePage');
        if (profilePage && !profilePage.classList.contains('hidden')) {
            hideProfilePage();
        }
    }
});
    </script>
</body>

</html>