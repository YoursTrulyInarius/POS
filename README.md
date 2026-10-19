# King Slayer's Sari-Sari Store POS System

A PHP and MySQL point-of-sale application for managing sari-sari store products, inventory, sales, and reports.

## Features

### Accounts

- Register an account with a unique username and email.
- Sign in and out using a session.
- Associate sales with the signed-in cashier.

> **Security note:** The current login implementation stores and checks passwords as plain text. This project is for educational use and should not be deployed with real accounts or sensitive data until password hashing and other production security controls are added.

### Point of Sale

- Add products to a cart and calculate the sale total.
- Validate cart quantities against available stock.
- Record payments, calculate change, and save sales and sale items.
- Reduce product stock when a sale is completed.
- Choose from the available POS color themes.

### Products and Inventory

- Create products with a name, description, price, initial stock, and optional image.
- Browse, search, filter, sort, and export inventory data.
- Review stock levels, inventory movement, and recent inventory activity.
- Update product details, add stock, and delete products.
- Product creation and restocking accept only whole-number quantities greater than or equal to zero. Negative values are rejected by server-side validation as well as restricted in the forms.
- Stock updates and their inventory-history entries are saved together.
- Uploaded product images are stored in `uploads/`.

### Dashboard and Reports

- View product, sales, and revenue summaries.
- See low-stock alerts.
- Review sales history and sales reports.

## Project Structure

```text
POS/
├── index.php                 # Root entry point; redirects to the login page
├── pages/                    # Browser-facing PHP pages and endpoints
├── includes/
│   └── db.php                # MySQL connection
├── assets/
│   ├── media/                # Logo and login video
│   ├── sample-products/      # Demo product images
│   └── team/                 # Developer profile images
├── database/                 # SQL schema and migration files
└── uploads/                  # Product images uploaded by users
```

The PHP pages and endpoints live under `pages/`. Shared database configuration is in `includes/`. Files uploaded by the application continue to use paths under `uploads/`.

## Requirements

- PHP 7.4 or later, with the MySQLi extension
- MySQL or MariaDB
- Apache or another PHP-enabled web server (XAMPP is suitable)

## Setup

1. Place the project directory in the web server's document root, such as `C:\xampp\htdocs\POS`.
2. Create the `op_db` database and import [`database/schema.sql`](database/schema.sql) using your MySQL administration tool.
3. Set the database host, username, password, and database name in [`includes/db.php`](includes/db.php). The defaults are `localhost`, `root`, an empty password, and `op_db`.
4. Start Apache and MySQL, then open `http://localhost/POS/`. The root `index.php` redirects to the login page at `pages/index.php`.
5. Register an account to sign in.

## Main Pages and Endpoints

- `pages/index.php` — login page
- `pages/register.php` — account registration
- `pages/dashboard.php` — dashboard and navigation
- `pages/pos.php` — point of sale
- `pages/products.php` — product management and product creation
- `pages/inventory.php` — inventory dashboard
- `pages/update_stock.php?id=<product_id>` — edit a product and add stock
- `pages/sales_history.php` — sales history
- `pages/sales_report.php` — sales report
- `pages/get_sale_items.php` — sales-report item data endpoint
- `pages/logout.php` — end the current session

## Database Files

- `database/schema.sql` — application database schema
- `database/legacy-user-schema.sql` — legacy user-table SQL
- `database/sales-columns-migration.sql` — SQL for adding payment and change columns to an existing sales table

## Technologies

- PHP, HTML, CSS, and vanilla JavaScript
- MySQL or MariaDB

## License

This project is for educational and assignment purposes.
