<?php
// Add this line
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'sibalom_msrms');

// Site Configuration
define('SITE_NAME', 'Sibalom Market Stall Rental and Mapping System');
define('SITE_URL', 'http://localhost/sibalom_msrms/');
define('ADMIN_EMAIL', 'admin@sibalom.gov.ph');

// Path Configuration
define('BASE_PATH', dirname(__FILE__));
define('UPLOAD_PATH', BASE_PATH . '/uploads/');
define('DOCUMENTS_PATH', BASE_PATH . '/uploads/documents/');
define('IMAGES_PATH', BASE_PATH . '/uploads/images/');
define('QR_PATH', BASE_PATH . '/uploads/qr_codes/');

// Create directories if they don't exist
$directories = [UPLOAD_PATH, DOCUMENTS_PATH, IMAGES_PATH, QR_PATH];
foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }
}

// Session Configuration
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Auto-create database and tables if not exists
function autoSetupDatabase() {
    try {
        $conn = new PDO(
            "mysql:host=" . DB_HOST,
            DB_USER,
            DB_PASS,
            array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
        );
        
        // Create database
        $conn->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME);
        $conn->exec("USE " . DB_NAME);
        
        // Check if tables exist
        $result = $conn->query("SHOW TABLES LIKE 'users'");
        if ($result->rowCount() == 0) {
            createTables($conn);
        }
        
        $conn = null;
        return true;
    } catch(PDOException $e) {
        return false;
    }
}

function createTables($conn) {
    // Users table
    $conn->exec("CREATE TABLE IF NOT EXISTS users (
        user_id INT PRIMARY KEY AUTO_INCREMENT,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(100) NOT NULL,
        email VARCHAR(100),
        role ENUM('administrator', 'staff', 'vendor') NOT NULL,
        status ENUM('active', 'inactive') DEFAULT 'active',
        last_login DATETIME,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Tenants table (enhanced)
    $conn->exec("CREATE TABLE IF NOT EXISTS tenants (
        tenant_id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT,
        full_name VARCHAR(100) NOT NULL,
        email VARCHAR(100),
        contact_no VARCHAR(20) NOT NULL,
        address TEXT,
        business_name VARCHAR(100) NOT NULL,
        business_type VARCHAR(100),
        tenant_type ENUM('permanent', 'temporary') NOT NULL,
        status ENUM('active', 'inactive', 'pending') DEFAULT 'pending',
        balance DECIMAL(10,2) DEFAULT 0,
        profile_image VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
    )");
    
    // Market Stalls table
    $conn->exec("CREATE TABLE IF NOT EXISTS market_stalls (
        stall_id INT PRIMARY KEY AUTO_INCREMENT,
        stall_number VARCHAR(20) UNIQUE NOT NULL,
        section VARCHAR(50) NOT NULL,
        location VARCHAR(100),
        size_sqm DECIMAL(10,2),
        price_per_day DECIMAL(10,2) NOT NULL,
        price_per_month DECIMAL(10,2) NOT NULL,
        status ENUM('available', 'occupied', 'maintenance', 'reserved') DEFAULT 'available',
        description TEXT,
        image VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Rental Applications table (NEW)
    $conn->exec("CREATE TABLE IF NOT EXISTS rental_applications (
        application_id INT PRIMARY KEY AUTO_INCREMENT,
        tenant_id INT NOT NULL,
        stall_id INT NOT NULL,
        application_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
        notes TEXT,
        reviewed_by INT,
        reviewed_date DATETIME,
        FOREIGN KEY (tenant_id) REFERENCES tenants(tenant_id) ON DELETE CASCADE,
        FOREIGN KEY (stall_id) REFERENCES market_stalls(stall_id) ON DELETE CASCADE,
        FOREIGN KEY (reviewed_by) REFERENCES users(user_id) ON DELETE SET NULL
    )");
    
    // Application Documents table (NEW)
    $conn->exec("CREATE TABLE IF NOT EXISTS application_documents (
        document_id INT PRIMARY KEY AUTO_INCREMENT,
        application_id INT NOT NULL,
        document_type ENUM('barangay_clearance', 'business_permit', 'valid_id', 'cedula', 'other') NOT NULL,
        document_name VARCHAR(255) NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (application_id) REFERENCES rental_applications(application_id) ON DELETE CASCADE
    )");
    
    // Rental Records table
    $conn->exec("CREATE TABLE IF NOT EXISTS rental_records (
        record_id INT PRIMARY KEY AUTO_INCREMENT,
        stall_id INT NOT NULL,
        tenant_id INT NOT NULL,
        application_id INT,
        start_date DATE NOT NULL,
        end_date DATE,
        monthly_rate DECIMAL(10,2) NOT NULL,
        status ENUM('active', 'expired', 'terminated') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (stall_id) REFERENCES market_stalls(stall_id) ON DELETE CASCADE,
        FOREIGN KEY (tenant_id) REFERENCES tenants(tenant_id) ON DELETE CASCADE,
        FOREIGN KEY (application_id) REFERENCES rental_applications(application_id) ON DELETE SET NULL
    )");
    
    // Payments table (enhanced with QR code)
    $conn->exec("CREATE TABLE IF NOT EXISTS payments (
        payment_id INT PRIMARY KEY AUTO_INCREMENT,
        tenant_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        payment_date DATE NOT NULL,
        due_date DATE,
        payment_method ENUM('cash', 'bank_transfer', 'gcash', 'check', 'other') NOT NULL,
        reference_number VARCHAR(100),
        receipt_number VARCHAR(50) UNIQUE,
        qr_code VARCHAR(255),
        status ENUM('paid', 'partial', 'pending', 'overdue') DEFAULT 'pending',
        remarks TEXT,
        processed_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (tenant_id) REFERENCES tenants(tenant_id) ON DELETE CASCADE,
        FOREIGN KEY (processed_by) REFERENCES users(user_id) ON DELETE SET NULL
    )");
    
    // Maintenance Requests table
    $conn->exec("CREATE TABLE IF NOT EXISTS maintenance_requests (
        request_id INT PRIMARY KEY AUTO_INCREMENT,
        stall_id INT NOT NULL,
        tenant_id INT,
        title VARCHAR(200) NOT NULL,
        description TEXT NOT NULL,
        priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
        status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
        request_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        assigned_to INT,
        completion_date DATETIME,
        notes TEXT,
        created_by INT,
        FOREIGN KEY (stall_id) REFERENCES market_stalls(stall_id) ON DELETE CASCADE,
        FOREIGN KEY (tenant_id) REFERENCES tenants(tenant_id) ON DELETE SET NULL,
        FOREIGN KEY (assigned_to) REFERENCES users(user_id) ON DELETE SET NULL,
        FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL
    )");
    
    // Reports table
    $conn->exec("CREATE TABLE IF NOT EXISTS reports (
        report_id INT PRIMARY KEY AUTO_INCREMENT,
        report_name VARCHAR(200) NOT NULL,
        report_type VARCHAR(50) NOT NULL,
        date_from DATE,
        date_to DATE,
        generated_by INT NOT NULL,
        generated_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        file_path VARCHAR(255),
        status ENUM('generated', 'archived') DEFAULT 'generated',
        FOREIGN KEY (generated_by) REFERENCES users(user_id) ON DELETE CASCADE
    )");
    
    // Insert default admin user
    $admin_password = password_hash('admin123', PASSWORD_BCRYPT);
    $conn->exec("INSERT INTO users (username, password, full_name, email, role) VALUES 
        ('admin', '$admin_password', 'System Administrator', 'admin@sibalom.gov.ph', 'administrator'),
        ('staff01', '$admin_password', 'Staff Member', 'staff@sibalom.gov.ph', 'staff'),
        ('vendor01', '$admin_password', 'Sample Vendor', 'vendor@example.com', 'vendor')
    ");
}

// Run auto-setup
autoSetupDatabase();

// Database Connection Class
class Database {
    private static $instance = null;
    private $conn;
    
    private function __construct() {
        try {
            $this->conn = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
                DB_USER,
                DB_PASS,
                array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                )
            );
        } catch(PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new Database();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->conn;
    }
}

// Utility Functions
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function formatCurrency($amount) {
    return '₱' . number_format($amount, 2);
}

function formatDate($date) {
    return date('F d, Y', strtotime($date));
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

function getUserRole() {
    return $_SESSION['role'] ?? null;
}

function isAdmin() {
    return getUserRole() === 'administrator';
}

function isStaff() {
    return getUserRole() === 'staff' || isAdmin();
}

function isVendor() {
    return getUserRole() === 'vendor';
}

function checkPermission($required_role) {
    $role = getUserRole();
    $hierarchy = ['administrator' => 3, 'staff' => 2, 'vendor' => 1];
    
    if (!isset($hierarchy[$role]) || !isset($hierarchy[$required_role])) {
        return false;
    }
    
    return $hierarchy[$role] >= $hierarchy[$required_role];
}

function requirePermission($required_role) {
    if (!checkPermission($required_role)) {
        header('Location: dashboard.php?error=unauthorized');
        exit();
    }
}

function getDB() {
    return Database::getInstance()->getConnection();
}

function setMessage($type, $message) {
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $type;
}

function getMessage() {
    if (isset($_SESSION['message'])) {
        $message = $_SESSION['message'];
        $type = $_SESSION['message_type'];
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);
        return ['type' => $type, 'message' => $message];
    }
    return null;
}

// File Upload Handler
function handleFileUpload($file, $directory = UPLOAD_PATH, $allowed_types = ['jpg', 'jpeg', 'png', 'pdf']) {
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'message' => 'Invalid file upload'];
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Upload failed'];
    }
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowed_types)) {
        return ['success' => false, 'message' => 'Invalid file type'];
    }
    
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $filepath = $directory . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => false, 'message' => 'Failed to save file'];
    }
    
    return ['success' => true, 'filename' => $filename, 'filepath' => $filepath];
}

// QR Code Generator (requires QR code library or API)
function generatePaymentQR($payment_id, $amount, $tenant_name) {
    // Using Google Charts API for QR generation
    $qr_data = "Payment ID: $payment_id\nAmount: " . formatCurrency($amount) . "\nTenant: $tenant_name";
    $qr_url = "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=" . urlencode($qr_data);
    
    $qr_filename = "qr_payment_" . $payment_id . ".png";
    $qr_filepath = QR_PATH . $qr_filename;
    
    // Download and save QR code
    $qr_image = file_get_contents($qr_url);
    if ($qr_image !== false) {
        file_put_contents($qr_filepath, $qr_image);
        return $qr_filename;
    }
    
    return null;
}
function requireApprovedAccount() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
    
    $db = getDB();
    $stmt = $db->prepare("SELECT status FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if ($user['status'] == 'pending') {
        // Only allow access to limited pages
        $allowed_pages = ['dashboard.php', 'vendorapplication.php', 'my-applications.php', 'logout.php'];
        $current_page = basename($_SERVER['PHP_SELF']);
        
        if (!in_array($current_page, $allowed_pages)) {
            setMessage('warning', 'Your account is pending approval. You can only access the application page.');
            header('Location: vendorapplication.php');
            exit();
        }
    }
}
?>