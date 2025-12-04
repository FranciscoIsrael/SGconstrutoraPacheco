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
        $memberId = isset($_GET['member_id']) ? (int)$_GET['member_id'] : null;
        $projectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : null;
        $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : null;
        $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : null;
        
        $sql = "SELECT te.*, tm.name as member_name, tm.role, tm.payment_type, 
                       tm.daily_rate, tm.contract_value, p.name as project_name
                FROM time_entries te
                JOIN team_members tm ON te.team_member_id = tm.id
                JOIN projects p ON te.project_id = p.id
                WHERE 1=1";
        $params = [];
        
        if ($memberId) {
            $sql .= " AND te.team_member_id = ?";
            $params[] = $memberId;
        }
        if ($projectId) {
            $sql .= " AND te.project_id = ?";
            $params[] = $projectId;
        }
        if ($startDate) {
            $sql .= " AND te.work_date >= ?";
            $params[] = $startDate;
        }
        if ($endDate) {
            $sql .= " AND te.work_date <= ?";
            $params[] = $endDate;
        }
        
        $sql .= " ORDER BY te.work_date DESC, te.created_at DESC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $entries = $stmt->fetchAll();
        
        // Calculate totals for each member
        $totals = [];
        foreach ($entries as $entry) {
            $mid = $entry['team_member_id'];
            if (!isset($totals[$mid])) {
                $totals[$mid] = [
                    'member_id' => $mid,
                    'member_name' => $entry['member_name'],
                    'total_hours' => 0,
                    'total_days' => 0,
                    'payment_type' => $entry['payment_type'],
                    'daily_rate' => $entry['daily_rate'],
                    'total_value' => 0
                ];
            }
            $totals[$mid]['total_hours'] += (float)$entry['hours_worked'];
            $totals[$mid]['total_days'] += (float)$entry['days_worked'];
            
            if ($entry['payment_type'] === 'diaria') {
                $totals[$mid]['total_value'] += (float)$entry['days_worked'] * (float)$entry['daily_rate'];
            }
        }
        
        echo json_encode([
            'entries' => $entries,
            'totals' => array_values($totals)
        ]);
        break;

    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['team_member_id']) || !isset($data['project_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Membro e projeto são obrigatórios']);
            exit;
        }
        
        $stmt = $db->prepare("INSERT INTO time_entries (team_member_id, project_id, work_date, hours_worked, days_worked, description) 
                              VALUES (?, ?, ?, ?, ?, ?) RETURNING id");
        $stmt->execute([
            $data['team_member_id'],
            $data['project_id'],
            $data['work_date'] ?? date('Y-m-d'),
            $data['hours_worked'] ?? 0,
            $data['days_worked'] ?? 0,
            $data['description'] ?? ''
        ]);
        
        $result = $stmt->fetch();
        echo json_encode(['success' => true, 'id' => $result['id']]);
        break;

    case 'PUT':
        $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID do registro é obrigatório']);
            exit;
        }
        
        $stmt = $db->prepare("UPDATE time_entries 
                              SET work_date = ?, hours_worked = ?, days_worked = ?, description = ?
                              WHERE id = ?");
        $stmt->execute([
            $data['work_date'] ?? date('Y-m-d'),
            $data['hours_worked'] ?? 0,
            $data['days_worked'] ?? 0,
            $data['description'] ?? '',
            $id
        ]);
        
        echo json_encode(['success' => true]);
        break;

    case 'DELETE':
        $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID do registro é obrigatório']);
            exit;
        }
        
        $stmt = $db->prepare("DELETE FROM time_entries WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Método não permitido']);
}
?>
