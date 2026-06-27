<?php

declare(strict_types=1);

namespace App\Handler\OAuth;

use App\Core\Contract\SessionInterface;
use App\Core\Logger;
use App\Http\Contract\ResponderInterface;
use App\Service\OAuth\Contract\GoogleOAuthServiceInterface;

final class GoogleOAuthStartHandler
{
    private const STATE_SESSION_KEY = 'google_oauth_state';

    public function __construct(
        private GoogleOAuthServiceInterface $googleOAuthService,
        private SessionInterface $session,
        private ResponderInterface $responder,
    ) {
    }

    public function handle(): void
    {
        $authorizationUrl = $this->googleOAuthService->getAuthorizationUrl();
        $state            = $this->googleOAuthService->getState();

        if ($authorizationUrl === '' || $state === '') {
            Logger::getLogger('auth')->error('google_oauth_start_failed', [
                'reason' => 'missing_authorization_url_or_state',
            ]);

            $this->responder->redirect('/coding-blog/login');
            return;
        }

        $this->session->set(self::STATE_SESSION_KEY, $state);

        Logger::getLogger('auth')->info('google_oauth_start', [
            'state_stored' => true,
        ]);

        $this->responder->redirect($authorizationUrl);
    }
}
