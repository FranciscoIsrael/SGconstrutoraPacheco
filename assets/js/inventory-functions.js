// Inventory and History Management Functions

// Load inventory items
app.loadInventory = async function() {
    try {
        const response = await fetch('api/inventory.php');
        const result = await response.json();
        
        if (result.success) {
            this.displayInventory(result.data);
        }
    } catch (error) {
        console.error('Error loading inventory:', error);
    }
};

// Display inventory items
app.displayInventory = function(items) {
    const container = document.getElementById('inventory-list');
    
    if (items.length === 0) {
        container.innerHTML = '<p class="text-center text-muted">Nenhum item no inventário.</p>';
        return;
    }
    
    container.innerHTML = `
        <table class="table">
            <thead>
                <tr>
                    <th>Foto</th>
                    <th>Item</th>
                    <th>Descrição</th>
                    <th>Quantidade</th>
                    <th>Custo Unit.</th>
                    <th>Valor Total</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                ${items.map(item => `
                    <tr class="${item.low_stock ? 'bg-warning-light' : ''}">
                        <td>${item.image_path ? `<img src="${item.image_path}" style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px;">` : '<i class="fas fa-box text-muted"></i>'}</td>
                        <td><strong>${item.name}</strong></td>
                        <td>${item.description || '-'}</td>
                        <td>${item.quantity} ${item.unit}</td>
                        <td>${this.formatCurrency(item.unit_cost)}</td>
                        <td><strong>${this.formatCurrency(item.total_value)}</strong></td>
                        <td>${item.low_stock ? '<span class="badge badge-warning">Estoque Baixo</span>' : '<span class="badge badge-success">OK</span>'}</td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick="app.showInventoryMovementModal(${item.id})" title="Movimentar"><i class="fas fa-exchange-alt"></i></button>
                            <button class="btn btn-sm btn-info" onclick="app.showInventoryHistory(${item.id})" title="Histórico"><i class="fas fa-history"></i></button>
                            <button class="btn btn-sm btn-warning" onclick="app.editInventoryItem(${item.id})"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-sm btn-danger" onclick="app.deleteInventoryItem(${item.id})"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
};

// Update inventory statistics
app.updateInventoryStats = async function() {
    try {
        const response = await fetch('api/inventory.php?summary=1');
        const result = await response.json();
        
        if (result.success) {
            document.getElementById('total-inventory-items').textContent = result.data.total_items;
            document.getElementById('total-inventory-value').textContent = this.formatCurrency(result.data.total_value);
        }
    } catch (error) {
        console.error('Error updating inventory stats:', error);
    }
};

// Show add inventory modal
window.showAddInventoryModal = function() {
    const content = `
        <form id="inventory-form">
            <div class="form-group">
                <label class="form-label">Nome do Item</label>
                <input type="text" id="inventory-name" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label">Descrição</label>
                <textarea id="inventory-description" class="form-textarea"></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Quantidade em Estoque</label>
                <input type="number" id="inventory-quantity" class="form-input" step="0.01" required>
            </div>
            <div class="form-group">
                <label class="form-label">Unidade</label>
                <input type="text" id="inventory-unit" class="form-input" value="unidade" required>
            </div>
            <div class="form-group">
                <label class="form-label">Custo Unitário (R$)</label>
                <input type="number" id="inventory-cost" class="form-input" step="0.01" required>
            </div>
            <div class="form-group">
                <label class="form-label">Quantidade Mínima (Alerta de Estoque Baixo)</label>
                <input type="number" id="inventory-min" class="form-input" step="0.01" value="0">
            </div>
            <div class="form-group">
                <label class="form-label">Foto do Item (opcional)</label>
                <input type="file" id="inventory-image" class="form-input" accept="image/*" onchange="app.handleImageUpload(this, 'inventory-image-preview')">
                <div id="inventory-image-preview" style="margin-top: 10px;"></div>
            </div>
        </form>
    `;

    const footer = `
        <button type="button" class="btn btn-secondary" onclick="app.closeModal()">Cancelar</button>
        <button type="button" class="btn btn-primary" onclick="app.saveInventoryItem()">Salvar</button>
    `;

    app.showModal('Novo Item no Inventário', content, footer);
};

// Save inventory item
app.saveInventoryItem = async function(itemId = null) {
    const name = document.getElementById('inventory-name').value;
    const description = document.getElementById('inventory-description').value;
    const quantity = document.getElementById('inventory-quantity').value;
    const unit = document.getElementById('inventory-unit').value;
    const unit_cost = document.getElementById('inventory-cost').value;
    const min_quantity = document.getElementById('inventory-min').value;
    const imageInput = document.getElementById('inventory-image');
    const image_path = imageInput?.dataset?.uploadedPath || null;

    if (!name || !quantity || !unit_cost) {
        this.showAlert('Nome, quantidade e custo são obrigatórios', 'error');
        return;
    }

    const data = { name, description, quantity: parseFloat(quantity), unit, unit_cost: parseFloat(unit_cost), min_quantity: parseFloat(min_quantity), image_path };

    try {
        const url = itemId ? `api/inventory.php?id=${itemId}` : 'api/inventory.php';
        const method = itemId ? 'PUT' : 'POST';
        
        const response = await fetch(url, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });

        const result = await response.json();
        
        if (result.success) {
            this.showAlert(itemId ? 'Item atualizado com sucesso!' : 'Item criado com sucesso!', 'success');
            this.closeModal();
            this.loadInventory();
            this.updateInventoryStats();
        } else {
            throw new Error(result.error);
        }
    } catch (error) {
        console.error('Error saving inventory item:', error);
        this.showAlert('Erro ao salvar item', 'error');
    }
};

// Edit inventory item
app.editInventoryItem = async function(itemId) {
    try {
        const response = await fetch(`api/inventory.php?id=${itemId}`);
        const result = await response.json();
        
        if (result.success) {
            const item = result.data;
            
            const content = `
                <form id="inventory-form">
                    <div class="form-group">
                        <label class="form-label">Nome do Item</label>
                        <input type="text" id="inventory-name" class="form-input" value="${item.name}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Descrição</label>
                        <textarea id="inventory-description" class="form-textarea">${item.description || ''}</textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Quantidade em Estoque</label>
                        <input type="number" id="inventory-quantity" class="form-input" value="${item.quantity}" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Unidade</label>
                        <input type="text" id="inventory-unit" class="form-input" value="${item.unit}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Custo Unitário (R$)</label>
                        <input type="number" id="inventory-cost" class="form-input" value="${item.unit_cost}" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Quantidade Mínima</label>
                        <input type="number" id="inventory-min" class="form-input" value="${item.min_quantity}" step="0.01">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Foto do Item (opcional)</label>
                        ${item.image_path ? `<img src="${item.image_path}" style="max-width: 200px; margin-bottom: 10px; border-radius: 5px;">` : ''}
                        <input type="file" id="inventory-image" class="form-input" accept="image/*" onchange="app.handleImageUpload(this, 'inventory-image-preview')">
                        <div id="inventory-image-preview" style="margin-top: 10px;"></div>
                    </div>
                </form>
            `;

            const footer = `
                <button type="button" class="btn btn-secondary" onclick="app.closeModal()">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="app.saveInventoryItem(${itemId})">Salvar</button>
            `;

            this.showModal('Editar Item do Inventário', content, footer);
        }
    } catch (error) {
        console.error('Error loading inventory item:', error);
        this.showAlert('Erro ao carregar item', 'error');
    }
};

// Delete inventory item
app.deleteInventoryItem = async function(itemId) {
    if (!confirm('Tem certeza que deseja excluir este item do inventário?')) return;

    try {
        const response = await fetch(`api/inventory.php?id=${itemId}`, {
            method: 'DELETE'
        });
        
        const result = await response.json();
        
        if (result.success) {
            this.showAlert('Item excluído com sucesso!', 'success');
            this.loadInventory();
            this.updateInventoryStats();
        }
    } catch (error) {
        console.error('Error deleting inventory item:', error);
        this.showAlert('Erro ao excluir item', 'error');
    }
};

// Show inventory movement modal
app.showInventoryMovementModal = async function(inventoryId) {
    // Load item name first
    try {
        const response = await fetch(`api/inventory.php?id=${inventoryId}`);
        const result = await response.json();
        
        if (!result.success) return;
        
        const item = result.data;
        
        const content = `
            <form id="movement-form">
                <div class="alert alert-info">
                    <strong>Item:</strong> ${item.name}<br>
                    <strong>Estoque Atual:</strong> ${item.quantity} ${item.unit}
                </div>
                <div class="form-group">
                    <label class="form-label">Tipo de Movimentação</label>
                    <select id="movement-type" class="form-select" required>
                        <option value="">Selecione</option>
                        <option value="in">Entrada (Compra/Recebimento)</option>
                        <option value="out">Saída (Envio para Cliente/Obra)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Quantidade</label>
                    <input type="number" id="movement-quantity" class="form-input" step="0.01" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Destino/Cliente (opcional)</label>
                    <input type="text" id="movement-destination" class="form-input" placeholder="Ex: Obra ABC, Cliente XYZ">
                </div>
                <div class="form-group">
                    <label class="form-label">Obra Relacionada (opcional)</label>
                    <select id="movement-project" class="form-select">
                        <option value="">Nenhuma</option>
                        ${this.projects.map(p => `<option value="${p.id}">${p.name}</option>`).join('')}
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Observações</label>
                    <textarea id="movement-notes" class="form-textarea"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Data da Movimentação</label>
                    <input type="date" id="movement-date" class="form-input" value="${new Date().toISOString().split('T')[0]}" required>
                </div>
            </form>
        `;

        const footer = `
            <button type="button" class="btn btn-secondary" onclick="app.closeModal()">Cancelar</button>
            <button type="button" class="btn btn-primary" onclick="app.saveInventoryMovement(${inventoryId})">Registrar Movimentação</button>
        `;

        this.showModal('Movimentar Estoque', content, footer);
    } catch (error) {
        this.showAlert('Erro ao carregar item', 'error');
    }
};

// Save inventory movement
app.saveInventoryMovement = async function(inventoryId) {
    const movement_type = document.getElementById('movement-type').value;
    const quantity = document.getElementById('movement-quantity').value;
    const destination = document.getElementById('movement-destination').value;
    const project_id = document.getElementById('movement-project').value;
    const notes = document.getElementById('movement-notes').value;
    const movement_date = document.getElementById('movement-date').value;

    if (!movement_type || !quantity) {
        this.showAlert('Tipo e quantidade são obrigatórios', 'error');
        return;
    }

    const data = {
        inventory_id: inventoryId,
        movement_type,
        quantity: parseFloat(quantity),
        destination,
        project_id: project_id || null,
        notes,
        movement_date
    };

    try {
        const response = await fetch('api/inventory-movements.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });

        const result = await response.json();
        
        if (result.success) {
            this.showAlert('Movimentação registrada com sucesso!', 'success');
            this.closeModal();
            this.loadInventory();
            this.updateInventoryStats();
        } else {
            throw new Error(result.error);
        }
    } catch (error) {
        console.error('Error saving movement:', error);
        this.showAlert('Erro ao registrar movimentação', 'error');
    }
};

// Show inventory history
app.showInventoryHistory = async function(inventoryId) {
    try {
        const [itemResponse, deliveriesResponse] = await Promise.all([
            fetch(`api/inventory.php?id=${inventoryId}`),
            fetch(`api/deliveries.php?inventory_id=${inventoryId}`)
        ]);
        
        const itemResult = await itemResponse.json();
        const deliveriesResult = await deliveriesResponse.json();
        
        const item = itemResult.success ? itemResult.data : {};
        const deliveries = deliveriesResult.success ? deliveriesResult.data : [];
        
        const content = `
            <div class="alert alert-info">
                <strong>Item:</strong> ${item.name || 'N/A'}<br>
                <strong>Estoque Atual:</strong> ${item.quantity || 0} ${item.unit || ''}
            </div>
            ${deliveries.length === 0 ? '<p class="text-muted">Nenhuma entrega registrada.</p>' : `
                <table class="table">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Data</th>
                            <th>Cliente/Destino</th>
                            <th>Quantidade</th>
                            <th>Valor</th>
                            <th>Obra</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${deliveries.map(d => `
                            <tr>
                                <td><strong>${d.delivery_code}</strong></td>
                                <td>${new Date(d.created_at).toLocaleDateString('pt-BR')}</td>
                                <td>${d.client_name || '-'}</td>
                                <td>${d.quantity} ${item.unit || ''}</td>
                                <td>${app.formatCurrency(d.total_value)}</td>
                                <td>${d.project_name || '-'}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `}
        `;

        const footer = `
            <button type="button" class="btn btn-primary" onclick="app.closeModal()">Fechar</button>
        `;

        this.showModal(`Histórico de Entregas - ${item.name || ''}`, content, footer);
    } catch (error) {
        console.error('Error loading history:', error);
        this.showAlert('Erro ao carregar histórico', 'error');
    }
};

// Global functions for inventory
window.showDeliveriesModal = async function() {
    try {
        const response = await fetch('api/deliveries.php');
        const result = await response.json();
        
        const deliveries = result.success ? result.data : [];
        
        const content = `
            <div class="deliveries-list">
                ${deliveries.length === 0 ? '<p class="text-center text-muted">Nenhuma entrega registrada.</p>' : `
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Data</th>
                                <th>Item</th>
                                <th>Cliente</th>
                                <th>Qtd</th>
                                <th>Valor</th>
                                <th>Obra</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${deliveries.map(d => `
                                <tr>
                                    <td><strong>${d.delivery_code}</strong></td>
                                    <td>${new Date(d.created_at).toLocaleDateString('pt-BR')}</td>
                                    <td>${d.item_name}</td>
                                    <td>${d.client_name || '-'}</td>
                                    <td>${d.quantity} ${d.unit || ''}</td>
                                    <td>${formatCurrency(d.total_value)}</td>
                                    <td>${d.project_name || '-'}</td>
                                    <td>
                                        <button class="btn btn-sm btn-warning" onclick="showImagesModal('inventory_deliveries', ${d.id}, 'Fotos da Entrega')">
                                            <i class="fas fa-images"></i>
                                        </button>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                `}
            </div>
        `;

        const footer = `
            <button type="button" class="btn btn-secondary" onclick="app.closeModal()">Fechar</button>
            <button type="button" class="btn btn-primary" onclick="app.closeModal(); showNewDeliveryModal()">
                <i class="fas fa-plus"></i> Nova Entrega
            </button>
        `;

        app.showModal('Histórico de Entregas', content, footer);
    } catch (error) {
        console.error('Error loading deliveries:', error);
    }
};

window.showNewDeliveryModal = async function() {
    try {
        const [inventoryRes, projectsRes] = await Promise.all([
            fetch('api/inventory.php'),
            fetch('api/projects.php')
        ]);
        
        const inventoryData = await inventoryRes.json();
        const projectsData = await projectsRes.json();
        
        const items = inventoryData.success ? inventoryData.data : [];
        const projects = projectsData.success ? projectsData.data : [];
        
        const content = `
            <form id="delivery-form">
                <div class="form-group">
                    <label>Item do Estoque *</label>
                    <select name="inventory_id" id="delivery-item" required>
                        <option value="">Selecione um item</option>
                        ${items.map(i => `<option value="${i.id}" data-qty="${i.quantity}" data-unit="${i.unit}" data-cost="${i.unit_cost}">${i.name} (${i.quantity} ${i.unit} disponíveis)</option>`).join('')}
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Quantidade *</label>
                        <input type="number" name="quantity" id="delivery-qty" step="0.01" min="0.01" required>
                    </div>
                    <div class="form-group">
                        <label>Preço Unitário (R$)</label>
                        <input type="number" name="unit_price" id="delivery-price" step="0.01">
                    </div>
                </div>
                <div class="form-group">
                    <label>Cliente/Destino</label>
                    <input type="text" name="client_name" placeholder="Nome do cliente ou destino">
                </div>
                <div class="form-group">
                    <label>Obra (opcional)</label>
                    <select name="project_id">
                        <option value="">Nenhuma obra</option>
                        ${projects.map(p => `<option value="${p.id}">${p.name}</option>`).join('')}
                    </select>
                </div>
                <div class="form-group">
                    <label>Observações</label>
                    <textarea name="notes" rows="3"></textarea>
                </div>
            </form>
        `;

        const footer = `
            <button type="button" class="btn btn-secondary" onclick="app.closeModal()">Cancelar</button>
            <button type="button" class="btn btn-primary" onclick="saveDelivery()">Registrar Entrega</button>
        `;

        app.showModal('Nova Entrega', content, footer);
        
        document.getElementById('delivery-item').addEventListener('change', function() {
            const selected = this.options[this.selectedIndex];
            if (selected.value) {
                document.getElementById('delivery-price').value = selected.dataset.cost || '';
            }
        });
    } catch (error) {
        console.error('Error loading delivery form:', error);
    }
};

window.saveDelivery = async function() {
    const form = document.getElementById('delivery-form');
    const formData = new FormData(form);
    const data = Object.fromEntries(formData);
    
    if (!data.inventory_id || !data.quantity) {
        showNotification('Selecione um item e informe a quantidade', 'error');
        return;
    }
    
    data.quantity = parseFloat(data.quantity);
    data.unit_price = parseFloat(data.unit_price) || null;
    data.project_id = data.project_id || null;
    
    try {
        const response = await fetch('api/deliveries.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await response.json();
        
        if (result.success) {
            app.closeModal();
            app.loadInventory();
            app.updateInventoryStats();
            showNotification(`Entrega registrada! Código: ${result.data.delivery_code}`, 'success');
        } else {
            showNotification(result.error || 'Erro ao registrar entrega', 'error');
        }
    } catch (error) {
        console.error('Error saving delivery:', error);
        showNotification('Erro ao registrar entrega', 'error');
    }
};

window.showInventoryHistory = async function() {
    try {
        const response = await fetch('api/history.php?table_name=inventory');
        const data = await response.json();
        
        if (data.success) {
            showHistoryModal(data.data, 'Histórico do Inventário');
        }
    } catch (error) {
        console.error('Error loading history:', error);
    }
};

window.generateInventoryPDF = async function() {
    try {
        showNotification('Gerando PDF...', 'info');
        
        const [inventoryRes, movementsRes] = await Promise.all([
            fetch('api/inventory.php'),
            fetch('api/inventory-movements.php')
        ]);
        
        const inventoryData = await inventoryRes.json();
        const movementsData = await movementsRes.json();
        
        if (!inventoryData.success) {
            showNotification('Erro ao carregar inventário', 'error');
            return;
        }
        
        const items = inventoryData.data;
        const movements = movementsData.success ? movementsData.data : [];
        
        const totalValue = items.reduce((acc, item) => acc + (item.quantity * item.unit_cost), 0);
        const lowStockItems = items.filter(i => i.low_stock);
        
        const formatCurrency = (value) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value || 0);
        
        const container = document.createElement('div');
        container.innerHTML = `
            <div style="font-family: Arial, sans-serif; padding: 20px; color: #333;">
                <div style="text-align: center; margin-bottom: 30px; border-bottom: 3px solid #F5A623; padding-bottom: 20px;">
                    <h1 style="color: #2E3B5B; margin: 0;">PACHECO EMPREENDIMENTOS</h1>
                    <p style="color: #666; margin: 5px 0 0 0;">Relatório de Inventário</p>
                </div>
                
                <div style="display: flex; justify-content: space-around; margin-bottom: 30px;">
                    <div style="text-align: center; padding: 15px; background: #f5f5f5; border-radius: 8px;">
                        <h3 style="margin: 0; color: #F5A623; font-size: 1.5em;">${items.length}</h3>
                        <p style="margin: 5px 0 0 0; color: #666;">Itens no Inventário</p>
                    </div>
                    <div style="text-align: center; padding: 15px; background: #f5f5f5; border-radius: 8px;">
                        <h3 style="margin: 0; color: #F5A623; font-size: 1.5em;">${formatCurrency(totalValue)}</h3>
                        <p style="margin: 5px 0 0 0; color: #666;">Valor Total</p>
                    </div>
                    <div style="text-align: center; padding: 15px; background: #f5f5f5; border-radius: 8px;">
                        <h3 style="margin: 0; color: #F5A623; font-size: 1.5em;">${lowStockItems.length}</h3>
                        <p style="margin: 5px 0 0 0; color: #666;">Itens com Estoque Baixo</p>
                    </div>
                </div>
                
                <div style="margin-bottom: 30px;">
                    <h2 style="color: #2E3B5B; border-bottom: 2px solid #F5A623; padding-bottom: 10px;">Inventário Completo</h2>
                    <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px;">
                        <thead>
                            <tr style="background: #2E3B5B; color: white;">
                                <th style="padding: 10px; text-align: left;">Item</th>
                                <th style="padding: 10px; text-align: left;">Descrição</th>
                                <th style="padding: 10px; text-align: left;">Quantidade</th>
                                <th style="padding: 10px; text-align: left;">Custo Unit.</th>
                                <th style="padding: 10px; text-align: left;">Valor Total</th>
                                <th style="padding: 10px; text-align: left;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${items.map((item, i) => `
                                <tr style="background: ${item.low_stock ? '#fff3cd' : (i % 2 === 0 ? '#f9f9f9' : 'white')};">
                                    <td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong>${item.name}</strong></td>
                                    <td style="padding: 8px; border-bottom: 1px solid #ddd;">${item.description || '-'}</td>
                                    <td style="padding: 8px; border-bottom: 1px solid #ddd;">${item.quantity} ${item.unit}</td>
                                    <td style="padding: 8px; border-bottom: 1px solid #ddd;">${formatCurrency(item.unit_cost)}</td>
                                    <td style="padding: 8px; border-bottom: 1px solid #ddd;">${formatCurrency(item.total_value)}</td>
                                    <td style="padding: 8px; border-bottom: 1px solid #ddd;">${item.low_stock ? 'Estoque Baixo' : 'OK'}</td>
                                </tr>
                            `).join('')}
                            <tr style="font-weight: bold; background: #eee;">
                                <td colspan="4" style="padding: 8px;">TOTAL GERAL</td>
                                <td style="padding: 8px;">${formatCurrency(totalValue)}</td>
                                <td style="padding: 8px;"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                ${movements.length > 0 ? `
                <div style="margin-bottom: 30px;">
                    <h2 style="color: #2E3B5B; border-bottom: 2px solid #F5A623; padding-bottom: 10px;">Últimas Movimentações</h2>
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #2E3B5B; color: white;">
                                <th style="padding: 10px; text-align: left;">Código</th>
                                <th style="padding: 10px; text-align: left;">Data</th>
                                <th style="padding: 10px; text-align: left;">Item</th>
                                <th style="padding: 10px; text-align: left;">Tipo</th>
                                <th style="padding: 10px; text-align: left;">Quantidade</th>
                                <th style="padding: 10px; text-align: left;">Destino</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${movements.slice(0, 20).map((m, i) => `
                                <tr style="background: ${i % 2 === 0 ? '#f9f9f9' : 'white'};">
                                    <td style="padding: 8px; border-bottom: 1px solid #ddd;">${m.transaction_code || '-'}</td>
                                    <td style="padding: 8px; border-bottom: 1px solid #ddd;">${m.movement_date_formatted || m.movement_date}</td>
                                    <td style="padding: 8px; border-bottom: 1px solid #ddd;">${m.item_name || '-'}</td>
                                    <td style="padding: 8px; border-bottom: 1px solid #ddd;">${m.movement_type === 'in' ? 'Entrada' : 'Saída'}</td>
                                    <td style="padding: 8px; border-bottom: 1px solid #ddd;">${m.quantity}</td>
                                    <td style="padding: 8px; border-bottom: 1px solid #ddd;">${m.destination || m.project_name || '-'}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
                ` : ''}
                
                <div style="text-align: center; margin-top: 30px; color: #666; font-size: 0.9em;">
                    Relatório gerado em ${new Date().toLocaleString('pt-BR')}<br>
                    Pacheco Empreendimentos - Sistema de Gerenciamento
                </div>
            </div>
        `;
        
        const opt = {
            margin: 10,
            filename: `inventario_pacheco_${new Date().toISOString().split('T')[0]}.pdf`,
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2 },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
        };
        
        html2pdf().set(opt).from(container).save();
        
        showNotification('PDF gerado com sucesso! Baixando...', 'success');
    } catch (error) {
        console.error('Error generating PDF:', error);
        showNotification('Erro ao gerar PDF', 'error');
    }
};