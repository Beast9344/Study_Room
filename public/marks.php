<?php
// marks.php
require '../config/config.php';
require '../utils/auth.php';

// Check admin status
$isAdmin = isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAdmin) {
    $mark = $conn->real_escape_string($_POST['mark']);
    $justification = $conn->real_escape_string($_POST['justification']);
    $route = $conn->real_escape_string($_POST['internal_route']);

    $stmt = $conn->prepare("INSERT INTO marks (mark, justification, internal_route) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $mark, $justification, $route);
    $stmt->execute();
    $stmt->close();
}

// Fetch marks from database
$marks = [];
$result = $conn->query("SELECT mark, justification, internal_route FROM marks");
while($row = $result->fetch_assoc()) $marks[] = $row;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marks Table</title>
    <script src="https://d3js.org/d3.v7.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen p-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold mb-6 text-gray-800">Assessment Marks</h1>
        
        <?php if($isAdmin): ?>
        <div class="bg-white p-6 rounded-lg shadow-md mb-8">
            <h2 class="text-xl font-semibold mb-4">Add New Mark (Admin Only)</h2>
            <form method="POST" class="space-y-4">
                <div>
                    <input type="text" name="mark" placeholder="Mark (e.g., A, B+)" 
                           class="w-full p-2 border rounded" required>
                </div>
                <div>
                    <textarea name="justification" placeholder="Justification" 
                              class="w-full p-2 border rounded" rows="3" required></textarea>
                </div>
                <div>
                    <input type="text" name="internal_route" placeholder="Internal Route" 
                           class="w-full p-2 border rounded" required>
                </div>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                    Add Mark
                </button>
            </form>
        </div>
        <?php endif; ?>

        <div id="marksTable" class="bg-white rounded-lg shadow-md overflow-hidden"></div>
    </div>

    <script>
        // D3.js Table Implementation
        const marksData = <?php echo json_encode($marks); ?>;

        function createTable() {
            const table = d3.select("#marksTable")
                .append("table")
                .classed("min-w-full divide-y divide-gray-200", true);

            // Header
            table.append("thead")
                .classed("bg-gray-50", true)
                .append("tr")
                .selectAll("th")
                .data(["Mark", "Justification", "Internal Route"])
                .enter()
                .append("th")
                .classed("px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase", true)
                .text(d => d);

            // Body
            const tbody = table.append("tbody")
                .classed("divide-y divide-gray-200", true);

            tbody.selectAll("tr")
                .data(marksData)
                .enter()
                .append("tr")
                .classed("hover:bg-gray-50", true)
                .html(d => `
                    <td class="px-6 py-4 whitespace-nowrap">${d.mark}</td>
                    <td class="px-6 py-4">${d.justification}</td>
                    <td class="px-6 py-4">
                        <a href="${d.internal_route}" class="text-blue-600 hover:text-blue-900">
                            ${d.internal_route}
                        </a>
                    </td>
                `);
        }

        createTable();
    </script>
</body>
</html>