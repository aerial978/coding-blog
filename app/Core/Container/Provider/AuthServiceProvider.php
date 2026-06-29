<?php

declare(strict_types=1);

namespace App\Core\Container\Provider;

use App\Core\Contract\SessionInterface;
use App\Core\Mail\MailerInterface;
use App\Model\Contract\Email2faChallengeModelInterface;
use App\Security\Contract\Email2faPendingSessionInterface;
use App\Security\Email2faPendingSession;
use App\Service\Security\Contract\Email2faServiceInterface;
use App\Service\Security\Email2faService;
use Psr\Container\ContainerInterface;

final class AuthServiceProvider
{
    /**
     * @return array<string, callable(ContainerInterface): mixed>
     */
    public static function getDefinitions(): array
    {
        return array_merge(
            AuthGuardServiceProvider::getDefinitions(),
            self::getEmail2faDefinitions(),
            GoogleOAuthServiceProvider::getDefinitions(),
            RegisterHandlerServiceProvider::getDefinitions(),
            LoginHandlerServiceProvider::getDefinitions(),
            Email2faHandlerServiceProvider::getDefinitions(),
            PasswordRecoveryHandlerServiceProvider::getDefinitions(),
            LogoutHandlerServiceProvider::getDefinitions(),
        );
    }

    /**
     * @return array<string, callable(ContainerInterface): mixed>
     */
    private static function getEmail2faDefinitions(): array
    {
        return [
            Email2faPendingSession::class => static function (ContainerInterface $container): Email2faPendingSession {
                /** @var SessionInterface $session */
                $session = $container->get(SessionInterface::class);

                return new Email2faPendingSession($session);
            },

            Email2faPendingSessionInterface::class => static function (
                ContainerInterface $container
            ): Email2faPendingSessionInterface {
                /** @var Email2faPendingSessionInterface $pendingSession */
                $pendingSession = $container->get(Email2faPendingSession::class);

                return $pendingSession;
            },

            Email2faService::class => static function (ContainerInterface $container): Email2faService {
                /** @var Email2faChallengeModelInterface $challengeModel */
                $challengeModel = $container->get(Email2faChallengeModelInterface::class);

                /** @var MailerInterface $mailer */
                $mailer = $container->get(MailerInterface::class);

                return new Email2faService($challengeModel, $mailer);
            },

            Email2faServiceInterface::class => static function (
                ContainerInterface $container
            ): Email2faServiceInterface {
                /** @var Email2faServiceInterface $service */
                $service = $container->get(Email2faService::class);

                return $service;
            },
        ];
    }
}
