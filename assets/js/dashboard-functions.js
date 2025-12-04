let privacyMode = false;
let notifications = [];
let chatInterval = null;
let lastMessageId = 0;

document.addEventListener('DOMContentLoaded', function() {
    setTimeout(() => {
        loadNotifications();
        populateChatProjectSelect();
    }, 500);
    setInterval(loadNotifications, 60000);
});

window.togglePrivacyMode = togglePrivacyMode;
window.toggleNotifications = toggleNotifications;
window.loadNotifications = loadNotifications;
window.filterNotifications = filterNotifications;
window.markNotificationRead = markNotificationRead;
window.markAllNotificationsRead = markAllNotificationsRead;
window.showProjectDetails = showProjectDetails;
window.downloadProjectPDF = downloadProjectPDF;
window.printProjectDetails = printProjectDetails;
window.showProjectChat = showProjectChat;
window.toggleChat = toggleChat;
window.loadChatMessages = loadChatMessages;
window.sendChatMessage = sendChatMessage;
window.handleChatKeypress = handleChatKeypress;
window.showAddProjectPhotoModal = showAddProjectPhotoModal;
window.showAddMaterialImagesModal = showAddMaterialImagesModal;
window.showAddTimeEntryModal = showAddTimeEntryModal;
window.generateTeamReport = generateTeamReport;
window.generateInventoryReport = generateInventoryReport;
window.filterMovements = filterMovements;
window.filterInventory = filterInventory;

function togglePrivacyMode() {
    privacyMode = !privacyMode;
    const icon = document.getElementById('privacy-icon');
    const privacyValues = document.querySelectorAll('.privacy-value');
    
    if (privacyMode) {
        icon.className = 'fas fa-eye-slash';
        privacyValues.forEach(el => el.classList.add('hidden-value'));
    } else {
        icon.className = 'fas fa-eye';
        privacyValues.forEach(el => el.classList.remove('hidden-value'));
    }
}

function toggleNotifications() {
    const panel = document.getElementById('notifications-panel');
    panel.classList.toggle('active');
}

async function loadNotifications() {
    try {
        const filter = document.getElementById('notification-filter')?.value || '';
        const url = filter ? `/api/notifications.php?type=${filter}` : '/api/notifications.php';
        const response = await fetch(url);
        const data = await response.json();
        
        if (Array.isArray(data)) {
            notifications = data;
        } else if (data.error) {
            notifications = [];
            console.warn('Notifications API error:', data.error);
        } else {
            notifications = [];
        }
        
        renderNotifications();
        updateNotificationBadge();
    } catch (error) {
        notifications = [];
        console.error('Error loading notifications:', error);
    }
}

function renderNotifications() {
    const container = document.getElementById('notifications-list');
    if (!container) return;
    
    if (notifications.length === 0) {
        container.innerHTML = '<div class="notification-empty">Nenhuma notificação</div>';
        return;
    }
    
    container.innerHTML = notifications.map(n => `
        <div class="notification-item ${n.is_read ? 'read' : 'unread'}" onclick="markNotificationRead(${n.id})">
            <div class="notification-icon">
                <i class="fas ${getNotificationIcon(n.type)}"></i>
            </div>
            <div class="notification-content">
                <strong>${escapeHtml(n.title)}</strong>
                <p>${escapeHtml(n.message)}</p>
                <span class="notification-time">${formatDate(n.created_at)}</span>
                ${n.project_name ? `<span class="notification-project">${escapeHtml(n.project_name)}</span>` : ''}
            </div>
        </div>
    `).join('');
}

function getNotificationIcon(type) {
    const icons = {
        'inventory': 'fa-boxes',
        'inventory_restock': 'fa-plus-circle',
        'photo': 'fa-camera',
        'chat': 'fa-comment',
        'material': 'fa-hammer',
        'team': 'fa-users'
    };
    return icons[type] || 'fa-bell';
}

function updateNotificationBadge() {
    const badge = document.getElementById('notification-count');
    const unreadCount = notifications.filter(n => !n.is_read).length;
    badge.textContent = unreadCount;
    badge.style.display = unreadCount > 0 ? 'flex' : 'none';
}

async function markNotificationRead(id) {
    try {
        await fetch(`/api/notifications.php?id=${id}`, { method: 'PUT' });
        loadNotifications();
    } catch (error) {
        console.error('Error marking notification read:', error);
    }
}

async function markAllNotificationsRead() {
    try {
        await fetch('/api/notifications.php?id=0', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ mark_all_read: true })
        });
        loadNotifications();
    } catch (error) {
        console.error('Error marking all notifications read:', error);
    }
}

function filterNotifications() {
    loadNotifications();
}

async function showProjectDetails(projectId) {
    try {
        const response = await fetch(`/api/pdf-generator.php?type=project_detail&project_id=${projectId}`);
        const data = await response.json();
        
        if (data.success) {
            const modal = document.createElement('div');
            modal.className = 'modal-overlay active';
            modal.innerHTML = `
                <div class="modal project-detail-modal">
                    <div class="modal-header">
                        <h3><i class="fas fa-building"></i> Detalhes da Obra: ${escapeHtml(data.project.name)}</h3>
                        <button class="btn-close" onclick="closeModal(this)">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="detail-actions">
                            <button class="btn btn-primary" onclick="downloadProjectPDF(${projectId})">
                                <i class="fas fa-download"></i> Baixar PDF
                            </button>
                            <button class="btn btn-secondary" onclick="printProjectDetails(${projectId})">
                                <i class="fas fa-print"></i> Imprimir
                            </button>
                            <button class="btn btn-secondary" onclick="showProjectChat(${projectId})">
                                <i class="fas fa-comments"></i> Chat
                            </button>
                            <button class="btn btn-secondary" onclick="showAddProjectPhotoModal(${projectId})">
                                <i class="fas fa-camera"></i> Adicionar Fotos
                            </button>
                        </div>
                        <div class="detail-content">
                            ${data.html}
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }
    } catch (error) {
        console.error('Error loading project details:', error);
        alert('Erro ao carregar detalhes do projeto');
    }
}

async function downloadProjectPDF(projectId) {
    try {
        const response = await fetch(`/api/pdf-generator.php?type=project_detail&project_id=${projectId}`);
        const data = await response.json();
        
        if (data.success) {
            const printWindow = window.open('', '_blank');
            printWindow.document.write(data.html);
            printWindow.document.close();
        }
    } catch (error) {
        console.error('Error generating PDF:', error);
    }
}

function printProjectDetails(projectId) {
    downloadProjectPDF(projectId);
}

function showProjectChat(projectId) {
    const chatSelect = document.getElementById('chat-project-select');
    if (chatSelect) {
        chatSelect.value = projectId;
        loadChatMessages();
    }
    document.getElementById('chat-body').style.display = 'block';
}

function toggleChat() {
    const chatBody = document.getElementById('chat-body');
    chatBody.style.display = chatBody.style.display === 'none' ? 'block' : 'none';
}

function populateChatProjectSelect() {
    fetch('/api/projects.php')
        .then(res => res.json())
        .then(projects => {
            const select = document.getElementById('chat-project-select');
            if (select && Array.isArray(projects)) {
                select.innerHTML = '<option value="">Selecione uma obra</option>' +
                    projects.map(p => `<option value="${p.id}">${escapeHtml(p.name)}</option>`).join('');
            }
        })
        .catch(err => console.error('Error loading projects for chat:', err));
}

async function loadChatMessages() {
    const projectId = document.getElementById('chat-project-select')?.value;
    if (!projectId) return;
    
    try {
        const url = lastMessageId > 0 
            ? `/api/chat.php?project_id=${projectId}&after=${lastMessageId}`
            : `/api/chat.php?project_id=${projectId}`;
        const response = await fetch(url);
        const messages = await response.json();
        
        if (Array.isArray(messages)) {
            if (lastMessageId === 0) {
                document.getElementById('chat-messages').innerHTML = '';
            }
            messages.forEach(msg => {
                appendChatMessage(msg);
                if (msg.id > lastMessageId) lastMessageId = msg.id;
            });
        }
    } catch (error) {
        console.error('Error loading chat messages:', error);
    }
}

function appendChatMessage(msg) {
    const container = document.getElementById('chat-messages');
    const div = document.createElement('div');
    div.className = 'chat-message';
    div.innerHTML = `
        <div class="chat-message-header">
            <strong>${escapeHtml(msg.sender_name)}</strong>
            <span class="chat-message-time">${formatDate(msg.created_at)}</span>
        </div>
        <div class="chat-message-body">${escapeHtml(msg.message)}</div>
    `;
    container.appendChild(div);
    container.scrollTop = container.scrollHeight;
}

async function sendChatMessage() {
    const projectId = document.getElementById('chat-project-select')?.value;
    const senderName = document.getElementById('chat-sender')?.value?.trim();
    const message = document.getElementById('chat-message')?.value?.trim();
    
    if (!projectId || !senderName || !message) {
        alert('Preencha todos os campos');
        return;
    }
    
    try {
        const response = await fetch('/api/chat.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ project_id: projectId, sender_name: senderName, message: message })
        });
        
        if (response.ok) {
            document.getElementById('chat-message').value = '';
            loadChatMessages();
        }
    } catch (error) {
        console.error('Error sending message:', error);
    }
}

function handleChatKeypress(event) {
    if (event.key === 'Enter') {
        sendChatMessage();
    }
}

function showAddProjectPhotoModal(projectId) {
    const modal = document.createElement('div');
    modal.className = 'modal-overlay active';
    modal.innerHTML = `
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fas fa-camera"></i> Adicionar Fotos do Andamento</h3>
                <button class="btn-close" onclick="closeModal(this)">&times;</button>
            </div>
            <div class="modal-body">
                <form id="project-photo-form" enctype="multipart/form-data">
                    <input type="hidden" name="project_id" value="${projectId}">
                    <div class="form-group">
                        <label>Fotos (pode selecionar várias)</label>
                        <input type="file" name="images" multiple accept="image/*" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Descrição</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Descrição das fotos..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>Data</label>
                        <input type="date" name="photo_date" class="form-control" value="${new Date().toISOString().split('T')[0]}">
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeModal(this)">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Salvar Fotos</button>
                    </div>
                </form>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    
    document.getElementById('project-photo-form').onsubmit = async function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        try {
            const response = await fetch('/api/project-photos.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            
            if (result.success) {
                alert('Fotos adicionadas com sucesso!');
                closeModal(modal.querySelector('.btn-close'));
            } else {
                alert(result.error || 'Erro ao adicionar fotos');
            }
        } catch (error) {
            console.error('Error uploading photos:', error);
            alert('Erro ao enviar fotos');
        }
    };
}

function showAddMaterialImagesModal() {
    const projectId = document.getElementById('project-select-materials')?.value;
    if (!projectId) return;
    
    fetch(`/api/materials.php?project_id=${projectId}`)
        .then(res => res.json())
        .then(materials => {
            const materialOptions = materials.map(m => 
                `<option value="${m.id}">${escapeHtml(m.name)}</option>`
            ).join('');
            
            const modal = document.createElement('div');
            modal.className = 'modal-overlay active';
            modal.innerHTML = `
                <div class="modal">
                    <div class="modal-header">
                        <h3><i class="fas fa-images"></i> Adicionar Fotos de Material</h3>
                        <button class="btn-close" onclick="closeModal(this)">&times;</button>
                    </div>
                    <div class="modal-body">
                        <form id="material-images-form" enctype="multipart/form-data">
                            <input type="hidden" name="project_id" value="${projectId}">
                            <div class="form-group">
                                <label>Material</label>
                                <select name="material_id" class="form-control" required>
                                    <option value="">Selecione um material</option>
                                    ${materialOptions}
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Fotos (pode selecionar várias)</label>
                                <input type="file" name="images" multiple accept="image/*" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label>Descrição</label>
                                <textarea name="description" class="form-control" rows="3" placeholder="Descrição das fotos..."></textarea>
                            </div>
                            <div class="form-actions">
                                <button type="button" class="btn btn-secondary" onclick="closeModal(this)">Cancelar</button>
                                <button type="submit" class="btn btn-primary">Salvar Fotos</button>
                            </div>
                        </form>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            
            document.getElementById('material-images-form').onsubmit = async function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                
                try {
                    const response = await fetch('/api/material-images.php', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();
                    
                    if (result.success) {
                        alert('Fotos adicionadas com sucesso!');
                        closeModal(modal.querySelector('.btn-close'));
                    } else {
                        alert(result.error || 'Erro ao adicionar fotos');
                    }
                } catch (error) {
                    console.error('Error uploading photos:', error);
                    alert('Erro ao enviar fotos');
                }
            };
        })
        .catch(err => console.error('Error loading materials:', err));
}

function showAddTimeEntryModal() {
    const projectId = document.getElementById('project-select-team')?.value;
    if (!projectId) return;
    
    fetch(`/api/team.php?project_id=${projectId}`)
        .then(res => res.json())
        .then(data => {
            const members = data.data || data;
            const memberOptions = members.map(m => 
                `<option value="${m.id}">${escapeHtml(m.name)} - ${escapeHtml(m.role)}</option>`
            ).join('');
            
            const modal = document.createElement('div');
            modal.className = 'modal-overlay active';
            modal.innerHTML = `
                <div class="modal">
                    <div class="modal-header">
                        <h3><i class="fas fa-clock"></i> Registrar Horas/Dias Trabalhados</h3>
                        <button class="btn-close" onclick="closeModal(this)">&times;</button>
                    </div>
                    <div class="modal-body">
                        <form id="time-entry-form">
                            <input type="hidden" name="project_id" value="${projectId}">
                            <div class="form-group">
                                <label>Membro da Equipe</label>
                                <select name="team_member_id" class="form-control" required>
                                    <option value="">Selecione um membro</option>
                                    ${memberOptions}
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Data</label>
                                <input type="date" name="work_date" class="form-control" value="${new Date().toISOString().split('T')[0]}" required>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Horas Trabalhadas</label>
                                    <input type="number" name="hours_worked" class="form-control" step="0.5" min="0" value="0">
                                </div>
                                <div class="form-group">
                                    <label>Dias Trabalhados</label>
                                    <input type="number" name="days_worked" class="form-control" step="0.5" min="0" value="0">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Descrição</label>
                                <textarea name="description" class="form-control" rows="2" placeholder="Descrição do trabalho realizado..."></textarea>
                            </div>
                            <div class="form-actions">
                                <button type="button" class="btn btn-secondary" onclick="closeModal(this)">Cancelar</button>
                                <button type="submit" class="btn btn-primary">Registrar</button>
                            </div>
                        </form>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            
            document.getElementById('time-entry-form').onsubmit = async function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                const data = Object.fromEntries(formData);
                
                try {
                    const response = await fetch('/api/time-entries.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(data)
                    });
                    const result = await response.json();
                    
                    if (result.success) {
                        alert('Horas registradas com sucesso!');
                        closeModal(modal.querySelector('.btn-close'));
                        loadTeamMembers(projectId);
                    } else {
                        alert(result.error || 'Erro ao registrar horas');
                    }
                } catch (error) {
                    console.error('Error saving time entry:', error);
                    alert('Erro ao registrar horas');
                }
            };
        })
        .catch(err => console.error('Error loading team members:', err));
}

async function generateTeamReport() {
    const projectId = document.getElementById('project-select-team')?.value;
    try {
        const url = projectId 
            ? `/api/pdf-generator.php?type=team_hours&project_id=${projectId}`
            : '/api/pdf-generator.php?type=team_hours';
        const response = await fetch(url);
        const data = await response.json();
        
        if (data.success) {
            const printWindow = window.open('', '_blank');
            printWindow.document.write(data.html);
            printWindow.document.close();
        }
    } catch (error) {
        console.error('Error generating team report:', error);
    }
}

async function generateInventoryReport() {
    try {
        const response = await fetch('/api/pdf-generator.php?type=inventory_materials');
        const data = await response.json();
        
        if (data.success) {
            const printWindow = window.open('', '_blank');
            printWindow.document.write(data.html);
            printWindow.document.close();
        }
    } catch (error) {
        console.error('Error generating inventory report:', error);
    }
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleDateString('pt-BR') + ' ' + date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
}

function closeModal(btn) {
    const modal = btn.closest('.modal-overlay');
    if (modal) modal.remove();
}

function filterMovements() {
    const typeFilter = document.getElementById('movement-type-filter')?.value || '';
    const projectFilter = document.getElementById('movement-project-filter')?.value || '';
    
    const rows = document.querySelectorAll('#movements-list table tbody tr');
    rows.forEach(row => {
        const type = row.dataset.type || '';
        const project = row.dataset.project || '';
        
        const typeMatch = !typeFilter || type === typeFilter;
        const projectMatch = !projectFilter || project === projectFilter;
        
        row.style.display = (typeMatch && projectMatch) ? '' : 'none';
    });
}

function filterInventory() {
    const filter = document.getElementById('inventory-filter')?.value || '';
    
    const rows = document.querySelectorAll('#inventory-list table tbody tr');
    rows.forEach(row => {
        const qty = parseFloat(row.dataset.quantity || 0);
        const minQty = parseFloat(row.dataset.minQuantity || 0);
        
        let show = true;
        if (filter === 'low') {
            show = qty > 0 && qty <= minQty;
        } else if (filter === 'out') {
            show = qty <= 0;
        }
        
        row.style.display = show ? '' : 'none';
    });
}

function loadTeamMembers(projectId) {
    if (typeof app !== 'undefined' && app.loadTeamMembers) {
        app.loadTeamMembers(projectId);
    }
}
