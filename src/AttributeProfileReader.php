<?php

namespace Teoprayoga\TeobiefyLaravelApiResponse;

use Illuminate\Routing\Route;
use ReflectionClass;
use ReflectionException;
use Teoprayoga\TeobiefyLaravelApiResponse\Attributes\RequestProfile;
use Teoprayoga\TeobiefyLaravelApiResponse\Attributes\ResponseProfile;

class AttributeProfileReader
{
    /** @var array<string, ?string> */
    private static array $responseCache = [];

    /** @var array<string, ?string> */
    private static array $requestCache = [];

    public function forResponse(?Route $route): ?string
    {
        return $this->resolve($route, ResponseProfile::class, self::$responseCache);
    }

    public function forRequest(?Route $route): ?string
    {
        return $this->resolve($route, RequestProfile::class, self::$requestCache);
    }

    /**
     * @param  array<string, ?string>  $cache
     */
    private function resolve(?Route $route, string $attributeClass, array &$cache): ?string
    {
        if ($route === null) {
            return null;
        }

        [$class, $method] = $this->controllerOf($route);

        if ($class === null) {
            return null;
        }

        $cacheKey = $attributeClass.'|'.$class.'@'.($method ?? '');

        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        try {
            $reflection = new ReflectionClass($class);
        } catch (ReflectionException) {
            return $cache[$cacheKey] = null;
        }

        if ($method !== null && $reflection->hasMethod($method)) {
            $methodRef = $reflection->getMethod($method);
            $attrs = $methodRef->getAttributes($attributeClass);
            if (! empty($attrs)) {
                /** @var ResponseProfile|RequestProfile $instance */
                $instance = $attrs[0]->newInstance();

                return $cache[$cacheKey] = $instance->name;
            }
        }

        $attrs = $reflection->getAttributes($attributeClass);
        if (! empty($attrs)) {
            /** @var ResponseProfile|RequestProfile $instance */
            $instance = $attrs[0]->newInstance();

            return $cache[$cacheKey] = $instance->name;
        }

        return $cache[$cacheKey] = null;
    }

    /**
     * @return array{0: ?string, 1: ?string}  [class, method] — method is null when not resolvable; class null for Closure routes
     */
    private function controllerOf(Route $route): array
    {
        $action = $route->getAction();
        $uses = $action['uses'] ?? null;

        if (is_string($uses) && str_contains($uses, '@')) {
            [$class, $method] = explode('@', $uses, 2);

            return [$class, $method !== '' ? $method : null];
        }

        if (is_array($uses) && count($uses) >= 2 && is_string($uses[0]) && is_string($uses[1])) {
            return [$uses[0], $uses[1]];
        }

        if (is_string($uses) && $uses !== 'Closure' && class_exists($uses)) {
            return [$uses, '__invoke'];
        }

        return [null, null];
    }

    public static function cacheSize(): int
    {
        return count(self::$responseCache) + count(self::$requestCache);
    }

    public static function flush(): void
    {
        self::$responseCache = [];
        self::$requestCache = [];
    }
}
