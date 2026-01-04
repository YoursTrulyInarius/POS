<?php
include "db.php";

// Fetch products with aggregated inventory data
$res = $conn->query("
    SELECT 
        p.product_id,
        p.product_name,
        p.stock,
        p.price,
        COALESCE(SUM(i.quantity), 0) as total_inventory_movement,
        COUNT(i.inventory_id) as transaction_count,
        MAX(i.last_updated) as last_updated
    FROM products p 
    LEFT JOIN inventory i ON p.product_id = i.product_id
    GROUP BY p.product_id, p.product_name, p.stock, p.price
    ORDER BY p.product_name
");

$products = [];
$total_stock_value = 0;
$low_stock_count = 0;
$out_of_stock_count = 0;

while($row = $res->fetch_assoc()) {
    $products[] = $row;
    $total_stock_value += ($row['stock'] * $row['price']);
    if ($row['stock'] <= 5) $low_stock_count++;
    if ($row['stock'] == 0) $out_of_stock_count++;
}

// Get recent inventory transactions
$recent_transactions = $conn->query("
    SELECT 
        i.inventory_id,
        i.product_id,
        p.product_name,
        i.quantity,
        i.last_updated
    FROM inventory i
    JOIN products p ON i.product_id = p.product_id
    ORDER BY i.last_updated DESC
    LIMIT 15
");

// Calculate inventory statistics
$total_products = count($products);
$total_units = array_sum(array_column($products, 'stock'));
?>
<!DOCTYPE html>
<html>
<head>
<title>Advanced Inventory Management</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
* {margin:0;padding:0;box-sizing:border-box;}

:root {
    --primary-color: #007bff;
    --primary-dark: #0056b3;
    --primary-light: #e8f0fe;
    --secondary-color: #fff;
    --text-color: #333;
    --bg-color: #f4f6f9;
    --success: #28a745;
    --warning: #ffc107;
    --danger: #dc3545;
    --info: #17a2b8;
    --light: #f8f9fa;
    --dark: #343a40;
    --card-shadow: 0 4px 12px rgba(0,0,0,0.1);
    --border-radius: 12px;
}

.theme-red {
    --primary-color: #dc3545;
    --primary-dark: #c82333;
    --primary-light: #f8d7da;
    --bg-color: #fff5f5;
}

.theme-green {
    --primary-color: #28a745;
    --primary-dark: #218838;
    --primary-light: #d4edda;
    --bg-color: #f8fff9;
}

.theme-purple {
    --primary-color: #6f42c1;
    --primary-dark: #59359a;
    --primary-light: #e2d9f3;
    --bg-color: #faf9fc;
}

.theme-orange {
    --primary-color: #fd7e14;
    --primary-dark: #e8690b;
    --primary-light: #ffeeba;
    --bg-color: #fffaf3;
}

body {
    font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;
    background:var(--bg-color);
    color:var(--text-color);
    line-height:1.6;
    transition:all 0.3s ease;
}

.container {
    max-width:1400px;
    margin:0 auto;
    padding:20px;
}

.header {
    background:white;
    border-radius:var(--border-radius);
    padding:25px;
    margin-bottom:25px;
    box-shadow:var(--card-shadow);
    display:flex;
    justify-content:space-between;
    align-items:center;
    flex-wrap:wrap;
    gap:15px;
}

.header-content h1 {
    color:var(--primary-color);
    font-size:32px;
    font-weight:700;
    margin-bottom:5px;
}

.header-content p {
    color:#666;
    font-size:16px;
}

.btn {
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:12px 20px;
    background:var(--primary-color);
    color:white;
    text-decoration:none;
    border-radius:8px;
    font-weight:500;
    transition:all 0.3s ease;
    border:none;
    cursor:pointer;
    font-size:14px;
}

.btn:hover {
    background:var(--primary-dark);
    transform:translateY(-2px);
}

.btn-sm {
    padding:8px 14px;
    font-size:12px;
}

.btn-warning{background:var(--warning);color:#000;}
.btn-warning:hover{background:#e0a800;}
.btn-danger{background:var(--danger);}
.btn-danger:hover{background:#c82333;}
.btn-info{background:var(--info);}
.btn-info:hover{background:#138496;}
.btn-success{background:var(--success);}
.btn-success:hover{background:#218838;}

/* Statistics Cards */
.stats-grid {
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(250px,1fr));
    gap:20px;
    margin-bottom:30px;
}

.stat-card {
    background:white;
    border-radius:var(--border-radius);
    padding:25px;
    box-shadow:var(--card-shadow);
    position:relative;
    overflow:hidden;
    transition:transform 0.3s ease;
}

.stat-card:hover {
    transform:translateY(-5px);
}

.stat-card::before {
    content:'';
    position:absolute;
    top:0;
    left:0;
    right:0;
    height:4px;
}

.stat-card.products::before {
    background:linear-gradient(90deg,var(--primary-color),var(--info));
}

.stat-card.units::before {
    background:linear-gradient(90deg,var(--success),#20c997);
}

.stat-card.value::before {
    background:linear-gradient(90deg,var(--warning),#fd7e14);
}

.stat-card.alerts::before {
    background:linear-gradient(90deg,var(--danger),#e74c3c);
}

.stat-header {
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    margin-bottom:15px;
}

.stat-title {
    font-size:14px;
    font-weight:600;
    color:#666;
    text-transform:uppercase;
    letter-spacing:0.5px;
}

.stat-icon {
    font-size:28px;
    opacity:0.7;
}

.stat-value {
    font-size:32px;
    font-weight:700;
    color:var(--dark);
    margin-bottom:8px;
}

.stat-subtitle {
    font-size:14px;
    color:#666;
}

.controls-card {
    background:white;
    border-radius:var(--border-radius);
    padding:20px;
    margin-bottom:25px;
    box-shadow:var(--card-shadow);
}

.controls {
    display:flex;
    gap:15px;
    flex-wrap:wrap;
    align-items:center;
}

.search-box {
    position:relative;
    flex:1;
    min-width:250px;
}

.search-box input {
    width:100%;
    padding:12px 16px 12px 45px;
    border:2px solid #e9ecef;
    border-radius:8px;
    font-size:14px;
    transition:border-color 0.3s ease;
}

.search-box input:focus {
    outline:none;
    border-color:var(--primary-color);
    box-shadow:0 0 0 3px rgba(0,123,255,0.1);
}

.search-box::before {
    content:"🔍";
    position:absolute;
    left:15px;
    top:50%;
    transform:translateY(-50%);
    font-size:16px;
    z-index:1;
}

.filter-select {
    padding:12px 16px;
    border:2px solid #e9ecef;
    border-radius:8px;
    background:white;
    font-size:14px;
    cursor:pointer;
    transition:border-color 0.3s ease;
    min-width:180px;
}

.filter-select:focus {
    outline:none;
    border-color:var(--primary-color);
    box-shadow:0 0 0 3px rgba(0,123,255,0.1);
}

.table-card {
    background:white;
    border-radius:var(--border-radius);
    box-shadow:var(--card-shadow);
    overflow:hidden;
}

.table-responsive {
    overflow-x:auto;
}

table {
    width:100%;
    border-collapse:collapse;
    font-size:14px;
}

thead th {
    background:linear-gradient(135deg,var(--primary-color),var(--primary-dark));
    color:white;
    padding:18px 15px;
    font-weight:600;
    text-align:left;
    cursor:pointer;
    user-select:none;
    white-space:nowrap;
    transition:background 0.3s ease;
}

thead th:hover {
    background:var(--primary-dark);
}

thead th .sort-icon {
    margin-left:8px;
    font-size:12px;
    opacity:0.7;
}

tbody td {
    padding:15px;
    border-bottom:1px solid #e9ecef;
    vertical-align:middle;
}

tbody tr:hover {
    background:#f8f9fa;
}

tbody tr:last-child td {
    border-bottom:none;
}

.product-name {
    font-weight:600;
    color:var(--dark);
}

.stock-badge {
    display:inline-block;
    padding:6px 14px;
    border-radius:20px;
    font-size:12px;
    font-weight:600;
    min-width:70px;
    text-align:center;
}

.good-stock {
    background:#d4edda;
    color:#155724;
}

.low-stock {
    background:#fff3cd;
    color:#856404;
}

.out-stock {
    background:#f8d7da;
    color:#721c24;
}

.inventory-qty {
    font-weight:600;
    font-size:16px;
}

.inventory-positive {
    color:var(--success);
}

.inventory-negative {
    color:var(--danger);
}

.inventory-neutral {
    color:#6c757d;
}

.actions {
    display:flex;
    gap:8px;
    flex-wrap:wrap;
}

.pagination {
    padding:20px;
    text-align:center;
    background:#f8f9fa;
    border-top:1px solid #e9ecef;
}

.pagination-info {
    margin-bottom:15px;
    color:#666;
    font-size:14px;
}

.pagination button {
    padding:8px 12px;
    margin:0 2px;
    border:none;
    background:var(--primary-color);
    color:white;
    border-radius:6px;
    cursor:pointer;
    transition:all 0.3s ease;
    font-size:14px;
}

.pagination button:hover {
    background:var(--primary-dark);
}

.pagination button.active {
    background:var(--dark);
}

.pagination button:disabled {
    background:#6c757d;
    cursor:not-allowed;
    opacity:0.5;
}

.empty-state {
    text-align:center;
    padding:60px 20px;
    color:#6c757d;
}

.empty-state .icon {
    font-size:64px;
    margin-bottom:20px;
    opacity:0.3;
}

.last-updated {
    font-size:12px;
    color:#6c757d;
}

.recent-transactions {
    background:white;
    border-radius:var(--border-radius);
    box-shadow:var(--card-shadow);
    padding:25px;
    margin-top:25px;
}

.recent-transactions h3 {
    color:var(--primary-color);
    font-size:22px;
    margin-bottom:20px;
}

.transaction-item {
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding:12px 0;
    border-bottom:1px solid #e9ecef;
    transition:all 0.3s ease;
}

.transaction-item:hover {
    background:#f8f9fa;
    padding-left:10px;
}

.transaction-item:last-child {
    border-bottom:none;
}

.transaction-type {
    font-weight:600;
    padding:4px 10px;
    border-radius:4px;
    font-size:12px;
}

.transaction-sale {
    background:#f8d7da;
    color:#721c24;
}

.transaction-restock {
    background:#d4edda;
    color:#155724;
}

.tabs {
    display:flex;
    gap:10px;
    margin-bottom:20px;
}

.tab {
    padding:12px 24px;
    background:#f8f9fa;
    border:2px solid transparent;
    cursor:pointer;
    border-radius:8px;
    font-weight:500;
    transition:all 0.3s ease;
    color:#666;
}

.tab:hover {
    background:#e9ecef;
}

.tab.active {
    background:white;
    border-color:var(--primary-color);
    color:var(--primary-color);
}

.tab-content {
    display:none;
}

.tab-content.active {
    display:block;
}

.stock-value {
    font-weight:600;
    color:var(--success);
}

.quick-filters {
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    margin-bottom:15px;
}

.quick-filter {
    padding:8px 16px;
    border:2px solid #e9ecef;
    border-radius:20px;
    background:white;
    cursor:pointer;
    font-size:13px;
    font-weight:500;
    transition:all 0.3s ease;
}

.quick-filter:hover {
    border-color:var(--primary-color);
    background:var(--primary-light);
}

.quick-filter.active {
    border-color:var(--primary-color);
    background:var(--primary-color);
    color:white;
}

.btn-export {
    background:var(--success);
    margin-left:auto;
}

.btn-export:hover {
    background:#218838;
}

@media (max-width: 768px) {
    .container {
        padding:15px;
    }
    
    .header {
        flex-direction:column;
        text-align:center;
    }
    
    .stats-grid {
        grid-template-columns:repeat(2,1fr);
    }
    
    .controls {
        flex-direction:column;
    }
    
    .search-box {
        min-width:100%;
    }
    
    .filter-select {
        width:100%;
    }
    
    thead th, tbody td {
        padding:10px 8px;
    }
    
    .actions {
        flex-direction:column;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns:1fr;
    }
}
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <div class="header-content">
            <h1>Inventory Management</h1>
            <p>Advanced stock tracking and analytics dashboard</p>
        </div>
        <div>
            <a href="dashboard.php" class="btn">← Return to Dashboard</a>
        </div>
    </div>

    <!-- Statistics Dashboard -->
    <div class="stats-grid">
        <div class="stat-card products">
            <div class="stat-header">
                <div class="stat-title">Total Products</div>
                <div class="stat-icon">📦</div>
            </div>
            <div class="stat-value"><?= number_format($total_products) ?></div>
            <div class="stat-subtitle">Items in inventory</div>
        </div>

        <div class="stat-card units">
            <div class="stat-header">
                <div class="stat-title">Total Units</div>
                <div class="stat-icon">📊</div>
            </div>
            <div class="stat-value"><?= number_format($total_units) ?></div>
            <div class="stat-subtitle">Stock quantity</div>
        </div>

        <div class="stat-card value">
            <div class="stat-header">
                <div class="stat-title">Stock Value</div>
                <div class="stat-icon">💰</div>
            </div>
            <div class="stat-value">₱<?= number_format($total_stock_value, 2) ?></div>
            <div class="stat-subtitle">Total inventory worth</div>
        </div>

        <div class="stat-card alerts">
            <div class="stat-header">
                <div class="stat-title">Stock Alerts</div>
                <div class="stat-icon">⚠️</div>
            </div>
            <div class="stat-value"><?= $low_stock_count ?></div>
            <div class="stat-subtitle"><?= $out_of_stock_count ?> out of stock</div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="controls-card">
        <div class="tabs">
            <button class="tab active" onclick="showTab('products')">Product Stock</button>
            <button class="tab" onclick="showTab('transactions')">Recent Transactions</button>
        </div>
    </div>

    <!-- Products Tab -->
    <div id="products-tab" class="tab-content active">
        <div class="controls-card">
            <div class="controls">
                <div class="search-box">
                    <input type="text" id="search" placeholder="Search by Number or Product Name">
                </div>
                <select id="filter" class="filter-select">
                    <option value="all">All Products</option>
                    <option value="low">Low Stock (<?= $low_stock_count ?>)</option>
                    <option value="out">Out of Stock (<?= $out_of_stock_count ?>)</option>
                    <option value="recent">Recently Updated</option>
                    <option value="high-value">High Value Items</option>
                    <option value="no-movement">No Movement</option>
                </select>
                <select id="sort" class="filter-select">
                    <option value="name-asc">Name (A-Z)</option>
                    <option value="name-desc">Name (Z-A)</option>
                    <option value="stock-high">Stock (High to Low)</option>
                    <option value="stock-low">Stock (Low to High)</option>
                    <option value="value-high">Value (High to Low)</option>
                    <option value="value-low">Value (Low to High)</option>
                    <option value="updated-new">Recently Updated</option>
                    <option value="updated-old">Oldest Updated</option>
                </select>
                <button class="btn btn-success" onclick="exportToCSV()">Export CSV</button>
            </div>
        </div>

        <div class="table-card">
            <div class="table-responsive">
                <table id="inventoryTable">
                    <thead>
                        <tr>
                            <th onclick="sortTable(0)">Number <span class="sort-icon">↕</span></th>
                            <th onclick="sortTable(1)">Product Name <span class="sort-icon">↕</span></th>
                            <th onclick="sortTable(2)">Current Stock <span class="sort-icon">↕</span></th>
                            <th onclick="sortTable(3)">Unit Price <span class="sort-icon">↕</span></th>
                            <th onclick="sortTable(4)">Stock Value <span class="sort-icon">↕</span></th>
                            <th onclick="sortTable(5)">Movement <span class="sort-icon">↕</span></th>
                            <th onclick="sortTable(6)">Last Updated <span class="sort-icon">↕</span></th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $rowNumber = 1;
                        foreach($products as $row): 
                            $stock = (int)$row['stock'];
                            $stockClass = $stock == 0 ? 'out-stock' : ($stock <= 5 ? 'low-stock' : 'good-stock');
                            $movement = (int)$row['total_inventory_movement'];
                            $movementClass = $movement > 0 ? 'inventory-positive' : ($movement < 0 ? 'inventory-negative' : 'inventory-neutral');
                            $stockValue = $stock * $row['price'];
                        ?>
                        <tr data-stock="<?= $stock ?>" data-value="<?= $stockValue ?>" data-updated="<?= $row['last_updated'] ?>" data-product-id="<?= $row['product_id'] ?>">
                            <td><strong class="row-number"><?= $rowNumber ?></strong></td>
                            <td class="product-name"><?= htmlspecialchars($row['product_name']) ?></td>
                            <td><span class="stock-badge <?= $stockClass ?>"><?= $stock ?> units</span></td>
                            <td>₱<?= number_format($row['price'], 2) ?></td>
                            <td class="stock-value">₱<?= number_format($stockValue, 2) ?></td>
                            <td class="inventory-qty <?= $movementClass ?>">
                                <?= $movement > 0 ? '+' : '' ?><?= $movement ?>
                            </td>
                            <td>
                                <?php if ($row['last_updated']): ?>
                                    <div><?= date('M j, Y', strtotime($row['last_updated'])) ?></div>
                                    <div class="last-updated"><?= date('g:i A', strtotime($row['last_updated'])) ?></div>
                                <?php else: ?>
                                    <span style="color:#999;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="actions">
                                    <a href="update_stock.php?id=<?= $row['product_id'] ?>" class="btn btn-info btn-sm">Update</a>
                                    <a href="delete_product.php?id=<?= $row['product_id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this product?')">Delete</a>
                                </div>
                            </td>
                        </tr>
                        <?php 
                        $rowNumber++;
                        endforeach; ?>
                        <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="8" class="empty-state">
                                <div class="icon">📦</div>
                                <div>No products in inventory</div>
                                <div style="font-size:14px;margin-top:5px;">Add products to start managing inventory</div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="pagination">
                <div class="pagination-info" id="paginationInfo"></div>
                <div id="paginationControls"></div>
            </div>
        </div>
    </div>

    <!-- Transactions Tab -->
    <div id="transactions-tab" class="tab-content">
        <div class="recent-transactions">
            <h3>Recent Inventory Transactions</h3>
            <?php if ($recent_transactions->num_rows > 0): ?>
                <?php while($transaction = $recent_transactions->fetch_assoc()): ?>
                <div class="transaction-item">
                    <div>
                        <strong><?= htmlspecialchars($transaction['product_name']) ?></strong>
                        <div class="last-updated">Product ID: <?= $transaction['product_id'] ?></div>
                    </div>
                    <div style="text-align:right;">
                        <span class="transaction-type <?= $transaction['quantity'] > 0 ? 'transaction-restock' : 'transaction-sale' ?>">
                            <?= $transaction['quantity'] > 0 ? '+' : '' ?><?= $transaction['quantity'] ?> units
                        </span>
                        <div class="last-updated">
                            <?= date('M j, Y g:i A', strtotime($transaction['last_updated'])) ?>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="icon">📋</div>
                    <div>No inventory transactions yet</div>
                    <div style="font-size:14px;margin-top:5px;">Transactions will appear here when you make sales or restock products</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
const rowsPerPage = 15;
let currentPage = 1;

// Tab functionality
function showTab(tabName) {
    document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    
    document.querySelector(`[onclick="showTab('${tabName}')"]`).classList.add('active');
    document.getElementById(tabName + '-tab').classList.add('active');
}

// Search
document.getElementById("search").addEventListener("input", function() {
    currentPage = 1;
    updatePagination();
});

// Filter dropdown
document.getElementById("filter").addEventListener("change", function() {
    currentPage = 1;
    updatePagination();
});

// Sort dropdown
document.getElementById("sort").addEventListener("change", function() {
    const sortBy = this.value;
    const table = document.getElementById("inventoryTable");
    const rows = Array.from(table.querySelectorAll('tbody tr[data-stock]'));
    
    rows.sort((a,b) => {
        let valA, valB;
        if (sortBy.includes('name')) {
            valA = a.cells[1].textContent.toLowerCase();
            valB = b.cells[1].textContent.toLowerCase();
            return sortBy === 'name-asc' ? valA.localeCompare(valB) : valB.localeCompare(valA);
        } else if (sortBy.includes('stock')) {
            valA = parseInt(a.getAttribute('data-stock'));
            valB = parseInt(b.getAttribute('data-stock'));
            return sortBy === 'stock-high' ? valB - valA : valA - valB;
        } else if (sortBy.includes('value')) {
            valA = parseFloat(a.getAttribute('data-value'));
            valB = parseFloat(a.getAttribute('data-value'));
            return sortBy === 'value-high' ? valB - valA : valA - valB;
        } else if (sortBy.includes('updated')) {
            valA = a.getAttribute('data-updated') || '';
            valB = b.getAttribute('data-updated') || '';
            return sortBy === 'updated-new' ? valB.localeCompare(valA) : valA.localeCompare(valB);
        }
    });
    
    rows.forEach(row => table.querySelector('tbody').appendChild(row));
    updateRowNumbers();
    updatePagination();
});

// Sort table by column
let sortDirection = {};
function sortTable(colIndex) {
    const table = document.getElementById("inventoryTable");
    const rows = Array.from(table.rows).slice(1);
    const isAsc = sortDirection[colIndex] !== 'asc';
    
    rows.sort((a,b)=>{
        let x=a.cells[colIndex].innerText.trim().toLowerCase();
        let y=b.cells[colIndex].innerText.trim().toLowerCase();
        if(!isNaN(x)&&!isNaN(y)) return isAsc?x-y:y-x;
        return isAsc?x.localeCompare(y,'en',{numeric:true}):y.localeCompare(x,'en',{numeric:true});
    });
    
    rows.forEach(r=>table.tBodies[0].appendChild(r));
    sortDirection[colIndex]=isAsc?'asc':'desc';
    document.querySelectorAll('.sort-icon').forEach((icon,i)=>{
        icon.textContent=i===colIndex?(isAsc?'↑':'↓'):'↕';
    });
    updateRowNumbers();
    updatePagination();
}

// Update row numbers after sorting/filtering
function updateRowNumbers() {
    const allRows = Array.from(document.querySelectorAll("#inventoryTable tbody tr[data-stock]"));
    allRows.forEach((row, index) => {
        const numberCell = row.querySelector('.row-number');
        if (numberCell) {
            numberCell.textContent = index + 1;
        }
    });
}

// Export to CSV
function exportToCSV() {
    const table = document.getElementById("inventoryTable");
    const rows = Array.from(table.querySelectorAll('tr'));
    let csvContent = '';
    
    rows.forEach(row => {
        const cols = Array.from(row.querySelectorAll('th, td'));
        const rowData = cols.slice(0, -1).map(col => {
            let text = col.innerText.replace(/"/g, '""');
            return `"${text}"`;
        }).join(',');
        csvContent += rowData + '\n';
    });
    
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `inventory_${new Date().toISOString().split('T')[0]}.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

// Pagination
function updatePagination(){
    const searchVal=document.getElementById("search").value.toLowerCase();
    const filterVal=document.getElementById("filter").value;
    const allRows=Array.from(document.querySelectorAll("#inventoryTable tbody tr[data-stock]"));

    const rows=allRows.filter(row=>{
        let matchSearch=row.innerText.toLowerCase().includes(searchVal);
        if(!matchSearch) return false;
        
        const stock=parseInt(row.getAttribute("data-stock"));
        const stockValue=parseFloat(row.getAttribute("data-value"));
        const movement=parseInt(row.cells[5].innerText.replace(/[+,]/g, '')) || 0;
        
        if(filterVal==="low"&&stock>5) return false;
        if(filterVal==="out"&&stock!==0) return false;
        if(filterVal==="recent"){
            let updated=row.getAttribute("data-updated");
            if(!updated||updated==="") return false;
        }
        if(filterVal==="high-value"&&stockValue<1000) return false;
        if(filterVal==="no-movement"&&movement!==0) return false;
        
        return true;
    });

    const pageCount=Math.ceil(rows.length/rowsPerPage);
    const paginationControls=document.getElementById("paginationControls");
    const paginationInfo=document.getElementById("paginationInfo");
    
    // Update info
    const start = (currentPage - 1) * rowsPerPage + 1;
    const end = Math.min(currentPage * rowsPerPage, rows.length);
    paginationInfo.textContent = rows.length > 0 ? `Showing ${start} to ${end} of ${rows.length} products` : 'No products found';
    
    paginationControls.innerHTML="";
    if(pageCount<=1){showPage(1,rows);return;}

    // Previous button
    let prev=document.createElement("button");
    prev.innerText="← Previous";
    prev.disabled=currentPage===1;
    prev.onclick=()=>{if(currentPage>1){currentPage--;updatePagination();}};
    paginationControls.appendChild(prev);

    // Page numbers
    const maxButtons = 5;
    let startPage = Math.max(1, currentPage - 2);
    let endPage = Math.min(pageCount, startPage + maxButtons - 1);
    
    if (endPage - startPage < maxButtons - 1) {
        startPage = Math.max(1, endPage - maxButtons + 1);
    }

    for(let i=startPage; i<=endPage; i++){
        let btn=document.createElement("button");
        btn.innerText=i;
        btn.className=i===currentPage?"active":"";
        btn.onclick=(function(page){
            return function(){currentPage=page;updatePagination();};
        })(i);
        paginationControls.appendChild(btn);
    }

    // Next button
    let next=document.createElement("button");
    next.innerText="Next →";
    next.disabled=currentPage===pageCount;
    next.onclick=()=>{if(currentPage<pageCount){currentPage++;updatePagination();}};
    paginationControls.appendChild(next);

    showPage(currentPage,rows);
}

function showPage(page,rows){
    document.querySelectorAll("#inventoryTable tbody tr[data-stock]").forEach(r=>r.style.display="none");
    const start=(page-1)*rowsPerPage;
    const end=start+rowsPerPage;
    rows.slice(start,end).forEach(r=>r.style.display="");
}

// Initialize
updatePagination();

// Load saved theme
document.addEventListener('DOMContentLoaded', function() {
    const savedTheme = localStorage.getItem('selectedTheme') || 'blue';
    if (savedTheme !== 'blue') {
        document.body.classList.add('theme-' + savedTheme);
    }
});
</script>
</body>
</html>