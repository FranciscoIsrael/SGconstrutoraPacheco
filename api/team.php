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
        if (isset($_GET['id'])) {
            getTeamMember($db, $_GET['id']);
        } elseif (isset($_GET['project_id'])) {
            getTeamByProject($db, $_GET['project_id']);
        } else {
            getAllTeamMembers($db);
        }
        break;
    
    case 'POST':
        createTeamMember($db);
        break;
    
    case 'PUT':
        if (isset($_GET['id'])) {
            updateTeamMember($db, $_GET['id']);
        } else {
            sendErrorResponse('ID é obrigatório');
        }
        break;
    
    case 'DELETE':
        if (isset($_GET['id'])) {
            deleteTeamMember($db, $_GET['id']);
        } else {
            sendErrorResponse('ID é obrigatório');
        }
        break;
    
    default:
        sendErrorResponse('Método não permitido', 405);
}

function getAllTeamMembers($db) {
    try {
        $stmt = $db->query("
            SELECT tm.*, 
                   (SELECT string_agg(p.name, ', ') 
                    FROM projects p 
                    WHERE p.id = tm.project_id) as project_names
            FROM team_members tm 
            ORDER BY tm.name ASC
        ");
        $teamMembers = $stmt->fetchAll();
        
        foreach ($teamMembers as &$member) {
            $member['payment_value'] = (float)($member['payment_value'] ?? 0);
            $member['images'] = getEntityImages($db, 'team_members', $member['id']);
        }
        
        sendSuccessResponse($teamMembers);
    } catch (PDOException $e) {
        sendErrorResponse('Erro ao buscar equipe: ' . $e->getMessage());
    }
}

function getTeamByProject($db, $projectId) {
    try {
        $stmt = $db->prepare("
            SELECT DISTINCT tm.*, p.name as project_name,
                   COALESCE(pta.payment_type, tm.payment_type) as assignment_payment_type,
                   COALESCE(pta.payment_value, tm.payment_value) as assignment_payment_value,
                   COALESCE(pta.role, tm.role) as assignment_role
            FROM team_members tm 
            LEFT JOIN projects p ON p.id = ?
            LEFT JOIN project_team_assignments pta ON pta.team_member_id = tm.id AND pta.project_id = ?
            WHERE tm.project_id = ? OR pta.project_id = ?
            ORDER BY tm.name ASC
        ");
        $stmt->execute([$projectId, $projectId, $projectId, $projectId]);
        $teamMembers = $stmt->fetchAll();
        
        foreach ($teamMembers as &$member) {
            $member['payment_value'] = (float)($member['assignment_payment_value'] ?? $member['payment_value'] ?? 0);
            $member['payment_type'] = $member['assignment_payment_type'] ?? $member['payment_type'];
            $member['role'] = $member['assignment_role'] ?? $member['role'];
            $member['images'] = getEntityImages($db, 'team_members', $member['id']);
        }
        
        sendSuccessResponse($teamMembers);
    } catch (PDOException $e) {
        sendErrorResponse('Erro ao buscar equipe: ' . $e->getMessage());
    }
}

function getTeamMember($db, $id) {
    try {
        $stmt = $db->prepare("SELECT * FROM team_members WHERE id = ?");
        $stmt->execute([$id]);
        $member = $stmt->fetch();
        
        if ($member) {
            $member['payment_value'] = (float)($member['payment_value'] ?? 0);
            $member['images'] = getEntityImages($db, 'team_members', $member['id']);
            $member['history'] = getEntityHistory($db, 'team_members', $member['id']);
            sendSuccessResponse($member);
        } else {
            sendErrorResponse('Membro não encontrado', 404);
        }
    } catch (PDOException $e) {
        sendErrorResponse('Erro ao buscar membro: ' . $e->getMessage());
    }
}

function createTeamMember($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['name'])) {
        sendErrorResponse('Nome é obrigatório');
    }
    
    try {
        $stmt = $db->prepare("
            INSERT INTO team_members (
                name, cpf_cnpj, role, payment_type, payment_value, 
                description, address, phone, image_path, project_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id
        ");
        $stmt->execute([
            sanitizeInput($input['name']),
            sanitizeInput($input['cpf_cnpj'] ?? ''),
            sanitizeInput($input['role'] ?? ''),
            sanitizeInput($input['payment_type'] ?? 'diaria'),
            $input['payment_value'] ?? 0,
            sanitizeInput($input['description'] ?? ''),
            sanitizeInput($input['address'] ?? ''),
            sanitizeInput($input['phone'] ?? ''),
            $input['image_path'] ?? null,
            $input['project_id'] ?? null
        ]);
        
        $memberId = $stmt->fetchColumn();
        logAudit($db, 'team_members', $memberId, 'create', null, $input);
        sendSuccessResponse(['id' => $memberId, 'message' => 'Membro adicionado com sucesso']);
    } catch (PDOException $e) {
        sendErrorResponse('Erro ao adicionar membro: ' . $e->getMessage());
    }
}

function updateTeamMember($db, $id) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['name'])) {
        sendErrorResponse('Nome é obrigatório');
    }
    
    try {
        $oldStmt = $db->prepare("SELECT * FROM team_members WHERE id = ?");
        $oldStmt->execute([$id]);
        $oldData = $oldStmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $db->prepare("
            UPDATE team_members SET 
                name = ?, cpf_cnpj = ?, role = ?, payment_type = ?, payment_value = ?,
                description = ?, address = ?, phone = ?, 
                image_path = COALESCE(?, image_path), project_id = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([
            sanitizeInput($input['name']),
            sanitizeInput($input['cpf_cnpj'] ?? ''),
            sanitizeInput($input['role'] ?? ''),
            sanitizeInput($input['payment_type'] ?? 'diaria'),
            $input['payment_value'] ?? 0,
            sanitizeInput($input['description'] ?? ''),
            sanitizeInput($input['address'] ?? ''),
            sanitizeInput($input['phone'] ?? ''),
            $input['image_path'] ?? null,
            $input['project_id'] ?? null,
            $id
        ]);
        
        if ($stmt->rowCount() > 0) {
            logAudit($db, 'team_members', $id, 'update', $oldData, $input);
            sendSuccessResponse(['message' => 'Membro atualizado com sucesso']);
        } else {
            sendErrorResponse('Membro não encontrado', 404);
        }
    } catch (PDOException $e) {
        sendErrorResponse('Erro ao atualizar membro: ' . $e->getMessage());
    }
}

function deleteTeamMember($db, $id) {
    try {
        $oldStmt = $db->prepare("SELECT * FROM team_members WHERE id = ?");
        $oldStmt->execute([$id]);
        $oldData = $oldStmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $db->prepare("DELETE FROM team_members WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() > 0) {
            logAudit($db, 'team_members', $id, 'delete', $oldData, null);
            sendSuccessResponse(['message' => 'Membro removido com sucesso']);
        } else {
            sendErrorResponse('Membro não encontrado', 404);
        }
    } catch (PDOException $e) {
        sendErrorResponse('Erro ao remover membro: ' . $e->getMessage());
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

function getEntityHistory($db, $tableName, $recordId) {
    try {
        $stmt = $db->prepare("SELECT * FROM audit_history WHERE table_name = ? AND record_id = ? ORDER BY created_at DESC LIMIT 20");
        $stmt->execute([$tableName, $recordId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}
?>
