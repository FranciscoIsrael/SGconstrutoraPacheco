<?php
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

function generateTransactionCode($prefix = 'MAT') {
    return $prefix . '-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

function getMaterialsByProject($db, $projectId) {
    try {
        $stmt = $db->prepare("
            SELECT m.*, p.name as project_name, i.name as inventory_name
            FROM materials m 
            JOIN projects p ON m.project_id = p.id 
            LEFT JOIN inventory i ON m.inventory_id = i.id
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
        $db->beginTransaction();
        
        $transactionCode = generateTransactionCode('MAT');
        $inventoryId = $input['inventory_id'] ?? null;
        
        if ($inventoryId) {
            $checkStmt = $db->prepare("SELECT quantity, unit_cost, name FROM inventory WHERE id = ?");
            $checkStmt->execute([$inventoryId]);
            $invItem = $checkStmt->fetch();
            
            if (!$invItem) {
                $db->rollBack();
                sendErrorResponse('Item de inventário não encontrado');
                return;
            }
            
            if ($invItem['quantity'] < $input['quantity']) {
                $db->rollBack();
                sendErrorResponse('Quantidade insuficiente no inventário');
                return;
            }
            
            $updateInv = $db->prepare("UPDATE inventory SET quantity = quantity - ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $updateInv->execute([$input['quantity'], $inventoryId]);
            
            $movementCode = generateTransactionCode('INV');
            $movementStmt = $db->prepare("
                INSERT INTO inventory_movements (transaction_code, inventory_id, project_id, movement_type, quantity, destination, notes, movement_date)
                VALUES (?, ?, ?, 'out', ?, 'obra', ?, CURRENT_DATE)
            ");
            $movementStmt->execute([
                $movementCode,
                $inventoryId,
                $input['project_id'],
                $input['quantity'],
                'Material enviado para obra - ' . $transactionCode
            ]);
            
            if (empty($input['cost'])) {
                $input['cost'] = $invItem['unit_cost'];
            }
            if (empty($input['name'])) {
                $input['name'] = $invItem['name'];
            }
        }
        
        $stmt = $db->prepare("
            INSERT INTO materials (project_id, name, description, quantity, unit, cost, inventory_id, transaction_code, image_path) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id
        ");
        $stmt->execute([
            $input['project_id'],
            sanitizeInput($input['name']),
            sanitizeInput($input['description'] ?? ''),
            $input['quantity'],
            sanitizeInput($input['unit'] ?? 'unidade'),
            $input['cost'],
            $inventoryId,
            $transactionCode,
            sanitizeInput($input['image_path'] ?? null)
        ]);
        
        $materialId = $stmt->fetchColumn();
        
        logAudit($db, 'materials', $materialId, 'create', null, $input);
        
        $db->commit();
        sendSuccessResponse([
            'id' => $materialId, 
            'transaction_code' => $transactionCode,
            'message' => 'Material criado com sucesso'
        ]);
    } catch (PDOException $e) {
        $db->rollBack();
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
        $oldStmt = $db->prepare("SELECT * FROM materials WHERE id = ?");
        $oldStmt->execute([$id]);
        $oldData = $oldStmt->fetch();
        
        $stmt = $db->prepare("
            UPDATE materials 
            SET name = ?, description = ?, quantity = ?, unit = ?, cost = ?, image_path = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([
            sanitizeInput($input['name']),
            sanitizeInput($input['description'] ?? ''),
            $input['quantity'],
            sanitizeInput($input['unit'] ?? 'unidade'),
            $input['cost'],
            sanitizeInput($input['image_path'] ?? $oldData['image_path']),
            $id
        ]);
        
        if ($stmt->rowCount() > 0) {
            logAudit($db, 'materials', $id, 'update', $oldData, $input);
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
        $stmt = $db->prepare("SELECT * FROM materials WHERE id = ?");
        $stmt->execute([$id]);
        $material = $stmt->fetch();
        
        if ($material) {
            $deleteStmt = $db->prepare("DELETE FROM materials WHERE id = ?");
            $deleteStmt->execute([$id]);
            
            logAudit($db, 'materials', $id, 'delete', $material, null);
            sendSuccessResponse(['message' => 'Material deleted successfully']);
        } else {
            sendErrorResponse('Material not found', 404);
        }
    } catch (PDOException $e) {
        sendErrorResponse('Failed to delete material: ' . $e->getMessage());
    }
}
?>
