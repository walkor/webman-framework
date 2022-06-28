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

use Workerman\Protocols\Http;
use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\Route\Route as RouteObject;
use Webman\Exception\ExceptionHandlerInterface;
use Webman\Exception\ExceptionHandler;
use Webman\Config;
use FastRoute\Dispatcher;
use Psr\Container\ContainerInterface;
use Monolog\Logger;

/**
 * Class App
 * @package Webman
 */
class App
{

    /**
     * @var array
     */
    protected static $_callbacks = [];

    /**
     * @var Worker
     */
    protected static $_worker = null;

    /**
     * @var ContainerInterface
     */
    protected static $_container = null;

    /**
     * @var Logger
     */
    protected static $_logger = null;

    /**
     * @var string
     */
    protected static $_appPath = '';

    /**
     * @var string
     */
    protected static $_publicPath = '';

    /**
     * @var string
     */
    protected static $_configPath = '';

    /**
     * @var TcpConnection
     */
    protected static $_connection = null;

    /**
     * @var Request
     */
    protected static $_request = null;

    /**
     * @var string
     */
    protected static $_requestClass = '';

    /**
     * App constructor.
     * @param Worker $worker
     * @param $logger
     * @param $app_path
     * @param $public_path
     */
    public function __construct($request_class, $logger, $app_path, $public_path)
    {
        static::$_requestClass = $request_class;
        static::$_logger = $logger;
        static::$_publicPath = $public_path;
        static::$_appPath = $app_path;
    }

    /**
     * @param TcpConnection $connection
     * @param Request $request
     * @return null
     */
    public function onMessage(TcpConnection $connection, $request)
    {
        try {
            static::$_request = $request;
            static::$_connection = $connection;
            $path = $request->path();
            $key = $request->method() . $path;
            if (isset(static::$_callbacks[$key])) {
                [$callback, $request->plugin, $request->app, $request->controller, $request->action, $request->route] = static::$_callbacks[$key];
                static::send($connection, $callback($request), $request);
                return null;
            }

            if (static::unsafeUri($connection, $path, $request)) {
                return null;
            }

            if (static::findFile($connection, $path, $key, $request)) {
                return null;
            }

            if (static::findRoute($connection, $path, $key, $request)) {
                return null;
            }

            $controller_and_action = static::parseControllerAction($path);
            if (!$controller_and_action || Route::hasDisableDefaultRoute()) {
                $callback = static::getFallback();
                $request->app = $request->controller = $request->action = '';
                static::send($connection, $callback($request), $request);
                return null;
            }
            $plugin = $controller_and_action['plugin'];
            $app = $controller_and_action['app'];
            $controller = $controller_and_action['controller'];
            $action = $controller_and_action['action'];
            $callback = static::getCallback($plugin, $app, [$controller, $action]);
            static::$_callbacks[$key] = [$callback, $plugin, $app, $controller, $action, null];
            [$callback, $request->plugin, $request->app, $request->controller, $request->action, $request->route] = static::$_callbacks[$key];
            static::send($connection, $callback($request), $request);
        } catch (\Throwable $e) {
            static::send($connection, static::exceptionResponse($e, $request), $request);
        }
        return null;
    }

    public function onWorkerStart($worker)
    {
        static::$_worker = $worker;
        Http::requestClass(static::$_requestClass);
    }

    /**
     * @param $connection
     * @param $path
     * @param $request
     * @return bool
     */
    protected static function unsafeUri($connection, $path, $request)
    {
        if (strpos($path, '..') !== false || strpos($path, "\\") !== false || strpos($path, "\0") !== false) {
            $callback = static::getFallback();
            $request->app = $request->controller = $request->action = '';
            static::send($connection, $callback($request), $request);
            return true;
        }
        return false;
    }

    /**
     * @return \Closure
     */
    protected static function getFallback()
    {
        // when route, controller and action not found, try to use Route::fallback
        return Route::getFallback() ?: function () {
            return new Response(404, [], \file_get_contents(static::$_publicPath . '/404.html'));
        };
    }

    /**
     * @param \Throwable $e
     * @param $request
     * @return string|Response
     */
    protected static function exceptionResponse(\Throwable $e, $request)
    {
        try {
            $app = $request->app ?: '';
            $plugin = $request->plugin ?: '';
            $exception_config = static::config($plugin, 'exception');
            $default_exception = $exception_config[''] ?? ExceptionHandler::class;
            $exception_handler_class = $exception_config[$app] ?? $default_exception;

            /** @var ExceptionHandlerInterface $exception_handler */
            $exception_handler = static::container($plugin)->make($exception_handler_class, [
                'logger' => static::$_logger,
                'debug' => Config::get('app.debug')
            ]);
            $exception_handler->report($e);
            $response = $exception_handler->render($request, $e);
            $response->exception($e);
            return $response;
        } catch (\Throwable $e) {
            $response = new Response(500, [], Config::get('app.debug') ? (string)$e : $e->getMessage());
            $response->exception($e);
            return $response;
        }
    }

    /**
     * @param $app
     * @param $call
     * @param null $args
     * @param bool $with_global_middleware
     * @param RouteObject $route
     * @return \Closure|mixed
     */
    protected static function getCallback($plugin, $app, $call, $args = null, bool $with_global_middleware = true, $route = null)
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
            $middlewares[$key][0] = static::container($plugin)->get($item[0]);
        }
        $controller_reuse = static::config($plugin, 'app.controller_reuse', true);
        if (\is_array($call) && is_string($call[0])) {
            if (!$controller_reuse) {
                $call = function ($request, ...$args) use ($call, $plugin) {
                    $call[0] = static::container($plugin)->make($call[0]);
                    return $call($request, ...$args);
                };
            } else {
                $call[0] = static::container($plugin)->get($call[0]);
            }
        }

        if ($middlewares) {
            $callback = array_reduce($middlewares, function ($carry, $pipe) {
                return function ($request) use ($carry, $pipe) {
                    return $pipe($request, $carry);
                };
            }, function ($request) use ($call, $args) {
                try {
                    if ($args === null) {
                        $response = $call($request);
                    } else {
                        $response = $call($request, ...$args);
                    }
                } catch (\Throwable $e) {
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
     * @return ContainerInterface
     */
    public static function container($plugin = '')
    {
        return static::config($plugin, 'container');
    }

    /**
     * @return Request
     */
    public static function request()
    {
        return static::$_request;
    }

    /**
     * @return TcpConnection
     */
    public static function connection()
    {
        return static::$_connection;
    }

    /**
     * @return Worker
     */
    public static function worker()
    {
        return static::$_worker;
    }

    /**
     * @param $connection
     * @param $path
     * @param $key
     * @param Request $request
     * @return bool
     */
    protected static function findRoute($connection, $path, $key, Request $request)
    {
        $ret = Route::dispatch($request->method(), $path);
        if ($ret[0] === Dispatcher::FOUND) {
            $ret[0] = 'route';
            $callback = $ret[1]['callback'];
            $route = clone $ret[1]['route'];
            $plugin = $app = $controller = $action = '';
            $args = !empty($ret[2]) ? $ret[2] : null;
            if ($args) {
                $route->setParams($args);
            }
            if (\is_array($callback)) {
                $controller = $callback[0];
                $plugin = static::getPluginByClass($controller);
                $app = static::getAppByController($controller);
                $action = static::getRealMethod($controller, $callback[1]) ?? '';
            }
            $callback = static::getCallback($plugin, $app, $callback, $args, true, $route);
            static::$_callbacks[$key] = [$callback, $plugin, $app, $controller ?: '', $action, $route];
            [$callback, $request->plugin, $request->app, $request->controller, $request->action, $request->route] = static::$_callbacks[$key];
            static::send($connection, $callback($request), $request);
            if (\count(static::$_callbacks) > 1024) {
                static::clearCache();
            }
            return true;
        }
        return false;
    }

    /**
     * @param $connection
     * @param $path
     * @param $key
     * @param $request
     * @return bool
     */
    protected static function findFile($connection, $path, $key, $request)
    {
        $path_explodes = \explode('/', trim($path, '/'));
        $plugin = '';
        if (isset($path_explodes[2]) && $path_explodes[0] === 'plugin') {
            $public_dir = BASE_PATH . "/{$path_explodes[0]}/{$path_explodes[1]}/public";
            $plugin = $path_explodes[1];
            $path = substr($path, strlen("/{$path_explodes[0]}/{$path_explodes[1]}/"));
        } else {
            $public_dir = static::$_publicPath;
        }
        $file = "$public_dir/$path";

        if (!\is_file($file)) {
            return false;
        }

        if (\pathinfo($file, PATHINFO_EXTENSION) === 'php') {
            if (!static::config($plugin, 'app.support_php_files', false)) {
                return false;
            }
            static::$_callbacks[$key] = [function () use ($file) {
                return static::execPhpFile($file);
            }, '', '', '', '', null];
            [, $request->plugin, $request->app, $request->controller, $request->action, $request->route] = static::$_callbacks[$key];
            static::send($connection, static::execPhpFile($file), $request);
            return true;
        }

        if (!static::config($plugin, 'static.enable', false)) {
            return false;
        }

        static::$_callbacks[$key] = [static::getCallback($plugin, '__static__', function ($request) use ($file) {
            \clearstatcache(true, $file);
            if (!\is_file($file)) {
                $callback = static::getFallback();
                return $callback($request);
            }
            return (new Response())->file($file);
        }, null, false), '', '', '', '', null];
        [$callback, $request->plugin, $request->app, $request->controller, $request->action, $request->route] = static::$_callbacks[$key];
        static::send($connection, $callback($request), $request);
        return true;
    }

    /**
     * @param TcpConnection $connection
     * @param $response
     * @param Request $request
     */
    protected static function send(TcpConnection $connection, $response, Request $request)
    {
        $keep_alive = $request->header('connection');
        static::$_request = static::$_connection = null;
        if (($keep_alive === null && $request->protocolVersion() === '1.1')
            || $keep_alive === 'keep-alive' || $keep_alive === 'Keep-Alive'
        ) {
            $connection->send($response);
            return;
        }
        $connection->close($response);
    }

    /**
     * @param $path
     * @return array|bool
     */
    protected static function parseControllerAction($path)
    {
        $path_explode = \explode('/', trim($path, '/'));
        $is_plugin = isset($path_explode[1]) && $path_explode[0] === 'plugin';
        $config_prefix = $is_plugin ? "{$path_explode[0]}.{$path_explode[1]}." : '';
        $path_prefix = $is_plugin ? "/{$path_explode[0]}/{$path_explode[1]}" : '';
        $class_prefix = $is_plugin ? "{$path_explode[0]}\\{$path_explode[1]}\\" : '';
        $suffix = Config::get("{$config_prefix}app.controller_suffix", '');
        $path_explode = explode('/', trim(substr($path, strlen($path_prefix)), '/'));
        $app = !empty($path_explode[0]) ? $path_explode[0] : 'index';
        $controller = $path_explode[1] ?? 'index';
        $action = $path_explode[2] ?? 'index';

        if (isset($path_explode[2])) {
            $controller_class = "{$class_prefix}app\\$app\\controller\\$controller$suffix";
            if ($controller_action = static::getControllerAction($controller_class, $action)) {
                return $controller_action;
            }
        }

        $controller = $app;
        $action = $path_explode[1] ?? 'index';

        $controller_class = "{$class_prefix}app\\controller\\$controller$suffix";
        if ($controller_action = static::getControllerAction($controller_class, $action)) {
            return $controller_action;
        }

        $controller = $path_explode[1] ?? 'index';
        $action = $path_explode[2] ?? 'index';
        $controller_class = "{$class_prefix}app\\$app\\controller\\$controller$suffix";
        if ($controller_action = static::getControllerAction($controller_class, $action)) {
            return $controller_action;
        }
        return false;
    }

    /**
     * @param $controller_class
     * @param $action
     * @return array|false
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \ReflectionException
     */
    protected static function getControllerAction($controller_class, $action)
    {
        if (static::loadController($controller_class) && ($controller_class = (new \ReflectionClass($controller_class))->name) && \is_callable([$controller_class, $action])) {
            return [
                'plugin' => static::getPluginByClass($controller_class),
                'app' => static::getAppByController($controller_class),
                'controller' => $controller_class,
                'action' => static::getRealMethod($controller_class, $action)
            ];
        }
        return false;
    }

    /**
     * @param $controller_class
     * @return bool
     */
    protected static function loadController($controller_class)
    {
        static $controller_files = [];
        if (empty($controller_files)) {
            $app_path = strpos($controller_class, '\plugin') === 0 ? BASE_PATH : static::$_appPath;
            $dir_iterator = new \RecursiveDirectoryIterator($app_path, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS);
            $iterator = new \RecursiveIteratorIterator($dir_iterator);
            $app_base_path_length = \strrpos($app_path, DIRECTORY_SEPARATOR) + 1;
            foreach ($iterator as $spl_file) {
                $file = (string)$spl_file;
                if (\is_dir($file) || false === \strpos(strtolower($file), '/controller/') || $spl_file->getExtension() !== 'php') {
                    continue;
                }
                $controller_files[$file] = \str_replace(DIRECTORY_SEPARATOR, "\\", \strtolower(\substr(\substr($file, $app_base_path_length), 0, -4)));
            }
        }

        if (\class_exists($controller_class)) {
            return true;
        }

        $controller_class = \strtolower($controller_class);
        if ($controller_class[0] === "\\") {
            $controller_class = \substr($controller_class, 1);
        }
        foreach ($controller_files as $real_path => $class_name) {
            if ($class_name === $controller_class) {
                require_once $real_path;
                if (\class_exists($controller_class, false)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @param $controller_class
     * @return string
     */
    public static function getPluginByClass($controller_class)
    {
        $controller_class = trim($controller_class, '\\');
        $tmp = \explode('\\', $controller_class, 3);
        if ($tmp[0] !== 'plugin') {
            return '';
        }
        return $tmp[1] ?? '';
    }

    /**
     * @param $controller_class
     * @return string
     */
    protected static function getAppByController($controller_class)
    {
        $controller_class = trim($controller_class, '\\');
        $tmp = \explode('\\', $controller_class, 5);
        $pos = $tmp[0] === 'plugin' ? 3 : 1;
        if (!isset($tmp[$pos])) {
            return '';
        }
        return strtolower($tmp[$pos]) === 'controller' ? '' : $tmp[$pos];
    }

    /**
     * @param $file
     * @return string
     */
    public static function execPhpFile($file)
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
     * Clear cache.
     */
    public static function clearCache()
    {
        static::$_callbacks = [];
    }

    /**
     * @param $class
     * @param $method
     * @return string
     */
    protected static function getRealMethod($class, $method)
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
     * @param $plugin
     * @param $key
     * @param $default
     * @return array|mixed|null
     */
    protected static function config($plugin, $key, $default = null)
    {
        return Config::get($plugin ? "plugin.$plugin.$key" : $key, $default);
    }

}
