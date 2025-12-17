<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

if ($method !== 'GET') {
    sendErrorResponse('Método não permitido', 405);
}

if (isset($_GET['table_name']) && isset($_GET['record_id'])) {
    getRecordHistory($db, $_GET['table_name'], $_GET['record_id']);
} elseif (isset($_GET['table_name'])) {
    getTableHistory($db, $_GET['table_name']);
} else {
    getAllHistory($db);
}

function getAllHistory($db) {
    try {
        $stmt = $db->query("
            SELECT * FROM audit_history
            ORDER BY created_at DESC
            LIMIT 100
        ");
        $history = $stmt->fetchAll();
        
        foreach ($history as &$entry) {
            $entry['created_at_formatted'] = date('d/m/Y H:i:s', strtotime($entry['created_at']));
            $entry['old_values'] = $entry['old_values'] ? json_decode($entry['old_values'], true) : null;
            $entry['new_values'] = $entry['new_values'] ? json_decode($entry['new_values'], true) : null;
        }
        
        sendSuccessResponse($history);
    } catch (PDOException $e) {
        sendErrorResponse('Erro ao buscar histórico: ' . $e->getMessage());
    }
}

function getTableHistory($db, $table) {
    try {
        $stmt = $db->prepare("
            SELECT * FROM audit_history
            WHERE table_name = ?
            ORDER BY created_at DESC
            LIMIT 100
        ");
        $stmt->execute([$table]);
        $history = $stmt->fetchAll();
        
        foreach ($history as &$entry) {
            $entry['created_at_formatted'] = date('d/m/Y H:i:s', strtotime($entry['created_at']));
            $entry['old_values'] = $entry['old_values'] ? json_decode($entry['old_values'], true) : null;
            $entry['new_values'] = $entry['new_values'] ? json_decode($entry['new_values'], true) : null;
        }
        
        sendSuccessResponse($history);
    } catch (PDOException $e) {
        sendErrorResponse('Erro ao buscar histórico: ' . $e->getMessage());
    }
}

function getRecordHistory($db, $table, $recordId) {
    try {
        $stmt = $db->prepare("
            SELECT * FROM audit_history
            WHERE table_name = ? AND record_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$table, $recordId]);
        $history = $stmt->fetchAll();
        
        foreach ($history as &$entry) {
            $entry['created_at_formatted'] = date('d/m/Y H:i:s', strtotime($entry['created_at']));
            $entry['old_values'] = $entry['old_values'] ? json_decode($entry['old_values'], true) : null;
            $entry['new_values'] = $entry['new_values'] ? json_decode($entry['new_values'], true) : null;
        }
        
        sendSuccessResponse($history);
    } catch (PDOException $e) {
        sendErrorResponse('Erro ao buscar histórico: ' . $e->getMessage());
    }
}
?>