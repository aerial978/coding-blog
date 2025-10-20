<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Core\Mail\MailerInterface;
use App\Core\SqlHelper;
use App\Model\Entity\UserEntity;
use App\Model\UserModel;
use App\Model\UserTokenModel;
use App\Security\Contract\TokenGeneratorInterface;
use App\Security\TokenGenerator;
use App\Service\SecurityService;
use App\Validation\FormValidator;
use Cocur\Slugify\Slugify;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(SecurityService::class)]
final class SecurityServiceTest extends TestCase
{
    private FormValidator $validator;          // réel
    private TokenGenerator $tokenGen;          // réel

    /** @var UserModel&MockObject */
    private UserModel $userModel;
    /** @var UserTokenModel&MockObject */
    private UserTokenModel $userTokenModel;
    /** @var Slugify&MockObject */
    private Slugify $slugify;
    /** @var MailerInterface&MockObject */
    private MailerInterface $mailer;
    /** @var SqlHelper&MockObject */
    private SqlHelper $sqlHelper;

    private SecurityService $service;

    protected function setUp(): void
    {
        $this->validator = new FormValidator();   // pas de mock
        $this->tokenGen  = new TokenGenerator();  // pas de mock

        $this->userModel      = $this->createMock(UserModel::class);
        $this->userTokenModel = $this->createMock(UserTokenModel::class);
        $this->slugify        = $this->createMock(Slugify::class);
        $this->mailer         = $this->createMock(MailerInterface::class);
        $this->sqlHelper      = $this->createMock(SqlHelper::class);

        $this->service = new SecurityService(
            $this->validator,
            $this->userModel,
            $this->userTokenModel,
            $this->slugify,
            $this->mailer,
            $this->tokenGen,
            $this->sqlHelper
        );
    }

    /**
     * @param array<string,mixed> $result
     * @return list<mixed>
     */
    private function errorsOf(array $result): array
    {
        $e = $result['errors'] ?? [];
        if (!\is_array($e)) {
            return [];
        }
        /** @var list<mixed> $list */
        $list = \array_values($e); // reindexe pour garantir des clés 0..n-1
        return $list;
    }

    #[Test]
    public function register_returns_ok_true_on_success(): void
    {
        $form = [
            'username'         => 'JohnDoe',
            'email'            => 'john@example.test',
            'password'         => 'StrongP@ssw0rd!',
            'confirm_password' => 'StrongP@ssw0rd!',
        ];

        $this->userModel->method('findOneByUsername')->with('JohnDoe')->willReturn(null);
        $this->userModel->method('findOneByEmail')->with('john@example.test')->willReturn(null);
        $this->slugify->method('slugify')->with('JohnDoe')->willReturn('johndoe');

        $this->sqlHelper->expects($this->once())->method('beginTransaction');
        $this->userModel->method('createUser')->with($this->isInstanceOf(UserEntity::class))->willReturn(42);

        $this->userTokenModel->method('createConfirmationToken')
            ->with(
                42,
                $this->callback(fn ($h) => is_string($h) && strlen($h) === 32),
                $this->isInstanceOf(\DateTimeImmutable::class)
            )
            ->willReturn(true);

        $this->sqlHelper->expects($this->once())->method('commit');

        $this->mailer->method('send')->with(
            'john@example.test',
            'JohnDoe',
            'Confirmation de votre compte',
            'confirmation.html',
            $this->callback(function (array $vars): bool {
                $link = $vars['link'] ?? '';
                if (!is_string($link)) {
                    return false;
                }
                if (!str_contains($link, '/confirm-account?token=')) {
                    return false;
                }
                $parts = parse_url($link);
                parse_str($parts['query'] ?? '', $qs);
                return isset($qs['token']) && is_string($qs['token']) && $qs['token'] !== '';
            })
        )->willReturn(true);

        $result = $this->service->register($form);
        self::assertSame(['ok' => true], $result);
    }

    #[Test]
    public function register_returns_errors_when_validation_fails(): void
    {
        $form = [
            'username'         => 'JD',        // invalide selon vos règles
            'email'            => 'bad-email',
            'password'         => 'x',
            'confirm_password' => 'y',
        ];

        $result = $this->service->register($form);

        self::assertArrayHasKey('errors', $result);
        self::assertSame(['username' => 'JD', 'email' => 'bad-email'], $result['old']);
    }

    #[Test]
    public function register_returns_error_when_username_already_exists(): void
    {
        $form = [
            'username'         => 'ExistingUser',
            'email'            => 'new@example.test',
            'password'         => 'ValidP@ssword123!',
            'confirm_password' => 'ValidP@ssword123!',
        ];

        // Simuler un utilisateur déjà existant pour ce username
        $this->userModel->method('findOneByUsername')->with('ExistingUser')->willReturn(new UserEntity());
        $this->userModel->method('findOneByEmail')->with('new@example.test')->willReturn(null);

        $result = $this->service->register($form);

        self::assertArrayHasKey('errors', $result);
        self::assertContains(\App\Core\ErrorCode::AUTH_USERNAME_EXISTS, $this->errorsOf($result));
        self::assertSame(['username' => 'ExistingUser', 'email' => 'new@example.test'], $result['old']);
    }

    #[Test]
    public function register_returns_error_when_email_already_exists(): void
    {
        $form = [
            'username'         => 'NewUser',
            'email'            => 'existing@example.test',
            'password'         => 'ValidP@ssword123!',
            'confirm_password' => 'ValidP@ssword123!',
        ];

        // Simuler un utilisateur déjà existant pour cet email
        $this->userModel->method('findOneByUsername')->with('NewUser')->willReturn(null);
        $this->userModel->method('findOneByEmail')->with('existing@example.test')->willReturn(new UserEntity());

        $result = $this->service->register($form);

        self::assertArrayHasKey('errors', $result);
        self::assertContains(\App\Core\ErrorCode::AUTH_EMAIL_EXISTS, $this->errorsOf($result));
        self::assertSame(['username' => 'NewUser', 'email' => 'existing@example.test'], $result['old']);
    }

    #[Test]
    public function register_returns_error_when_user_creation_fails(): void
    {
        $form = [
            'username'         => 'TinyPerson123',                 // sans espace, alphanumérique
            'email'            => 'tiny.person+test@example.test', // email canonique valide
            'password'         => 'VeryStrongP@ssw0rd123!',        // long, avec chiffres/symboles/majuscules
            'confirm_password' => 'VeryStrongP@ssw0rd123!',
        ];

        $this->userModel->method('findOneByUsername')->with('TinyPerson123')->willReturn(null);
        $this->userModel->method('findOneByEmail')->with('tiny.person+test@example.test')->willReturn(null);
        $this->slugify->method('slugify')->with('TinyPerson123')->willReturn('tinyperson123');

        // La validation passe -> on entre bien en transaction
        $this->sqlHelper->expects($this->once())->method('beginTransaction');

        // Forcer l’échec applicatif : createUser <= 0
        $this->userModel->method('createUser')->willReturn(0);

        // Pas d’attente spécifique sur commit/rollback ici (le code retourne directement une erreur)

        $result = $this->service->register($form);

        self::assertArrayHasKey('errors', $result);
        self::assertNotEmpty($result['errors']);
        self::assertSame(
            ['username' => 'TinyPerson123', 'email' => 'tiny.person+test@example.test'],
            $result['old']
        );
    }

    #[Test]
    public function register_rolls_back_when_token_insertion_fails(): void
    {
        $form = [
            'username'         => 'JaneSmith',
            'email'            => 'jane@example.test',
            'password'         => 'Password1234!',
            'confirm_password' => 'Password1234!',
        ];

        $this->userModel->method('findOneByUsername')->with('JaneSmith')->willReturn(null);
        $this->userModel->method('findOneByEmail')->with('jane@example.test')->willReturn(null);
        $this->slugify->method('slugify')->with('JaneSmith')->willReturn('janesmith');

        $this->sqlHelper->expects($this->once())->method('beginTransaction');
        $this->userModel->method('createUser')->willReturn(10);
        $this->userTokenModel->method('createConfirmationToken')->willReturn(false);
        $this->sqlHelper->expects($this->once())->method('rollBack');

        $result = $this->service->register($form);
        self::assertArrayHasKey('errors', $result);
        self::assertNotEmpty($result['errors']);
    }

    #[Test]
    public function register_rolls_back_and_returns_technical_error_on_exception_during_transaction(): void
    {
        $form = [
            'username'         => 'TxCrashUser',
            'email'            => 'tx.crash@example.test',
            'password'         => 'ValidP@ssword123!',
            'confirm_password' => 'ValidP@ssword123!',
        ];

        $this->userModel->method('findOneByUsername')->with('TxCrashUser')->willReturn(null);
        $this->userModel->method('findOneByEmail')->with('tx.crash@example.test')->willReturn(null);
        $this->slugify->method('slugify')->with('TxCrashUser')->willReturn('txcrashuser');

        $this->sqlHelper->expects($this->once())->method('beginTransaction');
        $this->userModel->method('createUser')->willReturn(456);

        // Crash au moment d'insérer le jeton
        $this->userTokenModel->method('createConfirmationToken')->willThrowException(new \RuntimeException('boom'));
        $this->sqlHelper->expects($this->once())->method('rollBack');

        $result = $this->service->register($form);

        self::assertArrayHasKey('errors', $result);
        self::assertContains(\App\Core\ErrorCode::AUTH_TECHNICAL_ERROR, $this->errorsOf($result));
        self::assertSame(['username' => 'TxCrashUser', 'email' => 'tx.crash@example.test'], $result['old']);
    }

    #[Test]
    public function register_returns_error_when_mailer_throws_exception(): void
    {
        $form = [
            'username'         => 'Alice',
            'email'            => 'alice@example.test',
            'password'         => 'S3cur3_P@ss',
            'confirm_password' => 'S3cur3_P@ss',
        ];

        $this->userModel->method('findOneByUsername')->with('Alice')->willReturn(null);
        $this->userModel->method('findOneByEmail')->with('alice@example.test')->willReturn(null);
        $this->slugify->method('slugify')->with('Alice')->willReturn('alice');

        $this->sqlHelper->method('beginTransaction');
        $this->userModel->method('createUser')->willReturn(7);
        $this->userTokenModel->method('createConfirmationToken')->willReturn(true);
        $this->sqlHelper->method('commit');

        $this->mailer->method('send')->willThrowException(new \RuntimeException('SMTP down'));

        $result = $this->service->register($form);
        self::assertArrayHasKey('errors', $result);
        self::assertNotEmpty($result['errors']);
    }

    #[Test]
    public function register_catches_mailer_throwable_and_returns_confirm_send_failed_with_old(): void
    {
        $form = [
        'username'         => 'AliceMailerBoom',
        'email'            => 'alice.boom@example.test',
        'password'         => 'S3cur3_P@ssw0rd!',
        'confirm_password' => 'S3cur3_P@ssw0rd!',
        ];

        // validation OK
        $this->userModel->method('findOneByUsername')->with('AliceMailerBoom')->willReturn(null);
        $this->userModel->method('findOneByEmail')->with('alice.boom@example.test')->willReturn(null);
        $this->slugify->method('slugify')->with('AliceMailerBoom')->willReturn('alicemailerboom');

        // enregistrement + jeton OK
        $this->sqlHelper->expects($this->once())->method('beginTransaction');
        $this->userModel->method('createUser')->willReturn(77);
        $this->userTokenModel->method('createConfirmationToken')
        ->with(
            77,
            $this->callback(fn ($h) => is_string($h) && strlen($h) === 32),
            $this->isInstanceOf(\DateTimeImmutable::class)
        )
        ->willReturn(true);
        $this->sqlHelper->expects($this->once())->method('commit');

        // ← on force l’exception du mailer pour couvrir le catch
        $this->mailer->expects($this->once())
        ->method('send')
        ->willThrowException(new \RuntimeException('SMTP down'));

        $res = $this->service->register($form);

        // on vérifie qu'on est bien passé par le catch (code + old)
        self::assertSame([\App\Core\ErrorCode::AUTH_CONFIRM_EMAIL_SEND_FAILED], $res['errors'] ?? null);
        self::assertSame(
            ['username' => 'AliceMailerBoom', 'email' => 'alice.boom@example.test'],
            $res['old'] ?? null
        );
    }


    #[Test]
    public function register_returns_error_when_mailer_returns_false(): void
    {
        $form = [
            'username'         => 'MailFailUser',
            'email'            => 'mail.fail@example.test',
            'password'         => 'ValidP@ssword123!',
            'confirm_password' => 'ValidP@ssword123!',
        ];

        $this->userModel->method('findOneByUsername')->with('MailFailUser')->willReturn(null);
        $this->userModel->method('findOneByEmail')->with('mail.fail@example.test')->willReturn(null);
        $this->slugify->method('slugify')->with('MailFailUser')->willReturn('mailfailuser');

        $this->sqlHelper->expects($this->once())->method('beginTransaction');
        $this->userModel->method('createUser')->willReturn(123);
        $this->userTokenModel->method('createConfirmationToken')
            ->with(
                123,
                $this->callback(fn ($h) => is_string($h) && strlen($h) === 32),
                $this->isInstanceOf(\DateTimeImmutable::class)
            )
            ->willReturn(true);
        $this->sqlHelper->expects($this->once())->method('commit');

        // Mailer renvoie false
        $this->mailer->method('send')->willReturn(false);

        $result = $this->service->register($form);

        self::assertArrayHasKey('errors', $result);
        self::assertContains(\App\Core\ErrorCode::AUTH_CONFIRM_EMAIL_SEND_FAILED, $this->errorsOf($result));
        self::assertSame(['username' => 'MailFailUser', 'email' => 'mail.fail@example.test'], $result['old']);
    }

    #[Test]
    public function register_mailer_returns_false_confirms_send_failed(): void
    {
        $form = [
            'username'         => 'MailFailUser',
            'email'            => 'mail.fail@example.test',
            'password'         => 'ValidP@ssword123!',
            'confirm_password' => 'ValidP@ssword123!',
        ];

        $this->userModel->method('findOneByUsername')->with('MailFailUser')->willReturn(null);
        $this->userModel->method('findOneByEmail')->with('mail.fail@example.test')->willReturn(null);
        $this->slugify->method('slugify')->with('MailFailUser')->willReturn('mailfailuser');

        $this->sqlHelper->expects($this->once())->method('beginTransaction');
        $this->userModel->method('createUser')->willReturn(123);
        $this->userTokenModel->method('createConfirmationToken')->willReturn(true);
        $this->sqlHelper->expects($this->once())->method('commit');

        $this->mailer->method('send')->willReturn(false);

        $result = $this->service->register($form);

        self::assertArrayHasKey('errors', $result);
        self::assertContains(\App\Core\ErrorCode::AUTH_CONFIRM_EMAIL_SEND_FAILED, $this->errorsOf($result));
    }

    #[Test]
    public function register_returns_email_exists_on_pdo_duplicate_email(): void
    {
        $form = [
            'username'         => 'DupOnEmail',
            'email'            => 'dup.email@example.test',
            'password'         => 'ValidP@ssword123!',
            'confirm_password' => 'ValidP@ssword123!',
        ];

        // username OK
        $this->userModel->method('findOneByUsername')->with('DupOnEmail')->willReturn(null);
        // Lève la PDOException AVANT la transaction
        $e = $this->createPdoDuplicate("Duplicate entry 'X' for key 'uniq_email'");
        $this->userModel->method('findOneByEmail')->with('dup.email@example.test')->willThrowException($e);

        // On ne doit pas démarrer de transaction
        $this->sqlHelper->expects($this->never())->method('beginTransaction');

        $result = $this->service->register($form);

        self::assertArrayHasKey('errors', $result);
        self::assertSame(
            [\App\Core\ErrorCode::AUTH_EMAIL_EXISTS, \App\Core\ErrorCode::AUTH_PASSWORD_REENTER],
            $result['errors']
        );
        self::assertSame(['username' => 'DupOnEmail', 'email' => 'dup.email@example.test'], $result['old']);
    }

    #[Test]
    public function register_returns_username_exists_on_pdo_duplicate_username(): void
    {
        $form = [
            'username'         => 'DupOnUsername',
            'email'            => 'dup.username@example.test',
            'password'         => 'ValidP@ssword123!',
            'confirm_password' => 'ValidP@ssword123!',
        ];

        // Lève la PDOException AVANT la transaction
        $e = $this->createPdoDuplicate("Duplicate entry 'X' for key 'uniq_username'");
        $this->userModel->method('findOneByUsername')->with('DupOnUsername')->willThrowException($e);

        // findOneByEmail ne sera pas appelée, mais on garde un stub "neutre" si besoin
        $this->userModel->method('findOneByEmail')->with('dup.username@example.test')->willReturn(null);

        $this->sqlHelper->expects($this->never())->method('beginTransaction');

        $result = $this->service->register($form);

        self::assertArrayHasKey('errors', $result);
        self::assertSame(
            [\App\Core\ErrorCode::AUTH_USERNAME_EXISTS, \App\Core\ErrorCode::AUTH_PASSWORD_REENTER],
            $result['errors']
        );
        self::assertSame(['username' => 'DupOnUsername', 'email' => 'dup.username@example.test'], $result['old']);
    }

    #[Test]
    public function register_returns_registration_failed_on_ambiguous_pdo_duplicate(): void
    {
        $form = [
            'username'         => 'DupUnknown',
            'email'            => 'dup.unknown@example.test',
            'password'         => 'ValidP@ssword123!',
            'confirm_password' => 'ValidP@ssword123!',
        ];

        // username OK
        $this->userModel->method('findOneByUsername')->with('DupUnknown')->willReturn(null);
        // Message sans 'email' ni 'username' -> branche générique
        $e = $this->createPdoDuplicate("Duplicate entry 'X' for key 'some_other_unique'");
        $this->userModel->method('findOneByEmail')->with('dup.unknown@example.test')->willThrowException($e);

        $this->sqlHelper->expects($this->never())->method('beginTransaction');

        $result = $this->service->register($form);

        self::assertArrayHasKey('errors', $result);
        self::assertSame([\App\Core\ErrorCode::AUTH_REGISTRATION_FAILED], $result['errors']);
        self::assertSame(['username' => 'DupUnknown', 'email' => 'dup.unknown@example.test'], $result['old']);
    }

    #[Test]
    public function register_returns_technical_error_on_non_duplicate_pdo_before_transaction(): void
    {
        $form = [
            'username'         => 'UserX',
            'email'            => 'x@example.test',
            'password'         => 'ValidP@ssword123!',
            'confirm_password' => 'ValidP@ssword123!',
        ];

        $this->userModel->method('findOneByUsername')->with('UserX')->willReturn(null);

        // PDOException avec SQLSTATE différent de 23000
        $e            = new \PDOException('DB temporary failure');
        $e->errorInfo = ['HY000', 1205, 'Lock wait timeout exceeded']; // ex. timeout
        // on force le code si besoin (facultatif ici)
        $this->userModel->method('findOneByEmail')->with('x@example.test')->willThrowException($e);

        $this->sqlHelper->expects($this->never())->method('beginTransaction');

        $result = $this->service->register($form);

        self::assertSame(['username' => 'UserX', 'email' => 'x@example.test'], $result['old']);
        self::assertContains(\App\Core\ErrorCode::AUTH_TECHNICAL_ERROR, $this->errorsOf($result));
    }

    #[Test]
    public function register_returns_technical_error_on_global_throwable_before_transaction(): void
    {
        $form = [
            'username'         => 'CrashMe',
            'email'            => 'crash@example.test',
            'password'         => 'ValidP@ssword123!',
            'confirm_password' => 'ValidP@ssword123!',
        ];

        $this->userModel->method('findOneByUsername')->with('CrashMe')->willReturn(null);
        $this->userModel->method('findOneByEmail')->with('crash@example.test')->willReturn(null);

        // Provoquer une erreur inattendue tôt (ex. slugify qui jette)
        $this->slugify->method('slugify')->with('CrashMe')->willThrowException(new \RuntimeException('boom'));

        $this->sqlHelper->expects($this->never())->method('beginTransaction');

        $result = $this->service->register($form);

        self::assertSame(['username' => 'CrashMe', 'email' => 'crash@example.test'], $result['old']);
        self::assertContains(\App\Core\ErrorCode::AUTH_TECHNICAL_ERROR, $this->errorsOf($result));
    }

    #[Test]
    public function confirmAccount_returns_empty_on_successful_activation(): void
    {
        $token = 'confirm-abc';
        $hash  = $this->tokenGen->hashToken($token);

        $this->userTokenModel->method('findConfirmationContextByHash')
            ->with($hash)
            ->willReturn([
                'user_id'    => 42,
                'status'     => 'inactive',
                'used'       => 0,
                'is_expired' => 0,
            ]);

        $this->userTokenModel->method('activateByHash')->with($hash)->willReturn(true);

        $result = $this->service->confirmAccount($token);
        self::assertSame([], $result);
    }

    #[Test]
    public function confirmAccount_returns_error_when_token_invalid_or_not_found(): void
    {
        $token = 'bad';
        $hash  = $this->tokenGen->hashToken($token);

        $this->userTokenModel->method('findConfirmationContextByHash')->with($hash)->willReturn(null);

        $result = $this->service->confirmAccount($token);

        self::assertArrayHasKey('error', $result);
        self::assertNotEmpty($result['error']);
    }

    #[Test]
    public function confirmAccount_returns_already_confirmed_when_user_active(): void
    {
        $token = 't-already-active';
        $hash  = $this->tokenGen->hashToken($token);

        $this->userTokenModel->method('findConfirmationContextByHash')
            ->with($hash)
            ->willReturn([
                'user_id'    => 123,
                'status'     => 'active', // ou '1'
                'used'       => 0,
                'is_expired' => 0,
            ]);

        $result = $this->service->confirmAccount($token);

        self::assertSame(['error' => \App\Core\ErrorCode::AUTH_ALREADY_CONFIRMED], $result);
    }

    #[Test]
    public function confirmAccount_returns_invalid_token_when_expired_and_used_inconsistent(): void
    {
        $token = 't-expired-used-incoh';
        $hash  = $this->tokenGen->hashToken($token);

        $this->userTokenModel->method('findConfirmationContextByHash')
            ->with($hash)
            ->willReturn([
                'user_id'    => 456,
                'status'     => 'inactive',
                'used'       => 1,
                'is_expired' => 1,
            ]);

        $result = $this->service->confirmAccount($token);

        self::assertSame(
            ['error' => \App\Core\ErrorCode::AUTH_INVALID_CONFIRM_TOKEN, 'reason' => 'expired'],
            $result
        );
    }

    #[Test]
    public function confirmAccount_returns_invalid_token_when_expired_before_activation(): void
    {
        $token = 't-expired-before-activation';
        $hash  = $this->tokenGen->hashToken($token);

        $this->userTokenModel->method('findConfirmationContextByHash')
            ->with($hash)
            ->willReturn([
                'user_id'    => 789,
                'status'     => 'inactive',
                'used'       => 0,
                'is_expired' => 1,
            ]);

        $result = $this->service->confirmAccount($token);

        self::assertSame(
            ['error' => \App\Core\ErrorCode::AUTH_INVALID_CONFIRM_TOKEN, 'reason' => 'expired'],
            $result
        );
    }

    #[Test]
    public function confirmAccount_returns_token_used_when_already_used_and_user_inactive(): void
    {
        $token = 't-used-not-active';
        $hash  = $this->tokenGen->hashToken($token);

        $this->userTokenModel->method('findConfirmationContextByHash')
            ->with($hash)
            ->willReturn([
                'user_id'    => 999,
                'status'     => 'inactive',
                'used'       => 1,
                'is_expired' => 0,
            ]);

        $result = $this->service->confirmAccount($token);

        self::assertSame(
            ['error' => \App\Core\ErrorCode::AUTH_CONFIRM_TOKEN_USED, 'reason' => 'used'],
            $result
        );
    }

    #[Test]
    public function confirmAccount_returns_technical_error_when_activateByHash_returns_false(): void
    {
        $token = 't-activation-fail';
        $hash  = $this->tokenGen->hashToken($token);

        // Contexte lu en BDD: token valide, user inactif, non expiré, non utilisé
        $this->userTokenModel->method('findConfirmationContextByHash')
            ->with($hash)
            ->willReturn([
                'user_id'    => 42,
                'status'     => 'inactive',
                'used'       => 0,
                'is_expired' => 0,
            ]);

        // Échec de l'activation en base
        $this->userTokenModel->method('activateByHash')
            ->with($hash)
            ->willReturn(false);

        $result = $this->service->confirmAccount($token);

        self::assertSame(
            ['error' => \App\Core\ErrorCode::AUTH_TECHNICAL_ERROR, 'reason' => 'activate_failed'],
            $result
        );
    }

    #[Test]
    public function confirmAccount_returns_technical_error_on_global_exception(): void
    {
        $token = 'any-token';
        $hash  = $this->tokenGen->hashToken($token);

        // La moindre exception dans la séquence doit être catchée
        $this->userTokenModel->method('findConfirmationContextByHash')
            ->with($hash)
            ->willThrowException(new \RuntimeException('db down'));

        $result = $this->service->confirmAccount($token);
        self::assertSame(['error' => \App\Core\ErrorCode::AUTH_TECHNICAL_ERROR], $result);
    }

    #[Test]
    public function resendConfirmation_returns_empty_when_email_resent(): void
    {
        $email = 'bob@example.test';
        $user  = (new UserEntity())
            ->setUserId(99)
            ->setUsername('Bob')
            ->setEmail($email)
            ->setStatus('inactive');

        $this->userModel->method('findOneByEmail')->with($email)->willReturn($user);

        $this->userTokenModel->method('createConfirmationToken')
            ->with(
                99,
                $this->callback(fn ($h) => is_string($h) && strlen($h) === 32),
                $this->isInstanceOf(\DateTimeImmutable::class)
            )
            ->willReturn(true);

        $this->mailer->method('send')->with(
            $email,
            'Bob',
            'Confirmation de votre compte',
            'confirmation.html',
            $this->callback(function (array $vars): bool {
                $link = $vars['link'] ?? '';
                if (!is_string($link)) {
                    return false;
                }
                if (!str_contains($link, '/confirm-account?token=')) {
                    return false;
                }
                $parts = parse_url($link);
                parse_str($parts['query'] ?? '', $qs);
                return isset($qs['token']) && is_string($qs['token']) && $qs['token'] !== '';
            })
        )->willReturn(true);

        $result = $this->service->resendConfirmation($email);
        self::assertSame([], $result);
    }

    #[Test]
    public function resendConfirmation_returns_empty_when_email_unknown(): void
    {
        $email = 'unknown@example.test';

        $this->userModel->method('findOneByEmail')->with($email)->willReturn(null);

        // Rien d’autre ne doit se produire
        $this->userTokenModel->expects($this->never())->method('createConfirmationToken');
        $this->mailer->expects($this->never())->method('send');

        $result = $this->service->resendConfirmation($email);
        self::assertSame([], $result);
    }

    #[Test]
    public function resendConfirmation_returns_error_when_user_already_active(): void
    {
        $email = 'active@example.test';
        $user  = (new UserEntity())
            ->setUserId(5)
            ->setUsername('ActiveUser')
            ->setEmail($email)
            ->setStatus('active');

        $this->userModel->method('findOneByEmail')->with($email)->willReturn($user);

        $result = $this->service->resendConfirmation($email);
        self::assertArrayHasKey('error', $result);
        self::assertNotEmpty($result['error']);
    }

    #[Test]
    public function resendConfirmation_returns_error_when_set_confirmation_token_failed(): void
    {
        $email = 'inactive@example.test';
        $user  = (new UserEntity())
            ->setUserId(10)
            ->setUsername('InactiveUser')
            ->setEmail($email)
            ->setStatus('inactive');

        $this->userModel->method('findOneByEmail')->with($email)->willReturn($user);

        // Echec lors de l’écriture du token
        $this->userTokenModel->method('createConfirmationToken')
            ->with(10, $this->callback(fn ($h) => is_string($h) && strlen($h) === 32), $this->isInstanceOf(\DateTimeImmutable::class))
            ->willReturn(false);

        // L’email ne doit pas partir
        $this->mailer->expects($this->never())->method('send');

        $result = $this->service->resendConfirmation($email);
        self::assertSame(['error' => \App\Core\ErrorCode::AUTH_TECHNICAL_ERROR], $result);
    }

    #[Test]
    public function resendConfirmation_returns_error_when_mailer_throws_exception(): void
    {
        $email = 'inactive2@example.test';
        $user  = (new UserEntity())
            ->setUserId(11)
            ->setUsername('InactiveUser2')
            ->setEmail($email)
            ->setStatus('inactive');

        $this->userModel->method('findOneByEmail')->with($email)->willReturn($user);

        $this->userTokenModel->method('createConfirmationToken')
            ->with(11, $this->callback(fn ($h) => is_string($h) && strlen($h) === 32), $this->isInstanceOf(\DateTimeImmutable::class))
            ->willReturn(true);

        $this->mailer->method('send')->willThrowException(new \RuntimeException('SMTP down'));

        $result = $this->service->resendConfirmation($email);
        self::assertSame(['error' => \App\Core\ErrorCode::AUTH_CONFIRM_EMAIL_SEND_FAILED], $result);
    }

    #[Test]
    public function resendConfirmation_returns_error_when_mailer_returns_false(): void
    {
        $email = 'inactive3@example.test';
        $user  = (new UserEntity())
            ->setUserId(12)
            ->setUsername('InactiveUser3')
            ->setEmail($email)
            ->setStatus('inactive');

        $this->userModel->method('findOneByEmail')->with($email)->willReturn($user);

        $this->userTokenModel->method('createConfirmationToken')
            ->with(12, $this->callback(fn ($h) => is_string($h) && strlen($h) === 32), $this->isInstanceOf(\DateTimeImmutable::class))
            ->willReturn(true);

        $this->mailer->method('send')->willReturn(false);

        $result = $this->service->resendConfirmation($email);
        self::assertSame(['error' => \App\Core\ErrorCode::AUTH_CONFIRM_EMAIL_SEND_FAILED], $result);
    }

    #[Test]
    public function resendConfirmation_returns_technical_error_on_global_exception(): void
    {
        $email = 'boom@example.test';

        // Provoque une exception dès la lecture utilisateur
        $this->userModel->method('findOneByEmail')->with($email)->willThrowException(new \RuntimeException('boom'));

        $result = $this->service->resendConfirmation($email);
        self::assertSame(['error' => \App\Core\ErrorCode::AUTH_TECHNICAL_ERROR], $result);
    }

    private function createPdoDuplicate(string $message): \PDOException
    {
        $e            = new \PDOException($message);
        $e->errorInfo = ['23000', 1062, $message];

        // Forcer $code en chaîne avec Reflection (car constructeur l'impose en int)
        $ref  = new \ReflectionClass($e);
        $prop = $ref->getProperty('code');
        $prop->setAccessible(true);
        $prop->setValue($e, '23000');

        return $e;
    }

    /**
     * Petit helper pour recréer le service avec un TokenGenerator mocké.
     */
    private function makeServiceWithTokenGen(TokenGeneratorInterface $tg): SecurityService
    {
        return new SecurityService(
            $this->validator,
            $this->userModel,
            $this->userTokenModel,
            $this->slugify,
            $this->mailer,
            $tg,
            $this->sqlHelper
        );
    }

    #[Test]
    public function register_retourne_technical_error_si_confirmation_hash_pas_32_octets(): void
    {
        $form = [
            'username'         => 'HashKoUser',
            'email'            => 'hash.ko@example.test',
            'password'         => 'ValidP@ssword123!',
            'confirm_password' => 'ValidP@ssword123!',
        ];

        // Contexte "normal" côté modèle
        $this->userModel->method('findOneByUsername')->with('HashKoUser')->willReturn(null);
        $this->userModel->method('findOneByEmail')->with('hash.ko@example.test')->willReturn(null);
        $this->slugify->method('slugify')->with('HashKoUser')->willReturn('hashkouser');

        // TokenGenerator mocké qui renvoie un hash de mauvaise taille
        $tg = $this->createMock(TokenGeneratorInterface::class);
        $tg->method('generateUrlSafeToken')->willReturn('tok');
        $tg->method('hashToken')->willReturn('short'); // <- ≠ 32

        // On doit s'arrêter AVANT toute transaction
        $this->sqlHelper->expects($this->never())->method('beginTransaction');

        $svc    = $this->makeServiceWithTokenGen($tg);
        $result = $svc->register($form);

        self::assertSame(
            ['errors' => [\App\Core\ErrorCode::AUTH_TECHNICAL_ERROR], 'old' => ['username' => 'HashKoUser', 'email' => 'hash.ko@example.test']],
            $result
        );
    }

    #[Test]
    public function confirmAccount_retourne_technical_error_si_hash_pas_32_octets(): void
    {
        $tg = $this->createMock(TokenGeneratorInterface::class);
        $tg->method('hashToken')->willReturn('bad'); // <- ≠ 32

        // On ne doit pas interroger la BDD si le hash est invalide
        $this->userTokenModel->expects($this->never())->method('findConfirmationContextByHash');

        $svc    = $this->makeServiceWithTokenGen($tg);
        $result = $svc->confirmAccount('any-token');

        self::assertSame(['error' => \App\Core\ErrorCode::AUTH_TECHNICAL_ERROR], $result);
    }

    #[Test]
    public function resendConfirmation_retourne_technical_error_si_hash_pas_32_octets(): void
    {
        $email = 'need.resend@example.test';
        $user  = (new UserEntity())
            ->setUserId(77)
            ->setUsername('NeedResend')
            ->setEmail($email)
            ->setStatus('inactive');

        $this->userModel->method('findOneByEmail')->with($email)->willReturn($user);

        $tg = $this->createMock(TokenGeneratorInterface::class);
        $tg->method('generateUrlSafeToken')->willReturn('tok');
        $tg->method('hashToken')->willReturn('bad'); // <- ≠ 32

        // Rien ne doit être écrit ni envoyé
        $this->userTokenModel->expects($this->never())->method('createConfirmationToken');
        $this->mailer->expects($this->never())->method('send');

        $svc    = $this->makeServiceWithTokenGen($tg);
        $result = $svc->resendConfirmation($email);

        self::assertSame(['error' => \App\Core\ErrorCode::AUTH_TECHNICAL_ERROR], $result);
    }
}
