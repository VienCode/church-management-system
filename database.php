<?php
$mysqli = new mysqli("localhost", "root", "", "your_database_name");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

return $mysqli;
