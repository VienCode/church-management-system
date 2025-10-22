<?php
include 'database.php';
include 'auth_check.php';
include 'includes/log_helper.php';
restrict_to_roles([ROLE_ADMIN]);

// === CONFIGURATION ===
$backupDir = __DIR__ . '/backups/';
if (!is_dir($backupDir)) mkdir($backupDir, 0775, true);

define('BACKUP_KEY', 'UCF-CMS-Encryption-Key-2025'); // Encryption key

// Detect mysqldump/mysql automatically (handles relocated XAMPP/WAMP)
$defaultMySQLDir = 'C:\\xampp\\mysql\\bin\\';
$mysqldumpPath = file_exists($defaultMySQLDir . 'mysqldump.exe')
    ? $defaultMySQLDir . 'mysqldump.exe'
    : 'mysqldump';
$mysqlPath = file_exists($defaultMySQLDir . 'mysql.exe')
    ? $defaultMySQLDir . 'mysql.exe'
    : 'mysql';

// === ENCRYPTION HELPERS ===
function encryptFile($source, $destination, $key)
{
    if (!file_exists($source)) return false;
    $ivlen = openssl_cipher_iv_length($cipher = "AES-256-CBC");
    $iv = openssl_random_pseudo_bytes($ivlen);
    $data = file_get_contents($source);
    $encrypted = openssl_encrypt($data, $cipher, $key, 0, $iv);
    return file_put_contents($destination, base64_encode($iv . $encrypted)) !== false;
}

function decryptFile($source, $destination, $key)
{
    if (!file_exists($source)) return false;
    $data = base64_decode(file_get_contents($source));
    $ivlen = openssl_cipher_iv_length("AES-256-CBC");
    $iv = substr($data, 0, $ivlen);
    $encryptedData = substr($data, $ivlen);
    $decrypted = openssl_decrypt($encryptedData, "AES-256-CBC", $key, 0, $iv);
    return file_put_contents($destination, $decrypted) !== false;
}

// === BACKUP CREATION ===
if (isset($_POST['create_backup'])) {
    $tables = $_POST['tables'] ?? [];
    $encrypt = isset($_POST['encrypt']);
    $filename = 'ucf_backup_' . date('Ymd_His') . ($encrypt ? '_enc' : '') . '.sql';
    $filepath = $backupDir . $filename;
    $selectedTables = !empty($tables) ? implode(' ', $tables) : '--all-databases';

    $command = "\"$mysqldumpPath\" -u root --password= -h localhost {$db_name} $selectedTables > \"$filepath\"";
    exec($command, $output, $status);

    if ($status !== 0 || !file_exists($filepath) || filesize($filepath) === 0) {
        log_action($mysqli, $_SESSION['user_id'], $_SESSION['role'], 'BACKUP_FAIL',
            "Backup failed. Check path or permissions. CMD: $command", 'Critical');
        header("Location: backup.php?msg=❌ Backup failed. Check permissions or mysqldump path.");
        exit;
    }

    if ($encrypt && filesize($filepath) > 0) {
        $encryptedPath = $filepath . '.enc';
        if (encryptFile($filepath, $encryptedPath, BACKUP_KEY)) {
            unlink($filepath);
            $filepath = $encryptedPath;
            $filename .= '.enc';
        }
    }

    $filesize = filesize($filepath);
    $status_text = 'Success';

    $stmt = $mysqli->prepare("
        INSERT INTO backups (file_name, file_path, created_by, file_size, status)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("ssiis", $filename, $filepath, $_SESSION['user_id'], $filesize, $status_text);
    $stmt->execute();
    $stmt->close();

    log_action($mysqli, $_SESSION['user_id'], $_SESSION['role'], 'BACKUP',
        "Database backup created (" . ($encrypt ? "Encrypted" : "Plain") . ", " . count($tables) . " tables).",
        'Critical');

    header("Location: backup.php?msg=✅ Backup created successfully!");
    exit;
}

// === RESTORE BACKUP (Manual Upload) ===
if (isset($_POST['restore_backup'])) {
    $file = $_FILES['backup_file']['tmp_name'] ?? null;
    $filename = $_FILES['backup_file']['name'] ?? null;

    if (!$file || !$filename) {
        header("Location: restore.php?msg=❌ No file selected.");
        exit;
    }

    $tempDecrypted = null;
    if (str_ends_with($filename, '.enc')) {
        $tempDecrypted = sys_get_temp_dir() . '/decrypted_' . time() . '.sql';
        decryptFile($file, $tempDecrypted, BACKUP_KEY);
        $file = $tempDecrypted;
    }

    $command = "\"$mysqlPath\" -u root --password= -h localhost {$db_name} < \"$file\"";
    exec($command, $output, $status);

    if ($tempDecrypted && file_exists($tempDecrypted)) unlink($tempDecrypted);

    if ($status !== 0) {
        log_action($mysqli, $_SESSION['user_id'], $_SESSION['role'], 'RESTORE_FAIL',
            "Restore failed from uploaded file: {$filename}", 'Critical');
        header("Location: restore.php?msg=❌ Restore failed. Check SQL file integrity or MySQL path.");
        exit;
    }

    log_action($mysqli, $_SESSION['user_id'], $_SESSION['role'], 'RESTORE',
        "Database restored successfully from uploaded file: {$filename}", 'Critical');

    header("Location: restore.php?msg=✅ Database restored successfully!");
    exit;
}

// === RESTORE EXISTING BACKUP (From History) ===
if (isset($_POST['restore_existing']) && !empty($_POST['restore_file_path'])) {
    $file = $_POST['restore_file_path'];
    $filename = basename($file);

    if (!file_exists($file)) {
        header("Location: backup.php?msg=❌ Backup file not found on server.");
        exit;
    }

    $tempDecrypted = null;
    if (str_ends_with($filename, '.enc')) {
        $tempDecrypted = sys_get_temp_dir() . '/decrypted_' . time() . '.sql';
        decryptFile($file, $tempDecrypted, BACKUP_KEY);
        $file = $tempDecrypted;
    }

    $command = "\"$mysqlPath\" -u root --password= -h localhost {$db_name} < \"$file\"";
    exec($command, $output, $status);

    if ($tempDecrypted && file_exists($tempDecrypted)) unlink($tempDecrypted);

    if ($status !== 0) {
        log_action($mysqli, $_SESSION['user_id'], $_SESSION['role'], 'RESTORE_FAIL',
            "Restore failed from existing file: {$filename}", 'Critical');
        header("Location: backup.php?msg=❌ Restore failed. Check file or MySQL path.");
        exit;
    }

    log_action($mysqli, $_SESSION['user_id'], $_SESSION['role'], 'RESTORE',
        "Database restored successfully from existing backup file: {$filename}", 'Critical');

    header("Location: backup.php?msg=✅ Database restored successfully from {$filename}!");
    exit;
}

// === TEST BACKUP CONNECTION ===
if (isset($_POST['test_backup'])) {
    $testFile = $backupDir . 'test_permission.sql';
    $mysqldumpExe = "C:\\xampp\\mysql\\bin\\mysqldump.exe";

    // Confirm mysqldump exists
    if (!file_exists($mysqldumpExe)) {
        header("Location: backup.php?msg=❌ mysqldump.exe not found in C:\\xampp\\mysql\\bin\\");
        exit;
    }

    // Use Windows CMD redirection for PHP compatibility
    $testCmd = "cmd /c \"$mysqldumpExe\" -u root --password= -h localhost {$db_name} > \"$testFile\" 2>&1";

    exec($testCmd, $output, $status);

    // Write everything to a log for inspection
    $logPath = __DIR__ . '/backup_debug.log';
    file_put_contents($logPath,
        "=== BACKUP DEBUG LOG ===\n" .
        "Date: " . date('Y-m-d H:i:s') . "\n" .
        "COMMAND:\n$testCmd\n\nSTATUS: $status\n\nOUTPUT:\n" . implode("\n", $output) . "\n=========================\n",
        FILE_APPEND
    );

    if (file_exists($testFile)) unlink($testFile);

    $msg = ($status === 0)
        ? "✅ mysqldump connection working and path valid!"
        : "❌ mysqldump test failed. Check 'backup_debug.log' for details.";

    log_action($mysqli, $_SESSION['user_id'], $_SESSION['role'], 'TEST_BACKUP', $msg, 'Normal');
    header("Location: backup.php?msg=$msg");
    exit;
}

// === DOWNLOAD ALL BACKUPS AS ZIP ===
if (isset($_POST['download_all_backups'])) {
    $zipName = 'ucf_all_backups_' . date('Ymd_His') . '.zip';
    $zipPath = $backupDir . $zipName;

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        header("Location: backup.php?msg=❌ Failed to create ZIP file.");
        exit;
    }

    $files = glob($backupDir . '*.{sql,sql.enc}', GLOB_BRACE);
    if (empty($files)) {
        $zip->close();
        unlink($zipPath);
        header("Location: backup.php?msg=⚠️ No backup files found to compress.");
        exit;
    }

    foreach ($files as $file) {
        $zip->addFile($file, basename($file));
    }
    $zip->close();

    log_action($mysqli, $_SESSION['user_id'], $_SESSION['role'], 'DOWNLOAD_ZIP',
        "Downloaded all backups as ZIP ({$zipName})", 'Normal');

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . basename($zipPath) . '"');
    header('Content-Length: ' . filesize($zipPath));
    readfile($zipPath);
    unlink($zipPath);
    exit;
}
?>
