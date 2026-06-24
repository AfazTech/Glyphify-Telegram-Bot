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

#[Step(name: 'font_input', autoClear: false)]
class FontStep
{
    private const MAX_MESSAGE_LENGTH = 4000;

    public function __construct(
        private App $app,
        private UserModel $userModel,
        private Client $client,
        private Language $language
    ) {
    }

    public function handle(array $update): void
    {
        $fromId = $update['message']['from']['id'];
        $lang = $this->userModel->getLanguage($fromId);
        $text = trim($update['message']['text'] ?? '');


        try {
            Logger::debug("FontStep generating fonts", ['text' => $text, 'user_id' => $fromId]);

            $glyphify = new Glyphify($text);
            $fonts = $glyphify->generate();

            if (empty($fonts)) {
                $this->client->sendMessage(
                    $fromId,
                    $this->language->get('font_error', $lang),
                    Keyboard::backButton($lang)
                );
                return;
            }
$fontReady = $this->language->get('font_ready', $lang) . "\n\n";

            $currentMessage = '';

            foreach ($fonts as $font) {
                if (empty($font['name']) || empty($font['text'])) {
                    continue;
                }
                $fontText = "🎨 <b>" . $font['name'] . "</b>\n";
                $fontText .= "<code>" .
                    htmlspecialchars($font['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                    . "</code>\n\n";
                if (strlen($currentMessage) + strlen($fontText) > self::MAX_MESSAGE_LENGTH) {
                    if (!empty($currentMessage)) {
                        $this->client->sendMessage($fromId, $fontReady. $currentMessage, Keyboard::backButton($lang), ["parse_mode" => 'HTML']);
                    }
                    $currentMessage = '';
                }

                $currentMessage .= $fontText;
            }

            if (!empty($currentMessage)) {
                $this->client->sendMessage($fromId,  $fontReady . $currentMessage, Keyboard::backButton($lang), ["parse_mode" => 'HTML']);
            }


        } catch (\Throwable $e) {
            Logger::error("FontStep error", [
                'user_id' => $fromId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->client->sendMessage(
                $fromId,
                $this->language->get('font_error', $lang),
                Keyboard::backButton($lang)
            );

        }

    }



}
