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


use Closure;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;
use function array_merge;
use function array_reverse;
use function is_array;
use function method_exists;

class Middleware
{

    /**
     * @var array
     */
    protected static $instances = [];

    /**
     * @param mixed $allMiddlewares
     * @param string $plugin
     * @return void
     */
    public static function load($allMiddlewares, string $plugin = '')
    {
        if (!is_array($allMiddlewares)) {
            return;
        }
        foreach ($allMiddlewares as $appName => $middlewares) {
            if (!is_array($middlewares)) {
                throw new RuntimeException('Bad middleware config');
            }
            if ($appName === '@') {
                $plugin = '';
            }
            if (strpos($appName, 'plugin.') !== false) {
                $explode = explode('.', $appName, 4);
                $plugin = $explode[1];
                $appName = $explode[2] ?? '';
            }
            foreach ($middlewares as $className) {
                if (method_exists($className, 'process')) {
                    static::$instances[$plugin][$appName][] = [$className, 'process'];
                } else {
                    // @todo Log
                    echo "middleware $className::process not exsits\n";
                }
            }
        }
    }

    /**
     * @param string $plugin
     * @param string $appName
     * @param string|array|Closure $controller
     * @param bool $withGlobalMiddleware
     * @return array
     */
    public static function getMiddleware(string $plugin, string $appName, string|array|Closure $controller, bool $withGlobalMiddleware = true): array
    {
        $isController = is_array($controller) && is_string($controller[0]);
        $globalMiddleware = $withGlobalMiddleware ? static::$instances['']['@'] ?? [] : [];
        $appGlobalMiddleware = $withGlobalMiddleware && isset(static::$instances[$plugin]['']) ? static::$instances[$plugin][''] : [];
        $controllerMiddleware = [];
        if ($isController && $controller[0] && class_exists($controller[0])) {
            // Controller middleware annotation
            $reflectionClass = new ReflectionClass($controller[0]);
            self::prepareAttributeMiddlewares($controllerMiddleware, $reflectionClass);
            // Controller middleware property
            if ($reflectionClass->hasProperty('middleware')) {
                $defaultProperties = $reflectionClass->getDefaultProperties();
                $controllerMiddlewareClasses = $defaultProperties['middleware'];
                foreach ((array)$controllerMiddlewareClasses as $className) {
                    $controllerMiddleware[] = [$className, 'process'];
                }
            }
            // Method middleware
            if ($reflectionClass->hasMethod($controller[1])) {
                self::prepareAttributeMiddlewares($controllerMiddleware, $reflectionClass->getMethod($controller[1]));
            }
        }
        if ($appName === '') {
            return array_reverse(array_merge($globalMiddleware, $appGlobalMiddleware, $controllerMiddleware));
        }
        $appMiddleware = static::$instances[$plugin][$appName] ?? [];
        return array_reverse(array_merge($globalMiddleware, $appGlobalMiddleware, $appMiddleware, $controllerMiddleware));
    }

    /**
     * @param array $middlewares
     * @param ReflectionClass|ReflectionMethod $reflection
     * @return void
     */
    private static function prepareAttributeMiddlewares(array &$middlewares, ReflectionClass|ReflectionMethod $reflection): void
    {
        $middlewareAttributes = $reflection->getAttributes(Annotation\Middleware::class);
        foreach ($middlewareAttributes as $middlewareAttribute) {
            $middlewareAttributeInstance = $middlewareAttribute->newInstance();
            $middlewares = array_merge($middlewares, $middlewareAttributeInstance->getMiddlewares());
        }
    }

    /**
     * @return void
     * @deprecated
     */
    public static function container($_)
    {

    }
}
