<?php

declare(strict_types=1);

namespace App\Core\Container\Provider;

use App\Core\Mail\MailerInterface;
use App\Core\SqlHelper;
use App\Model\UserModel;
use App\Model\UserTokenModel;
use App\Security\Contract\TokenGeneratorInterface;
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
     * @return array<class-string, \Closure(ContainerInterface):object>
     */
    public static function getDefinitions(): array
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

                return new RegistrationService(
                    $validator,
                    $userModel,
                    $userTokenModel,
                    $slugify,
                    $mailer,
                    $tokenGen,
                    $sql
                );
            },

            AccountConfirmationService::class => static function (ContainerInterface $container): AccountConfirmationService {
                /** @var UserTokenModel $userTokenModel */
                $userTokenModel = $container->get(UserTokenModel::class);
                /** @var TokenGeneratorInterface $tokenGen */
                $tokenGen = $container->get(TokenGeneratorInterface::class);

                return new AccountConfirmationService($userTokenModel, $tokenGen);
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

                return new ConfirmationResendService($validator, $userModel, $userTokenModel, $tokenGen, $mailer);
            },

            SecurityService::class => static function (ContainerInterface $container): SecurityService {
                /** @var RegistrationService $registrationService */
                $registrationService = $container->get(RegistrationService::class);
                /** @var AccountConfirmationService $accountConfirmation */
                $accountConfirmation = $container->get(AccountConfirmationService::class);
                /** @var ConfirmationResendService $confirmationResend */
                $confirmationResend = $container->get(ConfirmationResendService::class);

                return new SecurityService(
                    $registrationService,
                    $accountConfirmation,
                    $confirmationResend,
                );
            },

            SecurityServiceInterface::class => static function (ContainerInterface $container): SecurityServiceInterface {
                /** @var SecurityService $svc */
                $svc = $container->get(SecurityService::class);
                return $svc;
            },
        ];
    }
}
