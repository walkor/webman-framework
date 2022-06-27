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

namespace support;

use Psr\Container\ContainerInterface;

/**
 * Class Container
 * @package support
 * @method static mixed get($name)
 * @method static mixed make($name, array $parameters)
 * @method static bool has($name)
 */
class Container
{
    /**
     * @var ContainerInterface[]
     */
    protected static $_instance = [];

    /**
     * @return ContainerInterface
     */
    public static function instance($plugin = null)
    {
        $plugin = $plugin ?? '';
        if (!isset(static::$_instance[$plugin])) {
            if ($plugin === '') {
                static::$_instance[$plugin] = include config_path() . '/container.php';
            } else {
                static::$_instance[$plugin] =  base_path() . "/plugin/$plugin/config/container.php";
            }
        }
        return static::$_instance[$plugin];
    }

    /**
     * @param $name
     * @param $arguments
     * @return mixed
     */
    public static function __callStatic($name, $arguments)
    {
        return static::instance()->{$name}(... $arguments);
    }
}