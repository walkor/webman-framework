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
use FastRoute\Dispatcher;
use Monolog\Logger;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use Throwable;
use Webman\Exception\ExceptionHandler;
use Webman\Exception\ExceptionHandlerInterface;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\Route\Route as RouteObject;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http;
use Workerman\Worker;

/**
 * Class App
 * @package Webman
 */
class App
{

    /**
     * @var callable[]
     */
    protected static $callbacks = [];

    /**
     * @var Worker
     */
    protected static $worker = null;

    /**
     * @var Logger
     */
    protected static $logger = null;

    /**
     * @var string
     */
    protected static $appPath = '';

    /**
     * @var string
     */
    protected static $publicPath = '';

    /**
     * @var string
     */
    protected static $requestClass = '';

    /**
     * App constructor.
     * @param string $request_class
     * @param Logger $logger
     * @param string $app_path
     * @param string $public_path
     */
    public function __construct(string $request_class, Logger $logger, string $app_path, string $public_path)
    {
        static::$requestClass = $request_class;
        static::$logger = $logger;
        static::$publicPath = $public_path;
        static::$appPath = $app_path;
    }

    /**
     * OnMessage.
     * @param TcpConnection $connection
     * @param Request $request
     * @return null
     */
    public function onMessage($connection, $request)
    {
        try {
            Context::set(Request::class, $request);
            $path = $request->path();
            $key = $request->method() . $path;
            if (isset(static::$callbacks[$key])) {
                [$callback, $request->plugin, $request->app, $request->controller, $request->action, $request->route] = static::$callbacks[$key];
                static::send($connection, $callback($request), $request);
                return null;
            }

            if (
                static::unsafeUri($connection, $path, $request) ||
                static::findFile($connection, $path, $key, $request) ||
                static::findRoute($connection, $path, $key, $request)
            ) {
                return null;
            }

            $controller_and_action = static::parseControllerAction($path);
            $plugin = $controller_and_action['plugin'] ?? static::getPluginByPath($path);
            if (!$controller_and_action || Route::hasDisableDefaultRoute($plugin)) {
                $callback = static::getFallback($plugin);
                $request->app = $request->controller = $request->action = '';
                static::send($connection, $callback($request), $request);
                return null;
            }
            $app = $controller_and_action['app'];
            $controller = $controller_and_action['controller'];
            $action = $controller_and_action['action'];
            $callback = static::getCallback($plugin, $app, [$controller, $action]);
            static::collectCallbacks($key, [$callback, $plugin, $app, $controller, $action, null]);
            [$callback, $request->plugin, $request->app, $request->controller, $request->action, $request->route] = static::$callbacks[$key];
            static::send($connection, $callback($request), $request);
        } catch (Throwable $e) {
            static::send($connection, static::exceptionResponse($e, $request), $request);
        }
        return null;
    }

    /**
     * OnWorkerStart.
     * @param $worker
     * @return void
     */
    public function onWorkerStart($worker)
    {
        static::$worker = $worker;
        Http::requestClass(static::$requestClass);
    }

    /**
     * CollectCallbacks.
     * @param string $key
     * @param array $data
     * @return void
     */
    protected static function collectCallbacks(string $key, array $data)
    {
        static::$callbacks[$key] = $data;
        if (\count(static::$callbacks) >= 1024) {
            unset(static::$callbacks[\key(static::$callbacks)]);
        }
    }

    /**
     * UnsafeUri.
     * @param TcpConnection $connection
     * @param string $path
     * @param $request
     * @return bool
     */
    protected static function unsafeUri(TcpConnection $connection, string $path, $request): bool
    {
        if (
            !$path ||
            \strpos($path, '..') !== false ||
            \strpos($path, "\\") !== false ||
            \strpos($path, "\0") !== false
        ) {
            $callback = static::getFallback();
            $request->plugin = $request->app = $request->controller = $request->action = '';
            static::send($connection, $callback($request), $request);
            return true;
        }
        return false;
    }

    /**
     * GetFallback.
     * @param string $plugin
     * @return Closure
     */
    protected static function getFallback(string $plugin = ''): Closure
    {
        // when route, controller and action not found, try to use Route::fallback
        return Route::getFallback($plugin) ?: function () {
            try {
                $notFoundContent = \file_get_contents(static::$publicPath . '/404.html');
            } catch (Throwable $e) {
                $notFoundContent = '404 Not Found';
            }
            return new Response(404, [], $notFoundContent);
        };
    }

    /**
     * ExceptionResponse.
     * @param Throwable $e
     * @param $request
     * @return Response
     */
    protected static function exceptionResponse(Throwable $e, $request): Response
    {
        try {
            $app = $request->app ?: '';
            $plugin = $request->plugin ?: '';
            $exception_config = static::config($plugin, 'exception');
            $default_exception = $exception_config[''] ?? ExceptionHandler::class;
            $exception_handler_class = $exception_config[$app] ?? $default_exception;

            /** @var ExceptionHandlerInterface $exception_handler */
            $exception_handler = static::container($plugin)->make($exception_handler_class, [
                'logger' => static::$logger,
                'debug' => static::config($plugin, 'app.debug')
            ]);
            $exception_handler->report($e);
            $response = $exception_handler->render($request, $e);
            $response->exception($e);
            return $response;
        } catch (Throwable $e) {
            $response = new Response(500, [], static::config($plugin ?? '', 'app.debug') ? (string)$e : $e->getMessage());
            $response->exception($e);
            return $response;
        }
    }

    /**
     * GetCallback.
     * @param string $plugin
     * @param string $app
     * @param $call
     * @param array|null $args
     * @param bool $with_global_middleware
     * @param RouteObject|null $route
     * @return callable
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     */
    protected static function getCallback(string $plugin, string $app, $call, array $args = null, bool $with_global_middleware = true, RouteObject $route = null)
    {
        $args = $args === null ? null : \array_values($args);
        $middlewares = [];
        if ($route) {
            $route_middlewares = \array_reverse($route->getMiddleware());
            foreach ($route_middlewares as $class_name) {
                $middlewares[] = [$class_name, 'process'];
            }
        }
        $middlewares = \array_merge($middlewares, Middleware::getMiddleware($plugin, $app, $with_global_middleware));

        foreach ($middlewares as $key => $item) {
            $middleware = $item[0];
            if (is_string($middleware)) {
                $middleware = static::container($plugin)->get($middleware);
            } elseif ($middleware instanceof \Closure) {
                $middleware = call_user_func($middleware, static::container($plugin));
            }
            if (!$middleware instanceof MiddlewareInterface) {
                throw new \InvalidArgumentException('Not support middleware type');
            }
            $middlewares[$key][0] = $middleware;
        }

        $need_inject = static::isNeedInject($call, $args);
        if (\is_array($call) && \is_string($call[0])) {
            $controller_reuse = static::config($plugin, 'app.controller_reuse', true);
            if (!$controller_reuse) {
                if ($need_inject) {
                    $call = function ($request, ...$args) use ($call, $plugin) {
                        $call[0] = static::container($plugin)->make($call[0]);
                        $reflector = static::getReflector($call);
                        $args = static::resolveMethodDependencies($plugin, $request, $args, $reflector);
                        return $call(...$args);
                    };
                    $need_inject = false;
                } else {
                    $call = function ($request, ...$args) use ($call, $plugin) {
                        $call[0] = static::container($plugin)->make($call[0]);
                        return $call($request, ...$args);
                    };
                }
            } else {
                $call[0] = static::container($plugin)->get($call[0]);
            }
        }

        if ($need_inject) {
            $call = static::resolveInject($plugin, $call);
        }

        if ($middlewares) {
            $callback = \array_reduce($middlewares, function ($carry, $pipe) {
                return function ($request) use ($carry, $pipe) {
                    try {
                        return $pipe($request, $carry);
                    } catch (Throwable $e) {
                        return static::exceptionResponse($e, $request);
                    }
                };
            }, function ($request) use ($call, $args) {
                try {
                    if ($args === null) {
                        $response = $call($request);
                    } else {
                        $response = $call($request, ...$args);
                    }
                } catch (Throwable $e) {
                    return static::exceptionResponse($e, $request);
                }
                if (!$response instanceof Response) {
                    if (\is_array($response)) {
                        $response = 'Array';
                    }
                    $response = new Response(200, [], $response);
                }
                return $response;
            });
        } else {
            if ($args === null) {
                $callback = $call;
            } else {
                $callback = function ($request) use ($call, $args) {
                    return $call($request, ...$args);
                };
            }
        }
        return $callback;
    }

    /**
     * ResolveInject.
     * @param string $plugin
     * @param array|Closure $call
     * @return Closure
     * @see Dependency injection through reflection information
     */
    protected static function resolveInject(string $plugin, $call): Closure
    {
        return function (Request $request, ...$args) use ($plugin, $call) {
            $reflector = static::getReflector($call);
            $args = static::resolveMethodDependencies($plugin, $request, $args, $reflector);
            return $call(...$args);
        };
    }

    /**
     * Check whether inject is required.
     * @param $call
     * @param $args
     * @return bool
     * @throws ReflectionException
     */
    protected static function isNeedInject($call, $args): bool
    {
        if (\is_array($call) && !\method_exists($call[0], $call[1])) {
            return false;
        }
        $args = $args ?: [];
        $reflector = static::getReflector($call);
        $reflection_parameters = $reflector->getParameters();
        if (!$reflection_parameters) {
            return false;
        }
        $first_parameter = \current($reflection_parameters);
        unset($reflection_parameters[\key($reflection_parameters)]);
        $adapters_list = ['int', 'string', 'bool', 'array', 'object', 'float', 'mixed', 'resource'];
        foreach ($reflection_parameters as $parameter) {
            if ($parameter->hasType() && !\in_array($parameter->getType()->getName(), $adapters_list)) {
                return true;
            }
        }
        if (!$first_parameter->hasType()) {
            if (\count($args) <= count($reflection_parameters)) {
                return false;
            }
            return true;
        } elseif (!\is_a(static::$requestClass, $first_parameter->getType()->getName())) {
            return true;
        }

        return false;
    }

    /**
     * Get reflector.
     * @param $call
     * @return ReflectionFunction|ReflectionMethod
     * @throws ReflectionException
     */
    protected static function getReflector($call)
    {
        if ($call instanceof Closure || \is_string($call)) {
            return new ReflectionFunction($call);
        }
        return new ReflectionMethod($call[0], $call[1]);
    }

    /**
     * Return dependent parameters
     * @param string $plugin
     * @param Request $request
     * @param array $args
     * @param ReflectionFunctionAbstract $reflector
     * @return array
     * @throws Exception\NotFoundException
     */
    protected static function resolveMethodDependencies(string $plugin, Request $request, array $args, ReflectionFunctionAbstract $reflector): array
    {
        // Specification parameter information
        $args = \array_values($args);
        $parameters = [];
        // An array of reflection classes for loop parameters, with each $parameter representing a reflection object of parameters
        foreach ($reflector->getParameters() as $parameter) {
            // Parameter quota consumption
            if ($parameter->hasType()) {
                $name = $parameter->getType()->getName();
                switch ($name) {
                    case 'int':
                    case 'string':
                    case 'bool':
                    case 'array':
                    case 'object':
                    case 'float':
                    case 'mixed':
                    case 'resource':
                        goto _else;
                    default:
                        if (\is_a($request, $name)) {
                            //Inject Request
                            $parameters[] = $request;
                        } else {
                            $parameters[] = static::container($plugin)->make($name);
                        }
                        break;
                }
            } else {
                _else:
                // The variable parameter
                if (null !== \key($args)) {
                    $parameters[] = \current($args);
                } else {
                    // Indicates whether the current parameter has a default value.  If yes, return true
                    $parameters[] = $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;
                }
                // Quota of consumption variables
                \next($args);
            }
        }

        // Returns the result of parameters replacement
        return $parameters;
    }

    /**
     * Container.
     * @param string $plugin
     * @return ContainerInterface
     */
    public static function container(string $plugin = '')
    {
        return static::config($plugin, 'container');
    }

    /**
     * Get request.
     * @return Request|\support\Request
     */
    public static function request()
    {
        return Context::get(Request::class);
    }

    /**
     * Get worker.
     * @return Worker
     */
    public static function worker(): ?Worker
    {
        return static::$worker;
    }

    /**
     * Find Route.
     * @param TcpConnection $connection
     * @param string $path
     * @param string $key
     * @param Request $request
     * @return bool
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     */
    protected static function findRoute(TcpConnection $connection, string $path, string $key, $request): bool
    {
        $ret = Route::dispatch($request->method(), $path);
        if ($ret[0] === Dispatcher::FOUND) {
            $ret[0] = 'route';
            $callback = $ret[1]['callback'];
            $route = clone $ret[1]['route'];
            $app = $controller = $action = '';
            $args = !empty($ret[2]) ? $ret[2] : null;
            if ($args) {
                $route->setParams($args);
            }
            if (\is_array($callback)) {
                $controller = $callback[0];
                $plugin = static::getPluginByClass($controller);
                $app = static::getAppByController($controller);
                $action = static::getRealMethod($controller, $callback[1]) ?? '';
            } else {
                $plugin = static::getPluginByPath($path);
            }
            $callback = static::getCallback($plugin, $app, $callback, $args, true, $route);
            static::collectCallbacks($key, [$callback, $plugin, $app, $controller ?: '', $action, $route]);
            [$callback, $request->plugin, $request->app, $request->controller, $request->action, $request->route] = static::$callbacks[$key];
            static::send($connection, $callback($request), $request);
            return true;
        }
        return false;
    }

    /**
     * Find File.
     * @param TcpConnection $connection
     * @param string $path
     * @param string $key
     * @param Request $request
     * @return bool
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     */
    protected static function findFile(TcpConnection $connection, string $path, string $key, $request): bool
    {
        if (preg_match('/%[0-9a-f]{2}/i', $path)) {
            $path = urldecode($path);
            if (static::unsafeUri($connection, $path, $request)) {
                return true;
            }
        }

        $path_explodes = \explode('/', trim($path, '/'));
        $plugin = '';
        if (isset($path_explodes[1]) && $path_explodes[0] === 'app') {
            $public_dir = BASE_PATH . "/plugin/{$path_explodes[1]}/public";
            $plugin = $path_explodes[1];
            $path = \substr($path, strlen("/app/{$path_explodes[1]}/"));
        } else {
            $public_dir = static::$publicPath;
        }
        $file = "$public_dir/$path";
        if (!\is_file($file)) {
            return false;
        }

        if (\pathinfo($file, PATHINFO_EXTENSION) === 'php') {
            if (!static::config($plugin, 'app.support_php_files', false)) {
                return false;
            }
            static::collectCallbacks($key, [function () use ($file) {
                return static::execPhpFile($file);
            }, '', '', '', '', null]);
            [, $request->plugin, $request->app, $request->controller, $request->action, $request->route] = static::$callbacks[$key];
            static::send($connection, static::execPhpFile($file), $request);
            return true;
        }

        if (!static::config($plugin, 'static.enable', false)) {
            return false;
        }

        static::collectCallbacks($key, [static::getCallback($plugin, '__static__', function ($request) use ($file, $plugin) {
            \clearstatcache(true, $file);
            if (!\is_file($file)) {
                $callback = static::getFallback($plugin);
                return $callback($request);
            }
            return (new Response())->file($file);
        }, null, false), '', '', '', '', null]);
        [$callback, $request->plugin, $request->app, $request->controller, $request->action, $request->route] = static::$callbacks[$key];
        static::send($connection, $callback($request), $request);
        return true;
    }

    /**
     * Send.
     * @param TcpConnection $connection
     * @param mixed $response
     * @param Request $request
     * @return void
     */
    protected static function send(TcpConnection $connection, $response, $request)
    {
        $keep_alive = $request->header('connection');
        Context::destroy();
        if (($keep_alive === null && $request->protocolVersion() === '1.1')
            || $keep_alive === 'keep-alive' || $keep_alive === 'Keep-Alive'
        ) {
            $connection->send($response);
            return;
        }
        $connection->close($response);
    }

    /**
     * ParseControllerAction.
     * @param string $path
     * @return array|false
     * @throws ReflectionException
     */
    protected static function parseControllerAction(string $path)
    {
        $path = \str_replace('-', '', $path);
        $path_explode = \explode('/', trim($path, '/'));
        $is_plugin = isset($path_explode[1]) && $path_explode[0] === 'app';
        $config_prefix = $is_plugin ? "plugin.{$path_explode[1]}." : '';
        $path_prefix = $is_plugin ? "/app/{$path_explode[1]}" : '';
        $class_prefix = $is_plugin ? "plugin\\{$path_explode[1]}" : '';
        $suffix = Config::get("{$config_prefix}app.controller_suffix", '');
        $relative_path = \trim(substr($path, strlen($path_prefix)), '/');
        $path_explode = $relative_path ? \explode('/', $relative_path) : [];

        $action = 'index';
        if ($controller_action = static::guessControllerAction($path_explode, $action, $suffix, $class_prefix)) {
            return $controller_action;
        }
        if (count($path_explode) <= 1) {
            return false;
        }
        $action = \end($path_explode);
        unset($path_explode[count($path_explode) - 1]);
        return static::guessControllerAction($path_explode, $action, $suffix, $class_prefix);
    }

    /**
     * GuessControllerAction.
     * @param $path_explode
     * @param $action
     * @param $suffix
     * @param $class_prefix
     * @return array|false
     * @throws ReflectionException
     */
    protected static function guessControllerAction($path_explode, $action, $suffix, $class_prefix)
    {
        $map[] = \trim("$class_prefix\\app\\controller\\" . \implode('\\', $path_explode), '\\');
        foreach ($path_explode as $index => $section) {
            $tmp = $path_explode;
            \array_splice($tmp, $index, 1, [$section, 'controller']);
            $map[] = trim("$class_prefix\\" . \implode('\\', \array_merge(['app'], $tmp)), '\\');
        }
        foreach ($map as $item) {
            $map[] = $item . '\\index';
        }

        foreach ($map as $controller_class) {
            // Remove xx\xx\controller
            if (substr($controller_class, -11) === '\\controller') {
                continue;
            }
            $controller_class .= $suffix;
            if ($controller_action = static::getControllerAction($controller_class, $action)) {
                return $controller_action;
            }
        }
        return false;
    }

    /**
     * GetControllerAction.
     * @param string $controller_class
     * @param string $action
     * @return array|false
     * @throws ReflectionException
     */
    protected static function getControllerAction(string $controller_class, string $action)
    {
        // Disable calling magic methods
        if (\strpos($action, '__') === 0) {
            return false;
        }
        if (($controller_class = static::getController($controller_class)) && ($action = static::getAction($controller_class, $action))) {
            return [
                'plugin' => static::getPluginByClass($controller_class),
                'app' => static::getAppByController($controller_class),
                'controller' => $controller_class,
                'action' => $action
            ];
        }
        return false;
    }

    /**
     * GetController.
     * @param string $controller_class
     * @return string|false
     * @throws ReflectionException
     */
    protected static function getController(string $controller_class)
    {
        if (\class_exists($controller_class)) {
            return (new \ReflectionClass($controller_class))->name;
        }
        $explodes = \explode('\\', strtolower(ltrim($controller_class, '\\')));
        $base_path = $explodes[0] === 'plugin' ? BASE_PATH . '/plugin' : static::$appPath;
        unset($explodes[0]);
        $file_name = \array_pop($explodes) . '.php';
        $found = true;
        foreach ($explodes as $path_section) {
            if (!$found) {
                break;
            }
            $dirs = Util::scanDir($base_path, false);
            $found = false;
            foreach ($dirs as $name) {
                $path = "$base_path/$name";
                if (\is_dir($path) && \strtolower($name) === $path_section) {
                    $base_path = $path;
                    $found = true;
                    break;
                }
            }
        }
        if (!$found) {
            return false;
        }
        foreach (\scandir($base_path) ?: [] as $name) {
            if (\strtolower($name) === $file_name) {
                require_once "$base_path/$name";
                if (\class_exists($controller_class, false)) {
                    return (new \ReflectionClass($controller_class))->name;
                }
            }
        }
        return false;
    }

    /**
     * GetAction.
     * @param string $controller_class
     * @param string $action
     * @return string|false
     */
    protected static function getAction(string $controller_class, string $action)
    {
        $methods = \get_class_methods($controller_class);
        $action = \strtolower($action);
        $found = false;
        foreach ($methods as $candidate) {
            if (\strtolower($candidate) === $action) {
                $action = $candidate;
                $found = true;
                break;
            }
        }
        if ($found) {
            return $action;
        }
        // Action is not public method
        if (\method_exists($controller_class, $action)) {
            return false;
        }
        if (\method_exists($controller_class, '__call')) {
            return $action;
        }
        return false;
    }

    /**
     * GetPluginByClass.
     * @param string $controller_class
     * @return mixed|string
     */
    public static function getPluginByClass(string $controller_class)
    {
        $controller_class = \trim($controller_class, '\\');
        $tmp = \explode('\\', $controller_class, 3);
        if ($tmp[0] !== 'plugin') {
            return '';
        }
        return $tmp[1] ?? '';
    }

    /**
     * GetPluginByPath.
     * @param string $path
     * @return mixed|string
     */
    public static function getPluginByPath(string $path)
    {
        $path = \trim($path, '/');
        $tmp = \explode('/', $path, 3);
        if ($tmp[0] !== 'app') {
            return '';
        }
        return $tmp[1] ?? '';
    }

    /**
     * GetAppByController.
     * @param string $controller_class
     * @return mixed|string
     */
    protected static function getAppByController(string $controller_class)
    {
        $controller_class = \trim($controller_class, '\\');
        $tmp = \explode('\\', $controller_class, 5);
        $pos = $tmp[0] === 'plugin' ? 3 : 1;
        if (!isset($tmp[$pos])) {
            return '';
        }
        return \strtolower($tmp[$pos]) === 'controller' ? '' : $tmp[$pos];
    }

    /**
     * ExecPhpFile.
     * @param string $file
     * @return false|string
     */
    public static function execPhpFile(string $file)
    {
        \ob_start();
        // Try to include php file.
        try {
            include $file;
        } catch (\Exception $e) {
            echo $e;
        }
        return \ob_get_clean();
    }

    /**
     * GetRealMethod.
     * @param string $class
     * @param string $method
     * @return string
     */
    protected static function getRealMethod(string $class, string $method): string
    {
        $method = \strtolower($method);
        $methods = \get_class_methods($class);
        foreach ($methods as $candidate) {
            if (\strtolower($candidate) === $method) {
                return $candidate;
            }
        }
        return $method;
    }

    /**
     * Config.
     * @param string $plugin
     * @param string $key
     * @param $default
     * @return array|mixed|null
     */
    protected static function config(string $plugin, string $key, $default = null)
    {
        return Config::get($plugin ? "plugin.$plugin.$key" : $key, $default);
    }
}
