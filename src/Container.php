<?php
namespace Webman;

use Psr\Container\ContainerInterface;

/**
 * Class Container
 * @package Webman
 */
class Container implements ContainerInterface
{

    protected $_instances = [];

    public function get($name)
    {
        if (!isset($this->_instances[$name])) {
            if (!class_exists($name)) {
                throw new NotFoundException("Class '$name' not found");
            }
            $this->_instances[$name] = new $name();
        }
        return $this->_instances[$name];
    }

    public function has($name)
    {
        return \array_key_exists($name, $this->_instances);
    }

    public function make($name, array $constructor = [])
    {
        if (!class_exists($name)) {
            throw new NotFoundException("Class '$name' not found");
        }
        return new $name(... array_values($constructor));
    }

}