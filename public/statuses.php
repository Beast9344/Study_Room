<?php
// statuses.php

// Your connection to the database and other necessary setup
require 'config.php';

// Load CSV data (you could manually upload the CSV or directly use a URL)
$data = array_map('str_getcsv', file('https://raw.githubusercontent.com/connect2robiul/CSVfile/master/RafigCovid_19.csv'));
$headers = array_shift($data);  // Remove the header row

// Assuming the 'language' column exists in the CSV and is at the 5th index
$languageIndex = 4;  // Update this index if the column is elsewhere in your CSV
$language_counts = [];

// Process the languages and their counts
foreach ($data as $row) {
    $language = $row[$languageIndex];
    if (isset($language_counts[$language])) {
        $language_counts[$language]++;
    } else {
        $language_counts[$language] = 1;
    }
}

// Prepare color mapping
$language_colors = [
    'English' => 'red',
    'Spanish' => 'blue',
    'French' => 'green',
    'Italian' => 'purple',
    'German' => 'orange',
    'Other' => 'gray'
];

// Output the color-coded statuses
echo "<h1>Covid-19 Language Statuses</h1>";
echo "<div class='language-statuses'>";
foreach ($language_counts as $language => $count) {
    $color = isset($language_colors[$language]) ? $language_colors[$language] : $language_colors['Other'];
    echo "<div style='background-color: $color; padding: 10px; margin: 5px;'>$language: $count</div>";
}
echo "</div>";

// Display the histogram below
echo "<h2>Language Histogram</h2>";
echo "<div class='histogram'>";
foreach ($language_counts as $language => $count) {
    echo "<div>$language: " . str_repeat("|", $count) . "</div>";
}
echo "</div>";

?>
