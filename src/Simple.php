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
use support\exception\ExceptionHandler;
use FastRoute\Dispatcher;
use support\bootstrap\Log;

/**
 * Class App
 * @package Webman
 */
class Simple extends App
{

    /**
     * @param TcpConnection $connection
     * @param string $request
     * @return null
     */
    public function onMessage($connection, $request)
    {
        static $callbacks = [];
        try {
            $callback = $callbacks[$request] ?? null;
            if (null !== $callback) {
                $connection->send($ret = (string)$callback($request), true);
                return null;
            }

            list($method, $path, $protocol) = \explode(' ', $request);
            $ret = Route::dispatch($method, $path);
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
                $callbacks[$request] = $callback;
                $connection->send((string)$callback($request), true);
                return true;
            }

            $controller_and_action = static::parseControllerAction($path);
            if (false === $controller_and_action) {
                static::send404($connection, $request);
                return null;
            }
            $app = $controller_and_action['app'];
            $controller = $controller_and_action['controller'];
            $action = $controller_and_action['action'];
            $callback = static::getCallback($app, [singleton($controller), $action]);
            $callback[$request] = $callback;
            $connection->send((string)$callback($request), true);
        } catch (\Throwable $e) {
           echo $e;
        }
        return null;
    }

}