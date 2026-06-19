<?php
namespace Bot\Middleware;

use Bot\Core\App;
use Bot\Models\UserModel;
use Bot\Core\Logger;

class AdsMiddleware
{
    protected App $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    public function handleRandomAds(int $fromId): void
    {
        Logger::info("=== ADS CHECK STARTED ===", ['user_id' => $fromId]);
        
        try {
            $configModel = $this->app->getConfigModel();
            $client = $this->app->getClient();
            $userModel = $this->app->getUserModel();

            $isAdmin = $userModel->isAdmin($fromId) || $userModel->isOwner($fromId);
            Logger::debug("User admin check", ['user_id' => $fromId, 'is_admin' => $isAdmin]);
            
            if ($isAdmin) {
                Logger::info("Skipping ad for admin/owner", ['user_id' => $fromId]);
                return;
            }

            // Check random ads
            $adsRandomEnabled = (int) $configModel->get('ads_random_enabled');
            Logger::debug("Ads enabled status", ['user_id' => $fromId, 'enabled' => $adsRandomEnabled]);
            
            if ($adsRandomEnabled === 1) {
                $this->sendRandomAd($fromId, $configModel, $client, $userModel);
            } else {
                Logger::info("Random ads disabled in config", ['user_id' => $fromId]);
            }

            // Note: Menu ads are now handled separately in StartCommand
            
        } catch (\Throwable $e) {
            Logger::error("Error in handleRandomAds", [
                'user_id' => $fromId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
        
        Logger::info("=== ADS CHECK COMPLETED ===", ['user_id' => $fromId]);
    }

    private function sendRandomAd(int $fromId, $configModel, $client, $userModel): void
    {
        try {
            $chance = (int) $configModel->get('ads_random_chance');
            
            $random = rand(1, 100);
            Logger::debug("Chance check", [
                'user_id' => $fromId,
                'chance' => $chance,
                'random' => $random,
                'will_show' => ($random <= $chance)
            ]);
            
            if ($random > $chance) {
                Logger::info("Ad chance not met", [
                    'user_id' => $fromId,
                    'chance' => $chance,
                    'random' => $random
                ]);
                return;
            }

            // Get ad config
            $isForward = (int) $configModel->get('ads_random_is_forward');
            $chatId = $configModel->get('ads_random_chat_id');
            $messageId = (int) $configModel->get('ads_random_message_id');

            Logger::debug("Random Ad config loaded", [
                'user_id' => $fromId,
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'is_forward' => $isForward
            ]);

            if (empty($chatId) || empty($messageId)) {
                Logger::warning("Ad not sent - empty config", [
                    'user_id' => $fromId,
                    'chat_id' => $chatId,
                    'message_id' => $messageId
                ]);
                return;
            }

            // Send the ad
            Logger::info("Sending random ad to user", [
                'user_id' => $fromId,
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'is_forward' => $isForward
            ]);

            try {
                if ($isForward === 1) {
                    $result = $client->forwardMessage($fromId, $chatId, $messageId)->await();
                    Logger::info("Ad forwarded successfully", [
                        'user_id' => $fromId,
                        'result' => json_encode($result)
                    ]);
                } else {
                    $result = $client->copyMessage($fromId, $chatId, $messageId)->await();
                    Logger::info("Ad copied successfully", [
                        'user_id' => $fromId,
                        'result' => json_encode($result)
                    ]);
                }
                
                // Update last ad time
                try {
                    $userModel->setTemp($fromId, 'last_ad_time', time());
                    Logger::debug("Last ad time updated", ['user_id' => $fromId]);
                } catch (\Throwable $e) {
                    Logger::error("Failed to update last_ad_time", [
                        'user_id' => $fromId,
                        'error' => $e->getMessage()
                    ]);
                }
                
            } catch (\Throwable $e) {
                Logger::error("Failed to send random ad", [
                    'user_id' => $fromId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            
        } catch (\Throwable $e) {
            Logger::error("Error in sendRandomAd", [
                'user_id' => $fromId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
