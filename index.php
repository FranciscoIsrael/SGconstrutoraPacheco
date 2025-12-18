<?php
// Construction Management System - Main Entry Point
require_once 'config/database.php';
require_once 'includes/functions.php';

session_start();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gerenciamento de Construção</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <header class="header">
            <div class="header-content">
                <div class="logo">
                    <img src="assets/images/logo-pacheco.jpg" alt="Pacheco Empreendimentos">
                </div>
                <nav class="nav">
                    <a href="#" class="nav-link active" data-section="dashboard">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                    <a href="#" class="nav-link" data-section="projects">
                        <i class="fas fa-building"></i> Obras
                    </a>
                    <a href="#" class="nav-link" data-section="materials">
                        <i class="fas fa-boxes"></i> Materiais
                    </a>
                    <a href="#" class="nav-link" data-section="financial">
                        <i class="fas fa-chart-line"></i> Financeiro
                    </a>
                    <a href="#" class="nav-link" data-section="team">
                        <i class="fas fa-users"></i> Equipe
                    </a>
                    <a href="#" class="nav-link" data-section="inventory">
                        <i class="fas fa-warehouse"></i> Inventário
                    </a>
                </nav>
            </div>
        </header>

        <main class="main-content">
            <!-- Dashboard Section -->
            <section id="dashboard" class="content-section active">
                <div class="section-header">
                    <h2><i class="fas fa-tachometer-alt"></i> Dashboard</h2>
                    <div class="header-actions">
                        <button class="btn btn-secondary btn-toggle-values" id="toggle-values-btn" onclick="toggleValuesVisibility()">
                            <i class="fas fa-eye"></i> Ocultar Valores
                        </button>
                        <button class="btn btn-primary" onclick="showAddProjectModal()">
                            <i class="fas fa-plus"></i> Nova Obra
                        </button>
                    </div>
                </div>
                
                <div id="deadline-alerts" class="alerts-container"></div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-building"></i>
                        </div>
                        <div class="stat-content">
                            <h3 id="total-projects">0</h3>
                            <p>Obras Ativas</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <div class="stat-content">
                            <h3 id="total-budget">R$ 0,00</h3>
                            <p>Orçamento Total</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="stat-content">
                            <h3 id="total-spent">R$ 0,00</h3>
                            <p>Total Gasto</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-content">
                            <h3 id="total-team">0</h3>
                            <p>Membros da Equipe</p>
                        </div>
                    </div>
                </div>

                <div class="projects-overview">
                    <h3>Obras Recentes</h3>
                    <div id="projects-list" class="projects-grid">
                        <!-- Projects will be loaded here -->
                    </div>
                </div>
            </section>

            <!-- Projects Section -->
            <section id="projects" class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-building"></i> Gerenciar Obras</h2>
                    <button class="btn btn-primary" onclick="showAddProjectModal()">
                        <i class="fas fa-plus"></i> Nova Obra
                    </button>
                </div>
                <div id="all-projects-list" class="projects-grid">
                    <!-- All projects will be loaded here -->
                </div>
            </section>

            <!-- Materials Section -->
            <section id="materials" class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-boxes"></i> Gerenciar Materiais</h2>
                    <select id="project-select-materials" class="form-select">
                        <option value="">Selecione uma obra</option>
                    </select>
                    <button class="btn btn-primary" onclick="showAddMaterialModal()" disabled id="add-material-btn">
                        <i class="fas fa-plus"></i> Novo Material
                    </button>
                </div>
                <div id="materials-list" class="table-container">
                    <!-- Materials will be loaded here -->
                </div>
            </section>

            <!-- Financial Section -->
            <section id="financial" class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-chart-line"></i> Controle Financeiro</h2>
                    <select id="project-select-financial" class="form-select">
                        <option value="">Selecione uma obra</option>
                    </select>
                    <button class="btn btn-primary" onclick="showAddTransactionModal()" disabled id="add-transaction-btn">
                        <i class="fas fa-plus"></i> Nova Transação
                    </button>
                </div>
                <div class="financial-overview">
                    <div id="financial-summary" class="stats-grid">
                        <!-- Financial summary will be loaded here -->
                    </div>
                    <div id="transactions-list" class="table-container">
                        <!-- Transactions will be loaded here -->
                    </div>
                </div>
            </section>

            <!-- Team Section -->
            <section id="team" class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-users"></i> Gerenciar Equipe</h2>
                    <div class="header-actions">
                        <button class="btn btn-secondary" onclick="showTeamHistory()">
                            <i class="fas fa-history"></i> Histórico
                        </button>
                        <button class="btn btn-primary" onclick="showAddTeamMemberModal()">
                            <i class="fas fa-user-plus"></i> Novo Membro
                        </button>
                    </div>
                </div>
                <div class="filter-bar">
                    <select id="project-filter-team" class="form-select">
                        <option value="">Todos os membros</option>
                    </select>
                </div>
                <div id="team-list" class="cards-grid">
                    <!-- Team members will be loaded here as cards -->
                </div>
            </section>

            <!-- Inventory Section -->
            <section id="inventory" class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-warehouse"></i> Inventário da Empresa</h2>
                    <div class="header-actions">
                        <button class="btn btn-info" onclick="showDeliveriesModal()">
                            <i class="fas fa-truck"></i> Entregas
                        </button>
                        <button class="btn btn-secondary" onclick="showInventoryHistory()">
                            <i class="fas fa-history"></i> Histórico
                        </button>
                        <button class="btn btn-primary" onclick="showAddInventoryModal()">
                            <i class="fas fa-plus"></i> Novo Item
                        </button>
                    </div>
                </div>
                
                <div class="stats-grid mb-3">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-boxes"></i>
                        </div>
                        <div class="stat-content">
                            <h3 id="total-inventory-items">0</h3>
                            <p>Itens em Estoque</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <div class="stat-content">
                            <h3 id="total-inventory-value">R$ 0,00</h3>
                            <p>Valor Total do Estoque</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon warning">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="stat-content">
                            <h3 id="low-stock-items">0</h3>
                            <p>Estoque Baixo</p>
                        </div>
                    </div>
                </div>

                <div id="inventory-list" class="table-container">
                    <!-- Inventory items will be loaded here -->
                </div>
            </section>
        </main>
    </div>

    <!-- Modals will be included here -->
    <div id="modal-container"></div>

    <script src="assets/js/app.js"></script>
    <script src="assets/js/project-functions.js"></script>
    <script src="assets/js/team-functions.js"></script>
    <script src="assets/js/inventory-functions.js"></script>
</body>
</html>