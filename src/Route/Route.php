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
namespace Webman\Route;

use FastRoute\Dispatcher\GroupCountBased;
use FastRoute\RouteCollector;
use Webman\App;
use Webman\Route as Router;

/**
 * Class Route
 * @package Webman
 */
class Route
{
    /**
     * @var string|null
     */
    protected $_name = null;

    /**
     * @var array
     */
    protected $_methods = [];

    /**
     * @var string
     */
    protected $_path = '';

    /**
     * @var callable
     */
    protected $_callback = null;

    /**
     * @var array
     */
    protected $_middleware = [];

    /**
     * Route constructor.
     * @param $methods
     * @param $path
     */
    public function __construct($methods, $path, $callback)
    {
        $this->_methods = (array) $methods;
        $this->_path = $path;
        $this->_callback = $callback;
    }

    /**
     * @return mixed|null
     */
    public function getName()
    {
        return $this->_name ?? null;
    }

    /**
     * @param $name
     * @return $this
     */
    public function name($name)
    {
        $this->_name = $name;
        Router::setByName($name, $this);
        return $this;
    }

    /**
     * @param null $middleware
     * @return $this|array
     */
    public function middleware($middleware = null)
    {
        if ($middleware === null) {
            return $this->_middleware;
        }
        $this->_middleware = array_merge($this->_middleware, (array)$middleware);
        return $this;
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->_path;
    }

    /**
     * @return array
     */
    public function getMethods()
    {
        return $this->_methods;
    }

    /**
     * @return callable
     */
    public function getCallback()
    {
        return $this->_callback;
    }

    /**
     * @return array
     */
    public function getMiddleware()
    {
        $middleware = [];
        foreach ($this->_middleware as $class_name) {
            $middleware[] = [App::container()->get($class_name), 'process'];
        }
        return array_reverse($middleware);
    }

    /**
     * @param $parameters
     * @return string
     */
    public function url($parameters = [])
    {
        if (empty($parameters)) {
            return $this->_path;
        }
        return preg_replace_callback('/\{(.*?)(?:\:[^\}]*?)*?\}/', function ($matches) use ($parameters) {
            if (isset($parameters[$matches[1]])) {
                return $parameters[$matches[1]];
            }
            return $matches[0];
        }, $this->_path);
    }

}
