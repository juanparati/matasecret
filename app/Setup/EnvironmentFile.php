<?php

namespace App\Setup;

use InvalidArgumentException;
use RuntimeException;

/**
 * Builds a .env file from a template, replacing values in place so the template's
 * layout and comments survive.
 */
final class EnvironmentFile
{
    /**
     * @param  list<string>  $lines
     */
    private function __construct(private array $lines) {}

    public static function fromString(string $contents): self
    {
        return new self(preg_split('/\R/', rtrim($contents, "\r\n")) ?: []);
    }

    public static function fromFile(string $path): self
    {
        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Unable to read [{$path}].");
        }

        return self::fromString($contents);
    }

    /**
     * Set a variable. A missing variable is added after the last one sharing its prefix
     * (AUTH_, REDIS_…), or at the end of the file.
     */
    public function set(string $key, string $value): self
    {
        $line = $key.'='.self::quote($value);

        if ($this->has($key)) {
            $this->lines = array_map(
                fn (string $existing): string => self::keyOf($existing) === $key ? $line : $existing,
                $this->lines,
            );

            return $this;
        }

        $prefix = strtok($key, '_').'_';
        $lastRelated = null;

        foreach ($this->lines as $position => $existing) {
            if (str_starts_with((string) self::keyOf($existing), $prefix)) {
                $lastRelated = $position;
            }
        }

        if ($lastRelated === null) {
            array_push($this->lines, '', $line);
        } else {
            array_splice($this->lines, $lastRelated + 1, 0, [$line]);
        }

        return $this;
    }

    /**
     * Remove every variable whose name starts with the given prefix.
     */
    public function forget(string $prefix): self
    {
        $this->lines = array_values(array_filter(
            $this->lines,
            fn (string $line): bool => ! str_starts_with((string) self::keyOf($line), $prefix),
        ));

        return $this;
    }

    public function render(): string
    {
        return preg_replace("/\n{3,}/", "\n\n", implode("\n", $this->lines))."\n";
    }

    /**
     * Write the file atomically with the given permissions, so a reader never sees a
     * half-written file and the credentials are never world-readable.
     */
    public function writeTo(string $path, int $mode): void
    {
        $temporary = dirname($path).'/.'.basename($path).'.'.bin2hex(random_bytes(6)).'.tmp';
        $handle = @fopen($temporary, 'x');

        if ($handle === false) {
            throw new RuntimeException("Unable to create [{$temporary}].");
        }

        try {
            chmod($temporary, $mode);

            if (fwrite($handle, $this->render()) === false || ! fflush($handle)) {
                throw new RuntimeException("Unable to write [{$temporary}].");
            }
        } catch (RuntimeException $exception) {
            fclose($handle);
            @unlink($temporary);

            throw $exception;
        }

        fclose($handle);

        if (! rename($temporary, $path)) {
            @unlink($temporary);

            throw new RuntimeException("Unable to replace [{$path}].");
        }
    }

    /**
     * Quote a value so phpdotenv reads it back exactly: bare when safe, single quotes to
     * prevent ${} interpolation, otherwise double quotes with escapes.
     */
    public static function quote(string $value): string
    {
        if (preg_match('/[\r\n]/', $value) === 1) {
            throw new InvalidArgumentException('Environment values cannot contain line breaks.');
        }

        if ($value === '' || preg_match('/\A[^\s\\\\\'"#$]+\z/', $value) === 1) {
            return $value;
        }

        if (! str_contains($value, "'")) {
            return "'{$value}'";
        }

        return '"'.addcslashes($value, '\\"$').'"';
    }

    private function has(string $key): bool
    {
        foreach ($this->lines as $line) {
            if (self::keyOf($line) === $key) {
                return true;
            }
        }

        return false;
    }

    private static function keyOf(string $line): ?string
    {
        return preg_match('/\A\s*([A-Za-z_][A-Za-z0-9_.]*)\s*=/', $line, $matches) === 1 ? $matches[1] : null;
    }
}
