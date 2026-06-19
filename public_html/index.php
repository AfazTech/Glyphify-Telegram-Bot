<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use Bot\Core\App;
use Bot\Controllers\AdminController;
use Bot\Controllers\UserController;
use Bot\Controllers\StatisticsController;
use Bot\Middleware\AuthMiddleware;
use Slim\Routing\RouteCollectorProxy;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// Load environment
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

// Create Slim app (بدون Container)
$app = AppFactory::create();

// Error handling
$debugMode = filter_var($_ENV['DEBUG_MODE'] ?? true, FILTER_VALIDATE_BOOLEAN);
$errorMiddleware = $app->addErrorMiddleware($debugMode, true, true);

// CORS Middleware
$app->add(function (Request $request, $handler) use ($app) {
    if ($request->getMethod() === 'OPTIONS') {
        $response = $app->getResponseFactory()->createResponse(200);
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin')
            ->withHeader('Access-Control-Max-Age', '86400');
    }
    
    $response = $handler->handle($request);
    
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin')
        ->withHeader('Access-Control-Allow-Credentials', 'true');
});

$app->addBodyParsingMiddleware();

// ==================== Create Bot App Instance ====================
// فقط یکبار ایجاد می‌شود
$botApp = null;

function getBotApp() {
    global $botApp;
    if ($botApp === null) {
        $botApp = new App();
    }
    return $botApp;
}

// ==================== Helper to create Controllers ====================
function makeController($className) {
    $app = getBotApp();
    return new $className($app);
}

// ==================== Public Routes ====================

$app->get('/health', function (Request $request, Response $response) {
    try {
        $response->getBody()->write(json_encode([
            'status' => 'ok',
            'timestamp' => time(),
            'message' => 'API is working correctly',
            'version' => '1.0.0'
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    } catch (\Throwable $e) {
        $response->getBody()->write(json_encode([
            'status' => 'error',
            'error' => $e->getMessage()
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
});

$app->get('/', function (Request $request, Response $response) {
    $response->getBody()->write(json_encode([
        'name' => 'Telegram Bot API',
        'status' => 'running',
        'documentation' => 'See API_DOCUMENTATION.md',
        'auth' => 'Bearer token required for /api/* endpoints',
        'endpoints' => [
            'GET /health' => 'Health check',
            'GET /api/statistics' => 'Get bot statistics',
            'GET /api/users' => 'List users',
            'POST /api/broadcast/setup' => 'Setup broadcast',
            'POST /api/broadcast/enable' => 'Enable broadcast',
            'POST /api/broadcast/disable' => 'Disable broadcast',
            'GET /api/languages' => 'Get available languages',
            'POST /api/languages/default' => 'Set default language',
            'GET /api/users/{userId}/language' => 'Get user language',
            'POST /api/users/{userId}/language' => 'Set user language',
            'POST /api/languages/reload' => 'Reload language files',
        ]
    ]));
    return $response->withHeader('Content-Type', 'application/json');
});

// ==================== Auth Middleware for API ====================

$authMiddleware = function (Request $request, $handler) {
    $botApp = getBotApp();
    $authMiddleware = new AuthMiddleware($botApp);
    return $authMiddleware($request, $handler);
};

// ==================== Protected Routes ====================

$app->group('/api', function (RouteCollectorProxy $group) {
    // User Management
    $group->get('/users', function (Request $request, Response $response) {
        $controller = makeController(UserController::class);
        return $controller->getUsers($request, $response);
    });
    
    $group->get('/users/{userId}', function (Request $request, Response $response, array $args) {
        $controller = makeController(UserController::class);
        return $controller->getUser($request, $response, $args);
    });
    
    $group->post('/users/{userId}/block', function (Request $request, Response $response, array $args) {
        $controller = makeController(UserController::class);
        return $controller->blockUser($request, $response, $args);
    });
    
    $group->post('/users/{userId}/unblock', function (Request $request, Response $response, array $args) {
        $controller = makeController(UserController::class);
        return $controller->unblockUser($request, $response, $args);
    });
    
    $group->delete('/users/{userId}', function (Request $request, Response $response, array $args) {
        $controller = makeController(UserController::class);
        return $controller->deleteUser($request, $response, $args);
    });
    
    $group->get('/users/search/{query}', function (Request $request, Response $response, array $args) {
        $controller = makeController(UserController::class);
        return $controller->searchUsers($request, $response, $args);
    });
    
    // Statistics
    $group->get('/statistics', function (Request $request, Response $response) {
        $controller = makeController(StatisticsController::class);
        return $controller->getStatistics($request, $response);
    });
    
    $group->get('/statistics/daily', function (Request $request, Response $response) {
        $controller = makeController(StatisticsController::class);
        return $controller->getDailyStats($request, $response);
    });
    
    // Broadcast
    $group->post('/broadcast/setup', function (Request $request, Response $response) {
        $controller = makeController(AdminController::class);
        return $controller->setupBroadcast($request, $response);
    });
    
    $group->get('/broadcast/status', function (Request $request, Response $response) {
        $controller = makeController(AdminController::class);
        return $controller->getBroadcastStatus($request, $response);
    });
    
    $group->post('/broadcast/enable', function (Request $request, Response $response) {
        $controller = makeController(AdminController::class);
        return $controller->enableBroadcast($request, $response);
    });
    
    $group->post('/broadcast/disable', function (Request $request, Response $response) {
        $controller = makeController(AdminController::class);
        return $controller->disableBroadcast($request, $response);
    });
    
    $group->post('/broadcast/send', function (Request $request, Response $response) {
        $controller = makeController(AdminController::class);
        return $controller->sendBroadcastNow($request, $response);
    });
    
    // Join Mandatory
    $group->get('/join-mandatory/channels', function (Request $request, Response $response) {
        $controller = makeController(AdminController::class);
        return $controller->getChannels($request, $response);
    });
    
    $group->post('/join-mandatory/channels', function (Request $request, Response $response) {
        $controller = makeController(AdminController::class);
        return $controller->addChannel($request, $response);
    });
    
    $group->delete('/join-mandatory/channels/{chatId}', function (Request $request, Response $response, array $args) {
        $controller = makeController(AdminController::class);
        return $controller->removeChannel($request, $response, $args);
    });
    
    $group->put('/join-mandatory/channels/{chatId}/toggle', function (Request $request, Response $response, array $args) {
        $controller = makeController(AdminController::class);
        return $controller->toggleChannel($request, $response, $args);
    });
    
    // Anti-Spam
    $group->get('/anti-spam/config', function (Request $request, Response $response) {
        $controller = makeController(AdminController::class);
        return $controller->getAntiSpamConfig($request, $response);
    });
    
    $group->put('/anti-spam/config', function (Request $request, Response $response) {
        $controller = makeController(AdminController::class);
        return $controller->updateAntiSpamConfig($request, $response);
    });
    
    // Ads
    $group->get('/ads/random', function (Request $request, Response $response) {
        $controller = makeController(AdminController::class);
        return $controller->getRandomAdConfig($request, $response);
    });
    
    $group->post('/ads/random', function (Request $request, Response $response) {
        $controller = makeController(AdminController::class);
        return $controller->updateRandomAdConfig($request, $response);
    });
    
    $group->get('/ads/menu', function (Request $request, Response $response) {
        $controller = makeController(AdminController::class);
        return $controller->getMenuAdConfig($request, $response);
    });
    
    $group->post('/ads/menu', function (Request $request, Response $response) {
        $controller = makeController(AdminController::class);
        return $controller->updateMenuAdConfig($request, $response);
    });
    
    // Bot Management
    $group->get('/bot/info', function (Request $request, Response $response) {
        $controller = makeController(AdminController::class);
        return $controller->getBotInfo($request, $response);
    });
    
    $group->get('/bot/maintenance', function (Request $request, Response $response) {
        $controller = makeController(AdminController::class);
        return $controller->getMaintenanceStatus($request, $response);
    });
    
    $group->post('/bot/maintenance', function (Request $request, Response $response) {
        $controller = makeController(AdminController::class);
        return $controller->setMaintenanceMode($request, $response);
    });
    
    $group->post('/bot/maintenance/message', function (Request $request, Response $response) {
        $controller = makeController(AdminController::class);
        return $controller->setMaintenanceMessage($request, $response);
    });
    
    $group->get('/bot/server-info', function (Request $request, Response $response) {
        $controller = makeController(AdminController::class);
        return $controller->getServerInfo($request, $response);
    });
    
    $group->post('/bot/backup', function (Request $request, Response $response) {
        $controller = makeController(AdminController::class);
        return $controller->createBackup($request, $response);
    });
    
    // Config
    $group->get('/config', function (Request $request, Response $response) {
        $controller = makeController(AdminController::class);
        return $controller->getAllConfigs($request, $response);
    });
    
    $group->get('/config/{key}', function (Request $request, Response $response, array $args) {
        $controller = makeController(AdminController::class);
        return $controller->getConfig($request, $response, $args);
    });
    
    $group->put('/config/{key}', function (Request $request, Response $response, array $args) {
        $controller = makeController(AdminController::class);
        return $controller->setConfig($request, $response, $args);
    });
    
    // Language Management
    $group->get('/languages', function (Request $request, Response $response) {
        $controller = makeController(AdminController::class);
        return $controller->getLanguages($request, $response);
    });
    
    $group->post('/languages/default', function (Request $request, Response $response) {
        $controller = makeController(AdminController::class);
        return $controller->setDefaultLanguage($request, $response);
    });
    
    $group->get('/users/{userId}/language', function (Request $request, Response $response, array $args) {
        $controller = makeController(AdminController::class);
        return $controller->getUserLanguage($request, $response, $args);
    });
    
    $group->post('/users/{userId}/language', function (Request $request, Response $response, array $args) {
        $controller = makeController(AdminController::class);
        return $controller->setUserLanguage($request, $response, $args);
    });
    
    $group->post('/languages/reload', function (Request $request, Response $response) {
        $controller = makeController(AdminController::class);
        return $controller->reloadLanguages($request, $response);
    });
    
})->add($authMiddleware);

$app->run();
