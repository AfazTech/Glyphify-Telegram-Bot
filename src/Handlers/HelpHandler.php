<?php
namespace Bot\Handlers;

use Bot\Attributes\Text;
use Bot\Core\Language;
use Bot\Core\Logger;
use Bot\Models\UserModel;
use Bot\Core\Keyboard;
use Neili\Client;

#[Text(name: '/help', isCommand: true)]
class HelpHandler
{
    public function __construct(
        private Client $client,
        private Language $language,
        private UserModel $userModel
    ) {}

    public function handle(array $update): void
    {
        $fromId = $update['message']['from']['id'];
        $lang = $this->userModel->getLanguage($fromId);

        Logger::debug("HelpHandler handling user", ['user_id' => $fromId]);

        $text = "📘 " . $this->language->get('rules_text', $lang) . "\n\n";
        $text .= "💡 " . $this->language->get('language', $lang) . " " . $this->language->get('language_select', $lang);

        $keyboard = Keyboard::mainMenu($lang);
        
        try {
            $this->client->sendMessage($fromId, $text, $keyboard);
            Logger::info("Help message sent to user", ['user_id' => $fromId]);
        } catch (\Throwable $e) {
            Logger::error("Failed to send help message", [
                'user_id' => $fromId,
                'error' => $e->getMessage()
            ]);
        }
    }
}
