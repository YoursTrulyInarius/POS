<?php 
include "db.php"; 

// Get sales report data with additional statistics - FIXED to use correct tables
$res = $conn->query("
    SELECT 
        s.sale_id,
        s.sale_date,
        u.username as cashier,
        s.total_amount,
        COUNT(si.sale_item_id) as total_items
    FROM sales s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN sale_items si ON s.sale_id = si.sale_id
    GROUP BY s.sale_id, s.sale_date, u.username, s.total_amount
    ORDER BY s.sale_date DESC
");

// Calculate summary statistics - FIXED to use correct tables
$stats = $conn->query("
    SELECT 
        COUNT(*) as total_transactions,
        COALESCE(SUM(si.quantity), 0) as total_items_sold,
        COALESCE(SUM(s.total_amount), 0) as total_revenue,
        AVG(s.total_amount) as avg_transaction_value,
        MAX(s.total_amount) as highest_sale,
        MIN(s.total_amount) as lowest_sale
    FROM sales s
    LEFT JOIN sale_items si ON s.sale_id = si.sale_id
")->fetch_assoc();

// Get today's stats - FIXED to use correct tables
$today_stats = $conn->query("
    SELECT 
        COUNT(*) as today_transactions,
        COALESCE(SUM(si.quantity), 0) as today_items,
        COALESCE(SUM(s.total_amount), 0) as today_revenue
    FROM sales s
    LEFT JOIN sale_items si ON s.sale_id = si.sale_id
    WHERE DATE(s.sale_date) = CURDATE()
")->fetch_assoc();

// Get top performing days - FIXED to use correct tables
$top_days = $conn->query("
    SELECT 
        DATE(s.sale_date) as sale_day,
        COUNT(*) as transaction_count,
        SUM(s.total_amount) as day_revenue
    FROM sales s
    GROUP BY DATE(s.sale_date)
    ORDER BY day_revenue DESC
    LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title>Sales Report Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

/* Theme System */
:root {
    --primary-color: #007bff;
    --primary-dark: #0056b3;
    --primary-light: #e8f0fe;
    --secondary-color: #fff;
    --text-color: #333;
    --bg-color: #f4f6f9;
    --success: #28a745;
    --danger: #dc3545;
    --warning: #ffc107;
    --info: #17a2b8;
    --light: #f8f9fa;
    --dark: #343a40;
    --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
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
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
    background: var(--bg-color);
    color: #333;
    line-height: 1.6;
    transition: all 0.3s ease;
}

.container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

.header {
    background: white;
    border-radius: var(--border-radius);
    padding: 25px;
    margin-bottom: 25px;
    box-shadow: var(--card-shadow);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.header-content h1 {
    color: var(--primary-color);
    font-size: 32px;
    font-weight: 700;
    margin-bottom: 5px;
}

.header-content p {
    color: #666;
    font-size: 16px;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 20px;
    background: var(--primary-color);
    color: white;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 500;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    font-size: 14px;
}

.btn:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: var(--border-radius);
    padding: 25px;
    box-shadow: var(--card-shadow);
    position: relative;
    overflow: hidden;
    transition: transform 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-3px);
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
}

.stat-card.revenue::before {
    background: linear-gradient(90deg, var(--success), #20c997);
}

.stat-card.transactions::before {
    background: linear-gradient(90deg, var(--primary-color), var(--info));
}

.stat-card.items::before {
    background: linear-gradient(90deg, var(--warning), #fd7e14);
}

.stat-card.average::before {
    background: linear-gradient(90deg, var(--info), #6f42c1);
}

.stat-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
}

.stat-title {
    font-size: 14px;
    font-weight: 600;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-icon {
    font-size: 24px;
    opacity: 0.7;
}

.stat-value {
    font-size: 32px;
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 8px;
}

.stat-subtitle {
    font-size: 14px;
    color: #666;
}

.content-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 25px;
    margin-bottom: 25px;
}

.report-card {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--card-shadow);
    overflow: hidden;
}

.card-header {
    padding: 25px;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-title {
    color: var(--dark);
    font-size: 24px;
    font-weight: 600;
}

.card-subtitle {
    color: #666;
    font-size: 14px;
    margin-top: 5px;
}

.table-responsive {
    overflow-x: auto;
    max-height: 600px;
    overflow-y: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

thead th {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    color: white;
    padding: 18px 15px;
    font-weight: 600;
    text-align: left;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    position: sticky;
    top: 0;
    z-index: 10;
}

tbody td {
    padding: 15px;
    border-bottom: 1px solid #e9ecef;
    vertical-align: middle;
}

tbody tr:hover {
    background: #f8f9fa;
}

tbody tr:last-child td {
    border-bottom: none;
}

.sale-id {
    font-weight: 700;
    color: var(--primary-color);
    font-size: 16px;
}

.sale-date {
    color: #666;
}

.sale-time {
    font-size: 12px;
    color: #999;
    margin-top: 2px;
}

.cashier-name {
    font-weight: 600;
    color: var(--dark);
}

.amount {
    font-weight: 700;
    font-size: 16px;
    color: var(--success);
}

.items-count {
    background: #e9ecef;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    color: var(--primary-color);
    cursor: pointer;
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.items-count:hover {
    background: var(--primary-color);
    color: white;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}

.top-days-card {
    background: white;
    border-radius: var(--border-radius);
    padding: 25px;
    box-shadow: var(--card-shadow);
}

.top-day-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 0;
    border-bottom: 1px solid #e9ecef;
}

.top-day-item:last-child {
    border-bottom: none;
}

.day-date {
    font-weight: 600;
    color: var(--dark);
}

.day-stats {
    text-align: right;
}

.day-revenue {
    font-weight: 700;
    color: var(--success);
    font-size: 16px;
}

.day-transactions {
    font-size: 12px;
    color: #666;
    margin-top: 2px;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #6c757d;
}

.empty-state .icon {
    font-size: 64px;
    margin-bottom: 20px;
    opacity: 0.3;
}

.search-filter {
    padding: 20px;
    background: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
}

.search-box {
    position: relative;
    max-width: 300px;
}

.search-box input {
    width: 100%;
    padding: 12px 16px 12px 45px;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    font-size: 14px;
    transition: border-color 0.3s ease;
}

.search-box input:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
}

.search-box::before {
    content: "🔍";
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 16px;
    z-index: 1;
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(5px);
    animation: fadeIn 0.3s ease;
}

.modal-content {
    background-color: white;
    margin: 3% auto;
    padding: 0;
    border-radius: 15px;
    width: 90%;
    max-width: 800px;
    max-height: 85vh;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    animation: slideIn 0.3s ease;
    display: flex;
    flex-direction: column;
}

.modal-header {
    background: var(--primary-color);
    color: white;
    padding: 20px 25px;
    border-radius: 15px 15px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    font-size: 24px;
    font-weight: 600;
}

.modal-body {
    padding: 25px;
    overflow-y: auto;
    flex: 1;
    min-height: 0;
}

.close {
    color: white;
    font-size: 24px;
    font-weight: bold;
    cursor: pointer;
    background: none;
    border: none;
    padding: 5px 10px;
    border-radius: 50%;
    transition: 0.3s;
}

.close:hover {
    background: rgba(255, 255, 255, 0.2);
}

.sale-info {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    background: var(--primary-light);
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 25px;
}

.info-item {
    text-align: center;
}

.info-label {
    font-size: 12px;
    font-weight: 600;
    color: #666;
    text-transform: uppercase;
    margin-bottom: 5px;
}

.info-value {
    font-size: 18px;
    font-weight: 700;
    color: var(--primary-color);
}

.items-table {
    background: white;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.items-table thead th {
    background: var(--dark);
    color: white;
    padding: 15px;
    font-weight: 600;
    text-align: left;
}

.items-table tbody td {
    padding: 15px;
    border-bottom: 1px solid #e9ecef;
}

.items-table tbody tr:last-child td {
    border-bottom: none;
}

.item-name {
    font-weight: 600;
    color: var(--dark);
}

.item-price {
    color: var(--primary-color);
    font-weight: 600;
}

.item-total {
    color: var(--success);
    font-weight: 700;
    font-size: 16px;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideIn {
    from { transform: translateY(-50px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

@media (max-width: 1024px) {
    .content-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .container {
        padding: 15px;
    }
    
    .header {
        flex-direction: column;
        text-align: center;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .stat-value {
        font-size: 24px;
    }
    
    .table-responsive {
        font-size: 14px;
    }
    
    thead th, tbody td {
        padding: 10px 8px;
    }
    
    .modal-content {
        width: 95%;
        margin: 10% auto;
    }
    
    .sale-info {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <div class="header-content">
            <h1>Sales Report</h1>
            <p>Comprehensive sales transaction analysis</p>
        </div>
        <a href="dashboard.php" class="btn">← Return to Dashboard</a>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card revenue">
            <div class="stat-header">
                <div class="stat-title">Total Revenue</div>
                <div class="stat-icon">💰</div>
            </div>
            <div class="stat-value">₱<?= number_format($stats['total_revenue'] ?? 0, 2) ?></div>
            <div class="stat-subtitle">All-time sales revenue</div>
        </div>

        <div class="stat-card transactions">
            <div class="stat-header">
                <div class="stat-title">Total Transactions</div>
                <div class="stat-icon">🧾</div>
            </div>
            <div class="stat-value"><?= number_format($stats['total_transactions'] ?? 0) ?></div>
            <div class="stat-subtitle">Completed sales</div>
        </div>

        <div class="stat-card items">
            <div class="stat-header">
                <div class="stat-title">Items Sold</div>
                <div class="stat-icon">📦</div>
            </div>
            <div class="stat-value"><?= number_format($stats['total_items_sold'] ?? 0) ?></div>
            <div class="stat-subtitle">Total products sold</div>
        </div>

        <div class="stat-card average">
            <div class="stat-header">
                <div class="stat-title">Average Sale</div>
                <div class="stat-icon">📊</div>
            </div>
            <div class="stat-value">₱<?= number_format($stats['avg_transaction_value'] ?? 0, 2) ?></div>
            <div class="stat-subtitle">Per transaction average</div>
        </div>
    </div>

    <div class="content-grid">
        <!-- Main Sales Report Table -->
        <div class="report-card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Transaction History</h2>
                    <p class="card-subtitle">Detailed sales records</p>
                </div>
            </div>
            
            <div class="search-filter">
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="Search transactions...">
                </div>
            </div>
            
            <div class="table-responsive">
                <table id="salesTable">
                    <thead>
                        <tr>
                            <th>Sale ID</th>
                            <th>Date & Time</th>
                            <th>Cashier</th>
                            <th>Items</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($res && $res->num_rows > 0): ?>
                            <?php while($row = $res->fetch_assoc()): ?>
                            <tr>
                                <td class="sale-id">#<?= $row['sale_id'] ?></td>
                                <td>
                                    <div class="sale-date"><?= date('M j, Y', strtotime($row['sale_date'])) ?></div>
                                    <div class="sale-time"><?= date('g:i A', strtotime($row['sale_date'])) ?></div>
                                </td>
                                <td class="cashier-name"><?= htmlspecialchars($row['cashier']) ?></td>
                                <td>
                                    <span class="items-count" onclick="showSaleItems(<?= $row['sale_id'] ?>, '<?= date('M j, Y g:i A', strtotime($row['sale_date'])) ?>', '<?= htmlspecialchars($row['cashier']) ?>', <?= $row['total_amount'] ?>)">
                                        <?= $row['total_items'] ?> items
                                    </span>
                                </td>
                                <td class="amount">₱<?= number_format($row['total_amount'], 2) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="empty-state">
                                    <div class="icon">📋</div>
                                    <div>No sales reports found</div>
                                    <div style="font-size: 14px; margin-top: 10px;">Start making sales to see reports here</div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Top Performing Days -->
        <div class="top-days-card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Top Sales Days</h2>
                    <p class="card-subtitle">Best performing days by revenue</p>
                </div>
            </div>
            
            <?php if ($top_days && $top_days->num_rows > 0): ?>
                <?php $rank = 1; while($day = $top_days->fetch_assoc()): ?>
                <div class="top-day-item">
                    <div>
                        <div class="day-date"><?= date('M j, Y', strtotime($day['sale_day'])) ?></div>
                        <div style="font-size: 12px; color: #666;">#<?= $rank ?> Best Day</div>
                    </div>
                    <div class="day-stats">
                        <div class="day-revenue">₱<?= number_format($day['day_revenue'], 2) ?></div>
                        <div class="day-transactions"><?= $day['transaction_count'] ?> transactions</div>
                    </div>
                </div>
                <?php $rank++; endwhile; ?>
            <?php else: ?>
                <div class="empty-state" style="padding: 40px 20px;">
                    <div style="font-size: 32px; margin-bottom: 10px;">📅</div>
                    <div>No data available</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Sale Items Modal -->
<div id="saleItemsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Sale Details</h3>
            <button class="close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="sale-info" id="saleInfo">
                <!-- Sale info will be populated here -->
            </div>
            <div class="items-table">
                <table>
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Price</th>
                            <th>Quantity</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody id="itemsTableBody">
                        <!-- Items will be populated here -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Load saved theme
document.addEventListener('DOMContentLoaded', function() {
    const savedTheme = localStorage.getItem('selectedTheme') || 'blue';
    if (savedTheme !== 'blue') {
        document.body.classList.add('theme-' + savedTheme);
    }
});

// Search functionality
document.getElementById('searchInput').addEventListener('keyup', function() {
    const searchValue = this.value.toLowerCase();
    const rows = document.querySelectorAll('#salesTable tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchValue) ? '' : 'none';
    });
});

// Add some interactive effects
document.querySelectorAll('.stat-card').forEach(card => {
    card.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-5px)';
    });
    
    card.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0)';
    });
});

// Modal functionality
function showSaleItems(saleId, saleDate, cashier, totalAmount) {
    // Update modal title
    document.getElementById('modalTitle').textContent = `Sale #${saleId} - Items Purchased`;
    
    // Update sale info
    document.getElementById('saleInfo').innerHTML = `
        <div class="info-item">
            <div class="info-label">Sale ID</div>
            <div class="info-value">#${saleId}</div>
        </div>
        <div class="info-item">
            <div class="info-label">Date & Time</div>
            <div class="info-value">${saleDate}</div>
        </div>
        <div class="info-item">
            <div class="info-label">Cashier</div>
            <div class="info-value">${cashier}</div>
        </div>
        <div class="info-item">
            <div class="info-label">Total Amount</div>
            <div class="info-value">₱${parseFloat(totalAmount).toFixed(2)}</div>
        </div>
    `;
    
    // Show loading state
    document.getElementById('itemsTableBody').innerHTML = `
        <tr>
            <td colspan="4" style="text-align: center; padding: 40px; color: #666;">
                <div style="font-size: 18px; margin-bottom: 10px;">Loading items...</div>
                <div style="font-size: 14px;">Please wait</div>
            </td>
        </tr>
    `;
    
    // Show modal
    document.getElementById('saleItemsModal').style.display = 'block';
    
    // Fetch items via AJAX
    fetch(`get_sale_items.php?sale_id=${saleId}`)
        .then(response => response.json())
        .then(data => {
            let itemsHtml = '';
            
            if (data.success && data.items.length > 0) {
                data.items.forEach(item => {
                    const itemTotal = parseFloat(item.price) * parseInt(item.quantity);
                    itemsHtml += `
                        <tr>
                            <td class="item-name">${item.product_name}</td>
                            <td class="item-price">₱${parseFloat(item.price).toFixed(2)}</td>
                            <td style="text-align: center; font-weight: 600;">${item.quantity}</td>
                            <td class="item-total">₱${itemTotal.toFixed(2)}</td>
                        </tr>
                    `;
                });
            } else {
                itemsHtml = `
                    <tr>
                        <td colspan="4" style="text-align: center; padding: 40px; color: #666;">
                            <div style="font-size: 18px; margin-bottom: 10px;">No items found</div>
                            <div style="font-size: 14px;">This sale may have been deleted or corrupted</div>
                        </td>
                    </tr>
                `;
            }
            
            document.getElementById('itemsTableBody').innerHTML = itemsHtml;
        })
        .catch(error => {
            console.error('Error fetching sale items:', error);
            document.getElementById('itemsTableBody').innerHTML = `
                <tr>
                    <td colspan="4" style="text-align: center; padding: 40px; color: #dc3545;">
                        <div style="font-size: 18px; margin-bottom: 10px;">Error loading items</div>
                        <div style="font-size: 14px;">Please try again later</div>
                    </td>
                </tr>
            `;
        });
}

function closeModal() {
    document.getElementById('saleItemsModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('saleItemsModal');
    if (event.target === modal) {
        closeModal();
    }
}

// Close modal with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeModal();
    }
});
</script>
</body>
</html>