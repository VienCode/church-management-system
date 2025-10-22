<?php
include 'database.php';
include 'auth_check.php';
include 'includes/log_helper.php';
restrict_to_roles([ROLE_ADMIN]);

define('BACKUP_KEY', 'UCF-CMS-Encryption-Key-2025'); // same key used in backup_manager.php

// ✅ Validate file input
if (!isset($_GET['file']) || empty($_GET['file'])) {
    die("❌ Invalid download request.");
}

$file = basename($_GET['file']); // prevent directory traversal
$backupDir = __DIR__ . '/backups/';
$filepath = $backupDir . $file;

if (!file_exists($filepath)) {
    die("❌ Backup file not found.");
}

// ✅ Helper: Decrypt .enc file temporarily
function decryptFileForDownload($source, $key) {
    $data = base64_decode(file_get_contents($source));
    $ivlen = openssl_cipher_iv_length("AES-256-CBC");
    $iv = substr($data, 0, $ivlen);
    $encryptedData = substr($data, $ivlen);
    $decrypted = openssl_decrypt($encryptedData, "AES-256-CBC", $key, 0, $iv);

    // Create a temporary decrypted file
    $tempFile = sys_get_temp_dir() . '/ucf_decrypted_' . time() . '.sql';
    file_put_contents($tempFile, $decrypted);
    return $tempFile;
}

// ✅ Determine whether to decrypt
$downloadPath = $filepath;
$downloadName = $file;
$tempDecrypted = false;

if (str_ends_with($file, '.enc')) {
    $downloadPath = decryptFileForDownload($filepath, BACKUP_KEY);
    $downloadName = str_replace('.enc', '.sql', $file);
    $tempDecrypted = true;
}

// ✅ Log the download
log_action($mysqli, $_SESSION['user_id'], $_SESSION['role'], 'DOWNLOAD_BACKUP', "Downloaded backup file {$downloadName}", 'Normal');

// ✅ Send the file securely to browser
header('Content-Description: File Transfer');
header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . filesize($downloadPath));
flush();
readfile($downloadPath);

// ✅ Clean up temporary decrypted file
if ($tempDecrypted && file_exists($downloadPath)) {
    unlink($downloadPath);
}

exit;
?>
