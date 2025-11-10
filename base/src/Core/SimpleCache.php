<?php

declare(strict_types=1);

namespace CIS\Base\Core;

use function strlen;

use const LOCK_EX;

class SimpleCache
{
    private string $dir;

    public function __construct(string $dir)
    {
        $this->dir = rtrim($dir, '/') . '/';
        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0755, true);
        }
    }

    public function get(string $key)
    {
        $p = $this->path($key);
        if (!file_exists($p)) {
            return null;
        }
        $data = @file_get_contents($p);
        if ($data === false) {
            return null;
        }
        $metaLen = unpack('N', substr($data, 0, 4))[1];
        $meta    = substr($data, 4, $metaLen);
        $payload = substr($data, 4 + $metaLen);
        $metaArr = json_decode($meta, true);
        if (!$metaArr) {
            return null;
        }
        if ($metaArr['expires_at'] !== null && time() > $metaArr['expires_at']) {
            @unlink($p);

            return null;
        }

        return unserialize($payload);
    }

    public function set(string $key, $value, int $ttl = 3600): bool
    {
        $p        = $this->path($key);
        $meta     = ['expires_at' => $ttl > 0 ? time() + $ttl : null];
        $metaJson = json_encode($meta);
        $metaLen  = pack('N', strlen($metaJson));
        $payload  = serialize($value);

        return (bool) file_put_contents($p, $metaLen . $metaJson . $payload, LOCK_EX);
    }

    public function delete(string $key): bool
    {
        $p = $this->path($key);
        if (file_exists($p)) {
            return (bool) unlink($p);
        }

        return true;
    }

    public function clear(): bool
    {
        $files = glob($this->dir . '*.cache');
        $ok    = true;
        foreach ($files as $f) {
            $ok = $ok && @unlink($f);
        }

        return $ok;
    }

    private function path(string $key): string
    {
        $hash = hash('sha256', $key);

        return $this->dir . $hash . '.cache';
    }
}
