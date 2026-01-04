# King Slayer's Sari-Sari Store POS System

A feature-rich Point of Sale (POS) system designed for Sari-Sari stores, built with a fun One Piece theme ("Sonjeb Gwapo sakatanan"). This system helps manage inventory, process sales, and track revenue with a user-friendly interface.

## 👥 Developers
*   **Cabardo**

## ✨ Features

### 🔐 Authentication & Security
*   **Secure Login:** Custom login page with a video background (`g5.mp4`).
*   **Registration:** User registration with duplicate checks.
*   **Role-Based Access:** Cashier tracking for every transaction.

### 💰 Point of Sale (POS)
*   **Dynamic Cart:** Real-time addition of products with automatic subtotal calculation.
*   **Stock Validation:** Prevents selling more items than available in inventory.
*   **Smart Payment System:**
    *   Calculates change automatically.
    *   Validates insufficient payment.
*   **Theme Switcher:** Personalize the POS experience with multiple color themes (Blue, Red, Green, Purple, Orange).

### 📊 Dashboard & Analytics
*   **Real-time Stats:**
    *   Total Products count.
    *   Daily Sales tracker.
    *   Total Revenue accumulated.
*   **Low Stock Alerts:** Automatic notification for products with stock levels at 5 or below.
*   **Quick Navigation:** fast access to POS, Inventory, and Reports.

### 📦 Inventory & Management
*   **Product Management:** Add, delete, and update product stocks.
*   **Sales History:** View past transactions and download reports.

## 🚀 Installation & Setup

1.  **Server Requirements:**
    *   PHP 7.4 or higher
    *   MySQL Database (XAMPP/WAMP suggested)

2.  **Database Configuration:**
    *   Create a database named `op_db`.
    *   Import the provided SQL schema (if available, e.g., `database.sql` or from `sql comand.txt`).
    *   Configure `db.php` if you have a custom database password:
        ```php
        $host = "localhost";
        $user = "root";
        $pass = ""; // Enter your MySQL password here
        $db   = "op_db";
        ```

3.  **Run the Application:**
    *   Place the project folder in your `htdocs` directory.
    *   Navigate to `http://localhost/assignment/index.php`.
    *   Register a new account or login.

## 📝 Technologies Used
*   **Frontend:** HTML5, CSS3, JavaScript (Vanilla)
*   **Backend:** PHP
*   **Database:** MySQL

## 📄 License
This project is for educational/assignment purposes.
