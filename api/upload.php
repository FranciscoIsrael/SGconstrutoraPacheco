<?php
// API endpoint for image upload
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendErrorResponse('Only POST method is allowed', 405);
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    sendErrorResponse('No image uploaded or upload error occurred');
}

$file = $_FILES['image'];
$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
$maxSize = 5 * 1024 * 1024; // 5MB

// Validate file type
if (!in_array($file['type'], $allowedTypes)) {
    sendErrorResponse('Invalid file type. Only JPG, PNG, GIF and WebP are allowed');
}

// Validate file size
if ($file['size'] > $maxSize) {
    sendErrorResponse('File size exceeds 5MB limit');
}

// Create uploads directory if it doesn't exist
$uploadDir = __DIR__ . '/../uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Generate unique filename
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = uniqid('img_', true) . '.' . $extension;
$filepath = $uploadDir . $filename;

// Move uploaded file
if (move_uploaded_file($file['tmp_name'], $filepath)) {
    $relativePath = 'uploads/' . $filename;
    sendSuccessResponse([
        'message' => 'Image uploaded successfully',
        'path' => $relativePath,
        'url' => $relativePath
    ]);
} else {
    sendErrorResponse('Failed to save uploaded file');
}
?>