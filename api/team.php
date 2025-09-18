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
            SELECT tm.*, p.name as project_name 
            FROM team_members tm 
            JOIN projects p ON tm.project_id = p.id 
            WHERE tm.project_id = ? 
            ORDER BY tm.created_at DESC
        ");
        $stmt->execute([$projectId]);
        $teamMembers = $stmt->fetchAll();
        
        foreach ($teamMembers as &$member) {
            $member['hourly_rate'] = (float)$member['hourly_rate'];
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
    
    $required = ['project_id', 'name', 'role', 'hourly_rate'];
    if (!validateRequired($required, $input)) {
        sendErrorResponse('All fields are required');
    }
    
    try {
        $stmt = $db->prepare("
            INSERT INTO team_members (project_id, name, role, hourly_rate) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $input['project_id'],
            sanitizeInput($input['name']),
            sanitizeInput($input['role']),
            $input['hourly_rate']
        ]);
        
        $memberId = $db->lastInsertId();
        sendSuccessResponse(['id' => $memberId, 'message' => 'Team member added successfully']);
    } catch (PDOException $e) {
        sendErrorResponse('Failed to add team member: ' . $e->getMessage());
    }
}

function updateTeamMember($db, $id) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $required = ['name', 'role', 'hourly_rate'];
    if (!validateRequired($required, $input)) {
        sendErrorResponse('All fields are required');
    }
    
    try {
        $stmt = $db->prepare("
            UPDATE team_members 
            SET name = ?, role = ?, hourly_rate = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([
            sanitizeInput($input['name']),
            sanitizeInput($input['role']),
            $input['hourly_rate'],
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