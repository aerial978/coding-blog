<?php

declare(strict_types=1);

namespace Tests\Unit\Handler\Auth;

use App\Core\Contract\FlashInterface;
use App\Handler\Auth\Email2faGetHandler;
use App\Http\Contract\ResponderInterface;
use App\Security\Contract\CsrfTokenInterface;
use App\Security\Contract\Email2faPendingSessionInterface;
use App\Security\Contract\HoneypotValidatorInterface;
use App\Security\Contract\SubmissionDelayValidatorInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class Email2faGetHandlerTest extends TestCase
{
    private FlashInterface&MockObject $flash;
    private ResponderInterface&MockObject $responder;
    private CsrfTokenInterface&MockObject $csrf;
    private HoneypotValidatorInterface&MockObject $honeypot;
    private SubmissionDelayValidatorInterface&MockObject $submissionDelay;
    private Email2faPendingSessionInterface&MockObject $pendingSession;

    private Email2faGetHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->flash           = $this->createMock(FlashInterface::class);
        $this->responder       = $this->createMock(ResponderInterface::class);
        $this->csrf            = $this->createMock(CsrfTokenInterface::class);
        $this->honeypot        = $this->createMock(HoneypotValidatorInterface::class);
        $this->submissionDelay = $this->createMock(SubmissionDelayValidatorInterface::class);
        $this->pendingSession  = $this->createMock(Email2faPendingSessionInterface::class);

        $this->handler = new Email2faGetHandler(
            $this->flash,
            $this->responder,
            $this->csrf,
            $this->honeypot,
            $this->submissionDelay,
            $this->pendingSession,
        );
    }

    public function testRedirectsToLoginWhenNoPendingSession(): void
    {
        $this->pendingSession
            ->expects($this->once())
            ->method('hasPending')
            ->willReturn(false);

        $this->pendingSession
            ->expects($this->once())
            ->method('clear');

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with(
                'error',
                'Votre session de vérification a expiré. Veuillez vous reconnecter.'
            );

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/login');

        $this->responder
            ->expects($this->never())
            ->method('render');

        $this->handler->handle();
    }

    public function testRedirectsToLoginWhenPendingSessionIsExpired(): void
    {
        $this->pendingSession
            ->expects($this->once())
            ->method('hasPending')
            ->willReturn(true);

        $this->pendingSession
            ->expects($this->once())
            ->method('isExpired')
            ->willReturn(true);

        $this->pendingSession
            ->expects($this->once())
            ->method('clear');

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with(
                'error',
                'Votre session de vérification a expiré. Veuillez vous reconnecter.'
            );

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/login');

        $this->responder
            ->expects($this->never())
            ->method('render');

        $this->handler->handle();
    }

    public function testRendersEmail2faPageWhenPendingSessionIsValid(): void
    {
        $this->pendingSession
            ->expects($this->once())
            ->method('hasPending')
            ->willReturn(true);

        $this->pendingSession
            ->expects($this->once())
            ->method('isExpired')
            ->willReturn(false);

        $this->pendingSession
            ->expects($this->never())
            ->method('clear');

        $this->flash
            ->expects($this->never())
            ->method('add');

        $this->submissionDelay
            ->expects($this->exactly(2))
            ->method('markFormStart')
            ->with(
                $this->logicalOr(
                    $this->equalTo('email_2fa_form'),
                    $this->equalTo('email_2fa_resend_form')
                )
            );

        $this->csrf
            ->expects($this->exactly(2))
            ->method('generateToken')
            ->willReturnMap([
                ['email_2fa_form', 'csrf-main-token'],
                ['email_2fa_resend_form', 'csrf-resend-token'],
            ]);

        $this->honeypot
            ->expects($this->once())
            ->method('fieldName')
            ->willReturn('hp_email_2fa');

        $this->responder
            ->expects($this->once())
            ->method('render')
            ->with(
                'security/email-2fa.html.twig',
                $this->callback(function (array $data): bool {
                    $this->assertSame('Vérification de connexion', $data['title']);
                    $this->assertSame('csrf-main-token', $data['csrf_token']);
                    $this->assertSame('csrf-resend-token', $data['csrf_token_resend']);
                    $this->assertSame('hp_email_2fa', $data['honeypot_name']);
                    $this->assertSame('email_2fa_form', $data['form_id']);

                    $this->assertArrayNotHasKey('flashes', $data);
                    $this->assertArrayNotHasKey('show_header', $data);

                    return true;
                })
            );

        $this->responder
            ->expects($this->never())
            ->method('redirect');

        $this->handler->handle();
    }
}
