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
            this.displayRecentProjects();
            this.displayAllProjects();
            this.displayDeadlineAlerts();
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
window.showAddMaterialModal = async function() {
    let inventoryOptions = '<option value="">-- Cadastrar manualmente --</option>';
    try {
        const response = await fetch('api/inventory.php');
        const result = await response.json();
        if (result.success && result.data.length > 0) {
            inventoryOptions += result.data.map(item => 
                `<option value="${item.id}" data-name="${item.name}" data-unit="${item.unit}" data-cost="${item.unit_cost}" data-qty="${item.quantity}">${item.name} (Estoque: ${item.quantity} ${item.unit})</option>`
            ).join('');
        }
    } catch (e) { console.error(e); }

    const content = `
        <form id="material-form">
            <div class="form-group">
                <label class="form-label">Buscar no Inventário</label>
                <select id="material-inventory" class="form-input" onchange="fillMaterialFromInventory(this)">
                    ${inventoryOptions}
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Nome do Material</label>
                <input type="text" id="material-name" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label">Descrição</label>
                <textarea id="material-description" class="form-input" rows="2"></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Quantidade</label>
                    <input type="number" id="material-quantity" class="form-input" step="0.01" required>
                    <small id="material-stock-info" class="text-muted"></small>
                </div>
                <div class="form-group">
                    <label class="form-label">Unidade</label>
                    <input type="text" id="material-unit" class="form-input" value="unidade" required>
                </div>
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

window.fillMaterialFromInventory = function(select) {
    const option = select.options[select.selectedIndex];
    if (select.value) {
        document.getElementById('material-name').value = option.dataset.name || '';
        document.getElementById('material-unit').value = option.dataset.unit || 'unidade';
        document.getElementById('material-cost').value = option.dataset.cost || '';
        document.getElementById('material-stock-info').textContent = `Disponível: ${option.dataset.qty} ${option.dataset.unit}`;
    } else {
        document.getElementById('material-name').value = '';
        document.getElementById('material-unit').value = 'unidade';
        document.getElementById('material-cost').value = '';
        document.getElementById('material-stock-info').textContent = '';
    }
};

app.saveMaterial = async function(materialId = null) {
    const name = document.getElementById('material-name').value;
    const quantity = document.getElementById('material-quantity').value;
    const unit = document.getElementById('material-unit').value;
    const cost = document.getElementById('material-cost').value;
    const description = document.getElementById('material-description')?.value || '';
    const inventoryId = document.getElementById('material-inventory')?.value || null;

    if (!name || !quantity || !unit || !cost) {
        this.showAlert('Todos os campos são obrigatórios', 'error');
        return;
    }

    const data = { 
        project_id: this.currentProject, 
        name, 
        description,
        quantity: parseFloat(quantity), 
        unit, 
        cost: parseFloat(cost),
        inventory_id: inventoryId ? parseInt(inventoryId) : null
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
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Tipo</label>
                    <select id="transaction-type" class="form-select" required>
                        <option value="">Selecione o tipo</option>
                        <option value="expense">Despesa</option>
                        <option value="revenue">Receita</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Data</label>
                    <input type="date" id="transaction-date" class="form-input" value="${new Date().toISOString().split('T')[0]}" required>
                </div>
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
                <label class="form-label">Comprovante (opcional)</label>
                <div class="upload-area-mini" onclick="document.getElementById('transaction-receipt').click()">
                    <i class="fas fa-paperclip"></i> Anexar Comprovante
                    <input type="file" id="transaction-receipt" accept="image/*,.pdf" hidden onchange="previewTransactionReceipt(this)">
                </div>
                <div id="receipt-preview" class="receipt-preview"></div>
                <input type="hidden" id="transaction-receipt-path">
            </div>
        </form>
    `;

    const footer = `
        <button type="button" class="btn btn-secondary" onclick="app.closeModal()">Cancelar</button>
        <button type="button" class="btn btn-primary" onclick="app.saveTransaction()">Salvar</button>
    `;

    app.showModal('Nova Transação', content, footer);
};

window.previewTransactionReceipt = async function(input) {
    const file = input.files[0];
    if (!file) return;
    
    const preview = document.getElementById('receipt-preview');
    preview.innerHTML = '<span class="loading">Enviando...</span>';
    
    const formData = new FormData();
    formData.append('file', file);
    formData.append('file_type', 'document');
    
    try {
        const response = await fetch('api/upload.php', { method: 'POST', body: formData });
        const result = await response.json();
        
        if (result.success) {
            document.getElementById('transaction-receipt-path').value = result.path;
            const isImage = file.type.startsWith('image/');
            preview.innerHTML = isImage 
                ? `<img src="${result.path}" alt="Comprovante" style="max-width: 100px; max-height: 80px;">`
                : `<i class="fas fa-file-pdf"></i> ${file.name}`;
        }
    } catch (e) {
        preview.innerHTML = '<span class="text-danger">Erro ao enviar</span>';
    }
};

app.saveTransaction = async function(transactionId = null) {
    const type = document.getElementById('transaction-type').value;
    const description = document.getElementById('transaction-description').value;
    const amount = document.getElementById('transaction-amount').value;
    const transaction_date = document.getElementById('transaction-date').value;
    const receipt_path = document.getElementById('transaction-receipt-path')?.value || null;

    if (!type || !description || !amount || !transaction_date) {
        this.showAlert('Todos os campos são obrigatórios', 'error');
        return;
    }

    const data = { 
        project_id: this.currentProject, 
        type, 
        description, 
        amount: parseFloat(amount), 
        transaction_date,
        receipt_path
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

app.viewProjectDetails = async function(projectId) {
    try {
        const [projectRes, materialsRes, teamRes, transactionsRes, imagesRes] = await Promise.all([
            fetch(`api/projects.php?id=${projectId}`),
            fetch(`api/materials.php?project_id=${projectId}`),
            fetch(`api/team.php?project_id=${projectId}`),
            fetch(`api/transactions.php?project_id=${projectId}`),
            fetch(`api/images.php?table_name=projects&record_id=${projectId}`)
        ]);

        const project = (await projectRes.json()).data || {};
        const materials = (await materialsRes.json()).data || [];
        const team = (await teamRes.json()).data || [];
        const transactions = (await transactionsRes.json()).data || [];
        const images = (await imagesRes.json()).data || [];

        const totalMaterials = materials.reduce((sum, m) => sum + (m.quantity * m.cost), 0);
        const income = transactions.filter(t => t.type === 'income').reduce((s, t) => s + parseFloat(t.amount), 0);
        const expenses = transactions.filter(t => t.type === 'expense').reduce((s, t) => s + parseFloat(t.amount), 0);
        const balance = parseFloat(project.budget || 0) - expenses;

        const content = `
            <div class="project-details-modal">
                <div class="project-overview">
                    <div class="project-photo-section">
                        ${project.image_path 
                            ? `<img src="${project.image_path}" class="project-main-photo">`
                            : `<div class="project-photo-placeholder"><i class="fas fa-building"></i></div>`
                        }
                    </div>
                    <div class="project-info-section">
                        <h2>${project.name || 'Sem nome'}</h2>
                        <p class="project-address"><i class="fas fa-map-marker-alt"></i> ${project.address || ''}</p>
                        <p><i class="fas fa-user"></i> Responsável: ${project.manager || 'N/A'}</p>
                        <p><i class="fas fa-calendar"></i> Prazo: ${project.deadline ? new Date(project.deadline).toLocaleDateString('pt-BR') : 'N/A'}</p>
                        <span class="status-badge ${project.status || 'active'}">${project.status === 'completed' ? 'Concluída' : project.status === 'paused' ? 'Pausada' : 'Ativa'}</span>
                    </div>
                </div>

                <div class="project-stats-grid">
                    <div class="stat-item">
                        <span class="stat-label">Orçamento</span>
                        <span class="stat-value">${this.formatCurrency(project.budget)}</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Gastos</span>
                        <span class="stat-value expense">${this.formatCurrency(expenses)}</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Saldo</span>
                        <span class="stat-value ${balance >= 0 ? 'positive' : 'negative'}">${this.formatCurrency(balance)}</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Materiais</span>
                        <span class="stat-value">${this.formatCurrency(totalMaterials)}</span>
                    </div>
                </div>

                <div class="project-tabs">
                    <button class="tab-btn active" onclick="showProjectTab('materials-tab')"><i class="fas fa-boxes"></i> Materiais (${materials.length})</button>
                    <button class="tab-btn" onclick="showProjectTab('team-tab')"><i class="fas fa-users"></i> Equipe (${team.length})</button>
                    <button class="tab-btn" onclick="showProjectTab('finance-tab')"><i class="fas fa-dollar-sign"></i> Financeiro (${transactions.length})</button>
                    <button class="tab-btn" onclick="showProjectTab('photos-tab')"><i class="fas fa-images"></i> Fotos (${images.length})</button>
                    <button class="tab-btn" onclick="showProjectTab('docs-tab')"><i class="fas fa-file-alt"></i> Documentos</button>
                </div>

                <div id="materials-tab" class="tab-content active">
                    ${materials.length > 0 ? `
                        <table class="table">
                            <thead><tr><th>Material</th><th>Qtd</th><th>Custo Unit.</th><th>Total</th></tr></thead>
                            <tbody>
                                ${materials.map(m => `<tr><td>${m.name}</td><td>${m.quantity} ${m.unit}</td><td>${this.formatCurrency(m.cost)}</td><td>${this.formatCurrency(m.quantity * m.cost)}</td></tr>`).join('')}
                            </tbody>
                        </table>
                    ` : '<p class="text-center text-muted">Nenhum material cadastrado</p>'}
                </div>

                <div id="team-tab" class="tab-content">
                    ${team.length > 0 ? `
                        <div class="team-list-mini">
                            ${team.map(t => `
                                <div class="team-item-mini">
                                    ${t.image_path ? `<img src="${t.image_path}">` : `<i class="fas fa-user"></i>`}
                                    <div><strong>${t.name}</strong><br><small>${t.role || 'Sem função'}</small></div>
                                </div>
                            `).join('')}
                        </div>
                    ` : '<p class="text-center text-muted">Nenhum membro cadastrado</p>'}
                </div>

                <div id="finance-tab" class="tab-content">
                    <div class="finance-summary">
                        <span class="income"><i class="fas fa-arrow-up"></i> Receitas: ${this.formatCurrency(income)}</span>
                        <span class="expense"><i class="fas fa-arrow-down"></i> Despesas: ${this.formatCurrency(expenses)}</span>
                    </div>
                    ${transactions.length > 0 ? `
                        <table class="table">
                            <thead><tr><th>Data</th><th>Tipo</th><th>Descrição</th><th>Valor</th></tr></thead>
                            <tbody>
                                ${transactions.map(t => `
                                    <tr>
                                        <td>${new Date(t.transaction_date).toLocaleDateString('pt-BR')}</td>
                                        <td><span class="badge ${t.type === 'income' ? 'badge-success' : 'badge-danger'}">${t.type === 'income' ? 'Receita' : 'Despesa'}</span></td>
                                        <td>${t.description || '-'}</td>
                                        <td>${this.formatCurrency(t.amount)}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    ` : '<p class="text-center text-muted">Nenhuma transação registrada</p>'}
                </div>

                <div id="photos-tab" class="tab-content">
                    <div class="upload-form">
                        <div class="form-row">
                            <div class="form-group" style="flex: 2;">
                                <input type="text" id="photo-description-${projectId}" class="form-input" placeholder="Descrição da foto (opcional)">
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <button class="btn btn-primary" onclick="document.getElementById('project-gallery-input').click()">
                                    <i class="fas fa-camera"></i> Adicionar Foto
                                </button>
                                <input type="file" id="project-gallery-input" accept="image/*" onchange="uploadImageWithDescription(this, 'projects', ${projectId})" hidden>
                            </div>
                        </div>
                    </div>
                    <div class="images-gallery">
                        ${images.length > 0 
                            ? images.map(img => `
                                <div class="gallery-item">
                                    <img src="${img.file_path}" alt="${img.file_name || 'Foto'}">
                                    <div class="image-description">${img.description || ''}</div>
                                    <button class="delete-image-btn" onclick="deleteGalleryImage(${img.id})"><i class="fas fa-trash"></i></button>
                                </div>
                            `).join('')
                            : '<p class="no-images">Nenhuma foto cadastrada</p>'
                        }
                    </div>
                </div>

                <div id="docs-tab" class="tab-content">
                    <div class="upload-form">
                        <div class="form-row">
                            <div class="form-group" style="flex: 2;">
                                <input type="text" id="doc-description-${projectId}" class="form-input" placeholder="Descrição do documento">
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <button class="btn btn-primary" onclick="document.getElementById('project-doc-input').click()">
                                    <i class="fas fa-file-upload"></i> Anexar Documento
                                </button>
                                <input type="file" id="project-doc-input" accept=".pdf,.doc,.docx,.xls,.xlsx,.txt" onchange="uploadDocumentWithDescription(this, 'projects', ${projectId})" hidden>
                            </div>
                        </div>
                    </div>
                    <div id="docs-list-${projectId}" class="docs-list">
                        <p class="text-center text-muted">Carregando documentos...</p>
                    </div>
                </div>
            </div>
        `;

        loadProjectDocuments(projectId);

        const footer = `
            <button class="btn btn-secondary" onclick="app.closeModal()">Fechar</button>
            <button class="btn btn-info" onclick="generateProjectPDF(${projectId})"><i class="fas fa-file-pdf"></i> Gerar PDF</button>
            <button class="btn btn-primary" onclick="app.closeModal(); app.editProject(${projectId})"><i class="fas fa-edit"></i> Editar</button>
        `;

        this.showModal(`Detalhes da Obra - ${project.name || ''}`, content, footer, 'modal-xl');
    } catch (error) {
        console.error('Error loading project details:', error);
        this.showAlert('Erro ao carregar detalhes da obra', 'error');
    }
};

window.showProjectTab = function(tabId) {
    document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.getElementById(tabId).classList.add('active');
    event.target.classList.add('active');
};

window.generateProjectPDF = async function(projectId) {
    try {
        showNotification('Gerando PDF...', 'info');
        
        const [projectRes, materialsRes, teamRes, transactionsRes] = await Promise.all([
            fetch(`api/projects.php?id=${projectId}`),
            fetch(`api/materials.php?project_id=${projectId}`),
            fetch(`api/team.php?project_id=${projectId}`),
            fetch(`api/transactions.php?project_id=${projectId}`)
        ]);

        const project = (await projectRes.json()).data || {};
        const materials = (await materialsRes.json()).data || [];
        const team = (await teamRes.json()).data || [];
        const transactions = (await transactionsRes.json()).data || [];

        const totalMaterials = materials.reduce((sum, m) => sum + (m.quantity * m.cost), 0);
        const expenses = transactions.filter(t => t.type === 'expense').reduce((s, t) => s + parseFloat(t.amount), 0);
        const balance = parseFloat(project.budget || 0) - expenses;

        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Relatório - ${project.name}</title>
                <style>
                    body { font-family: Arial, sans-serif; padding: 20px; max-width: 800px; margin: 0 auto; }
                    .header { text-align: center; border-bottom: 2px solid #F5A623; padding-bottom: 20px; margin-bottom: 20px; }
                    .header h1 { color: #2E3B5B; margin: 0; }
                    .header .logo { color: #F5A623; font-weight: bold; font-size: 1.2em; }
                    .section { margin-bottom: 25px; }
                    .section h2 { color: #2E3B5B; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
                    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                    th { background: #2E3B5B; color: white; }
                    .stats { display: flex; gap: 20px; margin-bottom: 20px; }
                    .stat-box { flex: 1; background: #f5f5f5; padding: 15px; border-radius: 5px; text-align: center; }
                    .stat-box .value { font-size: 1.5em; font-weight: bold; color: #2E3B5B; }
                    .stat-box .label { color: #666; }
                    .positive { color: #27ae60; }
                    .negative { color: #e74c3c; }
                    .footer { text-align: center; margin-top: 30px; font-size: 0.9em; color: #666; }
                    @media print { .no-print { display: none; } }
                </style>
            </head>
            <body>
                <div class="header">
                    <div class="logo">PACHECO EMPREENDIMENTOS</div>
                    <h1>${project.name}</h1>
                    <p>${project.address}</p>
                </div>

                <div class="section">
                    <h2>Informações Gerais</h2>
                    <p><strong>Responsável:</strong> ${project.manager || 'N/A'}</p>
                    <p><strong>Prazo:</strong> ${project.deadline ? new Date(project.deadline).toLocaleDateString('pt-BR') : 'N/A'}</p>
                    <p><strong>Status:</strong> ${project.status === 'completed' ? 'Concluída' : project.status === 'paused' ? 'Pausada' : 'Ativa'}</p>
                </div>

                <div class="stats">
                    <div class="stat-box"><div class="value">${formatCurrency(project.budget)}</div><div class="label">Orçamento</div></div>
                    <div class="stat-box"><div class="value">${formatCurrency(expenses)}</div><div class="label">Gastos</div></div>
                    <div class="stat-box"><div class="value ${balance >= 0 ? 'positive' : 'negative'}">${formatCurrency(balance)}</div><div class="label">Saldo</div></div>
                </div>

                <div class="section">
                    <h2>Materiais (${materials.length})</h2>
                    ${materials.length > 0 ? `
                        <table>
                            <tr><th>Material</th><th>Quantidade</th><th>Custo Unit.</th><th>Total</th></tr>
                            ${materials.map(m => `<tr><td>${m.name}</td><td>${m.quantity} ${m.unit}</td><td>${formatCurrency(m.cost)}</td><td>${formatCurrency(m.quantity * m.cost)}</td></tr>`).join('')}
                            <tr style="font-weight: bold;"><td colspan="3">TOTAL</td><td>${formatCurrency(totalMaterials)}</td></tr>
                        </table>
                    ` : '<p>Nenhum material cadastrado</p>'}
                </div>

                <div class="section">
                    <h2>Equipe (${team.length})</h2>
                    ${team.length > 0 ? `
                        <table>
                            <tr><th>Nome</th><th>Função</th><th>Tipo Pagamento</th><th>Valor</th></tr>
                            ${team.map(t => `<tr><td>${t.name}</td><td>${t.role || '-'}</td><td>${t.payment_type || '-'}</td><td>${formatCurrency(t.payment_value)}</td></tr>`).join('')}
                        </table>
                    ` : '<p>Nenhum membro cadastrado</p>'}
                </div>

                <div class="section">
                    <h2>Transações Financeiras (${transactions.length})</h2>
                    ${transactions.length > 0 ? `
                        <table>
                            <tr><th>Data</th><th>Tipo</th><th>Descrição</th><th>Valor</th></tr>
                            ${transactions.map(t => `<tr><td>${new Date(t.transaction_date).toLocaleDateString('pt-BR')}</td><td>${t.type === 'income' ? 'Receita' : 'Despesa'}</td><td>${t.description || '-'}</td><td>${formatCurrency(t.amount)}</td></tr>`).join('')}
                        </table>
                    ` : '<p>Nenhuma transação registrada</p>'}
                </div>

                <div class="footer">
                    Relatório gerado em ${new Date().toLocaleString('pt-BR')}<br>
                    Pacheco Empreendimentos - Sistema de Gerenciamento de Obras
                </div>

                <div class="no-print" style="text-align: center; margin-top: 20px;">
                    <button onclick="window.print()" style="padding: 10px 30px; font-size: 1em; cursor: pointer;">Imprimir / Salvar PDF</button>
                </div>
            </body>
            </html>
        `);
        printWindow.document.close();
        
        showNotification('PDF gerado com sucesso!', 'success');
    } catch (error) {
        console.error('Error generating PDF:', error);
        showNotification('Erro ao gerar PDF', 'error');
    }
};

window.uploadImageWithDescription = async function(input, tableName, recordId) {
    const file = input.files[0];
    if (!file) return;
    
    const description = document.getElementById(`photo-description-${recordId}`)?.value || '';
    
    const formData = new FormData();
    formData.append('file', file);
    formData.append('table_name', tableName);
    formData.append('record_id', recordId);
    formData.append('description', description);
    
    try {
        showNotification('Enviando imagem...', 'info');
        const response = await fetch('api/upload.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        if (result.success) {
            showNotification('Imagem enviada com sucesso!', 'success');
            document.getElementById(`photo-description-${recordId}`).value = '';
            app.viewProjectDetails(recordId);
        } else {
            throw new Error(result.error);
        }
    } catch (error) {
        console.error('Error uploading image:', error);
        showNotification('Erro ao enviar imagem', 'error');
    }
};

window.uploadDocumentWithDescription = async function(input, tableName, recordId) {
    const file = input.files[0];
    if (!file) return;
    
    const description = document.getElementById(`doc-description-${recordId}`)?.value || '';
    
    const formData = new FormData();
    formData.append('file', file);
    formData.append('table_name', tableName);
    formData.append('record_id', recordId);
    formData.append('description', description);
    formData.append('file_type', 'document');
    
    try {
        showNotification('Enviando documento...', 'info');
        const response = await fetch('api/upload.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        if (result.success) {
            showNotification('Documento enviado com sucesso!', 'success');
            document.getElementById(`doc-description-${recordId}`).value = '';
            loadProjectDocuments(recordId);
        } else {
            throw new Error(result.error);
        }
    } catch (error) {
        console.error('Error uploading document:', error);
        showNotification('Erro ao enviar documento', 'error');
    }
};

window.loadProjectDocuments = async function(projectId) {
    try {
        const response = await fetch(`api/documents.php?table_name=projects&record_id=${projectId}`);
        const result = await response.json();
        const container = document.getElementById(`docs-list-${projectId}`);
        
        if (!container) return;
        
        if (result.success && result.data.length > 0) {
            container.innerHTML = result.data.map(doc => `
                <div class="doc-item">
                    <i class="fas fa-file-alt"></i>
                    <div class="doc-info">
                        <a href="${doc.file_path}" target="_blank">${doc.file_name}</a>
                        <small>${doc.description || 'Sem descrição'}</small>
                    </div>
                    <button class="btn btn-sm btn-danger" onclick="deleteDocument(${doc.id})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `).join('');
        } else {
            container.innerHTML = '<p class="text-center text-muted">Nenhum documento anexado</p>';
        }
    } catch (error) {
        console.error('Error loading documents:', error);
    }
};

window.deleteDocument = async function(docId) {
    if (!confirm('Tem certeza que deseja excluir este documento?')) return;
    
    try {
        const response = await fetch(`api/documents.php?id=${docId}`, { method: 'DELETE' });
        const result = await response.json();
        
        if (result.success) {
            showNotification('Documento excluído!', 'success');
            const openModal = document.querySelector('.modal.active');
            if (openModal) {
                const projectId = openModal.querySelector('[id^="docs-list-"]')?.id?.split('-')[2];
                if (projectId) loadProjectDocuments(projectId);
            }
        }
    } catch (error) {
        showNotification('Erro ao excluir documento', 'error');
    }
};