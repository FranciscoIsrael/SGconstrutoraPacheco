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
        if (isset($_GET['inventory_id'])) {
            getDeliveriesByInventory($db, $_GET['inventory_id']);
        } elseif (isset($_GET['id'])) {
            getDelivery($db, $_GET['id']);
        } else {
            getAllDeliveries($db);
        }
        break;
    
    case 'POST':
        createDelivery($db);
        break;
    
    case 'DELETE':
        if (isset($_GET['id'])) {
            deleteDelivery($db, $_GET['id']);
        } else {
            sendErrorResponse('ID é obrigatório');
        }
        break;
    
    default:
        sendErrorResponse('Método não permitido', 405);
}

function generateDeliveryCode() {
    return 'ENT-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

function getAllDeliveries($db) {
    try {
        $stmt = $db->query("
            SELECT d.*, i.name as item_name, i.unit, p.name as project_name
            FROM inventory_deliveries d
            LEFT JOIN inventory i ON d.inventory_id = i.id
            LEFT JOIN projects p ON d.project_id = p.id
            ORDER BY d.created_at DESC
        ");
        $deliveries = $stmt->fetchAll();
        
        foreach ($deliveries as &$delivery) {
            $delivery['quantity'] = (float)$delivery['quantity'];
            $delivery['unit_price'] = (float)$delivery['unit_price'];
            $delivery['total_value'] = (float)$delivery['total_value'];
        }
        
        sendSuccessResponse($deliveries);
    } catch (PDOException $e) {
        sendErrorResponse('Erro ao buscar entregas: ' . $e->getMessage());
    }
}

function getDeliveriesByInventory($db, $inventoryId) {
    try {
        $stmt = $db->prepare("
            SELECT d.*, i.name as item_name, i.unit, p.name as project_name
            FROM inventory_deliveries d
            LEFT JOIN inventory i ON d.inventory_id = i.id
            LEFT JOIN projects p ON d.project_id = p.id
            WHERE d.inventory_id = ?
            ORDER BY d.created_at DESC
        ");
        $stmt->execute([$inventoryId]);
        $deliveries = $stmt->fetchAll();
        
        foreach ($deliveries as &$delivery) {
            $delivery['quantity'] = (float)$delivery['quantity'];
            $delivery['unit_price'] = (float)$delivery['unit_price'];
            $delivery['total_value'] = (float)$delivery['total_value'];
        }
        
        sendSuccessResponse($deliveries);
    } catch (PDOException $e) {
        sendErrorResponse('Erro ao buscar entregas: ' . $e->getMessage());
    }
}

function getDelivery($db, $id) {
    try {
        $stmt = $db->prepare("
            SELECT d.*, i.name as item_name, i.unit, p.name as project_name
            FROM inventory_deliveries d
            LEFT JOIN inventory i ON d.inventory_id = i.id
            LEFT JOIN projects p ON d.project_id = p.id
            WHERE d.id = ?
        ");
        $stmt->execute([$id]);
        $delivery = $stmt->fetch();
        
        if ($delivery) {
            $delivery['quantity'] = (float)$delivery['quantity'];
            $delivery['unit_price'] = (float)$delivery['unit_price'];
            $delivery['total_value'] = (float)$delivery['total_value'];
            $delivery['images'] = getEntityImages($db, 'inventory_deliveries', $delivery['id']);
            sendSuccessResponse($delivery);
        } else {
            sendErrorResponse('Entrega não encontrada', 404);
        }
    } catch (PDOException $e) {
        sendErrorResponse('Erro ao buscar entrega: ' . $e->getMessage());
    }
}

function createDelivery($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['inventory_id']) || empty($input['quantity'])) {
        sendErrorResponse('Item e quantidade são obrigatórios');
    }
    
    try {
        $db->beginTransaction();
        
        $invStmt = $db->prepare("SELECT * FROM inventory WHERE id = ?");
        $invStmt->execute([$input['inventory_id']]);
        $inventory = $invStmt->fetch();
        
        if (!$inventory) {
            $db->rollBack();
            sendErrorResponse('Item não encontrado');
        }
        
        if ($inventory['quantity'] < $input['quantity']) {
            $db->rollBack();
            sendErrorResponse('Quantidade insuficiente em estoque');
        }
        
        $deliveryCode = generateDeliveryCode();
        $unitPrice = $input['unit_price'] ?? $inventory['unit_cost'];
        $totalValue = $input['quantity'] * $unitPrice;
        
        $stmt = $db->prepare("
            INSERT INTO inventory_deliveries (
                inventory_id, project_id, client_name, delivery_code,
                quantity, unit_price, total_value, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?) RETURNING id
        ");
        $stmt->execute([
            $input['inventory_id'],
            $input['project_id'] ?? null,
            sanitizeInput($input['client_name'] ?? ''),
            $deliveryCode,
            $input['quantity'],
            $unitPrice,
            $totalValue,
            sanitizeInput($input['notes'] ?? '')
        ]);
        
        $deliveryId = $stmt->fetchColumn();
        
        $updateStmt = $db->prepare("UPDATE inventory SET quantity = quantity - ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $updateStmt->execute([$input['quantity'], $input['inventory_id']]);
        
        $db->commit();
        
        logAudit($db, 'inventory_deliveries', $deliveryId, 'create', null, array_merge($input, ['delivery_code' => $deliveryCode]));
        
        sendSuccessResponse([
            'id' => $deliveryId, 
            'delivery_code' => $deliveryCode,
            'message' => 'Entrega registrada com sucesso'
        ]);
    } catch (PDOException $e) {
        $db->rollBack();
        sendErrorResponse('Erro ao registrar entrega: ' . $e->getMessage());
    }
}

function deleteDelivery($db, $id) {
    try {
        $stmt = $db->prepare("SELECT * FROM inventory_deliveries WHERE id = ?");
        $stmt->execute([$id]);
        $delivery = $stmt->fetch();
        
        if ($delivery) {
            $db->beginTransaction();
            
            $updateStmt = $db->prepare("UPDATE inventory SET quantity = quantity + ? WHERE id = ?");
            $updateStmt->execute([$delivery['quantity'], $delivery['inventory_id']]);
            
            $deleteStmt = $db->prepare("DELETE FROM inventory_deliveries WHERE id = ?");
            $deleteStmt->execute([$id]);
            
            $db->commit();
            
            logAudit($db, 'inventory_deliveries', $id, 'delete', $delivery, null);
            sendSuccessResponse(['message' => 'Entrega removida e estoque restaurado']);
        } else {
            sendErrorResponse('Entrega não encontrada', 404);
        }
    } catch (PDOException $e) {
        $db->rollBack();
        sendErrorResponse('Erro ao remover entrega: ' . $e->getMessage());
    }
}

function getEntityImages($db, $tableName, $recordId) {
    try {
        $stmt = $db->prepare("SELECT * FROM images WHERE table_name = ? AND record_id = ? ORDER BY created_at DESC");
        $stmt->execute([$tableName, $recordId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}
?>
