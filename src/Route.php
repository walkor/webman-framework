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
use FastRoute\RouteCollector;
use FilesystemIterator;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use Webman\Annotation\DisableDefaultRoute;
use Webman\Route\Route as RouteObject;
use function array_diff;
use function array_values;
use function class_exists;
use function explode;
use function FastRoute\simpleDispatcher;
use function in_array;
use function is_array;
use function is_callable;
use function is_file;
use function is_scalar;
use function is_string;
use function json_encode;
use function method_exists;
use function strpos;

/**
 * Class Route
 * @package Webman
 */
class Route
{
    /**
     * @var Route
     */
    protected static $instance = null;

    /**
     * @var GroupCountBased
     */
    protected static $dispatcher = null;

    /**
     * @var RouteCollector
     */
    protected static $collector = null;

    /**
     * @var RouteObject[]
     */
    protected static $fallbackRoutes = [];

    /**
     * @var array
     */
    protected static $fallback = [];

    /**
     * @var array
     */
    protected static $nameList = [];

    /**
     * @var string
     */
    protected static $groupPrefix = '';

    /**
     * @var bool
     */
    protected static $disabledDefaultAllRoute = false;

    /**
     * @var array
     */
    protected static $disabledDefaultRoutes = [];

    /**
     * @var array
     */
    protected static $disabledDefaultRouteControllers = [];

    /**
     * @var array
     */
    protected static $disabledDefaultRouteActions = [];

    /**
     * @var RouteObject[]
     */
    protected static $allRoutes = [];

    /**
     * @var RouteObject[]
     */
    protected $routes = [];

    /**
     * @var Route[]
     */
    protected $children = [];

    /**
     * @param string $path
     * @param callable|mixed $callback
     * @return RouteObject
     */
    public static function get(string $path, $callback): RouteObject
    {
        return static::addRoute('GET', $path, $callback);
    }

    /**
     * @param string $path
     * @param callable|mixed $callback
     * @return RouteObject
     */
    public static function post(string $path, $callback): RouteObject
    {
        return static::addRoute('POST', $path, $callback);
    }

    /**
     * @param string $path
     * @param callable|mixed $callback
     * @return RouteObject
     */
    public static function put(string $path, $callback): RouteObject
    {
        return static::addRoute('PUT', $path, $callback);
    }

    /**
     * @param string $path
     * @param callable|mixed $callback
     * @return RouteObject
     */
    public static function patch(string $path, $callback): RouteObject
    {
        return static::addRoute('PATCH', $path, $callback);
    }

    /**
     * @param string $path
     * @param callable|mixed $callback
     * @return RouteObject
     */
    public static function delete(string $path, $callback): RouteObject
    {
        return static::addRoute('DELETE', $path, $callback);
    }

    /**
     * @param string $path
     * @param callable|mixed $callback
     * @return RouteObject
     */
    public static function head(string $path, $callback): RouteObject
    {
        return static::addRoute('HEAD', $path, $callback);
    }

    /**
     * @param string $path
     * @param callable|mixed $callback
     * @return RouteObject
     */
    public static function options(string $path, $callback): RouteObject
    {
        return static::addRoute('OPTIONS', $path, $callback);
    }

    /**
     * @param string $path
     * @param callable|mixed $callback
     * @return RouteObject
     */
    public static function any(string $path, $callback): RouteObject
    {
        return static::addRoute(['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'], $path, $callback);
    }

    /**
     * @param $method
     * @param string $path
     * @param callable|mixed $callback
     * @return RouteObject
     */
    public static function add($method, string $path, $callback): RouteObject
    {
        return static::addRoute($method, $path, $callback);
    }

    /**
     * @param string|callable $path
     * @param callable|null $callback
     * @return static
     */
    public static function group($path, ?callable $callback = null): Route
    {
        if ($callback === null) {
            $callback = $path;
            $path = '';
        }
        $previousGroupPrefix = static::$groupPrefix;
        static::$groupPrefix = $previousGroupPrefix . $path;
        $previousInstance = static::$instance;
        $instance = static::$instance = new static;
        static::$collector->addGroup($path, $callback);
        static::$groupPrefix = $previousGroupPrefix;
        static::$instance = $previousInstance;
        if ($previousInstance) {
            $previousInstance->addChild($instance);
        }
        return $instance;
    }

    /**
     * @param string $name
     * @param string $controller
     * @param array $options
     * @return void
     */
    public static function resource(string $name, string $controller, array $options = [])
    {
        $name = trim($name, '/');
        if (is_array($options) && !empty($options)) {
            $diffOptions = array_diff($options, ['index', 'create', 'store', 'update', 'show', 'edit', 'destroy', 'recovery']);
            if (!empty($diffOptions)) {
                foreach ($diffOptions as $action) {
                    static::any("/$name/{$action}[/{id}]", [$controller, $action])->name("$name.{$action}");
                }
            }
            // 注册路由 由于顺序不同会导致路由无效 因此不适用循环注册
            if (in_array('index', $options)) static::get("/$name", [$controller, 'index'])->name("$name.index");
            if (in_array('create', $options)) static::get("/$name/create", [$controller, 'create'])->name("$name.create");
            if (in_array('store', $options)) static::post("/$name", [$controller, 'store'])->name("$name.store");
            if (in_array('update', $options)) static::put("/$name/{id}", [$controller, 'update'])->name("$name.update");
            if (in_array('patch', $options)) static::patch("/$name/{id}", [$controller, 'patch'])->name("$name.patch");
            if (in_array('show', $options)) static::get("/$name/{id}", [$controller, 'show'])->name("$name.show");
            if (in_array('edit', $options)) static::get("/$name/{id}/edit", [$controller, 'edit'])->name("$name.edit");
            if (in_array('destroy', $options)) static::delete("/$name/{id}", [$controller, 'destroy'])->name("$name.destroy");
            if (in_array('recovery', $options)) static::put("/$name/{id}/recovery", [$controller, 'recovery'])->name("$name.recovery");
        } else {
            //为空时自动注册所有常用路由
            if (method_exists($controller, 'index')) static::get("/$name", [$controller, 'index'])->name("$name.index");
            if (method_exists($controller, 'create')) static::get("/$name/create", [$controller, 'create'])->name("$name.create");
            if (method_exists($controller, 'store')) static::post("/$name", [$controller, 'store'])->name("$name.store");
            if (method_exists($controller, 'update')) static::put("/$name/{id}", [$controller, 'update'])->name("$name.update");
            if (method_exists($controller, 'patch')) static::patch("/$name/{id}", [$controller, 'patch'])->name("$name.patch");
            if (method_exists($controller, 'show')) static::get("/$name/{id}", [$controller, 'show'])->name("$name.show");
            if (method_exists($controller, 'edit')) static::get("/$name/{id}/edit", [$controller, 'edit'])->name("$name.edit");
            if (method_exists($controller, 'destroy')) static::delete("/$name/{id}", [$controller, 'destroy'])->name("$name.destroy");
            if (method_exists($controller, 'recovery')) static::put("/$name/{id}/recovery", [$controller, 'recovery'])->name("$name.recovery");
        }
    }

    /**
     * @return RouteObject[]
     */
    public static function getRoutes(): array
    {
        return static::$allRoutes;
    }

    /**
     * disableDefaultAllRoute
     */
    public static function disableDefaultAllRoute()
    {
        static::$disabledDefaultAllRoute = true;
    }

    /**
     * isDefaultAllRouteDisabled
     *
     * @return bool
     */
    public static function isDefaultAllRouteDisabled(): bool
    {
        return static::$disabledDefaultAllRoute;
    }

    /**
     * disableDefaultRoute.
     *
     * @param array|string $plugin
     * @param string|null $app
     * @return bool
     */
    public static function disableDefaultRoute(array|string $plugin = '', ?string $app = null): bool
    {
        // Is [controller action]
        if (is_array($plugin)) {
            $controllerAction = $plugin;
            if (!isset($controllerAction[0]) || !is_string($controllerAction[0]) ||
                !isset($controllerAction[1]) || !is_string($controllerAction[1])) {
                return false;
            }
            $controller = $controllerAction[0];
            $action = $controllerAction[1];
            static::$disabledDefaultRouteActions[$controller][$action] = $action;
            return true;
        }
        // Is plugin
        if (is_string($plugin) && (preg_match('/^[a-zA-Z0-9_]+$/', $plugin) || $plugin === '')) {
            if (!isset(static::$disabledDefaultRoutes[$plugin])) {
                static::$disabledDefaultRoutes[$plugin] = [];
            }
            $app = $app ?? '*';
            static::$disabledDefaultRoutes[$plugin][$app] = $app;
            return true;
        }
        // Is controller
        if (is_string($plugin) && class_exists($plugin)) {
            static::$disabledDefaultRouteControllers[$plugin] = $plugin;
            return true;
        }
        return false;
    }

    /**
     * @param array|string $plugin
     * @param string|null $app
     * @return bool
     */
    public static function isDefaultRouteDisabled(array|string $plugin = '', ?string $app = null): bool
    {
        // Is [controller action]
        if (is_array($plugin)) {
            if (!isset($plugin[0]) || !is_string($plugin[0]) ||
                !isset($plugin[1]) || !is_string($plugin[1])) {
                return false;
            }
            return isset(static::$disabledDefaultRouteActions[$plugin[0]][$plugin[1]]) || static::isDefaultRouteDisabledByAnnotation($plugin[0], $plugin[1]);
        }
        // Is plugin
        if (is_string($plugin) && (preg_match('/^[a-zA-Z0-9_]+$/', $plugin) || $plugin === '')) {
            $app = $app ?? '*';
            return isset(static::$disabledDefaultRoutes[$plugin]['*']) || isset(static::$disabledDefaultRoutes[$plugin][$app]);
        }
        // Is controller
        if (is_string($plugin) && class_exists($plugin)) {
            return isset(static::$disabledDefaultRouteControllers[$plugin]);
        }
        return false;
    }

    /**
     * @param string $controller
     * @param string|null $action
     * @return bool
     */
    protected static function isDefaultRouteDisabledByAnnotation(string $controller, ?string $action = null): bool
    {
        if (class_exists($controller)) {
            $reflectionClass = new ReflectionClass($controller);
            if (static::isRefHasDefaultRouteDisabledAnnotation($reflectionClass)) {
                return true;
            }
            if ($action && $reflectionClass->hasMethod($action)) {
                $reflectionMethod = $reflectionClass->getMethod($action);
                if ($reflectionMethod->getAttributes(DisableDefaultRoute::class, ReflectionAttribute::IS_INSTANCEOF)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @param ReflectionClass $reflectionClass
     * @return bool
     */
    protected static function isRefHasDefaultRouteDisabledAnnotation(ReflectionClass $reflectionClass): bool
    {
        $has = $reflectionClass->getAttributes(DisableDefaultRoute::class, ReflectionAttribute::IS_INSTANCEOF);
        if ($has) {
            return true;
        }
        if (method_exists($reflectionClass, 'getParentClass')) {
            $parent = $reflectionClass->getParentClass();
            if ($parent) {
                return static::isRefHasDefaultRouteDisabledAnnotation($parent);
            }
        }
        return false;
    }

    /**
     * @param $middleware
     * @return $this
     */
    public function middleware($middleware): Route
    {
        foreach ($this->routes as $route) {
            $route->middleware($middleware);
        }
        foreach ($this->getChildren() as $child) {
            $child->middleware($middleware);
        }
        return $this;
    }

    /**
     * @param RouteObject $route
     */
    public function collect(RouteObject $route)
    {
        $this->routes[] = $route;
    }

    /**
     * @param string $name
     * @param RouteObject $instance
     */
    public static function setByName(string $name, RouteObject $instance)
    {
        static::$nameList[$name] = $instance;
    }

    /**
     * @param string $name
     * @return null|RouteObject
     */
    public static function getByName(string $name): ?RouteObject
    {
        return static::$nameList[$name] ?? null;
    }

    /**
     * @param Route $route
     * @return void
     */
    public function addChild(Route $route)
    {
        $this->children[] = $route;
    }

    /**
     * @return Route[]
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * @param string $method
     * @param string $path
     * @return array
     */
    public static function dispatch(string $method, string $path): array
    {
        return static::$dispatcher->dispatch($method, $path);
    }

    /**
     * @param string $path
     * @param callable|mixed $callback
     * @return callable|false|string[]
     */
    public static function convertToCallable(string $path, $callback)
    {
        if (is_string($callback) && strpos($callback, '@')) {
            $callback = explode('@', $callback, 2);
        }

        if (!is_array($callback)) {
            if (!is_callable($callback)) {
                $callStr = is_scalar($callback) ? $callback : 'Closure';
                echo "Route $path $callStr is not callable\n";
                return false;
            }
        } else {
            $callback = array_values($callback);
            if (!isset($callback[1]) || !class_exists($callback[0]) || !method_exists($callback[0], $callback[1])) {
                echo "Route $path " . json_encode($callback) . " is not callable\n";
                return false;
            }
        }

        return $callback;
    }

    /**
     * @param array|string $methods
     * @param string $path
     * @param callable|mixed $callback
     * @return RouteObject
     */
    protected static function addRoute($methods, string $path, $callback): RouteObject
    {
        $route = new RouteObject($methods, static::$groupPrefix . $path, $callback);
        static::$allRoutes[] = $route;

        if ($callback = static::convertToCallable($path, $callback)) {
            static::$collector->addRoute($methods, $path, ['callback' => $callback, 'route' => $route]);
        }
        if (static::$instance) {
            static::$instance->collect($route);
        }
        return $route;
    }

    /**
     * Load.
     * @param mixed $paths
     * @return void
     */
    public static function load($paths)
    {
        if (!is_array($paths)) {
            return;
        }
        static::$dispatcher = simpleDispatcher(function (RouteCollector $route) use ($paths) {
            Route::setCollector($route);
            foreach ($paths as $configPath) {
                $routeConfigFile = $configPath . '/route.php';
                if (is_file($routeConfigFile)) {
                    require_once $routeConfigFile;
                }
                if (!is_dir($pluginConfigPath = $configPath . '/plugin')) {
                    continue;
                }
                $dirIterator = new RecursiveDirectoryIterator($pluginConfigPath, FilesystemIterator::FOLLOW_SYMLINKS);
                $iterator = new RecursiveIteratorIterator($dirIterator);
                foreach ($iterator as $file) {
                    if ($file->getBaseName('.php') !== 'route') {
                        continue;
                    }
                    $appConfigFile = pathinfo($file, PATHINFO_DIRNAME) . '/app.php';
                    if (!is_file($appConfigFile)) {
                        continue;
                    }
                    $appConfig = include $appConfigFile;
                    if (empty($appConfig['enable'])) {
                        continue;
                    }
                    require_once $file;
                }
            }
        });
    }

    /**
     * SetCollector.
     * @param RouteCollector $route
     * @return void
     */
    public static function setCollector(RouteCollector $route)
    {
        static::$collector = $route;
    }

    /**
     * Fallback.
     * @param callable|mixed $callback
     * @param string $plugin
     * @return RouteObject
     */
    public static function fallback(callable $callback, string $plugin = '')
    {
        $route = new RouteObject([], '', $callback);
        static::$fallbackRoutes[$plugin] = $route;
        return $route;
    }

    /**
     * GetFallBack.
     * @param string $plugin
     * @param int $status
     * @return callable|null
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     */
    public static function getFallback(string $plugin = '', int $status = 404)
    {
        if (!isset(static::$fallback[$plugin])) {
            $callback = null;
            $route = static::$fallbackRoutes[$plugin] ?? null;
            static::$fallback[$plugin] = $route ? App::getCallback($plugin, 'NOT_FOUND', $route->getCallback(), ['status' => $status], false, $route) : null;
        }
        return static::$fallback[$plugin];
    }

    /**
     * @return void
     * @deprecated
     */
    public static function container()
    {

    }

}
