<?php

namespace Bot\Controllers;

use Bot\Core\App;
use Bot\Models\User;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class UserController
{
    private App $app;
    
    public function __construct(App $app)
    {
        $this->app = $app;
    }
    
    public function getUsers(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $limit = isset($queryParams['limit']) ? (int) $queryParams['limit'] : null;
        
        $query = User::orderBy('id', 'desc');
        if ($limit) {
            $query->limit($limit);
        }
        $users = $query->get()->toArray();
        
        return $this->jsonResponse($response, ['success' => true, 'data' => $users]);
    }
    
    public function getUser(Request $request, Response $response, array $args): Response
    {
        $userId = (int) $args['userId'];
        $user = User::where('user_id', $userId)->first();
        
        if (!$user) {
            return $this->jsonResponse($response, ['success' => false, 'error' => 'User not found'], 404);
        }
        
        return $this->jsonResponse($response, ['success' => true, 'data' => $user->toArray()]);
    }
    
    public function blockUser(Request $request, Response $response, array $args): Response
    {
        $userId = (int) $args['userId'];
        $data = json_decode($request->getBody()->getContents(), true);
        
        $reason = $data['reason'] ?? null;
        $duration = isset($data['duration']) ? (int) $data['duration'] : null;
        
        $user = User::where('user_id', $userId)->first();
        if (!$user) {
            return $this->jsonResponse($response, ['success' => false, 'error' => 'User not found'], 404);
        }
        
        $user->update([
            'blocked' => true,
            'block_reason' => $reason,
            'blocked_until' => $duration ? date('Y-m-d H:i:s', time() + $duration) : null
        ]);
        
        return $this->jsonResponse($response, ['success' => true, 'message' => 'User blocked successfully']);
    }
    
    public function unblockUser(Request $request, Response $response, array $args): Response
    {
        $userId = (int) $args['userId'];
        
        $user = User::where('user_id', $userId)->first();
        if (!$user) {
            return $this->jsonResponse($response, ['success' => false, 'error' => 'User not found'], 404);
        }
        
        $user->update([
            'blocked' => false,
            'block_reason' => null,
            'blocked_until' => null
        ]);
        
        return $this->jsonResponse($response, ['success' => true, 'message' => 'User unblocked successfully']);
    }
    
    public function deleteUser(Request $request, Response $response, array $args): Response
    {
        $userId = (int) $args['userId'];
        
        $user = User::where('user_id', $userId)->first();
        if (!$user) {
            return $this->jsonResponse($response, ['success' => false, 'error' => 'User not found'], 404);
        }
        
        User::where('user_id', $userId)->delete();
        
        return $this->jsonResponse($response, ['success' => true, 'message' => 'User deleted successfully']);
    }
    
    public function searchUsers(Request $request, Response $response, array $args): Response
    {
        $query = $args['query'];
        
        if (is_numeric($query)) {
            $user = User::where('user_id', (int) $query)->first();
            $users = $user ? [$user->toArray()] : [];
        } else {
            $users = User::where('username', 'like', "%{$query}%")
                ->orWhere('first_name', 'like', "%{$query}%")
                ->orWhere('last_name', 'like', "%{$query}%")
                ->orderBy('id', 'desc')
                ->limit(50)
                ->get()
                ->toArray();
        }
        
        return $this->jsonResponse($response, ['success' => true, 'data' => $users]);
    }
    
    private function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
