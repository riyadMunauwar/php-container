<?php

use Closure;
use ReflectionClass;
use ReflectionParameter;

class Container
{
    protected $instances = [];
    protected $bindings = [];
    protected $resolving = [];

    public function bind($abstract, $concrete = null, $shared = false)
    {
        if (is_null($concrete)) {
            $concrete = $abstract;
        }

        if (!$concrete instanceof Closure) {
            $concrete = $this->getClosure($concrete);
        }

        $this->bindings[$abstract] = compact('concrete', 'shared');
    }

    public function instance($abstract, $instance)
    {
        $this->instances[$abstract] = $instance;
    }

    public function make($abstract, array $parameters = [])
    {
        $concrete = $this->getConcrete($abstract);

        if ($this->isBuildable($concrete, $abstract)) {
            $object = $this->build($concrete, $parameters);
        } else {
            $object = $this->make($concrete, $parameters);
        }

        if (isset($this->instances[$abstract])) {
            $object = $this->instances[$abstract];
        } elseif (isset($this->bindings[$abstract]['shared']) && $this->bindings[$abstract]['shared']) {
            $this->instances[$abstract] = $object;
        }

        return $object;
    }

    protected function getConcrete($abstract)
    {
        if ($concrete = $this->getContextualConcrete($abstract)) {
            return $concrete;
        }

        return $this->bindings[$abstract]['concrete'];
    }

    protected function getContextualConcrete($abstract)
    {
        if (isset($this->resolving[$abstract])) {
            return $this->bindings[$this->resolving[$abstract]]['concrete'];
        }
    }

    protected function isBuildable($concrete, $abstract)
    {
        return $concrete === $abstract || $concrete instanceof Closure;
    }

    protected function build($concrete, $parameters = [])
    {
        if ($concrete instanceof Closure) {
            return $concrete($this, $parameters);
        }

        $reflector = new ReflectionClass($concrete);
        $constructor = $reflector->getConstructor();

        if (is_null($constructor)) {
            return new $concrete;
        }

        $dependencies = $this->resolveDependencies($constructor->getParameters(), $parameters);

        return $reflector->newInstanceArgs($dependencies);
    }

    protected function resolveDependencies(array $dependencies, array $parameters)
    {
        $resolvedDependencies = [];

        foreach ($dependencies as $dependency) {
            $resolvedDependencies[] = $this->resolveClass($dependency, $parameters);
        }

        return $resolvedDependencies;
    }

    protected function resolveClass(ReflectionParameter $parameter, array $parameters)
    {
        if (array_key_exists($parameter->name, $parameters)) {
            return $parameters[$parameter->name];
        }

        $type = $parameter->getType();

        if ($type && !$type->isBuiltIn()) {
            $className = $type->getName();
            return $this->make($className);
        }

        throw new ContainerException("Unresolvable dependency: {$parameter->name}");
    }

    protected function getClosure($concrete)
    {
        return function ($container, $parameters = []) use ($concrete) {
            return $container->build($concrete, $parameters);
        };
    }
}

class ContainerException extends Exception
{
}