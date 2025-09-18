// Additional project management functions for the Construction Management System

// Project CRUD Functions
app.saveProject = async function(projectId = null) {
    const name = document.getElementById('project-name').value;
    const address = document.getElementById('project-address').value;
    const deadline = document.getElementById('project-deadline').value;
    const budget = document.getElementById('project-budget').value;
    const manager = document.getElementById('project-manager').value;

    if (!name || !address || !deadline || !budget || !manager) {
        this.showAlert('Todos os campos são obrigatórios', 'error');
        return;
    }

    const data = { name, address, deadline, budget: parseFloat(budget), manager };

    try {
        const url = projectId ? `api/projects.php?id=${projectId}` : 'api/projects.php';
        const method = projectId ? 'PUT' : 'POST';
        
        const response = await fetch(url, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });

        const result = await response.json();
        
        if (result.success) {
            this.showAlert(projectId ? 'Obra atualizada com sucesso!' : 'Obra criada com sucesso!', 'success');
            this.closeModal();
            await this.loadProjects();
            this.updateDashboardStats();
            if (this.currentSection === 'dashboard') {
                this.displayRecentProjects();
            } else if (this.currentSection === 'projects') {
                this.displayAllProjects();
            }
        } else {
            throw new Error(result.error);
        }
    } catch (error) {
        console.error('Error saving project:', error);
        this.showAlert('Erro ao salvar obra', 'error');
    }
};

app.editProject = async function(projectId) {
    try {
        const response = await fetch(`api/projects.php?id=${projectId}`);
        const result = await response.json();
        
        if (result.success) {
            const project = result.data;
            
            const content = `
                <form id="project-form">
                    <div class="form-group">
                        <label class="form-label">Nome da Obra</label>
                        <input type="text" id="project-name" class="form-input" value="${project.name}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Endereço</label>
                        <textarea id="project-address" class="form-textarea" required>${project.address}</textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Prazo</label>
                        <input type="date" id="project-deadline" class="form-input" value="${project.deadline}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Orçamento (R$)</label>
                        <input type="number" id="project-budget" class="form-input" value="${project.budget}" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Responsável</label>
                        <input type="text" id="project-manager" class="form-input" value="${project.manager}" required>
                    </div>
                </form>
            `;

            const footer = `
                <button type="button" class="btn btn-secondary" onclick="app.closeModal()">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="app.saveProject(${projectId})">Salvar</button>
            `;

            this.showModal('Editar Obra', content, footer);
        } else {
            throw new Error(result.error);
        }
    } catch (error) {
        console.error('Error loading project for edit:', error);
        this.showAlert('Erro ao carregar dados da obra', 'error');
    }
};

app.deleteProject = async function(projectId) {
    if (!confirm('Tem certeza que deseja excluir esta obra? Esta ação não pode ser desfeita.')) {
        return;
    }

    try {
        const response = await fetch(`api/projects.php?id=${projectId}`, {
            method: 'DELETE'
        });
        
        const result = await response.json();
        
        if (result.success) {
            this.showAlert('Obra excluída com sucesso!', 'success');
            await this.loadProjects();
            this.updateDashboardStats();
            if (this.currentSection === 'dashboard') {
                this.displayRecentProjects();
            } else if (this.currentSection === 'projects') {
                this.displayAllProjects();
            }
        } else {
            throw new Error(result.error);
        }
    } catch (error) {
        console.error('Error deleting project:', error);
        this.showAlert('Erro ao excluir obra', 'error');
    }
};

// Material Functions
window.showAddMaterialModal = function() {
    const content = `
        <form id="material-form">
            <div class="form-group">
                <label class="form-label">Nome do Material</label>
                <input type="text" id="material-name" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label">Quantidade</label>
                <input type="number" id="material-quantity" class="form-input" step="0.01" required>
            </div>
            <div class="form-group">
                <label class="form-label">Unidade</label>
                <input type="text" id="material-unit" class="form-input" value="unidade" required>
            </div>
            <div class="form-group">
                <label class="form-label">Custo Unitário (R$)</label>
                <input type="number" id="material-cost" class="form-input" step="0.01" required>
            </div>
        </form>
    `;

    const footer = `
        <button type="button" class="btn btn-secondary" onclick="app.closeModal()">Cancelar</button>
        <button type="button" class="btn btn-primary" onclick="app.saveMaterial()">Salvar</button>
    `;

    app.showModal('Novo Material', content, footer);
};

app.saveMaterial = async function(materialId = null) {
    const name = document.getElementById('material-name').value;
    const quantity = document.getElementById('material-quantity').value;
    const unit = document.getElementById('material-unit').value;
    const cost = document.getElementById('material-cost').value;

    if (!name || !quantity || !unit || !cost) {
        this.showAlert('Todos os campos são obrigatórios', 'error');
        return;
    }

    const data = { 
        project_id: this.currentProject, 
        name, 
        quantity: parseFloat(quantity), 
        unit, 
        cost: parseFloat(cost) 
    };

    try {
        const url = materialId ? `api/materials.php?id=${materialId}` : 'api/materials.php';
        const method = materialId ? 'PUT' : 'POST';
        
        const response = await fetch(url, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });

        const result = await response.json();
        
        if (result.success) {
            this.showAlert(materialId ? 'Material atualizado com sucesso!' : 'Material cadastrado com sucesso!', 'success');
            this.closeModal();
            this.loadMaterials(this.currentProject);
        } else {
            throw new Error(result.error);
        }
    } catch (error) {
        console.error('Error saving material:', error);
        this.showAlert('Erro ao salvar material', 'error');
    }
};

app.editMaterial = async function(materialId) {
    try {
        const response = await fetch(`api/materials.php?id=${materialId}`);
        const result = await response.json();
        
        if (result.success) {
            const material = result.data;
            
            const content = `
                <form id="material-form">
                    <div class="form-group">
                        <label class="form-label">Nome do Material</label>
                        <input type="text" id="material-name" class="form-input" value="${material.name}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Quantidade</label>
                        <input type="number" id="material-quantity" class="form-input" value="${material.quantity}" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Unidade</label>
                        <input type="text" id="material-unit" class="form-input" value="${material.unit}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Custo Unitário (R$)</label>
                        <input type="number" id="material-cost" class="form-input" value="${material.cost}" step="0.01" required>
                    </div>
                </form>
            `;

            const footer = `
                <button type="button" class="btn btn-secondary" onclick="app.closeModal()">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="app.saveMaterial(${materialId})">Salvar</button>
            `;

            this.showModal('Editar Material', content, footer);
        } else {
            throw new Error(result.error);
        }
    } catch (error) {
        console.error('Error loading material for edit:', error);
        this.showAlert('Erro ao carregar dados do material', 'error');
    }
};

app.deleteMaterial = async function(materialId) {
    if (!confirm('Tem certeza que deseja excluir este material?')) {
        return;
    }

    try {
        const response = await fetch(`api/materials.php?id=${materialId}`, {
            method: 'DELETE'
        });
        
        const result = await response.json();
        
        if (result.success) {
            this.showAlert('Material excluído com sucesso!', 'success');
            this.loadMaterials(this.currentProject);
        } else {
            throw new Error(result.error);
        }
    } catch (error) {
        console.error('Error deleting material:', error);
        this.showAlert('Erro ao excluir material', 'error');
    }
};

// Transaction Functions  
window.showAddTransactionModal = function() {
    const content = `
        <form id="transaction-form">
            <div class="form-group">
                <label class="form-label">Tipo</label>
                <select id="transaction-type" class="form-select" required>
                    <option value="">Selecione o tipo</option>
                    <option value="expense">Despesa</option>
                    <option value="revenue">Receita</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Descrição</label>
                <textarea id="transaction-description" class="form-textarea" required></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Valor (R$)</label>
                <input type="number" id="transaction-amount" class="form-input" step="0.01" required>
            </div>
            <div class="form-group">
                <label class="form-label">Data</label>
                <input type="date" id="transaction-date" class="form-input" value="${new Date().toISOString().split('T')[0]}" required>
            </div>
        </form>
    `;

    const footer = `
        <button type="button" class="btn btn-secondary" onclick="app.closeModal()">Cancelar</button>
        <button type="button" class="btn btn-primary" onclick="app.saveTransaction()">Salvar</button>
    `;

    app.showModal('Nova Transação', content, footer);
};

app.saveTransaction = async function(transactionId = null) {
    const type = document.getElementById('transaction-type').value;
    const description = document.getElementById('transaction-description').value;
    const amount = document.getElementById('transaction-amount').value;
    const transaction_date = document.getElementById('transaction-date').value;

    if (!type || !description || !amount || !transaction_date) {
        this.showAlert('Todos os campos são obrigatórios', 'error');
        return;
    }

    const data = { 
        project_id: this.currentProject, 
        type, 
        description, 
        amount: parseFloat(amount), 
        transaction_date 
    };

    try {
        const url = transactionId ? `api/transactions.php?id=${transactionId}` : 'api/transactions.php';
        const method = transactionId ? 'PUT' : 'POST';
        
        const response = await fetch(url, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });

        const result = await response.json();
        
        if (result.success) {
            this.showAlert(transactionId ? 'Transação atualizada com sucesso!' : 'Transação registrada com sucesso!', 'success');
            this.closeModal();
            this.loadTransactions(this.currentProject);
            this.loadFinancialSummary(this.currentProject);
        } else {
            throw new Error(result.error);
        }
    } catch (error) {
        console.error('Error saving transaction:', error);
        this.showAlert('Erro ao salvar transação', 'error');
    }
};

// Team Member Functions
window.showAddTeamMemberModal = function() {
    const content = `
        <form id="team-form">
            <div class="form-group">
                <label class="form-label">Nome</label>
                <input type="text" id="member-name" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label">Função</label>
                <input type="text" id="member-role" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label">Custo por Hora (R$)</label>
                <input type="number" id="member-rate" class="form-input" step="0.01" required>
            </div>
        </form>
    `;

    const footer = `
        <button type="button" class="btn btn-secondary" onclick="app.closeModal()">Cancelar</button>
        <button type="button" class="btn btn-primary" onclick="app.saveTeamMember()">Salvar</button>
    `;

    app.showModal('Novo Membro da Equipe', content, footer);
};

app.saveTeamMember = async function(memberId = null) {
    const name = document.getElementById('member-name').value;
    const role = document.getElementById('member-role').value;
    const hourly_rate = document.getElementById('member-rate').value;

    if (!name || !role || !hourly_rate) {
        this.showAlert('Todos os campos são obrigatórios', 'error');
        return;
    }

    const data = { 
        project_id: this.currentProject, 
        name, 
        role, 
        hourly_rate: parseFloat(hourly_rate) 
    };

    try {
        const url = memberId ? `api/team.php?id=${memberId}` : 'api/team.php';
        const method = memberId ? 'PUT' : 'POST';
        
        const response = await fetch(url, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });

        const result = await response.json();
        
        if (result.success) {
            this.showAlert(memberId ? 'Membro atualizado com sucesso!' : 'Membro adicionado com sucesso!', 'success');
            this.closeModal();
            this.loadTeamMembers(this.currentProject);
        } else {
            throw new Error(result.error);
        }
    } catch (error) {
        console.error('Error saving team member:', error);
        this.showAlert('Erro ao salvar membro da equipe', 'error');
    }
};