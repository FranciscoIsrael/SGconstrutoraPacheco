<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendErrorResponse('Only POST method is allowed', 405);
}

$fileKey = isset($_FILES['file']) ? 'file' : (isset($_FILES['image']) ? 'image' : null);

if (!$fileKey || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
    sendErrorResponse('No file uploaded or upload error occurred');
}

$file = $_FILES[$fileKey];
$fileType = $_POST['file_type'] ?? 'image';
$tableName = $_POST['table_name'] ?? '';
$recordId = $_POST['record_id'] ?? '';
$description = $_POST['description'] ?? '';

$maxSize = 10 * 1024 * 1024; // 10MB

if ($file['size'] > $maxSize) {
    sendErrorResponse('File size exceeds 10MB limit');
}

$imageTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
$docTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
             'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/plain'];

if ($fileType === 'document') {
    $uploadDir = __DIR__ . '/../uploads/documents/';
    $allowedTypes = array_merge($imageTypes, $docTypes);
} else {
    $uploadDir = __DIR__ . '/../uploads/';
    $allowedTypes = $imageTypes;
}

if (!in_array($file['type'], $allowedTypes)) {
    sendErrorResponse('Invalid file type');
}

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$prefix = $fileType === 'document' ? 'doc_' : 'img_';
$filename = $prefix . uniqid('', true) . '.' . $extension;
$filepath = $uploadDir . $filename;

if (move_uploaded_file($file['tmp_name'], $filepath)) {
    $relativePath = $fileType === 'document' ? 'uploads/documents/' . $filename : 'uploads/' . $filename;
    
    if ($tableName && $recordId) {
        $db = getDB();
        try {
            if ($fileType === 'document') {
                $stmt = $db->prepare("
                    INSERT INTO documents (table_name, record_id, file_path, file_name, description) 
                    VALUES (?, ?, ?, ?, ?) RETURNING id
                ");
                $stmt->execute([
                    sanitizeInput($tableName),
                    $recordId,
                    $relativePath,
                    $file['name'],
                    sanitizeInput($description)
                ]);
                $docId = $stmt->fetchColumn();
                logAudit($db, 'documents', $docId, 'create', null, ['file_name' => $file['name']]);
            } else {
                $stmt = $db->prepare("
                    INSERT INTO images (table_name, record_id, file_path, file_name, description) 
                    VALUES (?, ?, ?, ?, ?) RETURNING id
                ");
                $stmt->execute([
                    sanitizeInput($tableName),
                    $recordId,
                    $relativePath,
                    $file['name'],
                    sanitizeInput($description)
                ]);
                $imageId = $stmt->fetchColumn();
                logAudit($db, 'images', $imageId, 'create', null, ['file_name' => $file['name']]);
            }
        } catch (PDOException $e) {
            sendErrorResponse('Erro ao salvar no banco: ' . $e->getMessage());
        }
    }
    
    sendSuccessResponse([
        'message' => 'File uploaded successfully',
        'path' => $relativePath,
        'url' => $relativePath,
        'file_name' => $file['name']
    ]);
} else {
    sendErrorResponse('Failed to save uploaded file');
}
?>
