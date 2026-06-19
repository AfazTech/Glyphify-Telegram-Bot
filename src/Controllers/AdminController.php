<?php

namespace Bot\Controllers;

use Bot\Core\App;
use Bot\Core\Language;
use Bot\Models\User;
use Bot\Models\BotConfig;
use Bot\Models\JoinMandatoryChannel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminController
{
    private App $app;
    
    public function __construct(App $app)
    {
        $this->app = $app;
    }
    
    // ==================== پیام همگانی ====================
    
    private function sendBroadcastBatch(int $maxPerRun = 50): array
    {
        $configModel = $this->app->getConfigModel();
        $client = $this->app->getClient();
        $userModel = $this->app->getUserModel();
        
        $enabled = (int) $configModel->get('broadcast_enabled');
        if ($enabled !== 1) {
            return ['success' => false, 'message' => 'Broadcast is disabled'];
        }
        
        $isForward = (int) $configModel->get('broadcast_is_forward') === 1;
        $chatId = $configModel->get('broadcast_chat_id');
        $messageId = (int) $configModel->get('broadcast_message_id');
        $lastUserId = (int) ($configModel->get('broadcast_last_user_id') ?? 0);
        
        if (!$chatId || !$messageId) {
            return ['success' => false, 'message' => 'No broadcast message configured'];
        }
        
        $users = User::where('id', '>', $lastUserId)
            ->where('blocked', 0)
            ->orderBy('id', 'ASC')
            ->limit($maxPerRun)
            ->get()
            ->toArray();
        
        $sentCount = 0;
        $failedCount = 0;
        $lastProcessedId = $lastUserId;
        
        foreach ($users as $user) {
            $userId = $user['user_id'];
            
            try {
                if ($isForward) {
                    $client->forwardMessage($userId, $chatId, $messageId)->await();
                } else {
                    $client->copyMessage([
                        'from_chat_id' => $chatId,
                        'chat_id' => $userId,
                        'message_id' => $messageId
                    ])->await();
                }
                
                $lastProcessedId = $user['id'];
                $sentCount++;
                
            } catch (\Throwable $e) {
                $failedCount++;
            }
        }
        
        if ($lastProcessedId > $lastUserId) {
            $configModel->set('broadcast_last_user_id', (string) $lastProcessedId);
        }
        
        $remaining = User::where('id', '>', $lastProcessedId)
            ->where('blocked', 0)
            ->count();
        
        if ($remaining <= 0) {
            $configModel->set('broadcast_enabled', '0');
            $configModel->set('broadcast_chat_id', '');
            $configModel->set('broadcast_message_id', '');
            $configModel->set('broadcast_last_user_id', '0');
        }
        
        return [
            'success' => true,
            'sent' => $sentCount,
            'failed' => $failedCount,
            'remaining' => $remaining,
            'last_user_id' => $configModel->get('broadcast_last_user_id')
        ];
    }
    
    private function getGeneralStats(): array
    {
        $totalUsers = User::count();
        $blockedUsers = User::where('blocked', true)->count();
        $activeUsers = User::where('status', true)->count();
        $admins = User::where('is_admin', true)->count();
        
        return [
            'total_users' => $totalUsers,
            'blocked_users' => $blockedUsers,
            'active_users' => $activeUsers,
            'admins' => $admins,
            'last_updated' => date('Y-m-d H:i:s')
        ];
    }
    
    public function setupBroadcast(Request $request, Response $response): Response
    {
        $data = json_decode($request->getBody()->getContents(), true);
        $configModel = $this->app->getConfigModel();
        
        $chatId = $data['chat_id'] ?? null;
        $messageId = $data['message_id'] ?? null;
        $isForward = $data['is_forward'] ?? false;
        
        if (!$chatId || !$messageId) {
            return $this->jsonResponse($response, ['success' => false, 'error' => 'chat_id and message_id are required'], 400);
        }
        
        $configModel->set('broadcast_chat_id', $chatId);
        $configModel->set('broadcast_message_id', $messageId);
        $configModel->set('broadcast_is_forward', $isForward ? '1' : '0');
        $configModel->set('broadcast_last_user_id', '0');
        
        return $this->jsonResponse($response, ['success' => true, 'message' => 'Broadcast setup successfully']);
    }
    
    public function getBroadcastStatus(Request $request, Response $response): Response
    {
        $configModel = $this->app->getConfigModel();
        
        $status = [
            'enabled' => (int) ($configModel->get('broadcast_enabled') ?? 0) === 1,
            'is_forward' => (int) ($configModel->get('broadcast_is_forward') ?? 0) === 1,
            'chat_id' => $configModel->get('broadcast_chat_id'),
            'message_id' => $configModel->get('broadcast_message_id'),
            'last_user_id' => (int) ($configModel->get('broadcast_last_user_id') ?? 0)
        ];
        
        return $this->jsonResponse($response, ['success' => true, 'data' => $status]);
    }
    
    public function enableBroadcast(Request $request, Response $response): Response
    {
        $configModel = $this->app->getConfigModel();
        
        $chatId = $configModel->get('broadcast_chat_id');
        $messageId = $configModel->get('broadcast_message_id');
        
        if (!$chatId || !$messageId) {
            return $this->jsonResponse($response, ['success' => false, 'error' => 'No broadcast message configured'], 400);
        }
        
        $configModel->set('broadcast_enabled', '1');
        
        return $this->jsonResponse($response, ['success' => true, 'message' => 'Broadcast enabled']);
    }
    
    public function disableBroadcast(Request $request, Response $response): Response
    {
        $this->app->getConfigModel()->set('broadcast_enabled', '0');
        
        return $this->jsonResponse($response, ['success' => true, 'message' => 'Broadcast disabled']);
    }
    
    public function sendBroadcastNow(Request $request, Response $response): Response
    {
        $result = $this->sendBroadcastBatch();
        
        return $this->jsonResponse($response, ['success' => true, 'data' => $result]);
    }
    
    // ==================== جوین اجباری ====================
    
    public function getChannels(Request $request, Response $response): Response
    {
        $channels = JoinMandatoryChannel::all()->toArray();
        return $this->jsonResponse($response, ['success' => true, 'data' => $channels]);
    }
    
    public function addChannel(Request $request, Response $response): Response
    {
        $data = json_decode($request->getBody()->getContents(), true);
        $client = $this->app->getClient();
        
        $chatId = $data['chat_id'] ?? null;
        $link = $data['link'] ?? null;
        $title = $data['title'] ?? null;
        
        if (!$chatId) {
            return $this->jsonResponse($response, ['success' => false, 'error' => 'chat_id is required'], 400);
        }
        
        try {
            $botId = (int) $client->getMe()->await()['result']['id'];
            $member = $client->getChatMember($chatId, $botId)->await();
            
            if ($member['result']['status'] !== 'administrator') {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'error' => 'Bot is not admin in this channel'
                ], 400);
            }
        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Invalid channel or bot not admin'
            ], 400);
        }
        
        try {
            $chat = $client->getChat($chatId)->await();
            $inviteLink = $chat['result']['invite_link'] ?? $link;
            $channelTitle = $title ?? $chat['result']['title'] ?? "Channel {$chatId}";
        } catch (\Exception $e) {
            $inviteLink = $link;
            $channelTitle = $title ?? "Channel {$chatId}";
        }
        
        JoinMandatoryChannel::create([
            'chat_id' => $chatId,
            'title' => $channelTitle,
            'link' => $inviteLink,
            'active' => true
        ]);
        
        $this->app->getUserModel()->resetAllJoinMandatory();
        
        return $this->jsonResponse($response, ['success' => true, 'message' => 'Channel added successfully']);
    }
    
    public function removeChannel(Request $request, Response $response, array $args): Response
    {
        $chatId = (int) $args['chatId'];
        JoinMandatoryChannel::where('chat_id', $chatId)->delete();
        
        return $this->jsonResponse($response, ['success' => true, 'message' => 'Channel removed successfully']);
    }
    
    public function toggleChannel(Request $request, Response $response, array $args): Response
    {
        $chatId = (int) $args['chatId'];
        
        $channel = JoinMandatoryChannel::where('chat_id', $chatId)->first();
        
        if (!$channel) {
            return $this->jsonResponse($response, ['success' => false, 'error' => 'Channel not found'], 404);
        }
        
        $channel->update(['active' => !$channel->active]);
        
        $this->app->getUserModel()->resetAllJoinMandatory();
        
        return $this->jsonResponse($response, ['success' => true, 'message' => 'Channel toggled successfully']);
    }
    
    // ==================== آنتی اسپم ====================
    
    public function getAntiSpamConfig(Request $request, Response $response): Response
    {
        $configModel = $this->app->getConfigModel();
        
        $config = [
            'max_messages' => (int) ($configModel->get('spam_max_messages') ?? 5),
            'block_duration' => (int) ($configModel->get('spam_block_duration') ?? 3600)
        ];
        
        return $this->jsonResponse($response, ['success' => true, 'data' => $config]);
    }
    
    public function updateAntiSpamConfig(Request $request, Response $response): Response
    {
        $data = json_decode($request->getBody()->getContents(), true);
        $configModel = $this->app->getConfigModel();
        
        if (isset($data['max_messages'])) {
            $configModel->set('spam_max_messages', (string) (int) $data['max_messages']);
        }
        
        if (isset($data['block_duration'])) {
            $configModel->set('spam_block_duration', (string) (int) $data['block_duration']);
        }
        
        return $this->jsonResponse($response, ['success' => true, 'message' => 'Anti-spam config updated']);
    }
    
    // ==================== تبلیغات ====================
    
    public function getRandomAdConfig(Request $request, Response $response): Response
    {
        $configModel = $this->app->getConfigModel();
        
        $config = [
            'enabled' => (int) ($configModel->get('ads_random_enabled') ?? 0) === 1,
            'chance' => (int) ($configModel->get('ads_random_chance') ?? 50),
            'is_forward' => (int) ($configModel->get('ads_random_is_forward') ?? 0) === 1,
            'chat_id' => $configModel->get('ads_random_chat_id'),
            'message_id' => $configModel->get('ads_random_message_id')
        ];
        
        return $this->jsonResponse($response, ['success' => true, 'data' => $config]);
    }
    
    public function updateRandomAdConfig(Request $request, Response $response): Response
    {
        $data = json_decode($request->getBody()->getContents(), true);
        $configModel = $this->app->getConfigModel();
        
        if (isset($data['enabled'])) {
            $configModel->set('ads_random_enabled', $data['enabled'] ? '1' : '0');
        }
        
        if (isset($data['chance'])) {
            $configModel->set('ads_random_chance', (string) (int) $data['chance']);
        }
        
        if (isset($data['chat_id']) && isset($data['message_id'])) {
            $configModel->set('ads_random_chat_id', $data['chat_id']);
            $configModel->set('ads_random_message_id', $data['message_id']);
            $configModel->set('ads_random_is_forward', isset($data['is_forward']) && $data['is_forward'] ? '1' : '0');
        }
        
        return $this->jsonResponse($response, ['success' => true, 'message' => 'Random ad config updated']);
    }
    
    public function getMenuAdConfig(Request $request, Response $response): Response
    {
        $configModel = $this->app->getConfigModel();
        
        $config = [
            'enabled' => (int) ($configModel->get('ads_menu_enabled') ?? 0) === 1,
            'is_forward' => (int) ($configModel->get('ads_menu_is_forward') ?? 0) === 1,
            'chat_id' => $configModel->get('ads_menu_chat_id'),
            'message_id' => $configModel->get('ads_menu_message_id')
        ];
        
        return $this->jsonResponse($response, ['success' => true, 'data' => $config]);
    }
    
    public function updateMenuAdConfig(Request $request, Response $response): Response
    {
        $data = json_decode($request->getBody()->getContents(), true);
        $configModel = $this->app->getConfigModel();
        
        if (isset($data['enabled'])) {
            $configModel->set('ads_menu_enabled', $data['enabled'] ? '1' : '0');
        }
        
        if (isset($data['chat_id']) && isset($data['message_id'])) {
            $configModel->set('ads_menu_chat_id', $data['chat_id']);
            $configModel->set('ads_menu_message_id', $data['message_id']);
            $configModel->set('ads_menu_is_forward', isset($data['is_forward']) && $data['is_forward'] ? '1' : '0');
        }
        
        return $this->jsonResponse($response, ['success' => true, 'message' => 'Menu ad config updated']);
    }
    
    // ==================== مدیریت ربات ====================
    
    public function getBotInfo(Request $request, Response $response): Response
    {
        try {
            $me = $this->app->getClient()->getMe()->await();
            return $this->jsonResponse($response, ['success' => true, 'data' => $me['result']]);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    public function getMaintenanceStatus(Request $request, Response $response): Response
    {
        $configModel = $this->app->getConfigModel();
        
        $status = [
            'enabled' => (int) ($configModel->get('maintenance_mode') ?? 0) === 1,
            'message' => $configModel->get('maintenance_message') ?? 'ربات در حال تعمیر است. لطفاً بعداً مراجعه کنید.'
        ];
        
        return $this->jsonResponse($response, ['success' => true, 'data' => $status]);
    }
    
    public function setMaintenanceMode(Request $request, Response $response): Response
    {
        $data = json_decode($request->getBody()->getContents(), true);
        $enabled = $data['enabled'] ?? false;
        
        $this->app->getConfigModel()->set('maintenance_mode', $enabled ? '1' : '0');
        
        return $this->jsonResponse($response, ['success' => true, 'message' => 'Maintenance mode updated']);
    }
    
    public function setMaintenanceMessage(Request $request, Response $response): Response
    {
        $data = json_decode($request->getBody()->getContents(), true);
        $message = $data['message'] ?? null;
        
        if (!$message) {
            return $this->jsonResponse($response, ['success' => false, 'error' => 'message is required'], 400);
        }
        
        $this->app->getConfigModel()->set('maintenance_message', $message);
        
        return $this->jsonResponse($response, ['success' => true, 'message' => 'Maintenance message updated']);
    }
    
    public function getServerInfo(Request $request, Response $response): Response
    {
        $info = $this->getServerInfoData();
        return $this->jsonResponse($response, ['success' => true, 'data' => $info]);
    }
    
    public function createBackup(Request $request, Response $response): Response
    {
        $backupPath = $this->createDatabaseBackup();
        
        if (!$backupPath) {
            return $this->jsonResponse($response, ['success' => false, 'error' => 'Failed to create backup'], 500);
        }
        
        return $this->jsonResponse($response, ['success' => true, 'data' => ['path' => $backupPath]]);
    }
    
    // ==================== آمار ====================
    
    public function getStatistics(Request $request, Response $response): Response
    {
        $stats = $this->getGeneralStats();
        return $this->jsonResponse($response, ['success' => true, 'data' => $stats]);
    }
    
    // ==================== تنظیمات ربات ====================
    
    public function getAllConfigs(Request $request, Response $response): Response
    {
        $configs = BotConfig::all(['key', 'value'])->toArray();
        return $this->jsonResponse($response, ['success' => true, 'data' => $configs]);
    }
    
    public function getConfig(Request $request, Response $response, array $args): Response
    {
        $key = $args['key'];
        $value = BotConfig::where('key', $key)->value('value');
        
        return $this->jsonResponse($response, ['success' => true, 'data' => ['key' => $key, 'value' => $value]]);
    }
    
    public function setConfig(Request $request, Response $response, array $args): Response
    {
        $key = $args['key'];
        $data = json_decode($request->getBody()->getContents(), true);
        $value = $data['value'] ?? null;
        
        if ($value === null) {
            return $this->jsonResponse($response, ['success' => false, 'error' => 'value is required'], 400);
        }
        
        BotConfig::updateOrCreate(['key' => $key], ['value' => (string) $value]);
        
        return $this->jsonResponse($response, ['success' => true, 'message' => 'Config updated']);
    }
    
    // ==================== مدیریت زبان ====================
    
    public function getLanguages(Request $request, Response $response): Response
    {
        $language = Language::getInstance();
        $languages = [];
        
        foreach ($language->getAvailableLanguages() as $code) {
            $languages[] = [
                'code' => $code,
                'name' => $language->getLanguageName($code),
                'default' => $code === $language->getDefaultLanguage()
            ];
        }
        
        return $this->jsonResponse($response, ['success' => true, 'data' => $languages]);
    }
    
    public function setDefaultLanguage(Request $request, Response $response): Response
    {
        $data = json_decode($request->getBody()->getContents(), true);
        $lang = $data['language'] ?? null;
        
        if (!$lang) {
            return $this->jsonResponse($response, ['success' => false, 'error' => 'Language code is required'], 400);
        }
        
        $language = Language::getInstance();
        
        if (!in_array($lang, $language->getAvailableLanguages())) {
            return $this->jsonResponse($response, ['success' => false, 'error' => 'Invalid language code'], 400);
        }
        
        BotConfig::updateOrCreate(
            ['key' => 'default_language'],
            ['value' => $lang]
        );
        
        $language->setDefaultLanguage($lang);
        
        return $this->jsonResponse($response, ['success' => true, 'message' => 'Default language updated']);
    }
    
    public function getUserLanguage(Request $request, Response $response, array $args): Response
    {
        $userId = (int) $args['userId'];
        $user = User::where('user_id', $userId)->first();
        
        if (!$user) {
            return $this->jsonResponse($response, ['success' => false, 'error' => 'User not found'], 404);
        }
        
        return $this->jsonResponse($response, [
            'success' => true,
            'data' => [
                'user_id' => $userId,
                'language' => $user->language ?? 'fa'
            ]
        ]);
    }
    
    public function setUserLanguage(Request $request, Response $response, array $args): Response
    {
        $userId = (int) $args['userId'];
        $data = json_decode($request->getBody()->getContents(), true);
        $lang = $data['language'] ?? null;
        
        if (!$lang) {
            return $this->jsonResponse($response, ['success' => false, 'error' => 'Language code is required'], 400);
        }
        
        $language = Language::getInstance();
        
        if (!in_array($lang, $language->getAvailableLanguages())) {
            return $this->jsonResponse($response, ['success' => false, 'error' => 'Invalid language code'], 400);
        }
        
        $user = User::where('user_id', $userId)->first();
        if (!$user) {
            return $this->jsonResponse($response, ['success' => false, 'error' => 'User not found'], 404);
        }
        
        $user->language = $lang;
        $user->save();
        
        return $this->jsonResponse($response, ['success' => true, 'message' => 'User language updated']);
    }
    
    public function reloadLanguages(Request $request, Response $response): Response
    {
        $language = Language::getInstance();
        $language->reload();
        
        return $this->jsonResponse($response, ['success' => true, 'message' => 'Languages reloaded successfully']);
    }
    
    // ==================== متدهای کمکی ====================
    
    private function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
    
    private function getServerInfoData(): array
    {
        $loadAvg = sys_getloadavg();
        
        return [
            'os' => PHP_OS . ' ' . php_uname('r'),
            'php_version' => PHP_VERSION,
            'memory_usage' => $this->formatBytes(memory_get_usage(true)),
            'memory_peak' => $this->formatBytes(memory_get_peak_usage(true)),
            'disk_free' => $this->formatBytes(disk_free_space(__DIR__)),
            'disk_total' => $this->formatBytes(disk_total_space(__DIR__)),
            'load_average' => is_array($loadAvg) ? round($loadAvg[0], 2) : 0,
            'uptime' => $this->getUptime()
        ];
    }
    
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
    
    private function getUptime(): string
    {
        if (!file_exists('/proc/uptime')) {
            return 'Unknown';
        }
        $uptime = file_get_contents('/proc/uptime');
        $uptime = floatval(explode(' ', $uptime)[0]);
        $days = floor($uptime / 86400);
        $hours = floor(($uptime % 86400) / 3600);
        $minutes = floor(($uptime % 3600) / 60);
        return "{$days}d {$hours}h {$minutes}m";
    }
    
    private function createDatabaseBackup(): ?string
    {
        $backupDir = __DIR__ . '/../../backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $backupFile = $backupDir . '/backup_' . date('Y-m-d_H-i-s') . '.sql';
        $database = $this->app->getConfig()->getDatabaseConfig()['database'] ?? 'test.sqlite';
        
        if (file_exists($database)) {
            copy($database, $backupFile);
            return $backupFile;
        }
        
        return null;
    }
}
