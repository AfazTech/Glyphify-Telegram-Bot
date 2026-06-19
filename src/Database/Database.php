<?php
namespace Bot\Database;

use Bot\Core\App;
use Bot\Core\Logger;
use Illuminate\Database\Capsule\Manager as Capsule;
use Bot\Models\User;
use Bot\Models\BotConfig;
use Bot\Models\JoinMandatoryChannel;

class Database
{
    protected App $app;
    protected Capsule $capsule;

    public function __construct(App $app)
    {
        $this->app = $app;
        $this->capsule = new Capsule();
        
        Logger::debug("Database capsule created");
        
        $config = $this->app->getConfig()->getDatabaseConfig();
        
        // Ensure database path is absolute and in root
        if ($config['driver'] === 'sqlite') {
            $databasePath = $config['database'];
            // If path is relative, make it absolute from root
            if (!str_starts_with($databasePath, '/')) {
                $databasePath = __DIR__ . '/../../' . $databasePath;
            }
            
            $databaseDir = dirname($databasePath);
            
            if (!is_dir($databaseDir)) {
                mkdir($databaseDir, 0755, true);
                Logger::debug("Database directory created", ['dir' => $databaseDir]);
            }
            
            if (!file_exists($databasePath)) {
                touch($databasePath);
                chmod($databasePath, 0666);
                Logger::info("SQLite database file created", ['file' => $databasePath]);
            }
            
            // Update config with absolute path
            $config['database'] = $databasePath;
        }
        
        $this->capsule->addConnection($config);
        $this->capsule->setAsGlobal();
        $this->capsule->bootEloquent();
        
        Logger::debug("Database connection established", ['driver' => $config['driver']]);
    }

    public function getDb(): Capsule
    {
        return $this->capsule;
    }

    public function initSchema(): void
    {
        Logger::info("Initializing database schema");
        $tablesCreated = 0;
        
        if (!Capsule::schema()->hasTable('bot_config')) {
            Capsule::schema()->create('bot_config', function ($table) {
                $table->increments('id');
                $table->string('key', 64)->unique();
                $table->text('value');
                $table->timestamps();
            });
            $tablesCreated++;
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
            $tablesCreated++;
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
            $tablesCreated++;
            Logger::info("Table created: join_mandatory_channels");
        }

        Logger::info("Schema initialization completed", ['tables_created' => $tablesCreated]);

        $this->ensureOwnersAreAdmins();
        $this->initializeDefaultConfigs();
    }

    private function ensureOwnersAreAdmins(): void
    {
        Logger::debug("Ensuring owners are admins");
        $ownersAdded = 0;
        
        foreach ($this->app->getConfig()->getOwners() as $owner) {
            $exists = User::where('user_id', $owner)->exists();
            if (!$exists) {
                $user = User::create(['user_id' => $owner]);
                $user->is_admin = true;
                $user->save();
                $ownersAdded++;
                Logger::info("Owner added as admin", ['user_id' => $owner]);
            } else {
                User::where('user_id', $owner)->update(['is_admin' => true]);
            }
        }
        
        if ($ownersAdded > 0) {
            Logger::info("Owners processed as admins", ['count' => $ownersAdded]);
        }
    }

    private function initializeDefaultConfigs(): void
    {
        Logger::debug("Initializing default configurations");
        $configsAdded = 0;
        
        $defaults = [
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
            'debug_mode' => ($_ENV['DEBUG_MODE'] ?? 'false') === 'true' ? '1' : '0',
        ];

        foreach ($defaults as $key => $value) {
            $exists = BotConfig::where('key', $key)->exists();
            if (!$exists) {
                BotConfig::create(['key' => $key, 'value' => $value]);
                $configsAdded++;
            }
        }
        
        if (!empty($_ENV['TOKEN'])) {
            $tokenExists = BotConfig::where('key', 'token')->exists();
            if (!$tokenExists) {
                BotConfig::create(['key' => 'token', 'value' => $_ENV['TOKEN']]);
                $configsAdded++;
                Logger::debug("Token saved to database from environment");
            }
        }
        
        if (!empty($_ENV['API_URL'])) {
            $apiUrlExists = BotConfig::where('key', 'api_url')->exists();
            if (!$apiUrlExists) {
                BotConfig::create(['key' => 'api_url', 'value' => $_ENV['API_URL']]);
                $configsAdded++;
                Logger::debug("API URL saved to database from environment");
            }
        }
        
        if ($configsAdded > 0) {
            Logger::info("Default configurations added", ['count' => $configsAdded]);
        } else {
            Logger::debug("All configurations already exist");
        }
    }
}
