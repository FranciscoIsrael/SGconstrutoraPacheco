<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

switch ($method) {

    case 'GET':
        if (isset($_GET['member_id'])) {
            getByMember($db, $_GET['member_id']);
        } elseif (isset($_GET['project_id'])) {
            getByProject($db, $_GET['project_id']);
        } else {
            sendErrorResponse('member_id ou project_id é obrigatório');
        }
        break;

    case 'POST':
        createAssignment($db);
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

/* ================= FUNÇÕES ================= */

function getByMember($db, $memberId) {
    try {
        $stmt = $db->prepare("
            SELECT tmp.id, p.id AS project_id, p.name AS project_name, p.status
            FROM team_member_projects tmp
            JOIN projects p ON p.id = tmp.project_id
            WHERE tmp.team_member_id = ?
            ORDER BY tmp.assigned_at DESC
        ");
        $stmt->execute([$memberId]);
        sendSuccessResponse($stmt->fetchAll());
    } catch (PDOException $e) {
        sendErrorResponse($e->getMessage());
    }
}

function getByProject($db, $projectId) {
    try {
        $stmt = $db->prepare("
            SELECT tmp.id, tm.id AS member_id, tm.name, tm.phone, tm.role
            FROM team_member_projects tmp
            JOIN team_members tm ON tm.id = tmp.team_member_id
            WHERE tmp.project_id = ?
            ORDER BY tmp.assigned_at DESC
        ");
        $stmt->execute([$projectId]);
        sendSuccessResponse($stmt->fetchAll());
    } catch (PDOException $e) {
        sendErrorResponse($e->getMessage());
    }
}

function createAssignment($db) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['team_member_id']) || empty($input['project_id'])) {
        sendErrorResponse('team_member_id e project_id são obrigatórios');
    }

    try {
        // evita duplicação
        $check = $db->prepare("
            SELECT 1 FROM team_member_projects 
            WHERE team_member_id = ? AND project_id = ?
        ");
        $check->execute([$input['team_member_id'], $input['project_id']]);

        if ($check->fetch()) {
            sendErrorResponse('Membro já vinculado a esta obra');
            return;
        }

        $stmt = $db->prepare("
            INSERT INTO team_member_projects (team_member_id, project_id)
            VALUES (?, ?)
            RETURNING id
        ");
        $stmt->execute([
            $input['team_member_id'],
            $input['project_id']
        ]);

        $id = $stmt->fetchColumn();

        logAudit($db, 'team_member_projects', $id, 'create', null, $input);

        sendSuccessResponse([
            'id' => $id,
            'message' => 'Membro vinculado à obra com sucesso'
        ]);

    } catch (PDOException $e) {
        sendErrorResponse($e->getMessage());
    }
}

function deleteAssignment($db, $id) {
    try {
        $stmt = $db->prepare("DELETE FROM team_member_projects WHERE id = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            sendErrorResponse('Vínculo não encontrado', 404);
        }

        logAudit($db, 'team_member_projects', $id, 'delete', null, null);
        sendSuccessResponse(['message' => 'Vínculo removido com sucesso']);

    } catch (PDOException $e) {
        sendErrorResponse($e->getMessage());
    }
}
?>
