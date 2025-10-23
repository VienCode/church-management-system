<?php
$host = "localhost";
$username = "root";
$password = ""; 
$database = "ucf_database";

$mysqli = new mysqli($host, $username, $password, $database);

if ($mysqli->connect_error) {
    die("Database connection failed: " . $mysqli->connect_error);
}

// Add these 4 lines so backup_manager.php works properly
$db_host = $host;
$db_user = $username;
$db_pass = $password;
$db_name = $database;

return $mysqli;
?>
