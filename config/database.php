<?php
class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        try {
            $databaseUrl = getenv('DATABASE_URL');
            
            if ($databaseUrl) {
                $url = parse_url($databaseUrl);
                $host = $url['host'] ?? 'localhost';
                $port = $url['port'] ?? '5432';
                $dbname = ltrim($url['path'] ?? '/postgres', '/');
                $username = $url['user'] ?? 'postgres';
                $password = $url['pass'] ?? '';
            } else {
                $host = getenv('PGHOST') ?: 'localhost';
                $port = getenv('PGPORT') ?: '5432';
                $dbname = getenv('PGDATABASE') ?: 'postgres';
                $username = getenv('PGUSER') ?: 'postgres';
                $password = getenv('PGPASSWORD') ?: '';
            }

            $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";
            if (strpos($host, 'neon.tech') !== false || strpos($host, 'aws') !== false) {
                $dsn .= ";sslmode=require";
            }
            
            $this->pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
            
            $this->initializeTables();
        } catch (PDOException $e) {
            header('Content-Type: application/json');
            die(json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]));
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
            "CREATE TABLE IF NOT EXISTS projects (
                id SERIAL PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                address TEXT NOT NULL,
                deadline DATE NOT NULL,
                budget DECIMAL(15,2) NOT NULL DEFAULT 0,
                manager VARCHAR(255) NOT NULL,
                status VARCHAR(50) DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",

            "CREATE TABLE IF NOT EXISTS images (
                id SERIAL PRIMARY KEY,
                table_name VARCHAR(100) NOT NULL,
                record_id INTEGER NOT NULL,
                file_path TEXT NOT NULL,
                file_name VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",

            "CREATE TABLE IF NOT EXISTS materials (
                id SERIAL PRIMARY KEY,
                project_id INTEGER REFERENCES projects(id) ON DELETE CASCADE,
                name VARCHAR(255) NOT NULL,
                description TEXT,
                quantity DECIMAL(10,2) NOT NULL,
                unit VARCHAR(50) NOT NULL DEFAULT 'unidade',
                cost DECIMAL(10,2) NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",

            "CREATE TABLE IF NOT EXISTS transactions (
                id SERIAL PRIMARY KEY,
                project_id INTEGER REFERENCES projects(id) ON DELETE CASCADE,
                type VARCHAR(20) NOT NULL CHECK (type IN ('expense', 'revenue')),
                description TEXT NOT NULL,
                amount DECIMAL(15,2) NOT NULL,
                transaction_date DATE NOT NULL DEFAULT CURRENT_DATE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",

            "CREATE TABLE IF NOT EXISTS team_members (
                id SERIAL PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                cpf_cnpj VARCHAR(20),
                role VARCHAR(100) NOT NULL,
                payment_type VARCHAR(50) DEFAULT 'hourly',
                payment_value DECIMAL(10,2) NOT NULL DEFAULT 0,
                description TEXT,
                address TEXT,
                phone VARCHAR(20),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",

            "CREATE TABLE IF NOT EXISTS team_member_projects (
                id SERIAL PRIMARY KEY,
                team_member_id INTEGER REFERENCES team_members(id) ON DELETE CASCADE,
                project_id INTEGER REFERENCES projects(id) ON DELETE CASCADE,
                assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(team_member_id, project_id)
            )",

            "CREATE TABLE IF NOT EXISTS inventory (
                id SERIAL PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                description TEXT,
                quantity DECIMAL(10,2) NOT NULL DEFAULT 0,
                unit VARCHAR(50) NOT NULL DEFAULT 'unidade',
                unit_cost DECIMAL(10,2) NOT NULL DEFAULT 0,
                min_quantity DECIMAL(10,2) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",

            "CREATE TABLE IF NOT EXISTS inventory_movements (
                id SERIAL PRIMARY KEY,
                transaction_code VARCHAR(20) UNIQUE NOT NULL,
                inventory_id INTEGER REFERENCES inventory(id) ON DELETE CASCADE,
                project_id INTEGER REFERENCES projects(id) ON DELETE SET NULL,
                movement_type VARCHAR(20) NOT NULL CHECK (movement_type IN ('in', 'out')),
                quantity DECIMAL(10,2) NOT NULL,
                destination VARCHAR(255),
                notes TEXT,
                movement_date DATE NOT NULL DEFAULT CURRENT_DATE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",

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
    }
}

function getDB() {
    return Database::getInstance()->getConnection();
}
?>
