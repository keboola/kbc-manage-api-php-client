<?php

declare(strict_types=1);

namespace Keboola\ManageApi\Tests\Auth;

use InvalidArgumentException;
use Keboola\ManageApi\Auth\KubernetesServiceAccountTokenAuthenticationStrategy;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class KubernetesServiceAccountTokenAuthenticationStrategyTest extends TestCase
{
    private const OLD_DATA_DIR = '..2026_09_08_07_00_00.000000001';
    private const NEW_DATA_DIR = '..2026_09_08_07_39_20.000000002';

    /** @var list<string> */
    private array $volumeDirs = [];

    protected function tearDown(): void
    {
        ScriptedTokenFileStreamWrapper::unregister();
        foreach ($this->volumeDirs as $volumeDir) {
            $this->removeDirectory($volumeDir);
        }
        $this->volumeDirs = [];

        parent::tearDown();
    }

    public function testReadsTokenThroughProjectedVolumeSymlinkChain(): void
    {
        $volumeDir = $this->createProjectedTokenVolume('token-a');

        $strategy = new KubernetesServiceAccountTokenAuthenticationStrategy($volumeDir . '/token');

        self::assertSame(
            ['X-Kubernetes-Authorization' => 'Bearer token-a'],
            $strategy->getAuthenticationHeaders(),
        );
    }

    public function testRereadsTokenOnEveryCall(): void
    {
        $volumeDir = $this->createProjectedTokenVolume('token-a');
        $strategy = new KubernetesServiceAccountTokenAuthenticationStrategy($volumeDir . '/token');

        self::assertSame(['X-Kubernetes-Authorization' => 'Bearer token-a'], $strategy->getAuthenticationHeaders());

        $this->writeTimestampedTokenDir($volumeDir, self::NEW_DATA_DIR, 'token-b');
        symlink(self::NEW_DATA_DIR, $volumeDir . '/..data_tmp');
        rename($volumeDir . '/..data_tmp', $volumeDir . '/..data');

        self::assertSame(['X-Kubernetes-Authorization' => 'Bearer token-b'], $strategy->getAuthenticationHeaders());
    }

    public function testRecoversFromStaleRealpathCacheAfterKubeletRotation(): void
    {
        $volumeDir = $this->createProjectedTokenVolume('token-a');
        $this->writeTimestampedTokenDir($volumeDir, self::NEW_DATA_DIR, 'token-b');
        symlink(self::NEW_DATA_DIR, $volumeDir . '/..data_tmp');
        $tokenPath = $volumeDir . '/token';
        $strategy = new KubernetesServiceAccountTokenAuthenticationStrategy($tokenPath);

        // Primes PHP's realpath cache with the pre-rotation resolution.
        self::assertSame(['X-Kubernetes-Authorization' => 'Bearer token-a'], $strategy->getAuthenticationHeaders());

        $this->rotateFromExternalProcess($volumeDir);
        $this->assertStaleRealpathCacheBreaksPlainRead($tokenPath);

        self::assertSame(['X-Kubernetes-Authorization' => 'Bearer token-b'], $strategy->getAuthenticationHeaders());
    }

    public function testRetriesImmediatelyAfterTransientFailedRead(): void
    {
        $tokenPath = ScriptedTokenFileStreamWrapper::register([
            ScriptedTokenFileStreamWrapper::OPEN_FAILS,
            "token-after-retry\n",
        ]);

        $strategy = new KubernetesServiceAccountTokenAuthenticationStrategy($tokenPath);

        self::assertSame(
            ['X-Kubernetes-Authorization' => 'Bearer token-after-retry'],
            $strategy->getAuthenticationHeaders(),
        );
        self::assertSame(2, ScriptedTokenFileStreamWrapper::$openCount);
    }

    public function testRetriesWhenReadReturnsEmptyContent(): void
    {
        $tokenPath = ScriptedTokenFileStreamWrapper::register(['', "  \n", "token-after-empty-reads\n"]);

        $strategy = new KubernetesServiceAccountTokenAuthenticationStrategy($tokenPath, retryBaseDelayMicroseconds: 0);

        self::assertSame(
            ['X-Kubernetes-Authorization' => 'Bearer token-after-empty-reads'],
            $strategy->getAuthenticationHeaders(),
        );
        self::assertSame(3, ScriptedTokenFileStreamWrapper::$openCount);
    }

    public function testThrowsWhenReadBudgetIsExhaustedByEmptyReads(): void
    {
        $tokenPath = ScriptedTokenFileStreamWrapper::register(['', '', '', 'never-reached']);
        $strategy = new KubernetesServiceAccountTokenAuthenticationStrategy(
            $tokenPath,
            maxReadAttempts: 3,
            retryBaseDelayMicroseconds: 0,
        );

        try {
            $strategy->getAuthenticationHeaders();
            self::fail('Expected RuntimeException');
        } catch (RuntimeException $e) {
            self::assertSame(
                sprintf('Kubernetes service account token file is empty: "%s"', $tokenPath),
                $e->getMessage(),
            );
        }
        self::assertSame(3, ScriptedTokenFileStreamWrapper::$openCount);
    }

    public function testThrowsWhenEveryReadFailsAlthoughFileIsReadable(): void
    {
        $tokenPath = ScriptedTokenFileStreamWrapper::register(array_fill(0, 4, ScriptedTokenFileStreamWrapper::OPEN_FAILS));
        $strategy = new KubernetesServiceAccountTokenAuthenticationStrategy(
            $tokenPath,
            maxReadAttempts: 2,
            retryBaseDelayMicroseconds: 0,
        );

        try {
            $strategy->getAuthenticationHeaders();
            self::fail('Expected RuntimeException');
        } catch (RuntimeException $e) {
            self::assertSame(
                sprintf('Failed to read Kubernetes service account token file "%s"', $tokenPath),
                $e->getMessage(),
            );
        }
        // every attempt is one read plus one immediate re-read
        self::assertSame(4, ScriptedTokenFileStreamWrapper::$openCount);
    }

    public function testFailsFastWithoutRetriesWhenFileIsNotReadable(): void
    {
        $tokenPath = ScriptedTokenFileStreamWrapper::register(
            array_fill(0, 6, ScriptedTokenFileStreamWrapper::OPEN_FAILS),
            readable: false,
        );
        $strategy = new KubernetesServiceAccountTokenAuthenticationStrategy($tokenPath);

        try {
            $strategy->getAuthenticationHeaders();
            self::fail('Expected RuntimeException');
        } catch (RuntimeException $e) {
            self::assertSame(
                sprintf('Kubernetes service account token file "%s" is not readable', $tokenPath),
                $e->getMessage(),
            );
        }
        self::assertSame(1, ScriptedTokenFileStreamWrapper::$openCount);
    }

    public function testFailsFastWhenSymlinkChainIsDangling(): void
    {
        $volumeDir = $this->createProjectedTokenVolume('token-a');
        unlink($volumeDir . '/..data');
        symlink('..missing', $volumeDir . '/..data');

        $strategy = new KubernetesServiceAccountTokenAuthenticationStrategy($volumeDir . '/token');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(sprintf(
            'Kubernetes service account token file "%s" is not readable',
            $volumeDir . '/token',
        ));

        $strategy->getAuthenticationHeaders();
    }

    public function testThrowsWhenTokenFileDoesNotExist(): void
    {
        $strategy = new KubernetesServiceAccountTokenAuthenticationStrategy('/nonexistent/serviceaccount/token');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Kubernetes service account token file "/nonexistent/serviceaccount/token" is not readable',
        );

        $strategy->getAuthenticationHeaders();
    }

    public function testThrowsWhenTokenFileStaysEmpty(): void
    {
        $volumeDir = $this->createProjectedTokenVolume('   ');
        $strategy = new KubernetesServiceAccountTokenAuthenticationStrategy(
            $volumeDir . '/token',
            retryBaseDelayMicroseconds: 0,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(sprintf(
            'Kubernetes service account token file is empty: "%s"',
            $volumeDir . '/token',
        ));

        $strategy->getAuthenticationHeaders();
    }

    public function testRejectsEmptyTokenPath(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Kubernetes service account token path must not be empty');

        new KubernetesServiceAccountTokenAuthenticationStrategy(' ');
    }

    public function testRejectsZeroMaxReadAttempts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('maxReadAttempts must be at least 1');

        new KubernetesServiceAccountTokenAuthenticationStrategy('/fake/token', maxReadAttempts: 0);
    }

    public function testRejectsNegativeRetryDelay(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('retryBaseDelayMicroseconds must not be negative');

        new KubernetesServiceAccountTokenAuthenticationStrategy('/fake/token', retryBaseDelayMicroseconds: -1);
    }

    /**
     * Lays the token out the way kubelet's projected-volume AtomicWriter does:
     * token -> ..data/token and ..data -> ..<timestamp>, so a rotation is a symlink swap.
     */
    private function createProjectedTokenVolume(string $token): string
    {
        $volumeDir = sys_get_temp_dir() . '/kbc-manage-api-sa-token-' . bin2hex(random_bytes(8));
        mkdir($volumeDir);
        $this->volumeDirs[] = $volumeDir;

        $this->writeTimestampedTokenDir($volumeDir, self::OLD_DATA_DIR, $token);
        symlink(self::OLD_DATA_DIR, $volumeDir . '/..data');
        symlink('..data/token', $volumeDir . '/token');

        return $volumeDir;
    }

    private function writeTimestampedTokenDir(string $volumeDir, string $dataDir, string $token): void
    {
        mkdir($volumeDir . '/' . $dataDir);
        file_put_contents($volumeDir . '/' . $dataDir . '/token', $token . "\n");
    }

    /**
     * Kubelet rotates from outside the PHP process, so PHP's own filesystem functions must not
     * perform the swap here: they would flush the very realpath cache entries that break production.
     */
    private function rotateFromExternalProcess(string $volumeDir): void
    {
        $script = sprintf(
            'rename(%s, %s); unlink(%s); rmdir(%s);',
            var_export($volumeDir . '/..data_tmp', true),
            var_export($volumeDir . '/..data', true),
            var_export($volumeDir . '/' . self::OLD_DATA_DIR . '/token', true),
            var_export($volumeDir . '/' . self::OLD_DATA_DIR, true),
        );
        exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($script) . ' 2>&1', $output, $exitCode);

        self::assertSame(0, $exitCode, implode("\n", $output));
        self::assertSame(self::NEW_DATA_DIR, readlink($volumeDir . '/..data'));
    }

    private function assertStaleRealpathCacheBreaksPlainRead(string $tokenPath): void
    {
        if (!$this->isRealpathCacheEnabled()) {
            return;
        }

        self::assertFalse(
            @file_get_contents($tokenPath),
            'A plain read must fail through the stale realpath cache, otherwise the production failure is not exercised',
        );
    }

    private function isRealpathCacheEnabled(): bool
    {
        return ini_parse_quantity((string) ini_get('realpath_cache_size')) > 0
            && (int) ini_get('realpath_cache_ttl') > 0
            && (string) ini_get('open_basedir') === '';
    }

    private function removeDirectory(string $directory): void
    {
        $entries = scandir($directory);
        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $directory . '/' . $entry;
            if (is_dir($path) && !is_link($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($directory);
    }
}
