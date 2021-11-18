<?php


namespace Webman\route;

use Webman\App;
use Webman\Route\Route as RouteObject;

class RouteCollector extends \FastRoute\RouteCollector
{
    /**
     * @var array
     */
    protected static $_nameList = [];

    /**
     * @var null|callable
     */
    protected static $_fallback = null;
    /**
     * @var bool
     */
    protected static $_disableDefaultRoute = false;

    /**
     * @var RouteObject[]
     */
    protected $_routes = [];

    /**
     * @param $path
     * @param $callback
     * @return RouteObject
     */
    public function get($path, $callback)
    {
        return $this->addRouter('GET', $path, $callback);
    }

    /**
     * @param $path
     * @param $callback
     * @return RouteObject
     */
    public function post($path, $callback)
    {
        return $this->addRouter('POST', $path, $callback);
    }

    /**
     * @param $path
     * @param $callback
     * @return RouteObject
     */
    public function put($path, $callback)
    {
        return $this->addRouter('PUT', $path, $callback);
    }

    /**
     * @param $path
     * @param $callback
     * @return RouteObject
     */
    public function patch($path, $callback)
    {
        return $this->addRouter('PATCH', $path, $callback);
    }

    /**
     * @param $path
     * @param $callback
     * @return RouteObject
     */
    public function delete($path, $callback)
    {
        return $this->addRouter('DELETE', $path, $callback);
    }

    /**
     * @param $path
     * @param $callback
     * @return RouteObject
     */
    public function head($path, $callback)
    {
        return $this->addRouter('HEAD', $path, $callback);
    }

    /**
     * @param $path
     * @param $callback
     * @return RouteObject
     */
    public function options($path, $callback)
    {
        return $this->addRouter('OPTIONS', $path, $callback);
    }

    /**
     * @param $path
     * @param $callback
     * @return RouteObject
     */
    public function any($path, $callback)
    {
        return $this->addRouter(['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'], $path, $callback);
    }

    /**
     * @param $method
     * @param $path
     * @param $callback
     * @return RouteObject
     */
    public function add($method, $path, $callback)
    {
        return $this->addRouter($method, $path, $callback);
    }

    /**
     * @param $path
     * @param $callback
     * @return $this
     */
    public function group($path, $callback)
    {
        $this->addGroup($path, $callback);
        return $this;
    }

    /**
     * disableDefaultRoute.
     */
    public static function disableDefaultRoute()
    {
        static::$_disableDefaultRoute = true;
    }

    /**
     * @return bool
     */
    public static function hasDisableDefaultRoute()
    {
        return static::$_disableDefaultRoute === true;
    }

    /**
     * @param $middleware
     * @return $this
     */
    public function middleware($middleware)
    {
        foreach ($this->_routes as $route) {
            $route->middleware($middleware);
        }
        return $this;
    }

    /**
     * @param RouteObject $route
     */
    protected function collect(RouteObject $route)
    {
        $this->_routes[] = $route;
    }

    /**
     * @param $name
     * @param RouteObject $instance
     */
    public function setByName($name, RouteObject $instance)
    {
        static::$_nameList[$name] = $instance;
    }

    /**
     * @param $name
     * @return null|RouteObject
     */
    public function getByName($name)
    {
        return static::$_nameList[$name] ?? null;
    }

    /**
     * @param $path
     * @param $callback
     * @return array|bool|callable
     */
    protected static function convertToCallable($path, $callback)
    {
        if (\is_string($callback) && \strpos($callback, '@')) {
            $callback = \explode('@', $callback, 2);
        }

        if (\is_array($callback)) {
            $callback = \array_values($callback);
            if (isset($callback[1]) && \is_string($callback[0]) && \class_exists($callback[0])) {
                $callback = [App::container()->get($callback[0]), $callback[1]];
            }
        }

        if (!\is_callable($callback)) {
            echo "Route set to $path is not callable\n";
            return false;
        }

        return $callback;
    }

    /**
     * @return callable|null
     */
    public function getFallback() {
        return is_callable(static::$_fallback) ? static::$_fallback : null;
    }

    /**
     * @param callable $callback
     */
    public function fallback(callable $callback) {
        if (is_callable($callback)) {
            static::$_fallback = $callback;
        }
    }

    /**
     * @return RouteObject[]
     */
    public function getRoutes()
    {
        return $this->_routes;
    }

    /**
     * @param $method
     * @param $path
     * @param $callback
     * @return RouteObject
     */
    protected function addRouter($method, $path, $callback)
    {
        $route = new RouteObject($method, $this->currentGroupPrefix . $path, $callback);
        if ($callback = static::convertToCallable($path, $callback)) {
            $this->addRoute($method, $path, ['callback' => $callback, 'route' => $route]);
        }
        $this->collect($route);
        return $route;
    }
}
