# CoreInventory

A complete inventory management system built with PHP, MySQL, Bootstrap, and Chart.js.

## Features

- **Login / Register** - User authentication
- **Dashboard** - KPIs (Total Products, Low Stock, Pending Receipts, Pending Deliveries, Internal Transfers), Chart.js charts (Stock Movement, Product Categories), Low Stock Alert
- **Product Management** - CRUD with add, edit, delete, list. Fields: name, sku, category, unit, stock
- **Receipts** - Stock In: select product, add supplier, enter quantity. Updates products and stock_ledger
- **Delivery Orders** - Stock Out: select product, customer, quantity. Prevents delivery if insufficient stock
- **Internal Transfers** - Record transfers between warehouses
- **Stock Adjustments** - Enter counted stock, system calculates difference and updates
- **Stock Ledger** - Full transaction history with filters
- **Warehouses** - Warehouse list

## Stack

- **Frontend:** HTML, CSS, JavaScript, Bootstrap 5, Bootstrap Icons, Chart.js
- **Backend:** PHP
- **Database:** MySQL

## Setup

1. Ensure PHP and MySQL are installed (e.g. XAMPP, WAMP, or Laragon).
2. Update `config/database.php` with your MySQL credentials (host, user, password).
3. Run the database initialization:
   - Open `http://localhost/odoo_web/config/init_db.php` in your browser
   - Or run it once to create the database and tables
4. Register a new account at `register.php` and log in.

## Project Structure

```
CoreInventory/
├── index.php
├── login.php
├── register.php
├── logout.php
├── dashboard/dashboard.php
├── products/
│   ├── add_product.php
│   ├── edit_product.php
│   ├── delete_product.php
│   └── product_list.php
├── operations/
│   ├── receipts.php
│   ├── delivery.php
│   ├── transfers.php
│   ├── adjustments.php
│   └── stock_ledger.php
├── warehouse/warehouse_list.php
├── config/database.php
├── config/init_db.php
├── includes/header.php, auth.php, footer.php
└── assets/css, js, images
```

## Default Database

- **Database name:** coreinventory
- **Tables:** users, products, warehouses, stock_ledger, receipts, deliveries, transfers, adjustments
