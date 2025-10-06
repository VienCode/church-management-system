<?php
$mysqli = new mysqli("localhost", "root", "", "ucf_database");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

return $mysqli;
