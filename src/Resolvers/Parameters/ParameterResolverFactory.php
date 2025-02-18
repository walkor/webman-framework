<?php
declare(strict_types=1);

namespace Webman\Resolvers\Parameters;

final class ParameterResolverFactory
{
    public static function make(string $typeName): ?ParameterResolverInterface
    {
        if ($typeName && enum_exists($typeName)) {
            return new EnumParameterResolver();
        }

        // for simple types
        return match ($typeName) {
            'int', 'float', 'double' => new NumericParameterResolver(),
            'bool' => new BooleanParameterResolver(),
            'array', 'object' => new ComplexObjectParameterResolver(),
            'string', 'mixed', 'resource', 'NULL', 'null' => new PrimitiveParameterResolver(),
            default => throw new \RuntimeException('Unknown type: ' . $typeName),
        };
    }
}