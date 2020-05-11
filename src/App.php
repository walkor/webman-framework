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

use Workerman\Worker;
use Workerman\Timer;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http;
use support\Request;
use support\Response;
use Webman\Exception\ExceptionHandlerInterface;
use support\exception\Handler;
use FastRoute\Dispatcher;
use support\bootstrap\Log;

/**
 * Class App
 * @package Webman
 */
class App
{

    /**
     * @var bool
     */
    protected static $_enableStatic = true;

    /**
     * @var bool
     */
    protected static $_enablePHP = false;

    /**
     * @var array
     */
    protected static $_callbacks = [];

    /**
     * @var Worker
     */
    protected static $_worker = null;

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
     * @var int
     */
    protected static $_maxRequestCount = 1000000;

    /**
     * @var int
     */
    protected static $_gracefulStopTimer = null;

    /**
     * App constructor.
     * @param Worker $worker
     * @param $request_class
     */
    public function __construct(Worker $worker, $request_class)
    {
        if ($timezone = config('app.default_timezone')) {
            date_default_timezone_set($timezone);
        }
        static::$_worker = $worker;
        static::loadController(app_path());
        Route::load(config_path() . '/route.php');
        Middleware::load(config('middleware', []));
        Http::requestClass($request_class);
        static::onWorkerStart($worker);
        $max_requst_count = (int)config('server.max_request');
        if ($max_requst_count > 0) {
            static::$_maxRequestCount = $max_requst_count;
        }
    }

    /**
     * @param TcpConnection $connection
     * @param Request $request
     * @return null
     */
    public function onMessage(TcpConnection $connection, $request)
    {
        static $request_count = 0;
        if (++$request_count > static::$_maxRequestCount) {
            static::tryToGracefulExit();
        }

        try {
            static::$_request = $request;
            static::$_connection = $connection;
            $path = $request->path();
            $key = $request->method() . $path;

            if (isset(static::$_callbacks[$key])) {
                list($callback, $request->app, $request->controller, $request->action) = static::$_callbacks[$key];
                static::send($connection, $callback($request), $request);
                return null;
            }

            if (static::findRoute($connection, $path, $key, $request)) {
                return null;
            }

            if (static::findFile($connection, $path, $key, $request)) {
                return null;
            }

            $controller_and_action = static::parseControllerAction($path);
            if (!$controller_and_action) {
                // when route, controller and action not found, try to use Route::fallback
                if (($fallback = Route::getFallback()) !== null) {
                    static::send($connection, $fallback($request), $request);
                } else {
                    static::send404($connection, $request);
                }
                return null;
            }
            $app = $controller_and_action['app'];
            $controller = $controller_and_action['controller'];
            $action = $controller_and_action['action'];
            $callback = static::getCallback($app, [singleton($controller), $action]);
            static::$_callbacks[$key] = [$callback, $app, $controller, $action];
            list($callback, $request->app, $request->controller, $request->action) = static::$_callbacks[$key];
            static::send($connection, $callback($request), $request);
        } catch (\Throwable $e) {
            try {
                $app = $request->app ? : '';
                $exception_config = config('exception');
                $exception_handler_class = $exception_config[$app] ?? Handler::class;
                /** @var ExceptionHandlerInterface $exception_handler */
                $exception_handler = \singleton($exception_handler_class, [Log::channel('default')]);
                $exception_handler->report($e);
                $response = $exception_handler->render($request, $e);
                static::send($connection, $response, $request);
            } catch (\Throwable $e) {
                static::send($connection, config('app.debug') ? (string)$e : $e->getMessage(), $request);
            }
        }
        return null;
    }

    /**
     * @param $app
     * @param $call
     * @param null $args
     * @return \Closure|mixed
     */
    protected static function getCallback($app, $call, $args = null)
    {
        $args = $args === null ? null : \array_values($args);
        $middleware = Middleware::getMiddleware($app);
        if ($middleware) {
            $callback = array_reduce($middleware, function ($carry, $pipe) {
                return function ($request) use ($carry, $pipe) {
                    return $pipe($request, $carry);
                };
            }, function ($request) use ($call, $args) {

                if ($args === null) {
                    return $call($request);
                } else {
                    return $call($request, ...$args);
                }
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
     * @param $worker
     */
    public static function onWorkerStart($worker) {
        foreach (config('bootstrap', []) as $class_name) {
            /** @var \Webman\Bootstrap $class_name */
            $class_name::start($worker);
        }
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
            $callback = $ret[1];
            $app = $controller = $action = '';
            $args = !empty($ret[2]) ? $ret[2] : null;
            if (\is_array($callback) && isset($callback[0]) && $controller = \get_class($callback[0])) {
                $app = static::getAppByController($controller);
                $action = static::getRealMethod($controller, $callback[1]) ?? '';
            }
            $callback = static::getCallback($app, $callback, $args);
            static::$_callbacks[$key] = [$callback, $app, $controller ? $controller : '', $action];
            list($callback, $request->app, $request->controller, $request->action) = static::$_callbacks[$key];
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
        $public_dir = \public_path();
        $file = \realpath("$public_dir/$path");
        if (false === $file || false === \is_file($file)) {
            return false;
        }

        // Security check
        if (strpos($file, $public_dir) !== 0) {
            static::send($connection, new Response(400), $request);
            return true;
        }
        if (\pathinfo($file, PATHINFO_EXTENSION) === 'php') {
            static::$_callbacks[$key] = [function ($request) use ($file) {
                return static::execPhpFile($file);
            }, '', '', ''];
            list($callback, $request->app, $request->controller, $request->action) = static::$_callbacks[$key];
            static::send($connection, static::execPhpFile($file), $request);
            return true;
        }
        static::$_callbacks[$key] = [function ($request) use ($file) {
            return (new Response())->file($file);
        }, '', '', ''];
        list($callback, $request->app, $request->controller, $request->action) = static::$_callbacks[$key];
        static::send($connection, (new Response())->file($file), $request);
        return true;
    }

    /**
     * @param TcpConnection $connection
     * @param $response
     * @param Request $request
     */
    protected static function send(TcpConnection $connection, $response, Request $request)
    {
        static::$_connection = static::$_request = null;
        $keep_alive = $request->header('connection');
        if (($keep_alive === null && $request->protocolVersion() === '1.1')
            || $keep_alive === 'keep-alive' || $keep_alive === 'Keep-Alive'
        ) {
            $connection->send($response);
            return;
        }
        $connection->close($response);
    }

    /**
     * @param TcpConnection $connection
     * @param $request
     */
    protected static function send404(TcpConnection $connection, $request)
    {
        static::send($connection, new Response(404, [], '<h3>404 Not Found</h3>'), $request);
    }

    /**
     * @param $path
     * @return array|bool
     */
    protected static function parseControllerAction($path)
    {
        if ($path === '/' || $path === '') {
            $controller_class = '\app\controller\Index';
            $action = 'index';
            if (\class_exists($controller_class, false) && \is_callable([\singleton($controller_class), $action])) {
                return [
                    'app' => '',
                    'controller' => \app\controller\Index::class,
                    'action' => static::getRealMethod($controller_class, $action)
                ];
            }
            $controller_class = '\app\index\controller\Index';
            if (\class_exists($controller_class, false) && \is_callable([\singleton($controller_class), $action])) {
                return [
                    'app' => 'index',
                    'controller' => \app\index\controller\Index::class,
                    'action' => static::getRealMethod($controller_class, $action)
                ];
            }
            return false;
        }
        if ($path[0] === '/') {
            $path = \substr($path, 1);
        }
        $explode = \explode('/', $path);
        $action = 'index';

        $controller = $explode[0];
        if ($controller === '') {
            return false;
        }
        if (!empty($explode[1])) {
            $action = $explode[1];
        }
        $controller_class = "\\app\controller\\$controller";
        if (\class_exists($controller_class, false) && \is_callable([\singleton($controller_class), $action])) {
            return [
                'app' => '',
                'controller' => \get_class(\singleton($controller_class)),
                'action' => static::getRealMethod($controller_class, $action)
            ];
        }

        $app = $explode[0];
        $controller = 'index';
        if (!empty($explode[1])) {
            $controller = $explode[1];
            if (!empty($explode[2])) {
                $action = $explode[2];
            }
        }
        $controller_class = "\\app\\$app\\controller\\$controller";
        if (\class_exists($controller_class, false) && \is_callable([\singleton($controller_class), $action])) {
            return [
                'app' => $app,
                'controller' => \get_class(\singleton($controller_class)),
                'action' => static::getRealMethod($controller_class, $action)
            ];
        }
        return false;
    }

    /**
     * @param $controller_calss
     * @return string
     */
    protected static function getAppByController($controller_calss)
    {
        if ($controller_calss[0] === '\\') {
            $controller_calss = \substr($controller_calss, 1);
        }
        $tmp = \explode('\\', $controller_calss, 3);
        if (!isset($tmp[1])) {
            return '';
        }
        return $tmp[1] === 'controller' ? '' : $tmp[1];
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
     * @return void
     */
    public static function loadController($path)
    {
        foreach (\glob($path. '/controller/*.php') as $file) {
            require_once $file;
        }
        foreach (\glob($path . '/*/controller/*.php') as $file) {
            require_once $file;
        }
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
     * @return void
     */
    protected static function tryToGracefulExit()
    {
        if (static::$_gracefulStopTimer === null) {
            static::$_gracefulStopTimer = Timer::add(rand(1, 10), function(){
                if (\count(static::$_worker->connections) === 0) {
                    Worker::stopAll();
                }
            });
        }
    }
}