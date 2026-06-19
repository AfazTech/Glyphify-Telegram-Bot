<?php
namespace Bot\Core;

use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;

class Logger
{
    private static ?MonologLogger $instance = null;
    private static bool $debugMode = false;

    public static function init(?string $logFile = null, bool $debugMode = false): MonologLogger
    {
        self::$debugMode = $debugMode;
        
        if (self::$instance !== null) {
            return self::$instance;
        }

        $logFile = $logFile ?? __DIR__ . '/../../logs/bot.log';
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logger = new MonologLogger('telegram-bot');
        $handler = new RotatingFileHandler($logFile, 30, Level::Info);
        $logger->pushHandler($handler);
        
        if ($debugMode) {
            $debugHandler = new RotatingFileHandler(
                str_replace('.log', '-debug.log', $logFile), 
                7, 
                Level::Debug
            );
            $logger->pushHandler($debugHandler);
        }
        
        $logger->info("Logger initialized", [
            'debug_mode' => $debugMode,
            'log_file' => $logFile,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
        self::$instance = $logger;
        return $logger;
    }

    public static function getInstance(): MonologLogger
    {
        if (self::$instance === null) {
            $debugMode = filter_var($_ENV['DEBUG_MODE'] ?? false, FILTER_VALIDATE_BOOLEAN);
            self::init(null, $debugMode);
        }
        return self::$instance;
    }

    public static function info(string $message, array $context = []): void
    {
        self::getInstance()->info($message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::getInstance()->error($message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::getInstance()->warning($message, $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        if (self::$debugMode) {
            self::getInstance()->debug($message, $context);
        }
    }

    public static function critical(string $message, array $context = []): void
    {
        self::getInstance()->critical($message, $context);
    }
}
