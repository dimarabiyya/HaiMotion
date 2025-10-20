<?php
require_once 'vendor/autoload.php';

use Google\Client;
use Google\Service\Drive;

$client = new Client();
$client->setAuthConfig(__DIR__ . '/haimotion.json');
$client->addScope(Drive::DRIVE);
$service = new Drive($client);


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $fileMetadata = new Drive\DriveFile([
        'name' => $_FILES['file']['name']
    ]);

    $content = file_get_contents($_FILES['file']['tmp_name']);

    try {
        $file = $service->files->create($fileMetadata, [
            'data' => $content,
            'mimeType' => $_FILES['file']['type'],
            'uploadType' => 'multipart'
        ]);
        echo "✅ Berhasil upload ke root. ID: " . $file->getId();
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage();
    }
}
?>

<form method="POST" enctype="multipart/form-data">
    <input type="file" name="file">
    <input type="submit" value="Upload to Google Drive">
</form>
