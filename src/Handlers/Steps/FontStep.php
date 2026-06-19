<?php
namespace Bot\Handlers\Steps;

use Bot\Attributes\Step;
use Bot\Core\App;
use Bot\Core\Language;
use Bot\Models\UserModel;
use Neili\Client;
use Bot\Core\Keyboard;
use Bot\Core\Logger;
use AfazTech\Glyphify\Glyphify;

#[Step(name: 'font_input')]
class FontStep
{
    private const MAX_MESSAGE_LENGTH = 4000;

    public function __construct(
        private App $app,
        private UserModel $userModel,
        private Client $client,
        private Language $language
    ) {}

    public function handle(array $update): void
    {
        $fromId = $update['message']['from']['id'];
        $lang = $this->userModel->getLanguage($fromId);
        $text = trim($update['message']['text'] ?? '');
        
        $cancelText = $this->language->get('cancel', $lang);
        if ($text === '❌ ' . $cancelText || $text === $cancelText) {
            $this->userModel->setStep($fromId, null);
            $this->client->sendMessage(
                $fromId,
                $this->language->get('settings_saved', $lang),
                Keyboard::mainMenu($lang)
            );
            return;
        }
        
        try {
            Logger::debug("FontStep generating fonts", ['text' => $text, 'user_id' => $fromId]);
            
            $glyphify = new Glyphify($text);
            $fonts = $glyphify->generate();
            
            if (empty($fonts)) {
                $this->client->sendMessage(
                    $fromId,
                    $this->language->get('font_error', $lang)
                );
                return;
            }
            
            $this->sendAllFonts($fromId, $fonts, $lang);
            
            $this->userModel->setStep($fromId, null);
            $this->client->sendMessage(
                $fromId,
                $this->language->get('main_menu', $lang),
                Keyboard::mainMenu($lang)
            );
            
        } catch (\Throwable $e) {
            Logger::error("FontStep error", [
                'user_id' => $fromId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $this->client->sendMessage(
                $fromId,
                $this->language->get('font_error', $lang)
            );
        }
    }

    private function sendAllFonts(int $userId, array $fonts, string $lang): void
    {
        $currentMessage = '';
        
        foreach ($fonts as $font) {
            if (empty($font['name']) || empty($font['text'])) {
                continue;
            }
            
            $fontText = "🎨 *" . $this->escapeMarkdown($font['name']) . "*\n";
            $fontText .= "`" . $this->escapeMarkdown($font['text']) . "`\n\n";
            
            if (strlen($currentMessage) + strlen($fontText) > self::MAX_MESSAGE_LENGTH) {
                if (!empty($currentMessage)) {
                    $this->sendMessage($userId, $currentMessage);
                }
                $currentMessage = '';
            }
            
            $currentMessage .= $fontText;
        }
        
        if (!empty($currentMessage)) {
            $this->sendMessage($userId, $currentMessage);
        }
    }

    private function sendMessage(int $userId, string $message): void
    {
        try {
            $this->client->sendMessage(
                $userId,
                $message,
                null,
                ['parse_mode' => 'Markdown']
            );
            usleep(300000);
            
        } catch (\Throwable $e) {
            Logger::error("Failed to send font message", [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            
            try {
                $cleanMessage = strip_tags($message);
                $this->client->sendMessage($userId, $cleanMessage);
                usleep(300000);
            } catch (\Throwable $e2) {
                Logger::error("Failed to send clean font message", [
                    'user_id' => $userId,
                    'error' => $e2->getMessage()
                ]);
            }
        }
    }

    private function escapeMarkdown(string $text): string
    {
        $specialChars = ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];
        foreach ($specialChars as $char) {
            $text = str_replace($char, '\\' . $char, $text);
        }
        return $text;
    }
}
