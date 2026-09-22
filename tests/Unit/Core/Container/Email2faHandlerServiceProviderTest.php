<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Container;

use App\Core\Container\Provider\Email2faHandlerServiceProvider;
use App\Core\Contract\FlashInterface;
use App\Core\Contract\SessionInterface;
use App\Handler\Auth\Email2faGetHandler;
use App\Handler\Auth\Email2faPostHandler;
use App\Handler\Auth\Email2faResendPostHandler;
use App\Http\Contract\ResponderInterface;
use App\Model\Contract\UserModelInterface;
use App\Security\Contract\CsrfTokenInterface;
use App\Security\Contract\Email2faPendingSessionInterface;
use App\Security\Contract\HoneypotValidatorInterface;
use App\Security\Contract\RememberMeCookieManagerInterface;
use App\Security\Contract\SubmissionDelayValidatorInterface;
use App\Security\Guard\Contract\HoneypotGuardInterface;
use App\Security\Guard\Contract\RateLimitGuardInterface;
use App\Security\Guard\Contract\SubmissionDelayGuardInterface;
use App\Service\Security\Contract\Email2faServiceInterface;
use App\Service\Security\Contract\RememberMeServiceInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;

final class Email2faHandlerServiceProviderTest extends TestCase
{
    public function testGetDefinitionsReturnsExpectedDefinitions(): void
    {
        $definitions = Email2faHandlerServiceProvider::getDefinitions();

        $this->assertArrayHasKey(Email2faGetHandler::class, $definitions);
        $this->assertArrayHasKey(Email2faPostHandler::class, $definitions);
        $this->assertArrayHasKey(Email2faResendPostHandler::class, $definitions);
    }

    public function testEmail2faHandlerDefinitionsCanBeResolved(): void
    {
        $definitions = Email2faHandlerServiceProvider::getDefinitions();

        $email2faService = $this->createMock(
            Email2faServiceInterface::class
        );
        $pendingSession = $this->createMock(
            Email2faPendingSessionInterface::class
        );
        $userModel = $this->createMock(
            UserModelInterface::class
        );
        $session = $this->createMock(
            SessionInterface::class
        );
        $flash = $this->createMock(
            FlashInterface::class
        );
        $responder = $this->createMock(
            ResponderInterface::class
        );
        $csrf = $this->createMock(
            CsrfTokenInterface::class
        );
        $honeypot = $this->createMock(
            HoneypotValidatorInterface::class
        );
        $submissionDelay = $this->createMock(
            SubmissionDelayValidatorInterface::class
        );
        $honeypotGuard = $this->createMock(
            HoneypotGuardInterface::class
        );
        $submissionDelayGuard = $this->createMock(
            SubmissionDelayGuardInterface::class
        );
        $rateLimitGuard = $this->createMock(
            RateLimitGuardInterface::class
        );
        $rememberMeService = $this->createMock(
            RememberMeServiceInterface::class
        );
        $rememberMeManager = $this->createMock(
            RememberMeCookieManagerInterface::class
        );

        $getHandler = $definitions[Email2faGetHandler::class](
            $this->containerWith([
                FlashInterface::class                    => $flash,
                ResponderInterface::class                => $responder,
                CsrfTokenInterface::class                => $csrf,
                HoneypotValidatorInterface::class        => $honeypot,
                SubmissionDelayValidatorInterface::class => $submissionDelay,
                Email2faPendingSessionInterface::class   => $pendingSession,
            ])
        );

        $postHandler = $definitions[Email2faPostHandler::class](
            $this->containerWith([
                Email2faServiceInterface::class          => $email2faService,
                Email2faPendingSessionInterface::class   => $pendingSession,
                UserModelInterface::class                => $userModel,
                SessionInterface::class                  => $session,
                FlashInterface::class                    => $flash,
                ResponderInterface::class                => $responder,
                CsrfTokenInterface::class                => $csrf,
                HoneypotGuardInterface::class            => $honeypotGuard,
                SubmissionDelayGuardInterface::class     => $submissionDelayGuard,
                RateLimitGuardInterface::class           => $rateLimitGuard,
                RememberMeServiceInterface::class        => $rememberMeService,
                RememberMeCookieManagerInterface::class  => $rememberMeManager,
            ])
        );

        $resendHandler = $definitions[Email2faResendPostHandler::class](
            $this->containerWith([
                Email2faServiceInterface::class        => $email2faService,
                Email2faPendingSessionInterface::class => $pendingSession,
                UserModelInterface::class              => $userModel,
                FlashInterface::class                  => $flash,
                ResponderInterface::class              => $responder,
                CsrfTokenInterface::class              => $csrf,
                HoneypotGuardInterface::class          => $honeypotGuard,
                SubmissionDelayGuardInterface::class   => $submissionDelayGuard,
                RateLimitGuardInterface::class         => $rateLimitGuard,
            ])
        );

        $this->assertInstanceOf(Email2faGetHandler::class, $getHandler);
        $this->assertInstanceOf(Email2faPostHandler::class, $postHandler);
        $this->assertInstanceOf(
            Email2faResendPostHandler::class,
            $resendHandler
        );
    }

    /**
     * @param array<string,mixed> $services
     *
     * @return ContainerInterface&MockObject
     */
    private function containerWith(array $services): ContainerInterface&MockObject
    {
        $container = $this->createMock(ContainerInterface::class);

        $container
            ->method('get')
            ->willReturnCallback(
                static function (string $id) use ($services): mixed {
                    if (!array_key_exists($id, $services)) {
                        throw new RuntimeException(sprintf(
                            'Service "%s" is not defined in test container.',
                            $id
                        ));
                    }

                    return $services[$id];
                }
            );

        return $container;
    }
}
