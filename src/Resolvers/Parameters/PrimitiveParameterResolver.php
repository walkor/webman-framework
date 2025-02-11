<?php
declare(strict_types=1);

namespace Webman\Resolvers\Parameters;

final class PrimitiveParameterResolver implements ParameterResolverInterface
{

    public function resolve(
        array  &$parameters,
        mixed  $parameterValue,
        string $parameterName,
        string $typeName,
        ?bool  $debug = false,
    ): void
    {
        $parameters[$parameterName] = $parameterValue;
    }
}