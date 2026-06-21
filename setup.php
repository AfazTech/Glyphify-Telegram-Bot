<?php

require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
use Bot\Core\Logger;
use Illuminate\Database\Capsule\Manager as Capsule;
use Bot\Models\BotConfig;
use Bot\Models\User;
use Bot\Models\JoinMandatoryChannel;

if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

Logger::init(__DIR__ . '/logs/setup.log', true);
Logger::info("=== SETUP SCRIPT STARTED ===");

echo "========================================\n";
echo "TELEGRAM BOT - SETUP SCRIPT\n";
echo "========================================\n\n";

$dbType = $_ENV['DB_TYPE'] ?? 'sqlite';
$databaseName = $_ENV['DB_DATABASE'] ?? 'test.sqlite';

// اگر نام دیتابیس شامل "/" باشد، فقط نام دیتابیس را استخراج می‌کنیم
if ($dbType !== 'sqlite' && str_contains($databaseName, '/')) {
    $databaseName = basename($databaseName);
    echo "   ℹ️ Fixed database name: $databaseName\n";
}

echo "1. Connecting to database...\n";
Logger::info("Connecting to database", ['driver' => $dbType, 'database' => $databaseName]);

try {
    $capsule = new Capsule();
    
    if ($dbType === 'sqlite') {
        // برای SQLite مسیر فایل را درست می‌کنیم
        if (!str_starts_with($databaseName, '/')) {
            $databaseName = __DIR__ . '/' . $databaseName;
        }
        
        $databaseDir = dirname($databaseName);
        if (!is_dir($databaseDir)) {
            mkdir($databaseDir, 0755, true);
            echo "   ✓ Database directory created\n";
            Logger::info("Database directory created", ['dir' => $databaseDir]);
        }
        
        if (!file_exists($databaseName)) {
            touch($databaseName);
            chmod($databaseName, 0666);
            echo "   ✓ Database file created\n";
            Logger::info("Database file created", ['file' => $databaseName]);
        }
        
        $config = [
            'driver' => 'sqlite',
            'database' => $databaseName,
            'charset' => 'utf8',
            'collation' => 'utf8_unicode_ci',
        ];
    } else {
        // MySQL / MariaDB
        $config = [
            'driver' => 'mysql',
            'host' => $_ENV['DB_HOST'] ?? 'localhost',
            'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
            'database' => $databaseName,
            'username' => $_ENV['DB_USERNAME'] ?? '',
            'password' => $_ENV['DB_PASSWORD'] ?? '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => 'InnoDB',
        ];
    }
    
    $capsule->addConnection($config);
    $capsule->setAsGlobal();
    $capsule->bootEloquent();
    
    echo "   ✓ Database connected\n\n";
    Logger::info("Database connected successfully");
} catch (\Exception $e) {
    echo "   ❌ Database connection failed: " . $e->getMessage() . "\n";
    Logger::error("Database connection failed", ['error' => $e->getMessage()]);
    exit(1);
}

echo "2. Creating tables...\n";
Logger::info("Creating database tables");

try {
    $tables = ['bot_config', 'users', 'join_mandatory_channels'];
    foreach ($tables as $table) {
        if (Capsule::schema()->hasTable($table)) {
            echo "   ○ Table already exists: $table\n";
            Logger::debug("Table already exists", ['table' => $table]);
        }
    }

    if (!Capsule::schema()->hasTable('bot_config')) {
        Capsule::schema()->create('bot_config', function ($table) {
            $table->increments('id');
            $table->string('key', 64)->unique();
            $table->text('value');
            $table->timestamps();
        });
        echo "   ✓ Table created: bot_config\n";
        Logger::info("Table created: bot_config");
    }

    if (!Capsule::schema()->hasTable('users')) {
        Capsule::schema()->create('users', function ($table) {
            $table->increments('id');
            $table->bigInteger('user_id')->unique();
            $table->boolean('is_admin')->default(false);
            $table->string('username', 64)->nullable();
            $table->string('first_name', 64)->nullable();
            $table->string('last_name', 64)->nullable();
            $table->boolean('status')->default(true);
            $table->boolean('blocked')->default(false);
            $table->datetime('blocked_until')->nullable();
            $table->string('block_reason', 255)->nullable();
            $table->boolean('join_mandatory_channels')->default(false);
            $table->string('step', 255)->nullable();
            $table->json('temp')->nullable();
            $table->string('language', 10)->default('fa');
            $table->timestamps();
        });
        echo "   ✓ Table created: users\n";
        Logger::info("Table created: users");
    }

    if (!Capsule::schema()->hasTable('join_mandatory_channels')) {
        Capsule::schema()->create('join_mandatory_channels', function ($table) {
            $table->increments('id');
            $table->bigInteger('chat_id')->unique();
            $table->string('link', 255)->nullable();
            $table->string('title', 255)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        echo "   ✓ Table created: join_mandatory_channels\n";
        Logger::info("Table created: join_mandatory_channels");
    }
    
    echo "\n";
} catch (\Exception $e) {
    echo "   ❌ Error creating tables: " . $e->getMessage() . "\n";
    Logger::error("Error creating tables", ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    exit(1);
}

echo "3. Adding default configurations...\n";
Logger::info("Adding default configurations");

try {
    $defaults = [
        'token' => $_ENV['TOKEN'] ?? '',
        'api_url' => $_ENV['API_URL'] ?? 'https://api.telegram.org/bot',
        'debug_mode' => ($_ENV['DEBUG_MODE'] ?? 'false') === 'true' ? '1' : '0',
        'api_token' => $_ENV['API_TOKEN'] ?? 'token_' . bin2hex(random_bytes(24)),
        'spam_max_messages' => '5',
        'spam_block_duration' => '3600',
        'ads_random_enabled' => '0',
        'ads_random_is_forward' => '0',
        'ads_random_chat_id' => '',
        'ads_random_message_id' => '',
        'ads_random_chance' => '50',
        'ads_menu_enabled' => '0',
        'ads_menu_is_forward' => '0',
        'ads_menu_chat_id' => '',
        'ads_menu_message_id' => '',
        'broadcast_enabled' => '0',
        'broadcast_is_forward' => '0',
        'broadcast_chat_id' => '',
        'broadcast_message_id' => '',
        'broadcast_last_user_id' => '0',
        'maintenance_mode' => '0',
        'maintenance_message' => 'Bot is under maintenance. Please check back later.',
    ];

    $addedCount = 0;
    foreach ($defaults as $key => $value) {
        $exists = BotConfig::where('key', $key)->exists();
        if (!$exists) {
            BotConfig::create(['key' => $key, 'value' => $value]);
            $addedCount++;
            $displayValue = strlen($value) > 30 ? substr($value, 0, 20) . '...' : $value;
            echo "   ✓ $key = $displayValue\n";
            Logger::debug("Config added", ['key' => $key]);
        }
    }

    if ($addedCount === 0) {
        echo "   ○ All configurations already exist\n";
    } else {
        echo "   ✓ $addedCount new configurations added\n";
    }
    Logger::info("Configurations processed", ['added' => $addedCount]);
    echo "\n";
} catch (\Exception $e) {
    echo "   ❌ Error adding configurations: " . $e->getMessage() . "\n";
    Logger::error("Error adding configurations", ['error' => $e->getMessage()]);
    exit(1);
}

echo "4. Adding owners as admins...\n";
Logger::info("Processing owners");

try {
    $owners = $_ENV['OWNERS'] ?? '';
    if (!empty($owners)) {
        $ownerIds = array_map('intval', explode(',', $owners));
        foreach ($ownerIds as $ownerId) {
            $user = User::where('user_id', $ownerId)->first();
            if (!$user) {
                User::create([
                    'user_id' => $ownerId,
                    'is_admin' => true,
                    'status' => true,
                    'blocked' => false,
                ]);
                echo "   ✓ Owner added: $ownerId\n";
                Logger::info("Owner added", ['user_id' => $ownerId]);
            } else {
                $user->is_admin = true;
                $user->save();
                echo "   ○ Owner already exists: $ownerId\n";
                Logger::debug("Owner already exists", ['user_id' => $ownerId]);
            }
        }
    } else {
        echo "   ⚠ WARNING: No OWNERS configured in .env\n";
        Logger::warning("No owners configured");
    }
    echo "\n";
} catch (\Exception $e) {
    echo "   ❌ Error processing owners: " . $e->getMessage() . "\n";
    Logger::error("Error processing owners", ['error' => $e->getMessage()]);
    exit(1);
}

echo "5. Verifying bot token...\n";
Logger::info("Verifying bot token");

try {
    $token = BotConfig::where('key', 'token')->value('value');
    if (empty($token)) {
        echo "   ❌ ERROR: Bot token not set in database!\n";
        echo "   ➜ Set token using:\n";
        echo "     php -r \"use Bot\\Models\\BotConfig; require 'vendor/autoload.php'; BotConfig::updateOrCreate(['key' => 'token'], ['value' => 'YOUR_TOKEN']);\"\n";
        Logger::error("Bot token not set");
    } else {
        echo "   ✓ Token set: " . substr($token, 0, 10) . "...\n";
        Logger::info("Token verified", ['preview' => substr($token, 0, 10)]);
    }

    $apiUrl = BotConfig::where('key', 'api_url')->value('value');
    if (empty($apiUrl)) {
        echo "   ⚠ WARNING: API_URL not set, using default\n";
        Logger::warning("API URL not set");
    } else {
        echo "   ✓ API_URL set: $apiUrl\n";
        Logger::debug("API URL verified");
    }

    echo "\n";
} catch (\Exception $e) {
    echo "   ❌ Error verifying token: " . $e->getMessage() . "\n";
    Logger::error("Error verifying token", ['error' => $e->getMessage()]);
    exit(1);
}

echo "6. API Authentication Info...\n";
try {
    $apiToken = BotConfig::where('key', 'api_token')->value('value');
    echo "   🔐 API_TOKEN: $apiToken\n";
    echo "\n";
    echo "   📌 For API access, use this header:\n";
    echo "      - Authorization: Bearer $apiToken\n";
    Logger::info("API authentication configured");
    echo "\n";
} catch (\Exception $e) {
    echo "   ❌ Error getting API token: " . $e->getMessage() . "\n";
    Logger::error("Error getting API token", ['error' => $e->getMessage()]);
}

echo "========================================\n";
echo "✅ SETUP COMPLETED SUCCESSFULLY!\n";
echo "========================================\n\n";

echo "To run the bot:\n";
echo "  php bot.php\n\n";

echo "To run the API:\n";
echo "  php -S localhost:8000 -t public_html\n\n";

echo "To test API:\n";
echo "  curl -X GET 'http://localhost:8000/api/users' -H 'Authorization: Bearer $apiToken'\n";
echo "  curl -X GET 'http://localhost:8000/health'\n\n";

Logger::info("=== SETUP SCRIPT COMPLETED SUCCESSFULLY ===");
