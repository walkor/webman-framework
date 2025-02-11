<?php
declare(strict_types=1);

namespace Webman\Resolvers\Parameters;

final class ParameterResolverFactory
{
    public static function make(string $typeName): ?ParameterResolverInterface
    {
        return match ($typeName) {
            'int', 'float', 'double' => new NumericParameterResolver(),
            'bool' => new BooleanParameterResolver(),
            'array', 'object' => new ComplexObjectParameterResolver(),
            'string', 'mixed', 'resource', 'NULL', 'null' => new PrimitiveParameterResolver(),
            'enum' => new EnumParameterResolver(),
            default => null
        };
    }
}