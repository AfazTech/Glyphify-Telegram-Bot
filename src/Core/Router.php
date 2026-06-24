<?php
namespace Bot\Core;

use Bot\Attributes\Text;
use Bot\Attributes\Callback;
use Bot\Attributes\Step;
use Bot\Models\UserModel;
use Neili\Client;

class Router
{
    private array $commands = [];
    private array $texts = [];
    private array $callbacks = [];
    private array $steps = [];
    private Container $container;
    private UserModel $userModel;
    private Client $client;

    public function __construct(Container $container)
    {
        $this->container = $container;
        Logger::debug("Router instance created");
    }

    public function discover(string $directory): void
    {
        if (!is_dir($directory)) {
            Logger::warning("Router discovery directory not found", ['directory' => $directory]);
            return;
        }
        
        $files = glob($directory . '/*.php');
        $discoveredCount = 0;
        
        foreach ($files as $file) {
            $className = 'Bot\\Handlers';
            $relativePath = str_replace(__DIR__ . '/../Handlers/', '', $file);
            $className .= '\\' . str_replace(['/', '.php'], ['\\', ''], $relativePath);
            
            if (!class_exists($className)) {
                continue;
            }
            
            $reflection = new \ReflectionClass($className);
            
            $stepAttr = $this->getAttribute($reflection, Step::class);
            if ($stepAttr) {
                $this->steps[$stepAttr->name] = [
                    'class' => $className,
                    'next' => $stepAttr->nextStep,
                    'autoClear' => $stepAttr->autoClear
                ];
                Logger::debug("Discovered step handler", ['name' => $stepAttr->name, 'class' => $className]);
                $discoveredCount++;
                continue;
            }
            
            $callbackAttr = $this->getAttribute($reflection, Callback::class);
            if ($callbackAttr) {
                $this->callbacks[$callbackAttr->data] = [
                    'class' => $className,
                    'isStep' => $callbackAttr->isStep
                ];
                Logger::debug("Discovered callback handler", ['data' => $callbackAttr->data, 'class' => $className]);
                $discoveredCount++;
                continue;
            }
            
            // Get all Text attributes (including repeatable ones)
            $textAttrs = $reflection->getAttributes(Text::class);
            if (!empty($textAttrs)) {
                foreach ($textAttrs as $attr) {
                    $textAttr = $attr->newInstance();
                    $handlerData = [
                        'class' => $className,
                        'priority' => $textAttr->priority
                    ];
                    
                    if ($textAttr->isCommand) {
                        // برای کامندها، اگر قبلاً وجود داشت، اولویت بالاتر را نگه دار
                        if (!isset($this->commands[$textAttr->name]) || 
                            $textAttr->priority > $this->commands[$textAttr->name]['priority']) {
                            $this->commands[$textAttr->name] = $handlerData;
                        }
                        Logger::debug("Discovered command handler", [
                            'name' => $textAttr->name, 
                            'class' => $className,
                            'priority' => $textAttr->priority
                        ]);
                    } else {
                        // برای تکست‌ها، اگر قبلاً وجود داشت، اولویت بالاتر را نگه دار
                        if (!isset($this->texts[$textAttr->name]) || 
                            $textAttr->priority > $this->texts[$textAttr->name]['priority']) {
                            $this->texts[$textAttr->name] = $handlerData;
                        }
                        Logger::debug("Discovered text/button handler", [
                            'name' => $textAttr->name, 
                            'class' => $className,
                            'priority' => $textAttr->priority
                        ]);
                    }
                    $discoveredCount++;
                }
            }
        }
        
        if ($discoveredCount > 0) {
            Logger::debug("Router discovered handlers", ['directory' => basename($directory), 'count' => $discoveredCount]);
        }
    }

    private function getAttribute(\ReflectionClass $reflection, string $attributeClass): ?object
    {
        $attributes = $reflection->getAttributes($attributeClass);
        return empty($attributes) ? null : $attributes[0]->newInstance();
    }

    public function resolve(array $update): void
    {
        $this->userModel = $this->container->get(UserModel::class);
        $this->client = $this->container->get(Client::class);
        
        $fromId = $update['message']['from']['id'] ?? $update['callback_query']['from']['id'] ?? null;

        if (!$fromId) {
            Logger::warning("Cannot resolve route: no from_id found");
            return;
        }

        $text = trim($update['message']['text'] ?? '');
        $callbackData = $update['callback_query']['data'] ?? null;

        Logger::debug("Resolving route", ['user_id' => $fromId, 'type' => $callbackData ? 'callback' : ($text ? 'text' : 'unknown')]);

        if ($callbackData !== null) {
            foreach ($this->callbacks as $pattern => $config) {
                if (str_starts_with($callbackData, $pattern)) {
                    Logger::debug("Callback matched", ['pattern' => $pattern, 'class' => $config['class']]);
                    $this->executeHandler($config['class'], $update);
                    
                    if ($config['isStep'] ?? false) {
                        $this->userModel->setStep($fromId, null);
                    }
                    return;
                }
            }
            Logger::debug("No callback handler matched", ['data' => $callbackData]);
            return;
        }

        // اولویت 1: کامندها (با بالاترین اولویت)
        if ($text !== '' && str_starts_with($text, '/')) {
            $commandName = explode(' ', $text)[0];
            if (isset($this->commands[$commandName])) {
                Logger::debug("Command matched", [
                    'command' => $commandName, 
                    'class' => $this->commands[$commandName]['class'],
                    'priority' => $this->commands[$commandName]['priority']
                ]);
                $this->executeHandler($this->commands[$commandName]['class'], $update);
                return;
            }
        }

        // اولویت 2: تکست‌های معمولی (مانند back) - قبل از استپ‌ها
        // این کار باعث می‌شود دکمه back حتی در حالت استپ هم کار کند
        if ($text !== '') {
            $language = Language::getInstance();
            $lang = $this->userModel->getLanguage($fromId);
            
            // مرتب‌سازی تکست‌ها بر اساس اولویت (بالاترین اولویت اول)
            $sortedTexts = $this->texts;
            uasort($sortedTexts, function($a, $b) {
                return $b['priority'] <=> $a['priority'];
            });
            
            foreach ($sortedTexts as $textKey => $config) {
                $translatedText = $language->get($textKey, $lang);
                if ($text === $translatedText) {
                    Logger::debug("Text/Button matched (before step)", [
                        'textKey' => $textKey, 
                        'class' => $config['class'],
                        'priority' => $config['priority']
                    ]);
                    
                    // اگر textKey برابر با 'back' باشد، استپ را پاک می‌کنیم
                    if ($textKey === 'back') {
                        $this->userModel->setStep($fromId, null);
                        Logger::debug("Step cleared for back button", ['user_id' => $fromId]);
                    }
                    
                    $this->executeHandler($config['class'], $update);
                    return;
                }
            }
        }

        // اولویت 3: استپ‌ها
        $currentStep = $this->userModel->getStep($fromId);
        if ($currentStep !== null && isset($this->steps[$currentStep])) {
            $stepConfig = $this->steps[$currentStep];
            Logger::debug("Step matched", ['step' => $currentStep, 'class' => $stepConfig['class']]);
            $this->executeHandler($stepConfig['class'], $update);
            
            if ($stepConfig['autoClear']) {
                $this->userModel->setStep($fromId, $stepConfig['next']);
            }
            return;
        }

        Logger::debug("No route matched, sending unknown command message", ['user_id' => $fromId, 'text' => $text]);
        $lang = $this->userModel->getLanguage($fromId);
        $language = Language::getInstance();
        $message = $language->get('unknown_command', $lang);
        $this->client->sendMessage($fromId, $message);
    }

    private function executeHandler(string $handlerClass, array $update): void
    {
        try {
            $handler = $this->container->get($handlerClass);
            $handler->handle($update);
            Logger::debug("Handler executed successfully", ['class' => $handlerClass]);
        } catch (\Throwable $e) {
            Logger::error("Handler execution failed", [
                'class' => $handlerClass,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $fromId = $update['message']['from']['id'] ?? null;
            if ($fromId) {
                $lang = $this->userModel->getLanguage($fromId);
                $language = Language::getInstance();
                $message = $language->get('error', $lang);
                $this->client->sendMessage($fromId, $message);
            }
        }
    }

    public function getStep(string $stepName): ?array
    {
        return $this->steps[$stepName] ?? null;
    }

    public function registerStepHandler(string $stepName, string $handlerClass, ?string $nextStep = null, bool $autoClear = true): void
    {
        $this->steps[$stepName] = [
            'class' => $handlerClass,
            'next' => $nextStep,
            'autoClear' => $autoClear
        ];
        Logger::debug("Step handler registered dynamically", ['name' => $stepName, 'class' => $handlerClass]);
    }
}
