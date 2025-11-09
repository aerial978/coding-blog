<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Core\ErrorCode;
use App\Core\Mail\MailerInterface;
use App\Core\SqlHelper;
use App\Model\Entity\UserEntity;
use App\Model\UserModel;
use App\Model\UserTokenModel;
use App\Security\Contract\TokenGeneratorInterface;
use App\Service\Security\RegistrationService;
use App\Validation\FormValidator;
use Cocur\Slugify\Slugify;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class RegistrationServiceTest extends TestCase
{
    private FormValidator $validator;
    // réel
    /** @var UserModel&MockObject */
    private $users;
    /** @var UserTokenModel&MockObject */
    private $tokens;
    private Slugify $slugify;
    // réel
    /** @var MailerInterface&MockObject */
    private $mailer;
    /** @var TokenGeneratorInterface&MockObject */
    private $tokensGen;
    /** @var SqlHelper&MockObject */
    private $sql;
    protected function setUp(): void
    {
        $this->validator = new FormValidator();
        // class finale → on garde réel
        $this->users     = $this->createMock(UserModel::class);
        $this->tokens    = $this->createMock(UserTokenModel::class);
        $this->slugify   = new Slugify();
        // réel
        $this->mailer    = $this->createMock(MailerInterface::class);
        $this->tokensGen = $this->createMock(TokenGeneratorInterface::class);
        $this->sql       = $this->createMock(SqlHelper::class);
    }

    private function make(): RegistrationService
    {
        return new RegistrationService($this->validator, $this->users, $this->tokens, $this->slugify, $this->mailer, $this->tokensGen, $this->sql);
    }

    private function validForm(): array
    {
        return [
            'username'          => 'John Doe',
            'email'             => 'john@test.com',
            'password'          => 'Passw0rd!',
            'confirm_password'  => 'Passw0rd!',
        ];
    }

    private function arrangeNoConflictsAndValidArtifacts(): void
    {
        $this->users->method('findOneByUsername')->willReturn(null);
        $this->users->method('findOneByEmail')->willReturn(null);
        $this->tokensGen->method('generateUrlSafeToken')->willReturn('tok');
        // 32 chars to pass length check
        $this->tokensGen->method('hashToken')->willReturn(str_repeat('a', 32));
    }

    // --------------------------
    // 1) Succès nominal
    // --------------------------
    public function test_register_success_returns_ok_true(): void
    {
        $this->arrangeNoConflictsAndValidArtifacts();
        $this->sql->expects($this->once())->method('beginTransaction');
        $this->users
            ->expects($this->once())
            ->method('createUser')
            ->with($this->isInstanceOf(UserEntity::class))
            ->willReturn(123);
        $this->tokens
            ->expects($this->once())
            ->method('createConfirmationToken')
            ->with(123, $this->isType('string'), $this->isInstanceOf(\DateTimeImmutable::class))
            ->willReturn(true);
        $this->sql->expects($this->once())->method('commit');
        $this->mailer->expects($this->once())->method('send')->willReturn(true);
        $svc = $this->make();
        $res = $svc->register($this->validForm());
        $this->assertSame(['ok' => true], $res);
    }

    // --------------------------
    // 2) Artefacts invalides (hash ≠ 32) → erreur technique
    // --------------------------
    public function test_register_invalid_hash_length_returns_technical_error(): void
    {
        $this->users->method('findOneByUsername')->willReturn(null);
        $this->users->method('findOneByEmail')->willReturn(null);
        $this->tokensGen->method('generateUrlSafeToken')->willReturn('tok');
        $this->tokensGen->method('hashToken')->willReturn('short');
        // != 32

        $svc = $this->make();
        $res = $svc->register($this->validForm());
        $this->assertSame([ErrorCode::AUTH_TECHNICAL_ERROR], $res['errors']);
        $this->assertSame(['username' => 'John Doe', 'email' => 'john@test.com'], $res['old']);
    }

    // --------------------------
    // 3) createUser() = 0 → rollback + erreur technique
    // --------------------------
    public function test_register_user_creation_zero_triggers_rollback_and_technical_error(): void
    {
        $this->arrangeNoConflictsAndValidArtifacts();
        $this->sql->expects($this->once())->method('beginTransaction');
        $this->users->method('createUser')->willReturn(0);
        $this->sql->expects($this->once())->method('rollBack');
        $svc = $this->make();
        $res = $svc->register($this->validForm());
        $this->assertSame([ErrorCode::AUTH_TECHNICAL_ERROR], $res['errors']);
    }

    // --------------------------
    // 4) createConfirmationToken() = false → rollback + erreur technique
    // --------------------------
    public function test_register_token_creation_failure_triggers_rollback_and_technical_error(): void
    {
        $this->arrangeNoConflictsAndValidArtifacts();
        $this->sql->expects($this->once())->method('beginTransaction');
        $this->users->method('createUser')->willReturn(10);
        $this->tokens
            ->method('createConfirmationToken')
            ->willReturn(false);
        $this->sql->expects($this->once())->method('rollBack');
        $svc = $this->make();
        $res = $svc->register($this->validForm());
        $this->assertSame([ErrorCode::AUTH_TECHNICAL_ERROR], $res['errors']);
    }

    // --------------------------
    // 5) Mailer → false → send_failed
    // --------------------------
    public function test_register_mailer_returns_false_yields_send_failed(): void
    {
        $this->arrangeNoConflictsAndValidArtifacts();
        $this->sql->method('beginTransaction');
        $this->users->method('createUser')->willReturn(10);
        $this->tokens->method('createConfirmationToken')->willReturn(true);
        $this->sql->method('commit');
        $this->mailer->method('send')->willReturn(false);
        $svc = $this->make();
        $res = $svc->register($this->validForm());
        $this->assertSame([ErrorCode::AUTH_CONFIRM_EMAIL_SEND_FAILED], $res['errors']);
    }

    // --------------------------
    // 6) Mailer → exception → send_failed
    // --------------------------
    public function test_register_mailer_throws_exception_yields_send_failed(): void
    {
        $this->arrangeNoConflictsAndValidArtifacts();
        $this->sql->method('beginTransaction');
        $this->users->method('createUser')->willReturn(10);
        $this->tokens->method('createConfirmationToken')->willReturn(true);
        $this->sql->method('commit');
        $this->mailer->method('send')->willThrowException(new \RuntimeException('smtp down'));
        $svc = $this->make();
        $res = $svc->register($this->validForm());
        $this->assertSame([ErrorCode::AUTH_CONFIRM_EMAIL_SEND_FAILED], $res['errors']);
    }

    // --------------------------
    // 7) (Optionnel) PDO duplicate email → mapping spécifique
    // --------------------------
    public function test_register_pdo_duplicate_email_maps_to_specific_errors(): void
    {
        // Le validateur doit passer, on déclenche l’exception au moment de la détection de collision email
        $this->users->method('findOneByUsername')->willReturn(null);
        // Construction correcte de la PDOException
        // → le code doit être un entier (23000, pas '23000')
        $e = new \PDOException('Duplicate entry for key unique_email', 23000);
        // Le champ errorInfo simule une erreur MySQL de type "duplicate entry"
        $e->errorInfo = ['23000', 1062, 'Duplicate entry ... for key unique_email'];
        // Simule une exception PDO lors de la vérification de l’email
        $this->users->method('findOneByEmail')->willThrowException($e);
        $svc = $this->make();
        $res = $svc->register($this->validForm());
        // On attend bien le mapping spécifique défini dans handlePdoRegistrationException()
        $this->assertSame([ErrorCode::AUTH_EMAIL_EXISTS, ErrorCode::AUTH_PASSWORD_REENTER], $res['errors']);
        $this->assertSame(['username' => 'John Doe', 'email' => 'john@test.com'], $res['old']);
    }

    // --------------------------
    // 8) Collision username / email → erreurs spécifiques
    // --------------------------
    public function test_register_conflict_username_and_email(): void
    {
        $form = [
            'username'          => 'john',
            'email'             => 'john@test.com',
            'password'          => 'Passw0rd!',
            'confirm_password'  => 'Passw0rd!',
        ];
        // Simule la détection d’un username existant
        $this->users->method('findOneByUsername')
            ->with('john')
            ->willReturn($this->createMock(\App\Model\Entity\UserEntity::class));
        // Simule aussi une collision email
        $this->users->method('findOneByEmail')
            ->with('john@test.com')
            ->willReturn($this->createMock(\App\Model\Entity\UserEntity::class));
        $svc = $this->make();
        $res = $svc->register($form);
        $this->assertArrayHasKey('errors', $res);
        $this->assertContains(ErrorCode::AUTH_EMAIL_EXISTS, $res['errors']);
        $this->assertContains(ErrorCode::AUTH_PASSWORD_REENTER, $res['errors']);
    }

    // 9) PDO duplicate *username*  → mapping spécifique
    public function test_register_pdo_duplicate_username_maps_to_specific_errors(): void
    {
        // Le validateur doit passer
        $this->users->method('findOneByEmail')->willReturn(null);
        // Exception SQLSTATE 23000 / driver 1062 avec "username"
        $e            = new \PDOException('Duplicate entry ... for key unique_username', 23000);
        $e->errorInfo = ['23000', 1062, 'Duplicate entry ... for key unique_username'];
        // Déclenchée lors de la vérif du username
        $this->users->method('findOneByUsername')->willThrowException($e);
        $svc = $this->make();
        $res = $svc->register($this->validForm());
        $this->assertSame([ErrorCode::AUTH_USERNAME_EXISTS, ErrorCode::AUTH_PASSWORD_REENTER], $res['errors']);
        $this->assertSame(['username' => 'John Doe', 'email' => 'john@test.com'], $res['old']);
    }

    // 10) PDO duplicate ambigu (ni "email" ni "username") → AUTH_REGISTRATION_FAILED
    public function test_register_pdo_duplicate_ambiguous_maps_to_registration_failed(): void
    {
        $this->users->method('findOneByUsername')->willReturn(null);
        // Toujours 23000/1062 mais message qui ne mentionne ni email ni username
        $e            = new \PDOException('Duplicate entry ... for key some_other_unique', 23000);
        $e->errorInfo = ['23000', 1062, 'Duplicate entry ... for key some_other_unique'];
        $this->users->method('findOneByEmail')->willThrowException($e);
        $svc = $this->make();
        $res = $svc->register($this->validForm());
        $this->assertSame([ErrorCode::AUTH_REGISTRATION_FAILED], $res['errors']);
        $this->assertSame(['username' => 'John Doe', 'email' => 'john@test.com'], $res['old']);
    }

    // 11) Filet global \Throwable (ex: génération de token) → AUTH_TECHNICAL_ERROR
    public function test_register_global_throwable_returns_technical_error(): void
    {
        // Aucune collision, validation OK
        $this->users->method('findOneByUsername')->willReturn(null);
        $this->users->method('findOneByEmail')->willReturn(null);
        // Provoque une exception *avant* toute transaction
        $this->tokensGen->method('generateUrlSafeToken')
            ->willThrowException(new \RuntimeException('boom'));
        $svc = $this->make();
        $res = $svc->register($this->validForm());
        $this->assertSame([ErrorCode::AUTH_TECHNICAL_ERROR], $res['errors']);
        $this->assertSame(['username' => 'John Doe', 'email' => 'john@test.com'], $res['old']);
    }
}
