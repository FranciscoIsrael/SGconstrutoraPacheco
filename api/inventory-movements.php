<?php
// API endpoints for inventory movements management
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
            getMovementsByInventory($db, $_GET['inventory_id']);
        } elseif (isset($_GET['id'])) {
            getMovement($db, $_GET['id']);
        } else {
            getAllMovements($db);
        }
        break;
    
    case 'POST':
        createMovement($db);
        break;
    
    default:
        sendErrorResponse('Method not allowed', 405);
}

function getAllMovements($db) {
    try {
        $stmt = $db->query("
            SELECT im.*, i.name as item_name, p.name as project_name
            FROM inventory_movements im
            JOIN inventory i ON im.inventory_id = i.id
            LEFT JOIN projects p ON im.project_id = p.id
            ORDER BY im.movement_date DESC, im.created_at DESC
        ");
        $movements = $stmt->fetchAll();
        
        foreach ($movements as &$movement) {
            $movement['quantity'] = (float)$movement['quantity'];
            $movement['movement_date_formatted'] = formatDate($movement['movement_date']);
        }
        
        sendSuccessResponse($movements);
    } catch (PDOException $e) {
        sendErrorResponse('Failed to fetch movements: ' . $e->getMessage());
    }
}

function getMovementsByInventory($db, $inventoryId) {
    try {
        $stmt = $db->prepare("
            SELECT im.*, p.name as project_name
            FROM inventory_movements im
            LEFT JOIN projects p ON im.project_id = p.id
            WHERE im.inventory_id = ?
            ORDER BY im.movement_date DESC, im.created_at DESC
        ");
        $stmt->execute([$inventoryId]);
        $movements = $stmt->fetchAll();
        
        foreach ($movements as &$movement) {
            $movement['quantity'] = (float)$movement['quantity'];
            $movement['movement_date_formatted'] = formatDate($movement['movement_date']);
        }
        
        sendSuccessResponse($movements);
    } catch (PDOException $e) {
        sendErrorResponse('Failed to fetch movements: ' . $e->getMessage());
    }
}

function getMovement($db, $id) {
    try {
        $stmt = $db->prepare("SELECT * FROM inventory_movements WHERE id = ?");
        $stmt->execute([$id]);
        $movement = $stmt->fetch();
        
        if ($movement) {
            $movement['quantity'] = (float)$movement['quantity'];
            sendSuccessResponse($movement);
        } else {
            sendErrorResponse('Movement not found', 404);
        }
    } catch (PDOException $e) {
        sendErrorResponse('Failed to fetch movement: ' . $e->getMessage());
    }
}

function createMovement($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $required = ['inventory_id', 'movement_type', 'quantity'];
    if (!validateRequired($required, $input)) {
        sendErrorResponse('Inventory ID, movement type and quantity are required');
    }
    
    if (!in_array($input['movement_type'], ['in', 'out'])) {
        sendErrorResponse('Movement type must be either "in" or "out"');
    }
    
    try {
        $db->beginTransaction();
        
        // Get inventory item details for cost calculation
        $invStmt = $db->prepare("SELECT name, unit_cost FROM inventory WHERE id = ?");
        $invStmt->execute([$input['inventory_id']]);
        $inventoryItem = $invStmt->fetch();
        $unitCost = (float)($inventoryItem['unit_cost'] ?? 0);
        $itemName = $inventoryItem['name'] ?? 'Item';
        
        // Create movement record with unit cost
        $stmt = $db->prepare("
            INSERT INTO inventory_movements (inventory_id, project_id, movement_type, quantity, destination, notes, movement_date, unit_cost) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            RETURNING id
        ");
        $stmt->execute([
            $input['inventory_id'],
            $input['project_id'] ?? null,
            $input['movement_type'],
            $input['quantity'],
            sanitizeInput($input['destination'] ?? ''),
            sanitizeInput($input['notes'] ?? ''),
            $input['movement_date'] ?? date('Y-m-d'),
            $unitCost
        ]);
        
        $movementId = $stmt->fetchColumn();
        
        // Update inventory quantity
        $quantityChange = ($input['movement_type'] === 'in') ? $input['quantity'] : -$input['quantity'];
        $updateStmt = $db->prepare("
            UPDATE inventory 
            SET quantity = quantity + ?, updated_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        $updateStmt->execute([$quantityChange, $input['inventory_id']]);
        
        // If sending materials to a project (out), create an expense transaction
        if ($input['movement_type'] === 'out' && !empty($input['project_id'])) {
            $totalCost = $unitCost * (float)$input['quantity'];
            
            if ($totalCost > 0) {
                // Create expense transaction for the project
                $transStmt = $db->prepare("
                    INSERT INTO transactions (project_id, type, description, amount, transaction_date) 
                    VALUES (?, 'expense', ?, ?, ?)
                ");
                $transStmt->execute([
                    $input['project_id'],
                    'Material do estoque: ' . $itemName . ' (' . $input['quantity'] . ' unidades)',
                    $totalCost,
                    $input['movement_date'] ?? date('Y-m-d')
                ]);
            }
            
            // Create notification
            $projStmt = $db->prepare("SELECT name FROM projects WHERE id = ?");
            $projStmt->execute([$input['project_id']]);
            $project = $projStmt->fetch();
            
            $notifStmt = $db->prepare("
                INSERT INTO notifications (project_id, type, title, message) 
                VALUES (?, ?, ?, ?)
            ");
            $notifStmt->execute([
                $input['project_id'],
                'inventory',
                'Material enviado para obra',
                $itemName . ' - ' . $input['quantity'] . ' unidade(s) enviado para: ' . ($project['name'] ?? 'obra')
            ]);
        }
        
        // Create notification for stock replenishment
        if ($input['movement_type'] === 'in') {
            $notifStmt = $db->prepare("
                INSERT INTO notifications (project_id, type, title, message) 
                VALUES (NULL, ?, ?, ?)
            ");
            $notifStmt->execute([
                'inventory_restock',
                'Reposição de estoque',
                $itemName . ' - ' . $input['quantity'] . ' unidade(s) adicionada(s) ao estoque'
            ]);
        }
        
        // Log audit
        $auditStmt = $db->prepare("
            INSERT INTO audit_history (table_name, record_id, action, field_name, old_value, new_value)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $auditStmt->execute([
            'inventory_movements',
            $movementId,
            'create',
            'movement',
            null,
            json_encode($input)
        ]);
        
        $db->commit();
        
        sendSuccessResponse(['id' => (int)$movementId, 'message' => 'Movimentação registrada com sucesso']);
    } catch (PDOException $e) {
        $db->rollBack();
        sendErrorResponse('Failed to create movement: ' . $e->getMessage());
    }
}
?>