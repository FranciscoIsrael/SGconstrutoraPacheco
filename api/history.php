<?php
// API endpoint for audit history
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

if ($method !== 'GET') {
    sendErrorResponse('Only GET method is allowed', 405);
}

if (isset($_GET['table']) && isset($_GET['record_id'])) {
    getRecordHistory($db, $_GET['table'], $_GET['record_id']);
} elseif (isset($_GET['table'])) {
    getTableHistory($db, $_GET['table']);
} else {
    getAllHistory($db);
}

function getAllHistory($db) {
    try {
        $stmt = $db->query("
            SELECT * FROM audit_history
            ORDER BY changed_at DESC
            LIMIT 100
        ");
        $history = $stmt->fetchAll();
        
        foreach ($history as &$entry) {
            $entry['changed_at_formatted'] = date('d/m/Y H:i:s', strtotime($entry['changed_at']));
        }
        
        sendSuccessResponse($history);
    } catch (PDOException $e) {
        sendErrorResponse('Failed to fetch history: ' . $e->getMessage());
    }
}

function getTableHistory($db, $table) {
    try {
        $stmt = $db->prepare("
            SELECT * FROM audit_history
            WHERE table_name = ?
            ORDER BY changed_at DESC
            LIMIT 100
        ");
        $stmt->execute([$table]);
        $history = $stmt->fetchAll();
        
        foreach ($history as &$entry) {
            $entry['changed_at_formatted'] = date('d/m/Y H:i:s', strtotime($entry['changed_at']));
        }
        
        sendSuccessResponse($history);
    } catch (PDOException $e) {
        sendErrorResponse('Failed to fetch history: ' . $e->getMessage());
    }
}

function getRecordHistory($db, $table, $recordId) {
    try {
        $stmt = $db->prepare("
            SELECT * FROM audit_history
            WHERE table_name = ? AND record_id = ?
            ORDER BY changed_at DESC
        ");
        $stmt->execute([$table, $recordId]);
        $history = $stmt->fetchAll();
        
        foreach ($history as &$entry) {
            $entry['changed_at_formatted'] = date('d/m/Y H:i:s', strtotime($entry['changed_at']));
            
            // Decode JSON values if they exist
            if ($entry['old_value'] && json_decode($entry['old_value'])) {
                $entry['old_value_decoded'] = json_decode($entry['old_value'], true);
            }
            if ($entry['new_value'] && json_decode($entry['new_value'])) {
                $entry['new_value_decoded'] = json_decode($entry['new_value'], true);
            }
        }
        
        sendSuccessResponse($history);
    } catch (PDOException $e) {
        sendErrorResponse('Failed to fetch history: ' . $e->getMessage());
    }
}
?>