let allTeamMembers = [];

async function loadTeamMembers(projectId = null) {
    try {
        let url = 'api/team.php';
        if (projectId) {
            url += `?project_id=${projectId}`;
        }
        const response = await fetch(url);
        const data = await response.json();
        
        if (data.success) {
            allTeamMembers = data.data;
            renderTeamCards(data.data);
        }
    } catch (error) {
        console.error('Error loading team:', error);
    }
}

function renderTeamCards(members) {
    const container = document.getElementById('team-list');
    
    if (members.length === 0) {
        container.innerHTML = '<div class="empty-state"><i class="fas fa-users"></i><p>Nenhum membro cadastrado</p></div>';
        return;
    }
    
    container.innerHTML = members.map(member => `
        <div class="team-card">
            <div class="team-card-header">
                ${member.image_path 
                    ? `<img src="${member.image_path}" alt="${member.name}" class="team-photo">`
                    : `<div class="team-photo-placeholder"><i class="fas fa-user"></i></div>`
                }
                <div class="team-info">
                    <h4>${member.name}</h4>
                    <span class="team-role">${member.role || 'Sem função definida'}</span>
                </div>
            </div>
            <div class="team-card-body">
                ${member.cpf_cnpj ? `<p><i class="fas fa-id-card"></i> ${member.cpf_cnpj}</p>` : ''}
                ${member.phone ? `<p><i class="fas fa-phone"></i> ${member.phone}</p>` : ''}
                ${member.address ? `<p><i class="fas fa-map-marker-alt"></i> ${member.address}</p>` : ''}
                <p><i class="fas fa-money-bill"></i> 
                    ${getPaymentTypeLabel(member.payment_type)}: 
                    <span class="value-text">${formatCurrency(member.payment_value || 0)}</span>
                </p>
                ${member.project_names ? `<p><i class="fas fa-building"></i> ${member.project_names}</p>` : ''}
            </div>
            <div class="team-card-actions">
                <button class="btn btn-sm btn-info" onclick="viewTeamMember(${member.id})">
                    <i class="fas fa-eye"></i>
                </button>
                <button class="btn btn-sm btn-secondary" onclick="editTeamMember(${member.id})">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn btn-sm btn-warning" onclick="showMemberImages(${member.id})">
                    <i class="fas fa-images"></i>
                </button>
                <button class="btn btn-sm btn-danger" onclick="deleteTeamMember(${member.id})">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    `).join('');
}

function getPaymentTypeLabel(type) {
    const labels = {
        'salario': 'Salário',
        'diaria': 'Diária',
        'empreita': 'Empreita',
        'hora': 'Por Hora'
    };
    return labels[type] || type || 'Não definido';
}

function showAddTeamMemberModal() {
    const modal = document.createElement('div');
    modal.className = 'modal active';
    modal.id = 'team-modal';
    modal.innerHTML = `
        <div class="modal-content modal-lg">
            <div class="modal-header">
                <h3><i class="fas fa-user-plus"></i> Novo Membro da Equipe</h3>
                <button class="close-btn" onclick="closeModal('team-modal')">&times;</button>
            </div>
            <form id="team-form" onsubmit="saveTeamMember(event)">
                <div class="form-row">
                    <div class="form-group">
                        <label>Nome *</label>
                        <input type="text" name="name" required>
                    </div>
                    <div class="form-group">
                        <label>CPF/CNPJ</label>
                        <input type="text" name="cpf_cnpj">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Função</label>
                        <input type="text" name="role" placeholder="Ex: Pedreiro, Engenheiro">
                    </div>
                    <div class="form-group">
                        <label>Telefone</label>
                        <input type="text" name="phone">
                    </div>
                </div>
                <div class="form-group">
                    <label>Endereço</label>
                    <input type="text" name="address">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Tipo de Pagamento</label>
                        <select name="payment_type">
                            <option value="diaria">Diária</option>
                            <option value="salario">Salário Mensal</option>
                            <option value="empreita">Empreita</option>
                            <option value="hora">Por Hora</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Valor (R$)</label>
                        <input type="number" name="payment_value" step="0.01" value="0">
                    </div>
                </div>
                <div class="form-group">
                    <label>Associar à Obra</label>
                    <select name="project_id" id="team-project-select">
                        <option value="">Nenhuma obra</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Descrição</label>
                    <textarea name="description" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label>Foto</label>
                    <div class="upload-area" onclick="document.getElementById('team-photo-input').click()">
                        <i class="fas fa-camera"></i>
                        <p>Clique para adicionar foto</p>
                        <input type="file" id="team-photo-input" accept="image/*" onchange="previewTeamPhoto(this)" hidden>
                    </div>
                    <div id="team-photo-preview"></div>
                    <input type="hidden" name="image_path" id="team-image-path">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('team-modal')">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </form>
        </div>
    `;
    document.getElementById('modal-container').appendChild(modal);
    loadProjectsForSelect('team-project-select');
}

async function previewTeamPhoto(input) {
    if (input.files && input.files[0]) {
        const formData = new FormData();
        formData.append('image', input.files[0]);
        
        try {
            const response = await fetch('api/upload.php', { method: 'POST', body: formData });
            const data = await response.json();
            
            if (data.success) {
                document.getElementById('team-image-path').value = data.data.path;
                document.getElementById('team-photo-preview').innerHTML = 
                    `<img src="${data.data.path}" class="preview-image">`;
            }
        } catch (error) {
            console.error('Error uploading photo:', error);
        }
    }
}

async function saveTeamMember(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData);
    
    data.payment_value = parseFloat(data.payment_value) || 0;
    data.project_id = data.project_id || null;
    
    try {
        const response = await fetch('api/team.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await response.json();
        
        if (result.success) {
            closeModal('team-modal');
            loadTeamMembers();
            showNotification('Membro adicionado com sucesso!', 'success');
        } else {
            showNotification(result.error || 'Erro ao salvar', 'error');
        }
    } catch (error) {
        console.error('Error saving team member:', error);
        showNotification('Erro ao salvar membro', 'error');
    }
}

async function editTeamMember(id) {
    try {
        const response = await fetch(`api/team.php?id=${id}`);
        const data = await response.json();
        
        if (data.success) {
            const member = data.data;
            showEditTeamMemberModal(member);
        }
    } catch (error) {
        console.error('Error loading team member:', error);
    }
}

function showEditTeamMemberModal(member) {
    const modal = document.createElement('div');
    modal.className = 'modal active';
    modal.id = 'team-modal';
    modal.innerHTML = `
        <div class="modal-content modal-lg">
            <div class="modal-header">
                <h3><i class="fas fa-user-edit"></i> Editar Membro</h3>
                <button class="close-btn" onclick="closeModal('team-modal')">&times;</button>
            </div>
            <form id="team-form" onsubmit="updateTeamMember(event, ${member.id})">
                <div class="form-row">
                    <div class="form-group">
                        <label>Nome *</label>
                        <input type="text" name="name" value="${member.name || ''}" required>
                    </div>
                    <div class="form-group">
                        <label>CPF/CNPJ</label>
                        <input type="text" name="cpf_cnpj" value="${member.cpf_cnpj || ''}">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Função</label>
                        <input type="text" name="role" value="${member.role || ''}">
                    </div>
                    <div class="form-group">
                        <label>Telefone</label>
                        <input type="text" name="phone" value="${member.phone || ''}">
                    </div>
                </div>
                <div class="form-group">
                    <label>Endereço</label>
                    <input type="text" name="address" value="${member.address || ''}">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Tipo de Pagamento</label>
                        <select name="payment_type">
                            <option value="diaria" ${member.payment_type === 'diaria' ? 'selected' : ''}>Diária</option>
                            <option value="salario" ${member.payment_type === 'salario' ? 'selected' : ''}>Salário Mensal</option>
                            <option value="empreita" ${member.payment_type === 'empreita' ? 'selected' : ''}>Empreita</option>
                            <option value="hora" ${member.payment_type === 'hora' ? 'selected' : ''}>Por Hora</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Valor (R$)</label>
                        <input type="number" name="payment_value" step="0.01" value="${member.payment_value || 0}">
                    </div>
                </div>
                <div class="form-group">
                    <label>Associar à Obra</label>
                    <select name="project_id" id="team-project-select">
                        <option value="">Nenhuma obra</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Descrição</label>
                    <textarea name="description" rows="3">${member.description || ''}</textarea>
                </div>
                <div class="form-group">
                    <label>Foto</label>
                    <div class="upload-area" onclick="document.getElementById('team-photo-input').click()">
                        <i class="fas fa-camera"></i>
                        <p>Clique para alterar foto</p>
                        <input type="file" id="team-photo-input" accept="image/*" onchange="previewTeamPhoto(this)" hidden>
                    </div>
                    <div id="team-photo-preview">
                        ${member.image_path ? `<img src="${member.image_path}" class="preview-image">` : ''}
                    </div>
                    <input type="hidden" name="image_path" id="team-image-path" value="${member.image_path || ''}">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('team-modal')">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Atualizar</button>
                </div>
            </form>
        </div>
    `;
    document.getElementById('modal-container').appendChild(modal);
    loadProjectsForSelect('team-project-select', member.project_id);
}

async function updateTeamMember(event, id) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData);
    
    data.payment_value = parseFloat(data.payment_value) || 0;
    data.project_id = data.project_id || null;
    
    try {
        const response = await fetch(`api/team.php?id=${id}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await response.json();
        
        if (result.success) {
            closeModal('team-modal');
            loadTeamMembers();
            showNotification('Membro atualizado com sucesso!', 'success');
        } else {
            showNotification(result.error || 'Erro ao atualizar', 'error');
        }
    } catch (error) {
        console.error('Error updating team member:', error);
        showNotification('Erro ao atualizar membro', 'error');
    }
}

async function deleteTeamMember(id) {
    if (!confirm('Tem certeza que deseja remover este membro?')) return;
    
    try {
        const response = await fetch(`api/team.php?id=${id}`, { method: 'DELETE' });
        const result = await response.json();
        
        if (result.success) {
            loadTeamMembers();
            showNotification('Membro removido com sucesso!', 'success');
        } else {
            showNotification(result.error || 'Erro ao remover', 'error');
        }
    } catch (error) {
        console.error('Error deleting team member:', error);
        showNotification('Erro ao remover membro', 'error');
    }
}

async function viewTeamMember(id) {
    try {
        const response = await fetch(`api/team.php?id=${id}`);
        const data = await response.json();
        
        if (data.success) {
            const member = data.data;
            showTeamMemberDetailsModal(member);
        }
    } catch (error) {
        console.error('Error loading team member:', error);
    }
}

function showTeamMemberDetailsModal(member) {
    const modal = document.createElement('div');
    modal.className = 'modal active';
    modal.id = 'team-details-modal';
    modal.innerHTML = `
        <div class="modal-content modal-lg">
            <div class="modal-header">
                <h3><i class="fas fa-user"></i> Detalhes do Membro</h3>
                <button class="close-btn" onclick="closeModal('team-details-modal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="member-details">
                    <div class="member-photo-section">
                        ${member.image_path 
                            ? `<img src="${member.image_path}" alt="${member.name}" class="large-photo">`
                            : `<div class="large-photo-placeholder"><i class="fas fa-user"></i></div>`
                        }
                    </div>
                    <div class="member-info-section">
                        <h2>${member.name}</h2>
                        <p class="member-role">${member.role || 'Função não definida'}</p>
                        
                        <div class="info-grid">
                            ${member.cpf_cnpj ? `<div class="info-item"><strong>CPF/CNPJ:</strong> ${member.cpf_cnpj}</div>` : ''}
                            ${member.phone ? `<div class="info-item"><strong>Telefone:</strong> ${member.phone}</div>` : ''}
                            ${member.address ? `<div class="info-item"><strong>Endereço:</strong> ${member.address}</div>` : ''}
                            <div class="info-item"><strong>Pagamento:</strong> ${getPaymentTypeLabel(member.payment_type)}</div>
                            <div class="info-item"><strong>Valor:</strong> <span class="value-text">${formatCurrency(member.payment_value || 0)}</span></div>
                        </div>
                        
                        ${member.description ? `<div class="description-section"><strong>Descrição:</strong><p>${member.description}</p></div>` : ''}
                    </div>
                </div>
                
                ${member.history && member.history.length > 0 ? `
                    <div class="history-section">
                        <h4><i class="fas fa-history"></i> Histórico de Alterações</h4>
                        <div class="history-list">
                            ${member.history.map(h => `
                                <div class="history-item">
                                    <span class="history-action ${h.action}">${h.action.toUpperCase()}</span>
                                    <span class="history-date">${new Date(h.created_at).toLocaleString('pt-BR')}</span>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                ` : ''}
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('team-details-modal')">Fechar</button>
                <button class="btn btn-primary" onclick="closeModal('team-details-modal'); editTeamMember(${member.id})">
                    <i class="fas fa-edit"></i> Editar
                </button>
            </div>
        </div>
    `;
    document.getElementById('modal-container').appendChild(modal);
}

function showMemberImages(memberId) {
    showImagesModal('team_members', memberId, 'Fotos do Membro');
}

async function showTeamHistory() {
    try {
        const response = await fetch('api/history.php?table_name=team_members');
        const data = await response.json();
        
        if (data.success) {
            showHistoryModal(data.data, 'Histórico da Equipe');
        }
    } catch (error) {
        console.error('Error loading history:', error);
    }
}
