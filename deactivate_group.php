<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN]);

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) die("Invalid ID");

$mysqli->query("UPDATE cell_groups SET status='inactive', archived_at=NOW() WHERE id=$id");
header("Location: cell_groups_admin.php?msg=🛑 Group deactivated.");
exit();
?>
