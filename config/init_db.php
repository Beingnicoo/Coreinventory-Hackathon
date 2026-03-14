<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->query("CREATE DATABASE IF NOT EXISTS coreinventory");
$conn->select_db("coreinventory");

$tables = [
    "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        sku VARCHAR(50) UNIQUE NOT NULL,
        category VARCHAR(100) NOT NULL,
        unit VARCHAR(20) DEFAULT 'pcs',
        stock INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS warehouses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        location VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS stock_ledger (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        transaction_type ENUM('receipt', 'delivery', 'transfer', 'adjustment') NOT NULL,
        quantity INT NOT NULL,
        reference VARCHAR(100),
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id)
    )",
    "CREATE TABLE IF NOT EXISTS receipts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        supplier VARCHAR(255),
        quantity INT NOT NULL,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id)
    )",
    "CREATE TABLE IF NOT EXISTS deliveries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        customer VARCHAR(255),
        quantity INT NOT NULL,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id)
    )",
    "CREATE TABLE IF NOT EXISTS transfers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        from_warehouse_id INT,
        to_warehouse_id INT,
        quantity INT NOT NULL,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id)
    )",
    "CREATE TABLE IF NOT EXISTS adjustments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        previous_stock INT NOT NULL,
        counted_stock INT NOT NULL,
        difference INT NOT NULL,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id)
    )",
    // Suppliers table
    "CREATE TABLE IF NOT EXISTS suppliers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        code VARCHAR(50) UNIQUE,
        contact_person VARCHAR(255),
        email VARCHAR(100),
        phone VARCHAR(50),
        address TEXT,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",
    // Customers table
    "CREATE TABLE IF NOT EXISTS customers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        code VARCHAR(50) UNIQUE,
        contact_person VARCHAR(255),
        email VARCHAR(100),
        phone VARCHAR(50),
        address TEXT,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",
    // Purchase Orders table
    "CREATE TABLE IF NOT EXISTS purchase_orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        po_number VARCHAR(50) UNIQUE NOT NULL,
        supplier_id INT NOT NULL,
        order_date DATE NOT NULL,
        expected_date DATE,
        status ENUM('draft', 'confirmed', 'received', 'cancelled') DEFAULT 'draft',
        total_amount DECIMAL(10,2) DEFAULT 0.00,
        notes TEXT,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
        FOREIGN KEY (created_by) REFERENCES users(id)
    )",
    // Purchase Order Items table
    "CREATE TABLE IF NOT EXISTS purchase_order_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        po_id INT NOT NULL,
        product_id INT NOT NULL,
        quantity INT NOT NULL,
        unit_price DECIMAL(10,2) DEFAULT 0.00,
        received_quantity INT DEFAULT 0,
        FOREIGN KEY (po_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id)
    )",
    // Sales Orders table
    "CREATE TABLE IF NOT EXISTS sales_orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        so_number VARCHAR(50) UNIQUE NOT NULL,
        customer_id INT NOT NULL,
        order_date DATE NOT NULL,
        delivery_date DATE,
        status ENUM('draft', 'confirmed', 'delivered', 'cancelled') DEFAULT 'draft',
        total_amount DECIMAL(10,2) DEFAULT 0.00,
        notes TEXT,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (customer_id) REFERENCES customers(id),
        FOREIGN KEY (created_by) REFERENCES users(id)
    )",
    // Sales Order Items table
    "CREATE TABLE IF NOT EXISTS sales_order_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        so_id INT NOT NULL,
        product_id INT NOT NULL,
        quantity INT NOT NULL,
        unit_price DECIMAL(10,2) DEFAULT 0.00,
        delivered_quantity INT DEFAULT 0,
        FOREIGN KEY (so_id) REFERENCES sales_orders(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id)
    )",
    // Batches/Lots table
    "CREATE TABLE IF NOT EXISTS batches (
        id INT AUTO_INCREMENT PRIMARY KEY,
        batch_number VARCHAR(100) UNIQUE NOT NULL,
        product_id INT NOT NULL,
        quantity INT NOT NULL,
        expiry_date DATE,
        manufacturing_date DATE,
        warehouse_id INT,
        status ENUM('active', 'expired', 'consumed') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id),
        FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
    )",
    // Product costing table
    "CREATE TABLE IF NOT EXISTS product_costs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        cost_method ENUM('fifo', 'lifo', 'average', 'standard') DEFAULT 'average',
        unit_cost DECIMAL(10,2) DEFAULT 0.00,
        last_purchase_cost DECIMAL(10,2) DEFAULT 0.00,
        average_cost DECIMAL(10,2) DEFAULT 0.00,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    )",
    // Reorder points table
    "CREATE TABLE IF NOT EXISTS reorder_points (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        warehouse_id INT,
        min_stock INT NOT NULL DEFAULT 10,
        max_stock INT NOT NULL DEFAULT 100,
        reorder_quantity INT NOT NULL DEFAULT 50,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
        FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
    )",
    // User roles table
    "CREATE TABLE IF NOT EXISTS user_roles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        role ENUM('admin', 'manager', 'operator', 'viewer') DEFAULT 'operator',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )",
    // Audit logs table
    "CREATE TABLE IF NOT EXISTS audit_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        action VARCHAR(100) NOT NULL,
        table_name VARCHAR(50),
        record_id INT,
        old_values TEXT,
        new_values TEXT,
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )",
    // Stock valuation table
    "CREATE TABLE IF NOT EXISTS stock_valuations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        warehouse_id INT,
        quantity INT NOT NULL,
        unit_cost DECIMAL(10,2) NOT NULL,
        total_value DECIMAL(12,2) NOT NULL,
        valuation_date DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id),
        FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
    )"
];

foreach ($tables as $sql) {
    $conn->query($sql);
}

// Insert default data
$conn->query("INSERT IGNORE INTO warehouses (name, location) VALUES ('Main Warehouse', 'Building A')");

// Helper function to check if column exists
function columnExists($conn, $table, $column) {
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result->num_rows > 0;
}

// Add new columns to existing tables if they don't exist
$alterQueries = [
    ['products', 'cost_price', "ALTER TABLE products ADD COLUMN cost_price DECIMAL(10,2) DEFAULT 0.00"],
    ['products', 'selling_price', "ALTER TABLE products ADD COLUMN selling_price DECIMAL(10,2) DEFAULT 0.00"],
    ['products', 'reorder_point', "ALTER TABLE products ADD COLUMN reorder_point INT DEFAULT 10"],
    ['products', 'barcode', "ALTER TABLE products ADD COLUMN barcode VARCHAR(100)"],
    ['products', 'track_batch', "ALTER TABLE products ADD COLUMN track_batch BOOLEAN DEFAULT FALSE"],
    ['stock_ledger', 'warehouse_id', "ALTER TABLE stock_ledger ADD COLUMN warehouse_id INT"],
    ['stock_ledger', 'batch_id', "ALTER TABLE stock_ledger ADD COLUMN batch_id INT"],
    ['stock_ledger', 'unit_cost', "ALTER TABLE stock_ledger ADD COLUMN unit_cost DECIMAL(10,2) DEFAULT 0.00"],
    ['receipts', 'supplier_id', "ALTER TABLE receipts ADD COLUMN supplier_id INT"],
    ['receipts', 'po_id', "ALTER TABLE receipts ADD COLUMN po_id INT"],
    ['receipts', 'batch_id', "ALTER TABLE receipts ADD COLUMN batch_id INT"],
    ['receipts', 'unit_cost', "ALTER TABLE receipts ADD COLUMN unit_cost DECIMAL(10,2) DEFAULT 0.00"],
    ['deliveries', 'customer_id', "ALTER TABLE deliveries ADD COLUMN customer_id INT"],
    ['deliveries', 'so_id', "ALTER TABLE deliveries ADD COLUMN so_id INT"],
    ['deliveries', 'batch_id', "ALTER TABLE deliveries ADD COLUMN batch_id INT"],
    ['transfers', 'batch_id', "ALTER TABLE transfers ADD COLUMN batch_id INT"],
    ['users', 'role', "ALTER TABLE users ADD COLUMN role ENUM('admin', 'manager', 'operator', 'viewer') DEFAULT 'operator'"]
];

foreach ($alterQueries as $alter) {
    if (!columnExists($conn, $alter[0], $alter[1])) {
        $conn->query($alter[2]);
    }
}

echo "Database initialized successfully with all advanced features. <a href='../index.php'>Go to Home</a>";
