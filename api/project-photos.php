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
        
        if ($projectId) {
            $stmt = $db->prepare("SELECT * FROM project_photos WHERE project_id = ? ORDER BY photo_date DESC, created_at DESC");
            $stmt->execute([$projectId]);
        } else {
            $stmt = $db->query("SELECT pp.*, p.name as project_name 
                               FROM project_photos pp 
                               JOIN projects p ON pp.project_id = p.id 
                               ORDER BY pp.created_at DESC LIMIT 50");
        }
        echo json_encode($stmt->fetchAll());
        break;

    case 'POST':
        $projectId = isset($_POST['project_id']) ? (int)$_POST['project_id'] : null;
        $description = $_POST['description'] ?? '';
        $photoDate = $_POST['photo_date'] ?? date('Y-m-d');
        
        if (!$projectId) {
            http_response_code(400);
            echo json_encode(['error' => 'ID do projeto é obrigatório']);
            exit;
        }
        
        $uploadedImages = [];
        
        // Handle multiple file uploads
        if (isset($_FILES['images'])) {
            $files = $_FILES['images'];
            $uploadDir = __DIR__ . '/../uploads/projects/';
            
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Handle both single and multiple files
            if (is_array($files['name'])) {
                $fileCount = count($files['name']);
                for ($i = 0; $i < $fileCount; $i++) {
                    if ($files['error'][$i] === UPLOAD_ERR_OK) {
                        $ext = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
                        $filename = 'project_' . $projectId . '_' . time() . '_' . $i . '.' . $ext;
                        $filepath = $uploadDir . $filename;
                        
                        if (move_uploaded_file($files['tmp_name'][$i], $filepath)) {
                            $imagePath = 'uploads/projects/' . $filename;
                            
                            $stmt = $db->prepare("INSERT INTO project_photos (project_id, image_path, description, photo_date) 
                                                  VALUES (?, ?, ?, ?) RETURNING id");
                            $stmt->execute([$projectId, $imagePath, $description, $photoDate]);
                            $result = $stmt->fetch();
                            $uploadedImages[] = ['id' => $result['id'], 'path' => $imagePath];
                        }
                    }
                }
            } else {
                if ($files['error'] === UPLOAD_ERR_OK) {
                    $ext = pathinfo($files['name'], PATHINFO_EXTENSION);
                    $filename = 'project_' . $projectId . '_' . time() . '.' . $ext;
                    $filepath = $uploadDir . $filename;
                    
                    if (move_uploaded_file($files['tmp_name'], $filepath)) {
                        $imagePath = 'uploads/projects/' . $filename;
                        
                        $stmt = $db->prepare("INSERT INTO project_photos (project_id, image_path, description, photo_date) 
                                              VALUES (?, ?, ?, ?) RETURNING id");
                        $stmt->execute([$projectId, $imagePath, $description, $photoDate]);
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
        
        // Create notification
        $projStmt = $db->prepare("SELECT name FROM projects WHERE id = ?");
        $projStmt->execute([$projectId]);
        $project = $projStmt->fetch();
        
        $notifStmt = $db->prepare("INSERT INTO notifications (project_id, type, title, message) VALUES (?, ?, ?, ?)");
        $notifStmt->execute([
            $projectId,
            'photo',
            'Novas fotos adicionadas',
            count($uploadedImages) . ' foto(s) adicionada(s) à obra: ' . $project['name']
        ]);
        
        echo json_encode(['success' => true, 'images' => $uploadedImages]);
        break;

    case 'DELETE':
        $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID da foto é obrigatório']);
            exit;
        }
        
        // Get image path before deleting
        $stmt = $db->prepare("SELECT image_path FROM project_photos WHERE id = ?");
        $stmt->execute([$id]);
        $photo = $stmt->fetch();
        
        if ($photo && file_exists(__DIR__ . '/../' . $photo['image_path'])) {
            unlink(__DIR__ . '/../' . $photo['image_path']);
        }
        
        $stmt = $db->prepare("DELETE FROM project_photos WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Método não permitido']);
}
?>
