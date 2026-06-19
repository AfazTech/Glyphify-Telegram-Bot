<?php
namespace Bot\Core;

class Language
{
    private static ?Language $instance = null;
    private array $translations = [];
    private string $defaultLanguage = 'fa';
    private array $loadedLanguages = [];
    private string $langDir;

    private function __construct()
    {
        $this->langDir = __DIR__ . '/../../lang';
        $this->loadAllLanguages();
    }

    public static function getInstance(): Language
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function loadAllLanguages(): void
    {
        if (!is_dir($this->langDir)) {
            mkdir($this->langDir, 0755, true);
            return;
        }

        $files = glob($this->langDir . '/*.json');
        foreach ($files as $file) {
            $langCode = pathinfo($file, PATHINFO_FILENAME);
            $content = file_get_contents($file);
            $this->translations[$langCode] = json_decode($content, true) ?? [];
            $this->loadedLanguages[] = $langCode;
        }

        if (!isset($this->translations[$this->defaultLanguage])) {
            $this->translations[$this->defaultLanguage] = [];
        }
    }

    public function get(string $key, ?string $lang = null, array $params = []): string
    {
        $lang = $lang ?? $this->defaultLanguage;

        $text = $this->translations[$lang][$key] ?? 
                $this->translations[$this->defaultLanguage][$key] ?? 
                $key;

        foreach ($params as $param => $value) {
            $text = str_replace('{' . $param . '}', $value, $text);
        }

        return $text;
    }

    public function getAvailableLanguages(): array
    {
        return $this->loadedLanguages;
    }

    public function getLanguageName(string $code): string
    {
        $names = [
            'fa' => 'فارسی',
            'en' => 'English',
        ];
        return $names[$code] ?? $code;
    }

    public function reload(): void
    {
        $this->translations = [];
        $this->loadedLanguages = [];
        $this->loadAllLanguages();
    }

    public function addTranslation(string $lang, string $key, string $value): void
    {
        if (!isset($this->translations[$lang])) {
            $this->translations[$lang] = [];
        }
        $this->translations[$lang][$key] = $value;
    }

    public function saveLanguageFile(string $lang): bool
    {
        if (!isset($this->translations[$lang])) {
            return false;
        }

        if (!is_dir($this->langDir)) {
            mkdir($this->langDir, 0755, true);
        }

        $file = $this->langDir . '/' . $lang . '.json';
        $json = json_encode($this->translations[$lang], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        return file_put_contents($file, $json) !== false;
    }

    public function getDefaultLanguage(): string
    {
        return $this->defaultLanguage;
    }

    public function setDefaultLanguage(string $lang): void
    {
        if (in_array($lang, $this->loadedLanguages)) {
            $this->defaultLanguage = $lang;
        }
    }
}
