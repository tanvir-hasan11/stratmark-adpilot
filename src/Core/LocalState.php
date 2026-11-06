<?php
declare(strict_types=1);

namespace App\Core;

final class LocalState
{
    private string $directory;
    private string $file;
    private string $lockFile;

    public function __construct(?string $root = null)
    {
        $root ??= dirname(__DIR__, 2);
        $this->directory = rtrim($root, '/') . '/storage';
        $this->file = $this->directory . '/state.json';
        $this->lockFile = $this->directory . '/state.lock';
        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0750, true);
        }
    }

    public function all(): array
    {
        if (!is_readable($this->file)) {
            return $this->defaults();
        }
        $handle = fopen($this->file, 'rb');
        if ($handle === false) {
            return $this->defaults();
        }
        try {
            flock($handle, LOCK_SH);
            $data = json_decode((string)stream_get_contents($handle), true);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }
        return is_array($data ?? null) ? array_replace_recursive($this->defaults(), $data) : $this->defaults();
    }

    public function save(array $data): void
    {
        if (!is_dir($this->directory) && !@mkdir($this->directory, 0750, true)) {
            throw new \RuntimeException('State directory is not writable.');
        }
        $lock = fopen($this->lockFile, 'c');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            throw new \RuntimeException('Could not lock local state.');
        }
        try {
            $tmp = $this->file . '.tmp-' . bin2hex(random_bytes(8));
            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $written = file_put_contents($tmp, $json . PHP_EOL, LOCK_EX);
            if ($written === false || !@rename($tmp, $this->file)) {
                @unlink($tmp);
                throw new \RuntimeException('Could not save local state.');
            }
            @chmod($this->file, 0600);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function update(callable $callback): array
    {
        $lock = fopen($this->lockFile, 'c');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            throw new \RuntimeException('Could not lock local state.');
        }
        try {
            $data = $this->readUnlocked();
            $next = $callback($data);
            if (!is_array($next)) {
                throw new \RuntimeException('Invalid state update.');
            }
            $tmp = $this->file . '.tmp-' . bin2hex(random_bytes(8));
            $json = json_encode($next, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            if (file_put_contents($tmp, $json . PHP_EOL, LOCK_EX) === false || !@rename($tmp, $this->file)) {
                @unlink($tmp);
                throw new \RuntimeException('Could not save local state.');
            }
            @chmod($this->file, 0600);
            return array_replace_recursive($this->defaults(), $next);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function readUnlocked(): array
    {
        if (!is_readable($this->file)) return $this->defaults();
        $data = json_decode((string)file_get_contents($this->file), true);
        return is_array($data) ? array_replace_recursive($this->defaults(), $data) : $this->defaults();
    }

    public function defaults(): array
    {
        return [
            'brand' => ['name' => 'Stratmark Adpilot', 'logo' => null],
            'settings' => ['currency' => 'USD', 'timezone' => 'UTC', 'date_range' => '7', 'notifications' => true],
            'providers' => ['meta' => ['enabled' => false, 'account_id' => ''], 'google' => ['enabled' => false, 'customer_id' => '']],
            'demo_mode' => false,
            'demo' => ['spend' => 0, 'conversions' => 0, 'revenue' => 0, 'campaigns' => 0],
            'updated_at' => null,
        ];
    }
}
