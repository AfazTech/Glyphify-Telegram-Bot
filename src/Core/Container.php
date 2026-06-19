<?php
namespace Bot\Core;

class Container
{
    private array $instances = [];
    private array $bindings = [];
    private array $singletons = [];

    public function singleton(string $abstract, \Closure $factory): void
    {
        $this->singletons[$abstract] = $factory;
    }

    public function bind(string $abstract, \Closure $factory): void
    {
        $this->bindings[$abstract] = $factory;
    }

    public function get(string $abstract)
    {
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        if (isset($this->singletons[$abstract])) {
            $instance = $this->singletons[$abstract]($this);
            $this->instances[$abstract] = $instance;
            return $instance;
        }

        if (isset($this->bindings[$abstract])) {
            return $this->bindings[$abstract]($this);
        }

        return $this->resolve($abstract);
    }

    private function resolve(string $class)
    {
        $reflection = new \ReflectionClass($class);
        
        if (!$reflection->isInstantiable()) {
            throw new \Exception("Class {$class} is not instantiable");
        }

        $constructor = $reflection->getConstructor();
        
        if (!$constructor) {
            return $reflection->newInstance();
        }

        $parameters = $constructor->getParameters();
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();
            
            if (!$type || $type->isBuiltin()) {
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                    continue;
                }
                throw new \Exception("Cannot resolve parameter {$parameter->getName()}");
            }

            $dependencies[] = $this->get($type->getName());
        }

        return $reflection->newInstanceArgs($dependencies);
    }

    public function has(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) || 
               isset($this->singletons[$abstract]) || 
               isset($this->instances[$abstract]);
    }
}
