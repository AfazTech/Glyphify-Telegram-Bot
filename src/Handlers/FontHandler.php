<?php
namespace Bot\Handlers;

use Bot\Attributes\Text;
use Bot\Core\Language;
use Bot\Models\UserModel;
use Neili\Client;
use Bot\Core\Keyboard;
use Bot\Core\Logger;
use AfazTech\Glyphify\Glyphify;

#[Text(name: 'font', priority: 5)]
class FontHandler
{
    private const MAX_MESSAGE_LENGTH = 4000;

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
        

        $this->client->sendMessage(
                $fromId,
                $this->language->get('font_prompt', $lang),
                Keyboard::backButton($lang)
            );
            $this->userModel->setStep($fromId, 'font_input');
         
}

}
