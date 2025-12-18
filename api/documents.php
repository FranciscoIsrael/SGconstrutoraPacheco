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
            getDocuments($db, $_GET['table_name'], $_GET['record_id']);
        } else {
            sendErrorResponse('table_name e record_id são obrigatórios');
        }
        break;
    
    case 'POST':
        addDocument($db);
        break;
    
    case 'DELETE':
        if (isset($_GET['id'])) {
            deleteDocument($db, $_GET['id']);
        } else {
            sendErrorResponse('ID é obrigatório');
        }
        break;
    
    default:
        sendErrorResponse('Método não permitido', 405);
}

function getDocuments($db, $tableName, $recordId) {
    try {
        $stmt = $db->prepare("
            SELECT * FROM documents 
            WHERE table_name = ? AND record_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$tableName, $recordId]);
        $documents = $stmt->fetchAll();
        sendSuccessResponse($documents);
    } catch (PDOException $e) {
        sendErrorResponse('Erro ao buscar documentos: ' . $e->getMessage());
    }
}

function addDocument($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['table_name']) || empty($input['record_id']) || empty($input['file_path'])) {
        sendErrorResponse('table_name, record_id e file_path são obrigatórios');
    }
    
    try {
        $stmt = $db->prepare("
            INSERT INTO documents (table_name, record_id, file_path, file_name, description) 
            VALUES (?, ?, ?, ?, ?) RETURNING id
        ");
        $stmt->execute([
            sanitizeInput($input['table_name']),
            $input['record_id'],
            sanitizeInput($input['file_path']),
            sanitizeInput($input['file_name'] ?? basename($input['file_path'])),
            sanitizeInput($input['description'] ?? '')
        ]);
        
        $docId = $stmt->fetchColumn();
        logAudit($db, 'documents', $docId, 'create', null, $input);
        sendSuccessResponse(['id' => $docId, 'message' => 'Documento adicionado com sucesso']);
    } catch (PDOException $e) {
        sendErrorResponse('Erro ao adicionar documento: ' . $e->getMessage());
    }
}

function deleteDocument($db, $id) {
    try {
        $stmt = $db->prepare("SELECT * FROM documents WHERE id = ?");
        $stmt->execute([$id]);
        $document = $stmt->fetch();
        
        if ($document) {
            $filePath = __DIR__ . '/../' . $document['file_path'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            
            $deleteStmt = $db->prepare("DELETE FROM documents WHERE id = ?");
            $deleteStmt->execute([$id]);
            
            logAudit($db, 'documents', $id, 'delete', $document, null);
            sendSuccessResponse(['message' => 'Documento removido com sucesso']);
        } else {
            sendErrorResponse('Documento não encontrado', 404);
        }
    } catch (PDOException $e) {
        sendErrorResponse('Erro ao remover documento: ' . $e->getMessage());
    }
}
?>
