<?php
declare(strict_types=1);

namespace App\Core;

final class Env
{
    private array $values = [];

    public function __construct(string $path)
    {
        if (is_readable($path)) {
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                    continue;
                }
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                if (strlen($value) >= 2 && (($value[0] === '"' && $value[-1] === '"') || ($value[0] === "'" && $value[-1] === "'"))) {
                    $value = substr($value, 1, -1);
                }
                $this->values[$key] = $value;
            }
        }
    }

    public function get(string $key, ?string $default = null): ?string
    {
        if (array_key_exists($key, $this->values)) {
            return $this->values[$key];
        }
        $value = $_ENV[$key] ?? getenv($key);
        return $value === false || $value === null ? $default : (string)$value;
    }

    public function required(string $key): string
    {
        $value = $this->get($key);
        if ($value === null || trim($value) === '') {
            throw new \RuntimeException('Missing required environment variable: ' . $key);
        }
        return $value;
    }
}
