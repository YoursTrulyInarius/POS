<?php
include "db.php";

// Determine filter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Build WHERE clause based on filter
$where = "";
$title = "All Sales";
if ($filter == "today") {
    $where = "WHERE DATE(s.sale_date) = CURDATE()";
    $title = "Today's Sales";
} elseif ($filter == "month") {
    $where = "WHERE MONTH(s.sale_date) = MONTH(CURDATE()) AND YEAR(s.sale_date) = YEAR(CURDATE())";
    $title = "This Month's Sales";
} elseif ($filter == "year") {
    $where = "WHERE YEAR(s.sale_date) = YEAR(CURDATE())";
    $title = "This Year's Sales";
}

// Sales today
$today = $conn->query("
    SELECT COUNT(*) AS total_sales, COALESCE(SUM(total_amount),0) AS total_amount
    FROM sales WHERE DATE(sale_date)=CURDATE()
")->fetch_assoc();

// Sales this month
$month = $conn->query("
    SELECT COUNT(*) AS total_sales, COALESCE(SUM(total_amount),0) AS total_amount
    FROM sales WHERE MONTH(sale_date)=MONTH(CURDATE()) AND YEAR(sale_date)=YEAR(CURDATE())
")->fetch_assoc();

// Sales this year
$year = $conn->query("
    SELECT COUNT(*) AS total_sales, COALESCE(SUM(total_amount),0) AS total_amount
    FROM sales WHERE YEAR(sale_date)=YEAR(CURDATE())
")->fetch_assoc();

// Sales overall
$all = $conn->query("
    SELECT COUNT(*) AS total_sales, COALESCE(SUM(total_amount),0) AS total_amount
    FROM sales
")->fetch_assoc();

// Full sales history (with filter)
$res = $conn->query("
    SELECT s.sale_id, s.sale_date, u.username, s.total_amount
    FROM sales s
    JOIN users u ON s.user_id = u.id
    $where
    ORDER BY s.sale_date DESC
    LIMIT 50
");

// Calculate percentage changes (simplified - you might want more complex logic)
$yesterday = $conn->query("
    SELECT COALESCE(SUM(total_amount),0) AS total_amount
    FROM sales WHERE DATE(sale_date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
")->fetch_assoc();

$lastMonth = $conn->query("
    SELECT COALESCE(SUM(total_amount),0) AS total_amount
    FROM sales WHERE MONTH(sale_date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) 
    AND YEAR(sale_date) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title>Sales History Dashboard</title>
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
    --card-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
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

.revenue-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.revenue-card {
    background: white;
    border-radius: var(--border-radius);
    padding: 25px;
    box-shadow: var(--card-shadow);
    position: relative;
    overflow: hidden;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    color: inherit;
}

.revenue-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

.revenue-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary-color), var(--info));
}

.revenue-card.today::before {
    background: linear-gradient(90deg, var(--success), #20c997);
}

.revenue-card.month::before {
    background: linear-gradient(90deg, var(--primary-color), var(--info));
}

.revenue-card.year::before {
    background: linear-gradient(90deg, var(--warning), #fd7e14);
}

.revenue-card.all::before {
    background: linear-gradient(90deg, var(--dark), #6c757d);
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
}

.card-title {
    font-size: 16px;
    font-weight: 600;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.card-icon {
    font-size: 24px;
    opacity: 0.7;
}

.revenue-amount {
    font-size: 36px;
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 8px;
}

.sales-count {
    font-size: 14px;
    color: #666;
    margin-bottom: 12px;
}

.trend {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 12px;
    font-weight: 500;
}

.trend.positive {
    color: var(--success);
}

.trend.negative {
    color: var(--danger);
}

.trend.neutral {
    color: #6c757d;
}

.filter-tabs {
    background: white;
    border-radius: var(--border-radius);
    padding: 15px;
    margin-bottom: 25px;
    box-shadow: var(--card-shadow);
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.filter-tab {
    padding: 10px 20px;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    text-decoration: none;
    color: #666;
    font-weight: 500;
    transition: all 0.3s ease;
    cursor: pointer;
}

.filter-tab.active,
.filter-tab:hover {
    border-color: var(--primary-color);
    background: var(--primary-color);
    color: white;
}

.sales-table-card {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--card-shadow);
    overflow: hidden;
}

.table-header {
    padding: 25px;
    border-bottom: 1px solid #e9ecef;
}

.table-header h2 {
    color: var(--dark);
    font-size: 24px;
    font-weight: 600;
}

.table-header p {
    color: #666;
    margin-top: 5px;
}

.table-responsive {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

thead th {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    color: white;
    padding: 18px 20px;
    font-weight: 600;
    text-align: left;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

tbody td {
    padding: 18px 20px;
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

.summary-footer {
    background: #f8f9fa;
    padding: 20px 25px;
    border-top: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.summary-text {
    color: #666;
    font-size: 14px;
}

.summary-amount {
    font-size: 20px;
    font-weight: 700;
    color: var(--success);
}

@media (max-width: 768px) {
    .container {
        padding: 15px;
    }
    
    .header {
        flex-direction: column;
        text-align: center;
    }
    
    .revenue-cards {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .filter-tabs {
        justify-content: center;
    }
    
    .filter-tab {
        font-size: 14px;
        padding: 8px 16px;
    }
    
    .revenue-amount {
        font-size: 28px;
    }
    
    .table-responsive {
        font-size: 14px;
    }
    
    thead th, tbody td {
        padding: 12px 15px;
    }
}

@media (max-width: 480px) {
    .revenue-cards {
        grid-template-columns: 1fr;
    }
    
    .filter-tabs {
        flex-direction: column;
    }
    
    .summary-footer {
        flex-direction: column;
        text-align: center;
    }
}

</style>
</head>
<body>
<div class="container">
    <div class="header">
        <div class="header-content">
            <h1>Sales Dashboard</h1>
            <p>Track your revenue and sales performance</p>
        </div>
        <a href="dashboard.php" class="btn">← Return to Dashboard</a>
    </div>

    <!-- Revenue Cards -->
    <div class="revenue-cards">
        <a href="?filter=today" class="revenue-card today">
            <div class="card-header">
                <div class="card-title">Today's Revenue</div>
                <div class="card-icon">📊</div>
            </div>
            <div class="revenue-amount">₱<?= number_format($today['total_amount'], 2) ?></div>
            <div class="sales-count"><?= $today['total_sales'] ?> sales today</div>
            <div class="trend <?= $today['total_amount'] > $yesterday['total_amount'] ? 'positive' : ($today['total_amount'] < $yesterday['total_amount'] ? 'negative' : 'neutral') ?>">
                <?php 
                if ($today['total_amount'] > $yesterday['total_amount']) {
                    echo "↗ Higher than yesterday";
                } elseif ($today['total_amount'] < $yesterday['total_amount']) {
                    echo "↘ Lower than yesterday";
                } else {
                    echo "→ Same as yesterday";
                }
                ?>
            </div>
        </a>

        <a href="?filter=month" class="revenue-card month">
            <div class="card-header">
                <div class="card-title">Monthly Revenue</div>
                <div class="card-icon">📈</div>
            </div>
            <div class="revenue-amount">₱<?= number_format($month['total_amount'], 2) ?></div>
            <div class="sales-count"><?= $month['total_sales'] ?> sales this month</div>
            <div class="trend <?= $month['total_amount'] > $lastMonth['total_amount'] ? 'positive' : ($month['total_amount'] < $lastMonth['total_amount'] ? 'negative' : 'neutral') ?>">
                <?php 
                if ($month['total_amount'] > $lastMonth['total_amount']) {
                    echo "↗ Higher than last month";
                } elseif ($month['total_amount'] < $lastMonth['total_amount']) {
                    echo "↘ Lower than last month";
                } else {
                    echo "→ Same as last month";
                }
                ?>
            </div>
        </a>

        <a href="?filter=year" class="revenue-card year">
            <div class="card-header">
                <div class="card-title">Annual Revenue</div>
                <div class="card-icon">🎯</div>
            </div>
            <div class="revenue-amount">₱<?= number_format($year['total_amount'], 2) ?></div>
            <div class="sales-count"><?= $year['total_sales'] ?> sales this year</div>
            <div class="trend neutral">
                <?= date('Y') ?> performance
            </div>
        </a>

        <a href="?filter=all" class="revenue-card all">
            <div class="card-header">
                <div class="card-title">Total Revenue</div>
                <div class="card-icon">💰</div>
            </div>
            <div class="revenue-amount">₱<?= number_format($all['total_amount'], 2) ?></div>
            <div class="sales-count"><?= $all['total_sales'] ?> total sales</div>
            <div class="trend neutral">
                All time performance
            </div>
        </a>
    </div>

    <!-- Filter Tabs -->
    <div class="filter-tabs">
        <a href="?filter=today" class="filter-tab <?= $filter == 'today' ? 'active' : '' ?>">Today</a>
        <a href="?filter=month" class="filter-tab <?= $filter == 'month' ? 'active' : '' ?>">This Month</a>
        <a href="?filter=year" class="filter-tab <?= $filter == 'year' ? 'active' : '' ?>">This Year</a>
        <a href="?filter=all" class="filter-tab <?= $filter == 'all' ? 'active' : '' ?>">All Time</a>
    </div>

    <!-- Sales Table -->
    <div class="sales-table-card">
        <div class="table-header">
            <h2><?= $title ?></h2>
            <p>Detailed transaction history</p>
        </div>
        
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Sale ID</th>
                        <th>Date & Time</th>
                        <th>Cashier</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($res && $res->num_rows > 0): ?>
                        <?php 
                        $total_filtered = 0;
                        $count_filtered = 0;
                        while($row = $res->fetch_assoc()): 
                            $total_filtered += $row['total_amount'];
                            $count_filtered++;
                        ?>
                        <tr>
                            <td class="sale-id">#<?= $row['sale_id'] ?></td>
                            <td>
                                <div class="sale-date"><?= date('M j, Y', strtotime($row['sale_date'])) ?></div>
                                <div class="sale-time"><?= date('g:i A', strtotime($row['sale_date'])) ?></div>
                            </td>
                            <td class="cashier-name"><?= htmlspecialchars($row['username']) ?></td>
                            <td class="amount">₱<?= number_format($row['total_amount'], 2) ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="empty-state">
                                <div class="icon">📋</div>
                                <div>No sales records found</div>
                                <div style="font-size: 14px; margin-top: 10px;">No transactions match the selected filter</div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($res && $res->num_rows > 0): ?>
        <div class="summary-footer">
            <div class="summary-text">
                Showing <?= $count_filtered ?> transactions
            </div>
            <div class="summary-amount">
                Total: ₱<?= number_format($total_filtered, 2) ?>
            </div>
        </div>
        <?php endif; ?>
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
</script>

</body>
</html>