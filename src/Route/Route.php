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
use function array_merge;
use function count;
use function preg_replace_callback;
use function str_replace;

/**
 * Class Route
 * @package Webman
 */
class Route
{
    /**
     * @var string|null
     */
    protected $name = null;

    /**
     * @var array
     */
    protected $methods = [];

    /**
     * @var string
     */
    protected $path = '';

    /**
     * @var callable
     */
    protected $callback = null;

    /**
     * @var array
     */
    protected $middlewares = [];

    /**
     * Attribute middlewares exposed as route metadata.
     *
     * They are resolved separately by Webman\Middleware at runtime, so keeping
     * them out of $middlewares prevents duplicate execution.
     * @var array
     */
    protected $attributeMiddlewares = [];

    /**
     * @var array
     */
    protected $params = [];

    /**
     * Route constructor.
     * @param array $methods
     * @param string $path
     * @param callable $callback
     */
    public function __construct($methods, string $path, $callback)
    {
        $this->methods = (array)$methods;
        $this->path = $path;
        $this->callback = $callback;
    }

    /**
     * Get name.
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name ?? null;
    }

    /**
     * Name.
     * @param string $name
     * @return $this
     */
    public function name(string $name): Route
    {
        $this->name = $name;
        Router::setByName($name, $this);
        return $this;
    }

    /**
     * Middleware.
     * @param mixed $middleware
     * @return $this|array
     */
    public function middleware(mixed $middleware = null)
    {
        if ($middleware === null) {
            return $this->middlewares;
        }
        $this->middlewares = array_merge($this->middlewares, is_array($middleware) ? array_reverse($middleware) : [$middleware]);
        return $this;
    }

    /**
     * Add attribute middlewares as route metadata.
     *
     * Attribute middlewares are executed through controller reflection rather
     * than as route middlewares. Keeping them as metadata makes them visible to
     * route inspection tools without adding them to the execution chain twice.
     * @param mixed $middleware
     * @return $this
     */
    public function attributeMiddleware(mixed $middleware): Route
    {
        $middlewares = is_array($middleware) ? array_reverse($middleware) : [$middleware];
        $this->attributeMiddlewares = array_merge($this->attributeMiddlewares, $middlewares);
        return $this;
    }

    /**
     * GetPath.
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * GetMethods.
     * @return array
     */
    public function getMethods(): array
    {
        return $this->methods;
    }

    /**
     * GetCallback.
     * @return callable|null
     */
    public function getCallback()
    {
        return $this->callback;
    }

    /**
     * GetMiddleware.
     * @return array
     */
    public function getMiddleware(): array
    {
        return $this->attributeMiddlewares
            ? array_merge($this->middlewares, $this->attributeMiddlewares)
            : $this->middlewares;
    }

    /**
     * Get middlewares explicitly attached to this route.
     * @return array
     */
    public function getRouteMiddleware(): array
    {
        return $this->attributeMiddlewares ? $this->middlewares : $this->getMiddleware();
    }

    /**
     * Param.
     * @param string|null $name
     * @param mixed $default
     * @return mixed
     */
    public function param(?string $name = null, mixed $default = null)
    {
        if ($name === null) {
            return $this->params;
        }
        return $this->params[$name] ?? $default;
    }

    /**
     * SetParams.
     * @param array $params
     * @return $this
     */
    public function setParams(array $params): Route
    {
        $this->params = array_merge($this->params, $params);
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
            return $this->path;
        }
        $path = str_replace(['[', ']'], '', $this->path);
        $path = preg_replace_callback('/\{(.*?)(?:\:[^\}]*?)*?\}/', function ($matches) use (&$parameters) {
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
        return count($parameters) > 0 ? $path . '?' . http_build_query($parameters) : $path;
    }

}
