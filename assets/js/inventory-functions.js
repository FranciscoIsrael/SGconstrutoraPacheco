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
        const [itemResponse, movementsResponse] = await Promise.all([
            fetch(`api/inventory.php?id=${inventoryId}`),
            fetch(`api/inventory-movements.php?inventory_id=${inventoryId}`)
        ]);
        
        const itemResult = await itemResponse.json();
        const movementsResult = await movementsResponse.json();
        
        if (!itemResult.success || !movementsResult.success) {
            throw new Error('Failed to load history');
        }
        
        const item = itemResult.data;
        const movements = movementsResult.data;
        
        const content = `
            <div class="alert alert-info">
                <strong>Item:</strong> ${item.name}<br>
                <strong>Estoque Atual:</strong> ${item.quantity} ${item.unit}
            </div>
            ${movements.length === 0 ? '<p class="text-muted">Nenhuma movimentação registrada.</p>' : `
                <table class="table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Tipo</th>
                            <th>Quantidade</th>
                            <th>Destino</th>
                            <th>Obra</th>
                            <th>Observações</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${movements.map(m => `
                            <tr>
                                <td>${m.movement_date_formatted}</td>
                                <td><span class="badge ${m.movement_type === 'in' ? 'badge-success' : 'badge-danger'}">${m.movement_type === 'in' ? 'Entrada' : 'Saída'}</span></td>
                                <td>${m.quantity} ${item.unit}</td>
                                <td>${m.destination || '-'}</td>
                                <td>${m.project_name || '-'}</td>
                                <td>${m.notes || '-'}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `}
        `;

        const footer = `
            <button type="button" class="btn btn-primary" onclick="app.closeModal()">Fechar</button>
        `;

        this.showModal(`Histórico de Movimentações - ${item.name}`, content, footer);
    } catch (error) {
        console.error('Error loading history:', error);
        this.showAlert('Erro ao carregar histórico', 'error');
    }
};