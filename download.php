<?php
require_once 'vendor/autoload.php';

use Google\Client;
use Google\Service\Drive;

$client = new Client();
$client->setAuthConfig(__DIR__ . '/haimotion.json');
$client->addScope(Drive::DRIVE);
$service = new Drive($client);

$fileId = $_GET['id'] ?? null;

if (!$fileId) {
    die("ID file tidak ditemukan.");
}

try {
    $response = $service->files->get($fileId, ['alt' => 'media']);
    $file = $service->files->get($fileId);
    $fileName = $file->getName();

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    echo $response->getBody();
} catch (Exception $e) {
    die("❌ Gagal download file: " . $e->getMessage());
}
