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

namespace Webman;

use Fiber;
use StdClass;
use WeakMap;
use Workerman\Events\Revolt;
use Workerman\Events\Swoole;
use Workerman\Events\Swow;
use Workerman\Worker;
use function property_exists;

/**
 * Class Context
 * @package Webman
 */
class Context
{

    /**
     * @var WeakMap|null
     */
    protected static ?WeakMap $objectStorage = null;

    /**
     * @var StdClass
     */
    protected static ?stdClass $object = null;

    /**
     * @return void
     */
    public static function init(): void
    {
        if (!static::$objectStorage) {
            static::$objectStorage = new WeakMap();
            static::$object = new StdClass;
        }
    }

    /**
     * @return StdClass
     */
    protected static function getObject(): StdClass
    {
        $key = static::getKey();
        if (!isset(static::$objectStorage[$key])) {
            static::$objectStorage[$key] = new StdClass;
        }
        return static::$objectStorage[$key];
    }

    /**
     * @return mixed
     */
    protected static function getKey()
    {
        return match (Worker::$eventLoopClass) {
            Revolt::class => Fiber::getCurrent() ?: static::$object,
            Swoole::class => \Swoole\Coroutine::getContext(),
            Swow::class => \Swow\Coroutine::getCurrent(),
            default => static::$object,
        };
    }

    /**
     * @param string|null $key
     * @return mixed
     */
    public static function get(?string $key = null): mixed
    {
        $obj = static::getObject();
        if ($key === null) {
            return $obj;
        }
        return $obj->$key ?? null;
    }

    /**
     * @param string $key
     * @param $value
     * @return void
     */
    public static function set(string $key, $value): void
    {
        $obj = static::getObject();
        $obj->$key = $value;
    }

    /**
     * @param string $key
     * @return void
     */
    public static function delete(string $key): void
    {
        $obj = static::getObject();
        unset($obj->$key);
    }

    /**
     * @param string $key
     * @return bool
     */
    public static function has(string $key): bool
    {
        $obj = static::getObject();
        return property_exists($obj, $key);
    }

    /**
     * @param $callback
     * @return void
     */
    public static function onDestroy($callback): void
    {
        $obj = static::getObject();
        $obj->destroyCallbacks[] = $callback;
    }

    /**
     * @return void
     */
    public static function destroy(): void
    {
        $obj = static::getObject();
        if (isset($obj->destroyCallbacks)) {
            foreach ($obj->destroyCallbacks as $callback) {
                $callback();
            }
        }
        unset(static::$objectStorage[static::getKey()]);
    }
}
