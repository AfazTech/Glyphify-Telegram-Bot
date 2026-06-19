<?php
namespace Bot\Callbacks;

use Bot\Attributes\Callback;
use Bot\Core\App;
use Bot\Middleware\JoinMandatoryMiddleware;

#[Callback(data: 'user_check_join', isStep: false)]
class JoinCheckCallback
{
    private JoinMandatoryMiddleware $joinMiddleware;

    public function __construct(App $app)
    {
        $this->joinMiddleware = new JoinMandatoryMiddleware($app);
    }

    public function handle(array $update): void
    {
        $this->joinMiddleware->handleCallback($update);
    }
}
