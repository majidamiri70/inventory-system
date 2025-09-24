-- ایجاد دیتابیس
CREATE DATABASE IF NOT EXISTS inventory_accounting CHARACTER SET utf8mb4 COLLATE utf8mb4_persian_ci;
USE inventory_accounting;

-- جدول تنظیمات
CREATE TABLE settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_name VARCHAR(255) NOT NULL DEFAULT 'سیستم مدیریت انبار',
    profit_margin DECIMAL(5,2) NOT NULL DEFAULT 20.00,
    tax_rate DECIMAL(5,2) NOT NULL DEFAULT 9.00,
    logo_path VARCHAR(500),
    address TEXT,
    phone VARCHAR(20),
    email VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- جدول کاربران
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'user') DEFAULT 'user',
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- جدول محصولات
CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    sku VARCHAR(100) UNIQUE NOT NULL,
    purchase_price DECIMAL(15,2) NOT NULL,
    selling_price DECIMAL(15,2) NOT NULL,
    stock INT NOT NULL DEFAULT 0,
    min_stock INT NOT NULL DEFAULT 5,
    description TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_sku (sku),
    INDEX idx_stock (stock)
);

-- جدول مشتریان
CREATE TABLE customers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    notes TEXT, -- ✅ فیلد جدید برای توضیحات
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_name (name)
);

-- جدول فاکتورها
CREATE TABLE invoices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT,
    invoice_date DATE NOT NULL,
    subtotal DECIMAL(15,2) NOT NULL DEFAULT 0,
    tax DECIMAL(15,2) NOT NULL DEFAULT 0,
    discount DECIMAL(15,2) NOT NULL DEFAULT 0,
    total DECIMAL(15,2) NOT NULL DEFAULT 0,
    status ENUM('pending', 'paid') DEFAULT 'pending',
    notes TEXT,
    created_by INT, -- ✅ ثبت‌کننده فاکتور
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_date (invoice_date),
    INDEX idx_status (status)
);

-- جدول آیتم‌های فاکتور
CREATE TABLE invoice_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    invoice_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(15,2) NOT NULL,
    total_price DECIMAL(15,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    INDEX idx_invoice (invoice_id)
);

-- جدول چک‌ها
CREATE TABLE checks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    type ENUM('received', 'issued') NOT NULL,
    check_number VARCHAR(100) NOT NULL,
    bank_name VARCHAR(255) NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    issue_date DATE,
    due_date DATE NOT NULL,
    customer_id INT,
    status ENUM('pending', 'cleared', 'bounced') DEFAULT 'pending',
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_check_number (check_number),
    INDEX idx_due_date (due_date),
    INDEX idx_status (status)
);

-- جدول اعلان‌ها
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'warning', 'danger', 'success') DEFAULT 'info',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_read (user_id, is_read)
);

-- جدول لاگ ورود
CREATE TABLE login_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    success BOOLEAN NOT NULL,
    message VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_created_at (created_at)
);

-- درج داده‌های اولیه
INSERT INTO settings (company_name, profit_margin, tax_rate) 
VALUES ('سیستم مدیریت انبار', 20.00, 9.00);

INSERT INTO users (username, password, full_name, role) 
VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'مدیر سیستم', 'admin'),
('user', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'کاربر نمونه', 'user');