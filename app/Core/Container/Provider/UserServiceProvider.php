<?php

declare(strict_types=1);

namespace App\Core\Container\Provider;

use App\Core\Mail\MailerInterface;
use App\Core\SqlHelper;
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
use App\Service\Security\Contract\SecurityServiceInterface;
use App\Service\Security\RegistrationService;
use App\Service\Security\SecurityService;
use App\Service\Security\Contract\LoginServiceInterface;
use App\Service\Security\LoginService;
use App\Core\Contract\SessionInterface;
use Cocur\Slugify\Slugify;
use Psr\Container\ContainerInterface;
use App\Core\Contract\SqlHelperInterface;
use App\Model\Contract\UserModelInterface;
use App\Model\Contract\UserTokenModelInterface;
use App\Validation\Contract\FormValidatorInterface;

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

    /** @phpstan-return array<class-string, \Closure(ContainerInterface): object> */
    private static function getSecurityDefinitions(): array
    {
        return array_merge(
            self::getThrottleAndQuotaDefinitions(),
            self::getStaticResourceDefinitions(),
            self::getDomainServiceDefinitions(),
        );
    }

    /** Quotas / throttling.
     *  @phpstan-return array<class-string, \Closure(ContainerInterface): object>
     */
    private static function getThrottleAndQuotaDefinitions(): array
    {
        return [
            RegistrationThrottleService::class => static function (ContainerInterface $c): RegistrationThrottleService {
                /** @var RegistrationEventModel $model */
                $model = $c->get(RegistrationEventModel::class);
                return new RegistrationThrottleService($model);
            },
            EmailQuotaService::class => static function (ContainerInterface $c): EmailQuotaService {
                /** @var EmailEventModel $model */
                $model = $c->get(EmailEventModel::class);
                return new EmailQuotaService($model);
            },
        ];
    }

    /** Listes statiques (mots de passe interdits, domaines jetables).
     *  @phpstan-return array<class-string, \Closure(ContainerInterface): object>
     */
    private static function getStaticResourceDefinitions(): array
    {
        return [
            PasswordBlacklist::class => static function (ContainerInterface $c): PasswordBlacklist {
                $path = __DIR__ . '/../../../Config/password_blacklist.php';
                $raw  = require $path;
                /** @var array<int,string> $list */
                $list = is_array($raw) ? array_values(array_filter($raw, 'is_string')) : [];
                return new PasswordBlacklist($list);
            },
            DisposableChecker::class => static function (ContainerInterface $c): DisposableChecker {
                $raw = require __DIR__ . '/../../../Config/disposable_domains.php';
                /** @var array<int,string> $domains */
                $domains = is_array($raw) ? array_values(array_filter($raw, 'is_string')) : [];
                return new DisposableChecker($domains);
            },
        ];
    }

    /** Services métier (registration / confirm / resend + façade).
     *  @phpstan-return array<class-string, \Closure(ContainerInterface): object>
     */
    private static function getDomainServiceDefinitions(): array
    {
        return [
            RegistrationService::class => static function (ContainerInterface $c): RegistrationService {
                /** @var FormValidatorInterface $validator */ $validator = $c->get(FormValidatorInterface::class);
                /** @var UserModelInterface $userModel */ $userModel = $c->get(UserModelInterface::class);
                /** @var UserTokenModelInterface $userTokenModel */ $userTokenModel = $c->get(UserTokenModelInterface::class);
                /** @var SqlHelperInterface $sql */ $sql = $c->get(SqlHelperInterface::class);
                /** @var Slugify $slugify */                   $slugify = $c->get(Slugify::class);
                /** @var MailerInterface $mailer */            $mailer = $c->get(MailerInterface::class);
                /** @var TokenGeneratorInterface $tokenGen */  $tokenGen = $c->get(TokenGeneratorInterface::class);
                /** @var RegistrationThrottleService $thr */   $thr = $c->get(RegistrationThrottleService::class);
                /** @var PasswordBlacklist $blacklist */       $blacklist = $c->get(PasswordBlacklist::class);
                /** @var DisposableChecker $disp */            $disp = $c->get(DisposableChecker::class);

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

            AccountConfirmationService::class => static function (ContainerInterface $c): AccountConfirmationService {
                /** @var UserTokenModelInterface $userTokenModel */ $userTokenModel = $c->get(UserTokenModelInterface::class);
                /** @var TokenGeneratorInterface $tokenGen */  $tokenGen = $c->get(TokenGeneratorInterface::class);
                return new AccountConfirmationService($userTokenModel, $tokenGen);
            },

            ConfirmationResendService::class => static function (ContainerInterface $c): ConfirmationResendService {
                /** @var FormValidatorInterface $validator */ $validator = $c->get(FormValidatorInterface::class);
                /** @var UserModelInterface $userModel */ $userModel = $c->get(UserModelInterface::class);
                /** @var UserTokenModelInterface $userTokenModel */ $userTokenModel = $c->get(UserTokenModelInterface::class);
                /** @var TokenGeneratorInterface $tokenGen */  $tokenGen = $c->get(TokenGeneratorInterface::class);
                /** @var MailerInterface $mailer */            $mailer = $c->get(MailerInterface::class);
                /** @var EmailQuotaService $quota */           $quota = $c->get(EmailQuotaService::class);

                return new ConfirmationResendService(
                    $validator,
                    $userModel,
                    $userTokenModel,
                    $tokenGen,
                    $mailer,
                    $quota,
                );
            },

            LoginService::class => static function (ContainerInterface $c): LoginService {
                /** @var FormValidatorInterface $validator */ $validator = $c->get(FormValidatorInterface::class);
                /** @var UserModelInterface $userModel */     $userModel = $c->get(UserModelInterface::class);
                /** @var SessionInterface $session */  $session = $c->get(SessionInterface::class);

                return new LoginService(
                    $validator,
                    $userModel,
                    $session,
                );
            },


            SecurityService::class => static function (ContainerInterface $c): SecurityService {
                /** @var RegistrationService $registration */                $registration = $c->get(RegistrationService::class);
                /** @var AccountConfirmationService $accountConfirmation */  $accountConfirmation = $c->get(AccountConfirmationService::class);
                /** @var ConfirmationResendService $confirmationResend */    $confirmationResend = $c->get(ConfirmationResendService::class);
                /** @var LoginService $login */                              $login = $c->get(LoginService::class);


                return new SecurityService($registration, $accountConfirmation, $confirmationResend, $login);
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
        ];
    }
}
