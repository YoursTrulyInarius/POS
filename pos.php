<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// db connection
$conn = new mysqli("localhost", "root", "", "op_db");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// fetch products
$products = $conn->query("SELECT * FROM products");

// handle confirm payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cart_data'])) {
    // Get current logged-in user's ID from users table
    $username = $_SESSION['username'];
    $user_query = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $user_query->bind_param("s", $username);
    $user_query->execute();
    $user_result = $user_query->get_result();
    
    if ($user_result->num_rows === 0) {
        echo "<script>alert('Error: User not found!'); window.history.back();</script>";
        exit;
    }
    
    $user_data = $user_result->fetch_assoc();
    $user_id = $user_data['id'];
    $user_query->close();
    
    $cart = json_decode($_POST['cart_data'], true);
    $total = floatval($_POST['total_amount']);
    $paid = floatval($_POST['paid_amount']);
    
    // Payment validation
    if (empty($cart)) {
        echo "<script>alert('Error: Cart is empty!'); window.history.back();</script>";
        exit;
    }
    
    if ($paid <= 0) {
        echo "<script>alert('Error: Please enter a valid payment amount!'); window.history.back();</script>";
        exit;
    }
    
    if ($paid < $total) {
        $shortage = $total - $paid;
        echo "<script>alert('Error: Insufficient payment! You need $" . number_format($shortage, 2) . " more.'); window.history.back();</script>";
        exit;
    }
    
    $change = $paid - $total;

    // Begin transaction for data integrity
    $conn->begin_transaction();
    
    try {
        // Validate stock availability before processing
        foreach ($cart as $item) {
            $pid = intval($item['id']);
            $qty = intval($item['qty']);
            
            $stock_check = $conn->prepare("SELECT stock FROM products WHERE product_id = ?");
            if (!$stock_check) {
                throw new Exception("Failed to prepare stock check query: " . $conn->error);
            }
            
            $stock_check->bind_param("i", $pid);
            $stock_check->execute();
            $result = $stock_check->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception("Product with ID {$pid} not found!");
            }
            
            $product = $result->fetch_assoc();
            if ($product['stock'] < $qty) {
                $product_name = htmlspecialchars($item['name']);
                throw new Exception("Insufficient stock for {$product_name}! Available: {$product['stock']}, Requested: {$qty}");
            }
            $stock_check->close();
        }

        // insert sale
        $stmt = $conn->prepare("INSERT INTO sales (user_id, total_amount, amount_paid, change_amount) VALUES (?, ?, ?, ?)");
        if (!$stmt) {
            throw new Exception("Failed to prepare sales insert query: " . $conn->error);
        }
        
        $stmt->bind_param("iddd", $user_id, $total, $paid, $change);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to record sale: " . $stmt->error);
        }
        
        $sale_id = $stmt->insert_id;
        $stmt->close();

        // insert items + update stock
        foreach ($cart as $item) {
            $pid = intval($item['id']);
            $qty = intval($item['qty']);
            $price = floatval($item['price']);

            // Insert sale item
            $stmt = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
            if (!$stmt) {
                throw new Exception("Failed to prepare sale items insert query: " . $conn->error);
            }
            
            $stmt->bind_param("iiid", $sale_id, $pid, $qty, $price);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to record sale item: " . $stmt->error);
            }
            $stmt->close();

            // Update stock using prepared statement to prevent SQL injection
            $update_stmt = $conn->prepare("UPDATE products SET stock = stock - ? WHERE product_id = ?");
            if (!$update_stmt) {
                throw new Exception("Failed to prepare stock update query: " . $conn->error);
            }
            
            $update_stmt->bind_param("ii", $qty, $pid);
            
            if (!$update_stmt->execute()) {
                throw new Exception("Failed to update stock for product ID {$pid}: " . $update_stmt->error);
            }
            $update_stmt->close();
        }

        // Commit transaction
        $conn->commit();
        
        echo "<script>
            alert('Payment successful! Sale recorded.\\nTotal: $" . number_format($total, 2) . "\\nPaid: $" . number_format($paid, 2) . "\\nChange: $" . number_format($change, 2) . "'); 
            window.location.href='pos.php';
        </script>";
        exit;
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        echo "<script>alert('Error: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>POS System</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    /* Theme System */
    :root {
      --primary-color: #007bff;
      --primary-dark: #e8ebefff;
      --primary-light: #e8f0fe;
      --secondary-color: #fff;
      --text-color: #333;
      --bg-color: #f0f4fa;
    }

    .theme-red {
      --primary-color: #dc3545;
      --primary-dark: #fefbfcff;
      --primary-light: #f8d7da;
      --bg-color: #fff5f5;
    }

    .theme-green {
      --primary-color: #25b346ff;
      --primary-dark: #d9e3dbff;
      --primary-light: #d4edda;
      --bg-color: #f8fff9;
    }

    .theme-purple {
      --primary-color: #693ac0ff;
      --primary-dark: #f5ebf5ff;
      --primary-light: #e2d9f3;
      --bg-color: #faf9fc;
    }

    .theme-orange {
      --primary-color: #fd7e14;
      --primary-dark: #f9f4f4ff;
      --primary-light: #ffeeba;
      --bg-color: #fffaf3;
    }

    body {font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; margin:0; padding:0; background:var(--bg-color); transition:all 0.3s ease;}
    header {background:var(--primary-color); color:var(--secondary-color); padding:15px 20px; display:flex; justify-content:space-between; align-items:center; box-shadow: 0 2px 10px rgba(0,0,0,0.1);}
    header h1 {margin:0; font-size:24px; font-weight:700;}
    .user-info {
      font-size: 14px;
      color: rgba(255,255,255,0.9);
      margin-right: 20px;
    }
    header a {background:var(--secondary-color); color:var(--primary-color); padding:10px 16px; border-radius:8px; text-decoration:none; font-weight:600; transition: all 0.3s ease;}
    header a:hover {background:var(--primary-light); transform: translateY(-1px);}
    .container {display:flex; gap:25px; padding:25px; flex-wrap:wrap;}
    .products, .cart {flex:1; background:var(--secondary-color); border-radius:15px; box-shadow:0 8px 25px rgba(0,0,0,0.1); padding:25px; min-width:320px;}
    .products h2, .cart h2 {margin-top:0; font-size:22px; border-bottom:3px solid var(--primary-color); padding-bottom:12px; color: var(--primary-color); font-weight: 700;}
    .product-list {display:grid; grid-template-columns:repeat(auto-fill, minmax(160px, 1fr)); gap:15px;}
    .product {border:2px solid #e9ecef; border-radius:15px; padding:15px; text-align:center; background:#fafafa; transition: all 0.3s ease;}
    .product:hover {transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); border-color: var(--primary-color);}
    .product img {width:100px; height:100px; object-fit:cover; border-radius:10px; margin-bottom:10px;}
    .product input {width:60px; padding:6px; text-align:center; border:2px solid #ddd; border-radius:6px; font-weight: 500;}
    .product input:focus {outline: none; border-color: var(--primary-color);}
    .add-btn {margin-top:10px; background:var(--primary-color); color:var(--secondary-color); border:none; padding:8px 15px; border-radius:8px; cursor:pointer; font-weight: 600; transition: all 0.3s ease;}
    .add-btn:hover {background:var(--primary-dark); transform: translateY(-1px);}
    
    /* Enhanced Cart Styling */
    .cart {
      position: relative;
      border: 2px solid var(--primary-color);
    }
    
    .cart::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(90deg, var(--primary-color), var(--primary-dark));
      border-radius: 15px 15px 0 0;
    }
    
    .cart-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 20px;
      padding: 15px 20px;
      background: linear-gradient(135deg, var(--primary-light), #f8f9fa);
      border-radius: 10px;
      border-left: 4px solid var(--primary-color);
    }
    
    .cart-header h2 {
      margin: 0;
      border: none;
      padding: 0;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    .cart-icon {
      background: var(--primary-color);
      color: var(--secondary-color);
      width: 35px;
      height: 35px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 16px;
    }
    
    .cart-count {
      background: var(--primary-color);
      color: var(--secondary-color);
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
      min-width: 24px;
      text-align: center;
    }
    
    .cart-items {
      max-height:400px; 
      overflow-y:auto; 
      margin-bottom:20px;
      padding-right: 5px;
    }
    
    .cart-items::-webkit-scrollbar {
      width: 6px;
    }
    
    .cart-items::-webkit-scrollbar-track {
      background: #f1f1f1;
      border-radius: 3px;
    }
    
    .cart-items::-webkit-scrollbar-thumb {
      background: var(--primary-color);
      border-radius: 3px;
    }
    
    .cart-item {
      display:flex; 
      align-items:center; 
      justify-content:space-between; 
      background: linear-gradient(135deg, #f8f9fa, #ffffff);
      padding:15px; 
      border-radius:12px; 
      margin-bottom:12px;
      border: 1px solid #e9ecef;
      transition: all 0.3s ease;
      box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }
    
    .cart-item:hover {
      transform: translateX(5px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      border-color: var(--primary-color);
    }
    
    .cart-item img {
      width:55px; 
      height:55px; 
      border-radius:8px; 
      margin-right:15px;
      object-fit: cover;
      border: 2px solid #e9ecef;
    }
    
    .cart-item-details {
      flex:1; 
      font-size:14px;
    }
    
    .cart-item-details strong {
      display:block; 
      color: var(--primary-color);
      font-weight: 700;
      margin-bottom: 5px;
    }
    
    .cart-item-controls {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-top: 5px;
    }
    
    .cart-item-controls input {
      width:45px; 
      text-align:center;
      padding: 4px 6px;
      border: 1px solid #ddd;
      border-radius: 5px;
      font-weight: 500;
    }
    
    .cart-item-controls input:focus {
      outline: none;
      border-color: var(--primary-color);
    }
    
    .cart-item-price {
      text-align: right;
      min-width: 80px;
    }
    
    .cart-item-subtotal {
      font-size: 16px;
      font-weight: 700;
      color: var(--primary-color);
      margin-bottom: 8px;
    }
    
    .remove-btn {
      background:#dc3545; 
      color:var(--secondary-color); 
      border:none; 
      padding:6px 12px; 
      border-radius:6px; 
      cursor:pointer; 
      font-size:12px;
      font-weight: 600;
      transition: all 0.3s ease;
    }
    
    .remove-btn:hover {
      background:#c82333;
      transform: translateY(-1px);
    }
    
    .empty-cart {
      text-align: center;
      padding: 40px 20px;
      color: #6c757d;
    }
    
    .empty-cart-icon {
      font-size: 48px;
      color: #dee2e6;
      margin-bottom: 15px;
    }
    
    .totals-section {
      background: linear-gradient(135deg, var(--primary-light), #f8f9fa);
      padding: 20px;
      border-radius: 12px;
      border: 2px solid var(--primary-color);
      margin-bottom: 20px;
    }
    
    .totals {
      font-size:20px; 
      font-weight:700; 
      color: var(--primary-color);
      text-align: center;
      margin-bottom: 15px;
    }
    
    .payment-section {
      background: #ffffff;
      padding: 20px;
      border-radius: 12px;
      border: 1px solid #e9ecef;
      margin-bottom: 15px;
    }
    
    .payment {
      display:flex; 
      gap:12px; 
      align-items:center;
      margin-bottom: 15px;
    }
    
    .payment label {
      font-weight: 600;
      color: var(--primary-color);
      min-width: 60px;
    }
    
    .payment input {
      flex:1; 
      padding:12px 15px; 
      border:2px solid #e9ecef; 
      border-radius:8px; 
      font-size:16px;
      font-weight: 500;
      transition: all 0.3s ease;
    }
    
    .payment input:focus {
      outline: none;
      border-color: var(--primary-color);
      box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
    }
    
    .payment input.insufficient {
      border-color:#dc3545; 
      background:#fff5f5;
    }
    
    .change {
      font-size:18px; 
      font-weight:700;
      text-align: center;
      padding: 10px;
      border-radius: 8px;
    }
    
    .change.positive {
      color:#28a745;
      background: #d4edda;
    }
    
    .change.negative {
      color:#dc3545;
      background: #f8d7da;
    }
    
    .confirm-btn {
      margin-top:20px; 
      background: linear-gradient(135deg, #28a745, #20c997);
      color:var(--secondary-color); 
      border:none; 
      padding:15px 20px; 
      border-radius:10px; 
      font-size:16px; 
      cursor:pointer; 
      width:100%;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      transition: all 0.3s ease;
      box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
    }
    
    .confirm-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
    }
    
    .confirm-btn:disabled {
      background:#6c757d; 
      cursor:not-allowed;
      transform: none;
      box-shadow: none;
    }
    
    .error-message {
      background:#f8d7da; 
      color:#721c24; 
      padding:12px 15px; 
      border-radius:8px; 
      margin:10px 0; 
      border:1px solid #f5c6cb;
      font-weight: 500;
    }
    
    @media (max-width:768px) {
      .container{flex-direction:column; padding: 15px;}
      .products, .cart {min-width: auto;}
    }
  </style>
</head>
<body>

<header>
  <a href="dashboard.php">← Return to Dashboard</a>
  <h1>Point of Sale</h1>
  <div class="user-info">Cashier: <?= htmlspecialchars($_SESSION['username']) ?></div>
</header>

<div class="container">
  <!-- Products -->
  <div class="products">
    <h2>Products</h2>
    <div class="product-list">
      <?php while($row = $products->fetch_assoc()): ?>
        <div class="product">
          <img src="<?= htmlspecialchars($row['image']) ?>" alt="<?= htmlspecialchars($row['product_name']) ?>">
          <p><strong><?= htmlspecialchars($row['product_name']) ?></strong></p>
          <p>$<?= number_format($row['price'], 2) ?></p>
          <p>Stock: <?= intval($row['stock']) ?></p>
          <input type="number" id="qty-<?= $row['product_id'] ?>" min="1" max="<?= intval($row['stock']) ?>" value="1">
          <button class="add-btn" onclick="addToCart(<?= $row['product_id'] ?>, '<?= htmlspecialchars($row['product_name'], ENT_QUOTES) ?>', <?= $row['price'] ?>, '<?= htmlspecialchars($row['image'], ENT_QUOTES) ?>', <?= intval($row['stock']) ?>)">Add</button>
        </div>
      <?php endwhile; ?>
    </div>
  </div>

  <!-- Enhanced Cart -->
  <div class="cart">
    <div class="cart-header">
      <h2>
        <span class="cart-icon">🛒</span>
        Shopping Cart
      </h2>
      <span class="cart-count" id="cartCount">0</span>
    </div>
    
    <form method="post" onsubmit="return submitSale()">
      <div class="cart-items" id="cartItems">
        <div class="empty-cart">
          <div class="empty-cart-icon">🛒</div>
          <p>Your cart is empty</p>
          <small>Add products to start shopping</small>
        </div>
      </div>
      
      <div class="totals-section">
        <div class="totals">Total: $<span id="totalAmount">0.00</span></div>
      </div>
      
      <div class="payment-section">
        <div class="payment">
          <label>Cash:</label>
          <input type="number" id="paidAmount" name="paid_amount" value="" step="0.01" min="0" oninput="calculateChange()" placeholder="0.00" required>
        </div>
        <div class="change positive" id="changeDisplay">Change: $<span id="changeAmount">0.00</span></div>
      </div>
      
      <div class="error-message" id="errorMessage" style="display:none;"></div>

      <input type="hidden" name="cart_data" id="cartData">
      <input type="hidden" name="total_amount" id="totalAmountInput">

      <button type="submit" class="confirm-btn" id="confirmBtn">Confirm Payment</button>
    </form>
  </div>
</div>

<script>
let cart = [];

function updateCartCount() {
  const count = cart.reduce((sum, item) => sum + item.qty, 0);
  document.getElementById('cartCount').textContent = count;
}

function addToCart(id, name, price, image, stock) {
  let qtyInput = document.getElementById("qty-" + id);
  let qty = parseInt(qtyInput.value);
  
  if (qty <= 0) {
    alert("Please enter a valid quantity!");
    return;
  }
  
  if (qty > stock) {
    alert("Not enough stock! Available: " + stock);
    return;
  }

  let item = cart.find(p => p.id === id);
  if (item) {
    if (item.qty + qty > stock) {
      alert("Not enough stock! Available: " + stock + ", Already in cart: " + item.qty);
      return;
    }
    item.qty += qty;
  } else {
    cart.push({id, name, price, image, qty, stock});
  }
  qtyInput.value = 1; // reset qty
  renderCart();
}

function updateQty(id, newQty) {
  let item = cart.find(p => p.id === id);
  if (item) {
    if (newQty <= 0) {
      removeFromCart(id);
      return;
    }
    if (newQty > item.stock) {
      alert("Not enough stock! Available: " + item.stock);
      return;
    }
    item.qty = parseInt(newQty);
    renderCart();
  }
}

function removeFromCart(id) {
  cart = cart.filter(p => p.id !== id);
  renderCart();
}

function renderCart() {
  let cartDiv = document.getElementById("cartItems");
  cartDiv.innerHTML = "";
  let total = 0;

  if (cart.length === 0) {
    cartDiv.innerHTML = `
      <div class="empty-cart">
        <div class="empty-cart-icon">🛒</div>
        <p>Your cart is empty</p>
        <small>Add products to start shopping</small>
      </div>
    `;
  }

  cart.forEach(item => {
    let subtotal = item.price * item.qty;
    total += subtotal;

    let div = document.createElement("div");
    div.className = "cart-item";
    div.innerHTML = `
      <img src="${item.image}" alt="${item.name}">
      <div class="cart-item-details">
        <strong>${item.name}</strong>
        <div class="cart-item-controls">
          Qty: <input type="number" value="${item.qty}" min="1" max="${item.stock}" onchange="updateQty(${item.id}, this.value)"> × $${item.price.toFixed(2)}
        </div>
      </div>
      <div class="cart-item-price">
        <div class="cart-item-subtotal">$${subtotal.toFixed(2)}</div>
        <button type="button" class="remove-btn" onclick="removeFromCart(${item.id})">Remove</button>
      </div>
    `;
    cartDiv.appendChild(div);
  });

  document.getElementById("totalAmount").textContent = total.toFixed(2);
  document.getElementById("totalAmountInput").value = total.toFixed(2);
  updateCartCount();
  calculateChange();
}

function calculateChange() {
  let total = parseFloat(document.getElementById("totalAmount").textContent);
  let paid = parseFloat(document.getElementById("paidAmount").value) || 0;
  let change = paid - total;
  
  let changeElement = document.getElementById("changeAmount");
  let changeDisplay = document.getElementById("changeDisplay");
  let paidInput = document.getElementById("paidAmount");
  let confirmBtn = document.getElementById("confirmBtn");
  let errorMessage = document.getElementById("errorMessage");
  
  changeElement.textContent = change.toFixed(2);
  
  if (change < 0) {
    changeDisplay.className = "change negative";
    changeDisplay.innerHTML = "Insufficient: $<span id='changeAmount'>" + Math.abs(change).toFixed(2) + "</span>";
    paidInput.className = "insufficient";
    confirmBtn.disabled = true;
    errorMessage.textContent = "Payment amount is insufficient!";
    errorMessage.style.display = "block";
  } else {
    changeDisplay.className = "change positive";
    changeDisplay.innerHTML = "Change: $<span id='changeAmount'>" + change.toFixed(2) + "</span>";
    paidInput.className = "";
    confirmBtn.disabled = false;
    errorMessage.style.display = "none";
  }
}

function submitSale() {
  if (cart.length === 0) {
    alert("Cart is empty!");
    return false;
  }
  
  let total = parseFloat(document.getElementById("totalAmount").textContent);
  let paid = parseFloat(document.getElementById("paidAmount").value) || 0;
  
  if (paid < total) {
    alert("Insufficient payment! Please pay at least $" + total.toFixed(2));
    return false;
  }
  
  if (paid <= 0) {
    alert("Please enter a valid payment amount!");
    return false;
  }
  
  if (!confirm("Confirm payment of $" + paid.toFixed(2) + " for total $" + total.toFixed(2) + "?")) {
    return false;
  }
  
  document.getElementById("cartData").value = JSON.stringify(cart);
  return true;
}

// Initialize cart display
renderCart();

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