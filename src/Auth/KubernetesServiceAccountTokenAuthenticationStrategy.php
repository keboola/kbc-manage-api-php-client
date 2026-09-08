<?php

declare(strict_types=1);

namespace Keboola\ManageApi\Auth;

use InvalidArgumentException;
use RuntimeException;

/**
 * Reads the projected Kubernetes service account token on every request so kubelet rotation is picked up.
 *
 * Kubelet rotates the token by atomically swapping the volume's `..data` symlink and PHP's realpath cache
 * can keep resolving the token path into the removed directory, so a read transiently fails although a valid
 * token is on disk. Reads are therefore retried with the stat cache cleared in between.
 * Ported from keboola/platform-libraries libs/php-api-client-base KeboolaServiceAccountAuthenticator (v1.1.2).
 */
final readonly class KubernetesServiceAccountTokenAuthenticationStrategy implements AuthenticationStrategyInterface
{
    public const DEFAULT_MAX_READ_ATTEMPTS = 6;
    public const DEFAULT_RETRY_BASE_DELAY_MICROSECONDS = 40_000;
    private const MAX_RETRY_DELAY_MICROSECONDS = 1_000_000;

    /**
     * @param int $maxReadAttempts Total attempts (1 initial + N-1 retries) before giving up;
     *                             a failed read is re-read once within an attempt.
     * @param int $retryBaseDelayMicroseconds Base backoff, doubled each retry, capped at 1 s.
     */
    public function __construct(
        private string $tokenPath,
        private int $maxReadAttempts = self::DEFAULT_MAX_READ_ATTEMPTS,
        private int $retryBaseDelayMicroseconds = self::DEFAULT_RETRY_BASE_DELAY_MICROSECONDS,
    ) {
        if (trim($tokenPath) === '') {
            throw new InvalidArgumentException('Kubernetes service account token path must not be empty');
        }
        if ($maxReadAttempts < 1) {
            throw new InvalidArgumentException('maxReadAttempts must be at least 1');
        }
        if ($retryBaseDelayMicroseconds < 0) {
            throw new InvalidArgumentException('retryBaseDelayMicroseconds must not be negative');
        }
    }

    /**
     * @return array<string, string>
     */
    public function getAuthenticationHeaders(): array
    {
        return [
            'X-Kubernetes-Authorization' => 'Bearer ' . $this->readToken(),
        ];
    }

    private function readToken(): string
    {
        $attempt = 0;
        while (true) {
            $raw = @file_get_contents($this->tokenPath);
            if ($raw === false) {
                $this->clearStatCache();
                if (!is_readable($this->tokenPath)) {
                    throw new RuntimeException(sprintf(
                        'Kubernetes service account token file "%s" is not readable',
                        $this->tokenPath,
                    ));
                }
                $raw = @file_get_contents($this->tokenPath);
            }

            $token = $raw === false ? '' : trim($raw);
            if ($token !== '') {
                return $token;
            }

            if (++$attempt >= $this->maxReadAttempts) {
                if ($raw === false) {
                    throw new RuntimeException(sprintf(
                        'Failed to read Kubernetes service account token file "%s"',
                        $this->tokenPath,
                    ));
                }
                throw new RuntimeException(sprintf(
                    'Kubernetes service account token file is empty: "%s"',
                    $this->tokenPath,
                ));
            }

            usleep($this->backoffMicroseconds($attempt));
            $this->clearStatCache();
        }
    }

    private function backoffMicroseconds(int $attempt): int
    {
        $exponent = $attempt > 1 ? $attempt - 1 : 0;
        $delay = $this->retryBaseDelayMicroseconds * (2 ** $exponent);

        return max(0, (int) min($delay, self::MAX_RETRY_DELAY_MICROSECONDS));
    }

    /**
     * The projected token is `token -> ..data/token`: the path, the link target and its directory can all be stale.
     */
    private function clearStatCache(): void
    {
        clearstatcache(true, $this->tokenPath);
        if (!is_link($this->tokenPath)) {
            return;
        }

        $target = @readlink($this->tokenPath);
        if ($target === false || $target === '') {
            return;
        }

        $resolved = str_starts_with($target, '/') ? $target : dirname($this->tokenPath) . '/' . $target;
        clearstatcache(true, $resolved);
        clearstatcache(true, dirname($resolved));
    }
}
