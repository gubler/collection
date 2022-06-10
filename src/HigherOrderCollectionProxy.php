<?php

namespace Gubler\Collection;

class HigherOrderCollectionProxy
{
    /**
     * The collection being operated on.
     */
    protected Collection $collection;

    /**
     * The method being proxied.
     */
    protected string $method;

    public function __construct(Collection $collection, string $method)
    {
        $this->method = $method;
        $this->collection = $collection;
    }

    /**
     * Proxy accessing an attribute onto the collection items.
     */
    public function __get(string $key): mixed
    {
        return $this->collection->{$this->method}(function ($value) use ($key) {
            return is_array($value) ? $value[$key] : $value->{$key};
        });
    }

    /**
     * Proxy a method call onto the collection items.
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->collection->{$this->method}(function ($value) use ($method, $parameters) {
            return $value->{$method}(...$parameters);
        });
    }
}
