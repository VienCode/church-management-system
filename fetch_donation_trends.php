<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN, ROLE_ACCOUNTANT]);

$interval = $_GET['interval'] ?? 'daily';

switch ($interval) {
    case 'weekly':
        $sql = "
            SELECT YEARWEEK(donation_date, 1) AS period, 
                   CONCAT('Week ', WEEK(donation_date, 1)) AS label, 
                   SUM(amount) AS total
            FROM donations
            GROUP BY period
            ORDER BY MIN(donation_date) ASC
        ";
        break;
    case 'monthly':
        $sql = "
            SELECT DATE_FORMAT(donation_date, '%Y-%m') AS period, 
                   DATE_FORMAT(donation_date, '%b %Y') AS label, 
                   SUM(amount) AS total
            FROM donations
            GROUP BY period
            ORDER BY MIN(donation_date) ASC
        ";
        break;
    default: // daily
        $sql = "
            SELECT DATE(donation_date) AS period, 
                   DATE_FORMAT(donation_date, '%b %e') AS label, 
                   SUM(amount) AS total
            FROM donations
            GROUP BY period
            ORDER BY period ASC
        ";
        break;
}

$result = $mysqli->query($sql);
$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

header('Content-Type: application/json');
echo json_encode($data);
