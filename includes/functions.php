<?php
// Helper functions for the construction management system

function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function validateRequired($fields, $data) {
    foreach ($fields as $field) {
        if (empty($data[$field])) {
            return false;
        }
    }
    return true;
}

function formatCurrency($amount) {
    return 'R$ ' . number_format($amount, 2, ',', '.');
}

function formatDate($date) {
    return date('d/m/Y', strtotime($date));
}

function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function sendErrorResponse($message, $statusCode = 400) {
    sendJsonResponse(['error' => $message], $statusCode);
}

function sendSuccessResponse($data = []) {
    sendJsonResponse(['success' => true, 'data' => $data]);
}

function logAudit($db, $tableName, $recordId, $action, $oldValues = null, $newValues = null) {
    try {
        $stmt = $db->prepare("
            INSERT INTO audit_history (table_name, record_id, action, old_values, new_values, created_at)
            VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([
            $tableName,
            $recordId,
            $action,
            $oldValues ? json_encode($oldValues) : null,
            $newValues ? json_encode($newValues) : null
        ]);
    } catch (PDOException $e) {
        error_log("Audit log error: " . $e->getMessage());
    }
}
?>