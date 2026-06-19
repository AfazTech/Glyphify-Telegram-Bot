<?php
namespace Bot\Core;

use Bot\Database\Database;
use Bot\Models\JoinMandatoryModel;
use Neili\Settings;
use Neili\Client;
use Neili\Poller;
use Dotenv\Dotenv;
use Bot\Models\UserModel;
use Bot\Models\ConfigModel;
use Bot\Middleware\SpamMiddleware;
use Bot\Middleware\AdsMiddleware;
use Bot\Middleware\JoinMandatoryMiddleware;

class App
{
    protected Database $db;
    protected Client $client;
    protected Poller $poller;
    protected Config $config;
    protected Container $container;
    protected Router $router;
    protected UserModel $userModel;
    protected ConfigModel $configModel;
    protected JoinMandatoryModel $joinMandatoryModel;
    protected bool $initialized = false;

    public function __construct()
    {
        Logger::info("=== APP INITIALIZATION STARTED ===");
        
        if (file_exists(__DIR__ . '/../../.env')) {
            $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
            $dotenv->load();
            Logger::debug(".env file loaded");
        }

        $this->config = Config::getInstance();
        
        Logger::init(null, $this->config->isDebugMode());
        Logger::info("Logger initialized with debug mode: " . ($this->config->isDebugMode() ? 'ON' : 'OFF'));

        $this->container = new Container();
        $this->container->singleton(Language::class, fn() => Language::getInstance());
        
        Logger::info("Initializing database...");
        $this->initDatabase();
        
        Logger::info("Initializing services...");
        $this->initServices();
        
        Logger::info("Initializing router...");
        $this->initRouter();
        
        Logger::info("Registering container bindings...");
        $this->registerContainerBindings();
        
        Logger::info("=== APP INITIALIZATION COMPLETED SUCCESSFULLY ===");
        $this->initialized = true;
    }

    private function initDatabase(): void
    {
        Logger::debug("Creating database instance");
        $this->db = new Database($this);
        
        Logger::debug("Initializing database schema");
        $this->db->initSchema();
        
        $token = $this->config->getToken();
        if (empty($token)) {
            Logger::critical("TOKEN is not set in database or .env file");
            throw new \RuntimeException('TOKEN not set in database or .env file');
        }
        
        Logger::info("Token loaded successfully", [
            'token_preview' => substr($token, 0, 10) . '...',
            'token_length' => strlen($token)
        ]);
        
        $apiUrl = $this->config->getApiUrl();
        Logger::info("API URL configured", ['url' => $apiUrl]);
        
        $settings = (new Settings)
            ->setAccessToken($token)
            ->setApiUrl($apiUrl)
            ->setApiVerifySSL(false);

        $this->client = new Client($settings);
        $this->poller = new Poller($this->client);
        
        Logger::info("Telegram client initialized");
    }

    private function initServices(): void
    {
        $this->userModel = new UserModel($this);
        $this->configModel = new ConfigModel($this);
        $this->joinMandatoryModel = new JoinMandatoryModel($this);
        Logger::debug("Services initialized");
    }

    private function initRouter(): void
    {
        $this->router = new Router($this->container);
        
        // فقط پوشه Handlers و زیرپوشه‌های آن
        $directories = [
            __DIR__ . '/../Handlers',
            __DIR__ . '/../Handlers/Callbacks',
            __DIR__ . '/../Handlers/Steps'
        ];
        
        foreach ($directories as $dir) {
            if (is_dir($dir)) {
                $this->router->discover($dir);
                Logger::debug("Router discovered handlers in: " . basename($dir));
            }
        }
        
        Logger::info("Router initialized with handlers from all directories");
    }

    private function registerContainerBindings(): void
    {
        $this->container->singleton(App::class, fn() => $this);
        $this->container->singleton(Client::class, fn() => $this->client);
        $this->container->singleton(UserModel::class, fn() => $this->userModel);
        $this->container->singleton(ConfigModel::class, fn() => $this->configModel);
        $this->container->singleton(JoinMandatoryModel::class, fn() => $this->joinMandatoryModel);
        $this->container->singleton(Config::class, fn() => $this->config);
        $this->container->singleton(Router::class, fn() => $this->router);
        $this->container->singleton(Language::class, fn() => Language::getInstance());
        
        Logger::debug("Container bindings registered");
    }

    public function start(): void
    {
        Logger::info("=== STARTING BOT POLLING ===");
        
        $this->poller->onMessage(function ($update) {
            $chatType = $update['message']['chat']['type'] ?? null;
            if ($chatType !== 'private') {
                Logger::debug("Ignoring non-private chat", ['chat_type' => $chatType]);
                return;
            }
            
            $fromId = $update['message']['from']['id'] ?? null;
            if (is_null($fromId)) {
                Logger::warning("Message without from_id received");
                return;
            }

            Logger::debug("Received message from user", [
                'user_id' => $fromId,
                'text' => substr($update['message']['text'] ?? '', 0, 50)
            ]);
            
            $this->handleWithMiddlewares($update);
        });
        
        $this->poller->onCallbackQuery(function ($update) {
            $fromId = $update['callback_query']['from']['id'] ?? null;
            $data = $update['callback_query']['data'] ?? '';
            Logger::debug("Received callback from user", [
                'user_id' => $fromId,
                'data' => $data
            ]);
            $this->router->resolve($update);
        });
        
        $this->poller->onChatMember(function ($update) {
            $this->handleChatMemberUpdate($update);
        });
      
        Logger::info("Bot polling started successfully");
        $this->poller->start(true);
    }

    private function handleWithMiddlewares(array $update): void
    {
        $fromId = $update['message']['from']['id'];
        $lang = $this->userModel->getLanguage($fromId);
        $language = Language::getInstance();
        
        Logger::info("=== PROCESSING MESSAGE ===", ['user_id' => $fromId]);
        
        // Check spam
        Logger::debug("Checking spam", ['user_id' => $fromId]);
        $spamMiddleware = new SpamMiddleware($this);
        if ($spamMiddleware->checkAndBlock($fromId)) {
            Logger::info("User blocked by spam middleware", ['user_id' => $fromId]);
            return;
        }
        
        // Check maintenance
        if ($this->checkMaintenanceMode($fromId)) {
            Logger::debug("User blocked by maintenance mode", ['user_id' => $fromId]);
            return;
        }
        
        // Get user and check admin status
        $user = $this->userModel->getUser($fromId);
        $userIsAdmin = $this->userModel->isAdmin($fromId) || $this->userModel->isOwner($fromId);
        
        // Always sync user data
        $this->userModel->syncUser(
            $fromId,
            $update['message']['from']['username'] ?? null,
            $update['message']['from']['first_name'] ?? null,
            $update['message']['from']['last_name'] ?? null
        );
        
        // Check join mandatory only for non-admins
        if (!$userIsAdmin) {
            Logger::debug("Checking join mandatory", ['user_id' => $fromId]);
            $joinMiddleware = new JoinMandatoryMiddleware($this);
            
            if (!$user || !$user['join_mandatory_channels']) {
                Logger::debug("User needs to join channels", ['user_id' => $fromId]);
                $joinMiddleware->handle($update);
                return;
            }
        }
        
        // Send random ads (only to non-admins)
        if (!$userIsAdmin) {
            $adsMiddleware = new AdsMiddleware($this);
            $adsMiddleware->handleRandomAds($fromId);
        }
        
        // Finally route the message
        Logger::debug("Routing message to handler", ['user_id' => $fromId]);
        $this->router->resolve($update);
        
        Logger::info("=== MESSAGE PROCESSING COMPLETED ===", ['user_id' => $fromId]);
    }

    private function handleChatMemberUpdate(array $update): void
    {
        if (!isset($update['chat_member'])) {
            Logger::debug("Chat member update without chat_member field");
            return;
        }

        $chatId = $update['chat_member']['chat']['id'];
        $userId = $update['chat_member']['from']['id'];
        $newStatus = $update['chat_member']['new_chat_member']['status'];
        $oldStatus = $update['chat_member']['old_chat_member']['status'];

        try {
            $chat = $this->client->getChat($chatId)->await();
            if ($chat['result']['type'] !== 'channel') {
                Logger::debug("Chat member update for non-channel", ['chat_id' => $chatId]);
                return;
            }
        } catch (\Throwable $e) {
            Logger::error("Failed to get chat info for chat member update", [
                'chat_id' => $chatId,
                'error' => $e->getMessage()
            ]);
            return;
        }

        if (in_array($oldStatus, ['member', 'administrator', 'creator']) && $newStatus === 'left') {
            Logger::info("User left mandatory channel", [
                'user_id' => $userId,
                'chat_id' => $chatId
            ]);
            $joinMiddleware = new JoinMandatoryMiddleware($this);
            $joinMiddleware->handleChannelLeave($userId, $chatId);
        }
    }

    private function checkMaintenanceMode(int $userId): bool
    {
        $maintenanceMode = $this->configModel->get('maintenance_mode');
        if ($maintenanceMode && !$this->userModel->isAdmin($userId)) {
            $lang = $this->userModel->getLanguage($userId);
            $language = Language::getInstance();
            $message = $language->get('maintenance', $lang);
            $this->client->sendMessage($userId, $message);
            Logger::debug("User blocked by maintenance mode", ['user_id' => $userId]);
            return true;
        }
        return false;
    }

    public function getClient(): Client { return $this->client; }
    public function getConfigModel(): ConfigModel { return $this->configModel; }
    public function getUserModel(): UserModel { return $this->userModel; }
    public function getJoinMandatoryModel(): JoinMandatoryModel { return $this->joinMandatoryModel; }
    public function getConfig(): Config { return $this->config; }
    public function getContainer(): Container { return $this->container; }
    public function getRouter(): Router { return $this->router; }
}
