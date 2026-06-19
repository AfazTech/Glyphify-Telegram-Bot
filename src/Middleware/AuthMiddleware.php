<?php

namespace Bot\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Psr7\Factory\ResponseFactory;
use Bot\Core\App;

class AuthMiddleware
{
    private App $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        // Get authorization header
        $authHeader = $request->getHeaderLine('Authorization');
        
        // Get API token from database
        $configModel = $this->app->getConfigModel();
        $validToken = $configModel->get('api_token') ?? '';
        
        // Also check env as fallback
        if (empty($validToken)) {
            $validToken = $_ENV['API_TOKEN'] ?? '';
        }
        
        $isAuthorized = false;
        
        // Check Bearer token
        if (!empty($authHeader) && preg_match('/Bearer\s+(.+)$/i', $authHeader, $matches)) {
            $token = $matches[1];
            if ($token === $validToken) {
                $isAuthorized = true;
            }
        }
        
        if (!$isAuthorized) {
            $responseFactory = new ResponseFactory();
            $response = $responseFactory->createResponse(401);
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Unauthorized. Please provide valid Bearer token.'
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        }
        
        return $handler->handle($request);
    }
}
