<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Container;

use App\Core\Container\Provider\UserServiceProvider;
use App\Core\Contract\SessionInterface;
use App\Core\Contract\SqlHelperInterface;
use App\Core\Mail\MailerInterface;
use App\Model\Contract\UserModelInterface;
use App\Model\Contract\UserTokenModelInterface;
use App\Model\EmailEventModel;
use App\Model\RegistrationEventModel;
use App\Model\UserModel;
use App\Model\UserTokenModel;
use App\Security\Contract\TokenGeneratorInterface;
use App\Security\DisposableChecker;
use App\Security\EmailQuotaService;
use App\Security\PasswordBlacklist;
use App\Security\RegistrationThrottleService;
use App\Service\Security\AccountConfirmationService;
use App\Service\Security\ConfirmationResendService;
use App\Service\Security\Contract\ForgotPasswordServiceInterface;
use App\Service\Security\Contract\LoginServiceInterface;
use App\Service\Security\Contract\ResetPasswordServiceInterface;
use App\Service\Security\Contract\SecurityServiceInterface;
use App\Service\Security\ForgotPasswordService;
use App\Service\Security\LoginService;
use App\Service\Security\RegistrationService;
use App\Service\Security\ResetPasswordService;
use App\Service\Security\SecurityService;
use App\Validation\Contract\FormValidatorInterface;
use Cocur\Slugify\Slugify;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class UserServiceProviderTest extends TestCase
{
    /**
     * @param array<string, mixed> $services
     */
    private function makeContainer(array $services): ContainerInterface
    {
        return new class ($services) implements ContainerInterface {
            /**
             * @param array<string, mixed> $services
             */
            public function __construct(private array $services)
            {
            }

            public function get(string $id): mixed
            {
                if (!$this->has($id)) {
                    throw new \RuntimeException('Service not found: ' . $id);
                }

                return $this->services[$id];
            }

            public function has(string $id): bool
            {
                return array_key_exists($id, $this->services);
            }
        };
    }

    /**
     * Services transverses requis par le provider.
     *
     * @return array<string, mixed>
     */
    private function baseServices(): array
    {
        $sqlHelper = $this->createMock(\App\Core\SqlHelper::class);

        return [
            \App\Core\SqlHelper::class     => $sqlHelper,
            SqlHelperInterface::class      => $sqlHelper,
            FormValidatorInterface::class  => $this->createMock(FormValidatorInterface::class),
            MailerInterface::class         => $this->createMock(MailerInterface::class),
            TokenGeneratorInterface::class => $this->createMock(TokenGeneratorInterface::class),
            SessionInterface::class        => $this->createMock(SessionInterface::class),
            Slugify::class                 => new Slugify(),
        ];
    }

    public function testDefinitionsContainExpectedKeys(): void
    {
        $definitions = UserServiceProvider::getDefinitions();

        $this->assertArrayHasKey(UserModelInterface::class, $definitions);
        $this->assertArrayHasKey(UserTokenModelInterface::class, $definitions);
        $this->assertArrayHasKey(RegistrationEventModel::class, $definitions);
        $this->assertArrayHasKey(EmailEventModel::class, $definitions);

        $this->assertArrayHasKey(RegistrationThrottleService::class, $definitions);
        $this->assertArrayHasKey(EmailQuotaService::class, $definitions);
        $this->assertArrayHasKey(PasswordBlacklist::class, $definitions);
        $this->assertArrayHasKey(DisposableChecker::class, $definitions);

        $this->assertArrayHasKey(RegistrationService::class, $definitions);
        $this->assertArrayHasKey(AccountConfirmationService::class, $definitions);
        $this->assertArrayHasKey(ConfirmationResendService::class, $definitions);
        $this->assertArrayHasKey(LoginService::class, $definitions);
        $this->assertArrayHasKey(ForgotPasswordService::class, $definitions);
        $this->assertArrayHasKey(ResetPasswordService::class, $definitions);
        $this->assertArrayHasKey(SecurityService::class, $definitions);

        $this->assertArrayHasKey(SecurityServiceInterface::class, $definitions);
        $this->assertArrayHasKey(LoginServiceInterface::class, $definitions);
        $this->assertArrayHasKey(ForgotPasswordServiceInterface::class, $definitions);
        $this->assertArrayHasKey(ResetPasswordServiceInterface::class, $definitions);
    }

    public function testModelDefinitionsAreBuildable(): void
    {
        $definitions = UserServiceProvider::getDefinitions();
        $container   = $this->makeContainer($this->baseServices());

        $userModel              = $definitions[UserModelInterface::class]($container);
        $userTokenModel         = $definitions[UserTokenModelInterface::class]($container);
        $registrationEventModel = $definitions[RegistrationEventModel::class]($container);
        $emailEventModel        = $definitions[EmailEventModel::class]($container);

        $this->assertInstanceOf(UserModel::class, $userModel);
        $this->assertInstanceOf(UserModelInterface::class, $userModel);

        $this->assertInstanceOf(UserTokenModel::class, $userTokenModel);
        $this->assertInstanceOf(UserTokenModelInterface::class, $userTokenModel);

        $this->assertInstanceOf(RegistrationEventModel::class, $registrationEventModel);
        $this->assertInstanceOf(EmailEventModel::class, $emailEventModel);
    }

    public function testThrottleAndStaticResourceDefinitionsAreBuildable(): void
    {
        $definitions = UserServiceProvider::getDefinitions();

        $sql = $this->createMock(\App\Core\SqlHelper::class);

        $registrationEventModel = new RegistrationEventModel($sql);
        $emailEventModel        = new EmailEventModel($sql);

        $container = $this->makeContainer([
            RegistrationEventModel::class => $registrationEventModel,
            EmailEventModel::class        => $emailEventModel,
        ]);

        $registrationThrottle = $definitions[RegistrationThrottleService::class]($container);
        $emailQuota           = $definitions[EmailQuotaService::class]($container);
        $passwordBlacklist    = $definitions[PasswordBlacklist::class]($container);
        $disposableChecker    = $definitions[DisposableChecker::class]($container);

        $this->assertInstanceOf(RegistrationThrottleService::class, $registrationThrottle);
        $this->assertInstanceOf(EmailQuotaService::class, $emailQuota);
        $this->assertInstanceOf(PasswordBlacklist::class, $passwordBlacklist);
        $this->assertInstanceOf(DisposableChecker::class, $disposableChecker);
    }

    public function testDomainServiceDefinitionsAreBuildable(): void
    {
        $definitions = UserServiceProvider::getDefinitions();

        $services = $this->baseServices();
        $sql      = $services[\App\Core\SqlHelper::class];
        \assert($sql instanceof \App\Core\SqlHelper);

        $userModel              = new UserModel($sql);
        $userTokenModel         = new UserTokenModel($sql);
        $registrationEventModel = new RegistrationEventModel($sql);
        $emailEventModel        = new EmailEventModel($sql);

        $registrationThrottle = new RegistrationThrottleService($registrationEventModel);
        $emailQuota           = new EmailQuotaService($emailEventModel);

        $staticContainer   = $this->makeContainer([]);
        $passwordBlacklist = $definitions[PasswordBlacklist::class]($staticContainer);
        $disposableChecker = $definitions[DisposableChecker::class]($staticContainer);

        $container = $this->makeContainer($services + [
            UserModelInterface::class          => $userModel,
            UserTokenModelInterface::class     => $userTokenModel,
            RegistrationThrottleService::class => $registrationThrottle,
            EmailQuotaService::class           => $emailQuota,
            PasswordBlacklist::class           => $passwordBlacklist,
            DisposableChecker::class           => $disposableChecker,
        ]);

        $registrationService        = $definitions[RegistrationService::class]($container);
        $accountConfirmationService = $definitions[AccountConfirmationService::class]($container);
        $confirmationResendService  = $definitions[ConfirmationResendService::class]($container);
        $loginService               = $definitions[LoginService::class]($container);
        $forgotPasswordService      = $definitions[ForgotPasswordService::class]($container);
        $resetPasswordService       = $definitions[ResetPasswordService::class]($container);

        $this->assertInstanceOf(RegistrationService::class, $registrationService);
        $this->assertInstanceOf(AccountConfirmationService::class, $accountConfirmationService);
        $this->assertInstanceOf(ConfirmationResendService::class, $confirmationResendService);
        $this->assertInstanceOf(LoginService::class, $loginService);
        $this->assertInstanceOf(ForgotPasswordService::class, $forgotPasswordService);
        $this->assertInstanceOf(ResetPasswordService::class, $resetPasswordService);
    }

    public function testSecurityServiceAndInterfaceBindingsResolveCorrectly(): void
    {
        $definitions = UserServiceProvider::getDefinitions();

        $services = $this->baseServices();
        $sql      = $services[\App\Core\SqlHelper::class];
        \assert($sql instanceof \App\Core\SqlHelper);

        $userModel              = new UserModel($sql);
        $userTokenModel         = new UserTokenModel($sql);
        $registrationEventModel = new RegistrationEventModel($sql);
        $emailEventModel        = new EmailEventModel($sql);

        $registrationThrottle = new RegistrationThrottleService($registrationEventModel);
        $emailQuota           = new EmailQuotaService($emailEventModel);

        $staticContainer   = $this->makeContainer([]);
        $passwordBlacklist = $definitions[PasswordBlacklist::class]($staticContainer);
        $disposableChecker = $definitions[DisposableChecker::class]($staticContainer);

        $domainContainer = $this->makeContainer($services + [
            UserModelInterface::class          => $userModel,
            UserTokenModelInterface::class     => $userTokenModel,
            RegistrationThrottleService::class => $registrationThrottle,
            EmailQuotaService::class           => $emailQuota,
            PasswordBlacklist::class           => $passwordBlacklist,
            DisposableChecker::class           => $disposableChecker,
        ]);

        $registration        = $definitions[RegistrationService::class]($domainContainer);
        $accountConfirmation = $definitions[AccountConfirmationService::class]($domainContainer);
        $confirmationResend  = $definitions[ConfirmationResendService::class]($domainContainer);
        $login               = $definitions[LoginService::class]($domainContainer);
        $forgotPassword      = $definitions[ForgotPasswordService::class]($domainContainer);
        $resetPassword       = $definitions[ResetPasswordService::class]($domainContainer);

        $securityContainer = $this->makeContainer([
            RegistrationService::class        => $registration,
            AccountConfirmationService::class => $accountConfirmation,
            ConfirmationResendService::class  => $confirmationResend,
            LoginService::class               => $login,
            ForgotPasswordService::class      => $forgotPassword,
            ResetPasswordService::class       => $resetPassword,
        ]);

        $securityService = $definitions[SecurityService::class]($securityContainer);

        $this->assertInstanceOf(SecurityService::class, $securityService);

        $aliasContainer = $this->makeContainer([
            SecurityService::class       => $securityService,
            LoginService::class          => $login,
            ForgotPasswordService::class => $forgotPassword,
            ResetPasswordService::class  => $resetPassword,
        ]);

        $this->assertSame(
            $securityService,
            $definitions[SecurityServiceInterface::class]($aliasContainer)
        );

        $this->assertSame(
            $login,
            $definitions[LoginServiceInterface::class]($aliasContainer)
        );

        $this->assertSame(
            $forgotPassword,
            $definitions[ForgotPasswordServiceInterface::class]($aliasContainer)
        );

        $this->assertSame(
            $resetPassword,
            $definitions[ResetPasswordServiceInterface::class]($aliasContainer)
        );
    }
}
