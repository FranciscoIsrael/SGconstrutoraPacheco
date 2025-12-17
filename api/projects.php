<?php
// API endpoints for project management
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
            getProject($db, $_GET['id']);
        } else {
            getAllProjects($db);
        }
        break;
    
    case 'POST':
        createProject($db);
        break;
    
    case 'PUT':
        if (isset($_GET['id'])) {
            updateProject($db, $_GET['id']);
        } else {
            sendErrorResponse('ID is required for update');
        }
        break;
    
    case 'DELETE':
        if (isset($_GET['id'])) {
            deleteProject($db, $_GET['id']);
        } else {
            sendErrorResponse('ID is required for deletion');
        }
        break;
    
    default:
        sendErrorResponse('Method not allowed', 405);
}

function getAllProjects($db) {
    try {
        $stmt = $db->query("
            SELECT p.id, p.name, p.description, p.status, p.start_date, p.end_date, 
                   p.budget, p.responsible, p.image_path, p.created_at, p.updated_at,
                   COALESCE(SUM(CASE WHEN t.type = 'expense' THEN t.amount ELSE 0 END), 0) as total_spent,
                   COUNT(DISTINCT tm.id) as team_count,
                   COUNT(DISTINCT m.id) as materials_count
            FROM projects p
            LEFT JOIN transactions t ON p.id = t.project_id
            LEFT JOIN team_members tm ON p.id = tm.project_id
            LEFT JOIN materials m ON p.id = m.project_id
            GROUP BY p.id, p.name, p.description, p.status, p.start_date, p.end_date, 
                     p.budget, p.responsible, p.image_path, p.created_at, p.updated_at
            ORDER BY p.created_at DESC
        ");
        $projects = $stmt->fetchAll();
        
        foreach ($projects as &$project) {
            $project['budget'] = (float)$project['budget'];
            $project['total_spent'] = (float)$project['total_spent'];
        }
        
        sendSuccessResponse($projects);
    } catch (PDOException $e) {
        sendErrorResponse('Failed to fetch projects: ' . $e->getMessage());
    }
}

function getProject($db, $id) {
    try {
        $stmt = $db->prepare("SELECT * FROM projects WHERE id = ?");
        $stmt->execute([$id]);
        $project = $stmt->fetch();
        
        if ($project) {
            $project['budget'] = (float)$project['budget'];
            sendSuccessResponse($project);
        } else {
            sendErrorResponse('Project not found', 404);
        }
    } catch (PDOException $e) {
        sendErrorResponse('Failed to fetch project: ' . $e->getMessage());
    }
}

function createProject($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $required = ['name'];
    if (!validateRequired($required, $input)) {
        sendErrorResponse('Nome é obrigatório');
    }
    
    try {
        $stmt = $db->prepare("
            INSERT INTO projects (name, description, status, start_date, end_date, budget, responsible, image_path) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?) RETURNING id
        ");
        $stmt->execute([
            sanitizeInput($input['name']),
            sanitizeInput($input['description'] ?? ''),
            sanitizeInput($input['status'] ?? 'active'),
            $input['start_date'] ?? null,
            $input['end_date'] ?? null,
            $input['budget'] ?? 0,
            sanitizeInput($input['responsible'] ?? ''),
            $input['image_path'] ?? null
        ]);
        
        $projectId = $stmt->fetchColumn();
        logAudit($db, 'projects', $projectId, 'create', null, $input);
        sendSuccessResponse(['id' => $projectId, 'message' => 'Obra criada com sucesso']);
    } catch (PDOException $e) {
        sendErrorResponse('Falha ao criar obra: ' . $e->getMessage());
    }
}

function updateProject($db, $id) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $required = ['name'];
    if (!validateRequired($required, $input)) {
        sendErrorResponse('Nome é obrigatório');
    }
    
    try {
        $oldStmt = $db->prepare("SELECT * FROM projects WHERE id = ?");
        $oldStmt->execute([$id]);
        $oldData = $oldStmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $db->prepare("
            UPDATE projects 
            SET name = ?, description = ?, status = ?, start_date = ?, end_date = ?, 
                budget = ?, responsible = ?, image_path = COALESCE(?, image_path), updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([
            sanitizeInput($input['name']),
            sanitizeInput($input['description'] ?? ''),
            sanitizeInput($input['status'] ?? 'active'),
            $input['start_date'] ?? null,
            $input['end_date'] ?? null,
            $input['budget'] ?? 0,
            sanitizeInput($input['responsible'] ?? ''),
            $input['image_path'] ?? null,
            $id
        ]);
        
        if ($stmt->rowCount() > 0) {
            logAudit($db, 'projects', $id, 'update', $oldData, $input);
            sendSuccessResponse(['message' => 'Obra atualizada com sucesso']);
        } else {
            sendErrorResponse('Obra não encontrada', 404);
        }
    } catch (PDOException $e) {
        sendErrorResponse('Falha ao atualizar obra: ' . $e->getMessage());
    }
}

function deleteProject($db, $id) {
    try {
        $stmt = $db->prepare("DELETE FROM projects WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() > 0) {
            sendSuccessResponse(['message' => 'Project deleted successfully']);
        } else {
            sendErrorResponse('Project not found', 404);
        }
    } catch (PDOException $e) {
        sendErrorResponse('Failed to delete project: ' . $e->getMessage());
    }
}
?>