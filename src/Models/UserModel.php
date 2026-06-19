<?php
namespace Bot\Models;

use Bot\Core\App;
use Bot\Core\Config;
use Bot\Models\User;

class UserModel
{
    protected Config $config;
    protected App $app;

    public function __construct(App $app)
    {
        $this->app = $app;
        $this->config = $app->getConfig();
    }
    
    public function addUser(int $userId, ?string $username = null, ?string $firstName = null, ?string $lastName = null): ?User
    {
        $user = $this->getUser($userId);
        if (!$user) {
            $user = User::create([
                'user_id' => $userId,
                'username' => $username,
                'first_name' => $firstName,
                'last_name' => $lastName
            ]);
            return $user;
        }
        return null;
    }

    public function getAdmins(?int $limit = null): array
    {
        $query = User::where('is_admin', true)->orderBy('id', 'desc');
        if ($limit) {
            $query->limit($limit);
        }
        return $query->get()->toArray();
    }
    
    public function isOwner(int $userId): bool
    {
        return in_array($userId, $this->config->getOwners());
    }

    public function getUser(int $userId): ?array
    {
        $user = User::where('user_id', $userId)->first();
        return $user ? $user->toArray() : null;
    }

    public function updateUser(int $userId, array $data): void
    {
        $user = User::where('user_id', $userId)->first();
        if ($user) {
            $user->update($data);
        }
    }

    public function setStep(int $userId, ?string $step = null): void
    {
        User::updateOrCreate(
            ['user_id' => $userId],
            ['step' => $step]
        );
    }

    public function setTemp(int $userId, string $key, $data, int $ttl = 86400): void
    {
        $user = User::firstOrCreate(['user_id' => $userId]);
        $temp = $user->temp ?? [];
        $temp[$key] = [
            'value' => $data,
            'expires' => time() + $ttl
        ];
        $user->temp = $temp;
        $user->save();
    }

    public function getLanguage(int $userId): string
    {
        $user = $this->getUser($userId);
        return $user['language'] ?? 'fa';
    }

    public function setLanguage(int $userId, string $lang): void
    {
        $this->updateUser($userId, ['language' => $lang]);
    }

    public function getLanguageName(int $userId): string
    {
        $lang = $this->getLanguage($userId);
        $names = [
            'fa' => 'فارسی',
            'en' => 'English',
        ];
        return $names[$lang] ?? $lang;
    }

    public function deleteUser(int $userId): void
    {
        User::where('user_id', $userId)->delete();
    }

    public function getStep(int $userId): ?string
    {
        $user = User::where('user_id', $userId)->first();
        return $user ? $user->step : null;
    }

    public function getTemp(int $userId, string $key): ?string
    {
        $user = User::where('user_id', $userId)->first();
        if (!$user || !isset($user->temp[$key])) {
            return null;
        }
        
        $item = $user->temp[$key];
        if ($item['expires'] < time()) {
            $temp = $user->temp;
            unset($temp[$key]);
            $user->temp = $temp;
            $user->save();
            return null;
        }
        
        return $item['value'];
    }

    public function listUsers(array $conditions = [], array $order = []): array
    {
        $query = User::query();
        
        foreach ($conditions as $key => $value) {
            if (is_array($value) && count($value) === 3) {
                $query->where($value[0], $value[1], $value[2]);
            } elseif (is_array($value)) {
                $query->whereIn($key, $value);
            } else {
                $query->where($key, $value);
            }
        }
        
        foreach ($order as $column => $direction) {
            $query->orderBy($column, $direction);
        }
        
        return $query->get()->toArray();
    }

    public function syncUser(int $userId, ?string $username = null, ?string $firstName = null, ?string $lastName = null): array
    {
        $user = $this->getUser($userId);
        if (!$user) {
            $this->addUser($userId, $username, $firstName, $lastName);
            $user = $this->getUser($userId);
            return $user;
        }

        $hasChanges = false;

        if ($username != $user['username']) {
            $user['username'] = $username;
            $hasChanges = true;
        }
        if ($firstName != $user['first_name']) {
            $user['first_name'] = $firstName;
            $hasChanges = true;
        }
        if ($lastName != $user['last_name']) {
            $user['last_name'] = $lastName;
            $hasChanges = true;
        }

        if ($hasChanges) {
            User::where('user_id', $userId)->update([
                'username' => $username,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        }

        return $user;
    }

    public function searchUser(string|int $query, ?int $limit = null): array
    {
        $queryBuilder = User::query();
        
        if (is_numeric($query)) {
            $user = User::where('user_id', (int) $query)->first();
            return $user ? [$user->toArray()] : [];
        }

        $queryBuilder->where(function($q) use ($query) {
            $q->where('username', 'like', "%{$query}%")
              ->orWhere('first_name', 'like', "%{$query}%")
              ->orWhere('last_name', 'like', "%{$query}%");
        })->orderBy('id', 'desc');

        if ($limit) {
            $queryBuilder->limit($limit);
        }

        return $queryBuilder->get()->toArray();
    }

    public function blockUser(int $userId, ?string $reason = null, ?int $duration = null): void
    {
        $data = [
            'blocked' => true,
            'block_reason' => $reason,
            'blocked_until' => $duration
                ? date('Y-m-d H:i:s', time() + $duration)
                : null
        ];
        $this->updateUser($userId, $data);
    }

    public function unblockUser(int $userId): void
    {
        $this->updateUser($userId, [
            'blocked' => false,
            'block_reason' => null,
            'blocked_until' => null
        ]);
    }

    public function isBlocked(int $userId): bool
    {
        $user = $this->getUser($userId);
        if (!$user)
            return false;

        if ($user['blocked'] && $user['blocked_until']) {
            if (strtotime($user['blocked_until']) < time()) {
                $this->unblockUser($userId);
                return false;
            }
        }

        return (bool) $user['blocked'];
    }
    
    public function isAdmin(int $userId): bool
    {
        $user = $this->getUser($userId);
        if (!$user)
            return false;
        return (bool) $user['is_admin'];
    }
    
    public function isJoinedMandatoryChannels(int $userId): bool
    {
        $user = $this->getUser($userId);
        if (!$user)
            return false;
        return (bool) $user['join_mandatory_channels'];
    }
    
    public function getUsers(?int $limit = null): array
    {
        $query = User::orderBy('id', 'desc');
        if ($limit) {
            $query->limit($limit);
        }
        return $query->get()->toArray();
    }
    
    public function resetAllJoinMandatory(): void
    {
        User::query()->update(['join_mandatory_channels' => false]);
    }

    public function setUserStatus(int $userId, bool $status): void
    {
        $this->updateUser($userId, ['status' => $status]);
    }
}
