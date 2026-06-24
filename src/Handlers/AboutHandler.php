<?php
namespace Bot\Handlers;

use Bot\Attributes\Text;
use Bot\Core\Language;
use Bot\Models\UserModel;
use Neili\Client;
use Bot\Core\Keyboard;

#[Text(name: 'about', priority: 5)]
class AboutHandler
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

        $text = $this->language->get('about_text', $lang);
        $keyboard = Keyboard::mainMenu($lang);
        $this->client->sendMessage($fromId, $text, $keyboard);
    }
}
