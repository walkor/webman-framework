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

use ArrayObject;
use Fiber;
use SplObjectStorage;
use StdClass;
use Swow\Coroutine;
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
     * @var SplObjectStorage;
     */
    protected static $objectStorage;

    /**
     * @var StdClass
     */
    protected static $object;

    /**
     * @return StdClass
     */
    protected static function getObject(): StdClass
    {
        if (!static::$objectStorage) {
            static::$objectStorage = new SplObjectStorage();
            static::$object = new StdClass;
        }
        $coroutine = static::getCoroutine();
        if (!$coroutine) {
            return static::$object;
        }
        if (!isset(static::$objectStorage[$coroutine])) {
            static::$objectStorage[$coroutine] = new StdClass;
        }
        return static::$objectStorage[$coroutine];
    }

    /**
     * @return ArrayObject|Fiber|Coroutine|null
     */
    protected static function getCoroutine()
    {
        switch (Worker::$eventLoopClass) {
            case Revolt::class:
                return Fiber::getCurrent();
            case Swoole::class:
                return \Swoole\Coroutine::getContext();
            case Swow::class:
                return Coroutine::getCurrent();
        }
        return null;
    }

    /**
     * @param string|null $key
     * @return mixed
     */
    public static function get(string $key = null)
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
    public static function set(string $key, $value)
    {
        $obj = static::getObject();
        $obj->$key = $value;
    }

    /**
     * @param string $key
     * @return void
     */
    public static function delete(string $key)
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
     * @return void
     */
    public static function destroy()
    {
        static::$object = new StdClass;
        $coroutine = static::getCoroutine();
        if ($coroutine) {
            unset(static::$objectStorage[$coroutine]);
        }
    }
}
