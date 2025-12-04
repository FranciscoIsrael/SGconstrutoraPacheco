<?php
// API endpoints for team members management
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
            getTeamByProject($db, $_GET['project_id']);
        } elseif (isset($_GET['id'])) {
            getTeamMember($db, $_GET['id']);
        } else {
            sendErrorResponse('Project ID is required');
        }
        break;
    
    case 'POST':
        createTeamMember($db);
        break;
    
    case 'PUT':
        if (isset($_GET['id'])) {
            updateTeamMember($db, $_GET['id']);
        } else {
            sendErrorResponse('ID is required for update');
        }
        break;
    
    case 'DELETE':
        if (isset($_GET['id'])) {
            deleteTeamMember($db, $_GET['id']);
        } else {
            sendErrorResponse('ID is required for deletion');
        }
        break;
    
    default:
        sendErrorResponse('Method not allowed', 405);
}

function getTeamByProject($db, $projectId) {
    try {
        $stmt = $db->prepare("
            SELECT tm.*, p.name as project_name,
                   COALESCE((SELECT SUM(te.hours_worked) FROM time_entries te WHERE te.team_member_id = tm.id), 0) as total_hours,
                   COALESCE((SELECT SUM(te.days_worked) FROM time_entries te WHERE te.team_member_id = tm.id), 0) as total_days
            FROM team_members tm 
            JOIN projects p ON tm.project_id = p.id 
            WHERE tm.project_id = ? 
            ORDER BY tm.created_at DESC
        ");
        $stmt->execute([$projectId]);
        $teamMembers = $stmt->fetchAll();
        
        foreach ($teamMembers as &$member) {
            $member['hourly_rate'] = (float)$member['hourly_rate'];
            $member['daily_rate'] = (float)($member['daily_rate'] ?? 0);
            $member['contract_value'] = (float)($member['contract_value'] ?? 0);
            $member['total_hours'] = (float)$member['total_hours'];
            $member['total_days'] = (float)$member['total_days'];
            
            // Calculate total payment
            if ($member['payment_type'] === 'diaria') {
                $member['total_payment'] = $member['total_days'] * $member['daily_rate'];
            } else {
                $member['total_payment'] = $member['contract_value'];
            }
        }
        
        sendSuccessResponse($teamMembers);
    } catch (PDOException $e) {
        sendErrorResponse('Failed to fetch team members: ' . $e->getMessage());
    }
}

function getTeamMember($db, $id) {
    try {
        $stmt = $db->prepare("SELECT * FROM team_members WHERE id = ?");
        $stmt->execute([$id]);
        $member = $stmt->fetch();
        
        if ($member) {
            $member['hourly_rate'] = (float)$member['hourly_rate'];
            sendSuccessResponse($member);
        } else {
            sendErrorResponse('Team member not found', 404);
        }
    } catch (PDOException $e) {
        sendErrorResponse('Failed to fetch team member: ' . $e->getMessage());
    }
}

function createTeamMember($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $required = ['project_id', 'name', 'role'];
    if (!validateRequired($required, $input)) {
        sendErrorResponse('All fields are required');
    }
    
    try {
        $stmt = $db->prepare("
            INSERT INTO team_members (project_id, name, role, hourly_rate, payment_type, daily_rate, contract_value, payment_description) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?) RETURNING id
        ");
        $stmt->execute([
            $input['project_id'],
            sanitizeInput($input['name']),
            sanitizeInput($input['role']),
            $input['hourly_rate'] ?? 0,
            $input['payment_type'] ?? 'diaria',
            $input['daily_rate'] ?? 0,
            $input['contract_value'] ?? 0,
            $input['payment_description'] ?? ''
        ]);
        
        $memberId = $stmt->fetchColumn();
        sendSuccessResponse(['id' => $memberId, 'message' => 'Team member added successfully']);
    } catch (PDOException $e) {
        sendErrorResponse('Failed to add team member: ' . $e->getMessage());
    }
}

function updateTeamMember($db, $id) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $required = ['name', 'role'];
    if (!validateRequired($required, $input)) {
        sendErrorResponse('All fields are required');
    }
    
    try {
        $stmt = $db->prepare("
            UPDATE team_members 
            SET name = ?, role = ?, hourly_rate = ?, payment_type = ?, 
                daily_rate = ?, contract_value = ?, payment_description = ?, 
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([
            sanitizeInput($input['name']),
            sanitizeInput($input['role']),
            $input['hourly_rate'] ?? 0,
            $input['payment_type'] ?? 'diaria',
            $input['daily_rate'] ?? 0,
            $input['contract_value'] ?? 0,
            $input['payment_description'] ?? '',
            $id
        ]);
        
        if ($stmt->rowCount() > 0) {
            sendSuccessResponse(['message' => 'Team member updated successfully']);
        } else {
            sendErrorResponse('Team member not found', 404);
        }
    } catch (PDOException $e) {
        sendErrorResponse('Failed to update team member: ' . $e->getMessage());
    }
}

function deleteTeamMember($db, $id) {
    try {
        $stmt = $db->prepare("DELETE FROM team_members WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() > 0) {
            sendSuccessResponse(['message' => 'Team member removed successfully']);
        } else {
            sendErrorResponse('Team member not found', 404);
        }
    } catch (PDOException $e) {
        sendErrorResponse('Failed to remove team member: ' . $e->getMessage());
    }
}
?>