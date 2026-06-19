<?php
namespace Bot\Models;

use Bot\Core\App;

class JoinMandatoryModel
{
    public function __construct(App $app)
    {
    }

    public function addChannel(int $chatId, string $link, ?string $title = null): void
    {
        JoinMandatoryChannel::create([
            'chat_id' => $chatId,
            'title' => $title,
            'link' => $link,
            'active' => true
        ]);
    }

    public function removeChannel(int $chatId): void
    {
        JoinMandatoryChannel::where('chat_id', $chatId)->delete();
    }

    public function toggleChannel(int $chatId, bool $active): void
    {
        JoinMandatoryChannel::where('chat_id', $chatId)->update(['active' => $active]);
    }

    public function listChannels(): array
    {
        return JoinMandatoryChannel::all()->toArray();
    }

    public function getActiveChannels(): array
    {
        return JoinMandatoryChannel::where('active', true)->get()->toArray();
    }
}
