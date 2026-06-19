<?php
namespace Bot\Core;

use Bot\Models\BotConfig;

class Config
{
    private static ?Config $instance = null;
    private array $config;
    private bool $dbAvailable = false;
    private bool $dbChecked = false;

    private function __construct()
    {
        $this->config = $_ENV;
        Logger::debug("Config initialized with environment variables");
    }

    public static function getInstance(): Config
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getToken(): string
    {
        Logger::debug("Getting bot token");
        
        try {
            if ($this->isDatabaseAvailable()) {
                $token = BotConfig::where('key', 'token')->value('value');
                if (!empty($token)) {
                    Logger::debug("Token loaded from database");
                    return $token;
                }
                Logger::debug("Token not found in database, checking env");
            }
        } catch (\Throwable $e) {
            Logger::debug("Database not available for token: " . $e->getMessage());
        }
        
        $token = $this->config['TOKEN'] ?? '';
        if (!empty($token)) {
            Logger::debug("Token loaded from environment");
        } else {
            Logger::error("Token not found in database or environment");
        }
        return $token;
    }

    public function getApiUrl(): string
    {
        Logger::debug("Getting API URL");
        
        try {
            if ($this->isDatabaseAvailable()) {
                $apiUrl = BotConfig::where('key', 'api_url')->value('value');
                if (!empty($apiUrl)) {
                    Logger::debug("API URL loaded from database");
                    return $apiUrl;
                }
            }
        } catch (\Throwable $e) {
            Logger::debug("Database not available for API URL: " . $e->getMessage());
        }
        
        $apiUrl = $this->config['API_URL'] ?? 'https://api.telegram.org/bot';
        Logger::debug("API URL loaded from environment or default");
        return $apiUrl;
    }

    public function isDebugMode(): bool
    {
        try {
            if ($this->isDatabaseAvailable()) {
                $debugMode = BotConfig::where('key', 'debug_mode')->value('value');
                if ($debugMode !== null) {
                    return (bool) $debugMode;
                }
            }
        } catch (\Throwable $e) {
            // Database not ready
        }
        
        return filter_var($this->config['DEBUG_MODE'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    public function getOwners(): array
    {
        $owners = $this->config['OWNERS'] ?? '';
        if (empty($owners)) {
            Logger::warning("No owners configured in environment");
            return [];
        }
        $ownerIds = array_map('intval', explode(',', $owners));
        Logger::debug("Owners loaded: " . implode(', ', $ownerIds));
        return $ownerIds;
    }

    public function getDatabaseConfig(): array
    {
        $dbType = $this->config['DB_TYPE'] ?? 'sqlite';
        $databasePath = $this->config['DB_DATABASE'] ?? 'test.sqlite';
        
        // Ensure database is in root
        if (!str_starts_with($databasePath, '/') && !str_starts_with($databasePath, __DIR__)) {
            $databasePath = __DIR__ . '/../../' . $databasePath;
        }
        
        $config = [
            'driver' => $dbType === 'sqlite' ? 'sqlite' : 'mysql',
            'database' => $databasePath,
            'prefix' => '',
        ];
        
        if ($dbType !== 'sqlite') {
            $config['host'] = $this->config['DB_HOST'] ?? 'localhost';
            $config['port'] = (int) ($this->config['DB_PORT'] ?? 3306);
            $config['username'] = $this->config['DB_USERNAME'] ?? '';
            $config['password'] = $this->config['DB_PASSWORD'] ?? '';
            $config['charset'] = 'utf8';
            $config['collation'] = 'utf8_unicode_ci';
        }
        
        Logger::debug("Database config loaded", ['driver' => $config['driver'], 'path' => $config['database']]);
        return $config;
    }

    private function isDatabaseAvailable(): bool
    {
        if ($this->dbChecked) {
            return $this->dbAvailable;
        }
        
        $this->dbChecked = true;
        
        try {
            $dbConfig = $this->getDatabaseConfig();
            
            if ($dbConfig['driver'] === 'sqlite') {
                $dbFile = $dbConfig['database'];
                if (file_exists($dbFile)) {
                    $this->dbAvailable = true;
                    Logger::debug("SQLite database file exists: " . $dbFile);
                } else {
                    Logger::warning("SQLite database file not found: " . $dbFile);
                    $this->dbAvailable = false;
                }
            } else {
                Logger::debug("MySQL database assumed available");
                $this->dbAvailable = true;
            }
        } catch (\Throwable $e) {
            Logger::error("Failed to check database availability: " . $e->getMessage());
            $this->dbAvailable = false;
        }
        
        return $this->dbAvailable;
    }

    public function get(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }
}
