<?php
namespace Bot\Handlers;

use Bot\Attributes\Text;
use Bot\Core\Language;
use Bot\Models\UserModel;
use Neili\Client;
use Bot\Core\Keyboard;
use Bot\Core\Logger;
use AfazTech\Glyphify\Glyphify;

#[Text(name: 'font')]
class FontHandler
{
    private const MAX_MESSAGE_LENGTH = 4000; // محدودیت کاراکتر تلگرام

    public function __construct(
        private Client $client,
        private Language $language,
        private UserModel $userModel
    ) {}

    public function handle(array $update): void
    {
        $fromId = $update['message']['from']['id'];
        $lang = $this->userModel->getLanguage($fromId);
        
        $text = trim($update['message']['text'] ?? '');
        
        $fontButtonText = $this->language->get('font', $lang);
        if (empty($text) || $text === $fontButtonText) {
            $this->client->sendMessage(
                $fromId,
                $this->language->get('font_prompt', $lang),
                Keyboard::cancelButton($lang)
            );
            $this->userModel->setStep($fromId, 'font_input');
            return;
        }
        
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
            Logger::debug("Generating fonts for text", ['text' => $text, 'user_id' => $fromId]);
            
            $glyphify = new Glyphify($text);
            $fonts = $glyphify->generate();
            
            Logger::debug("Fonts generated", ['count' => count($fonts), 'user_id' => $fromId]);
            
            if (empty($fonts)) {
                $this->client->sendMessage(
                    $fromId,
                    $this->language->get('font_error', $lang)
                );
                return;
            }
            
            // ارسال همه فونت‌ها بدون محدودیت تعداد
            $this->sendAllFonts($fromId, $fonts, $lang);
            
            $this->userModel->setStep($fromId, null);
            
        } catch (\Throwable $e) {
            Logger::error("Font generation error", [
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
        $totalFonts = count($fonts);
        $sentCount = 0;
        
        foreach ($fonts as $index => $font) {
            // اگر نام فونت و متن خالی نباشه
            if (empty($font['name']) || empty($font['text'])) {
                continue;
            }
            
            // ساخت یک فونت با فرمت Markdown
            $fontText = "🎨 *" . $this->escapeMarkdown($font['name']) . "*\n";
            $fontText .= "`" . $this->escapeMarkdown($font['text']) . "`\n\n";
            
            // بررسی محدودیت کاراکتر
            if (strlen($currentMessage) + strlen($fontText) > self::MAX_MESSAGE_LENGTH) {
                // ارسال پیام فعلی
                if (!empty($currentMessage)) {
                    $this->sendMessage($userId, $currentMessage);
                    $sentCount += 1; // فقط برای آمار
                }
                
                // شروع پیام جدید
                $currentMessage = '';
            }
            
            // اضافه کردن فونت به پیام فعلی
            $currentMessage .= $fontText;
        }
        
        // ارسال پیام آخر
        if (!empty($currentMessage)) {
            $this->sendMessage($userId, $currentMessage);
        }
        
        Logger::info("All fonts sent", [
            'user_id' => $userId,
            'total_fonts' => $totalFonts
        ]);
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
            
            // تاخیر بین پیام‌ها برای جلوگیری از محدودیت Rate Limit
            usleep(300000); // 0.3 ثانیه
            
        } catch (\Throwable $e) {
            Logger::error("Failed to send font message", [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            
            // اگر Markdown خطا داد، بدون Markdown بفرست
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
        // کاراکترهای خاص Markdown رو escape کن
        $specialChars = ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];
        foreach ($specialChars as $char) {
            $text = str_replace($char, '\\' . $char, $text);
        }
        return $text;
    }
}
