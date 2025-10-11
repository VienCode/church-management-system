<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN, ROLE_EDITOR]);

$id = intval($_GET['id']);
$mysqli->query("UPDATE church_updates SET is_archived = 1 WHERE update_id = $id");
header("Location: upload.php?msg=✅ Post moved to history.");
exit();
?>
