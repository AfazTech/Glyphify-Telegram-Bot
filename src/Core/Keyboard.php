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
        
        // فقط یک ردیف با دو دکمه: پشتیبانی و فونت
        $kb->row(
            $language->get('support', $lang),
            $language->get('font', $lang)
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
