<?php

declare(strict_types=1);

namespace App\Core\Container\Provider;

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
use App\Service\Security\Contract\LogoutServiceInterface;
use App\Service\Security\Contract\RememberMeServiceInterface;
use App\Service\Security\Contract\ResetPasswordServiceInterface;
use App\Service\Security\Contract\SecurityServiceInterface;
use App\Service\Security\ForgotPasswordService;
use App\Service\Security\LoginService;
use App\Service\Security\LogoutService;
use App\Service\Security\RegistrationService;
use App\Service\Security\ResetPasswordService;
use App\Service\Security\SecurityService;
use App\Validation\Contract\FormValidatorInterface;
use Cocur\Slugify\Slugify;
use Psr\Container\ContainerInterface;
use App\Model\Contract\Email2faChallengeModelInterface;
use App\Model\Email2faChallengeModel;
use App\Security\Contract\Email2faPendingSessionInterface;
use App\Service\Security\Contract\Email2faServiceInterface;

/**
 * Services liés aux utilisateurs et à la sécurité (DAL + services métier).
 *
 * Cette version s’appuie sur SystemServiceProvider pour les services transverses
 * (FormValidator, Slugify, MailerInterface, TokenGeneratorInterface, SqlHelper, …)
 * et ajoute le binding SecurityServiceInterface → SecurityService.
 */
final class UserServiceProvider
{
    /**
     * @phpstan-return array<class-string, \Closure(ContainerInterface): object>
     */
    public static function getDefinitions(): array
    {
        return array_merge(
            self::getModelDefinitions(),
            self::getSecurityDefinitions(),
            self::getInterfaceBindings(),
        );
    }

    /**
     * @phpstan-return array<class-string, \Closure(ContainerInterface): object>
     */
    private static function getModelDefinitions(): array
    {
        return [
            UserModelInterface::class => static function (ContainerInterface $container): UserModelInterface {
                /** @var SqlHelperInterface $sql */
                $sql = $container->get(SqlHelperInterface::class);
                return new UserModel($sql);
            },

            UserTokenModelInterface::class => static function (ContainerInterface $container): UserTokenModelInterface {
                /** @var SqlHelperInterface $sql */
                $sql = $container->get(SqlHelperInterface::class);
                return new UserTokenModel($sql);
            },

            Email2faChallengeModelInterface::class => static function (ContainerInterface $container): Email2faChallengeModelInterface {
                /** @var SqlHelperInterface $sql */
                $sql = $container->get(SqlHelperInterface::class);
                return new Email2faChallengeModel($sql);
            },

            RegistrationEventModel::class => static function (ContainerInterface $container): RegistrationEventModel {
                /** @var SqlHelperInterface $sql */
                $sql = $container->get(SqlHelperInterface::class);
                return new RegistrationEventModel($sql);
            },

            EmailEventModel::class => static function (ContainerInterface $container): EmailEventModel {
                /** @var SqlHelperInterface $sql */
                $sql = $container->get(SqlHelperInterface::class);
                return new EmailEventModel($sql);
            },
        ];
    }

    /**
     * @phpstan-return array<class-string, \Closure(ContainerInterface): object>
     */
    private static function getSecurityDefinitions(): array
    {
        return array_merge(
            self::getThrottleAndQuotaDefinitions(),
            self::getStaticResourceDefinitions(),
            self::getDomainServiceDefinitions(),
            self::getSessionSecurityDefinitions(),
        );
    }

    /**
     * Quotas / throttling.
     *
     * @phpstan-return array<class-string, \Closure(ContainerInterface): object>
     */
    private static function getThrottleAndQuotaDefinitions(): array
    {
        return [
            RegistrationThrottleService::class => static function (ContainerInterface $container): RegistrationThrottleService {
                /** @var RegistrationEventModel $model */
                $model = $container->get(RegistrationEventModel::class);
                return new RegistrationThrottleService($model);
            },
            EmailQuotaService::class => static function (ContainerInterface $container): EmailQuotaService {
                /** @var EmailEventModel $model */
                $model = $container->get(EmailEventModel::class);
                return new EmailQuotaService($model);
            },
        ];
    }

    /**
     * Listes statiques (mots de passe interdits, domaines jetables).
     *
     * @phpstan-return array<class-string, \Closure(ContainerInterface): object>
     */
    private static function getStaticResourceDefinitions(): array
    {
        return [
            PasswordBlacklist::class => static function (): PasswordBlacklist {
                $path = __DIR__ . '/../../../Config/password_blacklist.php';
                $raw  = require $path;

                /** @var array<int, string> $list */
                $list = is_array($raw) ? array_values(array_filter($raw, 'is_string')) : [];

                return new PasswordBlacklist($list);
            },
            DisposableChecker::class => static function (): DisposableChecker {
                $raw = require __DIR__ . '/../../../Config/disposable_domains.php';

                /** @var array<int, string> $domains */
                $domains = is_array($raw) ? array_values(array_filter($raw, 'is_string')) : [];

                return new DisposableChecker($domains);
            },
        ];
    }

    /**
     * Services métier (registration / confirm / resend + façade).
     *
     * @phpstan-return array<class-string, \Closure(ContainerInterface): object>
     */
    private static function getDomainServiceDefinitions(): array
    {
        return array_merge(
            self::getRegistrationFlowDefinitions(),
            self::getAuthenticationFlowDefinitions(),
            self::getRecoveryFlowDefinitions(),
            self::getSecurityFacadeDefinitions(),
        );
    }

    /**
     * @phpstan-return array<class-string, \Closure(ContainerInterface): object>
     */
    private static function getSessionSecurityDefinitions(): array
    {
        return [
            LogoutService::class => static function (ContainerInterface $container): LogoutService {
                /** @var SessionInterface $session */
                $session = $container->get(SessionInterface::class);

                /** @var RememberMeServiceInterface $rememberMeService */
                $rememberMeService = $container->get(RememberMeServiceInterface::class);

                return new LogoutService($session, $rememberMeService);
            },
        ];
    }

    /**
     * @phpstan-return array<class-string, \Closure(ContainerInterface): object>
     */
    private static function getRegistrationFlowDefinitions(): array
    {
        return array_merge(
            self::getRegistrationServiceDefinition(),
            self::getAccountConfirmationServiceDefinition(),
            self::getConfirmationResendServiceDefinition(),
        );
    }

    /**
     * @phpstan-return array<class-string, \Closure(ContainerInterface): object>
     */
    private static function getRegistrationServiceDefinition(): array
    {
        return [
            RegistrationService::class => static function (ContainerInterface $container): RegistrationService {
                /** @var FormValidatorInterface $validator */
                $validator = $container->get(FormValidatorInterface::class);
                /** @var UserModelInterface $userModel */
                $userModel = $container->get(UserModelInterface::class);
                /** @var UserTokenModelInterface $userTokenModel */
                $userTokenModel = $container->get(UserTokenModelInterface::class);
                /** @var SqlHelperInterface $sql */
                $sql = $container->get(SqlHelperInterface::class);
                /** @var Slugify $slugify */
                $slugify = $container->get(Slugify::class);
                /** @var MailerInterface $mailer */
                $mailer = $container->get(MailerInterface::class);
                /** @var TokenGeneratorInterface $tokenGen */
                $tokenGen = $container->get(TokenGeneratorInterface::class);
                /** @var RegistrationThrottleService $thr */
                $thr = $container->get(RegistrationThrottleService::class);
                /** @var PasswordBlacklist $blacklist */
                $blacklist = $container->get(PasswordBlacklist::class);
                /** @var DisposableChecker $disp */
                $disp = $container->get(DisposableChecker::class);

                return new RegistrationService(
                    $validator,
                    $userModel,
                    $userTokenModel,
                    $slugify,
                    $mailer,
                    $tokenGen,
                    $sql,
                    $thr,
                    $blacklist,
                    $disp,
                );
            },
        ];
    }

    /**
     * @phpstan-return array<class-string, \Closure(ContainerInterface): object>
     */
    private static function getAccountConfirmationServiceDefinition(): array
    {
        return [
            AccountConfirmationService::class => static function (ContainerInterface $container): AccountConfirmationService {
                /** @var UserTokenModelInterface $userTokenModel */
                $userTokenModel = $container->get(UserTokenModelInterface::class);
                /** @var TokenGeneratorInterface $tokenGen */
                $tokenGen = $container->get(TokenGeneratorInterface::class);

                return new AccountConfirmationService($userTokenModel, $tokenGen);
            },
        ];
    }

    /**
     * @phpstan-return array<class-string, \Closure(ContainerInterface): object>
     */
    private static function getConfirmationResendServiceDefinition(): array
    {
        return [
            ConfirmationResendService::class => static function (ContainerInterface $container): ConfirmationResendService {
                /** @var FormValidatorInterface $validator */
                $validator = $container->get(FormValidatorInterface::class);
                /** @var UserModelInterface $userModel */
                $userModel = $container->get(UserModelInterface::class);
                /** @var UserTokenModelInterface $userTokenModel */
                $userTokenModel = $container->get(UserTokenModelInterface::class);
                /** @var TokenGeneratorInterface $tokenGen */
                $tokenGen = $container->get(TokenGeneratorInterface::class);
                /** @var MailerInterface $mailer */
                $mailer = $container->get(MailerInterface::class);
                /** @var EmailQuotaService $quota */
                $quota = $container->get(EmailQuotaService::class);

                return new ConfirmationResendService(
                    $validator,
                    $userModel,
                    $userTokenModel,
                    $tokenGen,
                    $mailer,
                    $quota,
                );
            },
        ];
    }

    /**
     * @phpstan-return array<class-string, \Closure(ContainerInterface): object>
     */
    private static function getAuthenticationFlowDefinitions(): array
    {
        return [
            LoginService::class => static function (ContainerInterface $container): LoginService {
                /** @var FormValidatorInterface $validator */
                $validator = $container->get(FormValidatorInterface::class);

                /** @var UserModelInterface $userModel */
                $userModel = $container->get(UserModelInterface::class);

                /** @var SessionInterface $session */
                $session = $container->get(SessionInterface::class);

                /** @var RememberMeServiceInterface $rememberMe */
                $rememberMe = $container->get(RememberMeServiceInterface::class);

                /** @var Email2faServiceInterface $email2faService */
                $email2faService = $container->get(Email2faServiceInterface::class);

                /** @var Email2faPendingSessionInterface $email2faPendingSession */
                $email2faPendingSession = $container->get(Email2faPendingSessionInterface::class);

                return new LoginService(
                    $validator,
                    $userModel,
                    $session,
                    $rememberMe,
                    $email2faService,
                    $email2faPendingSession,
                );
            },
        ];
    }

    /**
     * @phpstan-return array<class-string, \Closure(ContainerInterface): object>
     */
    private static function getRecoveryFlowDefinitions(): array
    {
        return [
            ForgotPasswordService::class => static function (ContainerInterface $container): ForgotPasswordService {
                /** @var FormValidatorInterface $validator */
                $validator = $container->get(FormValidatorInterface::class);
                /** @var UserModelInterface $userModel */
                $userModel = $container->get(UserModelInterface::class);
                /** @var UserTokenModelInterface $userTokenModel */
                $userTokenModel = $container->get(UserTokenModelInterface::class);
                /** @var TokenGeneratorInterface $tokenGen */
                $tokenGen = $container->get(TokenGeneratorInterface::class);
                /** @var MailerInterface $mailer */
                $mailer = $container->get(MailerInterface::class);
                /** @var EmailQuotaService $quota */
                $quota = $container->get(EmailQuotaService::class);

                return new ForgotPasswordService(
                    $validator,
                    $userModel,
                    $userTokenModel,
                    $tokenGen,
                    $mailer,
                    $quota,
                );
            },

            ResetPasswordService::class => static function (ContainerInterface $container): ResetPasswordService {
                /** @var FormValidatorInterface $validator */
                $validator = $container->get(FormValidatorInterface::class);
                /** @var UserTokenModelInterface $userTokenModel */
                $userTokenModel = $container->get(UserTokenModelInterface::class);
                /** @var TokenGeneratorInterface $tokenGen */
                $tokenGen = $container->get(TokenGeneratorInterface::class);

                return new ResetPasswordService(
                    $validator,
                    $userTokenModel,
                    $tokenGen,
                );
            },
        ];
    }

    /**
     * @phpstan-return array<class-string, \Closure(ContainerInterface): object>
     */
    private static function getSecurityFacadeDefinitions(): array
    {
        return [
            SecurityService::class => static function (ContainerInterface $container): SecurityService {
                /** @var RegistrationService $registration */
                $registration = $container->get(RegistrationService::class);
                /** @var AccountConfirmationService $accountConfirmation */
                $accountConfirmation = $container->get(AccountConfirmationService::class);
                /** @var ConfirmationResendService $confirmationResend */
                $confirmationResend = $container->get(ConfirmationResendService::class);
                /** @var LoginService $login */
                $login = $container->get(LoginService::class);
                /** @var LogoutService $logout */
                $logout = $container->get(LogoutService::class);
                /** @var ForgotPasswordService $forgotPassword */
                $forgotPassword = $container->get(ForgotPasswordService::class);
                /** @var ResetPasswordService $resetPassword */
                $resetPassword = $container->get(ResetPasswordService::class);

                return new SecurityService(
                    $registration,
                    $accountConfirmation,
                    $confirmationResend,
                    $login,
                    $logout,
                    $forgotPassword,
                    $resetPassword,
                );
            },
        ];
    }

    /**
     * @phpstan-return array<class-string, \Closure(ContainerInterface): object>
     */
    private static function getInterfaceBindings(): array
    {
        return [
            SecurityServiceInterface::class => static function (ContainerInterface $container): SecurityServiceInterface {
                /** @var SecurityService $service */
                $service = $container->get(SecurityService::class);
                return $service;
            },

            LoginServiceInterface::class => static function (ContainerInterface $container): LoginServiceInterface {
                /** @var LoginService $service */
                $service = $container->get(LoginService::class);
                return $service;
            },

            ForgotPasswordServiceInterface::class => static function (ContainerInterface $container): ForgotPasswordServiceInterface {
                /** @var ForgotPasswordService $service */
                $service = $container->get(ForgotPasswordService::class);
                return $service;
            },

            ResetPasswordServiceInterface::class => static function (ContainerInterface $container): ResetPasswordServiceInterface {
                /** @var ResetPasswordService $service */
                $service = $container->get(ResetPasswordService::class);
                return $service;
            },

            LogoutServiceInterface::class => static function (ContainerInterface $container): LogoutServiceInterface {
                /** @var LogoutService $service */
                $service = $container->get(LogoutService::class);
                return $service;
            },
        ];
    }
}
