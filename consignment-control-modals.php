<!-- ============================================================ -->
<!-- 🎛️ CONSIGNMENT CONTROL PANEL MODALS -->
<!-- Full CRUD + Stock Management Interface -->
<!-- ============================================================ -->

<!-- CREATE CONSIGNMENT MODAL -->
<div class="modal fade" id="createConsignmentModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Create New Consignment/Transfer</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="createConsignmentForm">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Source Outlet *</label>
                                <select class="form-select" name="source_outlet_id" required>
                                    <option value="">-- Select Source --</option>
                                    <option value="1">Adelaide - Main</option>
                                    <option value="2">Auckland CBD</option>
                                    <option value="3">Brisbane Central</option>
                                    <option value="4">Christchurch</option>
                                    <option value="5">Melbourne Central</option>
                                    <option value="6">Sydney - George St</option>
                                    <option value="7">Wellington</option>
                                    <!-- Add all 18 outlets -->
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Destination Outlet *</label>
                                <select class="form-select" name="destination_outlet_id" required>
                                    <option value="">-- Select Destination --</option>
                                    <option value="1">Adelaide - Main</option>
                                    <option value="2">Auckland CBD</option>
                                    <option value="3">Brisbane Central</option>
                                    <option value="4">Christchurch</option>
                                    <option value="5">Melbourne Central</option>
                                    <option value="6">Sydney - George St</option>
                                    <option value="7">Wellington</option>
                                    <!-- Add all 18 outlets -->
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Transfer Type</label>
                                <select class="form-select" name="type">
                                    <option value="OUTLET_TRANSFER">Outlet Transfer</option>
                                    <option value="WAREHOUSE_TRANSFER">Warehouse Transfer</option>
                                    <option value="RETURN">Return</option>
                                    <option value="EMERGENCY">Emergency Transfer</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Initial Status</label>
                                <select class="form-select" name="status">
                                    <option value="OPEN">OPEN (Draft)</option>
                                    <option value="SENT">SENT (Already Shipped)</option>
                                    <option value="RECEIVED">RECEIVED (Completed)</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Reference (Optional)</label>
                                <input type="text" class="form-control" name="reference" placeholder="Auto-generated if blank">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Notes</label>
                        <textarea class="form-control" name="notes" rows="3" placeholder="Add any notes about this transfer..."></textarea>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> <strong>Products:</strong> You can add products after creating the consignment.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="consignmentHub.submitCreateConsignment()">
                    <i class="fas fa-check"></i> Create Consignment
                </button>
            </div>
        </div>
    </div>
</div>

<!-- EDIT CONSIGNMENT MODAL -->
<div class="modal fade" id="editConsignmentModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Consignment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="editConsignmentContent">
                    <!-- Loaded dynamically -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- STOCK ADJUSTMENT MODAL -->
<div class="modal fade" id="stockAdjustmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-boxes"></i> Manual Stock Adjustment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> <strong>Warning:</strong> Manual stock adjustments will be logged and audited.
                </div>
                
                <form id="stockAdjustmentForm">
                    <input type="hidden" name="consignment_id" id="adjustConsignmentId">
                    <input type="hidden" name="variant_id" id="adjustVariantId">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Adjustment Location</label>
                        <select class="form-select" id="adjustmentLocation">
                            <option value="source">Source Outlet (Deduct Stock)</option>
                            <option value="destination">Destination Outlet (Add Stock)</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Adjustment Amount</label>
                        <input type="number" class="form-control" id="adjustmentAmount" placeholder="Enter positive or negative number">
                        <small class="text-muted">Positive = Add stock | Negative = Remove stock</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Reason for Adjustment *</label>
                        <textarea class="form-control" id="adjustmentReason" rows="3" required placeholder="Explain why this adjustment is necessary..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="consignmentHub.submitStockAdjustment()">
                    <i class="fas fa-check"></i> Apply Adjustment
                </button>
            </div>
        </div>
    </div>
</div>

<!-- PRODUCT MANAGEMENT MODAL -->
<div class="modal fade" id="productManagementModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-boxes"></i> Manage Products</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="productManagementConsignmentId">
                
                <!-- Current Products -->
                <h6 class="fw-bold mb-3">Current Products</h6>
                <div class="table-responsive mb-4">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>SKU</th>
                                <th>Quantity</th>
                                <th>Cost</th>
                                <th>Total</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="currentProductsList">
                            <tr>
                                <td colspan="6" class="text-center text-muted">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Add Products -->
                <h6 class="fw-bold mb-3">Add Products</h6>
                <div class="input-group mb-3">
                    <input type="text" class="form-control" id="productSearchInput" placeholder="Search products by name or SKU...">
                    <button class="btn btn-primary" onclick="consignmentHub.searchProducts()">
                        <i class="fas fa-search"></i> Search
                    </button>
                </div>
                
                <div id="productSearchResults">
                    <!-- Search results appear here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- MOVE/REROUTE CONSIGNMENT MODAL -->
<div class="modal fade" id="moveConsignmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-route"></i> Move/Reroute Consignment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="moveConsignmentForm">
                    <input type="hidden" name="consignment_id" id="moveConsignmentId">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">New Source Outlet (Optional)</label>
                        <select class="form-select" name="new_source_id">
                            <option value="">-- Keep Current --</option>
                            <option value="1">Adelaide - Main</option>
                            <option value="2">Auckland CBD</option>
                            <option value="3">Brisbane Central</option>
                            <!-- Add all outlets -->
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">New Destination Outlet (Optional)</label>
                        <select class="form-select" name="new_destination_id">
                            <option value="">-- Keep Current --</option>
                            <option value="1">Adelaide - Main</option>
                            <option value="2">Auckland CBD</option>
                            <option value="3">Brisbane Central</option>
                            <!-- Add all outlets -->
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Reason for Move</label>
                        <textarea class="form-control" name="reason" rows="2" required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="consignmentHub.submitMoveConsignment()">
                    <i class="fas fa-check"></i> Move Consignment
                </button>
            </div>
        </div>
    </div>
</div>

<!-- LIGHTSPEED APPROVAL MODAL -->
<div class="modal fade" id="lightspeedApprovalModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-check-circle"></i> Approve for Lightspeed</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="approvalConsignmentId">
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> This consignment will be pushed to Lightspeed. Choose the initial state:
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Push as State:</label>
                    <select class="form-select" id="approvalPushState">
                        <option value="OPEN">OPEN - Editable draft (default)</option>
                        <option value="SENT">SENT - Already shipped (historical)</option>
                        <option value="RECEIVED">RECEIVED - Already completed</option>
                    </select>
                    <small class="text-muted">
                        <strong>OPEN:</strong> Active transfer, can be edited<br>
                        <strong>SENT:</strong> For last week's shipped transfers<br>
                        <strong>RECEIVED:</strong> For completed historical data
                    </small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="consignmentHub.submitLightspeedApproval()">
                    <i class="fas fa-check"></i> Approve & Queue for Push
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ENHANCED JAVASCRIPT FOR CONTROL PANEL -->
<script>
// Add to existing ConsignmentHubController class

// CREATE CONSIGNMENT
async submitCreateConsignment() {
    const formData = new FormData(document.getElementById('createConsignmentForm'));
    formData.append('action', 'create_consignment');
    
    try {
        const response = await fetch('../api/consignment-control.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            this.showNotification(`✅ Consignment ${result.reference} created successfully!`, 'success');
            bootstrap.Modal.getInstance(document.getElementById('createConsignmentModal')).hide();
            this.loadRecentTransfers();
        } else {
            this.showNotification('❌ Error: ' + result.error, 'danger');
        }
    } catch (error) {
        this.showNotification('❌ Network error: ' + error.message, 'danger');
    }
}

// DELETE CONSIGNMENT
async deleteConsignment(consignmentId) {
    const reason = prompt('⚠️ REASON FOR DELETION:\n\nThis action will be logged and audited.');
    
    if (!reason || reason.trim() === '') {
        return;
    }
    
    if (!confirm('🗑️ DELETE CONSIGNMENT?\n\nThis will remove the consignment and all its products.\n\nContinue?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'delete_consignment');
    formData.append('consignment_id', consignmentId);
    formData.append('reason', reason);
    
    try {
        const response = await fetch('../api/consignment-control.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            this.showNotification('✅ ' + result.message, 'success');
            this.loadRecentTransfers();
        } else {
            this.showNotification('❌ Error: ' + result.error, 'danger');
        }
    } catch (error) {
        this.showNotification('❌ Network error: ' + error.message, 'danger');
    }
}

// CHANGE STATUS
async changeStatus(consignmentId) {
    const newStatus = prompt('Enter new status:\n\nOPEN | SENT | RECEIVED | CANCELLED');
    
    if (!newStatus) {
        return;
    }
    
    const reason = prompt('Reason for status change:');
    
    const formData = new FormData();
    formData.append('action', 'change_status');
    formData.append('consignment_id', consignmentId);
    formData.append('new_status', newStatus.toUpperCase());
    formData.append('reason', reason || 'Manual status change');
    
    try {
        const response = await fetch('../api/consignment-control.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            this.showNotification(`✅ Status changed to ${result.new_status}`, 'success');
            this.loadRecentTransfers();
        } else {
            this.showNotification('❌ Error: ' + result.error, 'danger');
        }
    } catch (error) {
        this.showNotification('❌ Network error: ' + error.message, 'danger');
    }
}

// STOCK ADJUSTMENT
openStockAdjustment(consignmentId, variantId, location) {
    document.getElementById('adjustConsignmentId').value = consignmentId;
    document.getElementById('adjustVariantId').value = variantId;
    document.getElementById('adjustmentLocation').value = location;
    
    new bootstrap.Modal(document.getElementById('stockAdjustmentModal')).show();
}

async submitStockAdjustment() {
    const consignmentId = document.getElementById('adjustConsignmentId').value;
    const variantId = document.getElementById('adjustVariantId').value;
    const location = document.getElementById('adjustmentLocation').value;
    const adjustment = document.getElementById('adjustmentAmount').value;
    const reason = document.getElementById('adjustmentReason').value;
    
    if (!adjustment || !reason) {
        this.showNotification('❌ Please fill in all fields', 'danger');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', location === 'source' ? 'adjust_source_stock' : 'adjust_destination_stock');
    formData.append('consignment_id', consignmentId);
    formData.append('variant_id', variantId);
    formData.append('adjustment', adjustment);
    formData.append('reason', reason);
    
    try {
        const response = await fetch('../api/consignment-control.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            this.showNotification(`✅ Stock adjusted: ${result.adjustment > 0 ? '+' : ''}${result.adjustment} units`, 'success');
            bootstrap.Modal.getInstance(document.getElementById('stockAdjustmentModal')).hide();
        } else {
            this.showNotification('❌ Error: ' + result.error, 'danger');
        }
    } catch (error) {
        this.showNotification('❌ Network error: ' + error.message, 'danger');
    }
}

// MOVE CONSIGNMENT
openMoveConsignment(consignmentId) {
    document.getElementById('moveConsignmentId').value = consignmentId;
    new bootstrap.Modal(document.getElementById('moveConsignmentModal')).show();
}

async submitMoveConsignment() {
    const formData = new FormData(document.getElementById('moveConsignmentForm'));
    formData.append('action', 'move_consignment');
    
    try {
        const response = await fetch('../api/consignment-control.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            this.showNotification('✅ ' + result.message, 'success');
            bootstrap.Modal.getInstance(document.getElementById('moveConsignmentModal')).hide();
            this.loadRecentTransfers();
        } else {
            this.showNotification('❌ Error: ' + result.error, 'danger');
        }
    } catch (error) {
        this.showNotification('❌ Network error: ' + error.message, 'danger');
    }
}

// LIGHTSPEED APPROVAL
openLightspeedApproval(consignmentId) {
    document.getElementById('approvalConsignmentId').value = consignmentId;
    new bootstrap.Modal(document.getElementById('lightspeedApprovalModal')).show();
}

async submitLightspeedApproval() {
    const consignmentId = document.getElementById('approvalConsignmentId').value;
    const pushState = document.getElementById('approvalPushState').value;
    
    const formData = new FormData();
    formData.append('action', 'approve_for_lightspeed');
    formData.append('consignment_id', consignmentId);
    formData.append('push_state', pushState);
    
    try {
        const response = await fetch('../api/consignment-control.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            this.showNotification(`✅ Approved for Lightspeed as ${result.push_state}`, 'success');
            bootstrap.Modal.getInstance(document.getElementById('lightspeedApprovalModal')).hide();
            this.loadRecentTransfers();
        } else {
            this.showNotification('❌ Error: ' + result.error, 'danger');
        }
    } catch (error) {
        this.showNotification('❌ Network error: ' + error.message, 'danger');
    }
}
</script>

<!-- Additional Control Panel Styles -->
<style>
.modal-xl {
    max-width: 1200px;
}

.form-label {
    margin-bottom: 0.25rem;
}

.form-select, .form-control {
    font-size: 0.95rem;
}

.alert {
    font-size: 0.9rem;
}

.btn-group-sm > .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

.table-sm td {
    vertical-align: middle;
}

.modal-body {
    max-height: 70vh;
    overflow-y: auto;
}
</style>
