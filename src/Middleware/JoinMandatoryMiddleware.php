<?php
namespace Bot\Middleware;

use Bot\Core\App;
use Bot\Handlers\Keyboards;
use Neili\KeyboardBuilder;
use Bot\Core\Logger;

class JoinMandatoryMiddleware
{
    protected App $app;
    private array $cache = [];

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    public function handle(array $update): void
    {
        try {
            $fromId = $update['message']['from']['id'] ?? $update['callback_query']['from']['id'] ?? null;
            $callbackId = $update['callback_query']['id'] ?? null;
            $data = $update['callback_query']['data'] ?? '';

            if (!$fromId) {
                Logger::warning("JoinMandatoryMiddleware: No fromId found in update");
                return;
            }

            $userModel = $this->app->getUserModel();
            $joinMandatory = $this->app->getJoinMandatoryModel();
            $client = $this->app->getClient();

            // Skip for admins and owners
            if ($userModel->isAdmin($fromId) || $userModel->isOwner($fromId)) {
                Logger::debug("User {$fromId} is admin/owner, skipping join check");
                return;
            }

            $user = $userModel->getUser($fromId);

            if (!$user) {
                // User doesn't exist yet, create them
                try {
                    $userModel->syncUser(
                        $fromId,
                        $update['message']['from']['username'] ?? null,
                        $update['message']['from']['first_name'] ?? null,
                        $update['message']['from']['last_name'] ?? null
                    );
                    $user = $userModel->getUser($fromId);
                    Logger::info("New user created: {$fromId}");
                } catch (\Throwable $e) {
                    Logger::error("Failed to create user {$fromId}: " . $e->getMessage());
                    $client->sendMessage($fromId, "❌ خطا در ایجاد کاربر. لطفاً دوباره تلاش کنید.");
                    return;
                }
            }

            // If already joined, return
            if ($user && $user['join_mandatory_channels']) {
                Logger::debug("User {$fromId} already joined mandatory channels");
                if ($data == 'user_check_join') {
                    try {
                        $client->answerCallbackQuery($callbackId, "عضویت شما قبلا تایید شده است.", true);
                    } catch (\Throwable $e) {
                        Logger::error("Failed to answer callback for user {$fromId}: " . $e->getMessage());
                    }
                }
                return;
            }

            $activeChannels = $joinMandatory->getActiveChannels();
            if (empty($activeChannels)) {
                try {
                    $userModel->updateUser($fromId, ['join_mandatory_channels' => 1]);
                    Logger::debug("No active channels, marked user {$fromId} as joined");
                    // Send welcome message after marking as joined
                    $this->sendWelcomeMessage($fromId);
                } catch (\Throwable $e) {
                    Logger::error("Failed to update user {$fromId} join status: " . $e->getMessage());
                }
                return;
            }

            $notJoined = $this->checkUserChannels($fromId, $activeChannels);

            if (empty($notJoined)) {
                try {
                    $userModel->updateUser($fromId, ['join_mandatory_channels' => 1]);
                    Logger::debug("User {$fromId} joined all channels");
                    // Send welcome message after successful join
                    $this->sendWelcomeMessage($fromId);
                } catch (\Throwable $e) {
                    Logger::error("Failed to update user {$fromId} join status: " . $e->getMessage());
                }
                return;
            }

            $this->showJoinButtons($fromId, $notJoined);

        } catch (\Throwable $e) {
            Logger::error("Error in JoinMandatoryMiddleware::handle", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $fromId = $update['message']['from']['id'] ?? $update['callback_query']['from']['id'] ?? null;
            if ($fromId) {
                try {
                    $client = $this->app->getClient();
                    $client->sendMessage($fromId, "⚠️ خطایی رخ داد. لطفاً دوباره تلاش کنید یا با پشتیبانی تماس بگیرید.");
                } catch (\Throwable $notifyError) {
                    Logger::error("Failed to notify user about error: " . $notifyError->getMessage());
                }
            }
        }
    }

    private function sendWelcomeMessage(int $userId): void
    {
        try {
            $userModel = $this->app->getUserModel();
            $router = $this->app->getRouter();
            $client = $this->app->getClient();
            $lang = $userModel->getLanguage($userId);
            $language = \Bot\Core\Language::getInstance();
            $keyboard = Keyboards::mainMenu($router, $lang);
            $client->sendMessage($userId, $language->get('welcome', $lang), $keyboard);
            Logger::info("Welcome message sent to user {$userId}");
        } catch (\Throwable $e) {
            Logger::error("Failed to send welcome message to user {$userId}: " . $e->getMessage());
        }
    }

    private function checkUserChannels(int $userId, array $channels): array
    {
        $client = $this->app->getClient();
        $notJoined = [];
        
        foreach ($channels as $channel) {
            try {
                $cacheKey = "member_check_{$userId}_{$channel['chat_id']}";
                
                if (isset($this->cache[$cacheKey])) {
                    $status = $this->cache[$cacheKey];
                    Logger::debug("Using cached status for user {$userId} in channel {$channel['chat_id']}: {$status}");
                } else {
                    $member = $client->getChatMember($channel['chat_id'], $userId)->await();
                    $status = $member['result']['status'] ?? 'left';
                    $this->cache[$cacheKey] = $status;
                    Logger::debug("User {$userId} status in channel {$channel['chat_id']}: {$status}");
                }
                
                if (!in_array($status, ['member', 'administrator', 'creator'])) {
                    $notJoined[] = [
                        'chat_id' => $channel['chat_id'],
                        'title' => $channel['title'] ?? 'کانال',
                        'link' => $channel['link'] ?? "https://t.me/joinchat/xxx"
                    ];
                    Logger::debug("User {$userId} is not joined to channel {$channel['chat_id']}");
                }
            } catch (\Throwable $e) {
                Logger::warning("Failed to check membership for user {$userId} in channel {$channel['chat_id']}", [
                    'error' => $e->getMessage()
                ]);
                $notJoined[] = [
                    'chat_id' => $channel['chat_id'],
                    'title' => $channel['title'] ?? 'کانال',
                    'link' => $channel['link'] ?? "https://t.me/joinchat/xxx"
                ];
            }
        }
        return $notJoined;
    }

    private function showJoinButtons(int $userId, array $notJoined): void
    {
        try {
            $client = $this->app->getClient();
            $kb = new KeyboardBuilder();
            $row = [];
            $buttonIndex = 0;
            
            foreach ($notJoined as $ch) {
                $buttonLabel = $ch['title'];
                if (strlen($buttonLabel) > 20) {
                    $buttonLabel = substr($buttonLabel, 0, 18) . '...';
                }
                
                $row[$buttonLabel] = $ch['link'];
                $buttonIndex++;
                
                if (count($row) === 2) {
                    $kb->inlineUrlRow($row);
                    $row = [];
                }
            }
            if (!empty($row)) {
                $kb->inlineUrlRow($row);
            }

            $kb->inlineRow(["بررسی مجدد عضویت 🔄" => "user_check_join"]);

            $message = "⚠️ برای استفاده از ربات باید در کانال‌های زیر عضو بشی:\n\n";
            foreach ($notJoined as $index => $ch) {
                $message .= ($index + 1) . ". " . ($ch['title'] ?? 'کانال') . "\n";
            }
            $message .= "\nپس از عضویت، دکمه بررسی را بزن.";

            $client->sendMessage(
                $userId,
                $message,
                $kb->build()
            );
            
            Logger::info("Join buttons shown to user {$userId} for " . count($notJoined) . " channels");
            
        } catch (\Throwable $e) {
            Logger::error("Failed to show join buttons to user {$userId}", [
                'error' => $e->getMessage()
            ]);
            
            try {
                $client = $this->app->getClient();
                $message = "⚠️ لطفاً در کانال‌های زیر عضو شوید:\n\n";
                foreach ($notJoined as $index => $ch) {
                    $message .= ($index + 1) . ". " . ($ch['title'] ?? 'کانال') . "\n";
                    if (!empty($ch['link'])) {
                        $message .= "   🔗 " . $ch['link'] . "\n";
                    }
                }
                $message .= "\nپس از عضویت، دستور /start را بزنید.";
                $client->sendMessage($userId, $message);
            } catch (\Throwable $notifyError) {
                Logger::error("Failed to send fallback message to user {$userId}: " . $notifyError->getMessage());
            }
        }
    }

    public function handleCallback(array $update): void
    {
        try {
            $data = $update['callback_query']['data'] ?? '';
            $fromId = $update['callback_query']['from']['id'];
            $callbackId = $update['callback_query']['id'];

            if (!$fromId) {
                Logger::warning("JoinMandatoryMiddleware::handleCallback: No fromId found");
                return;
            }

            $userModel = $this->app->getUserModel();
            $joinMandatory = $this->app->getJoinMandatoryModel();
            $client = $this->app->getClient();

            if ($userModel->isAdmin($fromId) || $userModel->isOwner($fromId)) {
                try {
                    $client->answerCallbackQuery($callbackId, "شما ادمین هستید و نیازی به عضویت ندارید.", true);
                } catch (\Throwable $e) {
                    Logger::error("Failed to answer callback for admin {$fromId}: " . $e->getMessage());
                }
                return;
            }

            if ($data === 'user_check_join') {
                $activeChannels = $joinMandatory->getActiveChannels();
                if (empty($activeChannels)) {
                    try {
                        $userModel->updateUser($fromId, ['join_mandatory_channels' => 1]);
                        $client->answerCallbackQuery($callbackId, "عضویت شما تایید شد.", true);
                        $this->sendWelcomeMessage($fromId);
                        Logger::info("User {$fromId} confirmed join with no active channels");
                    } catch (\Throwable $e) {
                        Logger::error("Failed to process join confirmation for user {$fromId}: " . $e->getMessage());
                    }
                    return;
                }
                
                $notJoined = $this->checkUserChannels($fromId, $activeChannels);
                if (!empty($notJoined)) {
                    try {
                        $client->answerCallbackQuery($callbackId, "لطفا وارد کانال های معرفی شده شوید سپس دکمه بررسی را بزنید.", true);
                        $this->showJoinButtons($fromId, $notJoined);
                    } catch (\Throwable $e) {
                        Logger::error("Failed to show join buttons for user {$fromId}: " . $e->getMessage());
                    }
                } else {
                    try {
                        $client->answerCallbackQuery($callbackId, "عضویت شما تایید شد.", true);
                        $userModel->updateUser($fromId, ['join_mandatory_channels' => 1]);
                        $this->sendWelcomeMessage($fromId);
                        Logger::info("User {$fromId} successfully joined all channels");
                    } catch (\Throwable $e) {
                        Logger::error("Failed to complete join confirmation for user {$fromId}: " . $e->getMessage());
                    }
                }
            }
        } catch (\Throwable $e) {
            Logger::error("Error in JoinMandatoryMiddleware::handleCallback", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $fromId = $update['callback_query']['from']['id'] ?? null;
            if ($fromId) {
                try {
                    $client = $this->app->getClient();
                    $client->sendMessage($fromId, "⚠️ خطایی رخ داد. لطفاً دوباره تلاش کنید.");
                } catch (\Throwable $notifyError) {
                    Logger::error("Failed to notify user about error: " . $notifyError->getMessage());
                }
            }
        }
    }

    public function handleChannelLeave(int $userId, int $channelId): void
    {
        try {
            $joinMandatory = $this->app->getJoinMandatoryModel();
            $userModel = $this->app->getUserModel();
            $client = $this->app->getClient();

            if ($userModel->isAdmin($userId) || $userModel->isOwner($userId)) {
                Logger::debug("User {$userId} is admin/owner, ignoring channel leave");
                return;
            }

            $channels = $joinMandatory->getActiveChannels();
            $leftChannel = null;

            foreach ($channels as $channel) {
                if ($channel['chat_id'] == $channelId) {
                    $leftChannel = $channel;
                    break;
                }
            }

            if (!$leftChannel) {
                Logger::debug("Channel {$channelId} not found in active channels");
                return;
            }

            try {
                $userModel->updateUser($userId, ['join_mandatory_channels' => 0]);
                Logger::info("User {$userId} left channel {$channelId}, resetting join status");
            } catch (\Throwable $e) {
                Logger::error("Failed to update user {$userId} join status on channel leave: " . $e->getMessage());
            }

            $message = "🚫 شما از کانال {$leftChannel['title']} خارج شدید.\n";
            $message .= "ربات برای شما غیر فعال شد.\n";
            $message .= "برای استفاده مجدد، روی دکمه /start کلیک کنید.";

            try {
                $client->sendMessage($userId, $message);
                Logger::info("Channel leave notification sent to user {$userId}");
            } catch (\Throwable $e) {
                Logger::warning("Failed to send channel leave notification to user {$userId}: " . $e->getMessage());
            }
            
        } catch (\Throwable $e) {
            Logger::error("Error in handleChannelLeave for user {$userId}, channel {$channelId}", [
                'error' => $e->getMessage()
            ]);
        }
    }
}
