<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: index.php"); 
    exit();
}

// Include database connection
include_once "db.php";

// Get low stock products (5 or less)
$low_stock_query = "SELECT product_name, stock FROM products WHERE stock <= 5 ORDER BY stock ASC";
$low_stock_result = $conn->query($low_stock_query);
$low_stock_products = [];
if ($low_stock_result) {
    while ($row = $low_stock_result->fetch_assoc()) {
        $low_stock_products[] = $row;
    }
}

// Get basic stats
$total_products = $conn->query("SELECT COUNT(*) as count FROM products")->fetch_assoc()['count'];
$daily_sales = $conn->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM sales WHERE DATE(sale_date) = CURDATE()")->fetch_assoc()['total'];
$total_revenue = $conn->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM sales")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard - POS</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root {
      --primary-color: #1a73e8;
      --primary-dark: #1557b0;
      --primary-light: #e8f0fe;
      --secondary-color: #fff;
      --text-color: #333;
      --bg-color: #f5f8ff;
      --card-shadow: 0 2px 5px rgba(0,0,0,0.1);
      --card-shadow-hover: 0 5px 15px rgba(0,0,0,0.2);
    }

    /* Theme variations */
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
      margin: 0;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
      background: var(--bg-color);
      color: var(--text-color);
      transition: all 0.3s ease;
    }

    /* Header */
    header {
      background: var(--primary-color);
      color: var(--secondary-color);
      padding: 15px 25px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: var(--card-shadow);
    }
    header .brand {
      display: flex;
      align-items: center;
    }
    header img {
      height: 40px;
      margin-right: 10px;
    }
    header h1 {
      font-size: 20px;
      margin: 0;
      font-weight: 600;
    }
    .header-icons {
      display: flex;
      align-items: center;
      gap: 20px;
    }
    .header-icon {
      position: relative;
      cursor: pointer;
      font-size: 18px;
      transition: 0.3s;
      color: var(--secondary-color);
      background: none;
      border: none;
      padding: 8px;
      border-radius: 50%;
    }
    .header-icon:hover {
      background: rgba(255, 255, 255, 0.1);
      color: #bbdefb;
    }
    .notification-badge {
      position: absolute;
      top: -5px;
      right: -5px;
      background: #ff4444;
      color: white;
      border-radius: 50%;
      width: 20px;
      height: 20px;
      font-size: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
    }
    .logout-btn {
      background: var(--secondary-color);
      color: var(--primary-color);
      text-decoration: none;
      padding: 8px 16px;
      border-radius: 6px;
      font-size: 14px;
      font-weight: 600;
      transition: 0.3s;
    }
    .logout-btn:hover {
      background: var(--primary-light);
    }

    /* Store Banner */
    .store-banner {
      background: var(--primary-color);
      color: var(--secondary-color);
      text-align: left;
      padding: 25px;
      border-radius: 12px;
      margin: 20px;
      box-shadow: var(--card-shadow);
    }
    .store-banner h2 {
      margin: 0 0 10px;
      font-size: 28px;
      font-weight: 700;
    }
    .store-banner p {
      margin: 0 0 20px;
      font-size: 16px;
      opacity: 0.9;
    }
    .start-sale-btn {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      background: var(--secondary-color);
      color: var(--primary-color);
      padding: 12px 24px;
      border-radius: 8px;
      font-weight: 600;
      text-decoration: none;
      transition: 0.3s;
      font-size: 16px;
    }
    .start-sale-btn:hover {
      background: var(--primary-light);
      transform: translateY(-2px);
    }

    /* Stats Section */
    .stats {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 20px;
      margin: 20px;
    }
    .stat-card {
      background: var(--secondary-color);
      padding: 25px;
      border-radius: 12px;
      text-align: center;
      box-shadow: var(--card-shadow);
      transition: 0.3s;
    }
    .stat-card:hover {
      transform: translateY(-5px);
      box-shadow: var(--card-shadow-hover);
    }
    .stat-card i {
      font-size: 32px;
      color: var(--primary-color);
      margin-bottom: 15px;
    }
    .stat-card p {
      margin: 5px 0;
      font-size: 14px;
      color: #666;
      font-weight: 500;
    }
    .stat-card h3 {
      margin: 0;
      font-size: 24px;
      color: var(--primary-color);
      font-weight: 700;
    }

    .quick-nav {
      margin: 20px;
    }
    .quick-nav h2 {
      font-size: 22px;
      margin-bottom: 20px;
      color: var(--text-color);
    }
    .quick-links {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
      gap: 20px;
    }
    .quick-link {
      background: var(--secondary-color);
      padding: 25px 40px;
      border: 2px solid var(--primary-color);
      border-radius: 12px;
      text-align: center;
      font-weight: 600;
      color: var(--primary-color);
      display: flex;
      flex-direction: column;
      align-items: center;
      transition: 0.3s;
      font-size: 18px;
      text-decoration: none;
      box-shadow: var(--card-shadow);
      min-height: 120px;
      justify-content: center;
    }
    .quick-link i {
      font-size: 32px;
      margin-bottom: 12px;
    }
    .quick-link:hover {
      background: var(--primary-color);
      color: var(--secondary-color);
      transform: translateY(-3px);
      box-shadow: var(--card-shadow-hover);
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
    }
    .modal-content {
      background-color: var(--secondary-color);
      margin: 5% auto;
      padding: 0;
      border-radius: 15px;
      width: 90%;
      max-width: 500px;
      max-height: 85vh;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
      animation: modalSlideIn 0.3s ease;
      display: flex;
      flex-direction: column;
    }
    .modal-header {
      background: var(--primary-color);
      color: var(--secondary-color);
      padding: 20px 25px;
      border-radius: 15px 15px 0 0;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .modal-header h3 {
      margin: 0;
      font-size: 20px;
      font-weight: 600;
    }
    .modal-body {
      padding: 25px;
      overflow-y: auto;
      flex: 1;
      min-height: 0;
    }
    .close {
      color: var(--secondary-color);
      font-size: 24px;
      font-weight: bold;
      cursor: pointer;
      background: none;
      border: none;
      padding: 5px;
      border-radius: 50%;
      transition: 0.3s;
    }
    .close:hover {
      background: rgba(255, 255, 255, 0.2);
    }

    /* Notification Modal */
    .notification-item {
      display: flex;
      align-items: center;
      padding: 15px;
      border-bottom: 1px solid #e9ecef;
      transition: 0.3s;
    }
    .notification-item:last-child {
      border-bottom: none;
    }
    .notification-item:hover {
      background: var(--primary-light);
    }
    .notification-icon {
      background: #ff4444;
      color: white;
      width: 40px;
      height: 40px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-right: 15px;
      font-size: 16px;
    }
    .notification-content h4 {
      margin: 0 0 5px 0;
      color: var(--text-color);
      font-size: 16px;
    }
    .notification-content p {
      margin: 0;
      color: #666;
      font-size: 14px;
    }

    /* Settings Sections */
    .settings-section {
      margin-bottom: 30px;
      padding-bottom: 25px;
      border-bottom: 1px solid #e9ecef;
    }
    .settings-section:last-child {
      border-bottom: none;
      margin-bottom: 0;
    }
    .settings-section h4 {
      margin: 0 0 15px 0;
      color: var(--text-color);
      font-size: 18px;
      font-weight: 600;
    }
    .form-group {
      margin-bottom: 15px;
    }
    .form-group label {
      display: block;
      margin-bottom: 8px;
      font-weight: 500;
      color: var(--text-color);
    }
    .form-control {
      width: 100%;
      padding: 12px 15px;
      border: 2px solid #e9ecef;
      border-radius: 8px;
      font-size: 14px;
      transition: border-color 0.3s ease;
      box-sizing: border-box;
      font-family: inherit;
    }
    .form-control:focus {
      outline: none;
      border-color: var(--primary-color);
      box-shadow: 0 0 0 3px rgba(26, 115, 232, 0.1);
    }
    .file-upload {
      position: relative;
      display: inline-block;
      cursor: pointer;
      width: 100%;
    }
    .file-upload input[type="file"] {
      position: absolute;
      opacity: 0;
      width: 100%;
      height: 100%;
      cursor: pointer;
    }
    .file-upload-label {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 12px 15px;
      border: 2px dashed #e9ecef;
      border-radius: 8px;
      background: var(--primary-light);
      color: var(--primary-color);
      font-weight: 500;
      transition: all 0.3s ease;
    }
    .file-upload:hover .file-upload-label {
      border-color: var(--primary-color);
      background: var(--primary-color);
      color: var(--secondary-color);
    }
    .btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 12px 20px;
      background: var(--primary-color);
      color: var(--secondary-color);
      border: none;
      border-radius: 8px;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.3s ease;
      font-size: 14px;
      text-decoration: none;
    }
    .btn:hover {
      background: var(--primary-dark);
      transform: translateY(-1px);
    }
    .btn-secondary {
      background: #6c757d;
      color: white;
    }
    .btn-secondary:hover {
      background: #545b62;
    }

    /* Theme Switcher */
    .theme-options {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 12px;
      margin-top: 15px;
    }
    .theme-option {
      padding: 12px;
      border: 2px solid #e9ecef;
      border-radius: 8px;
      cursor: pointer;
      transition: 0.3s;
      text-align: center;
      font-weight: 500;
      font-size: 13px;
    }
    .theme-option:hover {
      border-color: var(--primary-color);
    }
    .theme-option.active {
      border-color: var(--primary-color);
      background: var(--primary-light);
    }
    .theme-preview {
      width: 24px;
      height: 24px;
      border-radius: 50%;
      margin: 0 auto 8px;
    }
    
    /* Theme preview circles with specific colors that won't inherit from current theme */
    .theme-option.theme-blue .theme-preview { 
      background: #1a73e8 !important; 
      background-color: #1a73e8 !important; 
    }
    .theme-option.theme-red .theme-preview { 
      background: #dc3545 !important; 
      background-color: #dc3545 !important; 
    }
    .theme-option.theme-green .theme-preview { 
      background: #28a745 !important; 
      background-color: #28a745 !important; 
    }
    .theme-option.theme-purple .theme-preview { 
      background: #6f42c1 !important; 
      background-color: #6f42c1 !important; 
    }
    .theme-option.theme-orange .theme-preview { 
      background: #fd7e14 !important; 
      background-color: #fd7e14 !important; 
    }

    /* Developer Cards - REDESIGNED SECTION */
    .developer-card {
      background: var(--primary-light);
      padding: 20px;
      border-radius: 10px;
      margin-bottom: 15px;
      border-left: 4px solid var(--primary-color);
      display: flex;
      align-items: center;
      gap: 20px;
      transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
      cursor: pointer;
      position: relative;
      overflow: hidden;
      transform: translateY(0);
      opacity: 1;
    }
    
    .developer-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
      transition: left 0.7s ease;
    }
    
    .developer-card:hover::before {
      left: 100%;
    }
    
    .developer-card:hover {
      transform: translateY(-8px);
      box-shadow: 0 12px 25px rgba(0,0,0,0.15);
      border-left-width: 6px;
      background: var(--secondary-color);
    }
    
    .developer-photo {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      object-fit: cover;
      border: 3px solid var(--primary-color);
      flex-shrink: 0;
      transition: all 0.4s ease;
      position: relative;
      z-index: 2;
    }
    
    .developer-card:hover .developer-photo {
      transform: scale(1.15) rotate(5deg);
      border-width: 4px;
      box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    }
    
    .developer-info {
      flex: 1;
      position: relative;
      z-index: 2;
    }
    
    .developer-name {
      font-weight: 600;
      color: var(--primary-color);
      font-size: 18px;
      margin-bottom: 5px;
      transition: all 0.3s ease;
    }
    
    .developer-card:hover .developer-name {
      transform: translateX(5px);
      color: var(--primary-dark);
    }
    
    .developer-role {
      color: #666;
      font-size: 14px;
      margin-bottom: 8px;
      transition: all 0.3s ease;
    }
    
    .developer-card:hover .developer-role {
      transform: translateX(5px);
    }
    
    .developer-contributions {
      color: #555;
      font-size: 13px;
      font-style: italic;
      transition: all 0.3s ease;
    }
    
    .developer-card:hover .developer-contributions {
      transform: translateX(5px);
    }
    
    /* Animation for card entrance */
    @keyframes cardSlideIn {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    /* Apply staggered animation to cards */
    .developer-card:nth-child(1) {
      animation: cardSlideIn 0.6s ease 0.1s both;
    }
    
    .developer-card:nth-child(2) {
      animation: cardSlideIn 0.6s ease 0.3s both;
    }
    
    .developer-card:nth-child(3) {
      animation: cardSlideIn 0.6s ease 0.5s both;
    }

    /* Feature Lists */
    .feature-category {
      margin-bottom: 25px;
      padding: 15px;
      background: #f8f9fa;
      border-radius: 8px;
      border-left: 4px solid var(--primary-color);
      transition: all 0.3s ease;
      cursor: pointer;
    }
    .feature-category:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 20px rgba(0,0,0,0.15);
      background: var(--secondary-color);
      border-left-width: 6px;
    }
    .feature-category:hover h5 {
      color: var(--primary-dark);
    }
    .feature-category h5 {
      margin: 0 0 12px 0;
      color: var(--primary-color);
      font-size: 16px;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .feature-list {
      list-style: none;
      padding: 0;
      margin: 0;
    }
    .feature-list li {
      padding: 6px 0;
      color: #555;
      font-size: 14px;
      position: relative;
      padding-left: 20px;
    }
    .feature-list li::before {
      content: "✓";
      position: absolute;
      left: 0;
      color: var(--primary-color);
      font-weight: bold;
      font-size: 14px;
    }

    /* Technical Specifications */
    .tech-specs {
      display: grid;
      gap: 12px;
    }
    .spec-item {
      padding: 12px 15px;
      background: #f8f9fa;
      border-radius: 6px;
      border-left: 3px solid var(--primary-color);
      font-size: 14px;
      transition: all 0.3s ease;
      cursor: pointer;
    }
    .spec-item:hover {
      transform: translateX(5px);
      background: var(--primary-light);
      border-left-width: 5px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    .spec-item strong {
      color: var(--primary-color);
    }

    @keyframes modalSlideIn {
      from { transform: translateY(-50px); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
    }

    @media (max-width: 768px) {
      .stats {
        grid-template-columns: 1fr;
      }
      .quick-links {
        grid-template-columns: 1fr;
      }
      header {
        padding: 12px 20px;
      }
      header h1 {
        font-size: 18px;
      }
      .modal-content {
        width: 95%;
        margin: 10% auto;
      }
    }
  </style>
</head>
<body>

  <header>
    <div class="brand">
      <img src="logo.png" alt="Logo">
      <h1>CABARDO, QUENCO, DRAGON</h1>
    </div>
    <div class="header-icons">
      <button class="header-icon" onclick="openNotificationModal()">
        <i class="fa fa-bell"></i>
        <?php if (count($low_stock_products) > 0): ?>
          <span class="notification-badge"><?= count($low_stock_products) ?></span>
        <?php endif; ?>
      </button>
      <button class="header-icon" onclick="openSettingsModal()">
        <i class="fa fa-cog"></i>
      </button>
      <button class="header-icon" onclick="openInfoModal()">
        <i class="fa fa-info-circle"></i>
      </button>
      <a href="logout.php" class="logout-btn">Logout</a>
    </div>
  </header>

  <div class="store-banner">
    <h2>Kingslayer's Sari-Sari Store</h2>
    <p>Oh how i wish to be the written, not the writer, the poem, not the poet, the captured, not the capturer</p>
    <a href="pos.php" class="start-sale-btn">
      <i class="fa fa-cash-register"></i> Start New Sale
    </a>
  </div>

  <div class="stats">
    <div class="stat-card">
      <i class="fa fa-box"></i>
      <p>Total Products</p>
      <h3><?= number_format($total_products) ?></h3>
    </div>
    <div class="stat-card">
      <i class="fa fa-coins"></i>
      <p>Daily Sales</p>
      <h3>₱<?= number_format($daily_sales, 2) ?></h3>
    </div>
    <div class="stat-card">
      <i class="fa fa-chart-line"></i>
      <p>Total Revenue</p>
      <h3>₱<?= number_format($total_revenue, 2) ?></h3>
    </div>
  </div>

<div class="quick-nav">
  <h2>Quick Navigation</h2>

  <div class="quick-links" style="
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
      gap: 28px;
  ">
    <a href="pos.php" class="quick-link" style="
        min-height: 160px;
        padding: 36px 24px;
        border-radius: 18px;
        box-shadow: 0 5px 15px rgba(0,0,0,.07);
        transition: .35s;
        justify-content: center;
    " onmouseover="this.style.boxShadow='0 10px 25px rgba(0,0,0,.11)'; this.style.transform='translateY(-5px)'"
       onmouseout="this.style.boxShadow='0 5px 15px rgba(0,0,0,.07)'; this.style.transform='translateY(0)'">
      <i class="fa fa-cash-register" style="font-size: 40px; margin-bottom: 16px;"></i>
      <span>Point of Sale</span>
    </a>

    <a href="products.php" class="quick-link" style="
        min-height: 160px;
        padding: 36px 24px;
        border-radius: 18px;
        box-shadow: 0 5px 15px rgba(0,0,0,.07);
        transition: .35s;
        justify-content: center;
    " onmouseover="this.style.boxShadow='0 10px 25px rgba(0,0,0,.11)'; this.style.transform='translateY(-5px)'"
       onmouseout="this.style.boxShadow='0 5px 15px rgba(0,0,0,.07)'; this.style.transform='translateY(0)'">
      <i class="fa fa-box" style="font-size: 40px; margin-bottom: 16px;"></i>
      <span>Products</span>
    </a>

    <a href="inventory.php" class="quick-link" style="
        min-height: 160px;
        padding: 36px 24px;
        border-radius: 18px;
        box-shadow: 0 5px 15px rgba(0,0,0,.07);
        transition: .35s;
        justify-content: center;
    " onmouseover="this.style.boxShadow='0 10px 25px rgba(0,0,0,.11)'; this.style.transform='translateY(-5px)'"
       onmouseout="this.style.boxShadow='0 5px 15px rgba(0,0,0,.07)'; this.style.transform='translateY(0)'">
      <i class="fa fa-warehouse" style="font-size: 40px; margin-bottom: 16px;"></i>
      <span>Inventory</span>
    </a>

    <a href="sales_history.php" class="quick-link" style="
        min-height: 160px;
        padding: 36px 24px;
        border-radius: 18px;
        box-shadow: 0 5px 15px rgba(0,0,0,.07);
        transition: .35s;
        justify-content: center;
    " onmouseover="this.style.boxShadow='0 10px 25px rgba(0,0,0,.11)'; this.style.transform='translateY(-5px)'"
       onmouseout="this.style.boxShadow='0 5px 15px rgba(0,0,0,.07)'; this.style.transform='translateY(0)'">
      <i class="fa fa-history" style="font-size: 40px; margin-bottom: 16px;"></i>
      <span>Sales History</span>
    </a>

    <a href="sales_report.php" class="quick-link" style="
        min-height: 160px;
        padding: 36px 24px;
        border-radius: 18px;
        box-shadow: 0 5px 15px rgba(0,0,0,.07);
        transition: .35s;
        justify-content: center;
    " onmouseover="this.style.boxShadow='0 10px 25px rgba(0,0,0,.11)'; this.style.transform='translateY(-5px)'"
       onmouseout="this.style.boxShadow='0 5px 15px rgba(0,0,0,.07)'; this.style.transform='translateY(0)'">
      <i class="fa fa-chart-bar" style="font-size: 40px; margin-bottom: 16px;"></i>
      <span>Sales Report</span>
    </a>
  </div>
</div>

  <!-- Notification Modal -->
  <div id="notificationModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h3><i class="fa fa-bell"></i> Stock Alerts</h3>
        <button class="close" onclick="closeModal('notificationModal')">&times;</button>
      </div>
      <div class="modal-body">
        <?php if (count($low_stock_products) > 0): ?>
          <?php foreach ($low_stock_products as $product): ?>
            <div class="notification-item">
              <div class="notification-icon">
                <i class="fa fa-exclamation-triangle"></i>
              </div>
              <div class="notification-content">
                <h4>Low Stock Alert</h4>
                <p><strong><?= htmlspecialchars($product['product_name']) ?></strong> - Only <?= $product['stock'] ?> left in stock</p>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div style="text-align: center; padding: 40px; color: #666;">
            <i class="fa fa-check-circle" style="font-size: 48px; color: #28a745; margin-bottom: 15px;"></i>
            <h4>All Good!</h4>
            <p>No low stock alerts at the moment.</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Settings Modal -->
  <div id="settingsModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h3><i class="fa fa-cog"></i> System Settings</h3>
        <button class="close" onclick="closeModal('settingsModal')">&times;</button>
      </div>
      <div class="modal-body">
        
        <!-- System Name Section -->
        <div class="settings-section">
          <h4>System Information</h4>
          <div class="form-group">
            <label for="systemName">System Name</label>
            <input type="text" id="systemName" class="form-control" placeholder="Enter system name" value="CABARDO, QUENCO, DRAGON">
          </div>
          <div class="form-group">
            <label for="storeName">Store Name</label>
            <input type="text" id="storeName" class="form-control" placeholder="Enter store name" value="Kingslayer's Sari-Sari Store">
          </div>
          <div class="form-group">
            <label for="storeTagline">Store Tagline/Description</label>
            <textarea id="storeTagline" class="form-control" rows="3" placeholder="Enter store tagline or description">Oh how i wish to be the written, not the writer, the poem, not the poet, the captured, not the capturer</textarea>
          </div>
          <button class="btn" onclick="updateSystemInfo()">
            <i class="fa fa-save"></i> Save Changes
          </button>
        </div>

        <!-- Logo Section -->
        <div class="settings-section">
          <h4>Logo Settings</h4>
          <div class="form-group">
            <label>Upload New Logo</label>
            <div class="file-upload">
              <input type="file" id="logoUpload" accept="image/*" onchange="handleLogoUpload(this)">
              <div class="file-upload-label">
                <i class="fa fa-upload"></i>
                <span>Choose logo file (PNG, JPG, GIF)</span>
              </div>
            </div>
          </div>
          <div class="form-group">
            <button class="btn btn-secondary" onclick="resetLogo()">
              <i class="fa fa-undo"></i> Reset to Default
            </button>
          </div>
        </div>

        <!-- Theme Section -->
        <div class="settings-section">
          <h4>Theme Color</h4>
          <div class="theme-options">
            <div class="theme-option theme-blue active" onclick="changeTheme('blue')">
              <div class="theme-preview"></div>
              <div>Blue</div>
            </div>
            <div class="theme-option theme-red" onclick="changeTheme('red')">
              <div class="theme-preview"></div>
              <div>Red</div>
            </div>
            <div class="theme-option theme-green" onclick="changeTheme('green')">
              <div class="theme-preview"></div>
              <div>Green</div>
            </div>
            <div class="theme-option theme-purple" onclick="changeTheme('purple')">
              <div class="theme-preview"></div>
              <div>Purple</div>
            </div>
            <div class="theme-option theme-orange" onclick="changeTheme('orange')">
              <div class="theme-preview"></div>
              <div>Orange</div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>

  <!-- Info Modal -->
  <div id="infoModal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
      <div class="modal-header">
        <h3><i class="fa fa-info-circle"></i> System Information</h3>
        <button class="close" onclick="closeModal('infoModal')">&times;</button>
      </div>
      <div class="modal-body">
        
        <!-- System Features Section -->
        <div class="settings-section">
          <h4><i class="fa fa-star"></i> System Features</h4>
          
          <div class="feature-category">
            <h5><i class="fa fa-cash-register"></i> Point of Sale</h5>
            <ul class="feature-list">
              <li>Real-time product catalog with images</li>
              <li>Shopping cart with quantity management</li>
              <li>Payment validation and change calculation</li>
              <li>Stock availability checking</li>
              <li>Transaction processing with receipt</li>
            </ul>
          </div>

          <div class="feature-category">
            <h5><i class="fa fa-box"></i> Product Management</h5>
            <ul class="feature-list">
              <li>Add/edit products with descriptions</li>
              <li>Image upload and management</li>
              <li>Price and stock management</li>
              <li>Product categorization</li>
              <li>Bulk product operations</li>
            </ul>
          </div>

          <div class="feature-category">
            <h5><i class="fa fa-warehouse"></i> Inventory Control</h5>
            <ul class="feature-list">
              <li>Real-time stock tracking</li>
              <li>Low stock alerts and notifications</li>
              <li>Stock level color coding</li>
              <li>Search and filter capabilities</li>
              <li>Pagination for large inventories</li>
            </ul>
          </div>

          <div class="feature-category">
            <h5><i class="fa fa-chart-line"></i> Sales Analytics</h5>
            <ul class="feature-list">
              <li>Daily, monthly, and yearly revenue tracking</li>
              <li>Sales comparison and trends</li>
              <li>Transaction history with filters</li>
              <li>Comprehensive sales reports</li>
              <li>Top performing days analysis</li>
            </ul>
          </div>

          <div class="feature-category">
            <h5><i class="fa fa-cog"></i> System Customization</h5>
            <ul class="feature-list">
              <li>Dynamic theme switching (5 color schemes)</li>
              <li>Custom system and store naming</li>
              <li>Logo upload and customization</li>
              <li>User preference persistence</li>
              <li>Responsive mobile design</li>
            </ul>
          </div>

          <div class="feature-category">
            <h5><i class="fa fa-bell"></i> Smart Notifications</h5>
            <ul class="feature-list">
              <li>Real-time low stock alerts</li>
              <li>Automatic notification badges</li>
              <li>System status updates</li>
              <li>Transaction confirmations</li>
              <li>Error handling and feedback</li>
            </ul>
          </div>
        </div>

        <!-- Technical Specifications -->
        <div class="settings-section">
          <h4><i class="fa fa-code"></i> Technical Specifications</h4>
          <div class="tech-specs">
            <div class="spec-item">
              <strong>Frontend:</strong> HTML5, CSS3, JavaScript (ES6+)
            </div>
            <div class="spec-item">
              <strong>Backend:</strong> PHP 7.4+, MySQL Database
            </div>
            <div class="spec-item">
              <strong>Framework:</strong> Responsive CSS Grid & Flexbox
            </div>
            <div class="spec-item">
              <strong>Security:</strong> Prepared statements, Input validation
            </div>
            <div class="spec-item">
              <strong>Storage:</strong> Local storage for preferences
            </div>
          </div>
        </div>

        <!-- Developer Credits - REDESIGNED SECTION -->
        <div class="settings-section">
          <h4><i class="fa fa-users"></i> Development Team</h4>
          <div class="developer-card">
            <img src="./phs/jeb.jpg" alt="Sonjeev C. Cabardo" class="developer-photo">
            <div class="developer-info">
              <div class="developer-name">Sonjeev C. Cabardo</div>
              <div class="developer-role">Team Leader & Lead Developer</div>
              <div class="developer-contributions">
                System architecture, database design, core functionality
              </div>
            </div>
          </div>

          <div class="developer-card">
            <img src="./phs/dra.jpg" alt="Marychris Dragon" class="developer-photo">
            <div class="developer-info">
              <div class="developer-name">Marychris Dragon</div>
              <div class="developer-role">Team Member</div>
              <div class="developer-contributions">
                Design
              </div>
            </div>
          </div>
          
          <div class="developer-card">
            <img src="./phs/que.jpg" alt="Rechelle Quenco" class="developer-photo">
            <div class="developer-info">
              <div class="developer-name">Rechelle Quenco</div>
              <div class="developer-role">Team Member</div>
              <div class="developer-contributions">
                Quality assurance
              </div>
            </div>
          </div>
        </div>

        <!-- Project Info -->
        <div style="text-align: center; margin-top: 20px; padding: 20px; background: var(--primary-light); border-radius: 12px; border: 2px solid var(--primary-color);">
          <h4 style="margin: 0 0 10px 0; color: var(--primary-color); font-size: 18px;">
            <i class="fa fa-heart"></i> Kingslayer's POS System
          </h4>
          <p style="margin: 0 0 10px 0; font-style: italic; color: var(--primary-color); font-size: 16px;">
            "Built with pure hatred"
          </p>
          <div style="font-size: 14px; color: #666; margin-top: 15px;">
            <div><strong>Version:</strong> 1.0.0</div>
            <div><strong>Release Date:</strong> 2025</div>
            <div><strong>Status:</strong> Production Ready</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    // Modal functions
    function openNotificationModal() {
      document.getElementById('notificationModal').style.display = 'block';
    }

    function openSettingsModal() {
      document.getElementById('settingsModal').style.display = 'block';
      // Load saved settings
      loadSavedSettings();
    }

    function openInfoModal() {
      document.getElementById('infoModal').style.display = 'block';
    }

    function closeModal(modalId) {
      document.getElementById(modalId).style.display = 'none';
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
      const modals = ['notificationModal', 'settingsModal', 'infoModal'];
      modals.forEach(modalId => {
        const modal = document.getElementById(modalId);
        if (event.target === modal) {
          modal.style.display = 'none';
        }
      });
    }

    // System Information Functions
    function updateSystemInfo() {
      const systemName = document.getElementById('systemName').value.trim();
      const storeName = document.getElementById('storeName').value.trim();
      const storeTagline = document.getElementById('storeTagline').value.trim();
      
      if (systemName === '' || storeName === '') {
        alert('Please fill in both system name and store name.');
        return;
      }
      
      // Update the header - target the h1 element directly
      const headerTitle = document.querySelector('header h1');
      if (headerTitle) {
        headerTitle.textContent = systemName;
      }
      
      // Update the store banner - target the h2 element directly  
      const storeTitle = document.querySelector('.store-banner h2');
      if (storeTitle) {
        storeTitle.textContent = storeName;
      }
      
      // Update the store tagline - target the p element directly
      const storeDesc = document.querySelector('.store-banner p');
      if (storeDesc && storeTagline) {
        storeDesc.textContent = storeTagline;
      }
      
      // Save to localStorage
      localStorage.setItem('systemName', systemName);
      localStorage.setItem('storeName', storeName);
      localStorage.setItem('storeTagline', storeTagline);
      
      alert('System information updated successfully!');
    }

    // Logo Functions
    function handleLogoUpload(input) {
      const file = input.files[0];
      if (file) {
        if (file.size > 2 * 1024 * 1024) { // 2MB limit
          alert('Please choose a logo file smaller than 2MB.');
          input.value = ''; // Reset file input
          return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
          const logoUrl = e.target.result;
          
          // Update logo in header
          const logoImg = document.querySelector('header .brand img');
          if (logoImg) {
            logoImg.src = logoUrl;
          }
          
          // Save to localStorage
          localStorage.setItem('customLogo', logoUrl);
          alert('Logo updated successfully!');
        };
        reader.readAsDataURL(file);
      }
    }

    function resetLogo() {
      // Reset to default logo
      const logoImg = document.querySelector('header .brand img');
      if (logoImg) {
        logoImg.src = 'logo.png';
      }
      
      // Clear file input
      const fileInput = document.getElementById('logoUpload');
      if (fileInput) {
        fileInput.value = '';
      }
      
      // Remove from localStorage
      localStorage.removeItem('customLogo');
      alert('Logo reset to default!');
    }

    // Load saved settings
    function loadSavedSettings() {
      // Load system name
      const savedSystemName = localStorage.getItem('systemName');
      if (savedSystemName) {
        document.getElementById('systemName').value = savedSystemName;
      }
      
      // Load store name
      const savedStoreName = localStorage.getItem('storeName');
      if (savedStoreName) {
        document.getElementById('storeName').value = savedStoreName;
      }
      
      // Load store tagline
      const savedTagline = localStorage.getItem('storeTagline');
      if (savedTagline) {
        document.getElementById('storeTagline').value = savedTagline;
      }
    }

    // Theme switcher
    function changeTheme(theme) {
      // Remove all theme classes
      document.body.classList.remove('theme-blue', 'theme-red', 'theme-green', 'theme-purple', 'theme-orange');
      
      // Add selected theme class
      if (theme !== 'blue') {
        document.body.classList.add('theme-' + theme);
      }
      
      // Update active theme option
      document.querySelectorAll('.theme-option').forEach(option => {
        option.classList.remove('active');
      });
      document.querySelector('.theme-' + theme).classList.add('active');
      
      // Save theme preference
      localStorage.setItem('selectedTheme', theme);
    }

    // Load saved settings on page load
    document.addEventListener('DOMContentLoaded', function() {
      // Load saved theme
      const savedTheme = localStorage.getItem('selectedTheme') || 'blue';
      if (savedTheme !== 'blue') {
        document.body.classList.add('theme-' + savedTheme);
        // Update theme selector in settings modal
        setTimeout(() => {
          document.querySelectorAll('.theme-option').forEach(option => option.classList.remove('active'));
          const activeTheme = document.querySelector('.theme-' + savedTheme);
          if (activeTheme) activeTheme.classList.add('active');
        }, 100);
      }
      
      // Load saved system name
      const savedSystemName = localStorage.getItem('systemName');
      if (savedSystemName) {
        const headerTitle = document.querySelector('header .brand h1');
        if (headerTitle) {
          headerTitle.textContent = savedSystemName;
        }
      }
      
      // Load saved store name
      const savedStoreName = localStorage.getItem('storeName');
      if (savedStoreName) {
        const storeTitle = document.querySelector('.store-banner h2');
        if (storeTitle) {
          storeTitle.textContent = savedStoreName;
        }
      }
      
      const savedTagline = localStorage.getItem('storeTagline');
      if (savedTagline) {
        const storeDesc = document.querySelector('.store-banner p');
        if (storeDesc) {
          storeDesc.textContent = savedTagline;
        }
      }

      const savedLogo = localStorage.getItem('customLogo');
      if (savedLogo) {
        const logoImg = document.querySelector('header .brand img');
        if (logoImg) {
          logoImg.src = savedLogo;
        }
      }
    });
  </script>

</body>
</html>