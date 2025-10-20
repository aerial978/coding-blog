<?php

declare(strict_types=1);

/**
 * Interception de header() dans le namespace du contrôleur.
 * - Empile tous les en-têtes dans un spy
 * - Lance une exception SEULEMENT pour Location: afin de simuler la redirection
 * - Laisse passer Retry-After: sans interrompre le flux
 */

namespace App\Controller {
    function header(string $header, bool $replace = true, int $response_code = 0): void
    {
        if (\Tests\Support\HeaderSpy::isEnabled()) {
            \Tests\Support\HeaderSpy::push($header, $response_code);
            if (\stripos($header, 'Location:') === 0) {
                throw new \Tests\Support\InterceptRedirect();
                // court-circuite un exit;
            }
            return;
        }
        \header($header, $replace, $response_code);
    }

}

/**
 * Utilitaires (spy + exception) pour le test
 */

namespace Tests\Support {
    final class InterceptRedirect extends \RuntimeException
    {
    }

    final class HeaderSpy
    {
        /** @var list<array{0:string,1:int}> */
        private static array $stack  = [];
        private static bool $enabled = false;

        public static function enable(): void
        {
            self::$enabled = true;
        }
        public static function disable(): void
        {
            self::$enabled = false;
        }
        public static function isEnabled(): bool
        {
            return self::$enabled;
        }
        public static function reset(): void
        {
            self::$stack = [];
        }

        public static function push(string $header, int $code = 0): void
        {
            self::$stack[] = [$header, $code];
        }

        /** Retourne la dernière Location: ou null */
        public static function lastLocation(): ?string
        {
            for ($i = \count(self::$stack) - 1; $i >= 0; $i--) {
                [$h, ] = self::$stack[$i];
                if (\stripos($h, 'Location:') === 0) {
                    return \trim(\substr($h, \strlen('Location:')));
                }
            }
            return null;
        }

        /** @return list<array{0:string,1:int}> */
        public static function all(): array
        {
            return self::$stack;
        }
    }

}

/**
 * Tests du SecurityController.
 */

namespace Tests\App\Controller {

    use App\Controller\SecurityController;
    use App\Core\Contract\RateLimiterFactoryInterface;
    use App\Core\Contract\RateLimiterInterface;
    use App\Core\ErrorCode;
    use App\Core\View;
    use App\Http\Request;
    use App\Service\Contract\SecurityServiceInterface;
    use PHPUnit\Framework\Attributes\CoversClass;
    use PHPUnit\Framework\Attributes\CoversMethod;
    use PHPUnit\Framework\Attributes\Test;
    use PHPUnit\Framework\TestCase;
    use Tests\Double\CsrfFake;
    use Tests\Double\FlashFake;
    use Tests\Support\HeaderSpy;
    use Tests\Support\InterceptRedirect;

    final class TestLimiter implements \App\Core\Contract\RateLimiterInterface
    {
        public bool $allowed    = true;
        public int $retryAfter  = 60;
        public int $recordCount = 0;

        public function isAllowed(): bool
        {
            return $this->allowed;
        }
        public function getRetryAfter(): int
        {
            return $this->retryAfter;
        }
        public function recordAttempt(): void
        {
            $this->recordCount++;
        }
    }

    #[CoversClass(SecurityController::class)]
    #[CoversMethod(SecurityController::class, 'register')]
    #[CoversMethod(SecurityController::class, 'confirmAccount')]
    #[CoversMethod(SecurityController::class, 'resendConfirmation')]
    final class SecurityControllerTest extends TestCase
    {
        private FlashFake $flash;
        private CsrfFake $csrf;
        /** @var \PHPUnit\Framework\MockObject\MockObject&SecurityServiceInterface */
        private $securityService;
        /** @var \PHPUnit\Framework\MockObject\MockObject&RateLimiterFactoryInterface */
        private $limiterFactory;
        /** @var \PHPUnit\Framework\MockObject\MockObject&View */
        private $view;
        /** @var null|array{0:string,1:array<string,mixed>} */
        private ?array $lastRender = null;
        /** Limiteurs créés par clé */
        /** @var array<string, TestLimiter> */
        private array $limiters = [];
        // key => objet anonyme implémentant RateLimiterInterface

        protected function setUp(): void
        {
            HeaderSpy::enable();
            HeaderSpy::reset();
            // Stub View : capture le rendu
            $this->view = $this->createMock(View::class);
            $this->view->method('render')
                ->willReturnCallback(function (string $tpl, array $data = []): string {
                    /** @var array<string, mixed> $data */
                    $this->lastRender = [$tpl, $data];
                    return '';
                });
            $this->flash           = new FlashFake();
            $this->csrf            = new CsrfFake(true, 'csrf_test_token');
            $this->securityService = $this->createMock(SecurityServiceInterface::class);
            // Factory RateLimiter → objets anonymes contrôlables
            $this->limiterFactory = $this->createMock(RateLimiterFactoryInterface::class);
            $this->limiterFactory->method('create')
                ->willReturnCallback(function (string $key, int $maxAttempts, int $windowSeconds) {
                    if (!isset($this->limiters[$key])) {
                        $this->limiters[$key] = new TestLimiter();
                    }
                    return $this->limiters[$key];
                });
        }

        protected function tearDown(): void
        {
            HeaderSpy::disable();
            HeaderSpy::reset();
        }

        /** @param array<string, mixed> $post */
        private function makeController(string $httpMethod, array $post = []): SecurityController
        {
            $request = $this->createMock(Request::class);
            $request->method('getMethod')->willReturn($httpMethod);
            $request->method('request')->willReturn($post);
            return new SecurityController($this->view, $this->securityService, $request, $this->flash, $this->csrf, $this->limiterFactory);
        }

        private function assertRedirectTo(string $expected): void
        {
            self::assertSame($expected, HeaderSpy::lastLocation(), 'Mauvaise redirection (Location).');
        }

        private function assertFlashHasAtLeastOneMessage(): void
        {
            self::assertNotEmpty($this->flash->messages, 'Un message flash était attendu.');
        }

        // ─────────────────────────────────────────────────────────────────────
        // REGISTER
        // ─────────────────────────────────────────────────────────────────────

        #[Test]
        public function register_affiche_formulaire_en_get(): void
        {
            $controller = $this->makeController('GET');
            $controller->register();
            self::assertNotNull($this->lastRender);
            [$tpl, $data] = $this->lastRender;
            self::assertSame('security/register.html.twig', $tpl);
            self::assertSame('form', $data['mode']);
            self::assertArrayHasKey('csrf_token', $data);
            self::assertSame([], $data['old']);
        }

        #[Test]
        public function register_rate_limited_en_post(): void
        {
            // 1er passage : crée le limiter "registration"
            $tmp = $this->makeController('POST', [
                'username'   => 'john',
                'email'      => 'john@example.test',
                'csrf_token' => 'csrf_test_token',
            ]);
            try {
                $tmp->register();
            } catch (InterceptRedirect) {
            }

            // Bloque le limiter
            $limiter = $this->limiters['registration'] ?? null;
            self::assertNotNull($limiter, 'Le limiter "registration" aurait dû être créé.');
            self::assertInstanceOf(TestLimiter::class, $limiter);
            $limiter->allowed    = false;
            $limiter->retryAfter = 125;
            // Reset captures
            HeaderSpy::reset();
            $this->flash->messages = [];
            $this->flash->store    = [];
            $controller            = $this->makeController('POST', [
                'username'   => 'john',
                'email'      => 'john@example.test',
                'csrf_token' => 'csrf_test_token',
            ]);
            try {
                $controller->register();
                self::fail('Une redirection était attendue');
            } catch (InterceptRedirect) {
            }

            $this->assertRedirectTo('/coding-blog/register');
            $this->assertFlashHasAtLeastOneMessage();

            /** @var array{old?: array<string, string>} $store */
            $store = $this->flash->store;

            self::assertSame('john', $store['old']['username'] ?? null);
            self::assertSame('john@example.test', $store['old']['email'] ?? null);
        }

        #[Test]
        public function register_csrf_invalide(): void
        {
            $this->csrf->setValid(false);
            $controller = $this->makeController('POST', [
                'username'   => 'john',
                'email'      => 'john@example.test',
                'csrf_token' => 'bad',
            ]);
            try {
                $controller->register();
                self::fail('Une redirection était attendue');
            } catch (InterceptRedirect) {
            }

            $this->assertRedirectTo('/coding-blog/register');
            $this->assertFlashHasAtLeastOneMessage();

            /** @var array{old?: array<string, string>} $store */
            $store = $this->flash->store;

            self::assertSame('john', $store['old']['username'] ?? null);
        }

        #[Test]
        public function register_echec_envoi_email_confirmation_redirige_resend(): void
        {
            $this->securityService
                ->method('register')
                ->willReturn([
                    'errors' => [ErrorCode::AUTH_CONFIRM_EMAIL_SEND_FAILED],
                    'old'    => ['email' => 'john@example.test', 'username' => 'john'],
                ]);
            $controller = $this->makeController('POST', [
                'username'   => 'john',
                'email'      => 'john@example.test',
                'csrf_token' => 'csrf_test_token',
            ]);
            try {
                $controller->register();
                self::fail('Une redirection était attendue');
            } catch (InterceptRedirect) {
            }

            $this->assertRedirectTo('/coding-blog/resend-confirmation');
            $this->assertFlashHasAtLeastOneMessage();

            /** @var array{old?: array<string,string>} $store */
            $store = $this->flash->store;

            /** @var array<string,string> $old */
            $old = \is_array($store['old'] ?? null) ? $store['old'] : [];

            self::assertArrayHasKey('email', $old);
            self::assertSame('john@example.test', $old['email']);

            // le username n'est pas attendu sur /resend-confirmation
            self::assertArrayNotHasKey('username', $old);
        }

        #[Test]
        public function register_succes_redirige_register_et_mode_check_email_au_get_suivant(): void
        {
            $this->securityService->method('register')->willReturn(['ok' => true]);
            // POST succès → redirection /register
            $controller = $this->makeController('POST', [
                'username'   => 'john',
                'email'      => 'john@example.test',
                'csrf_token' => 'csrf_test_token',
            ]);
            try {
                $controller->register();
                self::fail('Une redirection était attendue');
            } catch (InterceptRedirect) {
            }

            $this->assertRedirectTo('/coding-blog/register');
            // GET suivant : 'register_state' déclenche 'check_email'
            $controller = $this->makeController('GET');
            $controller->register();
            self::assertNotNull($this->lastRender);
            [$tpl, $data] = $this->lastRender;
            self::assertSame('security/register.html.twig', $tpl);
            self::assertSame('check_email', $data['mode']);
            self::assertNotNull($data['obfuscated_email']);
        }

        #[Test]
        public function register_catch_technique_redirige_register_avec_error(): void
        {
            $this->securityService
                ->method('register')
                ->willThrowException(new \RuntimeException('boom'));
            $controller = $this->makeController('POST', [
                'username'   => 'john',
                'email'      => 'john@example.test',
                'csrf_token' => 'csrf_test_token',
            ]);
            try {
                $controller->register();
                self::fail('Une redirection était attendue');
            } catch (InterceptRedirect) {
            }

            $this->assertRedirectTo('/coding-blog/register');
            $this->assertFlashHasAtLeastOneMessage();
            /** @var array{old?: array<string, string>} $store */
            $store = $this->flash->store;

            self::assertSame('john', $store['old']['username'] ?? null);
            self::assertSame('john@example.test', $store['old']['email'] ?? null);
        }

        #[Test]
        public function register_autres_erreurs_metier_empilent_messages_et_retour_formulaire(): void
        {
            $this->securityService
                ->method('register')
                ->willReturn([
                    'errors' => [
                        ErrorCode::AUTH_USERNAME_EXISTS,
                        ErrorCode::AUTH_PASSWORD_REENTER,
                    ],
                    'old'    => ['username' => 'john', 'email' => 'john@example.test'],
                ]);
            $controller = $this->makeController('POST', [
                'username'   => 'john',
                'email'      => 'john@example.test',
                'csrf_token' => 'csrf_test_token',
            ]);
            try {
                $controller->register();
                self::fail('Une redirection était attendue');
            } catch (InterceptRedirect) {
            }

            $this->assertRedirectTo('/coding-blog/register');
            $this->assertFlashHasAtLeastOneMessage();
            /** @var array{old?: array<string, string>} $store */
            $store = $this->flash->store;

            self::assertSame('john', $store['old']['username'] ?? null);
            self::assertSame('john@example.test', $store['old']['email'] ?? null);
        }

        // ─────────────────────────────────────────────────────────────────────
        // CONFIRM ACCOUNT
        // ─────────────────────────────────────────────────────────────────────

        #[Test]
        public function confirmAccount_token_manquant_redirige_resend(): void
        {
            $_GET = [];
            // token manquant

            $controller = $this->makeController('GET');
            try {
                $controller->confirmAccount();
                self::fail('Une redirection était attendue');
            } catch (InterceptRedirect) {
            }

            $this->assertRedirectTo('/coding-blog/resend-confirmation');
            $this->assertFlashHasAtLeastOneMessage();
        }

        #[Test]
        public function confirmAccount_deja_confirme_redirige_login(): void
        {
            $_GET = ['token' => 'abc'];
            $this->securityService
                ->method('confirmAccount')
                ->willReturn(['error' => ErrorCode::AUTH_ALREADY_CONFIRMED]);
            $controller = $this->makeController('GET');
            try {
                $controller->confirmAccount();
                self::fail('Une redirection était attendue');
            } catch (InterceptRedirect) {
            }

            $this->assertRedirectTo('/coding-blog');
        }

        #[Test]
        public function confirmAccount_succes_redirige_login_avec_success(): void
        {
            $_GET = ['token' => 'ok_token'];
            $this->securityService
                ->method('confirmAccount')
                ->willReturn([]);
            // succès

            $controller = $this->makeController('GET');
            try {
                $controller->confirmAccount();
                self::fail('Une redirection était attendue');
            } catch (InterceptRedirect) {
            }

            $this->assertRedirectTo('/coding-blog');
            $this->assertFlashHasAtLeastOneMessage();
            $last = \end($this->flash->messages);
            $type = \is_array($last) ? $last[0] : null;
            self::assertSame('success', $type);
        }

        #[Test]
        public function confirmAccount_token_expire_redirige_resend(): void
        {
            $_GET = ['token' => 't'];
            $this->securityService
                ->method('confirmAccount')
                ->willReturn(['error' => ErrorCode::AUTH_INVALID_CONFIRM_TOKEN, 'reason' => 'expired']);
            $controller = $this->makeController('GET');
            try {
                $controller->confirmAccount();
                self::fail('Une redirection était attendue');
            } catch (InterceptRedirect) {
            }

            $this->assertRedirectTo('/coding-blog/resend-confirmation');
            $this->assertFlashHasAtLeastOneMessage();
        }

        #[Test]
        public function confirmAccount_token_deja_utilise_redirige_login_info(): void
        {
            $_GET = ['token' => 't'];
            $this->securityService
                ->method('confirmAccount')
                ->willReturn(['error' => ErrorCode::AUTH_CONFIRM_TOKEN_USED]);
            $controller = $this->makeController('GET');
            try {
                $controller->confirmAccount();
                self::fail('Une redirection était attendue');
            } catch (InterceptRedirect) {
            }

            $this->assertRedirectTo('/coding-blog');
            $this->assertFlashHasAtLeastOneMessage();
        }

        #[Test]
        public function confirmAccount_token_inconnu_altere_redirige_resend(): void
        {
            $_GET = ['token' => 't'];
            $this->securityService
                ->method('confirmAccount')
                ->willReturn(['error' => ErrorCode::AUTH_INVALID_CONFIRM_TOKEN, 'reason' => 'not_found']);
            $controller = $this->makeController('GET');
            try {
                $controller->confirmAccount();
                self::fail('Une redirection était attendue');
            } catch (InterceptRedirect) {
            }

            $this->assertRedirectTo('/coding-blog/resend-confirmation');
            $this->assertFlashHasAtLeastOneMessage();
        }

        #[Test]
        public function confirmAccount_catch_technique_redirige_login_avec_error(): void
        {
            $_GET = ['token' => 't'];
            $this->securityService
                ->method('confirmAccount')
                ->willThrowException(new \RuntimeException('boom'));
            $controller = $this->makeController('GET');
            try {
                $controller->confirmAccount();
                self::fail('Une redirection était attendue');
            } catch (InterceptRedirect) {
            }

            $this->assertRedirectTo('/coding-blog');
            $this->assertFlashHasAtLeastOneMessage();
        }

        // ─────────────────────────────────────────────────────────────────────
        // RESEND CONFIRMATION
        // ─────────────────────────────────────────────────────────────────────

        #[Test]
        public function resendConfirmation_get_affiche_formulaire(): void
        {
            $controller = $this->makeController('GET');
            $controller->resendConfirmation();
            self::assertNotNull($this->lastRender);
            [$tpl, $data] = $this->lastRender;
            self::assertSame('security/resend-confirmation.html.twig', $tpl);
            self::assertArrayHasKey('csrf_token', $data);
        }

        #[Test]
        public function resendConfirmation_rate_limited(): void
        {
            // 1er POST pour créer le limiter
            $tmp = $this->makeController('POST', [
                'email'      => 'jane@example.test',
                'csrf_token' => 'csrf_test_token',
            ]);
            try {
                $tmp->resendConfirmation();
            } catch (InterceptRedirect) {
            }

            // Bloque le limiter
            $limiter = $this->limiters['resend_confirmation'] ?? null;
            self::assertNotNull($limiter, 'Le limiter "resend_confirmation" aurait dû être créé.');
            self::assertInstanceOf(TestLimiter::class, $limiter);
            $limiter->allowed    = false;
            $limiter->retryAfter = 30;
            // Reset captures
            HeaderSpy::reset();
            $this->flash->messages = [];
            $controller            = $this->makeController('POST', [
                'email'      => 'jane@example.test',
                'csrf_token' => 'csrf_test_token',
            ]);
            try {
                $controller->resendConfirmation();
                self::fail('Une redirection était attendue');
            } catch (InterceptRedirect) {
            }

            $this->assertRedirectTo('/coding-blog/resend-confirmation');
            $this->assertFlashHasAtLeastOneMessage();
        }

        #[Test]
        public function resendConfirmation_csrf_invalide(): void
        {
            $this->csrf->setValid(false);
            $controller = $this->makeController('POST', [
                'email'      => 'jane@example.test',
                'csrf_token' => 'bad',
            ]);
            try {
                $controller->resendConfirmation();
                self::fail('Une redirection était attendue');
            } catch (InterceptRedirect) {
            }

            $this->assertRedirectTo('/coding-blog/resend-confirmation');
            $this->assertFlashHasAtLeastOneMessage();
        }

        #[Test]
        public function resendConfirmation_deja_confirme_redirige_login(): void
        {
            $this->securityService
                ->method('resendConfirmation')
                ->with('jane@example.test')
                ->willReturn(['error' => ErrorCode::AUTH_ALREADY_CONFIRMED]);
            $controller = $this->makeController('POST', [
                'email'      => 'jane@example.test',
                'csrf_token' => 'csrf_test_token',
            ]);
            try {
                $controller->resendConfirmation();
                self::fail('Une redirection était attendue');
            } catch (InterceptRedirect) {
            }

            $this->assertRedirectTo('/coding-blog');
        }

        #[Test]
        public function resendConfirmation_succes_reste_sur_formulaire_avec_success(): void
        {
            $this->securityService
                ->method('resendConfirmation')
                ->willReturn([]);
            // succès

            $controller = $this->makeController('POST', [
                'email'      => 'jane@example.test',
                'csrf_token' => 'csrf_test_token',
            ]);
            try {
                $controller->resendConfirmation();
                self::fail('Une redirection était attendue');
            } catch (InterceptRedirect) {
            }

            $this->assertRedirectTo('/coding-blog/resend-confirmation');
            $this->assertFlashHasAtLeastOneMessage();
            $last = \end($this->flash->messages);
            $type = \is_array($last) ? $last[0] : null;
            self::assertSame('success', $type);
        }

        #[Test]
        public function resendConfirmation_catch_technique_redirige_formulaire_avec_error(): void
        {
            $this->securityService
                ->method('resendConfirmation')
                ->willThrowException(new \RuntimeException('boom'));
            $controller = $this->makeController('POST', [
                'email'      => 'jane@example.test',
                'csrf_token' => 'csrf_test_token',
            ]);
            try {
                $controller->resendConfirmation();
                self::fail('Une redirection était attendue');
            } catch (InterceptRedirect) {
            }

            $this->assertRedirectTo('/coding-blog/resend-confirmation');
            $this->assertFlashHasAtLeastOneMessage();
        }

        #[Test]
        public function resendConfirmation_fallback_generique_redirige_formulaire_avec_error(): void
        {
            $this->securityService
                ->method('resendConfirmation')
                ->willReturn(['error' => ErrorCode::AUTH_TECHNICAL_ERROR]);
            $controller = $this->makeController('POST', [
                'email'      => 'jane@example.test',
                'csrf_token' => 'csrf_test_token',
            ]);
            try {
                $controller->resendConfirmation();
                self::fail('Une redirection était attendue');
            } catch (InterceptRedirect) {
            }

            $this->assertRedirectTo('/coding-blog/resend-confirmation');
            $this->assertFlashHasAtLeastOneMessage();
        }

        // ─────────────────────────────────────────────────────────────────────
        // SMOKE tests pour la métrique “Methods”
        // (garantissent une exécution “non interrompue” de chaque action)
        // ─────────────────────────────────────────────────────────────────────

        #[Test]
        public function _smoke_couvre_register(): void
        {
            $controller = $this->makeController('GET');
            // pas de redirection
            $controller->register();
            self::assertNotNull($this->lastRender);
        }

        #[Test]
        public function _smoke_couvre_confirmAccount(): void
        {
            $_GET = ['token' => 'ok'];
            $this->securityService->method('confirmAccount')->willReturn([]);
            // succès
            $controller = $this->makeController('GET');
            try {
                $controller->confirmAccount();
            } catch (InterceptRedirect) {
            }
            $this->assertFlashHasAtLeastOneMessage();
        }

        #[Test]
        public function _smoke_couvre_resendConfirmation(): void
        {
            $controller = $this->makeController('GET');
            // affiche le formulaire
            $controller->resendConfirmation();
            self::assertNotNull($this->lastRender);
        }
    }

}
