<?php
// API endpoints for materials management
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

switch ($method) {
    case 'GET':
        if (isset($_GET['project_id'])) {
            getMaterialsByProject($db, $_GET['project_id']);
        } elseif (isset($_GET['id'])) {
            getMaterial($db, $_GET['id']);
        } else {
            sendErrorResponse('Project ID is required');
        }
        break;
    
    case 'POST':
        createMaterial($db);
        break;
    
    case 'PUT':
        if (isset($_GET['id'])) {
            updateMaterial($db, $_GET['id']);
        } else {
            sendErrorResponse('ID is required for update');
        }
        break;
    
    case 'DELETE':
        if (isset($_GET['id'])) {
            deleteMaterial($db, $_GET['id']);
        } else {
            sendErrorResponse('ID is required for deletion');
        }
        break;
    
    default:
        sendErrorResponse('Method not allowed', 405);
}

function getMaterialsByProject($db, $projectId) {
    try {
        $stmt = $db->prepare("
            SELECT m.*, p.name as project_name 
            FROM materials m 
            JOIN projects p ON m.project_id = p.id 
            WHERE m.project_id = ? 
            ORDER BY m.created_at DESC
        ");
        $stmt->execute([$projectId]);
        $materials = $stmt->fetchAll();
        
        foreach ($materials as &$material) {
            $material['quantity'] = (float)$material['quantity'];
            $material['cost'] = (float)$material['cost'];
            $material['total_cost'] = $material['quantity'] * $material['cost'];
        }
        
        sendSuccessResponse($materials);
    } catch (PDOException $e) {
        sendErrorResponse('Failed to fetch materials: ' . $e->getMessage());
    }
}

function getMaterial($db, $id) {
    try {
        $stmt = $db->prepare("SELECT * FROM materials WHERE id = ?");
        $stmt->execute([$id]);
        $material = $stmt->fetch();
        
        if ($material) {
            $material['quantity'] = (float)$material['quantity'];
            $material['cost'] = (float)$material['cost'];
            sendSuccessResponse($material);
        } else {
            sendErrorResponse('Material not found', 404);
        }
    } catch (PDOException $e) {
        sendErrorResponse('Failed to fetch material: ' . $e->getMessage());
    }
}

function createMaterial($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $required = ['project_id', 'name', 'quantity', 'cost'];
    if (!validateRequired($required, $input)) {
        sendErrorResponse('All fields are required');
    }
    
    try {
        $stmt = $db->prepare("
            INSERT INTO materials (project_id, name, quantity, unit, cost) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $input['project_id'],
            sanitizeInput($input['name']),
            $input['quantity'],
            sanitizeInput($input['unit'] ?? 'unidade'),
            $input['cost']
        ]);
        
        $materialId = $db->lastInsertId();
        sendSuccessResponse(['id' => $materialId, 'message' => 'Material created successfully']);
    } catch (PDOException $e) {
        sendErrorResponse('Failed to create material: ' . $e->getMessage());
    }
}

function updateMaterial($db, $id) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $required = ['name', 'quantity', 'cost'];
    if (!validateRequired($required, $input)) {
        sendErrorResponse('All fields are required');
    }
    
    try {
        $stmt = $db->prepare("
            UPDATE materials 
            SET name = ?, quantity = ?, unit = ?, cost = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([
            sanitizeInput($input['name']),
            $input['quantity'],
            sanitizeInput($input['unit'] ?? 'unidade'),
            $input['cost'],
            $id
        ]);
        
        if ($stmt->rowCount() > 0) {
            sendSuccessResponse(['message' => 'Material updated successfully']);
        } else {
            sendErrorResponse('Material not found', 404);
        }
    } catch (PDOException $e) {
        sendErrorResponse('Failed to update material: ' . $e->getMessage());
    }
}

function deleteMaterial($db, $id) {
    try {
        $stmt = $db->prepare("DELETE FROM materials WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() > 0) {
            sendSuccessResponse(['message' => 'Material deleted successfully']);
        } else {
            sendErrorResponse('Material not found', 404);
        }
    } catch (PDOException $e) {
        sendErrorResponse('Failed to delete material: ' . $e->getMessage());
    }
}
?>