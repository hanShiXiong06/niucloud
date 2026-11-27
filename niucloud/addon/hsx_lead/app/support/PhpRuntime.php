<?php
declare(strict_types=1);

namespace addon\hsx_lead\app\support;

/** PHP 8.0 helpers; no global polyfills, php.ini edits or framework overrides. */
final class PhpRuntime
{
    private static int $scopes = 0;
    private static ?string $previous = null;
    private bool $active = false;

    private function __construct()
    {
        // Keep native SensitiveParameter redaction on PHP 8.2 and newer.
        if (PHP_VERSION_ID >= 80200) return;
        if (!function_exists('ini_get')) {
            throw new \RuntimeException('无法启用凭据日志保护，未执行敏感操作');
        }
        $previous = ini_get('zend.exception_ignore_args');
        if ($previous === false) throw new \RuntimeException('无法核实凭据日志保护，未执行敏感操作');
        if (!self::enabled($previous)) {
            if (!function_exists('ini_set')) throw new \RuntimeException('无法启用凭据日志保护，未执行敏感操作');
            @ini_set('zend.exception_ignore_args', '1');
        }
        $current = ini_get('zend.exception_ignore_args');
        if ($current === false || !self::enabled($current)) {
            throw new \RuntimeException('无法启用凭据日志保护，未执行敏感操作');
        }
        if (self::$scopes === 0) self::$previous = $previous;
        self::$scopes++;
        $this->active = true;
    }

    /** Retain the returned local until sensitive work has returned or unwound. */
    public static function guardSecrets(): self
    {
        return new self();
    }

    public static function isList(array $value): bool
    {
        $index = 0;
        foreach ($value as $key => $unused) {
            if ($key !== $index++) return false;
        }
        return true;
    }

    /** Reference counting also handles overlapping scopes released out of order. */
    public function close(): void
    {
        if (!$this->active) return;
        $this->active = false;
        self::$scopes--;
        if (self::$scopes !== 0) return;
        $previous = self::$previous;
        self::$previous = null;
        // A failed restore leaves the stricter setting enabled; never throw from
        // cleanup or expose an earlier credential-bearing exception.
        if ($previous !== null && function_exists('ini_set')) @ini_set('zend.exception_ignore_args', $previous);
    }

    public function __destruct()
    {
        $this->close();
    }

    private function __clone()
    {
    }

    private static function enabled(string $value): bool
    {
        return in_array(strtolower($value), ['1', 'on', 'yes', 'true'], true);
    }
}
