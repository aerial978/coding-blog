<?php

declare(strict_types=1);

namespace App\Service\OAuth;

use App\Model\Contract\UserModelInterface;
use App\Model\Entity\UserEntity;
use App\Service\OAuth\Contract\OAuthUserProvisioningServiceInterface;
use Cocur\Slugify\Slugify;
use RuntimeException;

final class OAuthUserProvisioningService implements OAuthUserProvisioningServiceInterface
{
    private const MAX_UNIQUE_ATTEMPTS = 20;

    public function __construct(
        private UserModelInterface $userModel,
        private Slugify $slugify,
    ) {
    }

    /**
     * @param array{
     *     id: string,
     *     email: string,
     *     email_verified: bool,
     *     name: string,
     *     avatar: string|null
     * } $profile
     */
    public function provisionFromGoogleProfile(array $profile): ?UserEntity
    {
        if (!$this->isValidProfile($profile)) {
            return null;
        }

        $baseUsername = $this->buildBaseUsername($profile);
        $username     = $this->makeUniqueUsername($baseUsername);
        $slug         = $this->makeUniqueSlug($username);

        $user = (new UserEntity())
            ->setUsername($username)
            ->setSlug($slug)
            ->setEmail($profile['email'])
            ->setPassword($this->generateTechnicalPasswordHash())
            ->setStatus('active')
            ->setEmail2faEnabled(false);

        $userId = $this->userModel->createOAuthUser($user);

        if ($userId <= 0) {
            return null;
        }

        $user->setUserId($userId);

        return $user;
    }

    /**
     * @param array{
     *     id: string,
     *     email: string,
     *     email_verified: bool,
     *     name: string,
     *     avatar: string|null
     * } $profile
     */
    private function isValidProfile(array $profile): bool
    {
        return $profile['id']    !== ''
            && $profile['email'] !== ''
            && $profile['email_verified'] === true;
    }

    /**
     * @param array{
     *     id: string,
     *     email: string,
     *     email_verified: bool,
     *     name: string,
     *     avatar: string|null
     * } $profile
     */
    private function buildBaseUsername(array $profile): string
    {
        $name = trim($profile['name']);

        if ($name !== '') {
            return $this->normalizeUsername($name);
        }

        $emailLocalPart = explode('@', $profile['email'])[0];

        return $this->normalizeUsername($emailLocalPart);
    }

    private function normalizeUsername(string $value): string
    {
        $slug = $this->slugify->slugify($value);

        $username = str_replace('-', '_', $slug);
        $username = preg_replace('/[^a-zA-Z0-9_]/', '', $username) ?? '';
        $username = trim($username, '_');

        if ($username === '') {
            return 'user';
        }

        if (strlen($username) < 3) {
            return str_pad($username, 3, '_');
        }

        return substr($username, 0, 20);
    }

    private function makeUniqueUsername(string $baseUsername): string
    {
        $baseUsername = substr($baseUsername, 0, 20);

        if ($this->userModel->findOneByUsername($baseUsername) === null) {
            return $baseUsername;
        }

        for ($i = 2; $i <= self::MAX_UNIQUE_ATTEMPTS; $i++) {
            $suffixLength = strlen((string) $i) + 1;
            $candidate    = substr($baseUsername, 0, 20 - $suffixLength) . '_' . $i;

            if ($this->userModel->findOneByUsername($candidate) === null) {
                return $candidate;
            }
        }

        throw new RuntimeException('Unable to generate unique OAuth username.');
    }

    private function makeUniqueSlug(string $username): string
    {
        $baseSlug = $this->slugify->slugify($username);

        if ($this->isSlugAvailable($baseSlug)) {
            return $baseSlug;
        }

        for ($i = 2; $i <= self::MAX_UNIQUE_ATTEMPTS; $i++) {
            $suffixLength = strlen((string) $i) + 1;
            $candidate    = substr($baseSlug, 0, 191 - $suffixLength) . '-' . $i;

            if ($this->isSlugAvailable($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('Unable to generate unique OAuth slug.');
    }

    private function isSlugAvailable(string $slug): bool
    {
        return $this->userModel->findOneBySlug($slug) === null;
    }

    private function generateTechnicalPasswordHash(): string
    {
        $randomPassword = bin2hex(random_bytes(32));

        return password_hash($randomPassword, PASSWORD_DEFAULT);
    }
}
