<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN, ROLE_EDITOR]);

$id = intval($_GET['id']);
$mysqli->query("UPDATE church_updates SET is_pinned = IF(is_pinned=1,0,1) WHERE update_id = $id");
header("Location: upload.php?msg=📌 Pin status updated.");
exit();
?>
