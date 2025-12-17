<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

switch ($method) {
    case 'GET':
        if (isset($_GET['table_name']) && isset($_GET['record_id'])) {
            getImages($db, $_GET['table_name'], $_GET['record_id']);
        } else {
            sendErrorResponse('table_name e record_id são obrigatórios');
        }
        break;
    
    case 'POST':
        addImage($db);
        break;
    
    case 'DELETE':
        if (isset($_GET['id'])) {
            deleteImage($db, $_GET['id']);
        } else {
            sendErrorResponse('ID é obrigatório');
        }
        break;
    
    default:
        sendErrorResponse('Método não permitido', 405);
}

function getImages($db, $tableName, $recordId) {
    try {
        $stmt = $db->prepare("
            SELECT * FROM images 
            WHERE table_name = ? AND record_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$tableName, $recordId]);
        $images = $stmt->fetchAll();
        sendSuccessResponse($images);
    } catch (PDOException $e) {
        sendErrorResponse('Erro ao buscar imagens: ' . $e->getMessage());
    }
}

function addImage($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['table_name']) || empty($input['record_id']) || empty($input['file_path'])) {
        sendErrorResponse('table_name, record_id e file_path são obrigatórios');
    }
    
    try {
        $stmt = $db->prepare("
            INSERT INTO images (table_name, record_id, file_path, file_name, description) 
            VALUES (?, ?, ?, ?, ?) RETURNING id
        ");
        $stmt->execute([
            sanitizeInput($input['table_name']),
            $input['record_id'],
            sanitizeInput($input['file_path']),
            sanitizeInput($input['file_name'] ?? basename($input['file_path'])),
            sanitizeInput($input['description'] ?? '')
        ]);
        
        $imageId = $stmt->fetchColumn();
        logAudit($db, 'images', $imageId, 'create', null, $input);
        sendSuccessResponse(['id' => $imageId, 'message' => 'Imagem adicionada com sucesso']);
    } catch (PDOException $e) {
        sendErrorResponse('Erro ao adicionar imagem: ' . $e->getMessage());
    }
}

function deleteImage($db, $id) {
    try {
        $stmt = $db->prepare("SELECT * FROM images WHERE id = ?");
        $stmt->execute([$id]);
        $image = $stmt->fetch();
        
        if ($image) {
            $filePath = __DIR__ . '/../' . $image['file_path'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            
            $deleteStmt = $db->prepare("DELETE FROM images WHERE id = ?");
            $deleteStmt->execute([$id]);
            
            logAudit($db, 'images', $id, 'delete', $image, null);
            sendSuccessResponse(['message' => 'Imagem removida com sucesso']);
        } else {
            sendErrorResponse('Imagem não encontrada', 404);
        }
    } catch (PDOException $e) {
        sendErrorResponse('Erro ao remover imagem: ' . $e->getMessage());
    }
}
?>
