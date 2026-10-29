<?php
// Safe to put on GitHub. Copy to connection.php and fill in the real credentials.
$servername = "localhost";
$username = "YOUR_DB_USERNAME";
$password = "YOUR_DB_PASSWORD";
$dbname = "u999011970_MehdiKacem";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Could not connect to the database.");
}
$conn->set_charset("utf8mb4");
