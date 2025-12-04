<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
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
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        $after = isset($_GET['after']) ? (int)$_GET['after'] : 0;
        
        if (!$projectId) {
            http_response_code(400);
            echo json_encode(['error' => 'ID do projeto é obrigatório']);
            exit;
        }
        
        $sql = "SELECT cm.*, p.name as project_name 
                FROM chat_messages cm 
                JOIN projects p ON cm.project_id = p.id 
                WHERE cm.project_id = ?";
        $params = [$projectId];
        
        if ($after > 0) {
            $sql .= " AND cm.id > ?";
            $params[] = $after;
        }
        
        $sql .= " ORDER BY cm.created_at ASC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        echo json_encode($stmt->fetchAll());
        break;

    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['project_id']) || !isset($data['sender_name']) || !isset($data['message'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Projeto, remetente e mensagem são obrigatórios']);
            exit;
        }
        
        $stmt = $db->prepare("INSERT INTO chat_messages (project_id, sender_name, message) 
                              VALUES (?, ?, ?) RETURNING id, created_at");
        $stmt->execute([
            $data['project_id'],
            $data['sender_name'],
            $data['message']
        ]);
        
        $result = $stmt->fetch();
        
        // Create notification for new message
        $notifStmt = $db->prepare("INSERT INTO notifications (project_id, type, title, message) VALUES (?, ?, ?, ?)");
        $notifStmt->execute([
            $data['project_id'],
            'chat',
            'Nova mensagem de ' . $data['sender_name'],
            substr($data['message'], 0, 100) . (strlen($data['message']) > 100 ? '...' : '')
        ]);
        
        echo json_encode([
            'success' => true, 
            'id' => $result['id'],
            'created_at' => $result['created_at']
        ]);
        break;

    case 'DELETE':
        $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID da mensagem é obrigatório']);
            exit;
        }
        
        $stmt = $db->prepare("DELETE FROM chat_messages WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Método não permitido']);
}
?>
