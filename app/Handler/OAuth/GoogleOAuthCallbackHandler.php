<?php

declare(strict_types=1);

namespace App\Handler\OAuth;

use App\Core\Contract\FlashInterface;
use App\Core\Contract\SessionInterface;
use App\Core\ErrorCode;
use App\Core\Logger;
use App\Core\MessageManager;
use App\Http\Contract\ResponderInterface;
use App\Model\Contract\OAuthAccountModelInterface;
use App\Model\Contract\UserModelInterface;
use App\Model\Entity\OAuthAccountEntity;
use App\Model\Entity\UserEntity;
use App\Service\OAuth\Contract\GoogleOAuthServiceInterface;
use App\Service\OAuth\Contract\OAuthUserProvisioningServiceInterface;
use RuntimeException;

final class GoogleOAuthCallbackHandler
{
    private const PROVIDER          = 'google';
    private const STATE_SESSION_KEY = 'google_oauth_state';
    private const LOGIN_REDIRECT    = '/coding-blog/login';
    private const SUCCESS_REDIRECT  = '/coding-blog';

    public function __construct(
        private GoogleOAuthServiceInterface $googleOAuthService,
        private OAuthAccountModelInterface $oauthAccountModel,
        private UserModelInterface $userModel,
        private OAuthUserProvisioningServiceInterface $oauthProvisioning,
        private SessionInterface $session,
        private FlashInterface $flash,
        private ResponderInterface $responder,
    ) {
    }

    /**
     * @param array<string,mixed> $query
     */
    public function handle(array $query): void
    {
        try {
            [$code, $state, $error] = $this->extractCallbackData($query);

            if ($this->handleOAuthProviderError($error)) {
                return;
            }

            if (!$this->isValidCallbackInput($code, $state)) {
                return;
            }

            $profile = $this->loadGoogleProfile($code);

            if (!$this->validateGoogleProfile($profile)) {
                return;
            }

            if ($this->loginExistingOAuthAccount($profile)) {
                return;
            }

            $user = $this->resolveUser($profile);

            if ($user === null) {
                $this->replyFailure(ErrorCode::AUTH_GOOGLE_TECHNICAL_ERROR);
                return;
            }

            $this->completeOAuthLogin($user, $profile);
        } catch (\Throwable $e) {
            Logger::getLogger('auth')->error('google_oauth_callback_failed', [
                'exception' => $e->getMessage(),
            ]);

            $this->replyFailure(ErrorCode::AUTH_GOOGLE_TECHNICAL_ERROR);
        }
    }

    /**
    * @param array<string,mixed> $query
    * @return array{0:string,1:string,2:string}
    */
    private function extractCallbackData(array $query): array
    {
        return [
            $this->stringValue($query['code'] ?? null),
            $this->stringValue($query['state'] ?? null),
            $this->stringValue($query['error'] ?? null),
        ];
    }

    private function handleOAuthProviderError(string $error): bool
    {
        if ($error !== 'access_denied') {
            return false;
        }

        Logger::getLogger('auth')->info('google_oauth_access_denied');

        $this->replyFailure(ErrorCode::AUTH_GOOGLE_ACCESS_DENIED);
        return true;
    }

    private function isValidCallbackInput(string $code, string $state): bool
    {
        if ($code === '' || $state === '') {
            $this->replyFailure(ErrorCode::AUTH_GOOGLE_INVALID_RESPONSE);
            return false;
        }

        $expectedState = $this->session->get(self::STATE_SESSION_KEY);

        if (!is_string($expectedState) || $expectedState === '' || !hash_equals($expectedState, $state)) {
            Logger::getLogger('auth')->warning('google_oauth_invalid_state', [
                'state_present' => true,
            ]);

            $this->replyFailure(ErrorCode::AUTH_GOOGLE_INVALID_STATE);
            return false;
        }

        $this->session->remove(self::STATE_SESSION_KEY);

        return true;
    }

    /**
     * @return array{
     *     id: string,
     *     email: string,
     *     email_verified: bool,
     *     name: string,
     *     avatar: string|null
     * }
     */
    private function loadGoogleProfile(string $code): array
    {
        $accessToken = $this->googleOAuthService->getAccessToken($code);

        return $this->googleOAuthService->getUserProfile($accessToken);
    }

    /**
     * @param array{
     *     id: string,
     *     email: string,
     *     email_verified: bool,
     *     name: string,
     *     avatar: string|null
     * } $profile
     */
    private function validateGoogleProfile(array $profile): bool
    {
        if ($this->isUsableGoogleProfile($profile)) {
            return true;
        }

        Logger::getLogger('auth')->warning('google_oauth_profile_invalid', [
            'has_id'         => $profile['id']    !== '',
            'has_email'      => $profile['email'] !== '',
            'email_verified' => $profile['email_verified'],
        ]);

        $this->replyFailure(ErrorCode::AUTH_GOOGLE_PROFILE_INVALID);
        return false;
    }

    /**
     * @param array{
     *     id: string,
     *     email: string,
     *     email_verified: bool,
     *     name: string,
     *     avatar: string|null
     * } $profile
     */
    private function loginExistingOAuthAccount(array $profile): bool
    {
        $oauthAccount = $this->oauthAccountModel->findByProviderAndProviderUserId(
            self::PROVIDER,
            $profile['id']
        );

        if ($oauthAccount === null) {
            return false;
        }

        $this->openSessionFromOAuthAccount($oauthAccount);
        return true;
    }

    /**
     * @param array{
     *     id: string,
     *     email: string,
     *     email_verified: bool,
     *     name: string,
     *     avatar: string|null
     * } $profile
     */
    private function resolveUser(array $profile): ?UserEntity
    {
        $user = $this->userModel->findOneByEmail($profile['email']);

        if ($user !== null) {
            return $user;
        }

        Logger::getLogger('auth')->info('google_oauth_local_account_not_found_provisioning_started', [
            'provider' => self::PROVIDER,
            'email'    => $profile['email'],
        ]);

        $user = $this->oauthProvisioning->provisionFromGoogleProfile($profile);

        if ($user === null) {
            Logger::getLogger('auth')->error('google_oauth_user_provisioning_failed', [
                'provider' => self::PROVIDER,
                'email'    => $profile['email'],
            ]);

            return null;
        }

        Logger::getLogger('auth')->info('google_oauth_user_created', [
            'user_id'  => $user->getUserId(),
            'email'    => $user->getEmail(),
            'provider' => self::PROVIDER,
        ]);

        return $user;
    }

    /**
     * @param array{
     *     id: string,
     *     email: string,
     *     email_verified: bool,
     *     name: string,
     *     avatar: string|null
     * } $profile
     */
    private function completeOAuthLogin(UserEntity $user, array $profile): void
    {
        if (!$this->isActiveUser($user)) {
            Logger::getLogger('auth')->warning('google_oauth_local_account_inactive', [
                'user_id' => $user->getUserId(),
                'status'  => $user->getStatus(),
            ]);

            $this->replyFailure(ErrorCode::AUTH_GOOGLE_LOCAL_ACCOUNT_INACTIVE);
            return;
        }

        if ($this->hasExistingProviderLink($user)) {
            Logger::getLogger('auth')->warning('google_oauth_local_user_already_linked', [
                'user_id'  => $user->getUserId(),
                'provider' => self::PROVIDER,
            ]);

            $this->openSessionFromUser($user);
            return;
        }

        $this->createOAuthLink($user, $profile);
        $this->openSessionFromUser($user);
    }

    /**
     * @param array{
     *     id: string,
     *     email: string,
     *     email_verified: bool,
     *     name: string,
     *     avatar: string|null
     * } $profile
     */
    private function isUsableGoogleProfile(array $profile): bool
    {
        return $profile['id']    !== ''
            && $profile['email'] !== ''
            && $profile['email_verified'] === true;
    }

    private function openSessionFromOAuthAccount(OAuthAccountEntity $oauthAccount): void
    {
        $userId = (int) ($oauthAccount->getUserId() ?? 0);

        if ($userId <= 0) {
            Logger::getLogger('auth')->error('google_oauth_linked_account_invalid', [
                'oauth_account_id' => $oauthAccount->getId(),
                'provider'         => $oauthAccount->getProvider(),
                'provider_user_id' => $oauthAccount->getProviderUserId(),
            ]);

            $this->replyFailure(ErrorCode::AUTH_GOOGLE_LINKED_ACCOUNT_INVALID);
            return;
        }

        $user = $this->userModel->findOneById($userId);

        if ($user === null) {
            Logger::getLogger('auth')->error('google_oauth_linked_user_not_found', [
                'user_id'          => $userId,
                'oauth_account_id' => $oauthAccount->getId(),
            ]);

            $this->replyFailure(ErrorCode::AUTH_GOOGLE_LINKED_ACCOUNT_INVALID);
            return;
        }

        if (!$this->isActiveUser($user)) {
            Logger::getLogger('auth')->warning('google_oauth_linked_user_not_active', [
                'user_id' => $user->getUserId(),
                'status'  => $user->getStatus(),
            ]);

            $this->replyFailure(ErrorCode::AUTH_GOOGLE_LOCAL_ACCOUNT_INACTIVE);
            return;
        }

        $this->openAuthenticatedSession($userId);

        Logger::getLogger('auth')->info('google_oauth_login_success', [
            'user_id' => $userId,
            'linked'  => true,
        ]);

        $this->responder->redirect(self::SUCCESS_REDIRECT);
    }

    /**
     * @param array{
     *     id: string,
     *     email: string,
     *     email_verified: bool,
     *     name: string,
     *     avatar: string|null
     * } $profile
     */
    private function createOAuthLink(UserEntity $user, array $profile): void
    {
        $userId = (int) ($user->getUserId() ?? 0);

        $account = (new OAuthAccountEntity())
            ->setUserId($userId)
            ->setProvider(self::PROVIDER)
            ->setProviderUserId($profile['id'])
            ->setEmail($profile['email'])
            ->setEmailVerified($profile['email_verified']);

        $createdId = $this->oauthAccountModel->create($account);

        if ($createdId <= 0) {
            throw new RuntimeException(ErrorCode::AUTH_GOOGLE_LINK_CREATION_FAILED);
        }

        Logger::getLogger('auth')->info('google_oauth_account_linked', [
            'user_id'          => $userId,
            'oauth_account_id' => $createdId,
        ]);
    }

    private function openSessionFromUser(UserEntity $user): void
    {
        $userId = (int) ($user->getUserId() ?? 0);

        $this->openAuthenticatedSession($userId);

        Logger::getLogger('auth')->info('google_oauth_login_success', [
            'user_id' => $userId,
            'linked'  => false,
        ]);

        $this->responder->redirect(self::SUCCESS_REDIRECT);
    }

    private function openAuthenticatedSession(int $userId): void
    {
        $this->session->regenerateAndDeleteOld();

        $this->session->set('user', [
            'id'    => $userId,
            'roles' => ['USER'],
        ]);

        $this->flash->add('success', 'connexion réussie');
    }

    private function isActiveUser(UserEntity $user): bool
    {
        return $user->getStatus() === 'active';
    }

    private function hasExistingProviderLink(UserEntity $user): bool
    {
        $userId = (int) ($user->getUserId() ?? 0);

        if ($userId <= 0) {
            throw new RuntimeException(ErrorCode::AUTH_GOOGLE_USER_INVALID);
        }

        $existingLink = $this->oauthAccountModel->findByProviderAndUserId(
            self::PROVIDER,
            $userId
        );

        return $existingLink !== null;
    }

    private function replyFailure(string $code): void
    {
        $this->flash->add('error', MessageManager::get($code));
        $this->responder->redirect(self::LOGIN_REDIRECT);
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }
}
