<?php
// EE 470 Part 2, Section 4: decode Base64 URL parameters.
// Accept either ?data=ENCODED_TEXT or ?ENCODED_TEXT.
$encoded = $_GET['data'] ?? rawurldecode($_SERVER['QUERY_STRING'] ?? '');

// Decode the URL message back into a normal query string.
$decoded = is_string($encoded) ? base64_decode($encoded, true) : false;
if ($decoded === false) {
    die("Invalid Base64 message");
}

// Extract nodeId, nodeTemp, nodeHum, and timeReceived.
parse_str($decoded, $_GET);

// For the Base64 section, the professor requires an included time.
if (empty($_GET['timeReceived']) || !is_string($_GET['timeReceived'])) {
    die("Timestamp is required");
}

// Display the decoded parameters for the assignment screenshot.
echo "Decoded data:<br>";
foreach ($_GET as $key => $value) {
    if (!is_string($value)) {
        die("Invalid sensor data");
    }
    echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . " = "
       . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . "<br>";
}

// Reuse the existing database validation and insertion.
require "insert.php";
?>
