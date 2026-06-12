<?php

declare(strict_types=1);

namespace App\Libraries;

use CodeIgniter\Cache\Handlers\BaseHandler;
use Config\Cache;

/**
 * Custom APCu cache handler for CodeIgniter 4.5.x
 */
class APCuHandler extends BaseHandler
{
    /**
     * Constructor
     */
    public function __construct(Cache $config)
    {
        $this->prefix = (string) $config->prefix;
    }

    /**
     * {@inheritDoc}
     */
    public function initialize()
    {
        // No-op
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $key)
    {
        $key = static::validateKey($key, $this->prefix);
        $success = false;
        $data = apcu_fetch($key, $success);
        if ($success) {
            return $data;
        }
        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function save(string $key, $value, int $ttl = 60)
    {
        $key = static::validateKey($key, $this->prefix);
        return apcu_store($key, $value, $ttl);
    }

    /**
     * {@inheritDoc}
     */
    public function delete(string $key)
    {
        $key = static::validateKey($key, $this->prefix);
        return apcu_delete($key);
    }

    /**
     * {@inheritDoc}
     */
    public function increment(string $key, int $offset = 1)
    {
        $key = static::validateKey($key, $this->prefix);
        return apcu_inc($key, $offset);
    }

    /**
     * {@inheritDoc}
     */
    public function decrement(string $key, int $offset = 1)
    {
        $key = static::validateKey($key, $this->prefix);
        return apcu_dec($key, $offset);
    }

    /**
     * {@inheritDoc}
     */
    public function clean()
    {
        return apcu_clear_cache();
    }

    /**
     * {@inheritDoc}
     */
    public function getCacheInfo()
    {
        return apcu_cache_info();
    }

    /**
     * {@inheritDoc}
     */
    public function getMetaData(string $key)
    {
        $key = static::validateKey($key, $this->prefix);
        $data = apcu_key_info($key);
        if (is_array($data)) {
            $expire = null;
            if (isset($data['ttl']) && $data['ttl'] > 0) {
                $expire = time() + $data['ttl'];
            }
            $mtime = null;
            if (isset($data['mtime'])) {
                $mtime = $data['mtime'];
            }
            return [
                'expire' => $expire,
                'mtime'  => $mtime,
            ];
        }
        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function isSupported(): bool
    {
        $supported = false;
        if (function_exists('apcu_enabled')) {
            if (apcu_enabled()) {
                $supported = true;
            }
        }
        return $supported;
    }
}
