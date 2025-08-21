// Handle Marks link click
document.getElementById('marksLink').addEventListener('click', function(e) {
    e.preventDefault();
    // Hide main content and profile page
    document.querySelector('.flex-1').classList.add('hidden');
    document.getElementById('profilePage').classList.add('hidden');
    // Show marks section
    document.getElementById('marksSection').classList.remove('hidden');
    // Render the table
    renderMarksTable();
});

function renderMarksTable() {
    // Clear existing content
    d3.select("#marksTable").html("");

    // Sample data
    const data = [
        { mark: "A", justification: "Excellent performance", internalRoute: "/details/a" },
        { mark: "B", justification: "Good with minor errors", internalRoute: "/details/b" },
        { mark: "C", justification: "Average understanding", internalRoute: "/details/c" }
    ];

    // Create table
    const table = d3.select("#marksTable")
        .append("table")
        .attr("class", "min-w-full bg-gray-800 text-white");

    // Create headers
    const headers = ["Mark", "Justification for this marking", "Internal Route"];
    table.append("thead")
        .append("tr")
        .selectAll("th")
        .data(headers)
        .enter()
        .append("th")
        .text(d => d)
        .attr("class", "py-3 px-6 text-left border-b border-gray-700");

    // Create rows
    const tbody = table.append("tbody");
    tbody.selectAll("tr")
        .data(data)
        .enter()
        .append("tr")
        .attr("class", "hover:bg-gray-750 transition")
        .each(function(d) {
            const row = d3.select(this);
            row.append("td")
                .text(d.mark)
                .attr("class", "py-4 px-6 border-b border-gray-700");
            row.append("td")
                .text(d.justification)
                .attr("class", "py-4 px-6 border-b border-gray-700");
            row.append("td")
                .append("a")
                .attr("href", d.internalRoute)
                .text(d.internalRoute)
                .attr("class", "text-blue-400 hover:text-blue-300");
        });
}