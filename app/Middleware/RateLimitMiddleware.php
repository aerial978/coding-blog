<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Contract\FlashInterface;
use App\Core\Contract\RateLimiterFactoryInterface;
use App\Core\Contract\RateLimiterInterface;
use App\Core\ErrorCode;
use App\Core\Logger;
use App\Core\MessageManager;
use App\Http\Middleware\MiddlewareInterface;
use App\Http\Request;

/**
 * Middleware de limitation de débit (Rate Limiting).
 *
 * Applique des règles par route + méthode HTTP. Si la limite est atteinte,
 * envoie un header Retry-After, flash un message utilisateur et interrompt la chaîne.
 */
final class RateLimitMiddleware implements MiddlewareInterface
{
    /**
     * Règles de throttling par méthode et URI.
     * - max   : nombre max de tentatives autorisées dans la fenêtre
     * - window: durée de la fenêtre (en secondes)
     *
     * Ajustez librement selon vos besoins.
     *
     * @var array<string, array<string, array{max:int,window:int}>>
     */
    private const RULES = [
        'POST' => [
            '/register'            => ['max' => 5, 'window' => 300], // 5 tentatives / 5 min
            '/resend-confirmation' => ['max' => 5, 'window' => 600], // 5 tentatives / 10 min
        ],
    ];

    public function __construct(
        private RateLimiterFactoryInterface $factory,
        private FlashInterface $flash
    ) {
    }

    /**
     * Intercepte la requête. Si une règle de rate limit existe pour (méthode, uri),
     * on vérifie la limite avant d’aller plus loin.
     *
     * Retourne false si la requête est bloquée (et gérée ici).
     */
    public function handle(Request $request, string $uri, string $method): bool
    {
        $method = strtoupper($method);

        Logger::getLogger('app')->info('ratelimit_mw_entry', [
            'method' => $method,
            'uri'    => $uri,
        ]);

        // 1) Résoudre la règle applicable (sinon, laisser passer)
        $rule = $this->resolveRule($method, $uri);
        if ($rule === null) {
            return true;
        }

        // 2) Instancier le limiteur pour le bucket route+client
        [$limiter, $bucket] = $this->buildLimiter($rule, $method, $uri);

        // 3) Vérifier/traiter le blocage
        if (!$limiter->isAllowed()) {
            $this->handleBlocked($limiter, $bucket, $method, $uri);
            return false;
        }

        // 4) Enregistrer la tentative et continuer
        $limiter->recordAttempt();
        return true;
    }

    /**
     * Retourne la règle applicable pour (méthode, uri) ou null si non protégée.
     *
     * @return array{max:int,window:int}|null
     */
    private function resolveRule(string $method, string $uri): ?array
    {
        /** @var array{max:int,window:int}|null $rule */
        $rule = self::RULES[$method][$uri] ?? null;
        return $rule;
    }

    /**
     * @param array{max:int,window:int} $rule
     * @return array{0:RateLimiterInterface,1:string}
     */
    private function buildLimiter(array $rule, string $method, string $uri): array
    {
        // Ici $rule['max'] et $rule['window'] sont déjà des int (cf. RULES + resolveRule())
        $max    = $rule['max'];
        $window = $rule['window'];

        $bucket  = $this->buildBucketKey($method, $uri, $this->clientId());
        $limiter = $this->factory->create($bucket, $max, $window);

        return [$limiter, $bucket];
    }

    private function handleBlocked(RateLimiterInterface $limiter, string $bucket, string $method, string $uri): void
    {
        $retryAfter = $limiter->getRetryAfter();
        header('Retry-After: ' . (string) $retryAfter);

        // Message dynamique "Réessayez dans {time}"
        $timeStr = $this->formatRetryTime($retryAfter);
        $msgTpl  = MessageManager::get(ErrorCode::AUTH_RATE_LIMITED_DYNAMIC);
        $waitMsg = str_replace('{time}', $timeStr, $msgTpl);

        Logger::logCodeAndGetMessage('auth', 'warning', ErrorCode::AUTH_RATE_LIMITED_DYNAMIC, [
            'retry_after' => $retryAfter,
            'bucket'      => $bucket,
            'method'      => $method,
            'uri'         => $uri,
        ]);

        $this->flash->add('error', $waitMsg);

        $target = is_string($_SERVER['HTTP_REFERER'] ?? null) && $_SERVER['HTTP_REFERER'] !== ''
            ? (string) $_SERVER['HTTP_REFERER']
            : $uri;

        header('Location: ' . $target, true, 302);

        Logger::getLogger('app')->warning('ratelimit_mw_block', [
            'uri'    => $uri,
            'bucket' => $bucket,
        ]);
    }

    private function buildBucketKey(string $method, string $uri, string $clientId): string
    {
        // Exemple de clé : POST:/register:client-<id>
        return $method . ':' . $uri . ':client-' . $clientId;
    }

    /**
     * Identifiant minimal de "client" (session ou IP) pour éviter de limiter tout le monde ensemble.
     */
    private function clientId(): string
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $sid = session_id();
            if (is_string($sid) && $sid !== '') {
                return $sid;
            }
        }

        $ip = isset($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR'])
            ? $_SERVER['REMOTE_ADDR']
            : '0.0.0.0';

        return 'ip-' . $ip;
    }

    private function formatRetryTime(int $retry): string
    {
        $min = intdiv($retry, 60);
        $sec = $retry % 60;
        return $min > 0 ? "{$min} min et {$sec} s" : "{$sec} s";
    }
}
