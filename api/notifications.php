<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $projectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : null;
        $type = isset($_GET['type']) ? $_GET['type'] : null;
        $isRead = isset($_GET['is_read']) ? ($_GET['is_read'] === 'true') : null;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
        
        $sql = "SELECT n.*, p.name as project_name 
                FROM notifications n 
                LEFT JOIN projects p ON n.project_id = p.id 
                WHERE 1=1";
        $params = [];
        
        if ($projectId) {
            $sql .= " AND n.project_id = ?";
            $params[] = $projectId;
        }
        if ($type) {
            $sql .= " AND n.type = ?";
            $params[] = $type;
        }
        if ($isRead !== null) {
            $sql .= " AND n.is_read = ?";
            $params[] = $isRead;
        }
        
        $sql .= " ORDER BY n.created_at DESC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        echo json_encode($stmt->fetchAll());
        break;

    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['title']) || !isset($data['message']) || !isset($data['type'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Título, mensagem e tipo são obrigatórios']);
            exit;
        }
        
        $stmt = $db->prepare("INSERT INTO notifications (project_id, type, title, message) 
                              VALUES (?, ?, ?, ?) RETURNING id");
        $stmt->execute([
            $data['project_id'] ?? null,
            $data['type'],
            $data['title'],
            $data['message']
        ]);
        
        $result = $stmt->fetch();
        echo json_encode(['success' => true, 'id' => $result['id']]);
        break;

    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID da notificação é obrigatório']);
            exit;
        }
        
        if (isset($data['mark_all_read']) && $data['mark_all_read']) {
            $stmt = $db->prepare("UPDATE notifications SET is_read = TRUE WHERE is_read = FALSE");
            $stmt->execute();
            echo json_encode(['success' => true, 'message' => 'Todas notificações marcadas como lidas']);
        } else {
            $stmt = $db->prepare("UPDATE notifications SET is_read = TRUE WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
        }
        break;

    case 'DELETE':
        $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID da notificação é obrigatório']);
            exit;
        }
        
        $stmt = $db->prepare("DELETE FROM notifications WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Método não permitido']);
}

function createNotification($db, $projectId, $type, $title, $message) {
    $stmt = $db->prepare("INSERT INTO notifications (project_id, type, title, message) VALUES (?, ?, ?, ?)");
    $stmt->execute([$projectId, $type, $title, $message]);
}
?>
