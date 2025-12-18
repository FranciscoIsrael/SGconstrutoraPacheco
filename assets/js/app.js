// Construction Management System - Main App JavaScript
const app = {
    projects: [],
    currentProject: null,
    currentSection: 'dashboard',
    valuesHidden: false,
    
    init() {
        this.setupNavigation();
        this.loadProjects().then(() => {
            this.displayRecentProjects();
            this.displayDeadlineAlerts();
        });
        this.updateDashboardStats();
        this.loadValuesVisibility();
    },

    displayDeadlineAlerts() {
        const container = document.getElementById('deadline-alerts');
        if (!container) return;
        
        const today = new Date();
        const sevenDays = new Date(today.getTime() + 7 * 24 * 60 * 60 * 1000);
        
        const upcomingDeadlines = this.projects.filter(p => {
            if (p.status === 'completed') return false;
            const deadline = new Date(p.deadline);
            return deadline <= sevenDays && deadline >= today;
        });

        const overdueProjects = this.projects.filter(p => {
            if (p.status === 'completed') return false;
            const deadline = new Date(p.deadline);
            return deadline < today;
        });

        const completedProjects = this.projects.filter(p => p.status === 'completed');

        let html = '';
        
        if (overdueProjects.length > 0) {
            html += `<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> ${overdueProjects.length} obra(s) com prazo vencido!</div>`;
        }
        
        if (upcomingDeadlines.length > 0) {
            html += `<div class="alert alert-warning"><i class="fas fa-clock"></i> ${upcomingDeadlines.length} obra(s) com prazo nos próximos 7 dias</div>`;
        }

        if (completedProjects.length > 0) {
            html += `<div class="alert alert-success"><i class="fas fa-check-circle"></i> ${completedProjects.length} obra(s) concluída(s)</div>`;
        }
        
        container.innerHTML = html;
    },

    loadValuesVisibility() {
        this.valuesHidden = localStorage.getItem('valuesHidden') === 'true';
        this.updateValuesVisibility();
    },

    toggleValuesVisibility() {
        this.valuesHidden = !this.valuesHidden;
        localStorage.setItem('valuesHidden', this.valuesHidden);
        this.updateValuesVisibility();
    },

    updateValuesVisibility() {
        const btn = document.getElementById('toggle-values-btn');
        const valueElements = document.querySelectorAll('.currency-value, #total-budget, #total-spent, #total-inventory-value');
        
        if (this.valuesHidden) {
            btn.innerHTML = '<i class="fas fa-eye-slash"></i> Mostrar Valores';
            valueElements.forEach(el => {
                if (!el.dataset.originalValue) {
                    el.dataset.originalValue = el.textContent;
                }
                el.textContent = 'R$ ••••••';
                el.classList.add('values-hidden');
            });
            document.body.classList.add('hide-values');
        } else {
            btn.innerHTML = '<i class="fas fa-eye"></i> Ocultar Valores';
            valueElements.forEach(el => {
                if (el.dataset.originalValue) {
                    el.textContent = el.dataset.originalValue;
                }
                el.classList.remove('values-hidden');
            });
            document.body.classList.remove('hide-values');
        }
    },

    setupNavigation() {
        const navLinks = document.querySelectorAll('.nav-link');
        navLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const section = link.dataset.section;
                this.showSection(section);
                
                navLinks.forEach(l => l.classList.remove('active'));
                link.classList.add('active');
            });
        });
    },

    showSection(sectionName) {
        document.querySelectorAll('.content-section').forEach(section => {
            section.classList.remove('active');
        });
        
        const targetSection = document.getElementById(sectionName);
        if (targetSection) {
            targetSection.classList.add('active');
            this.currentSection = sectionName;
            
            switch(sectionName) {
                case 'dashboard':
                    this.updateDashboardStats();
                    this.displayRecentProjects();
                    break;
                case 'projects':
                    this.displayAllProjects();
                    break;
                case 'materials':
                    this.loadProjectSelects('materials');
                    break;
                case 'financial':
                    this.loadProjectSelects('financial');
                    break;
                case 'team':
                    this.loadProjectSelects('team');
                    break;
                case 'inventory':
                    this.loadInventory();
                    this.updateInventoryStats();
                    break;
            }
        }
    },

    async loadProjects() {
        try {
            const response = await fetch('api/projects.php');
            const result = await response.json();
            
            if (result.success) {
                this.projects = result.data;
                this.loadProjectSelects();
            }
        } catch (error) {
            console.error('Error loading projects:', error);
        }
    },

    loadProjectSelects(module = null) {
        const selects = module ? 
            [`#project-select-${module}`] : 
            ['#project-select-materials', '#project-select-financial', '#project-select-team'];
        
        selects.forEach(selector => {
            const select = document.querySelector(selector);
            if (select) {
                select.innerHTML = '<option value="">Selecione uma obra</option>';
                this.projects.forEach(project => {
                    const option = document.createElement('option');
                    option.value = project.id;
                    option.textContent = project.name;
                    select.appendChild(option);
                });
                
                select.addEventListener('change', (e) => {
                    const projectId = e.target.value;
                    this.currentProject = projectId;
                    
                    if (module === 'materials' || selector.includes('materials')) {
                        const btn = document.getElementById('add-material-btn');
                        btn.disabled = !projectId;
                        if (projectId) this.loadMaterials(projectId);
                    } else if (module === 'financial' || selector.includes('financial')) {
                        const btn = document.getElementById('add-transaction-btn');
                        btn.disabled = !projectId;
                        if (projectId) {
                            this.loadTransactions(projectId);
                            this.loadFinancialSummary(projectId);
                        }
                    } else if (module === 'team' || selector.includes('team')) {
                        const btn = document.getElementById('add-member-btn');
                        btn.disabled = !projectId;
                        if (projectId) this.loadTeamMembers(projectId);
                    }
                });
            }
        });
    },

    async updateDashboardStats() {
        try {
            const response = await fetch('api/projects.php');
            const result = await response.json();
            
            if (result.success) {
                const projects = result.data;
                const totalProjects = projects.length;
                const totalBudget = projects.reduce((sum, p) => sum + parseFloat(p.budget || 0), 0);
                const totalSpent = projects.reduce((sum, p) => sum + parseFloat(p.total_spent || 0), 0);
                const totalTeam = projects.reduce((sum, p) => sum + parseInt(p.team_count || 0), 0);
                
                document.getElementById('total-projects').textContent = totalProjects;
                document.getElementById('total-budget').textContent = this.formatCurrency(totalBudget);
                document.getElementById('total-spent').textContent = this.formatCurrency(totalSpent);
                document.getElementById('total-team').textContent = totalTeam;
            }
        } catch (error) {
            console.error('Error updating stats:', error);
        }
    },

    displayRecentProjects() {
        const container = document.getElementById('projects-list');
        const recentProjects = this.projects.slice(0, 6);
        
        if (recentProjects.length === 0) {
            container.innerHTML = '<p class="text-center text-muted"><i class="fas fa-building"></i><br>Nenhuma obra cadastrada ainda.</p>';
            return;
        }
        
        container.innerHTML = recentProjects.map(project => this.createProjectCard(project)).join('');
    },

    displayAllProjects() {
        const container = document.getElementById('all-projects-list');
        
        if (this.projects.length === 0) {
            container.innerHTML = '<p class="text-center text-muted"><i class="fas fa-building"></i><br>Nenhuma obra cadastrada ainda.</p>';
            return;
        }
        
        container.innerHTML = this.projects.map(project => this.createProjectCard(project)).join('');
    },

    createProjectCard(project) {
        const deadline = project.deadline ? new Date(project.deadline) : null;
        const deadlineStr = deadline ? deadline.toLocaleDateString('pt-BR') : 'Não definido';
        const budgetPercent = project.budget > 0 ? ((project.total_spent || 0) / project.budget) * 100 : 0;
        const status = project.status || 'active';
        const statusClass = status === 'active' ? 'status-active' : 
                           status === 'completed' ? 'status-completed' : 'status-paused';
        const statusText = status === 'active' ? 'Ativa' : status === 'completed' ? 'Concluída' : 'Pausada';
        
        const today = new Date();
        let deadlineWarning = '';
        if (deadline && status !== 'completed') {
            const daysUntil = Math.ceil((deadline - today) / (1000 * 60 * 60 * 24));
            if (daysUntil < 0) {
                deadlineWarning = '<span class="deadline-alert overdue"><i class="fas fa-exclamation-triangle"></i> Vencido</span>';
            } else if (daysUntil <= 7) {
                deadlineWarning = `<span class="deadline-alert warning"><i class="fas fa-clock"></i> ${daysUntil} dias</span>`;
            }
        }
        
        return `
            <div class="project-card">
                <div class="project-header">
                    <div>
                        <h3 class="project-title">${project.name || 'Sem nome'}</h3>
                        <span class="project-status ${statusClass}">${statusText}</span>
                        ${deadlineWarning}
                    </div>
                </div>
                ${project.image_path ? `<img src="${project.image_path}" alt="${project.name || 'Obra'}" style="width: 100%; height: 150px; object-fit: cover; border-radius: 5px; margin: 10px 0;">` : ''}
                <div class="project-info">
                    <p><i class="fas fa-map-marker-alt"></i> ${project.address || 'Endereço não informado'}</p>
                    <p><i class="fas fa-calendar"></i> Prazo: ${deadlineStr}</p>
                    <p><i class="fas fa-user-tie"></i> Responsável: ${project.manager || 'Não definido'}</p>
                </div>
                <div class="project-budget">
                    <div class="budget-item">
                        <div class="budget-label">Orçamento</div>
                        <div class="budget-value">${this.formatCurrency(project.budget || 0)}</div>
                    </div>
                    <div class="budget-item">
                        <div class="budget-label">Gasto</div>
                        <div class="budget-value ${budgetPercent > 100 ? 'text-danger' : ''}">${this.formatCurrency(project.total_spent || 0)}</div>
                    </div>
                </div>
                <div class="project-actions">
                    <button class="btn btn-sm btn-info" onclick="app.viewProjectDetails(${project.id})" title="Ver Detalhes">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn btn-sm btn-warning" onclick="app.editProject(${project.id})" title="Editar">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-secondary" onclick="showImagesModal('projects', ${project.id}, 'Fotos da Obra')" title="Fotos">
                        <i class="fas fa-images"></i>
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="app.deleteProject(${project.id})" title="Excluir">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
    },

    async loadMaterials(projectId) {
        try {
            const response = await fetch(`api/materials.php?project_id=${projectId}`);
            const result = await response.json();
            
            if (result.success) {
                this.displayMaterials(result.data);
            }
        } catch (error) {
            console.error('Error loading materials:', error);
        }
    },

    displayMaterials(materials) {
        const container = document.getElementById('materials-list');
        
        if (materials.length === 0) {
            container.innerHTML = '<p class="text-center text-muted">Nenhum material cadastrado para esta obra.</p>';
            return;
        }
        
        const totalCost = materials.reduce((sum, m) => sum + (m.quantity * m.cost), 0);
        
        container.innerHTML = `
            <table class="table">
                <thead>
                    <tr>
                        <th>Foto</th>
                        <th>Material</th>
                        <th>Quantidade</th>
                        <th>Custo Unit.</th>
                        <th>Total</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    ${materials.map(m => `
                        <tr>
                            <td>${m.image_path ? `<img src="${m.image_path}" style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px;">` : '<i class="fas fa-box text-muted"></i>'}</td>
                            <td>${m.name}</td>
                            <td>${m.quantity} ${m.unit}</td>
                            <td>${this.formatCurrency(m.cost)}</td>
                            <td><strong>${this.formatCurrency(m.total_cost)}</strong></td>
                            <td>
                                <button class="btn btn-sm btn-warning" onclick="app.editMaterial(${m.id})"><i class="fas fa-edit"></i></button>
                                <button class="btn btn-sm btn-danger" onclick="app.deleteMaterial(${m.id})"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" class="text-right"><strong>Total:</strong></td>
                        <td colspan="2"><strong>${this.formatCurrency(totalCost)}</strong></td>
                    </tr>
                </tfoot>
            </table>
        `;
    },

    async loadFinancialSummary(projectId) {
        try {
            const response = await fetch(`api/transactions.php?project_id=${projectId}&summary=1`);
            const result = await response.json();
            
            if (result.success) {
                this.displayFinancialSummary(result.data);
            }
        } catch (error) {
            console.error('Error loading financial summary:', error);
        }
    },

    displayFinancialSummary(summary) {
        const container = document.getElementById('financial-summary');
        const usagePercent = summary.budget > 0 ? summary.budget_usage_percent : 0;
        
        container.innerHTML = `
            <div class="stat-card">
                <div class="stat-content">
                    <h3>${this.formatCurrency(summary.budget)}</h3>
                    <p>Orçamento Total</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-content">
                    <h3 class="text-danger">${this.formatCurrency(summary.total_expenses)}</h3>
                    <p>Total Despesas</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-content">
                    <h3 class="text-success">${this.formatCurrency(summary.total_revenue)}</h3>
                    <p>Total Receitas</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-content">
                    <h3 class="${usagePercent > 100 ? 'text-danger' : 'text-success'}">${this.formatCurrency(summary.remaining_budget)}</h3>
                    <p>Saldo Restante (${usagePercent.toFixed(1)}%)</p>
                </div>
            </div>
        `;
    },

    async loadTransactions(projectId) {
        try {
            const response = await fetch(`api/transactions.php?project_id=${projectId}`);
            const result = await response.json();
            
            if (result.success) {
                this.displayTransactions(result.data);
            }
        } catch (error) {
            console.error('Error loading transactions:', error);
        }
    },

    displayTransactions(transactions) {
        const container = document.getElementById('transactions-list');
        
        if (transactions.length === 0) {
            container.innerHTML = '<p class="text-center text-muted">Nenhuma transação registrada para esta obra.</p>';
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
                        <th>Anexo</th>
                    </tr>
                </thead>
                <tbody>
                    ${transactions.map(t => `
                        <tr>
                            <td>${t.transaction_date_formatted}</td>
                            <td><span class="badge ${t.type === 'expense' ? 'badge-danger' : 'badge-success'}">${t.type === 'expense' ? 'Despesa' : 'Receita'}</span></td>
                            <td>${t.description}</td>
                            <td class="${t.type === 'expense' ? 'text-danger' : 'text-success'}">${this.formatCurrency(t.amount)}</td>
                            <td>${t.image_path ? `<a href="${t.image_path}" target="_blank"><i class="fas fa-paperclip"></i></a>` : '-'}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    },

    async loadTeamMembers(projectId) {
        try {
            const response = await fetch(`api/team.php?project_id=${projectId}`);
            const result = await response.json();
            
            if (result.success) {
                this.displayTeamMembers(result.data);
            }
        } catch (error) {
            console.error('Error loading team members:', error);
        }
    },

    displayTeamMembers(members) {
        const container = document.getElementById('team-list');
        
        if (members.length === 0) {
            container.innerHTML = '<p class="text-center text-muted">Nenhum membro cadastrado para esta obra.</p>';
            return;
        }
        
        container.innerHTML = `
            <table class="table">
                <thead>
                    <tr>
                        <th>Foto</th>
                        <th>Nome</th>
                        <th>Função</th>
                        <th>Custo/Hora</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    ${members.map(m => `
                        <tr>
                            <td>${m.image_path ? `<img src="${m.image_path}" style="width: 40px; height: 40px; object-fit: cover; border-radius: 50%;">` : '<i class="fas fa-user-circle text-muted" style="font-size: 2rem;"></i>'}</td>
                            <td>${m.name}</td>
                            <td>${m.role}</td>
                            <td>${this.formatCurrency(m.hourly_rate)}/h</td>
                            <td>
                                <button class="btn btn-sm btn-danger" onclick="app.deleteTeamMember(${m.id})"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    },

    async deleteTeamMember(memberId) {
        if (!confirm('Tem certeza que deseja remover este membro?')) return;
        
        try {
            const response = await fetch(`api/team.php?id=${memberId}`, { method: 'DELETE' });
            const result = await response.json();
            
            if (result.success) {
                this.showAlert('Membro removido com sucesso!', 'success');
                this.loadTeamMembers(this.currentProject);
            }
        } catch (error) {
            this.showAlert('Erro ao remover membro', 'error');
        }
    },

    formatCurrency(value) {
        return 'R$ ' + parseFloat(value).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },

    showModal(title, content, footer, sizeClass = '') {
        const modal = `
            <div class="modal active">
                <div class="modal-content ${sizeClass}">
                    <div class="modal-header">
                        <h3>${title}</h3>
                        <button class="modal-close" onclick="app.closeModal()">&times;</button>
                    </div>
                    <div class="modal-body">
                        ${content}
                    </div>
                    <div class="modal-footer">
                        ${footer}
                    </div>
                </div>
            </div>
        `;
        document.getElementById('modal-container').innerHTML = modal;
    },

    closeModal() {
        document.getElementById('modal-container').innerHTML = '';
    },

    showAlert(message, type = 'info') {
        const alertClass = type === 'success' ? 'alert-success' : 
                          type === 'error' ? 'alert-error' : 
                          type === 'warning' ? 'alert-warning' : 'alert-info';
        
        const alert = document.createElement('div');
        alert.className = `alert ${alertClass}`;
        alert.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i> ${message}`;
        alert.style.position = 'fixed';
        alert.style.top = '20px';
        alert.style.right = '20px';
        alert.style.zIndex = '9999';
        
        document.body.appendChild(alert);
        
        setTimeout(() => {
            alert.remove();
        }, 3000);
    }
};

// Global function for adding new project
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
            <div class="form-group">
                <label class="form-label">Foto da Obra (opcional)</label>
                <input type="file" id="project-image" class="form-input" accept="image/*" onchange="app.handleImageUpload(this, 'project-image-preview')">
                <div id="project-image-preview" style="margin-top: 10px;"></div>
            </div>
        </form>
    `;

    const footer = `
        <button type="button" class="btn btn-secondary" onclick="app.closeModal()">Cancelar</button>
        <button type="button" class="btn btn-primary" onclick="app.saveProject()">Salvar</button>
    `;

    app.showModal('Nova Obra', content, footer);
};

// Handle image upload
app.handleImageUpload = async function(input, previewId) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const formData = new FormData();
        formData.append('image', file);
        
        try {
            const response = await fetch('api/upload.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            
            if (result.success) {
                const preview = document.getElementById(previewId);
                preview.innerHTML = `<img src="${result.data.path}" style="max-width: 200px; border-radius: 5px;">`;
                input.dataset.uploadedPath = result.data.path;
            } else {
                this.showAlert('Erro ao fazer upload da imagem', 'error');
            }
        } catch (error) {
            this.showAlert('Erro ao fazer upload da imagem', 'error');
        }
    }
};

// Global function for toggle values button
function toggleValuesVisibility() {
    app.toggleValuesVisibility();
}

// Global utility functions
function formatCurrency(value) {
    return 'R$ ' + parseFloat(value || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.remove();
    }
}

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
        <span>${message}</span>
    `;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.classList.add('show');
    }, 100);
    
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

async function loadProjectsForSelect(selectId, selectedValue = null) {
    try {
        const response = await fetch('api/projects.php');
        const data = await response.json();
        
        if (data.success) {
            const select = document.getElementById(selectId);
            if (select) {
                data.data.forEach(project => {
                    const option = document.createElement('option');
                    option.value = project.id;
                    option.textContent = project.name;
                    if (selectedValue && project.id == selectedValue) {
                        option.selected = true;
                    }
                    select.appendChild(option);
                });
            }
        }
    } catch (error) {
        console.error('Error loading projects:', error);
    }
}

// Images gallery modal
async function showImagesModal(tableName, recordId, title) {
    try {
        const response = await fetch(`api/images.php?table_name=${tableName}&record_id=${recordId}`);
        const data = await response.json();
        
        const modal = document.createElement('div');
        modal.className = 'modal active';
        modal.id = 'images-modal';
        modal.innerHTML = `
            <div class="modal-content modal-lg">
                <div class="modal-header">
                    <h3><i class="fas fa-images"></i> ${title}</h3>
                    <button class="close-btn" onclick="closeModal('images-modal')">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="upload-section">
                        <div class="upload-area" onclick="document.getElementById('multi-image-input').click()">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <p>Clique para adicionar imagens</p>
                            <input type="file" id="multi-image-input" accept="image/*" multiple 
                                onchange="uploadMultipleImages(this, '${tableName}', ${recordId})" hidden>
                        </div>
                    </div>
                    <div class="images-gallery" id="images-gallery">
                        ${data.success && data.data.length > 0 
                            ? data.data.map(img => `
                                <div class="gallery-item">
                                    <img src="${img.file_path}" alt="${img.file_name || 'Imagem'}">
                                    <button class="delete-image-btn" onclick="deleteGalleryImage(${img.id})">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            `).join('')
                            : '<p class="no-images">Nenhuma imagem cadastrada</p>'
                        }
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" onclick="closeModal('images-modal')">Fechar</button>
                </div>
            </div>
        `;
        document.getElementById('modal-container').appendChild(modal);
    } catch (error) {
        console.error('Error loading images:', error);
    }
}

async function uploadMultipleImages(input, tableName, recordId) {
    const files = input.files;
    if (!files.length) return;
    
    for (const file of files) {
        const formData = new FormData();
        formData.append('image', file);
        
        try {
            const uploadResponse = await fetch('api/upload.php', { method: 'POST', body: formData });
            const uploadData = await uploadResponse.json();
            
            if (uploadData.success) {
                await fetch('api/images.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        table_name: tableName,
                        record_id: recordId,
                        file_path: uploadData.data.path,
                        file_name: file.name
                    })
                });
            }
        } catch (error) {
            console.error('Error uploading image:', error);
        }
    }
    
    closeModal('images-modal');
    showImagesModal(tableName, recordId, 'Galeria de Imagens');
    showNotification('Imagens adicionadas com sucesso!', 'success');
}

async function deleteGalleryImage(imageId) {
    if (!confirm('Remover esta imagem?')) return;
    
    try {
        const response = await fetch(`api/images.php?id=${imageId}`, { method: 'DELETE' });
        const data = await response.json();
        
        if (data.success) {
            document.querySelector(`[onclick="deleteGalleryImage(${imageId})"]`).closest('.gallery-item').remove();
            showNotification('Imagem removida!', 'success');
        }
    } catch (error) {
        console.error('Error deleting image:', error);
    }
}

// History modal
function showHistoryModal(history, title) {
    const modal = document.createElement('div');
    modal.className = 'modal active';
    modal.id = 'history-modal';
    modal.innerHTML = `
        <div class="modal-content modal-lg">
            <div class="modal-header">
                <h3><i class="fas fa-history"></i> ${title}</h3>
                <button class="close-btn" onclick="closeModal('history-modal')">&times;</button>
            </div>
            <div class="modal-body">
                ${history.length > 0 
                    ? `<div class="history-timeline">
                        ${history.map(h => `
                            <div class="history-entry">
                                <div class="history-badge ${h.action}">
                                    <i class="fas fa-${h.action === 'create' ? 'plus' : h.action === 'update' ? 'edit' : 'trash'}"></i>
                                </div>
                                <div class="history-content">
                                    <div class="history-header">
                                        <span class="history-action-label">${h.action === 'create' ? 'Criado' : h.action === 'update' ? 'Atualizado' : 'Excluído'}</span>
                                        <span class="history-date">${h.created_at_formatted || new Date(h.created_at).toLocaleString('pt-BR')}</span>
                                    </div>
                                    <div class="history-details">
                                        <span>Registro #${h.record_id}</span>
                                    </div>
                                </div>
                            </div>
                        `).join('')}
                    </div>`
                    : '<p class="no-history">Nenhum registro de alterações</p>'
                }
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('history-modal')">Fechar</button>
            </div>
        </div>
    `;
    document.getElementById('modal-container').appendChild(modal);
}

// Initialize app when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    app.init();
    
    // Load team when section is opened
    const teamNavLink = document.querySelector('[data-section="team"]');
    if (teamNavLink) {
        teamNavLink.addEventListener('click', () => {
            if (typeof loadTeamMembers === 'function') {
                loadTeamMembers();
            }
        });
    }
});