<?php
namespace Bot\Core;

use Neili\KeyboardBuilder;

class Keyboard
{
    public static function mainMenu(?string $lang = null): array
    {
        $language = Language::getInstance();
        $lang = $lang ?? 'fa';

        $kb = new KeyboardBuilder();
        
        // دو دکمه: فونت و درباره
        $kb->row(
            $language->get('font', $lang),
            $language->get('about', $lang)
        );

        return $kb->resize(true)->oneTime(false)->build();
    }

    public static function cancelButton(?string $lang = null): array
    {
        $language = Language::getInstance();
        $lang = $lang ?? 'fa';
        
        return (new KeyboardBuilder())
            ->row('❌ ' . $language->get('cancel', $lang))
            ->resize(true)
            ->oneTime(true)
            ->build();
    }

    public static function backButton(?string $lang = null): array
    {
        $language = Language::getInstance();
        $lang = $lang ?? 'fa';
        
        return (new KeyboardBuilder())
            ->row('⬅️ ' . $language->get('back', $lang))
            ->resize(true)
            ->oneTime(true)
            ->build();
    }
}
