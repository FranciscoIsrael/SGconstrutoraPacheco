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
        if (isset($_GET['member_id'])) {
            getAssignmentsByMember($db, $_GET['member_id']);
        } elseif (isset($_GET['project_id'])) {
            getAssignmentsByProject($db, $_GET['project_id']);
        } else {
            sendErrorResponse('member_id ou project_id é obrigatório');
        }
        break;
    
    case 'POST':
        createAssignment($db);
        break;
    
    case 'PUT':
        if (isset($_GET['id'])) {
            updateAssignment($db, $_GET['id']);
        } else {
            sendErrorResponse('ID é obrigatório');
        }
        break;
    
    case 'DELETE':
        if (isset($_GET['id'])) {
            deleteAssignment($db, $_GET['id']);
        } else {
            sendErrorResponse('ID é obrigatório');
        }
        break;
    
    default:
        sendErrorResponse('Método não permitido', 405);
}

function getAssignmentsByMember($db, $memberId) {
    try {
        $stmt = $db->prepare("
            SELECT pta.*, p.name as project_name, p.status as project_status, p.budget
            FROM project_team_assignments pta
            JOIN projects p ON pta.project_id = p.id
            WHERE pta.team_member_id = ?
            ORDER BY pta.created_at DESC
        ");
        $stmt->execute([$memberId]);
        $assignments = $stmt->fetchAll();
        
        foreach ($assignments as &$a) {
            $a['payment_value'] = (float)$a['payment_value'];
        }
        
        sendSuccessResponse($assignments);
    } catch (PDOException $e) {
        sendErrorResponse('Erro ao buscar atribuições: ' . $e->getMessage());
    }
}

function getAssignmentsByProject($db, $projectId) {
    try {
        $stmt = $db->prepare("
            SELECT pta.*, tm.name as member_name, tm.phone, tm.email, tm.image_path as member_image
            FROM project_team_assignments pta
            JOIN team_members tm ON pta.team_member_id = tm.id
            WHERE pta.project_id = ?
            ORDER BY pta.created_at DESC
        ");
        $stmt->execute([$projectId]);
        $assignments = $stmt->fetchAll();
        
        foreach ($assignments as &$a) {
            $a['payment_value'] = (float)$a['payment_value'];
        }
        
        sendSuccessResponse($assignments);
    } catch (PDOException $e) {
        sendErrorResponse('Erro ao buscar atribuições: ' . $e->getMessage());
    }
}

function createAssignment($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $required = ['team_member_id', 'project_id'];
    if (!validateRequired($required, $input)) {
        sendErrorResponse('team_member_id e project_id são obrigatórios');
    }
    
    try {
        $checkStmt = $db->prepare("SELECT id FROM project_team_assignments WHERE team_member_id = ? AND project_id = ?");
        $checkStmt->execute([$input['team_member_id'], $input['project_id']]);
        if ($checkStmt->fetch()) {
            sendErrorResponse('Este membro já está atribuído a esta obra');
            return;
        }
        
        $stmt = $db->prepare("
            INSERT INTO project_team_assignments (team_member_id, project_id, payment_type, payment_value, role, start_date, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?) RETURNING id
        ");
        $stmt->execute([
            $input['team_member_id'],
            $input['project_id'],
            sanitizeInput($input['payment_type'] ?? null),
            $input['payment_value'] ?? 0,
            sanitizeInput($input['role'] ?? null),
            $input['start_date'] ?? date('Y-m-d'),
            sanitizeInput($input['notes'] ?? null)
        ]);
        
        $assignmentId = $stmt->fetchColumn();
        logAudit($db, 'project_team_assignments', $assignmentId, 'create', null, $input);
        
        sendSuccessResponse(['id' => $assignmentId, 'message' => 'Membro atribuído com sucesso']);
    } catch (PDOException $e) {
        sendErrorResponse('Erro ao criar atribuição: ' . $e->getMessage());
    }
}

function updateAssignment($db, $id) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    try {
        $oldStmt = $db->prepare("SELECT * FROM project_team_assignments WHERE id = ?");
        $oldStmt->execute([$id]);
        $oldData = $oldStmt->fetch();
        
        $stmt = $db->prepare("
            UPDATE project_team_assignments 
            SET payment_type = ?, payment_value = ?, role = ?, start_date = ?, end_date = ?, notes = ?
            WHERE id = ?
        ");
        $stmt->execute([
            sanitizeInput($input['payment_type'] ?? null),
            $input['payment_value'] ?? 0,
            sanitizeInput($input['role'] ?? null),
            $input['start_date'] ?? null,
            $input['end_date'] ?? null,
            sanitizeInput($input['notes'] ?? null),
            $id
        ]);
        
        if ($stmt->rowCount() > 0) {
            logAudit($db, 'project_team_assignments', $id, 'update', $oldData, $input);
            sendSuccessResponse(['message' => 'Atribuição atualizada com sucesso']);
        } else {
            sendErrorResponse('Atribuição não encontrada', 404);
        }
    } catch (PDOException $e) {
        sendErrorResponse('Erro ao atualizar atribuição: ' . $e->getMessage());
    }
}

function deleteAssignment($db, $id) {
    try {
        $stmt = $db->prepare("SELECT * FROM project_team_assignments WHERE id = ?");
        $stmt->execute([$id]);
        $assignment = $stmt->fetch();
        
        if ($assignment) {
            $deleteStmt = $db->prepare("DELETE FROM project_team_assignments WHERE id = ?");
            $deleteStmt->execute([$id]);
            
            logAudit($db, 'project_team_assignments', $id, 'delete', $assignment, null);
            sendSuccessResponse(['message' => 'Atribuição removida com sucesso']);
        } else {
            sendErrorResponse('Atribuição não encontrada', 404);
        }
    } catch (PDOException $e) {
        sendErrorResponse('Erro ao remover atribuição: ' . $e->getMessage());
    }
}
?>
