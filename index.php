<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
session_start();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pacheco Empreendimentos - Sistema de Gerenciamento</title>
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
                <div class="header-actions">
                    <button class="btn btn-icon" onclick="togglePrivacyMode()" title="Ocultar/Mostrar Valores">
                        <i class="fas fa-eye" id="privacy-icon"></i>
                    </button>
                    <button class="btn btn-icon notification-btn" onclick="toggleNotifications()" title="Notificações">
                        <i class="fas fa-bell"></i>
                        <span class="notification-badge" id="notification-count">0</span>
                    </button>
                </div>
            </div>
        </header>

        <div class="notifications-panel" id="notifications-panel">
            <div class="notifications-header">
                <h3><i class="fas fa-bell"></i> Notificações</h3>
                <div class="notifications-actions">
                    <select id="notification-filter" onchange="filterNotifications()">
                        <option value="">Todas</option>
                        <option value="inventory">Inventário</option>
                        <option value="inventory_restock">Reposição</option>
                        <option value="photo">Fotos</option>
                        <option value="chat">Mensagens</option>
                    </select>
                    <button class="btn btn-sm" onclick="markAllNotificationsRead()">Marcar como lidas</button>
                </div>
            </div>
            <div class="notifications-list" id="notifications-list"></div>
        </div>

        <main class="main-content">
            <section id="dashboard" class="content-section active">
                <div class="section-header">
                    <h2><i class="fas fa-tachometer-alt"></i> Dashboard</h2>
                    <button class="btn btn-primary" onclick="showAddProjectModal()">
                        <i class="fas fa-plus"></i> Nova Obra
                    </button>
                </div>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-building"></i></div>
                        <div class="stat-content">
                            <h3 id="total-projects" class="privacy-value">0</h3>
                            <p>Obras Ativas</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-dollar-sign"></i></div>
                        <div class="stat-content">
                            <h3 id="total-budget" class="privacy-value">R$ 0,00</h3>
                            <p>Orçamento Total</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                        <div class="stat-content">
                            <h3 id="total-spent" class="privacy-value">R$ 0,00</h3>
                            <p>Total Gasto</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-users"></i></div>
                        <div class="stat-content">
                            <h3 id="total-team" class="privacy-value">0</h3>
                            <p>Membros da Equipe</p>
                        </div>
                    </div>
                </div>

                <div class="projects-overview">
                    <h3>Obras Recentes</h3>
                    <div id="projects-list" class="projects-grid"></div>
                </div>
            </section>

            <section id="projects" class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-building"></i> Gerenciar Obras</h2>
                    <button class="btn btn-primary" onclick="showAddProjectModal()">
                        <i class="fas fa-plus"></i> Nova Obra
                    </button>
                </div>
                <div id="all-projects-list" class="projects-grid"></div>
            </section>

            <section id="materials" class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-boxes"></i> Gerenciar Materiais</h2>
                    <select id="project-select-materials" class="form-select">
                        <option value="">Selecione uma obra</option>
                    </select>
                    <button class="btn btn-primary" onclick="showAddMaterialModal()" disabled id="add-material-btn">
                        <i class="fas fa-plus"></i> Novo Material
                    </button>
                    <button class="btn btn-secondary" onclick="showAddMaterialImagesModal()" disabled id="add-material-images-btn">
                        <i class="fas fa-images"></i> Adicionar Fotos
                    </button>
                </div>
                <div id="materials-list" class="table-container"></div>
            </section>

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
                    <div id="financial-summary" class="stats-grid"></div>
                    <div id="transactions-list" class="table-container"></div>
                </div>
            </section>

            <section id="team" class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-users"></i> Gerenciar Equipe</h2>
                    <select id="project-select-team" class="form-select">
                        <option value="">Selecione uma obra</option>
                    </select>
                    <div class="btn-group">
                        <button class="btn btn-primary" onclick="showAddTeamMemberModal()" disabled id="add-member-btn">
                            <i class="fas fa-user-plus"></i> Novo Membro
                        </button>
                        <button class="btn btn-secondary" onclick="showAddTimeEntryModal()" disabled id="add-time-btn">
                            <i class="fas fa-clock"></i> Registrar Horas
                        </button>
                        <button class="btn btn-secondary" onclick="generateTeamReport()" disabled id="team-report-btn">
                            <i class="fas fa-file-pdf"></i> Relatório PDF
                        </button>
                    </div>
                </div>
                <div id="team-list" class="table-container"></div>
                <div id="time-entries-section" style="display:none;">
                    <h3><i class="fas fa-clock"></i> Registro de Horas</h3>
                    <div id="time-entries-list" class="table-container"></div>
                </div>
            </section>

            <section id="inventory" class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-warehouse"></i> Inventário da Empresa</h2>
                    <div class="btn-group">
                        <button class="btn btn-primary" onclick="showAddInventoryModal()">
                            <i class="fas fa-plus"></i> Novo Item
                        </button>
                        <button class="btn btn-secondary" onclick="generateInventoryReport()">
                            <i class="fas fa-file-pdf"></i> Relatório PDF
                        </button>
                    </div>
                </div>
                
                <div class="stats-grid mb-3">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-boxes"></i></div>
                        <div class="stat-content">
                            <h3 id="total-inventory-items" class="privacy-value">0</h3>
                            <p>Itens em Estoque</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-dollar-sign"></i></div>
                        <div class="stat-content">
                            <h3 id="total-inventory-value" class="privacy-value">R$ 0,00</h3>
                            <p>Valor Total do Estoque</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
                        <div class="stat-content">
                            <h3 id="low-stock-items">0</h3>
                            <p>Estoque Baixo</p>
                        </div>
                    </div>
                </div>

                <div class="inventory-filters">
                    <select id="inventory-filter" onchange="filterInventory()">
                        <option value="">Todos os itens</option>
                        <option value="low">Estoque baixo</option>
                        <option value="out">Sem estoque</option>
                    </select>
                </div>

                <div id="inventory-list" class="table-container"></div>
                
                <div class="inventory-history-section">
                    <h3><i class="fas fa-history"></i> Histórico de Movimentações</h3>
                    <div class="history-filters">
                        <select id="movement-type-filter" onchange="filterMovements()">
                            <option value="">Todos os tipos</option>
                            <option value="in">Entradas</option>
                            <option value="out">Saídas</option>
                        </select>
                        <select id="movement-project-filter" onchange="filterMovements()">
                            <option value="">Todas as obras</option>
                        </select>
                    </div>
                    <div id="movements-list" class="table-container"></div>
                </div>
            </section>
        </main>
    </div>

    <div id="modal-container"></div>

    <div class="chat-widget" id="chat-widget">
        <div class="chat-header" onclick="toggleChat()">
            <i class="fas fa-comments"></i> Chat da Obra
            <span class="chat-toggle"><i class="fas fa-chevron-up"></i></span>
        </div>
        <div class="chat-body" id="chat-body">
            <div class="chat-project-select">
                <select id="chat-project-select" onchange="loadChatMessages()">
                    <option value="">Selecione uma obra</option>
                </select>
            </div>
            <div class="chat-messages" id="chat-messages"></div>
            <div class="chat-input">
                <input type="text" id="chat-sender" placeholder="Seu nome" class="form-control mb-2">
                <div class="chat-input-row">
                    <input type="text" id="chat-message" placeholder="Digite sua mensagem..." class="form-control" onkeypress="handleChatKeypress(event)">
                    <button class="btn btn-primary" onclick="sendChatMessage()">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/app.js"></script>
    <script src="assets/js/project-functions.js"></script>
    <script src="assets/js/inventory-functions.js"></script>
    <script src="assets/js/dashboard-functions.js"></script>
</body>
</html>
