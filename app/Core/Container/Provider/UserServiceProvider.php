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
use App\Validation\FormValidator;
use Cocur\Slugify\Slugify;
use Psr\Container\ContainerInterface;

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
            UserModel::class => static function (ContainerInterface $container): UserModel {
                /** @var SqlHelper $sql */
                $sql = $container->get(SqlHelper::class);
                return new UserModel($sql);
            },

            UserTokenModel::class => static function (ContainerInterface $container): UserTokenModel {
                /** @var SqlHelper $sql */
                $sql = $container->get(SqlHelper::class);
                return new UserTokenModel($sql);
            },

            RegistrationEventModel::class => static function (ContainerInterface $container): RegistrationEventModel {
                /** @var SqlHelper $sql */
                $sql = $container->get(SqlHelper::class);
                return new RegistrationEventModel($sql);
            },

            EmailEventModel::class => static function (ContainerInterface $container): EmailEventModel {
                /** @var SqlHelper $sql */
                $sql = $container->get(SqlHelper::class);
                return new EmailEventModel($sql);
            },
        ];
    }

    /**
     * @phpstan-return array<class-string, \Closure(ContainerInterface): object>
     */
    private static function getSecurityDefinitions(): array
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

            PasswordBlacklist::class => static function (ContainerInterface $container): PasswordBlacklist {
                $path = __DIR__ . '/../../../Config/password_blacklist.php';
                $raw  = require $path;

                /** @var array<int, string> $list */
                $list = is_array($raw) ? array_values(array_filter($raw, 'is_string')) : [];

                return new PasswordBlacklist($list);
            },

            DisposableChecker::class => static function (ContainerInterface $container): DisposableChecker {
                $raw = require __DIR__ . '/../../../Config/disposable_domains.php';

                /** @var array<int, string> $domains */
                $domains = is_array($raw) ? array_values(array_filter($raw, 'is_string')) : [];

                return new DisposableChecker($domains);
            },

            RegistrationService::class => static function (ContainerInterface $container): RegistrationService {
                /** @var FormValidator $validator */
                $validator = $container->get(FormValidator::class);
                /** @var UserModel $userModel */
                $userModel = $container->get(UserModel::class);
                /** @var UserTokenModel $userTokenModel */
                $userTokenModel = $container->get(UserTokenModel::class);
                /** @var Slugify $slugify */
                $slugify = $container->get(Slugify::class);
                /** @var MailerInterface $mailer */
                $mailer = $container->get(MailerInterface::class);
                /** @var TokenGeneratorInterface $tokenGen */
                $tokenGen = $container->get(TokenGeneratorInterface::class);
                /** @var SqlHelper $sql */
                $sql = $container->get(SqlHelper::class);
                /** @var RegistrationThrottleService $throttle */
                $throttle = $container->get(RegistrationThrottleService::class);
                /** @var PasswordBlacklist $passwordBlacklist */
                $passwordBlacklist = $container->get(PasswordBlacklist::class);
                /** @var DisposableChecker $disposableChecker */
                $disposableChecker = $container->get(DisposableChecker::class);

                return new RegistrationService(
                    $validator,
                    $userModel,
                    $userTokenModel,
                    $slugify,
                    $mailer,
                    $tokenGen,
                    $sql,
                    $throttle,
                    $passwordBlacklist,
                    $disposableChecker,
                );
            },

            AccountConfirmationService::class => static function (ContainerInterface $container): AccountConfirmationService {
                /** @var UserTokenModel $userTokenModel */
                $userTokenModel = $container->get(UserTokenModel::class);
                /** @var TokenGeneratorInterface $tokenGen */
                $tokenGen = $container->get(TokenGeneratorInterface::class);

                return new AccountConfirmationService(
                    $userTokenModel,
                    $tokenGen
                );
            },

            ConfirmationResendService::class => static function (ContainerInterface $container): ConfirmationResendService {
                /** @var FormValidator $validator */
                $validator = $container->get(FormValidator::class);
                /** @var UserModel $userModel */
                $userModel = $container->get(UserModel::class);
                /** @var UserTokenModel $userTokenModel */
                $userTokenModel = $container->get(UserTokenModel::class);
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

            SecurityService::class => static function (ContainerInterface $container): SecurityService {
                /** @var RegistrationService $registration */
                $registration = $container->get(RegistrationService::class);
                /** @var AccountConfirmationService $accountConfirmation */
                $accountConfirmation = $container->get(AccountConfirmationService::class);
                /** @var ConfirmationResendService $confirmationResend */
                $confirmationResend = $container->get(ConfirmationResendService::class);

                return new SecurityService(
                    $registration,
                    $accountConfirmation,
                    $confirmationResend,
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
            SecurityServiceInterface::class => static function (ContainerInterface $container): object {
                /** @var SecurityService $service */
                $service = $container->get(SecurityService::class);
                return $service;
            },
        ];
    }
}
