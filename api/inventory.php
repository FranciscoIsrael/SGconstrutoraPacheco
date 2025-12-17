<?php
// API endpoints for inventory management
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
        if (isset($_GET['id'])) {
            getInventoryItem($db, $_GET['id']);
        } elseif (isset($_GET['summary'])) {
            getInventorySummary($db);
        } else {
            getAllInventory($db);
        }
        break;
    
    case 'POST':
        createInventoryItem($db);
        break;
    
    case 'PUT':
        if (isset($_GET['id'])) {
            updateInventoryItem($db, $_GET['id']);
        } else {
            sendErrorResponse('ID is required for update');
        }
        break;
    
    case 'DELETE':
        if (isset($_GET['id'])) {
            deleteInventoryItem($db, $_GET['id']);
        } else {
            sendErrorResponse('ID is required for deletion');
        }
        break;
    
    default:
        sendErrorResponse('Method not allowed', 405);
}

function getAllInventory($db) {
    try {
        $stmt = $db->query("
            SELECT i.*,
                   (i.quantity * i.unit_cost) as total_value,
                   CASE WHEN i.quantity <= i.min_quantity THEN true ELSE false END as low_stock
            FROM inventory i
            ORDER BY i.name ASC
        ");
        $items = $stmt->fetchAll();
        
        foreach ($items as &$item) {
            $item['quantity'] = (float)$item['quantity'];
            $item['unit_cost'] = (float)$item['unit_cost'];
            $item['min_quantity'] = (float)$item['min_quantity'];
            $item['total_value'] = (float)$item['total_value'];
            $item['low_stock'] = (bool)$item['low_stock'];
        }
        
        sendSuccessResponse($items);
    } catch (PDOException $e) {
        sendErrorResponse('Failed to fetch inventory: ' . $e->getMessage());
    }
}

function getInventorySummary($db) {
    try {
        $stmt = $db->query("
            SELECT 
                COUNT(*) as total_items,
                COALESCE(SUM(quantity * unit_cost), 0) as total_value,
                COUNT(CASE WHEN quantity <= min_quantity THEN 1 END) as low_stock_count
            FROM inventory
        ");
        $summary = $stmt->fetch();
        
        $summary['total_value'] = (float)$summary['total_value'];
        $summary['total_items'] = (int)$summary['total_items'];
        $summary['low_stock_count'] = (int)$summary['low_stock_count'];
        
        sendSuccessResponse($summary);
    } catch (PDOException $e) {
        sendErrorResponse('Failed to fetch inventory summary: ' . $e->getMessage());
    }
}

function getInventoryItem($db, $id) {
    try {
        $stmt = $db->prepare("SELECT * FROM inventory WHERE id = ?");
        $stmt->execute([$id]);
        $item = $stmt->fetch();
        
        if ($item) {
            $item['quantity'] = (float)$item['quantity'];
            $item['unit_cost'] = (float)$item['unit_cost'];
            $item['min_quantity'] = (float)$item['min_quantity'];
            sendSuccessResponse($item);
        } else {
            sendErrorResponse('Item not found', 404);
        }
    } catch (PDOException $e) {
        sendErrorResponse('Failed to fetch item: ' . $e->getMessage());
    }
}

function createInventoryItem($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $required = ['name', 'quantity', 'unit_cost'];
    if (!validateRequired($required, $input)) {
        sendErrorResponse('Name, quantity and unit cost are required');
    }
    
    try {
        $stmt = $db->prepare("
            INSERT INTO inventory (name, description, quantity, unit, unit_cost, min_quantity, image_path) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
            RETURNING id
        ");
        $stmt->execute([
            sanitizeInput($input['name']),
            sanitizeInput($input['description'] ?? ''),
            $input['quantity'],
            sanitizeInput($input['unit'] ?? 'unidade'),
            $input['unit_cost'],
            $input['min_quantity'] ?? 0,
            $input['image_path'] ?? null
        ]);
        
        $itemId = $stmt->fetchColumn();
        
        // Log audit
        logAudit($db, 'inventory', $itemId, 'create', null, null, json_encode($input));
        
        sendSuccessResponse(['id' => (int)$itemId, 'message' => 'Item criado com sucesso']);
    } catch (PDOException $e) {
        sendErrorResponse('Failed to create item: ' . $e->getMessage());
    }
}

function updateInventoryItem($db, $id) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $required = ['name', 'quantity', 'unit_cost'];
    if (!validateRequired($required, $input)) {
        sendErrorResponse('Name, quantity and unit cost are required');
    }
    
    try {
        // Get old values for audit
        $oldStmt = $db->prepare("SELECT * FROM inventory WHERE id = ?");
        $oldStmt->execute([$id]);
        $oldData = $oldStmt->fetch();
        
        $stmt = $db->prepare("
            UPDATE inventory 
            SET name = ?, description = ?, quantity = ?, unit = ?, unit_cost = ?, min_quantity = ?, image_path = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([
            sanitizeInput($input['name']),
            sanitizeInput($input['description'] ?? ''),
            $input['quantity'],
            sanitizeInput($input['unit'] ?? 'unidade'),
            $input['unit_cost'],
            $input['min_quantity'] ?? 0,
            $input['image_path'] ?? null,
            $id
        ]);
        
        if ($stmt->rowCount() > 0 || $oldData) {
            // Log audit for changed fields
            if ($oldData) {
                logAudit($db, 'inventory', $id, 'update', null, json_encode($oldData), json_encode($input));
            }
            sendSuccessResponse(['message' => 'Item atualizado com sucesso']);
        } else {
            sendErrorResponse('Item not found', 404);
        }
    } catch (PDOException $e) {
        sendErrorResponse('Failed to update item: ' . $e->getMessage());
    }
}

function deleteInventoryItem($db, $id) {
    try {
        // Get item data before deletion for audit
        $stmt = $db->prepare("SELECT * FROM inventory WHERE id = ?");
        $stmt->execute([$id]);
        $itemData = $stmt->fetch();
        
        $stmt = $db->prepare("DELETE FROM inventory WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() > 0) {
            // Log audit
            if ($itemData) {
                logAudit($db, 'inventory', $id, 'delete', null, json_encode($itemData), null);
            }
            sendSuccessResponse(['message' => 'Item excluído com sucesso']);
        } else {
            sendErrorResponse('Item not found', 404);
        }
    } catch (PDOException $e) {
        sendErrorResponse('Failed to delete item: ' . $e->getMessage());
    }
}

?>