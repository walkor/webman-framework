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

namespace support\bootstrap;

use Webman\Bootstrap;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;
use Illuminate\Database\Connection;
use Jenssegers\Mongodb\Connection as MongodbConnection;
use Workerman\Worker;
use Workerman\Timer;
use support\Db;

/**
 * Class Laravel
 * @package support\bootstrap
 */
class LaravelDb implements Bootstrap
{
    /**
     * @param Worker $worker
     *
     * @return void
     */
    public static function start($worker)
    {
        if (!class_exists(Capsule::class)) {
            return;
        }

        $connections = config('database.connections');
        if (!$connections) {
            return;
        }

        $capsule = new Capsule;
        $configs = config('database');

        $capsule->getDatabaseManager()->extend('mongodb', function ($config, $name) {
            $config['name'] = $name;

            return new MongodbConnection($config);
        });

        if (isset($configs['default'])) {
            $default_config = $connections[$configs['default']];
            $capsule->addConnection($default_config);
        }

        foreach ($connections as $name => $config) {
            $capsule->addConnection($config, $name);
        }

        if (class_exists(Dispatcher::class)) {
            $capsule->setEventDispatcher(new Dispatcher(new Container));
        }

        $capsule->setAsGlobal();

        $capsule->bootEloquent();

        // Heartbeat
        if ($worker) {
            Timer::add(55, function () use ($connections) {
                if (!class_exists(Connection::class, false)) {
                    return;
                }
                foreach ($connections as $key => $item) {
                    if ($item['driver'] == 'mysql') {
                        try {
                            Db::connection($key)->select('select 1');
                        } catch (\Throwable $e) {}
                    }
                }
            });
        }
    }
}
