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
use ReflectionMethod;
use RuntimeException;
use Webman\Annotation\Middleware as MiddlewareAttribute;
use Webman\Annotation\DisableDefaultRoute;
use Webman\Annotation\Route as RouteAttribute;
use Webman\Annotation\RouteGroup as RouteGroupAttribute;
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
     * Index for conflict detection: ["METHOD path" => "callback string"]
     * @var array<string, string>
     */
    protected static array $methodPathIndex = [];

    /**
     * @var string|null
     */
    protected static ?string $registeringSource = null;

    /**
     * @var RouteObject[]
     */
    protected $routes = [];

    /**
     * @var Route[]
     */
    protected $children = [];

    /**
     * Add GET route.
     * @param string $path
     * @param callable|mixed $callback
     * @return RouteObject
     */
    public static function get(string $path, $callback): RouteObject
    {
        return static::addRoute('GET', $path, $callback);
    }

    /**
     * Add POST route.
     * @param string $path
     * @param callable|mixed $callback
     * @return RouteObject
     */
    public static function post(string $path, $callback): RouteObject
    {
        return static::addRoute('POST', $path, $callback);
    }

    /**
     * Add PUT route.
     * @param string $path
     * @param callable|mixed $callback
     * @return RouteObject
     */
    public static function put(string $path, $callback): RouteObject
    {
        return static::addRoute('PUT', $path, $callback);
    }

    /**
     * Add PATCH route.
     * @param string $path
     * @param callable|mixed $callback
     * @return RouteObject
     */
    public static function patch(string $path, $callback): RouteObject
    {
        return static::addRoute('PATCH', $path, $callback);
    }

    /**
     * Add DELETE route.
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
     * Add HEAD route.
     * @param string $path
     * @param callable|mixed $callback
     * @return RouteObject
     */
    public static function options(string $path, $callback): RouteObject
    {
        return static::addRoute('OPTIONS', $path, $callback);
    }

    /**
     * Add OPTIONS route.
     * @param string $path
     * @param callable|mixed $callback
     * @return RouteObject
     */
    public static function any(string $path, $callback): RouteObject
    {
        return static::addRoute(['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'], $path, $callback);
    }

    /**
     * Add route.
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
     * Add group.
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
     * Add resource.
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
     * Get routes.
     * @return RouteObject[]
     */
    public static function getRoutes(): array
    {
        return static::$allRoutes;
    }

    /**
     * Disable default route.
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
     * Is default route disabled.
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
     * Is default route disabled by annotation.
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
     * Is reflection class has default route disabled annotation.
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
     * Add middleware.
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
     * Collect route.
     * @param RouteObject $route
     */
    public function collect(RouteObject $route)
    {
        $this->routes[] = $route;
    }

    /**
     * Set by name.
     * @param string $name
     * @param RouteObject $instance
     */
    public static function setByName(string $name, RouteObject $instance)
    {
        static::$nameList[$name] = $instance;
    }

    /**
     * Get by name.
     * @param string $name
     * @return null|RouteObject
     */
    public static function getByName(string $name): ?RouteObject
    {
        return static::$nameList[$name] ?? null;
    }

    /**
     * Add child.
     * @param Route $route
     * @return void
     */
    public function addChild(Route $route)
    {
        $this->children[] = $route;
    }

    /**
     * Get children.
     * @return Route[]
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * Dispatch.
     * @param string $method
     * @param string $path
     * @return array
     */
    public static function dispatch(string $method, string $path): array
    {
        return static::$dispatcher->dispatch($method, $path);
    }

    /**
     * Convert to callable.
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
     * Add route.
     * @param array|string $methods
     * @param string $path
     * @param callable|mixed $callback
     * @return RouteObject
     */
    protected static function addRoute($methods, string $path, $callback): RouteObject
    {
        $fullPath = static::$groupPrefix . $path;
        foreach ((array)$methods as $method) {
            $method = strtoupper((string)$method);
            $key = $method . ' ' . $fullPath;
            if (isset(static::$methodPathIndex[$key])) {
                $old = static::$methodPathIndex[$key];
                $new = static::callbackToString($callback);
                $source = static::$registeringSource ? (' from ' . static::$registeringSource) : '';
                throw new RuntimeException("Route conflict: [$key] already registered as $old, cannot register $new$source");
            }
            static::$methodPathIndex[$key] = static::callbackToString($callback);
        }

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
        static::$dispatcher = null;
        static::$collector = null;
        static::$fallbackRoutes = [];
        static::$fallback = [];
        static::$nameList = [];
        static::$disabledDefaultRoutes = [];
        static::$disabledDefaultRouteControllers = [];
        static::$disabledDefaultRouteActions = [];
        static::$allRoutes = [];
        static::$methodPathIndex = [];
        static::$registeringSource = null;

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
            static::loadAnnotationRoutes();
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
     * Load annotation routes.
     * @return void
     */
    protected static function loadAnnotationRoutes(): void
    {
        $roots = [];

        $appRoot = app_path();
        if (is_dir($appRoot)) {
            $roots[] = [
                'dir' => $appRoot,
                'ns' => 'app\\',
                'suffix' => (string)Config::get('app.controller_suffix', ''),
            ];
        }

        $pluginBase = base_path('plugin');
        if (is_dir($pluginBase)) {
            foreach (scandir($pluginBase) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $pluginDir = $pluginBase . DIRECTORY_SEPARATOR . $entry;
                if (!is_dir($pluginDir)) {
                    continue;
                }
                if (!static::isValidIdentifier($entry)) {
                    continue;
                }
                // Only load enabled plugins.
                $pluginAppConfig = Config::get("plugin.$entry.app");
                if (!$pluginAppConfig) {
                    continue;
                }
                $pluginAppDir = $pluginDir . DIRECTORY_SEPARATOR . 'app';
                if (!is_dir($pluginAppDir)) {
                    continue;
                }
                $roots[] = [
                    'dir' => $pluginAppDir,
                    'ns' => 'plugin\\' . $entry . '\\app\\',
                    'suffix' => is_array($pluginAppConfig) ? (string)($pluginAppConfig['controller_suffix'] ?? '') : (string)Config::get("plugin.$entry.app.controller_suffix", ''),
                ];
            }
        }

        foreach ($roots as $root) {
            $controllerFiles = static::scanControllerFiles($root['dir'], $root['suffix'] ?? '');
            if (!$controllerFiles) {
                continue;
            }
            $routes = static::buildAnnotationRouteDefinitions($controllerFiles, $root['dir'], $root['ns']);
            static::registerAnnotationRouteDefinitions($routes);
        }

    }

    /**
     * Build annotation route definitions.
     * @param string[] $controllerFiles
     * @param string $appRoot
     * @return array<int,array{methods: string[], path: string, callback: array{0:string,1:string}, name: ?string, middlewares: array}>
     */
    protected static function buildAnnotationRouteDefinitions(array $controllerFiles, string $rootDir, string $rootNamespace): array
    {
        $definitions = [];

        foreach ($controllerFiles as $file) {
            $controllerClass = static::classFromFile($rootDir, $rootNamespace, $file);
            if (!$controllerClass) {
                continue;
            }
            $declaredClass = static::extractDeclaredClassFromFile($file);
            if (!$declaredClass || $declaredClass !== $controllerClass) {
                continue;
            }
            if (!class_exists($controllerClass)) {
                require_once $file;
            }
            if (!class_exists($controllerClass)) {
                continue;
            }

            $ref = new ReflectionClass($controllerClass);
            if ($ref->isAbstract() || $ref->isInterface()) {
                continue;
            }

            $prefix = '';
            $groupAttrs = $ref->getAttributes(RouteGroupAttribute::class, ReflectionAttribute::IS_INSTANCEOF);
            if ($groupAttrs) {
                /** @var RouteGroupAttribute $group */
                $group = $groupAttrs[0]->newInstance();
                $prefix = static::normalizeRoutePrefix($group->prefix);
            }

            foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->isConstructor() || $method->isDestructor()) {
                    continue;
                }
                if ($method->getDeclaringClass()->getName() !== $controllerClass) {
                    continue;
                }

                $routeAttrs = $method->getAttributes(RouteAttribute::class, ReflectionAttribute::IS_INSTANCEOF);
                if (!$routeAttrs) {
                    continue;
                }

                foreach ($routeAttrs as $routeAttr) {
                    /** @var RouteAttribute $route */
                    $route = $routeAttr->newInstance();
                    if ($route->path === null) {
                        // Null path means "method restriction only" for default route, do not register.
                        continue;
                    }
                    $path = static::normalizeRoutePath($route->path, $controllerClass . '::' . $method->getName());
                    $fullPath = $prefix ? rtrim($prefix, '/') . $path : $path;

                    $methods = [];
                    foreach ($route->methods as $m) {
                        $methods[] = strtoupper((string)$m);
                    }

                    $definitions[] = [
                        'methods' => $methods,
                        'path' => $fullPath,
                        'callback' => [$controllerClass, $method->getName()],
                        'name' => $route->name,
                    ];
                }
            }
        }

        return $definitions;
    }

    /**
     * Collect middlewares from attributes.
     * @param array<ReflectionAttribute> $attributes
     * @return array
     */
    protected static function collectMiddlewaresFromAttributes(array $attributes): array
    {
        $middlewares = [];
        foreach ($attributes as $attribute) {
            /** @var MiddlewareAttribute $instance */
            $instance = $attribute->newInstance();
            foreach ($instance->getMiddlewares() as $middleware) {
                if (is_string($middleware)) {
                    $middlewares[] = $middleware;
                    continue;
                }
                if (is_array($middleware) && isset($middleware[0]) && is_string($middleware[0])) {
                    $middlewares[] = $middleware[0];
                }
            }
        }
        return $middlewares;
    }

    /**
     * Register annotation route definitions.
     * @param array $definitions
     * @return void
     */
    protected static function registerAnnotationRouteDefinitions(array $definitions): void
    {
        foreach ($definitions as $definition) {
            static::$registeringSource = 'annotation ' . $definition['callback'][0] . '::' . $definition['callback'][1];
            $route = static::add($definition['methods'], $definition['path'], $definition['callback']);
            if (!empty($definition['name'])) {
                $route->name($definition['name']);
            }
            if (!empty($definition['middlewares'])) {
                $route->middleware($definition['middlewares']);
            }
            static::$registeringSource = null;
        }
    }

    /**
     * Scan controller files.
     * @param string $appRoot
     * @return string[]
     */
    protected static function scanControllerFiles(string $appRoot, string $controllerSuffix = ''): array
    {
        $appRoot = get_realpath($appRoot) ?: $appRoot;
        if (!is_dir($appRoot)) {
            return [];
        }

        // Performance-first strategy:
        // - First locate directories named "controller" (case-insensitive).
        // - Then only scan PHP files under those controller folders.
        $controllerDirs = [];
        $pendingDirs = [$appRoot];
        while ($pendingDirs) {
            $dir = array_pop($pendingDirs);
            try {
                $iterator = new FilesystemIterator($dir, FilesystemIterator::SKIP_DOTS);
            } catch (\Throwable $e) {
                continue;
            }
            foreach ($iterator as $item) {
                /** @var \SplFileInfo $item */
                if (!$item->isDir()) {
                    continue;
                }
                $name = $item->getBasename();
                $path = $item->getPathname();
                if (strcasecmp($name, 'controller') === 0) {
                    $controllerDirs[] = $path;
                } else {
                    $pendingDirs[] = $path;
                }
            }
        }

        if (!$controllerDirs) {
            return [];
        }

        $files = [];
        foreach ($controllerDirs as $controllerDir) {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($controllerDir, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($it as $item) {
                /** @var \SplFileInfo $item */
                if (!$item->isFile() || $item->getExtension() !== 'php') {
                    continue;
                }
                $baseName = $item->getBasename('.php');
                // Ignore backup/temporary files like bak.UserController.php, UserController.bak.php, etc.
                if (!static::isValidIdentifier($baseName)) {
                    continue;
                }
                if ($controllerSuffix !== '' && !str_ends_with($baseName, $controllerSuffix)) {
                    continue;
                }
                $files[] = $item->getPathname();
            }
        }

        return $files;
    }

    /**
     * Class from file.
     * @param string $rootDir
     * @param string $rootNamespace
     * @param string $filePath
     * @return string|null
     */
    protected static function classFromFile(string $rootDir, string $rootNamespace, string $filePath): ?string
    {
        $rootDir = rtrim(get_realpath($rootDir) ?: $rootDir, '/\\');
        $filePath = get_realpath($filePath) ?: $filePath;

        $rootLen = strlen($rootDir);
        if (strncasecmp($filePath, $rootDir, $rootLen) !== 0) {
            return null;
        }
        $relative = ltrim(substr($filePath, $rootLen), '/\\');
        if ($relative === false || $relative === '') {
            return null;
        }
        if (!str_ends_with($relative, '.php')) {
            return null;
        }
        $relative = substr($relative, 0, -4);
        $relative = str_replace(['/', '\\'], '\\', $relative);
        if (!static::isValidPsr4ClassPath($relative)) {
            return null;
        }
        return rtrim($rootNamespace, '\\') . '\\' . $relative;
    }

    /**
     * Is valid PSR-4 class path.
     * @param string $relativeClassPath
     * @return bool
     */
    protected static function isValidPsr4ClassPath(string $relativeClassPath): bool
    {
        return (bool)preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)*$/', $relativeClassPath);
    }

    /**
     * Is valid identifier.
     * @param string $name
     * @return bool
     */
    protected static function isValidIdentifier(string $name): bool
    {
        return $name !== '' && (bool)preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name);
    }

    /**
     * Extract declared class FQCN from file.
     * Returns the first declared class in the file (ignores anonymous classes).
     * @param string $filePath
     * @return string|null
     */
    protected static function extractDeclaredClassFromFile(string $filePath): ?string
    {
        $code = file_get_contents($filePath);
        if ($code === false || $code === '') {
            return null;
        }
        // Fast path: no PHP8 attributes -> cannot contain annotation routes.
        if (strpos($code, '#[') === false) {
            return null;
        }

        $tokens = token_get_all($code);
        $namespace = '';
        $count = count($tokens);
        $prevSignificant = null;

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            $id = is_array($token) ? $token[0] : null;

            if ($id === T_NAMESPACE) {
                $ns = '';
                for ($j = $i + 1; $j < $count; $j++) {
                    $t = $tokens[$j];
                    if (is_array($t)) {
                        if ($t[0] === T_STRING || $t[0] === T_NS_SEPARATOR) {
                            $ns .= $t[1];
                            continue;
                        }
                        if (defined('T_NAME_QUALIFIED') && $t[0] === T_NAME_QUALIFIED) {
                            $ns .= $t[1];
                            continue;
                        }
                        if ($t[0] === T_WHITESPACE) {
                            continue;
                        }
                    } else {
                        if ($t === ';' || $t === '{') {
                            break;
                        }
                    }
                }
                $namespace = trim($ns, '\\');
                continue;
            }

            if ($id === T_CLASS) {
                // Skip class constant usage like Foo::class
                if ($prevSignificant === T_DOUBLE_COLON) {
                    $prevSignificant = null;
                    continue;
                }
                // Skip anonymous class: "new class"
                if ($prevSignificant === T_NEW) {
                    continue;
                }
                for ($j = $i + 1; $j < $count; $j++) {
                    $t = $tokens[$j];
                    if (!is_array($t)) {
                        continue;
                    }
                    if ($t[0] === T_WHITESPACE) {
                        continue;
                    }
                    if ($t[0] === T_STRING) {
                        $class = $t[1];
                        return $namespace !== '' ? ($namespace . '\\' . $class) : $class;
                    }
                    break;
                }
            }

            if (is_array($token)) {
                if ($id !== T_WHITESPACE && $id !== T_COMMENT && $id !== T_DOC_COMMENT) {
                    $prevSignificant = $id;
                }
            } else {
                if (trim($token) !== '') {
                    $prevSignificant = $token;
                }
            }
        }

        return null;
    }

    /**
     * Normalize route prefix.
     * @param string $prefix
     * @return string
     */
    protected static function normalizeRoutePrefix(string $prefix): string
    {
        $prefix = trim($prefix);
        if ($prefix === '') {
            return '';
        }
        if ($prefix[0] !== '/') {
            $prefix = '/' . $prefix;
        }
        return rtrim($prefix, '/');
    }

    /**
     * Normalize route path.
     * @param string $path
     * @param string $source
     * @return string
     */
    protected static function normalizeRoutePath(string $path, string $source): string
    {
        $path = trim($path);
        if ($path === '' || $path[0] !== '/') {
            throw new RuntimeException("Annotation route path must start with '/': $path ($source)");
        }
        return $path;
    }

    /**
     * Callback to string.
     * @param mixed $callback
     * @return string
     */
    protected static function callbackToString(mixed $callback): string
    {
        if (is_array($callback)) {
            $callback = array_values($callback);
            $class = $callback[0] ?? '';
            $method = $callback[1] ?? '';
            return $class && $method ? ($class . '::' . $method) : json_encode($callback);
        }
        if ($callback instanceof \Closure) {
            return 'Closure';
        }
        if (is_string($callback)) {
            return $callback;
        }
        return get_debug_type($callback);
    }

    /**
     * @return void
     * @deprecated
     */
    public static function container()
    {

    }

}
