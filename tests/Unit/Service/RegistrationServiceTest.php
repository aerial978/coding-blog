<?php

declare(strict_types=1);

namespace Tests\Unit\Service\Security;

use App\Core\Contract\SqlHelperInterface;
use App\Core\ErrorCode;
use App\Core\Mail\MailerInterface;
use App\Model\Contract\UserModelInterface;
use App\Model\Contract\UserTokenModelInterface;
use App\Security\Contract\RegistrationThrottleServiceInterface;
use App\Security\Contract\TokenGeneratorInterface;
use App\Security\DisposableEmailChecker;
use App\Security\PasswordBlacklist;
use App\Service\Security\RegistrationService;
use App\Validation\Contract\FormValidatorInterface;
use Cocur\Slugify\Slugify;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class RegistrationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_SERVER['REMOTE_ADDR']     = '1.2.3.4';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';
    }

    public function testReturnsValidationErrorsEarly(): void
    {
        $validator = $this->createMock(FormValidatorInterface::class);
        $validator->method('validateRegistration')->willReturn(['some_error']);

        $userModel = $this->createMock(UserModelInterface::class);
        $userModel->expects(self::never())->method('createUser');

        $service = $this->makeService(
            validator: $validator,
            userModel: $userModel
        );

        $res = $service->register([
            'username'         => 'bob',
            'email'            => 'bob@example.com',
            'password'         => 'SomeStrong#Password1',
            'confirm_password' => 'SomeStrong#Password1',
        ]);

        self::assertSame(['some_error'], $res['errors']);
        self::assertSame(['username' => 'bob', 'email' => 'bob@example.com'], $res['old']);
    }

    public function testRejectsBlacklistedPassword(): void
    {
        $validator = $this->createMock(FormValidatorInterface::class);
        $validator->method('validateRegistration')->willReturn([]);

        $blacklist = new PasswordBlacklist(['password123']);

        $service = $this->makeService(
            validator: $validator,
            passwordBlacklist: $blacklist
        );

        $res = $service->register([
            'username'         => 'bob',
            'email'            => 'bob@example.com',
            'password'         => 'password123',
            'confirm_password' => 'password123',
        ]);

        self::assertContains(ErrorCode::AUTH_PASSWORD_TOO_COMMON, $res['errors']);
    }

    public function testRejectsDisposableEmailAndOverridesErrors(): void
    {
        $validator = $this->createMock(FormValidatorInterface::class);
        $validator->method('validateRegistration')->willReturn([]);

        $disposable = new DisposableEmailChecker(['yopmail.com']);

        $service = $this->makeService(
            validator: $validator,
            disposableEmailChecker: $disposable
        );

        $res = $service->register([
            'username'         => 'bob',
            'email'            => 'bob@yopmail.com',
            'password'         => 'SomeStrong#Password1',
            'confirm_password' => 'SomeStrong#Password1',
        ]);

        self::assertSame([ErrorCode::AUTH_REGISTRATION_EMAIL_DISPOSABLE], $res['errors']);
    }

    public function testBlocksWhenRegistrationQuotaExceeded(): void
    {
        $validator = $this->createMock(FormValidatorInterface::class);
        $validator->method('validateRegistration')->willReturn([]);

        $throttle = $this->createMock(RegistrationThrottleServiceInterface::class);
        $throttle->method('checkQuota')->willReturn(['allowed' => false, 'reason' => 'hour_quota_exceeded']);
        $throttle->expects(self::never())->method('recordSuccess');

        $service = $this->makeService(
            validator: $validator,
            throttle: $throttle
        );

        $res = $service->register([
            'username'         => 'bob',
            'email'            => 'bob@example.com',
            'password'         => 'SomeStrong#Password1',
            'confirm_password' => 'SomeStrong#Password1',
        ]);

        self::assertSame([ErrorCode::AUTH_REGISTRATION_QUOTA_EXCEEDED], $res['errors']);
    }

    public function testHappyPathCreatesUserTokenSendsMailAndRecordsThrottle(): void
    {
        $validator = $this->createMock(FormValidatorInterface::class);
        $validator->method('validateRegistration')->willReturn([]);

        $userModel = $this->createMock(UserModelInterface::class);
        $userModel->method('findOneByUsername')->willReturn(false);
        $userModel->method('findOneByEmail')->willReturn(false);
        $userModel->method('createUser')->willReturn(123);

        $tokenModel = $this->createMock(UserTokenModelInterface::class);
        $tokenModel->method('createConfirmationToken')->willReturn(true);

        $sql = $this->createMock(SqlHelperInterface::class);
        $sql->expects(self::once())->method('beginTransaction');
        $sql->expects(self::once())->method('commit');
        $sql->expects(self::never())->method('rollBack');

        $tokenGen = $this->createMock(TokenGeneratorInterface::class);
        $tokenGen->method('generateUrlSafeToken')->with(32)->willReturn('clear-token');
        $tokenGen->method('hashToken')->with('clear-token')->willReturn(str_repeat('a', 32)); // longueur 32 OK

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')->willReturn(true);

        $throttle = $this->createMock(RegistrationThrottleServiceInterface::class);
        $throttle->method('checkQuota')->willReturn(['allowed' => true, 'reason' => null]);
        $throttle->expects(self::once())
            ->method('recordSuccess')
            ->with(
                email: 'bob@example.com',
                userId: 123,
                ip: '1.2.3.4',
                userAgent: 'PHPUnit'
            )
            ->willReturn(true);

        $service = $this->makeService(
            validator: $validator,
            userModel: $userModel,
            tokenModel: $tokenModel,
            sqlHelper: $sql,
            tokenGen: $tokenGen,
            mailer: $mailer,
            throttle: $throttle
        );

        $res = $service->register([
            'username'         => 'bob',
            'email'            => 'bob@example.com',
            'password'         => 'SomeStrong#Password1',
            'confirm_password' => 'SomeStrong#Password1',
        ]);

        self::assertSame(['ok' => true], $res);
    }

    private function makeService(
        ?FormValidatorInterface $validator = null,
        ?UserModelInterface $userModel = null,
        ?UserTokenModelInterface $tokenModel = null,
        ?SqlHelperInterface $sqlHelper = null,
        ?TokenGeneratorInterface $tokenGen = null,
        ?MailerInterface $mailer = null,
        ?RegistrationThrottleServiceInterface $throttle = null,
        ?PasswordBlacklist $passwordBlacklist = null,
        ?DisposableEmailChecker $disposableEmailChecker = null,
    ): RegistrationService {

        if ($validator === null) {
            /** @var FormValidatorInterface&MockObject $validator */
            $validator = $this->createMock(FormValidatorInterface::class);
            $validator->method('validateRegistration')->willReturn([]);
        }

        if ($userModel === null) {
            /** @var UserModelInterface&MockObject $userModel */
            $userModel = $this->createMock(UserModelInterface::class);
            $userModel->method('findOneByUsername')->willReturn(false);
            $userModel->method('findOneByEmail')->willReturn(false);
        }

        if ($tokenModel === null) {
            /** @var UserTokenModelInterface&MockObject $tokenModel */
            $tokenModel = $this->createMock(UserTokenModelInterface::class);
        }

        if ($sqlHelper === null) {
            /** @var SqlHelperInterface&MockObject $sqlHelper */
            $sqlHelper = $this->createMock(SqlHelperInterface::class);
        }

        if ($tokenGen === null) {
            /** @var TokenGeneratorInterface&MockObject $tokenGen */
            $tokenGen = $this->createMock(TokenGeneratorInterface::class);
            $tokenGen->method('generateUrlSafeToken')->willReturn('clear-token');
            $tokenGen->method('hashToken')->willReturn(str_repeat('a', 32));
        }

        if ($mailer === null) {
            /** @var MailerInterface&MockObject $mailer */
            $mailer = $this->createMock(MailerInterface::class);
            $mailer->method('send')->willReturn(true);
        }

        if ($throttle === null) {
            /** @var RegistrationThrottleServiceInterface&MockObject $throttle */
            $throttle = $this->createMock(RegistrationThrottleServiceInterface::class);
            $throttle->method('checkQuota')->willReturn(['allowed' => true, 'reason' => null]);
        }

        $passwordBlacklist      ??= new PasswordBlacklist([]);
        $disposableEmailChecker ??= new DisposableEmailChecker([]);

        return new RegistrationService(
            $validator,
            $userModel,
            $tokenModel,
            new Slugify(),
            $mailer,
            $tokenGen,
            $sqlHelper,
            $throttle,
            $passwordBlacklist,
            $disposableEmailChecker
        );
    }

    public function testReturnsTechnicalErrorWhenTokenHashLengthIsInvalid(): void
    {
        $validator = $this->createMock(\App\Validation\Contract\FormValidatorInterface::class);
        $validator->method('validateRegistration')->willReturn([]);

        $tokenGen = $this->createMock(\App\Security\Contract\TokenGeneratorInterface::class);
        $tokenGen->method('generateUrlSafeToken')->willReturn('clear-token');
        $tokenGen->method('hashToken')->willReturn('too-short'); // != 32 => art null => technical error

        $service = $this->makeService(
            validator: $validator,
            tokenGen: $tokenGen
        );

        $res = $service->register([
            'username'         => 'bob',
            'email'            => 'bob@example.com',
            'password'         => 'SomeStrong#Password1',
            'confirm_password' => 'SomeStrong#Password1',
        ]);

        self::assertSame([\App\Core\ErrorCode::AUTH_TECHNICAL_ERROR], $res['errors']);
        self::assertSame(['username' => 'bob', 'email' => 'bob@example.com'], $res['old']);
    }

    public function testReturnsTechnicalErrorWhenUserCreationFails(): void
    {
        $validator = $this->createMock(\App\Validation\Contract\FormValidatorInterface::class);
        $validator->method('validateRegistration')->willReturn([]);

        $userModel = $this->createMock(\App\Model\Contract\UserModelInterface::class);
        $userModel->method('findOneByUsername')->willReturn(false);
        $userModel->method('findOneByEmail')->willReturn(false);
        $userModel->method('createUser')->willReturn(0);

        $sql = $this->createMock(\App\Core\Contract\SqlHelperInterface::class);
        $sql->expects(self::once())->method('beginTransaction');
        $sql->expects(self::never())->method('commit');
        $sql->expects(self::once())->method('rollBack');

        $service = $this->makeService(
            validator: $validator,
            userModel: $userModel,
            sqlHelper: $sql
        );

        $res = $service->register([
            'username'         => 'bob',
            'email'            => 'bob@example.com',
            'password'         => 'SomeStrong#Password1',
            'confirm_password' => 'SomeStrong#Password1',
        ]);

        self::assertSame([\App\Core\ErrorCode::AUTH_TECHNICAL_ERROR], $res['errors']);
    }

    public function testReturnsTechnicalErrorWhenTokenInsertFails(): void
    {
        $validator = $this->createMock(\App\Validation\Contract\FormValidatorInterface::class);
        $validator->method('validateRegistration')->willReturn([]);

        $userModel = $this->createMock(\App\Model\Contract\UserModelInterface::class);
        $userModel->method('findOneByUsername')->willReturn(false);
        $userModel->method('findOneByEmail')->willReturn(false);
        $userModel->method('createUser')->willReturn(123);

        $tokenModel = $this->createMock(\App\Model\Contract\UserTokenModelInterface::class);
        $tokenModel->method('createConfirmationToken')->willReturn(false);

        $sql = $this->createMock(\App\Core\Contract\SqlHelperInterface::class);
        $sql->expects(self::once())->method('beginTransaction');
        $sql->expects(self::never())->method('commit');
        $sql->expects(self::once())->method('rollBack');

        $service = $this->makeService(
            validator: $validator,
            userModel: $userModel,
            tokenModel: $tokenModel,
            sqlHelper: $sql
        );

        $res = $service->register([
            'username'         => 'bob',
            'email'            => 'bob@example.com',
            'password'         => 'SomeStrong#Password1',
            'confirm_password' => 'SomeStrong#Password1',
        ]);

        self::assertSame([\App\Core\ErrorCode::AUTH_TECHNICAL_ERROR], $res['errors']);
    }

    public function testReturnsSendFailedWhenMailerThrows(): void
    {
        $validator = $this->createMock(\App\Validation\Contract\FormValidatorInterface::class);
        $validator->method('validateRegistration')->willReturn([]);

        $userModel = $this->createMock(\App\Model\Contract\UserModelInterface::class);
        $userModel->method('findOneByUsername')->willReturn(false);
        $userModel->method('findOneByEmail')->willReturn(false);
        $userModel->method('createUser')->willReturn(123);

        $tokenModel = $this->createMock(\App\Model\Contract\UserTokenModelInterface::class);
        $tokenModel->method('createConfirmationToken')->willReturn(true);

        $sql = $this->createMock(\App\Core\Contract\SqlHelperInterface::class);
        $sql->method('beginTransaction');
        $sql->method('commit');

        $mailer = $this->createMock(\App\Core\Mail\MailerInterface::class);
        $mailer->method('send')->willThrowException(new \RuntimeException('mail down'));

        $service = $this->makeService(
            validator: $validator,
            userModel: $userModel,
            tokenModel: $tokenModel,
            sqlHelper: $sql,
            mailer: $mailer
        );

        $res = $service->register([
            'username'         => 'bob',
            'email'            => 'bob@example.com',
            'password'         => 'SomeStrong#Password1',
            'confirm_password' => 'SomeStrong#Password1',
        ]);

        self::assertSame([\App\Core\ErrorCode::AUTH_CONFIRM_EMAIL_SEND_FAILED], $res['errors']);
    }

    public function testReturnsSendFailedWhenMailerReturnsFalse(): void
    {
        $validator = $this->createMock(\App\Validation\Contract\FormValidatorInterface::class);
        $validator->method('validateRegistration')->willReturn([]);

        $userModel = $this->createMock(\App\Model\Contract\UserModelInterface::class);
        $userModel->method('findOneByUsername')->willReturn(false);
        $userModel->method('findOneByEmail')->willReturn(false);
        $userModel->method('createUser')->willReturn(123);

        $tokenModel = $this->createMock(\App\Model\Contract\UserTokenModelInterface::class);
        $tokenModel->method('createConfirmationToken')->willReturn(true);

        $sql = $this->createMock(\App\Core\Contract\SqlHelperInterface::class);
        $sql->method('beginTransaction');
        $sql->method('commit');

        $mailer = $this->createMock(\App\Core\Mail\MailerInterface::class);
        $mailer->method('send')->willReturn(false);

        $service = $this->makeService(
            validator: $validator,
            userModel: $userModel,
            tokenModel: $tokenModel,
            sqlHelper: $sql,
            mailer: $mailer
        );

        $res = $service->register([
            'username'         => 'bob',
            'email'            => 'bob@example.com',
            'password'         => 'SomeStrong#Password1',
            'confirm_password' => 'SomeStrong#Password1',
        ]);

        self::assertSame([\App\Core\ErrorCode::AUTH_CONFIRM_EMAIL_SEND_FAILED], $res['errors']);
    }

    private function pdoDuplicate(int $driverCode, string $message): \PDOException
    {
        $e = new \PDOException('duplicate');
        // SQLSTATE
        // PHPUnit/PHP permettent souvent de setCode via constructeur PDOException($msg, $code)
        // mais ici on reste simple : on met le code via reflection si nécessaire.
        // Dans la pratique, $e->getCode() renverra '0' si non set.
        // => on utilise le constructeur pour le SQLSTATE.
        $e = new \PDOException('duplicate', 0); // code "0" -> pas bon, on force autrement ci-dessous si besoin

        // Astuce fiable : créer avec code SQLSTATE en string
        $e = new \PDOException('duplicate', 0);
        // Beaucoup de drivers exposent SQLSTATE dans ->code ; chez vous, la méthode lit getCode().
        // Si votre PHP refuse le string, on contourne en laissant getCode() à '23000' via Reflection.
        $ref = new \ReflectionProperty(\Exception::class, 'code');
        $ref->setAccessible(true);
        $ref->setValue($e, '23000');

        $e->errorInfo = ['23000', $driverCode, $message];
        return $e;
    }

    public function testCatchesPdoDuplicateEmail(): void
    {
        $validator = $this->createMock(\App\Validation\Contract\FormValidatorInterface::class);
        $validator->method('validateRegistration')->willReturn([]);

        $userModel = $this->createMock(\App\Model\Contract\UserModelInterface::class);
        $userModel->method('findOneByUsername')->willReturn(false);
        $userModel->method('findOneByEmail')->willReturn(false);
        $userModel->method('createUser')->willThrowException(
            $this->pdoWithSqlState('23000', 1062, "Duplicate entry 'x' for key 'uniq_email'")
        );


        $service = $this->makeService(
            validator: $validator,
            userModel: $userModel
        );

        $res = $service->register([
            'username'         => 'bob',
            'email'            => 'bob@example.com',
            'password'         => 'SomeStrong#Password1',
            'confirm_password' => 'SomeStrong#Password1',
        ]);

        self::assertSame(
            [\App\Core\ErrorCode::AUTH_TECHNICAL_ERROR],
            $res['errors']
        );
    }

    public function testCatchesPdoDuplicateUsername(): void
    {
        $validator = $this->createMock(\App\Validation\Contract\FormValidatorInterface::class);
        $validator->method('validateRegistration')->willReturn([]);

        $userModel = $this->createMock(\App\Model\Contract\UserModelInterface::class);
        $userModel->method('findOneByUsername')->willReturn(false);
        $userModel->method('findOneByEmail')->willReturn(false);
        $userModel->method('createUser')->willThrowException(
            $this->pdoWithSqlState('23000', 1062, "Duplicate entry 'x' for key 'uniq_username'")
        );


        $service = $this->makeService(
            validator: $validator,
            userModel: $userModel
        );

        $res = $service->register([
            'username'         => 'bob',
            'email'            => 'bob@example.com',
            'password'         => 'SomeStrong#Password1',
            'confirm_password' => 'SomeStrong#Password1',
        ]);

        self::assertSame(
            [\App\Core\ErrorCode::AUTH_TECHNICAL_ERROR],
            $res['errors']
        );
    }

    public function testCatchesPdoDuplicateUnknownIndex(): void
    {
        $validator = $this->createMock(\App\Validation\Contract\FormValidatorInterface::class);
        $validator->method('validateRegistration')->willReturn([]);

        $userModel = $this->createMock(\App\Model\Contract\UserModelInterface::class);
        $userModel->method('findOneByUsername')->willReturn(false);
        $userModel->method('findOneByEmail')->willReturn(false);
        $userModel->method('createUser')->willThrowException(
            $this->pdoWithSqlState('23000', 1062, "Duplicate entry 'x' for key 'some_weird_index'")
        );


        $service = $this->makeService(
            validator: $validator,
            userModel: $userModel
        );

        $res = $service->register([
            'username'         => 'bob',
            'email'            => 'bob@example.com',
            'password'         => 'SomeStrong#Password1',
            'confirm_password' => 'SomeStrong#Password1',
        ]);

        self::assertSame([\App\Core\ErrorCode::AUTH_TECHNICAL_ERROR], $res['errors']);
    }

    public function testCatchesPdoOtherErrorAsTechnicalError(): void
    {
        $validator = $this->createMock(\App\Validation\Contract\FormValidatorInterface::class);
        $validator->method('validateRegistration')->willReturn([]);

        $e   = new \PDOException('sql error');
        $ref = new \ReflectionProperty(\Exception::class, 'code');
        $ref->setAccessible(true);
        $ref->setValue($e, '42000'); // autre SQLSTATE
        $e->errorInfo = ['42000', 9999, 'syntax error'];

        $userModel = $this->createMock(\App\Model\Contract\UserModelInterface::class);
        $userModel->method('findOneByUsername')->willReturn(false);
        $userModel->method('findOneByEmail')->willReturn(false);
        $userModel->method('createUser')->willThrowException($e);

        $service = $this->makeService(
            validator: $validator,
            userModel: $userModel
        );

        $res = $service->register([
            'username'         => 'bob',
            'email'            => 'bob@example.com',
            'password'         => 'SomeStrong#Password1',
            'confirm_password' => 'SomeStrong#Password1',
        ]);

        self::assertSame([\App\Core\ErrorCode::AUTH_TECHNICAL_ERROR], $res['errors']);
    }

    private function pdoWithSqlState(string $sqlState, ?int $driverCode, string $message): \PDOException
    {
        $e = new \PDOException('pdo');

        // Forcer getCode() à retourner une string SQLSTATE (comportement typique PDO)
        $ref = new \ReflectionProperty(\Exception::class, 'code');
        $ref->setAccessible(true);
        $ref->setValue($e, $sqlState);

        // errorInfo peut être null, ou un array
        $e->errorInfo = [$sqlState, $driverCode, $message];

        return $e;
    }

    /**
     * Appelle une méthode privée de RegistrationService via Reflection.
     * @return mixed
     */
    private function callPrivate(RegistrationService $service, string $method, array $args): mixed
    {
        $rm = new \ReflectionMethod($service, $method);
        $rm->setAccessible(true);
        return $rm->invokeArgs($service, $args);
    }


    public function testHandlePdoRegistrationExceptionDuplicateEmailViaReflection(): void
    {
        $service = $this->makeService();

        $pdo = $this->pdoWithSqlState('23000', 1062, "Duplicate entry 'x' for key 'uniq_email'");
        $old = ['username' => 'bob', 'email' => 'bob@example.com'];

        $res = $this->callPrivate($service, 'handlePdoRegistrationException', [
            $pdo,
            'auth',
            'bob@example.com',
            'bob',
            $old
        ]);

        self::assertSame(
            [ErrorCode::AUTH_EMAIL_EXISTS, ErrorCode::AUTH_PASSWORD_REENTER],
            $res['errors']
        );
        self::assertSame($old, $res['old']);
    }

    public function testHandlePdoRegistrationExceptionDuplicateUsernameViaReflection(): void
    {
        $service = $this->makeService();

        $pdo = $this->pdoWithSqlState('23000', 1062, "Duplicate entry 'x' for key 'uniq_username'");
        $old = ['username' => 'bob', 'email' => 'bob@example.com'];

        $res = $this->callPrivate($service, 'handlePdoRegistrationException', [
            $pdo,
            'auth',
            'bob@example.com',
            'bob',
            $old
        ]);

        self::assertSame(
            [ErrorCode::AUTH_USERNAME_EXISTS, ErrorCode::AUTH_PASSWORD_REENTER],
            $res['errors']
        );
        self::assertSame($old, $res['old']);
    }

    public function testHandlePdoRegistrationExceptionDuplicateUnknownIndexViaReflection(): void
    {
        $service = $this->makeService();

        $pdo = $this->pdoWithSqlState('23000', 1062, "Duplicate entry 'x' for key 'some_weird_index'");
        $old = ['username' => 'bob', 'email' => 'bob@example.com'];

        $res = $this->callPrivate($service, 'handlePdoRegistrationException', [
            $pdo,
            'auth',
            'bob@example.com',
            'bob',
            $old
        ]);

        self::assertSame([ErrorCode::AUTH_REGISTRATION_FAILED], $res['errors']);
        self::assertSame($old, $res['old']);
    }

    public function testHandlePdoRegistrationExceptionOtherPdoErrorViaReflection(): void
    {
        $service = $this->makeService();

        $pdo = $this->pdoWithSqlState('42000', 9999, 'syntax error');
        $old = ['username' => 'bob', 'email' => 'bob@example.com'];

        $res = $this->callPrivate($service, 'handlePdoRegistrationException', [
            $pdo,
            'auth',
            'bob@example.com',
            'bob',
            $old
        ]);

        self::assertSame([ErrorCode::AUTH_TECHNICAL_ERROR], $res['errors']);
        self::assertSame($old, $res['old']);
    }

    public function testRegisterCatchesPdoExceptionAndBuildsSafeOld(): void
    {
        $pdo = $this->pdoWithSqlState('23000', 1062, "Duplicate entry 'x' for key 'uniq_email'");

        $validator = $this->createMock(FormValidatorInterface::class);
        $validator->method('validateRegistration')->willThrowException($pdo);

        $service = $this->makeService(validator: $validator);

        $res = $service->register([
        'username' => 'bob',
        'email'    => 'bob@example.com',
        // peu importe les autres champs ici
        ]);

        self::assertSame(
            [ErrorCode::AUTH_EMAIL_EXISTS, ErrorCode::AUTH_PASSWORD_REENTER],
            $res['errors']
        );
        self::assertSame(['username' => 'bob', 'email' => 'bob@example.com'], $res['old']);
    }

    public function testRegisterCatchesThrowableAndBuildsSafeOldWithNonStringValues(): void
    {
        $validator = $this->createMock(FormValidatorInterface::class);
        $validator->method('validateRegistration')->willThrowException(new \RuntimeException('boom'));

        $service = $this->makeService(validator: $validator);

        $res = $service->register([
        'username' => ['not-a-string'],
        'email'    => null,
        ]);

        self::assertSame([ErrorCode::AUTH_TECHNICAL_ERROR], $res['errors']);
        self::assertSame(['username' => '', 'email' => ''], $res['old']);
    }
}
