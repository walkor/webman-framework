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

use support\Request;
use support\Response;
use support\Translation;
use support\Container;
use support\view\Raw;
use support\view\Blade;
use support\view\ThinkPHP;
use support\view\Twig;
use Workerman\Worker;
use Webman\App;
use Webman\Config;
use Webman\Route;


if (!function_exists('response')) {
    /**
     * @param int $status
     * @param array $headers
     * @param string $body
     * @return Response
     */
    function response($body = '', $status = 200, $headers = array())
    {
        return new Response($status, $headers, $body);
    }
}

if (!function_exists('json')) {
    /**
     * @param $data
     * @param int $options
     * @return Response
     */
    function json($data, $options = JSON_UNESCAPED_UNICODE)
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode($data, $options));
    }
}

if (!function_exists('xml')) {
    /**
     * @param $xml
     * @return Response
     */
    function xml($xml)
    {
        if ($xml instanceof SimpleXMLElement) {
            $xml = $xml->asXML();
        }
        return new Response(200, ['Content-Type' => 'text/xml'], $xml);
    }
}

if (!function_exists('jsonp')) {
    /**
     * @param $data
     * @param string $callback_name
     * @return Response
     */
    function jsonp($data, $callback_name = 'callback')
    {
        if (!is_scalar($data) && null !== $data) {
            $data = json_encode($data);
        }
        return new Response(200, [], "$callback_name($data)");
    }
}

if (!function_exists('redirect')) {
    /**
     * @param $location
     * @param int $status
     * @param array $headers
     * @return Response
     */
    function redirect($location, $status = 302, $headers = [])
    {
        $response = new Response($status, ['Location' => $location]);
        if (!empty($headers)) {
            $response->withHeaders($headers);
        }
        return $response;
    }
}

if (!function_exists('view')) {
    /**
     * @param $template
     * @param array $vars
     * @param null $app
     * @return Response
     */
    function view($template, $vars = [], $app = null)
    {
        static $handler;
        if (null === $handler) {
            $handler = config('view.handler');
        }
        return new Response(200, [], $handler::render($template, $vars, $app));
    }
}

if (!function_exists('raw_view')) {
    /**
     * @param $template
     * @param array $vars
     * @param null $app
     * @return Response
     */
    function raw_view($template, $vars = [], $app = null)
    {
        return new Response(200, [], Raw::render($template, $vars, $app));
    }
}

if (!function_exists('blade_view')) {
    /**
     * @param $template
     * @param array $vars
     * @param null $app
     * @return Response
     */
    function blade_view($template, $vars = [], $app = null)
    {
        return new Response(200, [], Blade::render($template, $vars, $app));
    }
}

if (!function_exists('think_view')) {
    /**
     * @param $template
     * @param array $vars
     * @param null $app
     * @return Response
     */
    function think_view($template, $vars = [], $app = null)
    {
        return new Response(200, [], ThinkPHP::render($template, $vars, $app));
    }
}

if (!function_exists('twig_view')) {
    /**
     * @param $template
     * @param array $vars
     * @param null $app
     * @return Response
     */
    function twig_view($template, $vars = [], $app = null)
    {
        return new Response(200, [], Twig::render($template, $vars, $app));
    }
}

if (!function_exists('request')) {
    /**
     * @return Request
     */
    function request()
    {
        return App::request();
    }
}

if (!function_exists('config')) {
    /**
     * @param $key
     * @param null $default
     * @return mixed
     */
    function config($key = null, $default = null)
    {
        return Config::get($key, $default);
    }
}

if (!function_exists('route')) {
    /**
     * @param $name
     * @param ...$parameters
     * @return string
     */
    function route($name, ...$parameters)
    {
        $route = Route::getByName($name);
        if (!$route) {
            return '';
        }

        if (!$parameters) {
            return $route->url();
        }

        if (is_array(current($parameters))) {
            $parameters = current($parameters);
        }

        return $route->url($parameters);
    }
}

if (!function_exists('session')) {
    /**
     * @param mixed $key
     * @param mixed $default
     * @return mixed
     */
    function session($key = null, $default = null)
    {
        $session = request()->session();
        if (null === $key) {
            return $session;
        }
        if (\is_array($key)) {
            $session->put($key);
            return null;
        }
        if (\strpos($key, '.')) {
            $key_array = \explode('.', $key);
            $value = $session->all();
            foreach ($key_array as $index) {
                if (!isset($value[$index])) {
                    return $default;
                }
                $value = $value[$index];
            }
            return $value;
        }
        return $session->get($key, $default);
    }
}

if (!function_exists('trans')) {
    /**
     * @param null|string $id
     * @param array $parameters
     * @param string|null $domain
     * @param string|null $locale
     * @return string
     */
    function trans(string $id, array $parameters = [], string $domain = null, string $locale = null)
    {
        $res = Translation::trans($id, $parameters, $domain, $locale);
        return $res === '' ? $id : $res;
    }
}

if (!function_exists('locale')) {
    /**
     * @param null|string $locale
     * @return string
     */
    function locale(string $locale = null)
    {
        if (!$locale) {
            return Translation::getLocale();
        }
        Translation::setLocale($locale);
    }
}

if (!function_exists('not_found')) {
    /**
     * 404 not found
     *
     * @return Response
     */
    function not_found()
    {
        return new Response(404, [], file_get_contents(public_path() . '/404.html'));
    }
}

if (!function_exists('worker_bind')) {
    /**
     * @param $worker
     * @param $class
     */
    function worker_bind($worker, $class)
    {
        $callback_map = [
            'onConnect',
            'onMessage',
            'onClose',
            'onError',
            'onBufferFull',
            'onBufferDrain',
            'onWorkerStop',
            'onWebSocketConnect'
        ];
        foreach ($callback_map as $name) {
            if (method_exists($class, $name)) {
                $worker->$name = [$class, $name];
            }
        }
        if (method_exists($class, 'onWorkerStart')) {
            call_user_func([$class, 'onWorkerStart'], $worker);
        }
    }
}

if (!function_exists('worker_start')) {
    /**
     * @param $process_name
     * @param $config
     * @return void
     */
    function worker_start($process_name, $config)
    {
        $worker = new Worker($config['listen'] ?? null, $config['context'] ?? []);
        $property_map = [
            'count',
            'user',
            'group',
            'reloadable',
            'reusePort',
            'transport',
            'protocol',
        ];
        $worker->name = $process_name;
        foreach ($property_map as $property) {
            if (isset($config[$property])) {
                $worker->$property = $config[$property];
            }
        }

        $worker->onWorkerStart = function ($worker) use ($config) {
            require_once base_path() . '/support/bootstrap.php';

            foreach ($config['services'] ?? [] as $server) {
                if (!class_exists($server['handler'])) {
                    echo "process error: class {$server['handler']} not exists\r\n";
                    continue;
                }
                $listen = new Worker($server['listen'] ?? null, $server['context'] ?? []);
                if (isset($server['listen'])) {
                    echo "listen: {$server['listen']}\n";
                }
                $instance = Container::make($server['handler'], $server['constructor'] ?? []);
                worker_bind($listen, $instance);
                $listen->listen();
            }

            if (isset($config['handler'])) {
                if (!class_exists($config['handler'])) {
                    echo "process error: class {$config['handler']} not exists\r\n";
                    return;
                }

                $instance = Container::make($config['handler'], $config['constructor'] ?? []);
                worker_bind($worker, $instance);
            }

        };
    }
}

if (!function_exists('get_realpath')) {
    /**
     * Phar support.
     * Compatible with the 'realpath' function in the phar file.
     *
     * @param string $file_path
     * @return string
     */
    function get_realpath(string $file_path): string
    {
        if (strpos($file_path, 'phar://') === 0) {
            return $file_path;
        } else {
            return realpath($file_path);
        }
    }
}

if (!function_exists('cpu_count')) {
    /**
     * @return int
     */
    function cpu_count()
    {
        // Windows does not support the number of processes setting.
        if (\DIRECTORY_SEPARATOR === '\\') {
            return 1;
        }
        $count = 4;
        if (is_callable('shell_exec')) {
            if (strtolower(PHP_OS) === 'darwin') {
                $count = (int)shell_exec('sysctl -n machdep.cpu.core_count');
            } else {
                $count = (int)shell_exec('nproc');
            }
        }
        return $count > 0 ? $count : 4;
    }
}
