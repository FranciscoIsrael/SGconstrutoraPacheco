<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';

$db = getDB();
$type = isset($_GET['type']) ? $_GET['type'] : null;
$projectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : null;
$memberId = isset($_GET['member_id']) ? (int)$_GET['member_id'] : null;

if (!$type) {
    http_response_code(400);
    echo json_encode(['error' => 'Tipo de relatório é obrigatório']);
    exit;
}

switch ($type) {
    case 'project_detail':
        generateProjectDetailPDF($db, $projectId);
        break;
    case 'inventory_materials':
        generateInventoryMaterialsPDF($db, $projectId);
        break;
    case 'team_hours':
        generateTeamHoursPDF($db, $projectId, $memberId);
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Tipo de relatório inválido']);
}

function generateProjectDetailPDF($db, $projectId) {
    if (!$projectId) {
        http_response_code(400);
        echo json_encode(['error' => 'ID do projeto é obrigatório']);
        exit;
    }
    
    // Get project data
    $stmt = $db->prepare("SELECT * FROM projects WHERE id = ?");
    $stmt->execute([$projectId]);
    $project = $stmt->fetch();
    
    if (!$project) {
        http_response_code(404);
        echo json_encode(['error' => 'Projeto não encontrado']);
        exit;
    }
    
    // Get materials
    $stmt = $db->prepare("SELECT * FROM materials WHERE project_id = ?");
    $stmt->execute([$projectId]);
    $materials = $stmt->fetchAll();
    
    // Get transactions
    $stmt = $db->prepare("SELECT * FROM transactions WHERE project_id = ?");
    $stmt->execute([$projectId]);
    $transactions = $stmt->fetchAll();
    
    // Get team members
    $stmt = $db->prepare("SELECT * FROM team_members WHERE project_id = ?");
    $stmt->execute([$projectId]);
    $team = $stmt->fetchAll();
    
    // Get project photos
    $stmt = $db->prepare("SELECT * FROM project_photos WHERE project_id = ? ORDER BY photo_date DESC");
    $stmt->execute([$projectId]);
    $photos = $stmt->fetchAll();
    
    // Get inventory movements to this project
    $stmt = $db->prepare("
        SELECT im.*, i.name as item_name, i.unit 
        FROM inventory_movements im 
        JOIN inventory i ON im.inventory_id = i.id 
        WHERE im.project_id = ?
        ORDER BY im.movement_date DESC
    ");
    $stmt->execute([$projectId]);
    $movements = $stmt->fetchAll();
    
    // Generate HTML report
    $html = generateProjectHTML($project, $materials, $transactions, $team, $photos, $movements);
    
    echo json_encode([
        'success' => true,
        'html' => $html,
        'project' => $project
    ]);
}

function generateInventoryMaterialsPDF($db, $projectId = null) {
    $sql = "
        SELECT im.*, i.name as item_name, i.unit, i.unit_cost, p.name as project_name
        FROM inventory_movements im 
        JOIN inventory i ON im.inventory_id = i.id 
        LEFT JOIN projects p ON im.project_id = p.id 
        WHERE im.movement_type = 'out'
    ";
    $params = [];
    
    if ($projectId) {
        $sql .= " AND im.project_id = ?";
        $params[] = $projectId;
    }
    
    $sql .= " ORDER BY im.movement_date DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $movements = $stmt->fetchAll();
    
    // Get current inventory
    $stmt = $db->query("SELECT * FROM inventory ORDER BY name");
    $inventory = $stmt->fetchAll();
    
    $html = generateInventoryHTML($movements, $inventory, $projectId);
    
    echo json_encode([
        'success' => true,
        'html' => $html
    ]);
}

function generateTeamHoursPDF($db, $projectId = null, $memberId = null) {
    $sql = "
        SELECT te.*, tm.name as member_name, tm.role, tm.payment_type, 
               tm.daily_rate, tm.contract_value, p.name as project_name
        FROM time_entries te
        JOIN team_members tm ON te.team_member_id = tm.id
        JOIN projects p ON te.project_id = p.id
        WHERE 1=1
    ";
    $params = [];
    
    if ($projectId) {
        $sql .= " AND te.project_id = ?";
        $params[] = $projectId;
    }
    if ($memberId) {
        $sql .= " AND te.team_member_id = ?";
        $params[] = $memberId;
    }
    
    $sql .= " ORDER BY te.work_date DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $entries = $stmt->fetchAll();
    
    // Calculate totals
    $totals = [];
    foreach ($entries as $entry) {
        $mid = $entry['team_member_id'];
        if (!isset($totals[$mid])) {
            $totals[$mid] = [
                'name' => $entry['member_name'],
                'role' => $entry['role'],
                'hours' => 0,
                'days' => 0,
                'payment_type' => $entry['payment_type'],
                'daily_rate' => (float)$entry['daily_rate'],
                'contract_value' => (float)$entry['contract_value']
            ];
        }
        $totals[$mid]['hours'] += (float)$entry['hours_worked'];
        $totals[$mid]['days'] += (float)$entry['days_worked'];
    }
    
    $html = generateTeamHoursHTML($entries, $totals);
    
    echo json_encode([
        'success' => true,
        'html' => $html
    ]);
}

function generateProjectHTML($project, $materials, $transactions, $team, $photos, $movements) {
    $totalExpenses = 0;
    $totalRevenue = 0;
    foreach ($transactions as $t) {
        if ($t['type'] === 'expense') $totalExpenses += (float)$t['amount'];
        else $totalRevenue += (float)$t['amount'];
    }
    
    $materialsCost = 0;
    foreach ($materials as $m) {
        $materialsCost += (float)$m['cost'] * (float)$m['quantity'];
    }
    
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Relatório da Obra - ' . htmlspecialchars($project['name']) . '</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; color: #333; }
            .header { background: #2E3B5B; color: white; padding: 20px; margin-bottom: 20px; }
            .header h1 { margin: 0; }
            .section { margin-bottom: 30px; }
            .section h2 { color: #F5A623; border-bottom: 2px solid #F5A623; padding-bottom: 10px; }
            table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
            th { background: #f5f5f5; }
            .status { padding: 5px 10px; border-radius: 4px; }
            .status.active { background: #4CAF50; color: white; }
            .status.paused { background: #FFC107; color: black; }
            .status.completed { background: #2196F3; color: white; }
            .summary-box { background: #f9f9f9; padding: 15px; border-radius: 8px; margin: 10px 0; }
            .print-btn { background: #F5A623; color: white; padding: 10px 20px; border: none; cursor: pointer; margin-bottom: 20px; }
            @media print { .print-btn { display: none; } }
        </style>
    </head>
    <body>
        <button class="print-btn" onclick="window.print()">Imprimir / Salvar PDF</button>
        
        <div class="header">
            <h1>PACHECO EMPREENDIMENTOS</h1>
            <p>Relatório da Obra</p>
        </div>
        
        <div class="section">
            <h2>Informações da Obra</h2>
            <div class="summary-box">
                <p><strong>Nome:</strong> ' . htmlspecialchars($project['name']) . '</p>
                <p><strong>Endereço:</strong> ' . htmlspecialchars($project['address']) . '</p>
                <p><strong>Responsável:</strong> ' . htmlspecialchars($project['manager']) . '</p>
                <p><strong>Prazo:</strong> ' . date('d/m/Y', strtotime($project['deadline'])) . '</p>
                <p><strong>Status:</strong> <span class="status ' . $project['status'] . '">' . ucfirst($project['status']) . '</span></p>
                <p><strong>Orçamento:</strong> R$ ' . number_format((float)$project['budget'], 2, ',', '.') . '</p>
            </div>
        </div>
        
        <div class="section">
            <h2>Resumo Financeiro</h2>
            <div class="summary-box">
                <p><strong>Orçamento Total:</strong> R$ ' . number_format((float)$project['budget'], 2, ',', '.') . '</p>
                <p><strong>Total de Despesas:</strong> R$ ' . number_format($totalExpenses, 2, ',', '.') . '</p>
                <p><strong>Custo de Materiais:</strong> R$ ' . number_format($materialsCost, 2, ',', '.') . '</p>
                <p><strong>Saldo Restante:</strong> R$ ' . number_format((float)$project['budget'] - $totalExpenses, 2, ',', '.') . '</p>
            </div>
        </div>
        
        <div class="section">
            <h2>Materiais (' . count($materials) . ')</h2>
            <table>
                <tr><th>Material</th><th>Quantidade</th><th>Unidade</th><th>Custo Unit.</th><th>Total</th></tr>';
    
    foreach ($materials as $m) {
        $total = (float)$m['cost'] * (float)$m['quantity'];
        $html .= '<tr>
            <td>' . htmlspecialchars($m['name']) . '</td>
            <td>' . number_format((float)$m['quantity'], 2, ',', '.') . '</td>
            <td>' . htmlspecialchars($m['unit']) . '</td>
            <td>R$ ' . number_format((float)$m['cost'], 2, ',', '.') . '</td>
            <td>R$ ' . number_format($total, 2, ',', '.') . '</td>
        </tr>';
    }
    
    $html .= '</table></div>
        
        <div class="section">
            <h2>Materiais do Inventário Enviados (' . count($movements) . ')</h2>
            <table>
                <tr><th>Item</th><th>Quantidade</th><th>Unidade</th><th>Custo Unit.</th><th>Data</th><th>Observações</th></tr>';
    
    foreach ($movements as $m) {
        $html .= '<tr>
            <td>' . htmlspecialchars($m['item_name']) . '</td>
            <td>' . number_format((float)$m['quantity'], 2, ',', '.') . '</td>
            <td>' . htmlspecialchars($m['unit']) . '</td>
            <td>R$ ' . number_format((float)$m['unit_cost'], 2, ',', '.') . '</td>
            <td>' . date('d/m/Y', strtotime($m['movement_date'])) . '</td>
            <td>' . htmlspecialchars($m['notes'] ?? '') . '</td>
        </tr>';
    }
    
    $html .= '</table></div>
        
        <div class="section">
            <h2>Equipe (' . count($team) . ')</h2>
            <table>
                <tr><th>Nome</th><th>Função</th><th>Tipo Pagamento</th><th>Valor</th></tr>';
    
    foreach ($team as $t) {
        $paymentType = ($t['payment_type'] ?? 'diaria') === 'diaria' ? 'Diária' : 'Empreita';
        $paymentValue = ($t['payment_type'] ?? 'diaria') === 'diaria' 
            ? 'R$ ' . number_format((float)($t['daily_rate'] ?? $t['hourly_rate']), 2, ',', '.') . '/dia'
            : 'R$ ' . number_format((float)($t['contract_value'] ?? 0), 2, ',', '.');
        $html .= '<tr>
            <td>' . htmlspecialchars($t['name']) . '</td>
            <td>' . htmlspecialchars($t['role']) . '</td>
            <td>' . $paymentType . '</td>
            <td>' . $paymentValue . '</td>
        </tr>';
    }
    
    $html .= '</table></div>
        
        <div class="section">
            <h2>Transações Financeiras (' . count($transactions) . ')</h2>
            <table>
                <tr><th>Data</th><th>Tipo</th><th>Descrição</th><th>Valor</th></tr>';
    
    foreach ($transactions as $t) {
        $tipo = $t['type'] === 'expense' ? 'Despesa' : 'Receita';
        $html .= '<tr>
            <td>' . date('d/m/Y', strtotime($t['transaction_date'])) . '</td>
            <td>' . $tipo . '</td>
            <td>' . htmlspecialchars($t['description']) . '</td>
            <td>R$ ' . number_format((float)$t['amount'], 2, ',', '.') . '</td>
        </tr>';
    }
    
    $html .= '</table></div>
        
        <div class="section">
            <p><em>Relatório gerado em ' . date('d/m/Y H:i:s') . '</em></p>
        </div>
    </body>
    </html>';
    
    return $html;
}

function generateInventoryHTML($movements, $inventory, $projectId) {
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Relatório de Materiais - Inventário</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; color: #333; }
            .header { background: #2E3B5B; color: white; padding: 20px; margin-bottom: 20px; }
            .header h1 { margin: 0; }
            .section { margin-bottom: 30px; }
            .section h2 { color: #F5A623; border-bottom: 2px solid #F5A623; padding-bottom: 10px; }
            table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
            th { background: #f5f5f5; }
            .print-btn { background: #F5A623; color: white; padding: 10px 20px; border: none; cursor: pointer; margin-bottom: 20px; }
            @media print { .print-btn { display: none; } }
        </style>
    </head>
    <body>
        <button class="print-btn" onclick="window.print()">Imprimir / Salvar PDF</button>
        
        <div class="header">
            <h1>PACHECO EMPREENDIMENTOS</h1>
            <p>Relatório de Materiais do Inventário</p>
        </div>
        
        <div class="section">
            <h2>Materiais Enviados para Obras (' . count($movements) . ')</h2>
            <table>
                <tr><th>Item</th><th>Obra</th><th>Quantidade</th><th>Custo Unit.</th><th>Total</th><th>Data</th><th>Observações</th></tr>';
    
    $totalValue = 0;
    foreach ($movements as $m) {
        $total = (float)$m['quantity'] * (float)$m['unit_cost'];
        $totalValue += $total;
        $html .= '<tr>
            <td>' . htmlspecialchars($m['item_name']) . '</td>
            <td>' . htmlspecialchars($m['project_name'] ?? 'N/A') . '</td>
            <td>' . number_format((float)$m['quantity'], 2, ',', '.') . ' ' . htmlspecialchars($m['unit']) . '</td>
            <td>R$ ' . number_format((float)$m['unit_cost'], 2, ',', '.') . '</td>
            <td>R$ ' . number_format($total, 2, ',', '.') . '</td>
            <td>' . date('d/m/Y', strtotime($m['movement_date'])) . '</td>
            <td>' . htmlspecialchars($m['notes'] ?? '') . '</td>
        </tr>';
    }
    
    $html .= '<tr style="font-weight: bold; background: #f5f5f5;">
            <td colspan="4">TOTAL</td>
            <td>R$ ' . number_format($totalValue, 2, ',', '.') . '</td>
            <td colspan="2"></td>
        </tr>
    </table></div>
        
        <div class="section">
            <h2>Estoque Atual</h2>
            <table>
                <tr><th>Item</th><th>Quantidade</th><th>Custo Unit.</th><th>Valor Total</th></tr>';
    
    $totalStock = 0;
    foreach ($inventory as $i) {
        $total = (float)$i['quantity'] * (float)$i['unit_cost'];
        $totalStock += $total;
        $html .= '<tr>
            <td>' . htmlspecialchars($i['name']) . '</td>
            <td>' . number_format((float)$i['quantity'], 2, ',', '.') . ' ' . htmlspecialchars($i['unit']) . '</td>
            <td>R$ ' . number_format((float)$i['unit_cost'], 2, ',', '.') . '</td>
            <td>R$ ' . number_format($total, 2, ',', '.') . '</td>
        </tr>';
    }
    
    $html .= '<tr style="font-weight: bold; background: #f5f5f5;">
            <td colspan="3">VALOR TOTAL DO ESTOQUE</td>
            <td>R$ ' . number_format($totalStock, 2, ',', '.') . '</td>
        </tr>
    </table></div>
        
        <div class="section">
            <p><em>Relatório gerado em ' . date('d/m/Y H:i:s') . '</em></p>
        </div>
    </body>
    </html>';
    
    return $html;
}

function generateTeamHoursHTML($entries, $totals) {
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Relatório de Horas da Equipe</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; color: #333; }
            .header { background: #2E3B5B; color: white; padding: 20px; margin-bottom: 20px; }
            .header h1 { margin: 0; }
            .section { margin-bottom: 30px; }
            .section h2 { color: #F5A623; border-bottom: 2px solid #F5A623; padding-bottom: 10px; }
            table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
            th { background: #f5f5f5; }
            .print-btn { background: #F5A623; color: white; padding: 10px 20px; border: none; cursor: pointer; margin-bottom: 20px; }
            .summary-box { background: #f9f9f9; padding: 15px; border-radius: 8px; margin: 10px 0; }
            @media print { .print-btn { display: none; } }
        </style>
    </head>
    <body>
        <button class="print-btn" onclick="window.print()">Imprimir / Salvar PDF</button>
        
        <div class="header">
            <h1>PACHECO EMPREENDIMENTOS</h1>
            <p>Relatório de Horas Trabalhadas</p>
        </div>
        
        <div class="section">
            <h2>Resumo por Membro</h2>
            <table>
                <tr><th>Nome</th><th>Função</th><th>Tipo</th><th>Total Horas</th><th>Total Dias</th><th>Valor a Pagar</th></tr>';
    
    $totalPayment = 0;
    foreach ($totals as $t) {
        $payment = $t['payment_type'] === 'diaria' 
            ? $t['days'] * $t['daily_rate'] 
            : $t['contract_value'];
        $totalPayment += $payment;
        $html .= '<tr>
            <td>' . htmlspecialchars($t['name']) . '</td>
            <td>' . htmlspecialchars($t['role']) . '</td>
            <td>' . ($t['payment_type'] === 'diaria' ? 'Diária' : 'Empreita') . '</td>
            <td>' . number_format($t['hours'], 1, ',', '.') . 'h</td>
            <td>' . number_format($t['days'], 1, ',', '.') . '</td>
            <td>R$ ' . number_format($payment, 2, ',', '.') . '</td>
        </tr>';
    }
    
    $html .= '<tr style="font-weight: bold; background: #f5f5f5;">
            <td colspan="5">TOTAL A PAGAR</td>
            <td>R$ ' . number_format($totalPayment, 2, ',', '.') . '</td>
        </tr>
    </table></div>
        
        <div class="section">
            <h2>Detalhamento (' . count($entries) . ' registros)</h2>
            <table>
                <tr><th>Data</th><th>Membro</th><th>Obra</th><th>Horas</th><th>Dias</th><th>Descrição</th></tr>';
    
    foreach ($entries as $e) {
        $html .= '<tr>
            <td>' . date('d/m/Y', strtotime($e['work_date'])) . '</td>
            <td>' . htmlspecialchars($e['member_name']) . '</td>
            <td>' . htmlspecialchars($e['project_name']) . '</td>
            <td>' . number_format((float)$e['hours_worked'], 1, ',', '.') . 'h</td>
            <td>' . number_format((float)$e['days_worked'], 1, ',', '.') . '</td>
            <td>' . htmlspecialchars($e['description'] ?? '') . '</td>
        </tr>';
    }
    
    $html .= '</table></div>
        
        <div class="section">
            <p><em>Relatório gerado em ' . date('d/m/Y H:i:s') . '</em></p>
        </div>
    </body>
    </html>';
    
    return $html;
}
?>
