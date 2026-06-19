<?php
namespace Bot\Middleware;

use Bot\Core\App;
use Bot\Core\Language;
use Bot\Core\Logger;

class SpamMiddleware
{
    protected App $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    public function checkAndBlock(int $fromId): bool
    {
        try {
            $userModel = $this->app->getUserModel();
            $configModel = $this->app->getConfigModel();
            $client = $this->app->getClient();
            $language = Language::getInstance();
            $lang = $userModel->getLanguage($fromId);

            // Skip for admins and owners
            if ($userModel->isAdmin($fromId) || $userModel->isOwner($fromId)) {
                return false;
            }

            // Check if user is already blocked
            if ($userModel->isBlocked($fromId)) {
                Logger::debug("User {$fromId} is already blocked (spam check)");
                return true;
            }

            // Get current timestamp in seconds
            $currentTime = time();

            // Get stored spam data (array of timestamps)
            $spamData = $userModel->getTemp($fromId, 'spam_timestamps');
            if ($spamData === null) {
                $spamData = [];
            } else {
                // Decode JSON if stored as string
                if (is_string($spamData)) {
                    $spamData = json_decode($spamData, true) ?? [];
                }
            }

            // Filter timestamps older than 1 second
            $spamData = array_filter($spamData, function ($timestamp) use ($currentTime) {
                return ($currentTime - $timestamp) < 1;
            });

            // Add current timestamp
            $spamData[] = $currentTime;

            // Count messages in the last 1 second
            $spamCount = count($spamData);

            // Get spam limits from config
            $spamMax = (int) $configModel->get('spam_max_messages');
            $spamDuration = (int) $configModel->get('spam_block_duration');

            // Set defaults if not configured
            if ($spamMax <= 0) $spamMax = 5;
            if ($spamDuration <= 0) $spamDuration = 3600;

            // Store updated spam data (only if not blocked)
            if ($spamCount <= $spamMax) {
                $userModel->setTemp($fromId, 'spam_timestamps', json_encode($spamData), 60);
            }

            // Check if spam threshold is exceeded
            if ($spamCount > $spamMax) {
                Logger::warning("User {$fromId} exceeded spam limit", [
                    'count' => $spamCount,
                    'max' => $spamMax,
                    'duration' => $spamDuration
                ]);

                // Block user
                $userModel->blockUser($fromId, "Spam ({$spamCount} messages in 1 second)", $spamDuration);

                // Send block message
                $message = $language->get('blocked', $lang, ['duration' => $spamDuration]);
                $client->sendMessage($fromId, $message);

                Logger::info("User {$fromId} blocked for spam", [
                    'count' => $spamCount,
                    'max' => $spamMax,
                    'duration' => $spamDuration
                ]);

                return true;
            }

            Logger::debug("User {$fromId} spam count: {$spamCount}/{$spamMax} (per second)");
            return false;

        } catch (\Throwable $e) {
            Logger::error("Error in SpamMiddleware for user {$fromId}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Notify admin about the error
            $this->notifyAdminAboutSpam($fromId, 0, "Middleware error: " . $e->getMessage());
            return false;
        }
    }

    private function notifyAdminAboutSpam(int $userId, int $count, string $error = ''): void
    {
        try {
            $client = $this->app->getClient();
            $owners = $this->app->getConfig()->getOwners();

            if (empty($owners)) {
                return;
            }

            $message = "⚠️ Error in Anti-Spam System\n\n";
            $message .= "User: {$userId}\n";
            if ($count > 0) {
                $message .= "Message count: {$count}\n";
            }
            if (!empty($error)) {
                $message .= "Error: " . substr($error, 0, 200) . "\n";
            }
            $message .= "Time: " . date('Y-m-d H:i:s');

            foreach ($owners as $ownerId) {
                try {
                    $client->sendMessage($ownerId, $message)->await();
                } catch (\Throwable $e) {
                    Logger::error("Failed to notify admin {$ownerId} about spam error: " . $e->getMessage());
                }
            }
        } catch (\Throwable $e) {
            Logger::error("Failed to notify admins about spam error: " . $e->getMessage());
        }
    }
}
