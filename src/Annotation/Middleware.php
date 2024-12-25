<?php

namespace Webman\Annotation;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION)]
class Middleware
{
    protected $middlewares = [];

    public function __construct(...$middlewares)
    {
        $this->middlewares = $middlewares;
    }

    public function getMiddlewares(): array
    {
        $middlewares = [];
        foreach ($this->middlewares as $middleware) {
            if (class_exists($middleware) && method_exists($middleware, 'process')) {
                $middlewares[] = [$middleware, 'process'];
            }
        }
        return $middlewares;
    }
}