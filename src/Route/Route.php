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
    protected $_middlewares = [];

    /**
     * @var array
     */
    protected $_params = [];

    /**
     * Route constructor.
     * @param array $methods
     * @param string $path
     * @param callable $callback
     */
    public function __construct($methods, string $path, $callback)
    {
        $this->_methods = (array)$methods;
        $this->_path = $path;
        $this->_callback = $callback;
    }

    /**
     * Get name.
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->_name ?? null;
    }

    /**
     * Name.
     * @param string $name
     * @return $this
     */
    public function name(string $name): Route
    {
        $this->_name = $name;
        Router::setByName($name, $this);
        return $this;
    }

    /**
     * Middleware.
     * @param mixed $middleware
     * @return $this|array
     */
    public function middleware($middleware = null)
    {
        if ($middleware === null) {
            return $this->_middlewares;
        }
        $this->_middlewares = \array_merge($this->_middlewares, is_array($middleware) ? $middleware : [$middleware]);
        return $this;
    }

    /**
     * GetPath.
     * @return string
     */
    public function getPath(): string
    {
        return $this->_path;
    }

    /**
     * GetMethods.
     * @return array
     */
    public function getMethods(): array
    {
        return $this->_methods;
    }

    /**
     * GetCallback.
     * @return callable
     */
    public function getCallback(): ?callable
    {
        return $this->_callback;
    }

    /**
     * GetMiddleware.
     * @return array
     */
    public function getMiddleware(): array
    {
        return $this->_middlewares;
    }

    /**
     * Param.
     * @param string|null $name
     * @param $default
     * @return array|mixed|null
     */
    public function param(string $name = null, $default = null)
    {
        if ($name === null) {
            return $this->_params;
        }
        return $this->_params[$name] ?? $default;
    }

    /**
     * SetParams.
     * @param array $params
     * @return $this
     */
    public function setParams(array $params): Route
    {
        $this->_params = \array_merge($this->_params, $params);
        return $this;
    }

    /**
     * Url.
     * @param array $parameters
     * @return string
     */
    public function url(array $parameters = []): string
    {
        if (empty($parameters)) {
            return $this->_path;
        }
        $path = \str_replace(['[', ']'], '', $this->_path);
        $path = \preg_replace_callback('/\{(.*?)(?:\:[^\}]*?)*?\}/', function ($matches) use (&$parameters) {
            if (!$parameters) {
                return $matches[0];
            }
            if (isset($parameters[$matches[1]])) {
                $value = $parameters[$matches[1]];
                unset($parameters[$matches[1]]);
                return $value;
            }
            $key = key($parameters);
            if (is_int($key)) {
                $value = $parameters[$key];
                unset($parameters[$key]);
                return $value;
            }
            return $matches[0];
        }, $path);
        return \count($parameters) > 0 ? $path . '?' . http_build_query($parameters) : $path;
    }

}
