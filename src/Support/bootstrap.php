<?php
/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

use Dotenv\Dotenv;
use Webman\Config;
use Webman\Route;
use Webman\Middleware;

$worker = $worker ?? null;

if ($timezone = config('app.default_timezone')) {
    date_default_timezone_set($timezone);
}

set_error_handler(function ($level, $message, $file = '', $line = 0, $context = []) {
    if (error_reporting() & $level) {
        throw new ErrorException($message, 0, $level, $file, $line);
    }
});

if ($worker) {
    register_shutdown_function(function ($start_time) {
        if (time() - $start_time <= 1) {
            sleep(1);
        }
    }, time());
}

if (class_exists('Dotenv\Dotenv') && file_exists(base_path() . '/.env')) {
    if (method_exists('Dotenv\Dotenv', 'createUnsafeImmutable')) {
        Dotenv::createUnsafeImmutable(base_path())->load();
    } else {
        Dotenv::createMutable(base_path())->load();
    }
}

Support\App::loadAllConfig(['route']);

foreach (config('autoload.files', []) as $file) {
    include_once $file;
}

Middleware::load(config('middleware', []), '');
foreach (config('plugin', []) as $firm => $projects) {
    foreach ($projects as $name => $project) {
        if (!is_array($project) || $name === 'static') {
            continue;
        }
        Middleware::load($project['middleware'] ?? [], '');
    }
    Middleware::load($projects['middleware'] ?? [], $firm);
    if ($static_middlewares = config("plugin.$firm.static.middleware")) {
        Middleware::load(['__static__' => $static_middlewares], $firm);
    }
}
Middleware::load(['__static__' => config('static.middleware', [])], '');

foreach (config('bootstrap', []) as $class_name) {
    /** @var \Webman\Bootstrap $class_name */
    $class_name::start($worker);
}

foreach (config('plugin', []) as $firm => $projects) {
    foreach ($projects as $name => $project) {
        if (!is_array($project)) {
            continue;
        }
        foreach ($project['bootstrap'] ?? [] as $class_name) {
            /** @var \Webman\Bootstrap $class_name */
            $class_name::start($worker);
        }
    }
    foreach ($projects['bootstrap'] ?? [] as $class_name) {
        /** @var \Webman\Bootstrap $class_name */
        $class_name::start($worker);
    }
}

$paths = [config_path()];
$directory = base_path() . '/plugin';
if (is_dir($directory)) {
    $handle = opendir($directory);
    while (false !== ($entry = readdir($handle))) {
        if ($entry == '.' || $entry == '..') {
            continue;
        }
        $paths[] = $directory . '/' . $entry . '/config';
    }
    closedir($handle);
}

Route::load($paths);
