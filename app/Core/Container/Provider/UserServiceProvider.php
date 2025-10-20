<?php

declare(strict_types=1);

namespace App\Core\Container\Provider;

use App\Core\Mail\MailerInterface;
use App\Core\SqlHelper;
use App\Model\UserModel;
use App\Model\UserTokenModel;
use App\Security\Contract\TokenGeneratorInterface;
use App\Security\TokenGenerator;
use App\Service\SecurityService;
use App\Validation\FormValidator;
use Cocur\Slugify\Slugify;
use Psr\Container\ContainerInterface;

/**
 * Provides service definitions related to user management and security.
 *
 * This service provider registers user-related models, token handling,
 * and the security service used for authentication, registration, and
 * account confirmation.
 *
 * It defines how each component is created and how dependencies are wired
 * together in the application container.
 */
final class UserServiceProvider
{
    /**
     * Returns the list of container definitions for user-related services.
     *
     * Each array entry maps a class name to a factory closure that
     * instantiates the corresponding service with its dependencies.
     *
     * Registered services:
     * - UserModel: Handles CRUD operations for user data.
     * - UserTokenModel: Manages user-related tokens (confirmation, reset, etc.).
     * - TokenGenerator / TokenGeneratorInterface: Provides random secure token generation.
     * - SecurityService: Coordinates validation, persistence, email sending, and security logic.
     *
     * @return array<class-string, \Closure(ContainerInterface):object>
     *     A map of service definitions for dependency injection.
     */
    public static function getDefinitions(): array
    {
        return [
        UserModel::class => static function (ContainerInterface $container): UserModel {
            /** @var SqlHelper $sql */
            $sql = $container->get(SqlHelper::class);
            return new UserModel($sql); // <-- 1 seul argument si c’est bien la signature réelle
        },

        UserTokenModel::class => static function (ContainerInterface $container): UserTokenModel {
            /** @var SqlHelper $sql */
            $sql = $container->get(SqlHelper::class);
            return new UserTokenModel($sql);
        },

        TokenGenerator::class => static fn (): TokenGenerator => new TokenGenerator(),

        TokenGeneratorInterface::class => static function (ContainerInterface $container): TokenGeneratorInterface {
            /** @var TokenGeneratorInterface $tg */
            $tg = $container->get(TokenGenerator::class);
            return $tg;
        },

        SecurityService::class => static function (ContainerInterface $container): SecurityService {
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

            return new SecurityService(
                $validator,
                $userModel,
                $userTokenModel,
                $slugify,
                $mailer,
                $tokenGen,
                $sql
            );
        },
        ];
    }
}
