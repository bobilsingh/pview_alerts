# APCu Installation and Setup Guide

This document details the exact steps followed to compile, install, and configure the **APCu (Alternative PHP Cache User)** extension on your AWS Linux server.

---

## Server Environment Details
* **OS:** AWS Linux / Amazon Linux
* **User Profile:** `root`
* **Setup Directory:** `/home/pview/setup/`
* **Target PHP Version:** `PHP 8.1.29` (Custom source installation)
* **Custom PHP Base Path:** `/home/pview/apache_pview/php`
* **Custom phpize Path:** `/home/pview/apache_pview/php/bin/phpize`
* **Custom php-config Path:** `/home/pview/apache_pview/php/bin/php-config`
* **Active php.ini Path:** `/home/pview/apache_pview/php/php.ini`
* **Target Extensions Directory:** `/home/pview/apache_pview/php/lib/php/extensions/no-debug-zts-20210902/`

---

## Step-by-Step Installation Instructions

### Step 1: Download the Source Code
Log in as the `root` user and navigate to your setup folder, then download APCu version **5.1.24** (fully compatible with PHP 8.1):

```bash
cd /home/pview/setup
wget https://pecl.php.net/get/apcu-5.1.24.tgz
```

### Step 2: Extract the Package
Decompress the downloaded archive and enter the newly created source directory:

```bash
tar -xvzf apcu-5.1.24.tgz
cd apcu-5.1.24
```

### Step 3: Run phpize (Build Preparation)
Run the custom `phpize` binary corresponding to your target PHP 8.1 environment. This generates the `./configure` script:

```bash
/home/pview/apache_pview/php/bin/phpize
```
*Expected Output:*
```text
Configuring for:
PHP Api Version:         20210902
Zend Module Api No:      20210902
Zend Extension Api No:   420210902
```

### Step 4: Configure the Build
Configure the compilation settings by pointing directly to your custom `php-config` path. This ensures that the extension compiles correctly for your PHP 8.1.29 setup and not any system-default PHP version:

```bash
./configure --with-php-config=/home/pview/apache_pview/php/bin/php-config
```

### Step 5: Compile and Install
Build the extension binary and copy it to your custom PHP extension library folder:

```bash
make && make install
```
*Expected Output at End of Build:*
```text
Installing shared extensions:     /home/pview/apache_pview/php/lib/php/extensions/no-debug-zts-20210902/
Installing header files:          /home/pview/apache_pview/php/include/php/
```

---

## Step 6: Enable in php.ini Configuration

1. Open your active configuration file in an editor:
   ```bash
   vi /home/pview/apache_pview/php/php.ini
   ```
2. Navigate to the very bottom of the file (press `G` in `vi`), switch to insert mode (press `i`), and append the following configuration block:
   ```ini
   extension="/home/pview/apache_pview/php/lib/php/extensions/no-debug-zts-20210902/apcu.so"
   apc.enabled=1
   apc.enable_cli=1
   ```
3. Save the changes and quit (press `Esc`, then type `:wq` and press `Enter`).

---

## Step 7: Restart Web Server Services
Restart the PHP-FPM service (or your Apache service) to apply the configuration:

```bash
# If running php-fpm
systemctl restart php-fpm

# If running Apache httpd
systemctl restart httpd
```

---

## Step 8: Verify the Installation
Check that the APCu module is correctly loaded into your active PHP runtime:

```bash
/home/pview/apache_pview/php/bin/php -m | grep apc
```
*Successful Output:*
```text
apcu
```

---

## Step 9: Configure CodeIgniter 4.5.8 Application

Because CodeIgniter 4.5.8 does not ship with a native APCu cache handler in its core system files, we created a custom handler.

### 1. Create the custom handler file:
Create a file at `app/Libraries/APCuHandler.php` with the following content:
```php
<?php

declare(strict_types=1);

namespace App\Libraries;

use CodeIgniter\Cache\Handlers\BaseHandler;
use Config\Cache;

class APCuHandler extends BaseHandler
{
    public function __construct(Cache $config)
    {
        $this->prefix = (string) $config->prefix;
    }

    public function initialize() {}

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

    public function save(string $key, $value, int $ttl = 60)
    {
        $key = static::validateKey($key, $this->prefix);
        return apcu_store($key, $value, $ttl);
    }

    public function delete(string $key)
    {
        $key = static::validateKey($key, $this->prefix);
        return apcu_delete($key);
    }

    public function increment(string $key, int $offset = 1)
    {
        $key = static::validateKey($key, $this->prefix);
        return apcu_inc($key, $offset);
    }

    public function decrement(string $key, int $offset = 1)
    {
        $key = static::validateKey($key, $this->prefix);
        return apcu_dec($key, $offset);
    }

    public function clean()
    {
        return apcu_clear_cache();
    }

    public function getCacheInfo()
    {
        return apcu_cache_info();
    }

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
```

### 2. Register the custom handler in config:
Open `app/Config/Cache.php`, import the custom handler, and add it to the `$validHandlers` array:
```php
// Add this import near the top:
use App\Libraries\APCuHandler;

// Update the validHandlers array:
public array $validHandlers = [
    'apcu'      => APCuHandler::class,
    'dummy'     => DummyHandler::class,
    ...
];
```

### 3. Set the handler in .env:
Add the following line to the `.env` file in the root of the project directory on the server:
```env
cache.handler = 'apcu'
```
*(If APCu is not supported or not loaded on a system like your local environment, CodeIgniter will automatically fall back to the backup handler without crashing).*
