// Construction Management System - Main JavaScript Application
class ConstructionApp {
    constructor() {
        this.currentSection = 'dashboard';
        this.currentProject = null;
        this.projects = [];
        this.materials = [];
        this.transactions = [];
        this.teamMembers = [];
        
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.loadDashboardData();
    }

    setupEventListeners() {
        // Navigation
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const section = link.getAttribute('data-section');
                this.showSection(section);
            });
        });

        // Project selection dropdowns
        const projectSelects = ['project-select-materials', 'project-select-financial', 'project-select-team'];
        projectSelects.forEach(selectId => {
            const select = document.getElementById(selectId);
            if (select) {
                select.addEventListener('change', (e) => {
                    this.currentProject = e.target.value;
                    this.handleProjectSelection(selectId, e.target.value);
                });
            }
        });

        // Close modal when clicking outside
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal')) {
                this.closeModal();
            }
        });
    }

    showSection(section) {
        // Update navigation
        document.querySelectorAll('.nav-link').forEach(link => {
            link.classList.remove('active');
        });
        document.querySelector(`[data-section="${section}"]`).classList.add('active');

        // Update content
        document.querySelectorAll('.content-section').forEach(section => {
            section.classList.remove('active');
        });
        document.getElementById(section).classList.add('active');

        this.currentSection = section;

        // Load section-specific data
        switch (section) {
            case 'dashboard':
                this.loadDashboardData();
                break;
            case 'projects':
                this.loadProjects();
                break;
            case 'materials':
                this.loadProjectDropdown('project-select-materials');
                break;
            case 'financial':
                this.loadProjectDropdown('project-select-financial');
                break;
            case 'team':
                this.loadProjectDropdown('project-select-team');
                break;
        }
    }

    async loadDashboardData() {
        try {
            await this.loadProjects();
            this.updateDashboardStats();
            this.displayRecentProjects();
        } catch (error) {
            console.error('Error loading dashboard data:', error);
            this.showAlert('Erro ao carregar dados do dashboard', 'error');
        }
    }

    async loadProjects() {
        try {
            const response = await fetch('api/projects.php');
            const result = await response.json();
            
            if (result.success) {
                this.projects = result.data;
                if (this.currentSection === 'projects') {
                    this.displayAllProjects();
                }
                return this.projects;
            } else {
                throw new Error(result.error || 'Failed to load projects');
            }
        } catch (error) {
            console.error('Error loading projects:', error);
            this.showAlert('Erro ao carregar obras', 'error');
            return [];
        }
    }

    updateDashboardStats() {
        const totalProjects = this.projects.length;
        const totalBudget = this.projects.reduce((sum, project) => sum + parseFloat(project.budget || 0), 0);
        const totalSpent = this.projects.reduce((sum, project) => sum + parseFloat(project.total_spent || 0), 0);
        const totalTeam = this.projects.reduce((sum, project) => sum + parseInt(project.team_count || 0), 0);

        document.getElementById('total-projects').textContent = totalProjects;
        document.getElementById('total-budget').textContent = this.formatCurrency(totalBudget);
        document.getElementById('total-spent').textContent = this.formatCurrency(totalSpent);
        document.getElementById('total-team').textContent = totalTeam;
    }

    displayRecentProjects() {
        const recentProjects = this.projects.slice(0, 6);
        this.displayProjects(recentProjects, 'projects-list');
    }

    displayAllProjects() {
        this.displayProjects(this.projects, 'all-projects-list');
    }

    displayProjects(projects, containerId) {
        const container = document.getElementById(containerId);
        
        if (projects.length === 0) {
            container.innerHTML = '<div class="text-center text-muted"><i class="fas fa-building" style="font-size: 3rem; margin-bottom: 1rem;"></i><p>Nenhuma obra cadastrada ainda.</p></div>';
            return;
        }

        container.innerHTML = projects.map(project => `
            <div class="project-card">
                <div class="project-header">
                    <div>
                        <h3 class="project-title">${project.name}</h3>
                        <div class="project-status status-${project.status}">
                            ${this.getStatusLabel(project.status)}
                        </div>
                    </div>
                </div>
                <div class="project-info">
                    <p><i class="fas fa-map-marker-alt"></i> ${project.address}</p>
                    <p><i class="fas fa-calendar"></i> Prazo: ${project.deadline_formatted}</p>
                    <p><i class="fas fa-user"></i> Responsável: ${project.manager}</p>
                </div>
                <div class="project-budget">
                    <div class="budget-item">
                        <div class="budget-label">Orçamento</div>
                        <div class="budget-value text-info">${this.formatCurrency(project.budget)}</div>
                    </div>
                    <div class="budget-item">
                        <div class="budget-label">Gasto</div>
                        <div class="budget-value text-warning">${this.formatCurrency(project.total_spent)}</div>
                    </div>
                    <div class="budget-item">
                        <div class="budget-label">Restante</div>
                        <div class="budget-value text-success">${this.formatCurrency(project.budget - project.total_spent)}</div>
                    </div>
                </div>
                <div class="project-actions">
                    <button class="btn btn-sm btn-primary" onclick="app.editProject(${project.id})">
                        <i class="fas fa-edit"></i> Editar
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="app.deleteProject(${project.id})">
                        <i class="fas fa-trash"></i> Excluir
                    </button>
                </div>
            </div>
        `).join('');
    }

    async loadProjectDropdown(selectId) {
        try {
            if (this.projects.length === 0) {
                await this.loadProjects();
            }
            
            const select = document.getElementById(selectId);
            select.innerHTML = '<option value="">Selecione uma obra</option>' +
                this.projects.map(project => 
                    `<option value="${project.id}">${project.name}</option>`
                ).join('');
        } catch (error) {
            console.error('Error loading project dropdown:', error);
        }
    }

    handleProjectSelection(selectType, projectId) {
        const buttons = {
            'project-select-materials': 'add-material-btn',
            'project-select-financial': 'add-transaction-btn',
            'project-select-team': 'add-member-btn'
        };

        const button = document.getElementById(buttons[selectType]);
        if (button) {
            button.disabled = !projectId;
        }

        if (projectId) {
            switch (selectType) {
                case 'project-select-materials':
                    this.loadMaterials(projectId);
                    break;
                case 'project-select-financial':
                    this.loadTransactions(projectId);
                    this.loadFinancialSummary(projectId);
                    break;
                case 'project-select-team':
                    this.loadTeamMembers(projectId);
                    break;
            }
        } else {
            this.clearSectionContent(selectType);
        }
    }

    clearSectionContent(selectType) {
        const containers = {
            'project-select-materials': 'materials-list',
            'project-select-financial': 'transactions-list',
            'project-select-team': 'team-list'
        };

        const containerId = containers[selectType];
        if (containerId) {
            document.getElementById(containerId).innerHTML = '';
        }

        if (selectType === 'project-select-financial') {
            document.getElementById('financial-summary').innerHTML = '';
        }
    }

    async loadMaterials(projectId) {
        try {
            const response = await fetch(`api/materials.php?project_id=${projectId}`);
            const result = await response.json();
            
            if (result.success) {
                this.materials = result.data;
                this.displayMaterials();
            } else {
                throw new Error(result.error);
            }
        } catch (error) {
            console.error('Error loading materials:', error);
            this.showAlert('Erro ao carregar materiais', 'error');
        }
    }

    displayMaterials() {
        const container = document.getElementById('materials-list');
        
        if (this.materials.length === 0) {
            container.innerHTML = '<div class="text-center text-muted p-4">Nenhum material cadastrado para esta obra.</div>';
            return;
        }

        const totalCost = this.materials.reduce((sum, material) => sum + (material.quantity * material.cost), 0);

        container.innerHTML = `
            <table class="table">
                <thead>
                    <tr>
                        <th>Material</th>
                        <th>Quantidade</th>
                        <th>Unidade</th>
                        <th>Custo Unit.</th>
                        <th>Custo Total</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    ${this.materials.map(material => `
                        <tr>
                            <td>${material.name}</td>
                            <td>${material.quantity}</td>
                            <td>${material.unit}</td>
                            <td>${this.formatCurrency(material.cost)}</td>
                            <td>${this.formatCurrency(material.quantity * material.cost)}</td>
                            <td>
                                <button class="btn btn-sm btn-primary" onclick="app.editMaterial(${material.id})">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="app.deleteMaterial(${material.id})">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="4">Total</th>
                        <th>${this.formatCurrency(totalCost)}</th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
        `;
    }

    async loadTransactions(projectId) {
        try {
            const response = await fetch(`api/transactions.php?project_id=${projectId}`);
            const result = await response.json();
            
            if (result.success) {
                this.transactions = result.data;
                this.displayTransactions();
            } else {
                throw new Error(result.error);
            }
        } catch (error) {
            console.error('Error loading transactions:', error);
            this.showAlert('Erro ao carregar transações', 'error');
        }
    }

    async loadFinancialSummary(projectId) {
        try {
            const response = await fetch(`api/transactions.php?project_id=${projectId}&summary=1`);
            const result = await response.json();
            
            if (result.success) {
                this.displayFinancialSummary(result.data);
            } else {
                throw new Error(result.error);
            }
        } catch (error) {
            console.error('Error loading financial summary:', error);
        }
    }

    displayFinancialSummary(summary) {
        const container = document.getElementById('financial-summary');
        
        container.innerHTML = `
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="stat-content">
                    <h3>${this.formatCurrency(summary.budget)}</h3>
                    <p>Orçamento</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-arrow-down"></i>
                </div>
                <div class="stat-content">
                    <h3>${this.formatCurrency(summary.total_expenses)}</h3>
                    <p>Despesas</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-arrow-up"></i>
                </div>
                <div class="stat-content">
                    <h3>${this.formatCurrency(summary.total_revenue)}</h3>
                    <p>Receitas</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-content">
                    <h3>${this.formatCurrency(summary.remaining_budget)}</h3>
                    <p>Restante</p>
                </div>
            </div>
        `;
    }

    displayTransactions() {
        const container = document.getElementById('transactions-list');
        
        if (this.transactions.length === 0) {
            container.innerHTML = '<div class="text-center text-muted p-4">Nenhuma transação registrada para esta obra.</div>';
            return;
        }

        container.innerHTML = `
            <table class="table">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Tipo</th>
                        <th>Descrição</th>
                        <th>Valor</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    ${this.transactions.map(transaction => `
                        <tr>
                            <td>${transaction.transaction_date_formatted}</td>
                            <td>
                                <span class="badge ${transaction.type === 'expense' ? 'bg-danger' : 'bg-success'}">
                                    ${transaction.type === 'expense' ? 'Despesa' : 'Receita'}
                                </span>
                            </td>
                            <td>${transaction.description}</td>
                            <td class="${transaction.type === 'expense' ? 'text-danger' : 'text-success'}">
                                ${transaction.type === 'expense' ? '-' : '+'}${this.formatCurrency(transaction.amount)}
                            </td>
                            <td>
                                <button class="btn btn-sm btn-primary" onclick="app.editTransaction(${transaction.id})">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="app.deleteTransaction(${transaction.id})">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    }

    async loadTeamMembers(projectId) {
        try {
            const response = await fetch(`api/team.php?project_id=${projectId}`);
            const result = await response.json();
            
            if (result.success) {
                this.teamMembers = result.data;
                this.displayTeamMembers();
            } else {
                throw new Error(result.error);
            }
        } catch (error) {
            console.error('Error loading team members:', error);
            this.showAlert('Erro ao carregar equipe', 'error');
        }
    }

    displayTeamMembers() {
        const container = document.getElementById('team-list');
        
        if (this.teamMembers.length === 0) {
            container.innerHTML = '<div class="text-center text-muted p-4">Nenhum membro cadastrado para esta obra.</div>';
            return;
        }

        container.innerHTML = `
            <table class="table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Função</th>
                        <th>Custo/Hora</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    ${this.teamMembers.map(member => `
                        <tr>
                            <td>${member.name}</td>
                            <td>${member.role}</td>
                            <td>${this.formatCurrency(member.hourly_rate)}</td>
                            <td>
                                <button class="btn btn-sm btn-primary" onclick="app.editTeamMember(${member.id})">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="app.deleteTeamMember(${member.id})">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    }

    // Modal Management
    showModal(title, content, footer = '') {
        const modalHtml = `
            <div class="modal active">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>${title}</h3>
                        <button type="button" class="modal-close" onclick="app.closeModal()">&times;</button>
                    </div>
                    <div class="modal-body">
                        ${content}
                    </div>
                    ${footer ? `<div class="modal-footer">${footer}</div>` : ''}
                </div>
            </div>
        `;
        
        document.getElementById('modal-container').innerHTML = modalHtml;
    }

    closeModal() {
        const modal = document.querySelector('.modal');
        if (modal) {
            modal.classList.remove('active');
            setTimeout(() => {
                document.getElementById('modal-container').innerHTML = '';
            }, 300);
        }
    }

    showAlert(message, type = 'info') {
        const alertClass = `alert-${type}`;
        const iconMap = {
            success: 'fas fa-check-circle',
            error: 'fas fa-exclamation-triangle',
            warning: 'fas fa-exclamation-circle',
            info: 'fas fa-info-circle'
        };

        const alert = document.createElement('div');
        alert.className = `alert ${alertClass}`;
        alert.innerHTML = `
            <i class="${iconMap[type]}"></i>
            ${message}
        `;

        document.body.appendChild(alert);

        setTimeout(() => {
            alert.remove();
        }, 5000);
    }

    // Utility Functions
    formatCurrency(amount) {
        return new Intl.NumberFormat('pt-BR', {
            style: 'currency',
            currency: 'BRL'
        }).format(amount || 0);
    }

    formatDate(dateString) {
        return new Date(dateString).toLocaleDateString('pt-BR');
    }

    getStatusLabel(status) {
        const statusMap = {
            active: 'Ativa',
            completed: 'Concluída',
            paused: 'Pausada'
        };
        return statusMap[status] || status;
    }
}

// Project Management Functions
window.showAddProjectModal = function() {
    const content = `
        <form id="project-form">
            <div class="form-group">
                <label class="form-label">Nome da Obra</label>
                <input type="text" id="project-name" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label">Endereço</label>
                <textarea id="project-address" class="form-textarea" required></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Prazo</label>
                <input type="date" id="project-deadline" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label">Orçamento (R$)</label>
                <input type="number" id="project-budget" class="form-input" step="0.01" required>
            </div>
            <div class="form-group">
                <label class="form-label">Responsável</label>
                <input type="text" id="project-manager" class="form-input" required>
            </div>
        </form>
    `;

    const footer = `
        <button type="button" class="btn btn-secondary" onclick="app.closeModal()">Cancelar</button>
        <button type="button" class="btn btn-primary" onclick="app.saveProject()">Salvar</button>
    `;

    app.showModal('Nova Obra', content, footer);
};

// Initialize the application
const app = new ConstructionApp();