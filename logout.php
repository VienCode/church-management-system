<?php

session_start();

session_destroy();

header("Location: pre-index.php");
exit;