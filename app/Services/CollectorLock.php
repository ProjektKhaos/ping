<?php

declare(strict_types=1);

namespace PingFloodWatch\Services;

use PingFloodWatch\Config;

final class CollectorLock
{
    /** @var resource|null */
    private $handle = null;

    public function acquire(string $name): bool
    {
        $path = rtrim((string) Config::get('storage.locks'), '/') . '/' . preg_replace('/[^a-z0-9_-]/i', '-', $name) . '.lock';
        $this->handle = fopen($path, 'c');
        return is_resource($this->handle) && flock($this->handle, LOCK_EX | LOCK_NB);
    }

    public function __destruct()
    {
        if (is_resource($this->handle)) {
            flock($this->handle, LOCK_UN);
            fclose($this->handle);
        }
    }
}
