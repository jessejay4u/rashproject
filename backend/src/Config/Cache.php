<?php

declare(strict_types=1);

namespace App\Config;

class Cache
{
    private string $dir;

    public function __construct()
    {
        $this->dir = dirname(__DIR__, 2) . '/storage/cache/';
        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0755, true);
        }
    }

    public function get(string $key): mixed
    {
        $file = $this->file($key);
        if (!file_exists($file)) return null;

        $data = @unserialize(file_get_contents($file));
        if ($data === false) return null;

        [$value, $exp] = $data;
        if ($exp > 0 && $exp < time()) {
            @unlink($file);
            return null;
        }
        return $value;
    }

    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        $exp  = $ttl > 0 ? time() + $ttl : 0;
        $data = serialize([$value, $exp]);
        return file_put_contents($this->file($key), $data, LOCK_EX) !== false;
    }

    public function setex(string $key, int $ttl, mixed $value): bool
    {
        return $this->set($key, $value, $ttl);
    }

    public function del(string $key): bool
    {
        $file = $this->file($key);
        return file_exists($file) ? @unlink($file) : true;
    }

    public function incr(string $key): int
    {
        $current = (int) ($this->get($key) ?? 0);
        $new     = $current + 1;

        $file = $this->file($key);
        $exp  = 0;
        if (file_exists($file)) {
            $data = @unserialize(file_get_contents($file));
            if ($data !== false) $exp = $data[1];
        }
        file_put_contents($file, serialize([$new, $exp]), LOCK_EX);
        return $new;
    }

    public function expire(string $key, int $ttl): bool
    {
        $value = $this->get($key);
        if ($value === null) return false;
        return $this->set($key, $value, $ttl);
    }

    public function ttl(string $key): int
    {
        $file = $this->file($key);
        if (!file_exists($file)) return -2;

        $data = @unserialize(file_get_contents($file));
        if ($data === false) return -2;

        [$value, $exp] = $data;
        if ($exp === 0) return -1;
        $remaining = $exp - time();
        return $remaining > 0 ? $remaining : -2;
    }

    public function isConnected(): bool
    {
        return true;
    }

    private function file(string $key): string
    {
        return $this->dir . sha1($key) . '.cache';
    }
}
