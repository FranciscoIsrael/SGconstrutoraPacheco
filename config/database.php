<?php
// Database configuration and connection
class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        try {
            // Get database connection details from environment
            $host = $_ENV['PGHOST'] ?? 'localhost';
            $port = $_ENV['PGPORT'] ?? '5432';
            $dbname = $_ENV['PGDATABASE'] ?? 'construction_db';
            $username = $_ENV['PGUSER'] ?? 'postgres';
            $password = $_ENV['PGPASSWORD'] ?? '';

            $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";
            
            $this->pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
            
            $this->initializeTables();
        } catch (PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->pdo;
    }

    private function initializeTables() {
        $tables = [
            // Projects table
            "CREATE TABLE IF NOT EXISTS projects (
                id SERIAL PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                address TEXT NOT NULL,
                deadline DATE NOT NULL,
                budget DECIMAL(15,2) NOT NULL DEFAULT 0,
                manager VARCHAR(255) NOT NULL,
                status VARCHAR(50) DEFAULT 'active',
                image_path TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",

            // Materials table
            "CREATE TABLE IF NOT EXISTS materials (
                id SERIAL PRIMARY KEY,
                project_id INTEGER REFERENCES projects(id) ON DELETE CASCADE,
                name VARCHAR(255) NOT NULL,
                quantity DECIMAL(10,2) NOT NULL,
                unit VARCHAR(50) NOT NULL DEFAULT 'unidade',
                cost DECIMAL(10,2) NOT NULL DEFAULT 0,
                image_path TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",

            // Financial transactions table
            "CREATE TABLE IF NOT EXISTS transactions (
                id SERIAL PRIMARY KEY,
                project_id INTEGER REFERENCES projects(id) ON DELETE CASCADE,
                type VARCHAR(20) NOT NULL CHECK (type IN ('expense', 'revenue')),
                description TEXT NOT NULL,
                amount DECIMAL(15,2) NOT NULL,
                transaction_date DATE NOT NULL DEFAULT CURRENT_DATE,
                image_path TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",

            // Team members table
            "CREATE TABLE IF NOT EXISTS team_members (
                id SERIAL PRIMARY KEY,
                project_id INTEGER REFERENCES projects(id) ON DELETE CASCADE,
                name VARCHAR(255) NOT NULL,
                role VARCHAR(100) NOT NULL,
                hourly_rate DECIMAL(8,2) NOT NULL DEFAULT 0,
                image_path TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",

            // Inventory table (company-wide stock)
            "CREATE TABLE IF NOT EXISTS inventory (
                id SERIAL PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                description TEXT,
                quantity DECIMAL(10,2) NOT NULL DEFAULT 0,
                unit VARCHAR(50) NOT NULL DEFAULT 'unidade',
                unit_cost DECIMAL(10,2) NOT NULL DEFAULT 0,
                min_quantity DECIMAL(10,2) DEFAULT 0,
                image_path TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",

            // Inventory movements history (tracking shipments to clients/projects)
            "CREATE TABLE IF NOT EXISTS inventory_movements (
                id SERIAL PRIMARY KEY,
                inventory_id INTEGER REFERENCES inventory(id) ON DELETE CASCADE,
                project_id INTEGER REFERENCES projects(id) ON DELETE SET NULL,
                movement_type VARCHAR(20) NOT NULL CHECK (movement_type IN ('in', 'out')),
                quantity DECIMAL(10,2) NOT NULL,
                destination VARCHAR(255),
                notes TEXT,
                movement_date DATE NOT NULL DEFAULT CURRENT_DATE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",

            // Audit history table (tracking all changes)
            "CREATE TABLE IF NOT EXISTS audit_history (
                id SERIAL PRIMARY KEY,
                table_name VARCHAR(100) NOT NULL,
                record_id INTEGER NOT NULL,
                action VARCHAR(20) NOT NULL CHECK (action IN ('create', 'update', 'delete')),
                field_name VARCHAR(100),
                old_value TEXT,
                new_value TEXT,
                changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )"
        ];

        foreach ($tables as $sql) {
            $this->pdo->exec($sql);
        }
        
        // Add image_path columns to existing tables if they don't exist
        $alterStatements = [
            "ALTER TABLE projects ADD COLUMN IF NOT EXISTS image_path TEXT",
            "ALTER TABLE materials ADD COLUMN IF NOT EXISTS image_path TEXT",
            "ALTER TABLE transactions ADD COLUMN IF NOT EXISTS image_path TEXT",
            "ALTER TABLE team_members ADD COLUMN IF NOT EXISTS image_path TEXT"
        ];
        
        foreach ($alterStatements as $sql) {
            try {
                $this->pdo->exec($sql);
            } catch (PDOException $e) {
                // Column might already exist, ignore error
            }
        }
    }
}

// Helper function to get database connection
function getDB() {
    return Database::getInstance()->getConnection();
}
?>