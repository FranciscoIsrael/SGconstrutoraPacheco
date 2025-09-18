<?php
// API endpoints for financial transactions management
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
        if (isset($_GET['project_id'])) {
            if (isset($_GET['summary'])) {
                getFinancialSummary($db, $_GET['project_id']);
            } else {
                getTransactionsByProject($db, $_GET['project_id']);
            }
        } elseif (isset($_GET['id'])) {
            getTransaction($db, $_GET['id']);
        } else {
            sendErrorResponse('Project ID is required');
        }
        break;
    
    case 'POST':
        createTransaction($db);
        break;
    
    case 'PUT':
        if (isset($_GET['id'])) {
            updateTransaction($db, $_GET['id']);
        } else {
            sendErrorResponse('ID is required for update');
        }
        break;
    
    case 'DELETE':
        if (isset($_GET['id'])) {
            deleteTransaction($db, $_GET['id']);
        } else {
            sendErrorResponse('ID is required for deletion');
        }
        break;
    
    default:
        sendErrorResponse('Method not allowed', 405);
}

function getTransactionsByProject($db, $projectId) {
    try {
        $stmt = $db->prepare("
            SELECT t.*, p.name as project_name 
            FROM transactions t 
            JOIN projects p ON t.project_id = p.id 
            WHERE t.project_id = ? 
            ORDER BY t.transaction_date DESC, t.created_at DESC
        ");
        $stmt->execute([$projectId]);
        $transactions = $stmt->fetchAll();
        
        foreach ($transactions as &$transaction) {
            $transaction['amount'] = (float)$transaction['amount'];
            $transaction['transaction_date_formatted'] = formatDate($transaction['transaction_date']);
        }
        
        sendSuccessResponse($transactions);
    } catch (PDOException $e) {
        sendErrorResponse('Failed to fetch transactions: ' . $e->getMessage());
    }
}

function getFinancialSummary($db, $projectId) {
    try {
        $stmt = $db->prepare("
            SELECT 
                p.budget,
                COALESCE(SUM(CASE WHEN t.type = 'expense' THEN t.amount END), 0) as total_expenses,
                COALESCE(SUM(CASE WHEN t.type = 'revenue' THEN t.amount END), 0) as total_revenue,
                COALESCE(SUM(CASE WHEN t.type = 'expense' THEN t.amount ELSE -t.amount END), 0) as net_spent
            FROM projects p
            LEFT JOIN transactions t ON p.id = t.project_id
            WHERE p.id = ?
            GROUP BY p.id, p.budget
        ");
        $stmt->execute([$projectId]);
        $summary = $stmt->fetch();
        
        if ($summary) {
            $summary['budget'] = (float)$summary['budget'];
            $summary['total_expenses'] = (float)$summary['total_expenses'];
            $summary['total_revenue'] = (float)$summary['total_revenue'];
            $summary['net_spent'] = (float)$summary['net_spent'];
            $summary['remaining_budget'] = $summary['budget'] - $summary['net_spent'];
            $summary['budget_usage_percent'] = $summary['budget'] > 0 ? ($summary['net_spent'] / $summary['budget']) * 100 : 0;
        }
        
        sendSuccessResponse($summary);
    } catch (PDOException $e) {
        sendErrorResponse('Failed to fetch financial summary: ' . $e->getMessage());
    }
}

function getTransaction($db, $id) {
    try {
        $stmt = $db->prepare("SELECT * FROM transactions WHERE id = ?");
        $stmt->execute([$id]);
        $transaction = $stmt->fetch();
        
        if ($transaction) {
            $transaction['amount'] = (float)$transaction['amount'];
            sendSuccessResponse($transaction);
        } else {
            sendErrorResponse('Transaction not found', 404);
        }
    } catch (PDOException $e) {
        sendErrorResponse('Failed to fetch transaction: ' . $e->getMessage());
    }
}

function createTransaction($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $required = ['project_id', 'type', 'description', 'amount'];
    if (!validateRequired($required, $input)) {
        sendErrorResponse('All fields are required');
    }
    
    if (!in_array($input['type'], ['expense', 'revenue'])) {
        sendErrorResponse('Type must be either "expense" or "revenue"');
    }
    
    try {
        $stmt = $db->prepare("
            INSERT INTO transactions (project_id, type, description, amount, transaction_date) 
            VALUES (?, ?, ?, ?, ?) RETURNING id
        ");
        $stmt->execute([
            $input['project_id'],
            $input['type'],
            sanitizeInput($input['description']),
            $input['amount'],
            $input['transaction_date'] ?? date('Y-m-d')
        ]);
        
        $transactionId = $stmt->fetchColumn();
        sendSuccessResponse(['id' => $transactionId, 'message' => 'Transaction created successfully']);
    } catch (PDOException $e) {
        sendErrorResponse('Failed to create transaction: ' . $e->getMessage());
    }
}

function updateTransaction($db, $id) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $required = ['type', 'description', 'amount'];
    if (!validateRequired($required, $input)) {
        sendErrorResponse('All fields are required');
    }
    
    if (!in_array($input['type'], ['expense', 'revenue'])) {
        sendErrorResponse('Type must be either "expense" or "revenue"');
    }
    
    try {
        $stmt = $db->prepare("
            UPDATE transactions 
            SET type = ?, description = ?, amount = ?, transaction_date = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $input['type'],
            sanitizeInput($input['description']),
            $input['amount'],
            $input['transaction_date'] ?? date('Y-m-d'),
            $id
        ]);
        
        if ($stmt->rowCount() > 0) {
            sendSuccessResponse(['message' => 'Transaction updated successfully']);
        } else {
            sendErrorResponse('Transaction not found', 404);
        }
    } catch (PDOException $e) {
        sendErrorResponse('Failed to update transaction: ' . $e->getMessage());
    }
}

function deleteTransaction($db, $id) {
    try {
        $stmt = $db->prepare("DELETE FROM transactions WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() > 0) {
            sendSuccessResponse(['message' => 'Transaction deleted successfully']);
        } else {
            sendErrorResponse('Transaction not found', 404);
        }
    } catch (PDOException $e) {
        sendErrorResponse('Failed to delete transaction: ' . $e->getMessage());
    }
}
?>