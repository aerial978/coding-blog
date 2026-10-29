<?php

declare(strict_types=1);

namespace App\Service\Account;

use App\Core\ErrorCode;
use App\Core\Logger;
use App\Model\Contract\UserModelInterface;
use App\Security\Contract\AuthCheckerInterface;
use App\Service\Account\Contract\AccountUsernameUpdateServiceInterface;
use App\Validation\Contract\FormValidatorInterface;
use Cocur\Slugify\Slugify;
use PDOException;
use Throwable;

final class AccountUsernameUpdateService implements AccountUsernameUpdateServiceInterface
{
    private const MAX_UNIQUE_ATTEMPTS = 20;

    public function __construct(
        private AuthCheckerInterface $authChecker,
        private UserModelInterface $userModel,
        private FormValidatorInterface $validator,
        private Slugify $slugify
    ) {
    }

    /**
     * @param array<string, mixed> $form
     * @return array{ok: true}|array{errors: array<string, string>}
     */
    public function update(array $form): array
    {
        $userId = $this->authChecker->getUserId();

        if ($userId === null) {
            return $this->failure(ErrorCode::ACCOUNT_USER_NOT_FOUND);
        }

        $username = is_string($form['username'] ?? null)
            ? trim($form['username'])
            : '';

        $errors = $this->validator->validateUsernameUpdate([
            'username' => $username,
        ]);

        if ($errors !== []) {
            return ['errors' => $errors];
        }

        try {
            $user = $this->userModel->findOneById($userId);

            if ($user === null) {
                return $this->failure(ErrorCode::ACCOUNT_USER_NOT_FOUND);
            }

            $errorCode = $this->checkUsername($userId, $user->getUsername(), $username);

            if ($errorCode !== null) {
                return $this->failure($errorCode);
            }

            $baseSlug = $this->slugify->slugify($username);

            return $this->persistUsername($userId, $username, $baseSlug);
        } catch (Throwable $e) {
            Logger::logCodeAndGetMessage(
                'account',
                'error',
                ErrorCode::ACCOUNT_USERNAME_UPDATE_FAILED,
                [
                    'user_id'   => $userId,
                    'exception' => $e->getMessage(),
                ]
            );

            return $this->failure(ErrorCode::ACCOUNT_USERNAME_UPDATE_FAILED);
        }
    }

    private function checkUsername(
        int $userId,
        ?string $currentUsername,
        string $newUsername
    ): ?string {
        if ($currentUsername === $newUsername) {
            return ErrorCode::ACCOUNT_USERNAME_UNCHANGED;
        }

        $existingUser = $this->userModel->findOneByUsername($newUsername);

        if (
            $existingUser                 !== null
            && $existingUser->getUserId() !== $userId
        ) {
            return ErrorCode::ACCOUNT_USERNAME_EXISTS;
        }

        return null;
    }

    /**
     * @return array{ok: true}|array{errors: array<string, string>}
     */
    private function persistUsername(
        int $userId,
        string $username,
        string $baseSlug
    ): array {
        for ($i = 1; $i <= self::MAX_UNIQUE_ATTEMPTS; $i++) {
            $slug = $i === 1
                ? $baseSlug
                : $baseSlug . '-' . $i;

            if (!$this->isSlugAvailable($slug, $userId)) {
                continue;
            }

            try {
                if ($this->userModel->updateUsername($userId, $username, $slug)) {
                    return ['ok' => true];
                }

                return $this->failure(ErrorCode::ACCOUNT_USERNAME_UPDATE_FAILED);
            } catch (PDOException $e) {
                $errorCode = $this->handleDuplicateKey($e);

                if ($errorCode !== null) {
                    return $this->failure($errorCode);
                }

                // Another request may have claimed this slug.
                // Retry with the next candidate.
            }
        }

        return $this->failure(ErrorCode::ACCOUNT_USERNAME_UPDATE_FAILED);
    }

    /**
     * Returns an error code for a username collision.
     * Returns null when a slug collision allows a retry.
     *
     * @throws PDOException
     */
    private function handleDuplicateKey(PDOException $exception): ?string
    {
        if (!$this->isDuplicateKeyException($exception)) {
            throw $exception;
        }

        $duplicateIndex = $this->classifyDuplicateIndex($exception);

        if ($duplicateIndex === 'username') {
            return ErrorCode::ACCOUNT_USERNAME_EXISTS;
        }

        if ($duplicateIndex !== 'slug') {
            throw $exception;
        }

        return null;
    }

    private function isSlugAvailable(string $slug, int $userId): bool
    {
        $existingUser = $this->userModel->findOneBySlug($slug);

        return $existingUser              === null
            || $existingUser->getUserId() === $userId;
    }

    private function isDuplicateKeyException(PDOException $exception): bool
    {
        $sqlState   = $exception->errorInfo[0] ?? $exception->getCode();
        $driverCode = $exception->errorInfo[1] ?? null;

        return $sqlState === '23000'
            && (is_int($driverCode) || is_string($driverCode))
            && (int) $driverCode === 1062;
    }

    /**
     * Identifies the unique index responsible for a duplicate-key error.
     *
     * @return 'username'|'slug'|null
     */
    private function classifyDuplicateIndex(PDOException $exception): ?string
    {
        $errorInfo = $exception->errorInfo;

        $message = is_array($errorInfo)
            && isset($errorInfo[2])
            && is_string($errorInfo[2])
                ? $errorInfo[2]
                : $exception->getMessage();

        $message = strtolower($message);

        if (str_contains($message, 'username')) {
            return 'username';
        }

        if (str_contains($message, 'slug')) {
            return 'slug';
        }

        return null;
    }

    /**
     * @return array{errors: array<string, string>}
     */
    private function failure(string $code): array
    {
        return ['errors' => ['username' => $code]];
    }
}
