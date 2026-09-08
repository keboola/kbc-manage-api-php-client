<?php

declare(strict_types=1);

namespace Keboola\ManageApi\Tests\Auth;

use RuntimeException;

// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps -- stream wrapper hooks are named by PHP

/**
 * Stream wrapper whose open() outcomes are scripted, so the token read/retry loop can be driven deterministically.
 */
final class ScriptedTokenFileStreamWrapper
{
    public const SCHEME = 'kbc-scripted-token';
    public const OPEN_FAILS = false;

    /** @var list<string|false> */
    public static array $openOutcomes = [];

    public static int $openCount = 0;

    public static bool $readable = true;

    /** @var resource|null */
    public $context;

    private string $buffer = '';

    private int $position = 0;

    /**
     * @param list<string|false> $openOutcomes Outcome per open() call: file content, or OPEN_FAILS.
     */
    public static function register(array $openOutcomes, bool $readable = true): string
    {
        self::unregister();
        self::$openOutcomes = $openOutcomes;
        self::$openCount = 0;
        self::$readable = $readable;
        if (!stream_wrapper_register(self::SCHEME, self::class)) {
            throw new RuntimeException(sprintf('Failed to register the "%s" stream wrapper', self::SCHEME));
        }

        return self::SCHEME . '://serviceaccount/token';
    }

    public static function unregister(): void
    {
        if (in_array(self::SCHEME, stream_get_wrappers(), true)) {
            stream_wrapper_unregister(self::SCHEME);
        }
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        self::$openCount++;
        $outcome = array_shift(self::$openOutcomes);
        if ($outcome === null || $outcome === self::OPEN_FAILS) {
            return false;
        }

        $this->buffer = $outcome;
        $this->position = 0;

        return true;
    }

    public function stream_read(int $count): string
    {
        $chunk = substr($this->buffer, $this->position, $count);
        $this->position += strlen($chunk);

        return $chunk;
    }

    public function stream_eof(): bool
    {
        return $this->position >= strlen($this->buffer);
    }

    public function stream_close(): void
    {
    }

    /**
     * @return array<string, int>
     */
    public function stream_stat(): array
    {
        return self::regularFileStat(strlen($this->buffer));
    }

    /**
     * @return array<string, int>|false
     */
    public function url_stat(string $path, int $flags): array|false
    {
        return self::$readable ? self::regularFileStat(0) : false;
    }

    /**
     * @return array<string, int>
     */
    private static function regularFileStat(int $size): array
    {
        return [
            'dev' => 0,
            'ino' => 0,
            'mode' => 0100644,
            'nlink' => 1,
            'uid' => 0,
            'gid' => 0,
            'rdev' => 0,
            'size' => $size,
            'atime' => 0,
            'mtime' => 0,
            'ctime' => 0,
            'blksize' => -1,
            'blocks' => -1,
        ];
    }
}
