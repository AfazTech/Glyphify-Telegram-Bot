<?php
namespace Bot\Models;

use Bot\Core\App;
use Bot\Core\Logger;

class ConfigModel
{
    private App $app;

    public function __construct(App $app)
    {
        $this->app = $app;
        Logger::debug("ConfigModel initialized");
    }

    public function get(string $key)
    {
        try {
            $row = BotConfig::where('key', $key)->first();
            return $row ? $row->value : null;
        } catch (\Throwable $e) {
            Logger::error("Failed to get config", [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    public function getInt(string $key, int $default = 0): int
    {
        $value = $this->get($key);
        return $value !== null ? (int) $value : $default;
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $value = $this->get($key);
        return $value !== null ? (bool) $value : $default;
    }

    public function set(string $key, $value): void
    {
        try {
            BotConfig::updateOrCreate(
                ['key' => $key],
                ['value' => (string) $value]
            );
            Logger::debug("Config updated", ['key' => $key, 'value' => $value]);
        } catch (\Throwable $e) {
            Logger::error("Failed to set config", [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function all(): array
    {
        try {
            $configs = BotConfig::all(['key', 'value'])->toArray();
            $result = [];
            foreach ($configs as $config) {
                $result[$config['key']] = $config['value'];
            }
            Logger::debug("All configs loaded", ['count' => count($result)]);
            return $result;
        } catch (\Throwable $e) {
            Logger::error("Failed to load all configs", ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function has(string $key): bool
    {
        try {
            return BotConfig::where('key', $key)->exists();
        } catch (\Throwable $e) {
            Logger::error("Failed to check config existence", ['key' => $key]);
            return false;
        }
    }

    public function delete(string $key): void
    {
        try {
            BotConfig::where('key', $key)->delete();
            Logger::debug("Config deleted", ['key' => $key]);
        } catch (\Throwable $e) {
            Logger::error("Failed to delete config", ['key' => $key]);
        }
    }

    public function getToken(): ?string
    {
        return $this->get('token');
    }

    public function getApiUrl(): ?string
    {
        return $this->get('api_url');
    }

    public function isDebugMode(): bool
    {
        return $this->getBool('debug_mode', false);
    }
}
