<?php
require 'vendor/autoload.php';

use Bot\Core\App;
use Bot\Core\Logger;
use Bot\Models\User;
use Bot\Models\BotConfig;

Logger::init(__DIR__ . '/logs/cron.log', true);
Logger::info("=== CRON JOB STARTED ===");

$lockFile = __DIR__ . '/logs/broadcast.lock';
if (file_exists($lockFile) && (time() - filemtime($lockFile)) < 300) {
    Logger::warning("Another broadcast is running, exiting");
    exit("Another broadcast is running\n");
}
touch($lockFile);
Logger::debug("Lock file created");

try {
    Logger::debug("Initializing application");
    $app = new App();
    $client = $app->getClient();
    $userModel = $app->getUserModel();
    $configModel = $app->getConfigModel();

    $chunkSize = 10;
    $delaySeconds = 1;
    $maxUsersPerRun = 50;
    $owners = $app->getConfig()->getOwners();
    $admin = !empty($owners) ? $owners[0] : null;

    if (!$admin) {
        Logger::error("No admin found for broadcast notifications");
        exit(1);
    }
    Logger::debug("Admin ID for notifications", ['admin_id' => $admin]);

    $broadcastEnabled = (int) $configModel->get('broadcast_enabled');
    if ($broadcastEnabled !== 1) {
        Logger::info("Broadcast is disabled, sending notification");
        $client->sendMessage($admin, "⏸️ Broadcast is disabled. Cron executed but no messages sent.")->await();
        exit;
    }

    $broadcastIsForward = $configModel->get('broadcast_is_forward') === '1';
    $broadcastChatId = $configModel->get('broadcast_chat_id') ?? '';
    $broadcastMessageId = (int) ($configModel->get('broadcast_message_id') ?? 0);
    $broadcastLastUserId = (int) ($configModel->get('broadcast_last_user_id') ?? 0);

    Logger::debug("Broadcast config loaded", [
        'is_forward' => $broadcastIsForward,
        'chat_id' => $broadcastChatId,
        'message_id' => $broadcastMessageId,
        'last_user_id' => $broadcastLastUserId
    ]);

    if (!$broadcastChatId || !$broadcastMessageId) {
        Logger::warning("Broadcast message not configured");
        $client->sendMessage($admin, "❌ Cron executed but broadcast message is not configured.\n\n📝 Please setup a broadcast message first.")->await();
        exit;
    }

    $startTime = microtime(true);
    $sentCount = 0;
    $failedCount = 0;

    $remainingUsers = User::where('id', '>', $broadcastLastUserId)
        ->where('blocked', 0)
        ->orderBy('id', 'ASC')
        ->limit($maxUsersPerRun)
        ->get()
        ->toArray();
    
    $totalRemaining = User::where('id', '>', $broadcastLastUserId)
        ->where('blocked', 0)
        ->count();
    
    $totalUsersCount = User::count();
    $totalToProcess = count($remainingUsers);

    Logger::info("Broadcast statistics", [
        'total_users' => $totalUsersCount,
        'remaining' => $totalRemaining,
        'to_process' => $totalToProcess
    ]);

    if ($totalToProcess === 0) {
        Logger::info("Broadcast completed, resetting");
        $completionMessage = "✅ Broadcast completed successfully!\n\n";
        $completionMessage .= "📊 Summary:\n";
        $completionMessage .= "• Total users: {$totalUsersCount}\n";
        $completionMessage .= "• All users received the message.\n\n";
        $completionMessage .= "♻️ Broadcast settings have been reset.";

        $client->sendMessage($admin, $completionMessage)->await();
        
        $configModel->set('broadcast_enabled', '0');
        $configModel->set('broadcast_chat_id', '');
        $configModel->set('broadcast_message_id', '');
        $configModel->set('broadcast_is_forward', '0');
        $configModel->set('broadcast_last_user_id', '0');
        
        Logger::info("Broadcast completed and reset");
        exit;
    }

    $startMessage = "🚀 Starting broadcast\n\n";
    $startMessage .= "📊 Statistics:\n";
    $startMessage .= "• Total users: {$totalUsersCount}\n";
    $startMessage .= "• Processed users: {$broadcastLastUserId}\n";
    $startMessage .= "• Remaining users: {$totalRemaining}\n";
    $startMessage .= "• Processing this run: {$totalToProcess} users\n\n";
    
    if ($totalRemaining > 0) {
        $estimatedSeconds = $totalRemaining * $delaySeconds;
        $estimatedMinutes = ceil($estimatedSeconds / 60);
        $estimatedHours = floor($estimatedMinutes / 60);
        $remainingMinutes = $estimatedMinutes % 60;
        
        $startMessage .= "⏱️ Estimated completion time:\n";
        if ($estimatedHours > 0) {
            $startMessage .= "• {$estimatedHours} hours and {$remainingMinutes} minutes\n";
        } else {
            $startMessage .= "• {$estimatedMinutes} minutes\n";
        }
    }
    
    $client->sendMessage($admin, $startMessage)->await();
    Logger::info("Start notification sent to admin");

    $lastProcessedId = $broadcastLastUserId;
    
    foreach ($remainingUsers as $index => $user) {
        $userId = $user['user_id'];
        $currentNumber = $index + 1;
        
        try {
            if ($broadcastIsForward) {
                $client->forwardMessage($userId, $broadcastChatId, $broadcastMessageId)->await();
            } else {
                $client->copyMessage( $userId, $broadcastChatId,
                     $broadcastMessageId
                )->await();
            }
            
            $lastProcessedId = $user['id'];
            $sentCount++;
            
            if ($sentCount % $chunkSize === 0 || $currentNumber === $totalToProcess) {
                $configModel->set('broadcast_last_user_id', (string) $lastProcessedId);
                
                $progressPercentage = round(($currentNumber / $totalToProcess) * 100, 1);
                $remainingInThisRun = $totalToProcess - $currentNumber;
                $remainingTime = $remainingInThisRun * $delaySeconds;
                
                $progressMessage = "📈 Broadcast progress\n\n";
                $progressMessage .= "• Sent: {$sentCount}/{$totalToProcess} ({$progressPercentage}%)\n";
                $progressMessage .= "• Successful: {$sentCount} | Failed: {$failedCount}\n";
                $progressMessage .= "• Remaining this run: {$remainingInThisRun} users\n";
                $progressMessage .= "• Time remaining: " . ceil($remainingTime / 60) . " minutes\n";
                
                $client->sendMessage($admin, $progressMessage)->await();
                Logger::debug("Progress update sent", ['sent' => $sentCount]);
            }
            
            if ($delaySeconds > 0) {
                usleep($delaySeconds * 1000000);
            }
            
        } catch (\Throwable $e) {
            $failedCount++;
            $errorMessage = $e->getMessage();
            Logger::warning("Failed to send broadcast", [
                'user_id' => $userId,
                'error' => $errorMessage
            ]);
            
            if ($failedCount % 5 === 0 || $failedCount === 1) {
                $errorReport = "⚠️ Send error\n\n";
                $errorReport .= "• User: @{$user['username']} (ID: {$userId})\n";
                $errorReport .= "• Total errors: {$failedCount}\n";
                
                $client->sendMessage($admin, $errorReport)->await();
            }
            
            if (str_contains($errorMessage, 'Too Many Requests')) {
                Logger::warning("Rate limit hit, sleeping");
                sleep(10);
            }
        }
    }

    if ($lastProcessedId > $broadcastLastUserId) {
        $configModel->set('broadcast_last_user_id', (string) $lastProcessedId);
    }

    $remainingTotal = User::where('id', '>', (int) $configModel->get('broadcast_last_user_id'))
        ->where('blocked', 0)
        ->count();

    $endTime = microtime(true);
    $executionTime = round($endTime - $startTime, 2);
    $executionMinutes = round($executionTime / 60, 1);

    $endMessage = "🏁 Broadcast run completed\n\n";
    $endMessage .= "📊 This run statistics:\n";
    $endMessage .= "• Execution time: {$executionMinutes} minutes\n";
    $endMessage .= "• Successful sends: {$sentCount} users\n";
    $endMessage .= "• Failed sends: {$failedCount} users\n";
    $endMessage .= "• Success rate: " . ($totalToProcess > 0 ? round(($sentCount / $totalToProcess) * 100, 1) : 0) . "%\n\n";

    if ($remainingTotal > 0) {
        $remainingTimeTotal = $remainingTotal * $delaySeconds;
        $hoursLeft = floor($remainingTimeTotal / 3600);
        $minutesLeft = ceil(($remainingTimeTotal % 3600) / 60);
        
        $endMessage .= "⏱️ Estimated total completion:\n";
        if ($hoursLeft > 0) {
            $endMessage .= "• {$hoursLeft} hours and {$minutesLeft} minutes\n";
        } else {
            $endMessage .= "• {$minutesLeft} minutes\n";
        }
        $endMessage .= "• Remaining users: {$remainingTotal}\n";
    }

    $endMessage .= "\n✅ Cron executed successfully.";

    $client->sendMessage($admin, $endMessage)->await();
    Logger::info("Broadcast run completed", [
        'sent' => $sentCount,
        'failed' => $failedCount,
        'remaining' => $remainingTotal
    ]);

    if ($remainingTotal <= 0) {
        $configModel->set('broadcast_enabled', '0');
        $finalMessage = "🎉 Broadcast completed successfully!\n\n";
        $finalMessage .= "• Total users: {$totalUsersCount}\n";
        $finalMessage .= "• All users received the message.\n";
        $finalMessage .= "• Broadcast has been disabled.";
        
        $client->sendMessage($admin, $finalMessage)->await();
        Logger::info("Broadcast completed and disabled");
    }

} catch (\Throwable $e) {
    Logger::error("CRON job failed", [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    echo "Error: " . $e->getMessage() . "\n";
} finally {
    if (file_exists($lockFile)) {
        unlink($lockFile);
        Logger::debug("Lock file removed");
    }
}

Logger::info("=== CRON JOB COMPLETED ===");
