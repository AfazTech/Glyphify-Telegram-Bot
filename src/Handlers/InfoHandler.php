<?php
namespace Bot\Handlers;

use Bot\Attributes\Text;
use Bot\Core\Language;
use Bot\Core\Logger;
use Bot\Models\UserModel;
use Neili\Client;

#[Text(name: '/info', isCommand: true)]
class InfoHandler
{
    public function __construct(
        private Client $client,
        private Language $language,
        private UserModel $userModel
    ) {}

    public function handle(array $update): void
    {
        $fromId = $update['message']['from']['id'];
        $replyToMessage = $update['message']['reply_to_message'] ?? null;

        if (!$this->userModel->isAdmin($fromId) && !$this->userModel->isOwner($fromId)) {
            Logger::warning("Non-admin user attempted admin reply", ['user_id' => $fromId]);
            return;
        }

        if (!$replyToMessage) {
            $this->client->sendMessage($fromId, "❌ این دستور فقط زمانی کار می‌کند که روی یک پیام ریپلای بزنید.");
            return;
        }

        $originalChatId = $replyToMessage['chat']['id'];
        $originalMessageId = $replyToMessage['message_id'];

        $message = "📋 *اطلاعات پیام اصلی*\n\n";
        $message .= "🔆 *Chat ID:* `{$originalChatId}`\n";
        $message .= "🔢 *Message ID:* `{$originalMessageId}`\n";
        $message .= "👤 *فرستنده:* " . $this->getSenderInfo($replyToMessage) . "\n";
        $message .= "📝 *متن:*\n" . ($replyToMessage['text'] ?? '🎺 (پیام غیر متنی)');

        $this->client->sendMessage(
            $fromId, 
            $message, 
            null,
            ['parse_mode' => 'Markdown']  
        );
        
        Logger::info("Admin replied info sent", ['admin_id' => $fromId, 'chat_id' => $originalChatId]);
    }

    private function getSenderInfo(array $message): string
    {
        $from = $message['from'] ?? ['first_name' => 'ناشناس'];
        $name = $from['first_name'] ?? '';
        if (!empty($from['last_name'])) {
            $name .= ' ' . $from['last_name'];
        }
        if (!empty($from['username'])) {
            $name .= " (@{$from['username']})";
        }
        return $name;
    }
}
