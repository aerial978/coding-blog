<?php

declare(strict_types=1);

namespace Tests\Unit\Service\Security;

use App\Core\Contract\SqlHelperInterface;
use App\Core\ErrorCode;
use App\Core\Mail\MailerInterface;
use App\Model\Contract\UserModelInterface;
use App\Model\Contract\UserTokenModelInterface;
use App\Model\Entity\UserEntity;
use App\Security\Contract\RegistrationThrottleServiceInterface;
use App\Security\Contract\TokenGeneratorInterface;
use App\Security\DisposableChecker;
use App\Security\PasswordBlacklist;
use App\Service\Security\RegistrationService;
use App\Validation\Contract\FormValidatorInterface;
use Cocur\Slugify\Slugify;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class RegistrationServiceTest extends TestCase
{
    private FormValidatorInterface&MockObject $validator;
    private UserModelInterface&MockObject $userModel;
    private UserTokenModelInterface&MockObject $userTokenModel;
    private MailerInterface&MockObject $mailer;
    private TokenGeneratorInterface&MockObject $tokenGen;
    private SqlHelperInterface&MockObject $sqlHelper;
    private RegistrationThrottleServiceInterface&MockObject $throttle;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REMOTE_ADDR']     = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';

        $this->validator      = $this->createMock(FormValidatorInterface::class);
        $this->userModel      = $this->createMock(UserModelInterface::class);
        $this->userTokenModel = $this->createMock(UserTokenModelInterface::class);
        $this->mailer         = $this->createMock(MailerInterface::class);
        $this->tokenGen       = $this->createMock(TokenGeneratorInterface::class);
        $this->sqlHelper      = $this->createMock(SqlHelperInterface::class);
        $this->throttle       = $this->createMock(RegistrationThrottleServiceInterface::class);
    }

    protected function tearDown(): void
    {
        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
        parent::tearDown();
    }

    /**
     * @param list<string> $blacklist
     */
    private function makePasswordBlacklist(array $blacklist = []): PasswordBlacklist
    {
        return new PasswordBlacklist($blacklist);
    }

    /**
     * @param list<string> $domains
     */
    private function makeDisposableChecker(array $domains = []): DisposableChecker
    {
        return new DisposableChecker($domains);
    }

    private function makeService(
        ?PasswordBlacklist $passwordBlacklist = null,
        ?DisposableChecker $disposableChecker = null,
    ): RegistrationService {
        return new RegistrationService(
            $this->validator,
            $this->userModel,
            $this->userTokenModel,
            new Slugify(),
            $this->mailer,
            $this->tokenGen,
            $this->sqlHelper,
            $this->throttle,
            $passwordBlacklist ?? $this->makePasswordBlacklist(),
            $disposableChecker ?? $this->makeDisposableChecker(),
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function validForm(): array
    {
        return [
            'username'         => 'John Doe',
            'email'            => 'john@example.com',
            'password'         => 'StrongPassword123!',
            'confirm_password' => 'StrongPassword123!',
        ];
    }

    public function testRegisterReturnsValidationErrorsAndOldWhenValidatorFails(): void
    {
        $form = $this->validForm();

        $this->validator
            ->expects($this->once())
            ->method('validateRegistration')
            ->willReturn(['ERR_FORM']);

        $this->userModel
            ->expects($this->never())
            ->method('createUser');

        $service = $this->makeService();

        $result = $service->register($form);

        $this->assertSame(['ERR_FORM'], $result['errors']);
        $this->assertSame(
            ['username' => 'John Doe', 'email' => 'john@example.com'],
            $result['old']
        );
    }

    public function testRegisterReturnsPasswordTooCommonWhenPasswordIsBlacklisted(): void
    {
        $form = $this->validForm();

        $this->validator
            ->expects($this->once())
            ->method('validateRegistration')
            ->willReturn([]);

        $this->userModel
            ->expects($this->never())
            ->method('createUser');

        $service = $this->makeService(
            passwordBlacklist: $this->makePasswordBlacklist(['StrongPassword123!'])
        );

        $result = $service->register($form);

        $this->assertSame([ErrorCode::AUTH_PASSWORD_TOO_COMMON], $result['errors']);
        $this->assertSame(
            ['username' => 'John Doe', 'email' => 'john@example.com'],
            $result['old']
        );
    }

    public function testRegisterReturnsDisposableEmailErrorWhenEmailIsDisposable(): void
    {
        $form          = $this->validForm();
        $form['email'] = 'john@mailinator.com';

        $this->validator
            ->expects($this->once())
            ->method('validateRegistration')
            ->willReturn([]);

        $this->userModel
            ->expects($this->never())
            ->method('createUser');

        $service = $this->makeService(
            disposableChecker: $this->makeDisposableChecker(['mailinator.com'])
        );

        $result = $service->register($form);

        $this->assertSame([ErrorCode::AUTH_REGISTRATION_EMAIL_DISPOSABLE], $result['errors']);
        $this->assertSame(
            ['username' => 'John Doe', 'email' => 'john@mailinator.com'],
            $result['old']
        );
    }

    public function testRegisterReturnsUsernameExistsWhenUsernameAlreadyExists(): void
    {
        $form = $this->validForm();

        $this->validator
            ->expects($this->once())
            ->method('validateRegistration')
            ->willReturn([]);

        $this->userModel
            ->expects($this->once())
            ->method('findOneByUsername')
            ->with('John Doe')
            ->willReturn(new UserEntity());

        $this->userModel
            ->expects($this->never())
            ->method('createUser');

        $service = $this->makeService();

        $result = $service->register($form);

        $this->assertSame(
            [ErrorCode::AUTH_USERNAME_EXISTS, ErrorCode::AUTH_PASSWORD_REENTER],
            $result['errors']
        );
        $this->assertSame(
            ['username' => 'John Doe', 'email' => 'john@example.com'],
            $result['old']
        );
    }

    public function testRegisterReturnsQuotaExceededWhenThrottleDeniesRegistration(): void
    {
        $form = $this->validForm();

        $this->validator
            ->method('validateRegistration')
            ->willReturn([]);

        $this->userModel
            ->method('findOneByUsername')
            ->willReturn(null);

        $this->userModel
            ->method('findOneByEmail')
            ->willReturn(null);

        $this->throttle
            ->expects($this->once())
            ->method('checkQuota')
            ->with('127.0.0.1')
            ->willReturn([
                'allowed' => false,
                'reason'  => 'quota_exceeded',
            ]);

        $this->userModel
            ->expects($this->never())
            ->method('createUser');

        $service = $this->makeService();

        $result = $service->register($form);

        $this->assertSame([ErrorCode::AUTH_REGISTRATION_QUOTA_EXCEEDED], $result['errors']);
        $this->assertSame(
            ['username' => 'John Doe', 'email' => 'john@example.com'],
            $result['old']
        );
    }

    public function testRegisterReturnsConfirmEmailSendFailedWhenMailerReturnsFalse(): void
    {
        $form = $this->validForm();

        $this->validator->method('validateRegistration')->willReturn([]);
        $this->userModel->method('findOneByUsername')->willReturn(null);
        $this->userModel->method('findOneByEmail')->willReturn(null);

        $this->throttle
            ->method('checkQuota')
            ->willReturn(['allowed' => true]);

        $this->tokenGen
            ->expects($this->once())
            ->method('generateUrlSafeToken')
            ->with(32)
            ->willReturn('plain-token');

        $this->tokenGen
            ->expects($this->once())
            ->method('hashToken')
            ->with('plain-token')
            ->willReturn(str_repeat('a', 32));

        $this->sqlHelper->expects($this->once())->method('beginTransaction');
        $this->sqlHelper->expects($this->once())->method('commit');
        $this->sqlHelper->expects($this->never())->method('rollBack');

        $this->userModel
            ->expects($this->once())
            ->method('createUser')
            ->with($this->isInstanceOf(UserEntity::class))
            ->willReturn(42);

        $this->userTokenModel
            ->expects($this->once())
            ->method('createConfirmationToken')
            ->with(
                42,
                str_repeat('a', 32),
                $this->isInstanceOf(\DateTimeImmutable::class)
            )
            ->willReturn(true);

        $this->mailer
            ->expects($this->once())
            ->method('send')
            ->willReturn(false);

        $this->throttle
            ->expects($this->never())
            ->method('recordSuccess');

        $service = $this->makeService();

        $result = $service->register($form);

        $this->assertSame([ErrorCode::AUTH_CONFIRM_EMAIL_SEND_FAILED], $result['errors']);
        $this->assertSame(
            ['username' => 'John Doe', 'email' => 'john@example.com'],
            $result['old']
        );
    }

    public function testRegisterReturnsOkWhenRegistrationSucceeds(): void
    {
        $form = $this->validForm();

        $this->validator->method('validateRegistration')->willReturn([]);
        $this->userModel->method('findOneByUsername')->willReturn(null);
        $this->userModel->method('findOneByEmail')->willReturn(null);

        $this->throttle
            ->expects($this->once())
            ->method('checkQuota')
            ->with('127.0.0.1')
            ->willReturn(['allowed' => true]);

        $this->tokenGen
            ->expects($this->once())
            ->method('generateUrlSafeToken')
            ->with(32)
            ->willReturn('plain-token');

        $this->tokenGen
            ->expects($this->once())
            ->method('hashToken')
            ->with('plain-token')
            ->willReturn(str_repeat('b', 32));

        $this->sqlHelper->expects($this->once())->method('beginTransaction');
        $this->sqlHelper->expects($this->once())->method('commit');
        $this->sqlHelper->expects($this->never())->method('rollBack');

        $this->userModel
            ->expects($this->once())
            ->method('createUser')
            ->with($this->callback(function (UserEntity $user): bool {
                return $user->getUsername() === 'John Doe'
                    && $user->getEmail()    === 'john@example.com'
                    && $user->getSlug()     === 'john-doe'
                    && password_verify('StrongPassword123!', $user->getPassword());
            }))
            ->willReturn(42);

        $this->userTokenModel
            ->expects($this->once())
            ->method('createConfirmationToken')
            ->with(
                42,
                str_repeat('b', 32),
                $this->isInstanceOf(\DateTimeImmutable::class)
            )
            ->willReturn(true);

        $this->mailer
            ->expects($this->once())
            ->method('send')
            ->with(
                'john@example.com',
                'John Doe',
                'Confirmation de votre compte',
                'confirmation.html',
                $this->callback(function (array $vars): bool {
                    return $vars['username'] === 'John Doe'
                        && is_string($vars['link'])
                        && str_contains($vars['link'], '/confirm-account?token=plain-token');
                })
            )
            ->willReturn(true);

        $this->throttle
            ->expects($this->once())
            ->method('recordSuccess')
            ->with(
                email: 'john@example.com',
                userId: 42,
                ip: '127.0.0.1',
                userAgent: 'PHPUnit',
            );

        $service = $this->makeService();

        $result = $service->register($form);

        $this->assertSame(['ok' => true], $result);
    }

    public function testRegisterMapsDuplicateEmailPdoExceptionToDomainErrors(): void
    {
        $form = $this->validForm();

        $this->validator->method('validateRegistration')->willReturn([]);

        $this->userModel
            ->method('findOneByUsername')
            ->willReturn(null);

        $pdo            = new \PDOException('duplicate');
        $pdo->errorInfo = ['23000', 1062, "Duplicate entry 'john@example.com' for key 'users.email'"];

        $ref  = new \ReflectionClass($pdo);
        $prop = $ref->getParentClass()->getProperty('code');
        $prop->setAccessible(true);
        $prop->setValue($pdo, '23000');

        $this->userModel
            ->expects($this->once())
            ->method('findOneByEmail')
            ->with('john@example.com')
            ->willThrowException($pdo);

        $service = $this->makeService();

        $result = $service->register($form);

        $this->assertSame(
            [ErrorCode::AUTH_EMAIL_EXISTS, ErrorCode::AUTH_PASSWORD_REENTER],
            $result['errors']
        );
        $this->assertSame(
            ['username' => 'John Doe', 'email' => 'john@example.com'],
            $result['old']
        );
    }

    public function testRegisterReturnsTechnicalErrorWhenUnexpectedThrowableOccurs(): void
    {
        $form = $this->validForm();

        $this->validator
            ->expects($this->once())
            ->method('validateRegistration')
            ->willThrowException(new \RuntimeException('unexpected failure'));

        $service = $this->makeService();

        $result = $service->register($form);

        $this->assertSame([ErrorCode::AUTH_TECHNICAL_ERROR], $result['errors']);
        $this->assertSame(
            ['username' => 'John Doe', 'email' => 'john@example.com'],
            $result['old']
        );
    }
}
