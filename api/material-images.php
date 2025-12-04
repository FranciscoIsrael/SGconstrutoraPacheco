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
        $materialId = isset($_GET['material_id']) ? (int)$_GET['material_id'] : null;
        $projectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : null;
        
        $sql = "SELECT mi.*, m.name as material_name, p.name as project_name
                FROM material_images mi
                JOIN materials m ON mi.material_id = m.id
                LEFT JOIN projects p ON mi.project_id = p.id
                WHERE 1=1";
        $params = [];
        
        if ($materialId) {
            $sql .= " AND mi.material_id = ?";
            $params[] = $materialId;
        }
        if ($projectId) {
            $sql .= " AND mi.project_id = ?";
            $params[] = $projectId;
        }
        
        $sql .= " ORDER BY mi.created_at DESC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        echo json_encode($stmt->fetchAll());
        break;

    case 'POST':
        $materialId = isset($_POST['material_id']) ? (int)$_POST['material_id'] : null;
        $projectId = isset($_POST['project_id']) ? (int)$_POST['project_id'] : null;
        $description = $_POST['description'] ?? '';
        
        if (!$materialId) {
            http_response_code(400);
            echo json_encode(['error' => 'ID do material é obrigatório']);
            exit;
        }
        
        $uploadedImages = [];
        
        if (isset($_FILES['images'])) {
            $files = $_FILES['images'];
            $uploadDir = __DIR__ . '/../uploads/materials/';
            
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            if (is_array($files['name'])) {
                $fileCount = count($files['name']);
                for ($i = 0; $i < $fileCount; $i++) {
                    if ($files['error'][$i] === UPLOAD_ERR_OK) {
                        $ext = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
                        $filename = 'material_' . $materialId . '_' . time() . '_' . $i . '.' . $ext;
                        $filepath = $uploadDir . $filename;
                        
                        if (move_uploaded_file($files['tmp_name'][$i], $filepath)) {
                            $imagePath = 'uploads/materials/' . $filename;
                            
                            $stmt = $db->prepare("INSERT INTO material_images (material_id, project_id, image_path, description) 
                                                  VALUES (?, ?, ?, ?) RETURNING id");
                            $stmt->execute([$materialId, $projectId, $imagePath, $description]);
                            $result = $stmt->fetch();
                            $uploadedImages[] = ['id' => $result['id'], 'path' => $imagePath];
                        }
                    }
                }
            } else {
                if ($files['error'] === UPLOAD_ERR_OK) {
                    $ext = pathinfo($files['name'], PATHINFO_EXTENSION);
                    $filename = 'material_' . $materialId . '_' . time() . '.' . $ext;
                    $filepath = $uploadDir . $filename;
                    
                    if (move_uploaded_file($files['tmp_name'], $filepath)) {
                        $imagePath = 'uploads/materials/' . $filename;
                        
                        $stmt = $db->prepare("INSERT INTO material_images (material_id, project_id, image_path, description) 
                                              VALUES (?, ?, ?, ?) RETURNING id");
                        $stmt->execute([$materialId, $projectId, $imagePath, $description]);
                        $result = $stmt->fetch();
                        $uploadedImages[] = ['id' => $result['id'], 'path' => $imagePath];
                    }
                }
            }
        }
        
        if (empty($uploadedImages)) {
            http_response_code(400);
            echo json_encode(['error' => 'Nenhuma imagem foi enviada']);
            exit;
        }
        
        echo json_encode(['success' => true, 'images' => $uploadedImages]);
        break;

    case 'DELETE':
        $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID da imagem é obrigatório']);
            exit;
        }
        
        $stmt = $db->prepare("SELECT image_path FROM material_images WHERE id = ?");
        $stmt->execute([$id]);
        $image = $stmt->fetch();
        
        if ($image && file_exists(__DIR__ . '/../' . $image['image_path'])) {
            unlink(__DIR__ . '/../' . $image['image_path']);
        }
        
        $stmt = $db->prepare("DELETE FROM material_images WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Método não permitido']);
}
?>
