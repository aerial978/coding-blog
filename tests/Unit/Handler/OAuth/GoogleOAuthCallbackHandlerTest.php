<?php

declare(strict_types=1);

namespace Tests\Unit\Handler\OAuth;

use App\Core\Contract\FlashInterface;
use App\Core\Contract\SessionInterface;
use App\Core\ErrorCode;
use App\Core\MessageManager;
use App\Handler\OAuth\GoogleOAuthCallbackHandler;
use App\Http\Contract\ResponderInterface;
use App\Model\Contract\OAuthAccountModelInterface;
use App\Model\Contract\UserModelInterface;
use App\Model\Entity\OAuthAccountEntity;
use App\Model\Entity\UserEntity;
use App\Service\OAuth\Contract\GoogleOAuthServiceInterface;
use App\Service\OAuth\Contract\OAuthUserProvisioningServiceInterface;
use League\OAuth2\Client\Token\AccessToken;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class GoogleOAuthCallbackHandlerTest extends TestCase
{
    private GoogleOAuthServiceInterface&MockObject $googleOAuthService;
    private OAuthAccountModelInterface&MockObject $oauthAccountModel;
    private UserModelInterface&MockObject $userModel;
    private OAuthUserProvisioningServiceInterface&MockObject $oauthProvisioning;
    private SessionInterface&MockObject $session;
    private FlashInterface&MockObject $flash;
    private ResponderInterface&MockObject $responder;

    private GoogleOAuthCallbackHandler $handler;

    protected function setUp(): void
    {
        $this->googleOAuthService = $this->createMock(GoogleOAuthServiceInterface::class);
        $this->oauthAccountModel  = $this->createMock(OAuthAccountModelInterface::class);
        $this->userModel          = $this->createMock(UserModelInterface::class);
        $this->oauthProvisioning  = $this->createMock(OAuthUserProvisioningServiceInterface::class);
        $this->session            = $this->createMock(SessionInterface::class);
        $this->flash              = $this->createMock(FlashInterface::class);
        $this->responder          = $this->createMock(ResponderInterface::class);

        $this->handler = new GoogleOAuthCallbackHandler(
            $this->googleOAuthService,
            $this->oauthAccountModel,
            $this->userModel,
            $this->oauthProvisioning,
            $this->session,
            $this->flash,
            $this->responder,
        );
    }

    public function testHandleRedirectsWhenGoogleAccessIsDenied(): void
    {
        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with('error', MessageManager::get(ErrorCode::AUTH_GOOGLE_ACCESS_DENIED));

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/login');

        $this->googleOAuthService
            ->expects($this->never())
            ->method('getAccessToken');

        $this->handler->handle([
            'error' => 'access_denied',
        ]);
    }

    public function testHandleRedirectsWhenCallbackStateIsInvalid(): void
    {
        $this->session
            ->expects($this->once())
            ->method('get')
            ->with('google_oauth_state')
            ->willReturn('expected_state');

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with('error', MessageManager::get(ErrorCode::AUTH_GOOGLE_INVALID_STATE));

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/login');

        $this->googleOAuthService
            ->expects($this->never())
            ->method('getAccessToken');

        $this->handler->handle([
            'code'  => 'valid_code',
            'state' => 'bad_state',
        ]);
    }

    public function testHandleRedirectsWhenGoogleProfileIsInvalid(): void
    {
        $this->mockValidState();

        $this->googleOAuthService
            ->expects($this->once())
            ->method('getAccessToken')
            ->with('valid_code')
            ->willReturn($this->accessToken());

        $this->googleOAuthService
            ->expects($this->once())
            ->method('getUserProfile')
            ->willReturn([
                'id'             => 'google_123',
                'email'          => 'michael@example.com',
                'email_verified' => false,
                'name'           => 'Michael Doe',
                'avatar'         => null,
            ]);

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with('error', MessageManager::get(ErrorCode::AUTH_GOOGLE_PROFILE_INVALID));

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/login');

        $this->handler->handle([
            'code'  => 'valid_code',
            'state' => 'valid_state',
        ]);
    }

    public function testHandleLogsInExistingLinkedOAuthAccount(): void
    {
        $profile = $this->validProfile();

        $oauthAccount = (new OAuthAccountEntity())
            ->setId(1)
            ->setUserId(42)
            ->setProvider('google')
            ->setProviderUserId('google_123')
            ->setEmail('michael@example.com')
            ->setEmailVerified(true);

        $user = $this->activeUser(42, 'michael@example.com');

        $this->mockValidGoogleProfile($profile);

        $this->oauthAccountModel
            ->expects($this->once())
            ->method('findByProviderAndProviderUserId')
            ->with('google', 'google_123')
            ->willReturn($oauthAccount);

        $this->userModel
            ->expects($this->once())
            ->method('findOneById')
            ->with(42)
            ->willReturn($user);

        $this->expectAuthenticatedSession(42);

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog');

        $this->handler->handle([
            'code'  => 'valid_code',
            'state' => 'valid_state',
        ]);
    }

    public function testHandleRefusesExistingLinkedOAuthAccountWhenLocalUserIsInactive(): void
    {
        $profile = $this->validProfile();

        $oauthAccount = (new OAuthAccountEntity())
            ->setId(1)
            ->setUserId(42)
            ->setProvider('google')
            ->setProviderUserId('google_123')
            ->setEmail('michael@example.com')
            ->setEmailVerified(true);

        $user = $this->inactiveUser(42, 'michael@example.com');

        $this->mockValidGoogleProfile($profile);

        $this->oauthAccountModel
            ->expects($this->once())
            ->method('findByProviderAndProviderUserId')
            ->with('google', 'google_123')
            ->willReturn($oauthAccount);

        $this->userModel
            ->expects($this->once())
            ->method('findOneById')
            ->with(42)
            ->willReturn($user);

        $this->session
            ->expects($this->never())
            ->method('regenerateAndDeleteOld');

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with('error', MessageManager::get(ErrorCode::AUTH_GOOGLE_LOCAL_ACCOUNT_INACTIVE));

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/login');

        $this->handler->handle([
            'code'  => 'valid_code',
            'state' => 'valid_state',
        ]);
    }

    public function testHandleCreatesUserWithAutoProvisioningThenCreatesOAuthLinkAndLogsIn(): void
    {
        $profile = $this->validProfile();
        $user    = $this->activeUser(77, 'michael@example.com');

        $this->mockValidGoogleProfile($profile);

        $this->oauthAccountModel
            ->expects($this->once())
            ->method('findByProviderAndProviderUserId')
            ->with('google', 'google_123')
            ->willReturn(null);

        $this->userModel
            ->expects($this->once())
            ->method('findOneByEmail')
            ->with('michael@example.com')
            ->willReturn(null);

        $this->oauthProvisioning
            ->expects($this->once())
            ->method('provisionFromGoogleProfile')
            ->with($profile)
            ->willReturn($user);

        $this->oauthAccountModel
            ->expects($this->once())
            ->method('findByProviderAndUserId')
            ->with('google', 77)
            ->willReturn(null);

        $this->oauthAccountModel
            ->expects($this->once())
            ->method('create')
            ->with($this->callback(
                static function (OAuthAccountEntity $account): bool {
                    return $account->getUserId()         === 77
                        && $account->getProvider()       === 'google'
                        && $account->getProviderUserId() === 'google_123'
                        && $account->getEmail()          === 'michael@example.com'
                        && $account->isEmailVerified()   === true;
                }
            ))
            ->willReturn(10);

        $this->expectAuthenticatedSession(77);

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog');

        $this->handler->handle([
            'code'  => 'valid_code',
            'state' => 'valid_state',
        ]);
    }

    public function testHandleRedirectsWhenAutoProvisioningFails(): void
    {
        $profile = $this->validProfile();

        $this->mockValidGoogleProfile($profile);

        $this->oauthAccountModel
            ->expects($this->once())
            ->method('findByProviderAndProviderUserId')
            ->with('google', 'google_123')
            ->willReturn(null);

        $this->userModel
            ->expects($this->once())
            ->method('findOneByEmail')
            ->with('michael@example.com')
            ->willReturn(null);

        $this->oauthProvisioning
            ->expects($this->once())
            ->method('provisionFromGoogleProfile')
            ->with($profile)
            ->willReturn(null);

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with('error', MessageManager::get(ErrorCode::AUTH_GOOGLE_TECHNICAL_ERROR));

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/login');

        $this->handler->handle([
            'code'  => 'valid_code',
            'state' => 'valid_state',
        ]);
    }

    public function testHandleRefusesInactiveLocalUserFoundByEmail(): void
    {
        $profile = $this->validProfile();
        $user    = $this->inactiveUser(42, 'michael@example.com');

        $this->mockValidGoogleProfile($profile);

        $this->oauthAccountModel
            ->expects($this->once())
            ->method('findByProviderAndProviderUserId')
            ->with('google', 'google_123')
            ->willReturn(null);

        $this->userModel
            ->expects($this->once())
            ->method('findOneByEmail')
            ->with('michael@example.com')
            ->willReturn($user);

        $this->session
            ->expects($this->never())
            ->method('regenerateAndDeleteOld');

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with('error', MessageManager::get(ErrorCode::AUTH_GOOGLE_LOCAL_ACCOUNT_INACTIVE));

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/login');

        $this->handler->handle([
            'code'  => 'valid_code',
            'state' => 'valid_state',
        ]);
    }

    public function testHandleLogsInWhenLocalUserAlreadyHasProviderLink(): void
    {
        $profile = $this->validProfile();
        $user    = $this->activeUser(42, 'michael@example.com');

        $existingLink = (new OAuthAccountEntity())
            ->setId(2)
            ->setUserId(42)
            ->setProvider('google')
            ->setProviderUserId('other_google_id')
            ->setEmail('michael@example.com')
            ->setEmailVerified(true);

        $this->mockValidGoogleProfile($profile);

        $this->oauthAccountModel
            ->expects($this->once())
            ->method('findByProviderAndProviderUserId')
            ->with('google', 'google_123')
            ->willReturn(null);

        $this->userModel
            ->expects($this->once())
            ->method('findOneByEmail')
            ->with('michael@example.com')
            ->willReturn($user);

        $this->oauthAccountModel
            ->expects($this->once())
            ->method('findByProviderAndUserId')
            ->with('google', 42)
            ->willReturn($existingLink);

        $this->oauthAccountModel
            ->expects($this->never())
            ->method('create');

        $this->expectAuthenticatedSession(42);

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog');

        $this->handler->handle([
            'code'  => 'valid_code',
            'state' => 'valid_state',
        ]);
    }

    private function mockValidState(): void
    {
        $this->session
            ->expects($this->once())
            ->method('get')
            ->with('google_oauth_state')
            ->willReturn('valid_state');

        $this->session
            ->expects($this->once())
            ->method('remove')
            ->with('google_oauth_state');
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
    private function mockValidGoogleProfile(array $profile): void
    {
        $this->mockValidState();

        $this->googleOAuthService
            ->expects($this->once())
            ->method('getAccessToken')
            ->with('valid_code')
            ->willReturn($this->accessToken());

        $this->googleOAuthService
            ->expects($this->once())
            ->method('getUserProfile')
            ->willReturn($profile);
    }

    private function expectAuthenticatedSession(int $userId): void
    {
        $this->session
            ->expects($this->once())
            ->method('regenerateAndDeleteOld');

        $this->session
            ->expects($this->once())
            ->method('set')
            ->with('user', [
                'id'    => $userId,
                'roles' => ['USER'],
            ]);

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with('success', 'connexion réussie');
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
    private function validProfile(): array
    {
        return [
            'id'             => 'google_123',
            'email'          => 'michael@example.com',
            'email_verified' => true,
            'name'           => 'Michael doe',
            'avatar'         => null,
        ];
    }

    private function activeUser(int $userId, string $email): UserEntity
    {
        return (new UserEntity())
            ->setUserId($userId)
            ->setUsername('michael')
            ->setSlug('michael')
            ->setEmail($email)
            ->setPassword(password_hash('password', PASSWORD_DEFAULT))
            ->setStatus('active')
            ->setEmail2faEnabled(false);
    }

    private function inactiveUser(int $userId, string $email): UserEntity
    {
        return (new UserEntity())
            ->setUserId($userId)
            ->setUsername('michael')
            ->setSlug('michael')
            ->setEmail($email)
            ->setPassword(password_hash('password', PASSWORD_DEFAULT))
            ->setStatus('inactive')
            ->setEmail2faEnabled(false);
    }

    private function accessToken(): AccessToken
    {
        return new AccessToken([
            'access_token' => 'fake_access_token',
        ]);
    }

    public function testHandleRedirectsWithTechnicalErrorWhenExceptionOccurs(): void
    {
        $this->session
            ->expects($this->once())
            ->method('get')
            ->with('google_oauth_state')
            ->willReturn('valid_state');

        $this->session
            ->expects($this->once())
            ->method('remove')
            ->with('google_oauth_state');

        $this->googleOAuthService
            ->expects($this->once())
            ->method('getAccessToken')
            ->with('valid_code')
            ->willThrowException(new \RuntimeException('Google OAuth failure'));

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with(
                'error',
                MessageManager::get(ErrorCode::AUTH_GOOGLE_TECHNICAL_ERROR)
            );

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/login');

        $this->handler->handle([
            'code'  => 'valid_code',
            'state' => 'valid_state',
        ]);
    }

    public function testHandleRedirectsWhenCodeIsMissing(): void
    {
        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with(
                'error',
                MessageManager::get(ErrorCode::AUTH_GOOGLE_INVALID_RESPONSE)
            );

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/login');

        $this->handler->handle([
            'state' => 'valid_state',
        ]);
    }

    public function testHandleRedirectsWhenStateIsMissing(): void
    {
        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with(
                'error',
                MessageManager::get(ErrorCode::AUTH_GOOGLE_INVALID_RESPONSE)
            );

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/login');

        $this->handler->handle([
            'code' => 'valid_code',
        ]);
    }

    public function testHandleRedirectsWhenLinkedOAuthAccountHasInvalidUserId(): void
    {
        $profile = $this->validProfile();

        $oauthAccount = (new OAuthAccountEntity())
            ->setId(1)
            ->setUserId(0)
            ->setProvider('google')
            ->setProviderUserId('google_123')
            ->setEmail('michael@example.com')
            ->setEmailVerified(true);

        $this->mockValidGoogleProfile($profile);

        $this->oauthAccountModel
            ->expects($this->once())
            ->method('findByProviderAndProviderUserId')
            ->with('google', 'google_123')
            ->willReturn($oauthAccount);

        $this->userModel
            ->expects($this->never())
            ->method('findOneById');

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with(
                'error',
                MessageManager::get(ErrorCode::AUTH_GOOGLE_LINKED_ACCOUNT_INVALID)
            );

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/login');

        $this->handler->handle([
            'code'  => 'valid_code',
            'state' => 'valid_state',
        ]);
    }

    public function testHandleRedirectsWhenLinkedLocalUserIsNotFound(): void
    {
        $profile = $this->validProfile();

        $oauthAccount = (new OAuthAccountEntity())
            ->setId(1)
            ->setUserId(42)
            ->setProvider('google')
            ->setProviderUserId('google_123')
            ->setEmail('michael@example.com')
            ->setEmailVerified(true);

        $this->mockValidGoogleProfile($profile);

        $this->oauthAccountModel
            ->expects($this->once())
            ->method('findByProviderAndProviderUserId')
            ->with('google', 'google_123')
            ->willReturn($oauthAccount);

        $this->userModel
            ->expects($this->once())
            ->method('findOneById')
            ->with(42)
            ->willReturn(null);

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with(
                'error',
                MessageManager::get(ErrorCode::AUTH_GOOGLE_LINKED_ACCOUNT_INVALID)
            );

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/login');

        $this->handler->handle([
            'code'  => 'valid_code',
            'state' => 'valid_state',
        ]);
    }

    public function testHandleRedirectsWithTechnicalErrorWhenUserIdIsInvalidBeforeCreatingOAuthLink(): void
    {
        $profile = $this->validProfile();

        $user = (new UserEntity())
            ->setUsername('michael')
            ->setSlug('michael')
            ->setEmail('michael@example.com')
            ->setPassword(password_hash('password', PASSWORD_DEFAULT))
            ->setStatus('active')
            ->setEmail2faEnabled(false);

        $this->mockValidGoogleProfile($profile);

        $this->oauthAccountModel
            ->expects($this->once())
            ->method('findByProviderAndProviderUserId')
            ->with('google', 'google_123')
            ->willReturn(null);

        $this->userModel
            ->expects($this->once())
            ->method('findOneByEmail')
            ->with('michael@example.com')
            ->willReturn($user);

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with(
                'error',
                MessageManager::get(ErrorCode::AUTH_GOOGLE_TECHNICAL_ERROR)
            );

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/login');

        $this->handler->handle([
            'code'  => 'valid_code',
            'state' => 'valid_state',
        ]);
    }

    public function testHandleRedirectsWithTechnicalErrorWhenOAuthLinkCreationFails(): void
    {
        $profile = $this->validProfile();
        $user    = $this->activeUser(42, 'michael@example.com');

        $this->mockValidGoogleProfile($profile);

        $this->oauthAccountModel
        ->expects($this->once())
        ->method('findByProviderAndProviderUserId')
        ->with('google', 'google_123')
        ->willReturn(null);

        $this->userModel
        ->expects($this->once())
        ->method('findOneByEmail')
        ->with('michael@example.com')
        ->willReturn($user);

        $this->oauthAccountModel
        ->expects($this->once())
        ->method('findByProviderAndUserId')
        ->with('google', 42)
        ->willReturn(null);

        $this->oauthAccountModel
        ->expects($this->once())
        ->method('create')
        ->willReturn(0);

        $this->flash
        ->expects($this->once())
        ->method('add')
        ->with(
            'error',
            MessageManager::get(ErrorCode::AUTH_GOOGLE_TECHNICAL_ERROR)
        );

        $this->responder
        ->expects($this->once())
        ->method('redirect')
        ->with('/coding-blog/login');

        $this->handler->handle([
        'code'  => 'valid_code',
        'state' => 'valid_state',
        ]);
    }
}
