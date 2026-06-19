<?php

use Bot\Core\App;
use Dotenv\Dotenv;

require 'vendor/autoload.php';

echo "========================================\n";
echo "🤖 TELEGRAM BOT\n";
echo "========================================\n";

if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();
    echo "✓ .env file loaded\n";
}

echo "✓ Initializing bot...\n";

try {
    $app = new App();
    echo "✓ Bot initialized successfully\n";
    echo "✓ Starting long polling...\n";
    echo "========================================\n";
    echo "🚀 Bot is running and listening for messages\n";
    echo "📝 Press Ctrl+C to stop\n";
    echo "========================================\n\n";
    
    $app->start();
} catch (Throwable $e) {
    echo "\n";
    echo "❌ FATAL ERROR: " . $e->getMessage() . "\n";
    echo "📁 File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "📋 Trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
