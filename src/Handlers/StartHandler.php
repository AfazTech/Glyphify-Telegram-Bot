<?php
namespace Bot\Handlers;

use Bot\Attributes\Text;
use Bot\Core\App;
use Bot\Core\Language;
use Bot\Core\Logger;
use Bot\Models\UserModel;
use Bot\Core\Keyboard;
use Neili\Client;
use Bot\Middleware\JoinMandatoryMiddleware;

#[Text(name: '/start', isCommand: true)]
class StartHandler
{
    public function __construct(
        private App $app,
        private UserModel $userModel,
        private Client $client,
        private Language $language
    ) {}

    public function handle(array $update): void
    {
        $fromId = $update['message']['from']['id'];
        
        Logger::debug("StartHandler handling user", ['user_id' => $fromId]);
        
        $this->userModel->syncUser(
            $fromId,
            $update['message']['from']['username'] ?? null,
            $update['message']['from']['first_name'] ?? null,
            $update['message']['from']['last_name'] ?? null
        );
        
        $this->userModel->setStep($fromId, null);
        
        $userLang = $this->userModel->getLanguage($fromId);
        
        $isAdmin = $this->userModel->isAdmin($fromId) || $this->userModel->isOwner($fromId);
        
        if (!$isAdmin) {
            $user = $this->userModel->getUser($fromId);
            $joinMandatory = $this->app->getJoinMandatoryModel();
            $activeChannels = $joinMandatory->getActiveChannels();
            
            if (!empty($activeChannels) && (!$user || !$user['join_mandatory_channels'])) {
                Logger::debug("User needs to join channels", ['user_id' => $fromId]);
                $joinMiddleware = new JoinMandatoryMiddleware($this->app);
                $joinMiddleware->handle($update);
                return;
            }
        }
        
        $keyboard = Keyboard::mainMenu($userLang);
        $text = $this->language->get('start_message', $userLang);
        
        Logger::debug("Sending start message", ['user_id' => $fromId, 'lang' => $userLang]);
        
        try {
            if (!$isAdmin) {
                $this->sendMenuAd($fromId);
            }
            $this->client->sendMessage($fromId, $text, $keyboard);
            Logger::info("Start message sent to user", ['user_id' => $fromId]);
            
        } catch (\Throwable $e) {
            Logger::error("Failed to send start message", [
                'user_id' => $fromId,
                'error' => $e->getMessage()
            ]);
            try {
                $keyboard = Keyboard::mainMenu('fa');
                $this->client->sendMessage($fromId, $text, $keyboard);
            } catch (\Throwable $e2) {
                Logger::error("Failed to send start message with fallback", [
                    'user_id' => $fromId,
                    'error' => $e2->getMessage()
                ]);
            }
        }
    }

    private function sendMenuAd(int $fromId): void
    {
        try {
            $configModel = $this->app->getConfigModel();
            $client = $this->app->getClient();

            $adsMenuEnabled = (int) $configModel->get('ads_menu_enabled');
            
            if ($adsMenuEnabled !== 1) {
                Logger::debug("Menu ads disabled in config", ['user_id' => $fromId]);
                return;
            }

            $isForward = (int) $configModel->get('ads_menu_is_forward');
            $chatId = $configModel->get('ads_menu_chat_id');
            $messageId = (int) $configModel->get('ads_menu_message_id');

            if (empty($chatId) || empty($messageId)) {
                Logger::warning("Menu ad not sent - empty config", [
                    'user_id' => $fromId,
                    'chat_id' => $chatId,
                    'message_id' => $messageId
                ]);
                return;
            }

            Logger::info("Sending menu ad to user", [
                'user_id' => $fromId,
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'is_forward' => $isForward
            ]);

            try {
                if ($isForward === 1) {
                    $client->forwardMessage($fromId, $chatId, $messageId)->await();
                } else {
                    $client->copyMessage($fromId, $chatId, $messageId)->await();
                }
            } catch (\Throwable $e) {
                Logger::error("Failed to send menu ad", [
                    'user_id' => $fromId,
                    'error' => $e->getMessage()
                ]);
            }
            
        } catch (\Throwable $e) {
            Logger::error("Error in sendMenuAd", [
                'user_id' => $fromId,
                'error' => $e->getMessage()
            ]);
        }
    }
}
