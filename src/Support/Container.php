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

namespace Support;

use Psr\Container\ContainerInterface;
use Webman\Config;

/**
 * Class Container
 * @package Support
 * @method static mixed get($name)
 * @method static mixed make($name, array $parameters)
 * @method static bool has($name)
 */
class Container
{
    /**
     * @return ContainerInterface
     */
    public static function instance($plugin = '')
    {
        return Config::get($plugin ? "plugin.$plugin.container" : 'container');
    }

    /**
     * @param $name
     * @param $arguments
     * @return mixed
     */
    public static function __callStatic($name, $arguments)
    {
        $plugin = \Webman\App::getPluginByClass($name);
        return static::instance($plugin)->{$name}(... $arguments);
    }
}
