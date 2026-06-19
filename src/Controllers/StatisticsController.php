<?php

namespace Bot\Controllers;

use Bot\Core\App;
use Bot\Models\User;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class StatisticsController
{
    private App $app;
    
    public function __construct(App $app)
    {
        $this->app = $app;
    }
    
    public function getStatistics(Request $request, Response $response): Response
    {
        $stats = $this->getGeneralStats();
        
        return $this->jsonResponse($response, ['success' => true, 'data' => $stats]);
    }
    
    public function getDailyStats(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $days = isset($queryParams['days']) ? (int) $queryParams['days'] : 7;
        
        $stats = $this->getDailyStatsData($days);
        
        return $this->jsonResponse($response, ['success' => true, 'data' => $stats]);
    }
    
    private function getGeneralStats(): array
    {
        return [
            'total_users' => User::count(),
            'blocked_users' => User::where('blocked', true)->count(),
            'active_users' => User::where('status', true)->count(),
            'admins' => User::where('is_admin', true)->count(),
            'last_updated' => date('Y-m-d H:i:s')
        ];
    }
    
    private function getDailyStatsData(int $days): array
    {
        $stats = [];
        
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $stats[] = [
                'date' => $date,
                'new_users' => User::whereDate('created_at', $date)->count(),
                'active_users' => User::whereDate('updated_at', $date)->count()
            ];
        }
        
        return $stats;
    }
    
    private function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
