<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Contract\FlashInterface;
use App\Core\Contract\RateLimiterFactoryInterface;
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

        // Pas de règle → on laisse passer
        $rule = self::RULES[$method][$uri] ?? null;
        if ($rule === null) {
            return true;
        }

        $max    = (int) $rule['max'];
        $window = (int) $rule['window'];

        // Clé logique du seau de limitation (par route + client)
        $bucket = $this->buildBucketKey($method, $uri, $this->clientId());

        // Crée / récupère un limiteur pour ce bucket
        $limiter = $this->factory->create($bucket, $max, $window);

        if (!$limiter->isAllowed()) {
            $retryAfter = $limiter->getRetryAfter();
            header('Retry-After: ' . $retryAfter);

            // Message dynamique "Réessayez dans {time}"
            $timeStr = $this->formatRetryTime($retryAfter);
            $msgTpl  = MessageManager::get(ErrorCode::AUTH_RATE_LIMITED_DYNAMIC);
            $waitMsg = str_replace('{time}', $timeStr, $msgTpl);

            // Logs + feedback utilisateur + redirection back
            Logger::logCodeAndGetMessage('auth', 'warning', ErrorCode::AUTH_RATE_LIMITED_DYNAMIC, [
                'retry_after' => $retryAfter,
                'bucket'      => $bucket,
                'method'      => $method,
                'uri'         => $uri,
            ]);
            $this->flash->add('error', $waitMsg);

            // On reste sur la route courante (ou referer)
            $target = is_string($_SERVER['HTTP_REFERER'] ?? null) && $_SERVER['HTTP_REFERER'] !== ''
                ? (string) $_SERVER['HTTP_REFERER']
                : $uri;
            header('Location: ' . $target, true, 302);

            Logger::getLogger('app')->warning('ratelimit_mw_block', [
                'uri'    => $uri,
                'bucket' => $bucket,
            ]);

            return false;
        }

        // Enregistre la tentative et continue
        $limiter->recordAttempt();
        return true;
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
