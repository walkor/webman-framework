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

use FastRoute\Dispatcher\GroupCountBased;
use Webman\Route\RouteCollector;
use Webman\Route\Route as RouteObject;

/**
 * Class Route
 * @package manage
 * @method static get($path, $callback)
 * @method static post($path, $callback)
 * @method static put($path, $callback)
 * @method static patch($path, $callback)
 * @method static delete($path, $callback)
 * @method static head($path, $callback)
 * @method static options($path, $callback)
 * @method static any($path, $callback)
 * @method static group($path, $callback)
 * @method static middleware($middleware)
 * @method static fallback(callable $callback)
 * @method static getFallback()
 * @method static setByName($name, RouteObject $instance)
 * @method static getByName($name)
 * @method static getRoutes()
 */
class Route
{
    /**
     * @var GroupCountBased
     */
    protected static $_dispatcher = null;
    /**
     * @var RouteCollector
     */
    protected static $_collector = null;

    protected static $_routeFiles = [];

    protected static function instanceCollector(?callable $routeDefinitionCallback = null)
    {
        $options = [
            'routeParser' => 'FastRoute\\RouteParser\\Std',
            'dataGenerator' => 'FastRoute\\DataGenerator\\GroupCountBased',
            'dispatcher' => 'FastRoute\\Dispatcher\\GroupCountBased',
            'routeCollector' => RouteCollector::class,
        ];
        if (is_null(static::$_collector)) {
            /** @var RouteCollector $routeCollector */
            $routeCollector = new $options['routeCollector'](
                new $options['routeParser'], new $options['dataGenerator']
            );

            static::$_collector = $routeCollector;
        }

        if (is_callable($routeDefinitionCallback)) {
            $routeDefinitionCallback(static::$_collector);
        }
        static::$_dispatcher = new $options['dispatcher'](static::$_collector->getData());
    }

    public static function dispatch($method, $path)
    {
        if (!static::$_dispatcher) {
            static::instanceCollector();
        }
        return static::$_dispatcher->dispatch($method, $path);
    }

    public static function load($path)
    {
        if (isset(static::$_routeFiles[$path]) || !\is_file($path)) {
            return;
        }
        static::$_routeFiles[] = $path;
        static::instanceCollector(function () {
            foreach (static::$_routeFiles as $routeFile) {
                require_once $routeFile;
            }
        });
    }

    public static function __callStatic($name, $arguments)
    {
        if (!static::$_collector) {
            static::instanceCollector();
        }

        if (method_exists(static::$_collector, $name)) {
            return call_user_func_array([static::$_collector, $name], $arguments);
        }
    }
}
