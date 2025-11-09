<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Core\ErrorCode;
use App\Core\Mail\MailerInterface;
use App\Model\Entity\UserEntity;
use App\Model\UserModel;
use App\Model\UserTokenModel;
use App\Security\Contract\TokenGeneratorInterface;
use App\Service\Security\ConfirmationResendService;
use App\Validation\FormValidator;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ConfirmationResendServiceTest extends TestCase
{
    /** @var MockObject&UserModel */
    private $userModel;
    /** @var MockObject&UserTokenModel */
    private $userTokenModel;
    /** @var MockObject&TokenGeneratorInterface */
    private $tokenGen;
    /** @var MockObject&MailerInterface */
    private $mailer;
    private FormValidator $validator;
    protected function setUp(): void
    {
        $this->userModel      = $this->createMock(UserModel::class);
        $this->userTokenModel = $this->createMock(UserTokenModel::class);
        $this->tokenGen       = $this->createMock(TokenGeneratorInterface::class);
        $this->mailer         = $this->createMock(MailerInterface::class);
        $this->validator      = new FormValidator();
        // fonctions pures : pas besoin de mock
    }

    private function makeService(): ConfirmationResendService
    {
        return new ConfirmationResendService($this->validator, $this->userModel, $this->userTokenModel, $this->tokenGen, $this->mailer);
    }

    /**
     * Crée un stub de UserEntity avec les getters utilisés par le service.
     * Si UserEntity est "final" non mockable chez vous, remplacez par une
     * vraie instance construite via son constructeur/setters.
     *
     * @return MockObject&UserEntity
     */
    private function mockUserEntity(int $id, string $status = 'pending', ?string $username = 'john')
    {
        /** @var MockObject&UserEntity $entity */
        $entity = $this->createStub(UserEntity::class);
        $entity->method('getUserId')->willReturn($id);
        $entity->method('getStatus')->willReturn($status);
        $entity->method('getUsername')->willReturn($username);
        return $entity;
    }

    // -------------------- Cas Validation e-mail --------------------

    public static function invalidEmailProvider(): array
    {
        return [
            'empty'      => [''],
            'spaces'     => ['   '],
            'bad_format' => ['foo@bar'],
        ];
    }

    #[DataProvider('invalidEmailProvider')]
    public function test_resend_returns_error_on_invalid_email(string $email): void
    {
        $service = $this->makeService();
        $result  = $service->resend($email);
        $this->assertSame(['error' => ErrorCode::AUTH_EMAIL_INVALID], $result);
    }

    // ---------------------- Cas métier principaux ----------------------

    public function test_resend_user_not_found_returns_generic_success_and_no_mail(): void
    {
        $email = 'nobody@example.test';
        $this->userModel
            ->expects($this->once())
            ->method('findOneByEmail')
            ->with($email)
            ->willReturn(null);
        $this->mailer
            ->expects($this->never())
            ->method('send');
        $service = $this->makeService();
        $result  = $service->resend($email);
        $this->assertSame([], $result, 'Anti-énumération: succès générique attendu');
    }

    public function test_resend_user_already_active_returns_error_already_confirmed(): void
    {
        $email = 'active@example.test';
        $user  = $this->mockUserEntity(10, 'active', 'alice');
        $this->userModel
            ->method('findOneByEmail')
            ->with($email)
            ->willReturn($user);
        $service = $this->makeService();
        $result  = $service->resend($email);
        $this->assertSame(['error' => ErrorCode::AUTH_ALREADY_CONFIRMED], $result);
    }

    public function test_resend_invalid_hash_length_returns_technical_error(): void
    {
        $email = 'john@example.test';
        $user  = $this->mockUserEntity(42, 'pending', 'john');
        $this->userModel->method('findOneByEmail')->willReturn($user);
        $this->tokenGen
            ->method('generateUrlSafeToken')
            ->with(32)
            ->willReturn('dummy-token');
        // Hash "binaire" de longueur ≠ 32 -> déclenche AUTH_TECHNICAL_ERROR
        $this->tokenGen
            ->method('hashToken')
            ->with('dummy-token')
            ->willReturn(str_repeat('A', 16));
        $service = $this->makeService();
        $result  = $service->resend($email);
        $this->assertSame(['error' => ErrorCode::AUTH_TECHNICAL_ERROR], $result);
    }

    public function test_resend_persistence_failure_returns_technical_error(): void
    {
        $email = 'john@example.test';
        $user  = $this->mockUserEntity(42, 'pending', 'john');
        $this->userModel->method('findOneByEmail')->willReturn($user);
        $this->tokenGen->method('generateUrlSafeToken')->willReturn('dummy-token');
        $this->tokenGen->method('hashToken')->willReturn(str_repeat('B', 32));
        // valide

        $this->userTokenModel
            ->method('createConfirmationToken')
            ->with(42, str_repeat('B', 32), $this->isInstanceOf(DateTimeImmutable::class))
            ->willReturn(false);
        $service = $this->makeService();
        $result  = $service->resend($email);
        $this->assertSame(['error' => ErrorCode::AUTH_TECHNICAL_ERROR], $result);
    }

    public function test_resend_mailer_exception_returns_send_failed(): void
    {
        $email = 'john@example.test';
        $user  = $this->mockUserEntity(42, 'pending', 'john');
        $this->userModel->method('findOneByEmail')->willReturn($user);
        $this->tokenGen->method('generateUrlSafeToken')->willReturn('dummy-token');
        $this->tokenGen->method('hashToken')->willReturn(str_repeat('C', 32));
        $this->userTokenModel->method('createConfirmationToken')->willReturn(true);
        $this->mailer
            ->method('send')
            ->willThrowException(new \RuntimeException('smtp down'));
        $service = $this->makeService();
        $result  = $service->resend($email);
        $this->assertSame(['error' => ErrorCode::AUTH_CONFIRM_EMAIL_SEND_FAILED], $result);
    }

    public function test_resend_mailer_returns_false_returns_send_failed(): void
    {
        $email = 'john@example.test';
        $user  = $this->mockUserEntity(42, 'pending', 'john');
        $this->userModel->method('findOneByEmail')->willReturn($user);
        $this->tokenGen->method('generateUrlSafeToken')->willReturn('dummy-token');
        $this->tokenGen->method('hashToken')->willReturn(str_repeat('D', 32));
        $this->userTokenModel->method('createConfirmationToken')->willReturn(true);
        $this->mailer
            ->method('send')
            ->willReturn(false);
        $service = $this->makeService();
        $result  = $service->resend($email);
        $this->assertSame(['error' => ErrorCode::AUTH_CONFIRM_EMAIL_SEND_FAILED], $result);
    }

    public function test_resend_success_nominal_returns_empty_array_and_sends_mail(): void
    {
        $email = 'john@example.test';
        $user  = $this->mockUserEntity(42, 'pending', 'john');
        $this->userModel->method('findOneByEmail')->willReturn($user);
        $this->tokenGen->method('generateUrlSafeToken')->willReturn('dummy-token');
        $this->tokenGen->method('hashToken')->willReturn(str_repeat('E', 32));
        $this->userTokenModel->method('createConfirmationToken')->willReturn(true);
        $this->mailer
            ->expects($this->once())
            ->method('send')
            ->with(
                $email,
                'john',
                $this->anything(), // subject
                $this->anything(), // template
                $this->callback(fn (array $ctx) => isset($ctx['link']) && is_string($ctx['link']))
            )
            ->willReturn(true);
        $service = $this->makeService();
        $result  = $service->resend($email);
        $this->assertSame([], $result);
    }

    public function test_resend_unexpected_exception_returns_technical_error(): void
    {
        $email = 'boom@example.test';
        // On simule une panne inattendue au tout début du try
        $this->userModel
        ->method('findOneByEmail')
        ->with($email)
        ->willThrowException(new \RuntimeException('DB offline'));
        $service = $this->makeService();
        $result  = $service->resend($email);
        $this->assertSame(['error' => ErrorCode::AUTH_TECHNICAL_ERROR], $result);
    }
}
